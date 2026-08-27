(function () {
	// Config por tipo de repositorio — todo lo que cambia entre Rebate y
	// Participación de Percha vive acá (columnas, formato, placeholder de
	// búsqueda) para no duplicar la lógica de render/edición en 2 copias.
	var CONFIG = {
		rebate: {
			label: 'Rebate',
			// Ciudad/Canal reemplazan a Segmento (2026-08-27) — el Excel real
			// de JW (datos/RABATE.xlsx) no trae Segmento, pero sí Ciudad y
			// Canal, que cambian el % del mismo Sector+Categoría+Marca (ver
			// CLAUDE.md "Rebate: el Excel real de JW no usa el vocabulario...").
			// Etiquetas "Categoría"/"Subcategoría" (no "Sector"/"Categoría")
			// — mismo criterio que ya se aplicó en Meta de Compras de
			// Registrar: la columna interna `sector`/`categoria` no cambia de
			// nombre, solo el texto visible, para que se lea igual que el
			// Excel real que sube JW (su "Categoría" = nuestro Sector, su
			// "Subcategoría" = nuestra Categoría).
			buscarPlaceholder: 'Buscar por ciudad, canal, categoría, subcategoría o marca...',
			columnas: [
				{ key: 'ciudad', label: 'Ciudad' },
				{ key: 'canal', label: 'Canal' },
				{ key: 'sector', label: 'Categoría' },
				{ key: 'categoria', label: 'Subcategoría' },
				{ key: 'marca', label: 'Marca' },
				{ key: 'rebate_pct', label: 'Rebate %', numero: true, formato: function (v) { return (parseFloat(v) * 100).toFixed(1) + '%'; } }
			]
		},
		participacion: {
			label: 'Participación de Percha',
			buscarPlaceholder: 'Buscar por marca...',
			columnas: [
				{ key: 'marca', label: 'Marca' },
				{ key: 'participacion_pct', label: 'Participación %', numero: true, formato: function (v) { return parseFloat(v).toFixed(1) + '%'; } }
			]
		},
		// Cuotas trimestrales por cliente (2026-08-25, ver CLAUDE.md
		// "Repositorio de Cuotas trimestrales + Actas precargadas") — a
		// diferencia de Rebate/Participación, SÍ tiene cliente y el pos_id se
		// resuelve en el servidor (cuotas_guardar.php), no en el Excel. Por eso
		// tiene 2 juegos de columnas: `columnasPreview` (lo que trae el Excel
		// crudo, antes de guardar) y `columnas` (lo que se ve en la tabla
		// principal ya guardada, con pos_id/período/estado resueltos). Sin
		// edición inline (`editable: false`) — estos datos vienen de un match
		// automático, no de texto libre como Rebate/Participación.
		cuotas: {
			label: 'Cuotas Trimestrales',
			buscarPlaceholder: 'Buscar por cliente, pos_id o categoría...',
			editable: false,
			agruparPor: 'pos_id',
			// mes1/mes2/mes3 (2026-08-25, corregido — la primera versión asumía
			// mal que los 3 meses del trimestre siempre traían el mismo monto):
			// posición dentro del trimestre, no el índice real de mes — el
			// índice real (0-11) se calcula recién en cuotas_guardar.php a
			// partir de `trimestre`. Etiquetados genéricos "Mes 1/2/3" porque acá
			// todavía no se sabe qué trimestre es (recién se conoce al leer
			// data.trimestre de la respuesta de previsualizar, ver
			// previsualizarArchivo() más abajo).
			// Mismo orden que trae el Excel real (CEDI, CLIENTE, PLAN, CATEGORIAS,
			// ...meses) — pedido explícito 2026-08-25, para que la previsualización
			// se lea igual que el archivo original.
			columnasPreview: [
				{ key: 'cedi_excel', label: 'CEDI' },
				{ key: 'cliente_excel', label: 'Cliente' },
				{ key: 'plan', label: 'Plan' },
				{ key: 'sector', label: 'Categoría' },
				{ key: 'mes1', label: 'Mes 1', numero: true, formato: function (v) { return '$' + parseFloat(v).toFixed(2); } },
				{ key: 'mes2', label: 'Mes 2', numero: true, formato: function (v) { return '$' + parseFloat(v).toFixed(2); } },
				{ key: 'mes3', label: 'Mes 3', numero: true, formato: function (v) { return '$' + parseFloat(v).toFixed(2); } }
			],
			// Mismo orden y mismas columnas "de origen" que columnasPreview
			// (CEDI, Cliente, Plan, Categoría) — pedido explícito 2026-08-25,
			// el usuario esperaba poder comparar la previsualización contra la
			// tabla ya guardada sin que las columnas cambien de golpe. Pos ID se
			// sigue resolviendo y guardando igual, solo se dejó de mostrar acá
			// (pedido explícito) — sigue disponible en `fila.pos_id` para
			// agruparPor y para Fase 2. Período va antes de los 3 meses
			// independientes, Estado al final (pedido explícito).
			columnas: [
				{ key: 'cedi_excel', label: 'CEDI' },
				{ key: 'cliente_excel', label: 'Cliente' },
				{ key: 'plan', label: 'Plan' },
				{ key: 'sector', label: 'Categoría' },
				{
					key: 'periodo', label: 'Período',
					render: function (fila) { return 'Q' + fila.trimestre + ' ' + fila.anio; }
				},
				{
					key: 'mes1', label: 'Mes 1', numero: true,
					render: function (fila) { return mesMensualPorPosicion(fila.valores_mensuales, 0); }
				},
				{
					key: 'mes2', label: 'Mes 2', numero: true,
					render: function (fila) { return mesMensualPorPosicion(fila.valores_mensuales, 1); }
				},
				{
					key: 'mes3', label: 'Mes 3', numero: true,
					render: function (fila) { return mesMensualPorPosicion(fila.valores_mensuales, 2); }
				},
				{
					key: 'estado', label: 'Estado',
					render: function (fila) {
						if (fila.estado === 'usada') return '<span class="ac-badge ac-badge-ok">Usada</span>';
						if (fila.estado === 'descartada') return '<span class="ac-field-hint">Descartada</span>';
						return '<span class="ac-badge ac-badge-revisar">Pendiente de uso</span>';
					}
				}
			]
		}
	};

	var tipoActivo = 'rebate';
	var paginaActual = 1;
	var busquedaActual = '';
	var buscarTimeout = null;
	var filasPreview = null; // filas leídas del Excel, en edición dentro del modal
	var trimestrePreview = null; // solo cuotas: inferido del propio Excel por repositorio_parsear_cuotas()
	var estadosPreview = null; // solo cuotas: nuevo/actualiza/usada/sin_cliente por fila, ver verificarEstadosPreview()

	var tablaHead = document.getElementById('repo-tabla-head');
	var tablaBody = document.getElementById('repo-tabla-body');
	// Paginación arriba Y abajo (2026-08-25) — ambos pares se pintan siempre
	// juntos, ver renderPaginacion() más abajo.
	var paginacionInfoEls = [document.getElementById('repo-paginacion-info-top'), document.getElementById('repo-paginacion-info')];
	var paginacionBtnsEls = [document.getElementById('repo-paginacion-btns-top'), document.getElementById('repo-paginacion-btns')];
	var buscarInput = document.getElementById('repo-buscar');
	var exportarWrap = document.getElementById('repo-exportar-wrap');
	var exportarBtn = document.getElementById('repo-exportar-btn');
	var exportarCsvLink = document.getElementById('repo-exportar-csv');
	var exportarXlsxLink = document.getElementById('repo-exportar-xlsx');
	var tabRebate = document.getElementById('repo-tab-rebate');
	var tabParticipacion = document.getElementById('repo-tab-participacion');
	var tabCuotas = document.getElementById('repo-tab-cuotas');
	var pendientesAbrirBtn = document.getElementById('repo-pendientes-abrir');
	var pendientesCount = document.getElementById('repo-pendientes-count');
	var resumenAbrirBtn = document.getElementById('repo-resumen-abrir');
	var eliminadosAbrirBtn = document.getElementById('repo-eliminados-abrir');

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.textContent = str == null ? '' : String(str);
		return div.innerHTML;
	}

	function mostrarMensaje(texto, ok) {
		mostrarToast(texto, ok ? 'success' : 'error');
	}

	// "{"3": 600, "4": 650, "5": 700}" -> "$600.00 / $650.00 / $700.00" —
	// mismo formato JSON que repositorio_acuerdo_lineas.valores_mensuales,
	// ordenado por índice de mes (las claves de un objeto no garantizan
	// orden numérico en JS si vinieran como texto "10" antes que "3").
	function montosMensualesTexto(valoresMensuales) {
		var meses = Object.keys(valoresMensuales || {}).sort(function (a, b) { return parseInt(a, 10) - parseInt(b, 10); });
		if (!meses.length) return '—';
		return meses.map(function (m) { return '$' + parseFloat(valoresMensuales[m]).toFixed(2); }).join(' / ');
	}

	// Valor de un mes puntual por posición (0=primero, 1=segundo, 2=tercero)
	// dentro del trimestre — para las 3 columnas independientes de la tabla
	// principal de Cuotas (2026-08-25, pedido explícito: separar en vez de un
	// solo texto "$a / $b / $c").
	function mesMensualPorPosicion(valoresMensuales, posicion) {
		var meses = Object.keys(valoresMensuales || {}).sort(function (a, b) { return parseInt(a, 10) - parseInt(b, 10); });
		var clave = meses[posicion];
		return clave !== undefined ? '$' + parseFloat(valoresMensuales[clave]).toFixed(2) : '—';
	}

	// ---------- Tabla principal ----------
	function renderCabecera() {
		var cols = CONFIG[tipoActivo].columnas;
		var html = '<tr>';
		cols.forEach(function (c) {
			html += '<th' + (c.numero ? ' class="ac-text-right"' : '') + '>' + escapeHtml(c.label) + '</th>';
		});
		html += '<th class="ac-text-right">Acciones</th></tr>';
		tablaHead.innerHTML = html;
	}

	function celdaValor(col, fila) {
		if (col.render) return col.render(fila);
		var v = fila[col.key];
		if (col.numero) return '<span class="ac-repo-badge">' + escapeHtml(col.formato(v)) + '</span>';
		return escapeHtml(v);
	}

	function renderFilas(filas) {
		var cols = CONFIG[tipoActivo].columnas;
		var editable = CONFIG[tipoActivo].editable !== false;
		var agruparPor = CONFIG[tipoActivo].agruparPor; // ej. 'pos_id' en Cuotas — varias filas seguidas son el mismo cliente
		if (!filas.length) {
			tablaBody.innerHTML = '<tr><td colspan="' + (cols.length + 1) + '" class="ac-table-empty">Sin registros.</td></tr>';
			return;
		}
		// Fondo alternado por GRUPO (no por fila) cuando hay agruparPor — así
		// se distingue de un vistazo dónde termina un cliente y empieza el
		// siguiente (2026-08-25, pedido explícito tras ver Cuotas con 5 filas
		// seguidas del mismo cliente sin ninguna separación visual). Se
		// mantiene el texto completo en cada fila (a diferencia de un rowspan
		// que lo ocultaría) para que la vista mobile en tarjetas siga
		// mostrando el cliente en cada una, sin quedar una tarjeta "vacía".
		var grupoAnterior = null;
		var grupoPar = false;
		tablaBody.innerHTML = filas.map(function (fila) {
			if (agruparPor) {
				var claveGrupo = fila[agruparPor];
				if (claveGrupo !== grupoAnterior) { grupoPar = !grupoPar; grupoAnterior = claveGrupo; }
			}
			// data-key/data-label (2026-08-24): Rebate y Participación de Percha
			// tienen distinta cantidad de columnas (5 vs 2) — la vista mobile
			// (ver style.css, tarjetas por fila) arma el layout por estos
			// atributos en vez de nth-child, para no depender de una posición
			// fija de columna que solo calza con uno de los 2 tipos.
			var tds = cols.map(function (c) {
				return '<td' + (c.numero ? ' class="ac-text-right"' : '') + ' data-key="' + c.key + '" data-label="' + escapeHtml(c.label) + '">' + celdaValor(c, fila) + '</td>';
			}).join('');
			// Cuotas descartada (2026-08-25, borrado lógico): "Eliminar" se
			// reemplaza por "Reactivar" — no tiene sentido "descartar de nuevo"
			// algo que ya está descartado, y sí tiene sentido poder deshacerlo.
			var accionesHtml = (fila.estado === 'descartada')
				? '<button type="button" class="ac-icon-btn ac-icon-btn-success ac-repo-reactivar" title="Reactivar"><span class="material-symbols-outlined">restore</span><span class="ac-btn-text">Reactivar</span></button>'
				: (editable ? '<button type="button" class="ac-icon-btn ac-repo-editar" title="Editar"><span class="material-symbols-outlined">edit</span><span class="ac-btn-text">Editar</span></button>' : '') +
				  '<button type="button" class="ac-icon-btn ac-icon-btn-danger ac-repo-eliminar" title="' + (tipoActivo === 'cuotas' ? 'Descartar' : 'Eliminar') + '"><span class="material-symbols-outlined">delete</span><span class="ac-btn-text">' + (tipoActivo === 'cuotas' ? 'Descartar' : 'Eliminar') + '</span></button>';
			return '<tr data-id="' + fila.id + '"' + (agruparPor && grupoPar ? ' class="ac-repo-fila-grupo-par"' : '') + '>' + tds +
				'<td class="ac-text-right" data-key="acciones"><div class="ac-row-actions">' + accionesHtml + '</div></td></tr>';
		}).join('');

		Array.prototype.forEach.call(tablaBody.querySelectorAll('.ac-repo-eliminar'), function (btn) {
			btn.addEventListener('click', function () {
				var tr = btn.closest('tr');
				confirmarYEliminar(parseInt(tr.dataset.id, 10));
			});
		});
		Array.prototype.forEach.call(tablaBody.querySelectorAll('.ac-repo-editar'), function (btn) {
			btn.addEventListener('click', function () {
				var tr = btn.closest('tr');
				var fila = filas.filter(function (f) { return f.id === parseInt(tr.dataset.id, 10); })[0];
				if (fila) activarEdicionFila(tr, fila);
			});
		});
		Array.prototype.forEach.call(tablaBody.querySelectorAll('.ac-repo-reactivar'), function (btn) {
			btn.addEventListener('click', function () {
				var tr = btn.closest('tr');
				var params = new URLSearchParams({ id: tr.dataset.id });
				fetch('getters/cuotas_reactivar.php', { method: 'POST', body: params })
					.then(function (r) { return r.json(); })
					.then(function (data) {
						mostrarMensaje(data.message, data.ok);
						if (data.ok) cargarLista();
					})
					.catch(function () { mostrarMensaje('Error de conexión.', false); });
			});
		});
	}

	// Convierte una fila de la tabla en inputs editables in-place (mismo
	// componente visual .ac-preview-input que usa el modal de subida) — sin
	// abrir un modal aparte para un cambio puntual de 1-2 campos.
	function activarEdicionFila(tr, fila) {
		var cols = CONFIG[tipoActivo].columnas;
		var tds = tr.querySelectorAll('td');
		cols.forEach(function (c, i) {
			var valorCrudo = c.numero ? c.formato(fila[c.key]).replace('%', '') : fila[c.key];
			tds[i].innerHTML = '<input type="text" class="ac-preview-input" data-key="' + c.key + '" value="' + escapeHtml(valorCrudo) + '">';
		});
		tds[cols.length].innerHTML =
			'<div class="ac-row-actions">' +
			'<button type="button" class="ac-icon-btn ac-icon-btn-success ac-repo-guardar-fila" title="Guardar"><span class="material-symbols-outlined">check</span></button>' +
			'<button type="button" class="ac-icon-btn ac-repo-cancelar-fila" title="Cancelar"><span class="material-symbols-outlined">close</span></button>' +
			'</div>';

		tds[cols.length].querySelector('.ac-repo-cancelar-fila').addEventListener('click', function () { cargarLista(); });
		tds[cols.length].querySelector('.ac-repo-guardar-fila').addEventListener('click', function () {
			var filaEditada = { id: fila.id };
			cols.forEach(function (c) {
				var input = tr.querySelector('input[data-key="' + c.key + '"]');
				var valor = input.value;
				filaEditada[c.key] = c.numero ? (parseFloat(valor) || 0) / (c.key === 'rebate_pct' ? 100 : 1) : valor;
			});
			guardarFilas([filaEditada], function () { cargarLista(); });
		});
	}

	function confirmarYEliminar(id) {
		var esCuotas = tipoActivo === 'cuotas';
		Swal.fire({
			icon: 'warning',
			title: esCuotas ? '¿Descartar esta categoría?' : '¿Eliminar registro?',
			text: esCuotas ? 'Se puede reactivar después desde la misma tabla.' : 'Esta acción no se puede deshacer.',
			showCancelButton: true,
			confirmButtonText: esCuotas ? 'Sí, descartar' : 'Sí, eliminar',
			cancelButtonText: 'Cancelar',
			confirmButtonColor: '#ba1a1a'
		}).then(function (resultado) {
			if (!resultado.isConfirmed) return;
			fetch('getters/repositorio_eliminar.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ tipo: tipoActivo, id: id })
			})
				.then(function (r) { return r.json(); })
				.then(function (data) {
					mostrarMensaje(data.message, data.ok);
					if (data.ok) cargarLista();
				})
				.catch(function () { mostrarMensaje('Error de conexión.', false); });
		});
	}

	// Pinta los botones en AMBOS contenedores (arriba y abajo) — mismo HTML,
	// cada uno con sus propios listeners, para que cambiar de página funcione
	// igual sin importar cuál de los 2 el usuario tenga a la vista.
	function renderPaginacion(pagina, totalPaginas) {
		var html = '';
		html += '<button type="button" class="ac-page-btn" data-pg="' + (pagina - 1) + '" ' + (pagina <= 1 ? 'disabled' : '') + '>' +
			'<span class="material-symbols-outlined">chevron_left</span></button>';
		for (var i = 1; i <= totalPaginas; i++) {
			html += '<button type="button" class="ac-page-btn' + (i === pagina ? ' ac-page-btn-active' : '') + '" data-pg="' + i + '">' + i + '</button>';
		}
		html += '<button type="button" class="ac-page-btn" data-pg="' + (pagina + 1) + '" ' + (pagina >= totalPaginas ? 'disabled' : '') + '>' +
			'<span class="material-symbols-outlined">chevron_right</span></button>';

		paginacionBtnsEls.forEach(function (contenedor) {
			if (!contenedor) return;
			contenedor.innerHTML = html;
			Array.prototype.forEach.call(contenedor.querySelectorAll('.ac-page-btn'), function (btn) {
				btn.addEventListener('click', function () {
					var pg = parseInt(btn.dataset.pg, 10);
					if (pg < 1 || pg > totalPaginas) return;
					paginaActual = pg;
					cargarLista();
				});
			});
		});
	}

	function cargarLista() {
		renderCabecera();
		tablaBody.innerHTML = '<tr><td class="ac-table-empty">Cargando...</td></tr>';
		var params = new URLSearchParams({ tipo: tipoActivo, q: busquedaActual, pg: paginaActual });
		fetch('getters/repositorio_listar.php?' + params.toString())
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) { mostrarMensaje(data.message || 'No se pudo cargar el repositorio.', false); return; }
				renderFilas(data.filas);
				var infoHtml = 'Mostrando <strong>' + data.filas.length + '</strong> de <strong>' + data.total + '</strong> registros';
				paginacionInfoEls.forEach(function (el) { if (el) el.innerHTML = infoHtml; });
				renderPaginacion(data.pagina, data.total_paginas);
				var contador = document.getElementById('repo-tab-' + tipoActivo + '-count');
				if (contador) contador.textContent = data.total;
			})
			.catch(function () { mostrarMensaje('Error de conexión al cargar el repositorio.', false); });
	}

	// ---------- Exportar (CSV/Excel) ----------
	function actualizarHrefsExportar() {
		var base = 'getters/repositorio_exportar.php?tipo=' + tipoActivo + '&q=' + encodeURIComponent(busquedaActual) + '&formato=';
		exportarCsvLink.href = base + 'csv';
		exportarXlsxLink.href = base + 'xlsx';
	}

	// ---------- Tabs ----------
	function activarTab(tipo) {
		tipoActivo = tipo;
		paginaActual = 1;
		busquedaActual = '';
		buscarInput.value = '';
		buscarInput.placeholder = CONFIG[tipo].buscarPlaceholder;
		tabRebate.classList.toggle('active', tipo === 'rebate');
		tabParticipacion.classList.toggle('active', tipo === 'participacion');
		tabCuotas.classList.toggle('active', tipo === 'cuotas');
		// pendientesAbrirBtn: oculto a propósito (2026-08-26, pedido explícito
		// "quita el botón de Pendientes de Asignar") — se deja el resto del
		// mecanismo intacto (getters, modal), por si se retoma después.
		resumenAbrirBtn.classList.toggle('hidden', tipo !== 'cuotas');
		// "Eliminados" (borrado lógico, 2026-08-25): Rebate/Participación solo
		// — Cuotas ya tiene su propio mecanismo (estado='descartada'), no se
		// duplica el botón acá.
		eliminadosAbrirBtn.classList.toggle('hidden', tipo === 'cuotas');
		if (tipo === 'cuotas') actualizarContadorPendientes();
		actualizarHrefsExportar();
		cargarLista();
	}
	tabRebate.addEventListener('click', function () { activarTab('rebate'); });
	tabParticipacion.addEventListener('click', function () { activarTab('participacion'); });
	tabCuotas.addEventListener('click', function () { activarTab('cuotas'); });

	// ---------- Búsqueda ----------
	buscarInput.addEventListener('input', function () {
		clearTimeout(buscarTimeout);
		buscarTimeout = setTimeout(function () {
			busquedaActual = buscarInput.value.trim();
			paginaActual = 1;
			actualizarHrefsExportar();
			cargarLista();
		}, 350);
	});

	// ---------- Modal "Subir Archivo" ----------
	var subirOverlay = document.getElementById('repo-subir-modal-overlay');
	var subirModal = subirOverlay.querySelector('.ac-repo-subir-modal');
	var subirTitulo = document.getElementById('repo-subir-modal-titulo');
	var pasoElegir = document.getElementById('repo-subir-paso-elegir');
	var pasoPreview = document.getElementById('repo-subir-paso-preview');
	var footerElegir = document.getElementById('repo-subir-footer-elegir');
	var footerPreview = document.getElementById('repo-subir-footer-preview');
	var dropzone = document.getElementById('repo-dropzone');
	var archivoInput = document.getElementById('repo-archivo-input');
	var progresoCarga = document.getElementById('repo-subir-progreso');
	var progresoCargaFill = document.getElementById('repo-subir-progreso-fill');
	var progresoCargaTexto = document.getElementById('repo-subir-progreso-texto');
	var previewNombreArchivo = document.getElementById('repo-preview-nombre-archivo');
	var previewCantidad = document.getElementById('repo-preview-cantidad');
	var previewTablaHead = document.getElementById('repo-preview-tabla-head');
	var previewTablaBody = document.getElementById('repo-preview-tabla-body');
	var previewErrores = document.getElementById('repo-preview-errores');
	var previewAnioWrap = document.getElementById('repo-preview-anio-wrap');
	var previewAnioInput = document.getElementById('repo-preview-anio');

	// Arrastre horizontal con mouse, tipo touch (2026-08-25, pedido explícito:
	// "que pueda con el mouse mover la tabla sosteniendo y moviendo el mouse"
	// — con el ancho auto-ajustado la tabla de previsualización puede quedar
	// más ancha que el modal, y el scrollbar nativo del navegador solo se ve
	// pegado abajo del todo, no arriba). Mantener click y arrastrar mueve el
	// contenido, sin depender de encontrar el scrollbar. Se excluye el
	// arrastre si el click empezó en un input/botón/link — si no, no se
	// podría hacer foco normal para editar una celda.
	function activarArrastreScroll(contenedor) {
		if (!contenedor) return;
		var arrastrando = false;
		var startX = 0;
		var startScroll = 0;
		contenedor.addEventListener('mousedown', function (e) {
			if (e.target.closest('input, button, a, select, textarea')) return;
			arrastrando = true;
			contenedor.classList.add('ac-arrastrando');
			startX = e.pageX;
			startScroll = contenedor.scrollLeft;
		});
		document.addEventListener('mouseup', function () {
			arrastrando = false;
			contenedor.classList.remove('ac-arrastrando');
		});
		contenedor.addEventListener('mouseleave', function () {
			arrastrando = false;
			contenedor.classList.remove('ac-arrastrando');
		});
		contenedor.addEventListener('mousemove', function (e) {
			if (!arrastrando) return;
			e.preventDefault();
			contenedor.scrollLeft = startScroll - (e.pageX - startX);
		});
	}
	activarArrastreScroll(document.querySelector('.ac-preview-table-scroll'));

	// Columnas a usar en la previsualización — para Cuotas es un juego
	// distinto al de la tabla principal (ver comentario de CONFIG.cuotas más
	// arriba); para Rebate/Participación es el mismo de siempre.
	function columnasPreview() {
		return CONFIG[tipoActivo].columnasPreview || CONFIG[tipoActivo].columnas;
	}

	function ocultarErroresPreview() {
		previewErrores.classList.add('hidden');
		previewErrores.innerHTML = '';
	}


	// Muestra el detalle post-guardado (2026-08-24, pedido explícito: "que el
	// sistema pueda defenderse solo, sin estar yo detrás de él") — qué fila
	// NO se guardó y por qué (errores) y qué fila SÍ se guardó pero conviene
	// revisar (avisos, ej. un producto repetido en el mismo archivo). No toca
	// la tabla en sí (sin bordes rojos por celda), es una caja de resumen
	// aparte arriba de la tabla.
	function mostrarErroresPreview(errores, avisos) {
		errores = errores || [];
		avisos = avisos || [];
		if (!errores.length && !avisos.length) { ocultarErroresPreview(); return; }
		var html = '';
		if (errores.length) {
			html += '<p>' + errores.length + ' fila(s) NO se guardaron:</p><ul>' +
				errores.map(function (e) { return '<li>' + escapeHtml(e.fila) + ': ' + escapeHtml(e.motivo) + '</li>'; }).join('') +
				'</ul>';
		}
		if (avisos.length) {
			html += '<p' + (errores.length ? ' style="margin-top:8px;"' : '') + '>' + avisos.length + ' fila(s) se guardaron, pero revisá:</p><ul>' +
				avisos.map(function (a) { return '<li>' + escapeHtml(a.fila) + ': ' + escapeHtml(a.motivo) + '</li>'; }).join('') +
				'</ul>';
		}
		previewErrores.innerHTML = html;
		previewErrores.classList.remove('hidden');
	}

	function abrirModalSubir() {
		subirTitulo.textContent = 'Subir Archivo — ' + CONFIG[tipoActivo].label;
		mostrarPasoElegir();
		archivoInput.value = '';
		subirOverlay.classList.add('ac-modal-open');
	}
	function cerrarModalSubir() {
		subirOverlay.classList.remove('ac-modal-open');
	}
	function mostrarPasoElegir() {
		pasoElegir.classList.remove('hidden');
		pasoPreview.classList.add('hidden');
		footerElegir.classList.remove('hidden');
		footerPreview.classList.add('hidden');
		subirModal.classList.remove('ac-repo-subir-modal-ancho');
		ocultarProgresoCarga(); // por si se reabre el modal a mitad de una subida anterior
		filasPreview = null;
		trimestrePreview = null;
		estadosPreview = null;
		previewAnioWrap.classList.add('hidden');
		ocultarErroresPreview();
	}
	function mostrarPasoPreview() {
		pasoElegir.classList.add('hidden');
		pasoPreview.classList.remove('hidden');
		footerElegir.classList.add('hidden');
		footerPreview.classList.remove('hidden');
		subirModal.classList.add('ac-repo-subir-modal-ancho');
		ocultarErroresPreview();
	}

	document.getElementById('repo-subir-abrir').addEventListener('click', abrirModalSubir);
	document.getElementById('repo-subir-modal-close').addEventListener('click', cerrarModalSubir);
	document.getElementById('repo-subir-cancelar').addEventListener('click', cerrarModalSubir);
	document.getElementById('repo-subir-atras').addEventListener('click', mostrarPasoElegir);
	// Sin cierre por click afuera (2026-08-25, pedido explícito: "se me
	// cierra esta ventanita por clicks accidentales afuera") — el arrastre
	// con mouse de la tabla de previsualización (activarArrastreScroll())
	// mueve el cursor bastante, y si el mouseup termina cayendo justo sobre
	// el fondo oscuro del overlay, el `click` nativo podía disparar acá y
	// cerrar el modal perdiendo lo que el usuario ya había corregido. Cerrar
	// sigue disponible por la "X" (`repo-subir-modal-close`) y "Cancelar".

	dropzone.addEventListener('click', function () { archivoInput.click(); });
	dropzone.addEventListener('dragover', function (e) { e.preventDefault(); dropzone.classList.add('ac-dropzone-hover'); });
	dropzone.addEventListener('dragleave', function () { dropzone.classList.remove('ac-dropzone-hover'); });
	dropzone.addEventListener('drop', function (e) {
		e.preventDefault();
		dropzone.classList.remove('ac-dropzone-hover');
		if (e.dataTransfer.files.length) previsualizarArchivo(e.dataTransfer.files[0]);
	});
	archivoInput.addEventListener('change', function () {
		if (archivoInput.files.length) previsualizarArchivo(archivoInput.files[0]);
	});

	// Paso 1 -> 2: sube el archivo a repositorio_previsualizar_excel.php, que
	// SOLO lo parsea (no toca la base) y devuelve las filas leídas — ver
	// comentario de cabecera en ese getter. Sin límite de tamaño propio acá
	// (pedido explícito 2026-08-24: "no limites la subida") — vía XHR en vez
	// de fetch() porque fetch() no expone progreso de subida, y con un
	// archivo pesado el pedido fue justamente mostrar una barra de carga
	// real, no dejar la ventana "trabada" sin feedback.
	function previsualizarArchivo(archivo) {
		var formData = new FormData();
		formData.append('tipo', tipoActivo);
		formData.append('archivo', archivo);

		mostrarProgresoCarga();
		var xhr = new XMLHttpRequest();
		var url = tipoActivo === 'cuotas' ? 'getters/cuotas_previsualizar_excel.php' : 'getters/repositorio_previsualizar_excel.php';
		xhr.open('POST', url);
		xhr.upload.addEventListener('progress', function (e) {
			if (!e.lengthComputable) return;
			var pct = Math.round((e.loaded / e.total) * 100);
			progresoCargaFill.style.width = pct + '%';
			progresoCargaTexto.textContent = 'Subiendo… ' + pct + '%';
		});
		xhr.addEventListener('load', function () {
			ocultarProgresoCarga();
			var data;
			try { data = JSON.parse(xhr.responseText); } catch (err) {
				mostrarMensaje('Respuesta inválida del servidor al leer el archivo.', false);
				return;
			}
			if (!data.ok) { mostrarMensaje(data.message, false); return; }
			filasPreview = data.filas;
			trimestrePreview = data.trimestre || null;
			previewNombreArchivo.textContent = data.nombre_archivo;
			previewCantidad.textContent = data.filas.length + ' fila(s) detectada(s)' + (trimestrePreview ? ' — Q' + trimestrePreview : '');
			estadosPreview = null;
			if (tipoActivo === 'cuotas') {
				previewAnioInput.value = new Date().getFullYear();
				previewAnioWrap.classList.remove('hidden');
			} else {
				previewAnioWrap.classList.add('hidden');
			}
			renderPreviewTabla();
			mostrarPasoPreview();
			if (data.avisos && data.avisos.length) mostrarErroresPreview([], data.avisos);
			verificarEstadosPreview(); // año por default ya está puesto, resuelve solo
		});
		xhr.addEventListener('error', function () {
			ocultarProgresoCarga();
			mostrarMensaje('Error de conexión al leer el archivo.', false);
		});
		xhr.send(formData);
	}

	function mostrarProgresoCarga() {
		dropzone.classList.add('hidden');
		progresoCargaFill.style.width = '0%';
		progresoCargaTexto.textContent = 'Subiendo…';
		progresoCarga.classList.remove('hidden');
	}
	function ocultarProgresoCarga() {
		progresoCarga.classList.add('hidden');
		dropzone.classList.remove('hidden');
	}

	// Ancho "inteligente" de verdad (2026-08-25, corregido tras feedback: los
	// % fijos de `anchoPct` eran una adivinanza a mano que no se adaptaba a
	// cuántas columnas trajera el Excel esa vez — se ve mal apenas la
	// cantidad/el contenido real no calza con lo que se supuso). Ahora la
	// tabla usa `table-layout: auto` (default del navegador, sin forzar
	// nada) y cada <input> lleva el atributo HTML `size` (NO `width` de CSS)
	// calculado del largo real de SU valor — el motor de layout de tablas ya
	// sabe medir el ancho natural de cada input según ese `size` y ensancha
	// la columna entera a la celda más ancha (incluido el <th>, que se mide
	// solo, sin tocar nada acá) — exactamente lo mismo que hace Excel al
	// autoajustar una columna, sin tener que adivinar porcentajes.
	function tamanoInput(valor) {
		var largo = String(valor == null ? '' : valor).length;
		return Math.max(4, Math.min(40, largo + 1));
	}

	// Badge "Nuevo"/"Actualiza"/"Ya usada"/"Cliente sin identificar" por fila
	// de la previsualización de Cuotas (2026-08-25, pedido explícito: "no
	// quiero enterarme recién después de guardar qué modifiqué" — se resuelve
	// ANTES de confirmar, no después). null mientras no se corrió la consulta
	// todavía (ej. recién subido el archivo, antes de que el Año esté listo).
	function badgeEstadoPreview(estado) {
		if (!estado) return '<span class="ac-field-hint">…</span>';
		var html;
		if (estado.estado === 'nuevo') html = '<span class="ac-badge ac-badge-ok">Nuevo</span>';
		else if (estado.estado === 'actualiza') html = '<span class="ac-badge ac-badge-revisar">Actualiza</span>';
		else if (estado.estado === 'usada') html = '<span class="ac-badge ac-badge-urgente">Ya usada — no se puede modificar</span>';
		else if (estado.estado === 'sin_cliente') html = '<span class="ac-field-hint">Cliente sin identificar</span>';
		else html = '<span class="ac-field-hint">—</span>';
		// Nota de interpretación de Categoría (2026-08-25, pedido explícito: no
		// enterarse recién en el aviso rojo de después de guardar) — mismo
		// dato que ya avisa cuotas_guardar.php, mostrado ACÁ antes.
		if (estado.sector_interpretado) {
			html += '<br><span class="ac-field-hint">Se interpreta como Sector "' + escapeHtml(estado.sector_resuelto) + '"</span>';
		} else if (estado.sector_sin_resolver) {
			html += '<br><span class="ac-field-hint">Categoría no coincide con el catálogo — se guarda tal cual</span>';
		}
		return html;
	}

	function renderPreviewTabla() {
		var cols = columnasPreview();
		var conEstado = tipoActivo === 'cuotas';
		previewTablaHead.innerHTML = '<tr>' + cols.map(function (c) {
			return '<th>' + escapeHtml(c.label) + '</th>';
		}).join('') + (conEstado ? '<th>Al guardar</th>' : '') + '</tr>';
		previewTablaBody.innerHTML = filasPreview.map(function (fila, i) {
			var tds = cols.map(function (c) {
				var valor = c.numero ? (parseFloat(fila[c.key]) * (c.key === 'rebate_pct' ? 100 : 1)).toString() : fila[c.key];
				return '<td><input type="text" class="ac-preview-input" data-key="' + c.key + '" size="' + tamanoInput(valor) + '" value="' + escapeHtml(valor) + '"></td>';
			}).join('');
			var estadoFila = conEstado && estadosPreview ? estadosPreview[i] : null;
			if (conEstado) tds += '<td>' + badgeEstadoPreview(estadoFila) + '</td>';
			var claseFila = estadoFila ? claseFilaEstado(estadoFila.estado) : '';
			return '<tr data-i="' + i + '"' + (claseFila ? ' class="' + claseFila + '"' : '') + '>' + tds + '</tr>';
		}).join('');
	}

	// Fila entera pintada según el estado (2026-08-25, pedido explícito: "que
	// se pinten las filas también", no solo el badge chico de la última
	// columna) — mismo criterio de color que badgeEstadoPreview().
	function claseFilaEstado(estado) {
		if (estado === 'nuevo') return 'ac-preview-fila-nueva';
		if (estado === 'actualiza') return 'ac-preview-fila-actualiza';
		if (estado === 'usada') return 'ac-preview-fila-usada';
		return '';
	}

	// Se llama al terminar de subir (con el año por default, hoy) y cada vez
	// que el superdesarrollador cambia el Año — resuelve cliente/sector de
	// SOLO LECTURA (nunca escribe) contra la base real para decidir si cada
	// fila sería nueva, actualizaría algo que ya existe, o ya no se puede
	// tocar (ya usada).
	function verificarEstadosPreview() {
		if (tipoActivo !== 'cuotas' || !filasPreview || !trimestrePreview) return;
		var anio = parseInt(previewAnioInput.value, 10);
		if (!anio) return;
		fetch('getters/cuotas_verificar_estado.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ filas: filasPreview, trimestre: trimestrePreview, anio: anio })
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) return; // silencioso — no bloquea la previsualización, es solo una ayuda visual
				estadosPreview = data.estados;
				renderPreviewTabla();
			})
			.catch(function () { /* silencioso, ver comentario de arriba */ });
	}
	var verificarEstadosTimeout = null;
	previewAnioInput.addEventListener('input', function () {
		clearTimeout(verificarEstadosTimeout);
		verificarEstadosTimeout = setTimeout(verificarEstadosPreview, 400);
	});

	// Lee los valores actuales de los inputs (el usuario puede haber
	// corregido cualquier celda) antes de guardar — nunca se guarda el dato
	// crudo tal como vino del Excel si se editó en pantalla.
	function leerFilasPreviewEditadas() {
		var cols = columnasPreview();
		return Array.prototype.map.call(previewTablaBody.querySelectorAll('tr'), function (tr) {
			var fila = {};
			cols.forEach(function (c) {
				var input = tr.querySelector('input[data-key="' + c.key + '"]');
				var valor = input.value;
				fila[c.key] = c.numero ? (parseFloat(valor) || 0) / (c.key === 'rebate_pct' ? 100 : 1) : valor;
			});
			return fila;
		});
	}

	// onDone recibe la respuesta COMPLETA (no solo "ok") — quien llama decide
	// qué hacer con data.errores (detalle por fila, ver repositorio_guardar.php),
	// porque el modal de subida necesita quedarse abierto si hubo problemas y
	// la edición inline de una fila suelta no.
	function guardarFilas(filas, onDone) {
		ponerGuardarCargando(true);
		fetch('getters/repositorio_guardar.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ tipo: tipoActivo, filas: filas })
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				mostrarMensaje(data.message, data.ok);
				if (onDone) onDone(data);
			})
			.catch(function () { ponerGuardarCargando(false); mostrarMensaje('Error de conexión al guardar.', false); });
	}

	// Guarda el paso 2 de la subida de Cuotas — endpoint y payload distintos
	// a Rebate/Participación (getters/cuotas_guardar.php espera
	// {filas, trimestre, anio}, no {tipo, filas}), y el año lo tipeó el
	// usuario a mano (el Excel no lo trae, ver previsualizarArchivo()).
	function guardarCuotas(onDone) {
		var anio = parseInt(previewAnioInput.value, 10);
		var anioActual = new Date().getFullYear();
		if (!anio || anio < anioActual - 1 || anio > anioActual + 1) {
			mostrarMensaje('Elegí un año válido.', false);
			return;
		}
		var filas = leerFilasPreviewEditadas();
		ponerGuardarCargando(true);
		fetch('getters/cuotas_guardar.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ filas: filas, trimestre: trimestrePreview, anio: anio })
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				mostrarMensaje(data.message, data.ok);
				if (onDone) onDone(data);
			})
			.catch(function () { ponerGuardarCargando(false); mostrarMensaje('Error de conexión al guardar.', false); });
	}

	var subirGuardarBtn = document.getElementById('repo-subir-guardar');
	var subirGuardarHtmlOriginal = subirGuardarBtn.innerHTML;
	function ponerGuardarCargando(cargando) {
		subirGuardarBtn.classList.toggle('ac-btn-cargando', cargando);
		subirGuardarBtn.innerHTML = cargando
			? '<span class="material-symbols-outlined">progress_activity</span>Guardando…'
			: subirGuardarHtmlOriginal;
	}

	subirGuardarBtn.addEventListener('click', function () {
		var onDone = function (data) {
			ponerGuardarCargando(false);
			if (!data.ok) return;
			cargarLista(); // lo que sí se guardó ya debe verse en la tabla de atrás
			if (tipoActivo === 'cuotas') actualizarContadorPendientes();
			var hayAlgoQueRevisar = (data.errores && data.errores.length) || (data.avisos && data.avisos.length);
			// "Nuevo vs. actualizado" se resuelve ANTES de confirmar, con los
			// badges por fila (ver verificarEstadosPreview()) — no hace falta
			// además retener el modal después de guardar, así que Cuotas se
			// comporta igual que Rebate/Participación acá: se cierra solo si
			// no hay nada que revisar, el toast (con el detalle real de
			// nuevas/actualizadas/sin_cambios) alcanza como confirmación.
			if (hayAlgoQueRevisar) {
				// Se queda en el modal para que el usuario vea qué pasó (no
				// guardado, o guardado con aviso) y pueda corregir sin perder
				// el resto del archivo.
				mostrarErroresPreview(data.errores, data.avisos);
			} else {
				cerrarModalSubir();
			}
		};
		if (tipoActivo === 'cuotas') {
			guardarCuotas(onDone);
		} else {
			guardarFilas(leerFilasPreviewEditadas(), onDone);
		}
	});

	// Botón "Exportar" que se transforma in-place en CSV/Excel (2026-08-24,
	// pedido explícito: "no quiero otra ventanita, usa animaciones") — la
	// animación en sí es CSS puro (ver style.css, .ac-repo-exportar), esto
	// solo prende/apaga la clase y cierra al elegir una opción o al hacer
	// click afuera, mismo patrón que el panel de combos de registrar.js.
	exportarBtn.addEventListener('click', function () {
		exportarWrap.classList.add('ac-repo-exportar-abierto');
	});
	function cerrarExportar() {
		exportarWrap.classList.remove('ac-repo-exportar-abierto');
	}
	[exportarCsvLink, exportarXlsxLink].forEach(function (link) {
		link.addEventListener('click', function () {
			// No preventDefault: el link igual navega/descarga normal, solo se
			// repliega visualmente después de un toque para que la animación de
			// apertura no se corte en seco.
			setTimeout(cerrarExportar, 150);
		});
	});
	document.addEventListener('click', function (e) {
		if (!exportarWrap.contains(e.target)) cerrarExportar();
	});

	// ---------- Pendientes de Asignar (solo Cuotas) ----------
	// Filas donde resolverPosIdCliente() no encontró exactamente un cliente
	// (ver getters/cuotas_guardar.php) — mismo concepto visual que la
	// pantalla homónima de Liquidación (assets/js/liquidacion.js): cada fila
	// muestra los candidatos sugeridos (mismo nombre, sin filtrar por CEDI)
	// como botones clicables, más un input libre por si el candidato
	// correcto no aparece en la lista corta.
	var pendientesOverlay = document.getElementById('repo-pendientes-modal-overlay');
	var pendientesBody = document.getElementById('repo-pendientes-body');

	function actualizarContadorPendientes() {
		fetch('getters/cuotas_pendientes_asignar.php')
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) return;
				pendientesCount.textContent = data.filas.length;
			})
			.catch(function () { /* silencioso — el contador no es crítico */ });
	}

	function abrirPendientes() {
		pendientesOverlay.classList.add('ac-modal-open');
		pendientesBody.innerHTML = '<tr><td colspan="6" class="ac-table-empty">Cargando...</td></tr>';
		fetch('getters/cuotas_pendientes_asignar.php')
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) { mostrarMensaje(data.message || 'No se pudo cargar la cola.', false); return; }
				pendientesCount.textContent = data.filas.length;
				renderPendientes(data.filas);
			})
			.catch(function () {
				pendientesBody.innerHTML = '<tr><td colspan="6" class="ac-table-empty">Error al cargar.</td></tr>';
			});
	}
	function cerrarPendientes() {
		pendientesOverlay.classList.remove('ac-modal-open');
	}

	function renderPendientes(filas) {
		if (!filas.length) {
			pendientesBody.innerHTML = '<tr><td colspan="6" class="ac-table-empty">No quedan filas pendientes — todo se resolvió.</td></tr>';
			return;
		}
		pendientesBody.innerHTML = filas.map(function (f) {
			var candidatosHtml = (f.candidatos || []).map(function (c) {
				return '<button type="button" class="ac-btn-outline ac-btn-inline repo-pend-btn-candidato" style="margin:2px;" data-pos-id="' + escapeHtml(c.pos_id) + '">' +
					escapeHtml(c.pos_name) + (c.cedi ? ' (' + escapeHtml(c.cedi) + ')' : '') + '</button>';
			}).join('');
			return '<tr data-id="' + f.id + '">' +
				'<td>' + escapeHtml(f.cliente_excel) + '</td>' +
				'<td>' + escapeHtml(f.cedi_excel) + '</td>' +
				'<td>' + escapeHtml(f.sector) + '</td>' +
				'<td>Q' + f.trimestre + ' ' + f.anio + '</td>' +
				'<td class="ac-text-right">' + montosMensualesTexto(f.valores_mensuales) + '</td>' +
				'<td>' +
				'<div class="repo-pend-candidatos">' + (candidatosHtml || '<span class="ac-field-hint">Sin candidatos sugeridos</span>') + '</div>' +
				'<div class="ac-row-actions" style="margin-top:6px;">' +
				'<input type="text" class="ac-input ac-mini-input repo-pend-pos-id" placeholder="pos_id manual..." style="max-width:160px;">' +
				'<button type="button" class="ac-btn-outline ac-btn-inline repo-pend-btn-asignar">Asignar</button>' +
				'<button type="button" class="ac-icon-btn ac-icon-btn-danger repo-pend-btn-descartar" title="Descartar"><span class="material-symbols-outlined">delete</span></button>' +
				'</div>' +
				'</td></tr>';
		}).join('');

		Array.prototype.forEach.call(pendientesBody.querySelectorAll('.repo-pend-btn-candidato'), function (btn) {
			btn.addEventListener('click', function () {
				var tr = btn.closest('tr');
				resolverPendiente(parseInt(tr.dataset.id, 10), 'matchear', btn.dataset.posId);
			});
		});
		Array.prototype.forEach.call(pendientesBody.querySelectorAll('.repo-pend-btn-asignar'), function (btn) {
			btn.addEventListener('click', function () {
				var tr = btn.closest('tr');
				var posId = tr.querySelector('.repo-pend-pos-id').value.trim();
				if (!posId) { mostrarMensaje('Tipeá un pos_id.', false); return; }
				resolverPendiente(parseInt(tr.dataset.id, 10), 'matchear', posId);
			});
		});
		Array.prototype.forEach.call(pendientesBody.querySelectorAll('.repo-pend-btn-descartar'), function (btn) {
			btn.addEventListener('click', function () {
				var tr = btn.closest('tr');
				resolverPendiente(parseInt(tr.dataset.id, 10), 'descartar', null);
			});
		});
	}

	function resolverPendiente(id, accion, posId) {
		var params = new URLSearchParams({ id: id, accion: accion });
		if (posId) params.set('pos_id', posId);
		fetch('getters/cuotas_resolver_match.php', { method: 'POST', body: params })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				mostrarMensaje(data.message, data.ok);
				if (!data.ok) return;
				abrirPendientes(); // recarga la cola completa (cuenta y filas quedan consistentes)
				if (tipoActivo === 'cuotas') cargarLista(); // la fila resuelta ya debe verse en la tabla principal
			})
			.catch(function () { mostrarMensaje('Error de conexión.', false); });
	}

	pendientesAbrirBtn.addEventListener('click', abrirPendientes);
	document.getElementById('repo-pendientes-modal-close').addEventListener('click', cerrarPendientes);
	pendientesOverlay.addEventListener('click', function (e) { if (e.target === pendientesOverlay) cerrarPendientes(); });

	// ---------- Resumen (solo Cuotas) ----------
	// "¿A quién le estoy mandando qué Actas?" (2026-08-25, pedido explícito) —
	// tarjetas de stat + lista agrupada en 2 secciones (con cuenta/sin
	// cuenta, ver renderResumenChart()). Rediseño visual 2026-08-26: primero
	// maquetado en Claude Design, aprobado por el usuario y pasado a código
	// real acá — colores/avatares en las clases .ac-resumen-* nuevas de
	// style.css, tomadas de los tokens reales del proyecto (--color-primary,
	// .ac-avatar-initials), no inventadas.
	var resumenOverlay = document.getElementById('repo-resumen-modal-overlay');
	var resumenStats = document.getElementById('repo-resumen-stats');
	var resumenChart = document.getElementById('repo-resumen-chart');

	// "Sin usuario asignado" como número suelto se sacó (2026-08-26, pedido
	// explícito: "me hace ruido... quítalo, lo veo innecesario") — esa misma
	// información ahora vive en la lista de abajo, con nombre y una marca
	// pasiva por fila (ver renderResumenChart()), no como un conteo ciego.
	function renderResumenStats(data) {
		var tiles = [
			{ label: 'Actas pendientes de completar', value: String(data.pendientes) },
			{ label: 'Ya generadas (usadas)', value: String(data.usadas) },
			{ label: 'Clientes sin identificar', value: String(data.pendientes_match), warn: data.pendientes_match > 0 }
		];
		resumenStats.innerHTML = tiles.map(function (t) {
			return '<div class="ac-stat-tile' + (t.warn ? ' ac-stat-tile-warn' : '') + '">' +
				'<p class="ac-stat-label">' + t.label + '</p>' +
				'<p class="ac-stat-value">' + t.value + '</p>' +
				'</div>';
		}).join('');
	}

	// Lista única de a quién le corresponden las Actas pendientes — usuarios
	// reales CON cuenta (barra de color normal) y supervisores del maestro
	// que todavía no tienen cuenta creada (`tiene_cuenta: false`, ver
	// resumen_cuotas() en functions.php) con una marca pasiva "Sin cuenta"
	// al lado del nombre, en vez de un número aparte sin decir a quién
	// corresponde (2026-08-26, pedido explícito).
	// Iniciales para el avatar circular de cada fila — mismo criterio visual
	// que .ac-avatar-initials ya usa en Gestión de Usuarios (primeras letras
	// de las 2 primeras palabras del nombre).
	function inicialesDe(nombre) {
		var partes = (nombre || '').trim().split(/\s+/);
		return ((partes[0] || '')[0] || '') + ((partes[1] || '')[0] || '');
	}

	function filaResumenUsuario(u, conCuenta) {
		var avatarClase = conCuenta ? 'ac-resumen-avatar-activo' : 'ac-resumen-avatar-inactivo';
		var filaClase = conCuenta ? 'ac-resumen-fila-activa' : '';
		var barraClase = conCuenta ? 'ac-resumen-barra-activa' : 'ac-resumen-barra-inactiva';
		var nombreClase = conCuenta ? 'ac-resumen-nombre-activo' : 'ac-resumen-nombre-inactivo';
		return '<div class="ac-resumen-fila ' + filaClase + '">' +
			'<div class="' + avatarClase + '">' + escapeHtml(inicialesDe(u.nombre).toUpperCase()) + '</div>' +
			'<span class="ac-resumen-nombre ' + nombreClase + '">' + escapeHtml(u.nombre) + '</span>' +
			'<div class="ac-chart-track"><div class="ac-chart-seg ' + barraClase + '" style="width:' + Math.max((u.actas_pendientes / u._max) * 100, 6) + '%;" title="' + escapeHtml(u.nombre) + ' — ' + u.actas_pendientes + ' Acta(s) pendiente(s)"></div></div>' +
			'<span class="ac-chart-row-value">' + u.actas_pendientes + '</span>' +
			'</div>';
	}

	// Agrupado en 2 secciones — "Con cuenta de usuario" / "Sin cuenta
	// todavía" — en vez de una lista sola con un badge chico al lado del
	// nombre (2026-08-26, rediseño hecho primero en Claude Design y
	// aprobado por el usuario): separar espacialmente los dos grupos se
	// distingue de un vistazo, sin tener que leer cada fila una por una.
	function renderResumenChart(porUsuario) {
		if (!porUsuario.length) {
			resumenChart.innerHTML = '<p class="ac-field-hint">Nadie tiene Actas precargadas pendientes ahora mismo.</p>';
			return;
		}
		var max = Math.max.apply(null, porUsuario.map(function (u) { return u.actas_pendientes; })) || 1;
		porUsuario.forEach(function (u) { u._max = max; });
		var conCuenta = porUsuario.filter(function (u) { return u.tiene_cuenta; });
		var sinCuenta = porUsuario.filter(function (u) { return !u.tiene_cuenta; });

		var html = '';
		if (conCuenta.length) {
			html += '<p class="ac-resumen-grupo-titulo ac-resumen-grupo-titulo-activo">Con cuenta de usuario</p>' +
				'<div class="ac-chart-rows" style="margin-bottom:16px;">' + conCuenta.map(function (u) { return filaResumenUsuario(u, true); }).join('') + '</div>';
		}
		if (sinCuenta.length) {
			html += '<p class="ac-resumen-grupo-titulo ac-resumen-grupo-titulo-inactivo">Sin cuenta todavía</p>' +
				'<div class="ac-chart-rows">' + sinCuenta.map(function (u) { return filaResumenUsuario(u, false); }).join('') + '</div>' +
				'<p class="ac-resumen-nota"><span class="material-symbols-outlined" style="font-size:16px;">info</span>Sus Actas quedan asignadas solas apenas se les cree la cuenta en Gestión de Usuarios.</p>';
		}
		resumenChart.innerHTML = html;
	}

	function abrirResumen() {
		resumenOverlay.classList.add('ac-modal-open');
		resumenStats.innerHTML = '';
		resumenChart.innerHTML = '<p class="ac-field-hint">Cargando...</p>';
		fetch('getters/cuotas_resumen.php')
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) { mostrarMensaje(data.message || 'No se pudo cargar el resumen.', false); return; }
				renderResumenStats(data);
				renderResumenChart(data.por_usuario);
			})
			.catch(function () { mostrarMensaje('Error de conexión al cargar el resumen.', false); });
	}
	function cerrarResumen() {
		resumenOverlay.classList.remove('ac-modal-open');
	}
	resumenAbrirBtn.addEventListener('click', abrirResumen);
	document.getElementById('repo-resumen-modal-close').addEventListener('click', cerrarResumen);
	resumenOverlay.addEventListener('click', function (e) { if (e.target === resumenOverlay) cerrarResumen(); });

	// ---------- "Eliminados" (borrado lógico, 2026-08-25) ----------
	// Solo Rebate/Participación — ver nota en activarTab(). Filtro por fecha
	// de borrado (desde/hasta) para el caso real que motivó esto: "me dicen
	// que por error borraron algo, quiero filtrar rápido el día y
	// reactivarlo". Columnas propias (no CONFIG[tipoActivo].columnas): acá
	// interesa además CUÁNDO y QUIÉN borró, y la acción es Reactivar, no
	// Editar/Eliminar.
	var eliminadosOverlay = document.getElementById('repo-eliminados-modal-overlay');
	var eliminadosHead = document.getElementById('repo-eliminados-tabla-head');
	var eliminadosBody = document.getElementById('repo-eliminados-body');
	var eliminadosDesde = document.getElementById('repo-eliminados-desde');
	var eliminadosHasta = document.getElementById('repo-eliminados-hasta');

	function columnasEliminados() {
		return tipoActivo === 'rebate'
			? [
				{ key: 'ciudad', label: 'Ciudad' },
				{ key: 'canal', label: 'Canal' },
				{ key: 'sector', label: 'Categoría' },
				{ key: 'categoria', label: 'Subcategoría' },
				{ key: 'marca', label: 'Marca' },
				{ key: 'rebate_pct', label: 'Rebate %', numero: true, formato: function (v) { return (parseFloat(v) * 100).toFixed(1) + '%'; } }
			]
			: [
				{ key: 'marca', label: 'Marca' },
				{ key: 'participacion_pct', label: 'Participación %', numero: true, formato: function (v) { return parseFloat(v).toFixed(1) + '%'; } }
			];
	}

	function formatoFechaHora(fechaSql) {
		if (!fechaSql) return '—';
		// 'YYYY-MM-DD HH:MM:SS' -> 'DD/MM/YYYY HH:MM', sin new Date() (evita
		// líos de timezone del navegador contra una hora que ya viene en
		// hora local del servidor).
		var partes = fechaSql.split(' ');
		var fecha = partes[0].split('-');
		var hora = (partes[1] || '').slice(0, 5);
		return fecha[2] + '/' + fecha[1] + '/' + fecha[0] + (hora ? ' ' + hora : '');
	}

	function cargarEliminados() {
		var cols = columnasEliminados();
		eliminadosHead.innerHTML = '<tr>' + cols.map(function (c) { return '<th' + (c.numero ? ' class="ac-text-right"' : '') + '>' + escapeHtml(c.label) + '</th>'; }).join('') +
			'<th>Eliminado el</th><th>Por</th><th class="ac-text-right">Acciones</th></tr>';
		eliminadosBody.innerHTML = '<tr><td colspan="' + (cols.length + 3) + '" class="ac-table-empty">Cargando...</td></tr>';

		var params = new URLSearchParams({ tipo: tipoActivo, desde: eliminadosDesde.value, hasta: eliminadosHasta.value });
		fetch('getters/repositorio_eliminados.php?' + params.toString())
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) { mostrarMensaje(data.message || 'No se pudo cargar.', false); return; }
				if (!data.filas.length) {
					eliminadosBody.innerHTML = '<tr><td colspan="' + (cols.length + 3) + '" class="ac-table-empty">Sin filas eliminadas en este rango.</td></tr>';
					return;
				}
				eliminadosBody.innerHTML = data.filas.map(function (fila) {
					var tds = cols.map(function (c) {
						var v = fila[c.key];
						return '<td' + (c.numero ? ' class="ac-text-right"' : '') + '>' + (c.numero ? '<span class="ac-repo-badge">' + escapeHtml(c.formato(v)) + '</span>' : escapeHtml(v)) + '</td>';
					}).join('');
					return '<tr data-id="' + fila.id + '">' + tds +
						'<td>' + escapeHtml(formatoFechaHora(fila.eliminado_en)) + '</td>' +
						'<td>' + escapeHtml(fila.eliminado_por_usuario || '—') + '</td>' +
						'<td class="ac-text-right"><button type="button" class="ac-btn-outline ac-btn-inline ac-repo-reactivar"><span class="material-symbols-outlined">restore</span>Reactivar</button></td>' +
						'</tr>';
				}).join('');
				Array.prototype.forEach.call(eliminadosBody.querySelectorAll('.ac-repo-reactivar'), function (btn) {
					btn.addEventListener('click', function () {
						var tr = btn.closest('tr');
						fetch('getters/repositorio_reactivar.php', {
							method: 'POST',
							headers: { 'Content-Type': 'application/json' },
							body: JSON.stringify({ tipo: tipoActivo, id: parseInt(tr.dataset.id, 10) })
						})
							.then(function (r) { return r.json(); })
							.then(function (data) {
								mostrarMensaje(data.message, data.ok);
								if (data.ok) { cargarEliminados(); cargarLista(); }
							})
							.catch(function () { mostrarMensaje('Error de conexión.', false); });
					});
				});
			})
			.catch(function () { mostrarMensaje('Error de conexión al cargar eliminados.', false); });
	}

	function abrirEliminados() {
		eliminadosOverlay.classList.add('ac-modal-open');
		eliminadosDesde.value = '';
		eliminadosHasta.value = '';
		cargarEliminados();
	}
	function cerrarEliminados() {
		eliminadosOverlay.classList.remove('ac-modal-open');
	}
	eliminadosAbrirBtn.addEventListener('click', abrirEliminados);
	document.getElementById('repo-eliminados-modal-close').addEventListener('click', cerrarEliminados);
	document.getElementById('repo-eliminados-buscar').addEventListener('click', cargarEliminados);
	eliminadosOverlay.addEventListener('click', function (e) { if (e.target === eliminadosOverlay) cerrarEliminados(); });

	// Refresco al volver a esta pestaña (mismo patrón que Historial/Liquidación,
	// ver index.php) — la arquitectura de la app renderiza todo una sola vez.
	window.acRepositoriosRefrescar = function () { cargarLista(); };

	actualizarHrefsExportar();
	cargarLista();
})();

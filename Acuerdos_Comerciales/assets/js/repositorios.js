(function () {
	// Config por tipo de repositorio — todo lo que cambia entre Rebate y
	// Participación de Percha vive acá (columnas, formato, placeholder de
	// búsqueda) para no duplicar la lógica de render/edición en 2 copias.
	var CONFIG = {
		rebate: {
			label: 'Rebate',
			buscarPlaceholder: 'Buscar por segmento, sector, categoría o marca...',
			columnas: [
				{ key: 'segmento', label: 'Segmento' },
				{ key: 'sector', label: 'Sector' },
				{ key: 'categoria', label: 'Categoría' },
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
		}
	};

	var tipoActivo = 'rebate';
	var paginaActual = 1;
	var busquedaActual = '';
	var buscarTimeout = null;
	var filasPreview = null; // filas leídas del Excel, en edición dentro del modal

	var tablaHead = document.getElementById('repo-tabla-head');
	var tablaBody = document.getElementById('repo-tabla-body');
	var paginacionInfo = document.getElementById('repo-paginacion-info');
	var paginacionBtns = document.getElementById('repo-paginacion-btns');
	var buscarInput = document.getElementById('repo-buscar');
	var exportarWrap = document.getElementById('repo-exportar-wrap');
	var exportarBtn = document.getElementById('repo-exportar-btn');
	var exportarCsvLink = document.getElementById('repo-exportar-csv');
	var exportarXlsxLink = document.getElementById('repo-exportar-xlsx');
	var tabRebate = document.getElementById('repo-tab-rebate');
	var tabParticipacion = document.getElementById('repo-tab-participacion');

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.textContent = str == null ? '' : String(str);
		return div.innerHTML;
	}

	function mostrarMensaje(texto, ok) {
		mostrarToast(texto, ok ? 'success' : 'error');
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
		var v = fila[col.key];
		if (col.numero) return '<span class="ac-repo-badge">' + escapeHtml(col.formato(v)) + '</span>';
		return escapeHtml(v);
	}

	function renderFilas(filas) {
		var cols = CONFIG[tipoActivo].columnas;
		if (!filas.length) {
			tablaBody.innerHTML = '<tr><td colspan="' + (cols.length + 1) + '" class="ac-table-empty">Sin registros.</td></tr>';
			return;
		}
		tablaBody.innerHTML = filas.map(function (fila) {
			var tds = cols.map(function (c) {
				return '<td' + (c.numero ? ' class="ac-text-right"' : '') + '>' + celdaValor(c, fila) + '</td>';
			}).join('');
			return '<tr data-id="' + fila.id + '">' + tds +
				'<td class="ac-text-right"><div class="ac-row-actions">' +
				'<button type="button" class="ac-icon-btn ac-repo-editar" title="Editar"><span class="material-symbols-outlined">edit</span></button>' +
				'<button type="button" class="ac-icon-btn ac-icon-btn-danger ac-repo-eliminar" title="Eliminar"><span class="material-symbols-outlined">delete</span></button>' +
				'</div></td></tr>';
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
		Swal.fire({
			icon: 'warning',
			title: '¿Eliminar registro?',
			text: 'Esta acción no se puede deshacer.',
			showCancelButton: true,
			confirmButtonText: 'Sí, eliminar',
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

	function renderPaginacion(pagina, totalPaginas) {
		var html = '';
		html += '<button type="button" class="ac-page-btn" data-pg="' + (pagina - 1) + '" ' + (pagina <= 1 ? 'disabled' : '') + '>' +
			'<span class="material-symbols-outlined">chevron_left</span></button>';
		for (var i = 1; i <= totalPaginas; i++) {
			html += '<button type="button" class="ac-page-btn' + (i === pagina ? ' ac-page-btn-active' : '') + '" data-pg="' + i + '">' + i + '</button>';
		}
		html += '<button type="button" class="ac-page-btn" data-pg="' + (pagina + 1) + '" ' + (pagina >= totalPaginas ? 'disabled' : '') + '>' +
			'<span class="material-symbols-outlined">chevron_right</span></button>';
		paginacionBtns.innerHTML = html;

		Array.prototype.forEach.call(paginacionBtns.querySelectorAll('.ac-page-btn'), function (btn) {
			btn.addEventListener('click', function () {
				var pg = parseInt(btn.dataset.pg, 10);
				if (pg < 1 || pg > totalPaginas) return;
				paginaActual = pg;
				cargarLista();
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
				paginacionInfo.innerHTML = 'Mostrando <strong>' + data.filas.length + '</strong> de <strong>' + data.total + '</strong> registros';
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
		actualizarHrefsExportar();
		cargarLista();
	}
	tabRebate.addEventListener('click', function () { activarTab('rebate'); });
	tabParticipacion.addEventListener('click', function () { activarTab('participacion'); });

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
	var previewNombreArchivo = document.getElementById('repo-preview-nombre-archivo');
	var previewCantidad = document.getElementById('repo-preview-cantidad');
	var previewTablaHead = document.getElementById('repo-preview-tabla-head');
	var previewTablaBody = document.getElementById('repo-preview-tabla-body');
	var previewErrores = document.getElementById('repo-preview-errores');

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
		filasPreview = null;
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
	subirOverlay.addEventListener('click', function (e) { if (e.target === subirOverlay) cerrarModalSubir(); });

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
	// comentario de cabecera en ese getter.
	function previsualizarArchivo(archivo) {
		var formData = new FormData();
		formData.append('tipo', tipoActivo);
		formData.append('archivo', archivo);

		fetch('getters/repositorio_previsualizar_excel.php', { method: 'POST', body: formData })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) { mostrarMensaje(data.message, false); return; }
				filasPreview = data.filas;
				previewNombreArchivo.textContent = data.nombre_archivo;
				previewCantidad.textContent = data.filas.length + ' fila(s) detectada(s)';
				renderPreviewTabla();
				mostrarPasoPreview();
			})
			.catch(function () { mostrarMensaje('Error de conexión al leer el archivo.', false); });
	}

	function renderPreviewTabla() {
		var cols = CONFIG[tipoActivo].columnas;
		previewTablaHead.innerHTML = '<tr>' + cols.map(function (c) { return '<th>' + escapeHtml(c.label) + '</th>'; }).join('') + '</tr>';
		previewTablaBody.innerHTML = filasPreview.map(function (fila, i) {
			return '<tr data-i="' + i + '">' + cols.map(function (c) {
				var valor = c.numero ? (parseFloat(fila[c.key]) * (c.key === 'rebate_pct' ? 100 : 1)).toString() : fila[c.key];
				return '<td><input type="text" class="ac-preview-input" data-key="' + c.key + '" value="' + escapeHtml(valor) + '"></td>';
			}).join('') + '</tr>';
		}).join('');
	}

	// Lee los valores actuales de los inputs (el usuario puede haber
	// corregido cualquier celda) antes de guardar — nunca se guarda el dato
	// crudo tal como vino del Excel si se editó en pantalla.
	function leerFilasPreviewEditadas() {
		var cols = CONFIG[tipoActivo].columnas;
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
			.catch(function () { mostrarMensaje('Error de conexión al guardar.', false); });
	}

	document.getElementById('repo-subir-guardar').addEventListener('click', function () {
		var filas = leerFilasPreviewEditadas();
		guardarFilas(filas, function (data) {
			if (!data.ok) return;
			cargarLista(); // lo que sí se guardó ya debe verse en la tabla de atrás
			var hayAlgoQueRevisar = (data.errores && data.errores.length) || (data.avisos && data.avisos.length);
			if (hayAlgoQueRevisar) {
				// Se queda en el modal para que el usuario vea qué pasó (no
				// guardado, o guardado con aviso) y pueda corregir sin perder
				// el resto del archivo.
				mostrarErroresPreview(data.errores, data.avisos);
			} else {
				cerrarModalSubir();
			}
		});
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

	// Refresco al volver a esta pestaña (mismo patrón que Historial/Liquidación,
	// ver index.php) — la arquitectura de la app renderiza todo una sola vez.
	window.acRepositoriosRefrescar = function () { cargarLista(); };

	actualizarHrefsExportar();
	cargarLista();
})();

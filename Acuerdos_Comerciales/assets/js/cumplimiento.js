// Módulo "Cumplimiento de Cuota" (2026-08-30) — mismo patrón que
// Seguimiento de Equipo/Repositorios: el getter devuelve JSON crudo, este
// script arma TODO el DOM en cliente (más rápido para cambiar de filtro sin
// ida y vuelta al servidor por cada click).
document.addEventListener('DOMContentLoaded', function () {
	var root = document.getElementById('ac-cumpl');
	if (!root) return; // rol sin acceso, el componente no se renderizó

	var trimestreGroup = document.getElementById('cumpl-trimestre-group');
	var anioSelect = document.getElementById('cumpl-anio');
	var buscarInput = document.getElementById('cumpl-buscar');
	var lista = document.getElementById('cumpl-lista');
	var statClientes = document.getElementById('cumpl-stat-clientes');
	var statGanan = document.getElementById('cumpl-stat-ganan');
	var statNoGanan = document.getElementById('cumpl-stat-no-ganan');
	var statPromedio = document.getElementById('cumpl-stat-promedio');

	var estado = { trimestre: 0, anio: 0, busqueda: '' };
	var listaReqId = 0;

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.textContent = str == null ? '' : String(str);
		return div.innerHTML;
	}
	function mostrarMensaje(texto, ok) { mostrarToast(texto, ok ? 'success' : 'error'); }
	function moneda(v) { return '$' + (parseFloat(v) || 0).toFixed(2); }
	function pctTexto(v) { return (parseFloat(v) || 0).toFixed(2) + '%'; }

	function badgeGana(valor, outline) {
		var esGana = valor === 'gana';
		var clase = esGana ? 'ac-badge-ok' : 'ac-badge-critico';
		return '<span class="ac-badge ' + clase + (outline ? ' ac-cumpl-badge-outline' : '') + '">' + (esGana ? 'GANA' : 'NO GANA') + '</span>';
	}

	// ---------- Filtros ----------
	Array.prototype.forEach.call(trimestreGroup.querySelectorAll('.ac-seg-pill'), function (btn) {
		btn.addEventListener('click', function () {
			Array.prototype.forEach.call(trimestreGroup.querySelectorAll('.ac-seg-pill'), function (b) { b.classList.remove('ac-seg-pill-activo'); });
			btn.classList.add('ac-seg-pill-activo');
			estado.trimestre = parseInt(btn.dataset.trimestre, 10) || 0;
			cargarLista();
		});
	});
	anioSelect.addEventListener('change', function () {
		estado.anio = parseInt(anioSelect.value, 10) || 0;
		cargarLista();
	});
	var buscarTimeout = null;
	buscarInput.addEventListener('input', function () {
		clearTimeout(buscarTimeout);
		buscarTimeout = setTimeout(function () {
			estado.busqueda = buscarInput.value.trim();
			cargarLista();
		}, 350);
	});

	// ---------- Anillo del asesor (conic-gradient), mismo criterio visual que
	// Seguimiento de Equipo: verde = % de categorías que gana, el resto en
	// gris neutro (acá no hay "urgencia" de fecha como en Seguimiento, solo
	// proporción). ----------
	function ringDeUsuario(u) {
		var pct = u.total_categorias > 0 ? Math.round((u.ganan_categoria / u.total_categorias) * 100) : 0;
		if (pct >= 100) return 'conic-gradient(#1e9e5a 0% 100%)';
		if (pct <= 0) return 'conic-gradient(#c4c5d5 0% 100%)';
		return 'conic-gradient(#1e9e5a 0% ' + pct + '%, #ffdad6 ' + pct + '% 100%)';
	}

	var GRUPO_CLASES = ['ac-repo-fila-grupo-a', 'ac-repo-fila-grupo-b', 'ac-repo-fila-grupo-c'];

	function filaCategoria(cat, grupoClase) {
		var cambioHtml = '';
		if (cat.cambio === 'mejora') {
			cambioHtml = '<div class="ac-cumpl-cambio ac-cumpl-cambio-mejora">' +
				'<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>' +
				'Mejoró desde la última subida</div>';
		} else if (cat.cambio === 'empeora') {
			cambioHtml = '<div class="ac-cumpl-cambio ac-cumpl-cambio-empeora">' +
				'<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>' +
				'Bajó desde la última subida</div>';
		}
		return '<div class="ac-cumpl-fila-cat ' + grupoClase + '">' +
			'<div>' + escapeHtml(cat.sector) + cambioHtml + '</div>' +
			'<div>' + pctTexto(cat.cumplimiento_pct) + '</div>' +
			'<div>' + moneda(cat.venta_total) + '</div>' +
			'<div>' + moneda(cat.cuota_total) + '</div>' +
			'<div>' + badgeGana(cat.gana_categoria, false) + '</div>' +
			// Gana Total al lado de Gana Categoría, en la MISMA fila — mismo
			// pedido explícito del usuario tras confundirse con el mockup
			// anterior (donde Gana Total solo se veía arriba, en la cabecera
			// del cliente). Con borde (outline) en vez de relleno sólido, para
			// que se lea como "resultado heredado del cliente" y no se
			// confunda con el resultado propio de esta categoría.
			'<div>' + badgeGana(cat.gana_total, true) + '</div>' +
			'<div>' + (cat.gana_categoria === 'gana' ? moneda(cat.rebate_real_vol) : '<span class="ac-field-hint">' + moneda(cat.rebate_real_vol) + '</span>') + '</div>' +
			'<div><button type="button" class="ac-icon-btn ac-icon-btn-danger ac-cumpl-eliminar" data-id="' + cat.id + '" title="Eliminar"><span class="material-symbols-outlined">delete</span></button></div>' +
			'</div>';
	}

	function filaCliente(cliente, grupoClase) {
		// Sin badge de Gana Total acá (a propósito): ya se ve en cada fila de
		// categoría, al lado de Gana Categoría — repetirlo también en esta
		// cabecera, justo arriba de la primera fila, sería la misma
		// información dos veces seguidas sin aportar nada nuevo.
		var actualizado = cliente.actualizado_en ? new Date(cliente.actualizado_en.replace(' ', 'T')) : null;
		var actualizadoTexto = actualizado ? 'Actualizado ' + actualizado.toLocaleDateString('es-EC', { day: '2-digit', month: 'short' }) : '';
		var header = '<div class="ac-cumpl-fila-cliente ' + grupoClase + '">' +
			'<div class="ac-cumpl-cliente-nombre">' +
			'<span>' + escapeHtml(cliente.cliente) + '</span>' +
			(cliente.cedi ? '<span class="ac-field-hint">' + escapeHtml(cliente.cedi) + (cliente.plan ? ' &middot; ' + escapeHtml(cliente.plan) : '') + '</span>' : '') +
			'</div>' +
			(actualizadoTexto ? '<span class="ac-field-hint ac-cumpl-cliente-meta">' + actualizadoTexto + '</span>' : '') +
			'</div>';
		var filas = cliente.categorias.map(function (cat) { return filaCategoria(cat, grupoClase); }).join('');
		return header + filas;
	}

	function renderLista(usuarios) {
		if (!usuarios.length) {
			lista.innerHTML = '<div class="ac-table-empty">Sin registros para este filtro.</div>';
			return;
		}
		var html = usuarios.map(function (u) {
			var ring = ringDeUsuario(u);
			var cabecera = '<div class="ac-cumpl-fila-usuario">' +
				'<div class="ac-cumpl-usuario-id">' +
				'<div class="ac-seg-avatar-ring" style="background:' + ring + '"><div class="ac-avatar-initials">' + escapeHtml(u.iniciales) + '</div></div>' +
				'<div><div class="ac-cumpl-usuario-nombre">' + escapeHtml(u.nombre) + '</div>' +
				'<div class="ac-field-hint">' + u.total_clientes + ' cliente(s) &middot; ' + u.total_categorias + ' categoría(s)</div></div>' +
				'</div>' +
				'<div class="ac-cumpl-usuario-resumen">' +
				'<span class="ac-cumpl-resumen-ok"><span class="ac-cumpl-dot" style="background:#1e9e5a"></span>' + u.ganan_categoria + ' ganan</span>' +
				'<span class="ac-cumpl-resumen-no"><span class="ac-cumpl-dot" style="background:#ba1a1a"></span>' + u.no_ganan_categoria + ' no ganan</span>' +
				'</div></div>';

			var grupoIndice = -1;
			var clientesHtml = u.clientes.map(function (c) {
				grupoIndice = (grupoIndice + 1) % GRUPO_CLASES.length;
				return filaCliente(c, GRUPO_CLASES[grupoIndice]);
			}).join('');
			return cabecera + clientesHtml;
		}).join('');
		lista.innerHTML = html;

		Array.prototype.forEach.call(lista.querySelectorAll('.ac-cumpl-eliminar'), function (btn) {
			btn.addEventListener('click', function () { confirmarYEliminar(parseInt(btn.dataset.id, 10)); });
		});
	}

	function confirmarYEliminar(id) {
		Swal.fire({
			icon: 'warning',
			title: '¿Eliminar esta categoría?',
			text: 'Se puede recuperar volviendo a subir el mismo Excel de este trimestre.',
			showCancelButton: true,
			confirmButtonText: 'Sí, eliminar',
			cancelButtonText: 'Cancelar',
			confirmButtonColor: '#ba1a1a'
		}).then(function (resultado) {
			if (!resultado.isConfirmed) return;
			fetch('getters/cumplimiento_eliminar.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ id: id })
			})
				.then(function (r) { return r.json(); })
				.then(function (data) {
					mostrarMensaje(data.message, data.ok);
					if (data.ok) cargarLista();
				})
				.catch(function () { mostrarMensaje('Error de conexión.', false); });
		});
	}

	function cargarLista() {
		var miReqId = ++listaReqId;
		if (window.acMostrarCargando) acMostrarCargando(root.closest('.ac-card') || root);
		var params = new URLSearchParams({ trimestre: estado.trimestre, anio: estado.anio, q: estado.busqueda });
		fetch('getters/cumplimiento_listar.php?' + params.toString())
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (miReqId !== listaReqId) return; // respuesta vieja, se descarta (condición de carrera)
				if (window.acOcultarCargando) acOcultarCargando(root.closest('.ac-card') || root);
				if (!data.ok) { lista.innerHTML = '<div class="ac-table-empty">No se pudo cargar.</div>'; return; }
				renderLista(data.usuarios);
				statClientes.textContent = data.stats.clientes;
				statGanan.textContent = data.stats.ganan_categoria;
				statNoGanan.textContent = data.stats.no_ganan_categoria;
				statPromedio.textContent = data.stats.cumplimiento_promedio.toFixed(1) + '%';
			})
			.catch(function () {
				if (miReqId !== listaReqId) return;
				if (window.acOcultarCargando) acOcultarCargando(root.closest('.ac-card') || root);
				lista.innerHTML = '<div class="ac-table-empty">Error de conexión.</div>';
			});
	}
	window.acCumplimientoRefrescar = cargarLista;
	cargarLista();

	// ---------- Modal "Subir Excel" ----------
	var subirBtn = document.getElementById('cumpl-subir-btn');
	var subirOverlay = document.getElementById('cumpl-subir-modal-overlay');
	var subirModal = subirOverlay.querySelector('.ac-repo-subir-modal');
	var pasoElegir = document.getElementById('cumpl-subir-paso-elegir');
	var pasoPreview = document.getElementById('cumpl-subir-paso-preview');
	var footerElegir = document.getElementById('cumpl-subir-footer-elegir');
	var footerPreview = document.getElementById('cumpl-subir-footer-preview');
	var dropzone = document.getElementById('cumpl-dropzone');
	var archivoInput = document.getElementById('cumpl-archivo-input');
	var progresoCarga = document.getElementById('cumpl-subir-progreso');
	var progresoCargaFill = document.getElementById('cumpl-subir-progreso-fill');
	var progresoCargaTexto = document.getElementById('cumpl-subir-progreso-texto');
	var previewNombreArchivo = document.getElementById('cumpl-preview-nombre-archivo');
	var previewCantidad = document.getElementById('cumpl-preview-cantidad');
	var previewTablaHead = document.getElementById('cumpl-preview-tabla-head');
	var previewTablaBody = document.getElementById('cumpl-preview-tabla-body');
	var previewErrores = document.getElementById('cumpl-preview-errores');
	var previewAnioInput = document.getElementById('cumpl-preview-anio');
	var subirGuardarBtn = document.getElementById('cumpl-subir-guardar');
	var subirGuardarHtmlOriginal = subirGuardarBtn.innerHTML;

	var filasPreview = null;
	var trimestrePreview = null;
	var estadosPreview = null;

	function ocultarErroresPreview() {
		previewErrores.classList.add('hidden');
		previewErrores.innerHTML = '';
	}
	function mostrarErroresPreview(errores, avisos) {
		errores = errores || [];
		avisos = avisos || [];
		if (!errores.length && !avisos.length) { ocultarErroresPreview(); return; }
		var html = '';
		if (errores.length) {
			html += '<p>' + errores.length + ' fila(s) NO se guardaron:</p><ul>' +
				errores.map(function (e) { return '<li>' + escapeHtml(e.fila) + ': ' + escapeHtml(e.motivo) + '</li>'; }).join('') + '</ul>';
		}
		if (avisos.length) {
			html += '<p' + (errores.length ? ' style="margin-top:8px;"' : '') + '>' + avisos.length + ' fila(s) se guardaron, pero revisá:</p><ul>' +
				avisos.map(function (a) { return '<li>' + escapeHtml(a.fila) + ': ' + escapeHtml(a.motivo) + '</li>'; }).join('') + '</ul>';
		}
		previewErrores.innerHTML = html;
		previewErrores.classList.toggle('ac-alert-error', errores.length > 0);
		previewErrores.classList.toggle('ac-alert-warning', errores.length === 0);
		previewErrores.classList.remove('hidden');
		previewErrores.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}

	function abrirModalSubir() {
		mostrarPasoElegir();
		archivoInput.value = '';
		subirOverlay.classList.add('ac-modal-open');
	}
	function cerrarModalSubir() { subirOverlay.classList.remove('ac-modal-open'); }
	function mostrarPasoElegir() {
		pasoElegir.classList.remove('hidden');
		pasoPreview.classList.add('hidden');
		footerElegir.classList.remove('hidden');
		footerPreview.classList.add('hidden');
		subirModal.classList.remove('ac-repo-subir-modal-ancho');
		ocultarProgresoCarga();
		filasPreview = null;
		trimestrePreview = null;
		estadosPreview = null;
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

	subirBtn.addEventListener('click', abrirModalSubir);
	document.getElementById('cumpl-subir-modal-close').addEventListener('click', cerrarModalSubir);
	document.getElementById('cumpl-subir-cancelar').addEventListener('click', cerrarModalSubir);
	document.getElementById('cumpl-subir-atras').addEventListener('click', mostrarPasoElegir);
	// Sin cierre por click afuera a propósito (mismo motivo que Repositorios:
	// evitar perder el paso de previsualización por un click accidental).

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

	function previsualizarArchivo(archivo) {
		var formData = new FormData();
		formData.append('archivo', archivo);

		mostrarProgresoCarga();
		var xhr = new XMLHttpRequest();
		xhr.open('POST', 'getters/cumplimiento_previsualizar_excel.php');
		xhr.upload.addEventListener('progress', function (e) {
			if (!e.lengthComputable) return;
			var pctVal = Math.round((e.loaded / e.total) * 100);
			progresoCargaFill.style.width = pctVal + '%';
			progresoCargaTexto.textContent = 'Subiendo… ' + pctVal + '%';
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
			previewCantidad.textContent = data.filas.length + ' fila(s) detectada(s)' + (trimestrePreview ? ' (Q' + trimestrePreview + ')' : '');
			estadosPreview = null;
			previewAnioInput.value = new Date().getFullYear();
			renderPreviewTabla();
			mostrarPasoPreview();
			verificarEstadosPreview();
		});
		xhr.addEventListener('error', function () {
			ocultarProgresoCarga();
			mostrarMensaje('Error de conexión al leer el archivo.', false);
		});
		xhr.send(formData);
	}

	function badgeEstadoPreview(item) {
		if (!item) return '<span class="ac-field-hint">…</span>';
		if (item.estado === 'nuevo') return '<span class="ac-badge ac-badge-ok">Nuevo</span>';
		if (item.estado === 'actualiza') return '<span class="ac-badge ac-badge-revisar">Se actualiza</span>';
		if (item.estado === 'mejora') return '<span class="ac-badge ac-badge-ok">Ahora gana</span>';
		if (item.estado === 'empeora') return '<span class="ac-badge ac-badge-critico">Ya no gana</span>';
		if (item.estado === 'sin_cliente') return '<span class="ac-field-hint">Cliente sin identificar</span>';
		return '<span class="ac-field-hint">—</span>';
	}
	function claseFilaEstado(item) {
		if (!item) return '';
		if (item.estado === 'nuevo') return 'ac-preview-fila-nueva';
		if (item.estado === 'mejora') return 'ac-preview-fila-nueva';
		if (item.estado === 'empeora') return 'ac-preview-fila-usada';
		return '';
	}

	function renderPreviewTabla() {
		var cols = [
			{ label: 'Cliente', key: 'cliente_excel' },
			{ label: 'Categoría', key: 'sector' },
			{ label: 'Cumplimiento', render: function (f) { return pctTexto(f.cumplimiento_pct); } },
			{ label: 'Venta real', render: function (f) { return moneda(f.venta_total); } },
			{ label: 'Cuota', render: function (f) { return moneda(f.cuota_total); } },
			{ label: 'Gana categoría', render: function (f) { return badgeGana(f.gana_categoria, false); } },
			{ label: 'Gana total', render: function (f) { return badgeGana(f.gana_total, true); } },
			{ label: 'Rebate ganado', render: function (f) { return moneda(f.rebate_real_vol); } }
		];
		previewTablaHead.innerHTML = '<tr>' + cols.map(function (c) { return '<th>' + escapeHtml(c.label) + '</th>'; }).join('') + '<th>Al guardar</th></tr>';
		previewTablaBody.innerHTML = filasPreview.map(function (fila, i) {
			var tds = cols.map(function (c) {
				var contenido = c.render ? c.render(fila) : escapeHtml(fila[c.key]);
				return '<td>' + contenido + '</td>';
			}).join('');
			var item = estadosPreview ? estadosPreview[i] : null;
			tds += '<td>' + badgeEstadoPreview(item) + '</td>';
			var clase = claseFilaEstado(item);
			return '<tr' + (clase ? ' class="' + clase + '"' : '') + '>' + tds + '</tr>';
		}).join('');
	}

	function verificarEstadosPreview() {
		if (!filasPreview || !trimestrePreview) return;
		var anio = parseInt(previewAnioInput.value, 10);
		if (!anio) return;
		fetch('getters/cumplimiento_verificar_estado.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ filas: filasPreview, trimestre: trimestrePreview, anio: anio })
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) return; // silencioso, no bloquea la previsualización
				estadosPreview = data.estados;
				renderPreviewTabla();
			})
			.catch(function () { /* silencioso */ });
	}
	var verificarEstadosTimeout = null;
	previewAnioInput.addEventListener('input', function () {
		clearTimeout(verificarEstadosTimeout);
		verificarEstadosTimeout = setTimeout(verificarEstadosPreview, 400);
	});

	function ponerGuardarCargando(cargando) {
		subirGuardarBtn.classList.toggle('ac-btn-cargando', cargando);
		subirGuardarBtn.innerHTML = cargando
			? '<span class="material-symbols-outlined">progress_activity</span>Guardando…'
			: subirGuardarHtmlOriginal;
	}

	subirGuardarBtn.addEventListener('click', function () {
		var anio = parseInt(previewAnioInput.value, 10);
		var anioActual = new Date().getFullYear();
		if (!anio || anio < anioActual - 1 || anio > anioActual + 1) {
			mostrarMensaje('Elige un año válido.', false);
			return;
		}
		if (!filasPreview || !filasPreview.length) return;
		ponerGuardarCargando(true);
		fetch('getters/cumplimiento_guardar.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ filas: filasPreview, trimestre: trimestrePreview, anio: anio })
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				ponerGuardarCargando(false);
				// data.ok siempre es true si la petición se procesó (aunque 0 filas
				// se hayan guardado de verdad) — el color del toast usa
				// data.guardadas, no data.ok a secas (mismo criterio que
				// Repositorios, ver assets/js/repositorios.js).
				mostrarMensaje(data.message, data.ok && data.guardadas > 0);
				if (!data.ok) return;
				cargarLista();
				var errores = data.errores || [];
				var avisos = data.avisos || [];
				if (errores.length) {
					mostrarErroresPreview(errores, avisos);
					return;
				}
				var avisosRelevantes = avisos.filter(function (a) { return a.tipo !== 'duplicado_archivo'; });
				if (avisosRelevantes.length) {
					var grupos = {};
					var ordenMotivos = [];
					avisosRelevantes.forEach(function (a) {
						if (!grupos[a.motivo]) { grupos[a.motivo] = []; ordenMotivos.push(a.motivo); }
						grupos[a.motivo].push(a.fila);
					});
					var html = ordenMotivos.map(function (motivo) {
						var filasMotivo = grupos[motivo];
						return '<div class="ac-avisos-grupo"><div class="ac-avisos-grupo-motivo">' + escapeHtml(motivo) +
							' <span class="ac-avisos-count">' + filasMotivo.length + '</span></div>' +
							'<div class="ac-avisos-grupo-filas">' +
							filasMotivo.map(function (f) { return '<span class="ac-avisos-chip">' + escapeHtml(f) + '</span>'; }).join('') +
							'</div></div>';
					}).join('');
					Swal.fire({ icon: 'info', title: 'Guardado. Hay algo para revisar.', html: '<div class="ac-avisos-lista">' + html + '</div>', width: 720, confirmButtonText: 'Entendido', confirmButtonColor: '#00288e' });
					return;
				}
				cerrarModalSubir();
			})
			.catch(function () { ponerGuardarCargando(false); mostrarMensaje('Error de conexión al guardar.', false); });
	});
});

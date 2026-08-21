(function () {
	var vistaLista     = document.getElementById('ac-historial-lista');
	var vistaPreview   = document.getElementById('ac-historial-preview');
	var pdfFrame       = document.getElementById('hist-pdf-frame');
	var descargarBtn   = document.getElementById('hist-descargar-pdf');

	var buscarInput     = document.getElementById('hist-buscar');
	var trimestreSelect = document.getElementById('hist-trimestre');
	var anioSelect      = document.getElementById('hist-anio');
	var buscarBtn       = document.getElementById('hist-buscar-btn');
	var exportarCuotaLink = document.getElementById('hist-exportar-cuota');
	var tbody           = document.getElementById('hist-tabla-body');
	var paginacionEl    = document.getElementById('hist-paginacion');
	var paginacionInfo  = document.getElementById('hist-paginacion-info');
	var paginacionBtns  = document.getElementById('hist-paginacion-btns');
	var buscarTimeout   = null;

	function escapeHtml(texto) {
		var div = document.createElement('div');
		div.textContent = texto == null ? '' : String(texto);
		return div.innerHTML;
	}

	// Confirmación (SweetAlert2, acción destructiva) + guardado real vía
	// eliminar_acuerdo.php (nunca DELETE físico, marca estado='anulado') —
	// compartido entre el listado de Historial y el modal "Mis Borradores".
	// La única diferencia entre los dos usos es qué pasa con la fila después
	// de borrar (recargar toda la lista vs. sacar solo esa fila con
	// animación), por eso queda a cargo de onOk.
	function confirmarYEliminarAcuerdo(id, documentoNo, onOk) {
		Swal.fire({
			icon: 'warning',
			title: '¿Eliminar acuerdo?',
			text: 'Se eliminará el acuerdo #' + documentoNo + '. Esta acción no se puede deshacer desde aquí.',
			showCancelButton: true,
			confirmButtonText: 'Sí, eliminar',
			cancelButtonText: 'Cancelar',
			confirmButtonColor: '#ba1a1a'
		}).then(function (resultado) {
			if (!resultado.isConfirmed) return;

			fetch('getters/eliminar_acuerdo.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({ id: id })
			})
				.then(function (r) { return r.json(); })
				.then(function (data) {
					mostrarToast(data.message, data.ok ? 'success' : 'error');
					if (data.ok && onOk) onOk();
				})
				.catch(function () { mostrarToast('Error de conexión. Intenta nuevamente.', 'error'); });
		});
	}

	// Saca una fila de una tabla con una animación corta (fade + slide) en vez
	// de desaparecer de golpe, y deja el placeholder de "vacío" si era la
	// última fila que quedaba — mismo patrón para Mis Borradores.
	function animarYQuitarFila(fila, colspanVacio, mensajeVacio) {
		fila.classList.add('ac-fila-eliminando');
		fila.addEventListener('transitionend', function () {
			var tbodyDeLaFila = fila.parentElement;
			fila.remove();
			if (tbodyDeLaFila && !tbodyDeLaFila.querySelector('tr')) {
				tbodyDeLaFila.innerHTML = '<tr><td colspan="' + colspanVacio + '" class="ac-table-empty">' + mensajeVacio + '</td></tr>';
			}
		}, { once: true });
	}

	// ---------- Stat tiles = también filtro de firma (2026-08-21) ----------
	// "todos" | "firmadas" | "pendientes" — click en un tile ya activo vuelve
	// a "todos" (toggle), no queda un estado sin salida.
	var firmaFiltroActual = 'todos';
	var statTiles = {
		firmadas:   document.getElementById('hist-stat-firmadas'),
		pendientes: document.getElementById('hist-stat-pendientes')
	};

	// Solo alimentan el ancho de las barras — el % y "más antigua" ya no se
	// muestran como texto (pedido explícito: dejar solo el número).
	function renderStats(stats) {
		var pctFirmadas = stats.total > 0 ? Math.round(stats.firmadas / stats.total * 100) : 0;
		var pctPendientes = stats.total > 0 ? Math.round(stats.pendientes / stats.total * 100) : 0;
		document.getElementById('hist-stat-total-valor').textContent = stats.total;
		document.getElementById('hist-stat-firmadas-valor').textContent = stats.firmadas;
		document.getElementById('hist-stat-firmadas-bar').style.width = pctFirmadas + '%';
		document.getElementById('hist-stat-pendientes-valor').textContent = stats.pendientes;
		document.getElementById('hist-stat-pendientes-bar').style.width = pctPendientes + '%';
	}

	function actualizarTilesActivos() {
		statTiles.firmadas.classList.toggle('ac-hist-stat-activo', firmaFiltroActual === 'firmadas');
		statTiles.pendientes.classList.toggle('ac-hist-stat-activo', firmaFiltroActual === 'pendientes');
	}

	Object.keys(statTiles).forEach(function (clave) {
		statTiles[clave].addEventListener('click', function () {
			firmaFiltroActual = (firmaFiltroActual === statTiles[clave].dataset.filtro) ? 'todos' : statTiles[clave].dataset.filtro;
			actualizarTilesActivos();
			cargarHistorial(1);
		});
	});
	document.getElementById('hist-stat-total').addEventListener('click', function () {
		firmaFiltroActual = 'todos';
		actualizarTilesActivos();
		cargarHistorial(1);
	});

	// ---------- Listado: búsqueda + filtro de período (trimestre + año + firma) + paginación ----------
	function cargarHistorial(pagina) {
		var q          = buscarInput.value.trim();
		var trimestre  = trimestreSelect.value;
		var anio       = anioSelect.value;
		var filtrosQs  = '&trimestre=' + encodeURIComponent(trimestre) + '&anio=' + encodeURIComponent(anio);
		var url = 'getters/listar_historial.php?q=' + encodeURIComponent(q) + filtrosQs +
			'&firma=' + encodeURIComponent(firmaFiltroActual) + '&pg=' + (pagina || 1);

		// El botón de export siempre apunta a lo mismo que está filtrado en
		// pantalla ahora mismo — mismos parámetros que la lista, salvo firma
		// (el Excel es de Cuota/Categoría, no distingue si ya está firmada).
		exportarCuotaLink.href = 'getters/exportar_cuota_categoria.php?q=' + encodeURIComponent(q) + filtrosQs;

		fetch(url)
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) return;
				tbody.innerHTML = data.filas;
				paginacionEl.dataset.pagina = data.pagina;
				paginacionEl.dataset.totalPaginas = data.total_paginas;
				paginacionInfo.innerHTML = 'Mostrando <strong>' + data.mostrando + '</strong> de <strong>' + data.total + '</strong> acuerdos';
				renderPaginacionBtns(data.pagina, data.total_paginas);
				if (data.stats) renderStats(data.stats);
			});
	}

	function renderPaginacionBtns(pagina, totalPaginas) {
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
				if (!btn.disabled) cargarHistorial(parseInt(btn.dataset.pg, 10));
			});
		});
	}

	buscarInput.addEventListener('input', function () {
		clearTimeout(buscarTimeout);
		buscarTimeout = setTimeout(function () { cargarHistorial(1); }, 350);
	});
	trimestreSelect.addEventListener('change', function () { cargarHistorial(1); });
	anioSelect.addEventListener('change', function () { cargarHistorial(1); });
	buscarBtn.addEventListener('click', function () { cargarHistorial(1); });

	// Recarga la página actual sin perder la búsqueda/filtro de mes — a
	// diferencia de "Nuevo Acuerdo", esto no reinicia nada, solo vuelve a
	// pedir los mismos datos por si algo cambió (ej. un Acuerdo generado
	// desde otra pestaña/sesión).
	function refrescarHistorial() {
		cargarHistorial(parseInt(paginacionEl.dataset.pagina, 10) || 1);
	}
	document.getElementById('hist-actualizar').addEventListener('click', refrescarHistorial);
	// Expuesto para que index.php pueda refrescar este módulo automáticamente
	// al navegar hacia él desde el sidebar (ver script inline de index.php).
	window.acHistorialRefrescar = refrescarHistorial;

	function irARegistrar() {
		var link = document.querySelector('.ac-sidebar-nav a[href="#sec-registrar"]');
		if (link) link.click();
	}

	document.getElementById('hist-nuevo-acuerdo').addEventListener('click', irARegistrar);

	// ---------- Mis Borradores ----------
	// El listado y el modal viven acá; cargar el borrador en el formulario lo
	// hace registrar.js (todo el estado de las 4 tablas vive ahí adentro) —
	// solo cambiamos a esa pestaña y le pasamos el id.
	var borraModalOverlay = document.getElementById('hist-borradores-modal-overlay');
	var borraBody = document.getElementById('hist-borradores-body');

	function abrirModalBorradores() {
		borraBody.innerHTML = '<tr><td colspan="5" class="ac-table-empty">Cargando...</td></tr>';
		borraModalOverlay.classList.add('ac-modal-open');
		fetch('getters/listar_borradores.php')
			.then(function (r) { return r.json(); })
			.then(function (data) {
				var borradores = (data.ok && data.borradores) ? data.borradores : [];
				if (!borradores.length) {
					borraBody.innerHTML = '<tr><td colspan="5" class="ac-table-empty">No tenés borradores guardados.</td></tr>';
					return;
				}
				borraBody.innerHTML = borradores.map(function (b) {
					var fecha = new Date(b.updated_at.replace(' ', 'T')).toLocaleString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
					return '<tr>' +
						'<td>#' + escapeHtml(b.documento_no) + '</td>' +
						'<td>' + escapeHtml(b.distribuidor) + '</td>' +
						'<td>' + escapeHtml(b.periodo) + ' ' + b.anio + '</td>' +
						'<td class="ac-tabular">' + fecha + '</td>' +
						'<td class="ac-text-right">' +
							'<div class="ac-row-actions">' +
								'<button type="button" class="ac-btn-continuar" data-id="' + b.id + '">Continuar editando</button>' +
								'<button type="button" class="ac-icon-btn ac-icon-btn-danger ac-btn-eliminar-borrador" data-id="' + b.id + '" data-doc="' + escapeHtml(b.documento_no) + '" title="Eliminar borrador">' +
									'<span class="material-symbols-outlined">delete</span>' +
								'</button>' +
							'</div>' +
						'</td>' +
						'</tr>';
				}).join('');
				Array.prototype.forEach.call(borraBody.querySelectorAll('.ac-btn-continuar'), function (btn) {
					btn.addEventListener('click', function () {
						var id = parseInt(btn.dataset.id, 10);
						cerrarModalBorradores();
						irARegistrar();
						if (window.acRegistrarCargarBorrador) window.acRegistrarCargarBorrador(id);
					});
				});
				Array.prototype.forEach.call(borraBody.querySelectorAll('.ac-btn-eliminar-borrador'), function (btn) {
					btn.addEventListener('click', function () {
						var fila = btn.closest('tr');
						confirmarYEliminarAcuerdo(btn.dataset.id, btn.dataset.doc, function () {
							animarYQuitarFila(fila, 5, 'No tenés borradores guardados.');
						});
					});
				});
			})
			.catch(function () {
				borraBody.innerHTML = '<tr><td colspan="5" class="ac-table-empty">Error al cargar los borradores.</td></tr>';
			});
	}

	function cerrarModalBorradores() {
		borraModalOverlay.classList.remove('ac-modal-open');
	}

	document.getElementById('hist-abrir-borradores').addEventListener('click', abrirModalBorradores);
	document.getElementById('hist-borradores-modal-close').addEventListener('click', cerrarModalBorradores);
	borraModalOverlay.addEventListener('click', function (e) {
		if (e.target === borraModalOverlay) cerrarModalBorradores();
	});

	// ---------- Detalle / Acta (Ver Detalles y Descargar PDF) ----------
	// Mismo PDF real que genera Registrar (getters/generar_acta_pdf.php) —
	// "Ver Detalles" y "Descargar PDF" abren el mismo iframe, no hay una
	// segunda maqueta HTML que reconstruir ni mantener sincronizada.
	function abrirDetalle(id) {
		// &t= evita que el navegador reuse un PDF viejo cacheado con la misma URL ?id=X.
		var url = 'getters/generar_acta_pdf.php?id=' + encodeURIComponent(id) + '&t=' + Date.now();
		pdfFrame.src = url;
		descargarBtn.href = url;
		vistaLista.classList.add('hidden');
		vistaPreview.classList.remove('hidden');
		window.scrollTo(0, 0);
	}

	function eliminarAcuerdo(id, documentoNo) {
		confirmarYEliminarAcuerdo(id, documentoNo, function () {
			cargarHistorial(parseInt(paginacionEl.dataset.pagina, 10) || 1);
		});
	}

	// ---------- Subir/ver Acta firmada (2026-08-21) ----------
	// Modal con 2 paneles lado a lado: el Acta generada (izquierda, siempre
	// de referencia) y el Acta firmada (derecha) — un solo componente sirve
	// tanto para "ver la firma ya subida" como para "elegir una nueva y
	// guardarla", solo cambia el estado inicial del panel derecho.
	var firmaModalOverlay  = document.getElementById('hist-firma-modal-overlay');
	var firmaModalTitle    = document.getElementById('hist-firma-modal-title');
	var firmaOriginalFrame = document.getElementById('hist-firma-original-frame');
	var firmaPreviewArea   = document.getElementById('hist-firma-preview-area');
	var firmaModalHint     = document.getElementById('hist-firma-modal-hint');
	var firmaElegirBtn     = document.getElementById('hist-firma-elegir-btn');
	var firmaGuardarBtn    = document.getElementById('hist-firma-guardar-btn');
	var firmaFileInput     = document.getElementById('hist-firma-file-input');

	var firmaAcuerdoIdActual = null;
	var firmaArchivoElegido  = null;
	var firmaObjectUrl       = null;
	var firmaGuardando       = false; // guarda contra doble click/doble submit al guardar.

	var HTML_BOTON_GUARDAR = '<span class="material-symbols-outlined">save</span> Guardar Acta Firmada';

	function firmaPreviewVacia(mensaje) {
		firmaPreviewArea.innerHTML = '<div class="ac-firma-preview-vacio">' +
			'<span class="material-symbols-outlined">add_a_photo</span>' +
			'<p>' + escapeHtml(mensaje) + '</p></div>';
	}

	function mostrarPreviewArchivoElegido(archivo) {
		if (firmaObjectUrl) URL.revokeObjectURL(firmaObjectUrl);
		firmaObjectUrl = URL.createObjectURL(archivo);
		if (archivo.type === 'application/pdf') {
			firmaPreviewArea.innerHTML = '<iframe title="Vista previa del archivo elegido"></iframe>';
			firmaPreviewArea.querySelector('iframe').src = firmaObjectUrl;
		} else {
			firmaPreviewArea.innerHTML = '<img alt="Vista previa del archivo elegido">';
			firmaPreviewArea.querySelector('img').src = firmaObjectUrl;
		}
	}

	// Foto → <img> (se ajusta/centra con object-fit igual que la vista previa
	// local); PDF → <iframe> (el visor nativo del navegador ya centra y
	// ajusta la página solo). Antes esto siempre usaba <iframe> para lo ya
	// subido — para una imagen, el navegador la muestra a tamaño natural
	// pegada arriba, sin centrar ni ajustar, corregido acá (2026-08-21).
	function mostrarFirmaYaSubida(id, mime) {
		var url = 'getters/descargar_acta_firmada.php?id=' + encodeURIComponent(id) + '&t=' + Date.now();
		if (mime && mime.indexOf('image/') === 0) {
			firmaPreviewArea.innerHTML = '<img alt="Acta firmada ya subida">';
			firmaPreviewArea.querySelector('img').src = url;
		} else {
			firmaPreviewArea.innerHTML = '<iframe title="Acta firmada ya subida"></iframe>';
			firmaPreviewArea.querySelector('iframe').src = url;
		}
	}

	function abrirModalFirma(id, documentoNo, tieneFirma, mime) {
		firmaAcuerdoIdActual = id;
		firmaArchivoElegido = null;
		firmaGuardando = false;
		firmaModalTitle.textContent = 'Acta Firmada — #' + documentoNo;
		firmaOriginalFrame.src = 'getters/generar_acta_pdf.php?id=' + encodeURIComponent(id) + '&t=' + Date.now();
		firmaElegirBtn.disabled = false;
		firmaGuardarBtn.disabled = true;
		firmaGuardarBtn.innerHTML = HTML_BOTON_GUARDAR;

		if (tieneFirma) {
			mostrarFirmaYaSubida(id, mime);
			firmaModalHint.textContent = 'Ya hay un archivo subido. Elige uno nuevo para reemplazarlo.';
		} else {
			firmaPreviewVacia('Selecciona una foto o PDF del Acta firmada para compararla acá');
			firmaModalHint.textContent = 'Sin archivo subido todavía.';
		}
		firmaModalOverlay.classList.add('ac-modal-open');
	}

	function cerrarModalFirma() {
		firmaModalOverlay.classList.remove('ac-modal-open');
		firmaOriginalFrame.src = '';
		if (firmaObjectUrl) { URL.revokeObjectURL(firmaObjectUrl); firmaObjectUrl = null; }
		firmaPreviewArea.innerHTML = '';
		firmaArchivoElegido = null;
		firmaAcuerdoIdActual = null;
	}

	firmaElegirBtn.addEventListener('click', function () {
		firmaFileInput.value = '';
		firmaFileInput.click();
	});

	firmaFileInput.addEventListener('change', function () {
		var archivo = firmaFileInput.files[0];
		if (!archivo) return;
		firmaArchivoElegido = archivo;
		mostrarPreviewArchivoElegido(archivo);
		firmaGuardarBtn.disabled = false;
		firmaModalHint.textContent = archivo.name;
	});

	firmaGuardarBtn.addEventListener('click', function () {
		if (firmaGuardando || !firmaArchivoElegido || !firmaAcuerdoIdActual) return;
		firmaGuardando = true;
		firmaGuardarBtn.disabled = true;
		firmaElegirBtn.disabled = true;
		firmaGuardarBtn.innerHTML = '<span class="material-symbols-outlined">progress_activity</span> Guardando...';

		var formData = new FormData();
		formData.append('id', firmaAcuerdoIdActual);
		formData.append('archivo', firmaArchivoElegido);

		function restaurarBotones() {
			firmaGuardando = false;
			firmaElegirBtn.disabled = false;
			firmaGuardarBtn.disabled = false;
			firmaGuardarBtn.innerHTML = HTML_BOTON_GUARDAR;
		}

		fetch('getters/subir_acta_firmada.php', { method: 'POST', body: formData })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				mostrarToast(data.message, data.ok ? 'success' : 'error');
				if (data.ok) {
					cerrarModalFirma();
					cargarHistorial(parseInt(paginacionEl.dataset.pagina, 10) || 1);
				} else {
					restaurarBotones();
				}
			})
			.catch(function () {
				mostrarToast('Error de conexión. Intenta nuevamente.', 'error');
				restaurarBotones();
			});
	});

	document.getElementById('hist-firma-modal-close').addEventListener('click', cerrarModalFirma);
	firmaModalOverlay.addEventListener('click', function (e) {
		if (e.target === firmaModalOverlay) cerrarModalFirma();
	});

	tbody.addEventListener('click', function (e) {
		var verBtn = e.target.closest('.hist-btn-ver');
		var descargarBtn2 = e.target.closest('.hist-btn-descargar');
		var eliminarBtn = e.target.closest('.hist-btn-eliminar');
		var firmaBtn = e.target.closest('.hist-btn-firma');
		if (verBtn) abrirDetalle(verBtn.dataset.id);
		else if (descargarBtn2) abrirDetalle(descargarBtn2.dataset.id);
		else if (eliminarBtn) eliminarAcuerdo(eliminarBtn.dataset.id, eliminarBtn.dataset.doc);
		else if (firmaBtn) abrirModalFirma(firmaBtn.dataset.id, firmaBtn.dataset.doc, firmaBtn.dataset.tieneFirma === '1', firmaBtn.dataset.mime);
	});

	document.getElementById('hist-volver-lista').addEventListener('click', function () {
		vistaPreview.classList.add('hidden');
		vistaLista.classList.remove('hidden');
		pdfFrame.src = '';
	});

	renderPaginacionBtns(parseInt(paginacionEl.dataset.pagina, 10) || 1, parseInt(paginacionEl.dataset.totalPaginas, 10) || 1);
})();

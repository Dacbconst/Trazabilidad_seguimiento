(function () {
	var vistaLista     = document.getElementById('ac-historial-lista');
	var vistaPreview   = document.getElementById('ac-historial-preview');
	var pdfFrame       = document.getElementById('hist-pdf-frame');
	var descargarBtn   = document.getElementById('hist-descargar-pdf');

	var buscarInput     = document.getElementById('hist-buscar');
	var mesSelect       = document.getElementById('hist-mes');
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

	// ---------- Listado: búsqueda + filtro de mes + paginación ----------
	function cargarHistorial(pagina) {
		var q   = buscarInput.value.trim();
		var mes = mesSelect.value;
		var url = 'getters/listar_historial.php?q=' + encodeURIComponent(q) + '&mes=' + encodeURIComponent(mes) + '&pg=' + (pagina || 1);

		// El botón de export siempre apunta a lo mismo que está filtrado en
		// pantalla ahora mismo — mismos parámetros que la lista.
		exportarCuotaLink.href = 'getters/exportar_cuota_categoria.php?q=' + encodeURIComponent(q) + '&mes=' + encodeURIComponent(mes);

		fetch(url)
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) return;
				tbody.innerHTML = data.filas;
				paginacionEl.dataset.pagina = data.pagina;
				paginacionEl.dataset.totalPaginas = data.total_paginas;
				paginacionInfo.innerHTML = 'Mostrando <strong>' + data.mostrando + '</strong> de <strong>' + data.total + '</strong> acuerdos';
				renderPaginacionBtns(data.pagina, data.total_paginas);
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
	mesSelect.addEventListener('change', function () { cargarHistorial(1); });
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

	tbody.addEventListener('click', function (e) {
		var verBtn = e.target.closest('.hist-btn-ver');
		var descargarBtn2 = e.target.closest('.hist-btn-descargar');
		var eliminarBtn = e.target.closest('.hist-btn-eliminar');
		if (verBtn) abrirDetalle(verBtn.dataset.id);
		else if (descargarBtn2) abrirDetalle(descargarBtn2.dataset.id);
		else if (eliminarBtn) eliminarAcuerdo(eliminarBtn.dataset.id, eliminarBtn.dataset.doc);
	});

	document.getElementById('hist-volver-lista').addEventListener('click', function () {
		vistaPreview.classList.add('hidden');
		vistaLista.classList.remove('hidden');
		pdfFrame.src = '';
	});

	renderPaginacionBtns(parseInt(paginacionEl.dataset.pagina, 10) || 1, parseInt(paginacionEl.dataset.totalPaginas, 10) || 1);
})();

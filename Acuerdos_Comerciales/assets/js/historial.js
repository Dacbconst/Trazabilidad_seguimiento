(function () {
	var vistaLista     = document.getElementById('ac-historial-lista');
	var vistaPreview   = document.getElementById('ac-historial-preview');
	var pdfFrame       = document.getElementById('hist-pdf-frame');
	var descargarBtn   = document.getElementById('hist-descargar-pdf');

	var buscarInput     = document.getElementById('hist-buscar');
	var mesSelect       = document.getElementById('hist-mes');
	var buscarBtn       = document.getElementById('hist-buscar-btn');
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

	// ---------- Listado: búsqueda + filtro de mes + paginación ----------
	function cargarHistorial(pagina) {
		var q   = buscarInput.value.trim();
		var mes = mesSelect.value;
		var url = 'getters/listar_historial.php?q=' + encodeURIComponent(q) + '&mes=' + encodeURIComponent(mes) + '&pg=' + (pagina || 1);

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
						'<td class="ac-text-right"><button type="button" class="ac-btn-continuar" data-id="' + b.id + '">Continuar editando</button></td>' +
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

	tbody.addEventListener('click', function (e) {
		var verBtn = e.target.closest('.hist-btn-ver');
		var descargarBtn2 = e.target.closest('.hist-btn-descargar');
		if (verBtn) abrirDetalle(verBtn.dataset.id);
		else if (descargarBtn2) abrirDetalle(descargarBtn2.dataset.id);
	});

	document.getElementById('hist-volver-lista').addEventListener('click', function () {
		vistaPreview.classList.add('hidden');
		vistaLista.classList.remove('hidden');
		pdfFrame.src = '';
	});

	renderPaginacionBtns(parseInt(paginacionEl.dataset.pagina, 10) || 1, parseInt(paginacionEl.dataset.totalPaginas, 10) || 1);
})();

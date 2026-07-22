(function () {
	var mesesCortos = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
	var mesesLargos = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

	var vistaLista     = document.getElementById('ac-historial-lista');
	var vistaPreview   = document.getElementById('ac-historial-preview');
	var canvas         = document.getElementById('hist-canvas');

	var buscarInput     = document.getElementById('hist-buscar');
	var mesSelect       = document.getElementById('hist-mes');
	var buscarBtn       = document.getElementById('hist-buscar-btn');
	var tbody           = document.getElementById('hist-tabla-body');
	var paginacionEl    = document.getElementById('hist-paginacion');
	var paginacionInfo  = document.getElementById('hist-paginacion-info');
	var paginacionBtns  = document.getElementById('hist-paginacion-btns');
	var buscarTimeout   = null;

	var formatCurr = function (val) {
		return (isNaN(val) ? 0 : val).toLocaleString('en-US', { style: 'currency', currency: 'USD' });
	};

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

	document.getElementById('hist-nuevo-acuerdo').addEventListener('click', function () {
		var link = document.querySelector('.ac-sidebar-nav a[href="#sec-registrar"]');
		if (link) link.click();
	});

	// ---------- Detalle / Acta imprimible (Ver Detalles y Descargar PDF) ----------
	// El PDF todavía no se genera ni se guarda en el servidor (ver CLAUDE.md,
	// pendiente de decidir librería) — "Descargar PDF" arma el mismo Acta que
	// "Generar Acta" en Registrar y abre el diálogo de impresión del navegador
	// (el usuario puede "Guardar como PDF" desde ahí), igual que ya hace el resto
	// de la app con este documento.
	function periodoLargo(mesInicio, mesFin, anio) {
		var txt = mesInicio === mesFin ? mesesLargos[mesInicio] : mesesLargos[mesInicio] + ' - ' + mesesLargos[mesFin];
		return txt + ' ' + anio;
	}

	function sumaValores(valoresMensuales) {
		return Object.keys(valoresMensuales || {}).reduce(function (acc, k) {
			return acc + (parseFloat(valoresMensuales[k]) || 0);
		}, 0);
	}

	function tablaMeta(lineas, mesInicio, mesFin) {
		if (!lineas.length) return '';
		var meses = [];
		for (var m = mesInicio; m <= mesFin; m++) meses.push(m);

		var filas = lineas.map(function (l) {
			var total = sumaValores(l.valores_mensuales);
			var estimado = total * (1 + (parseFloat(l.rebate_pct) || 0));
			var celdas = meses.map(function (m) { return '<td class="ac-text-right">' + formatCurr(parseFloat(l.valores_mensuales[m]) || 0) + '</td>'; }).join('');
			return '<tr><td>' + l.segmento + '</td><td>' + l.categoria + '</td><td>' + l.marca + '</td>' + celdas +
				'<td class="ac-text-right">' + formatCurr(total) + '</td>' +
				'<td class="ac-text-right">' + formatCurr(estimado) + '</td></tr>';
		}).join('');

		return '<h2 class="ac-acuerdo-canvas-subtitle">1. Meta de Compras en Dólares</h2>' +
			'<table class="ac-table ac-table-bordered ac-table-print"><thead><tr><th>Segmento</th><th>Categoría</th><th>Marca</th>' +
			meses.map(function (m) { return '<th class="ac-text-right">' + mesesCortos[m] + '</th>'; }).join('') +
			'<th class="ac-text-right">Total</th><th class="ac-text-right">Valor Est.</th></tr></thead><tbody>' + filas + '</tbody></table>';
	}

	function tablaSegCatMarca(titulo, lineas, calcularTotal) {
		if (!lineas.length) return '';
		var filas = lineas.map(function (l) {
			return '<tr><td>' + l.segmento + '</td><td>' + l.categoria + '</td><td>' + l.marca + '</td>' +
				'<td class="ac-text-right">' + formatCurr(calcularTotal(l)) + '</td></tr>';
		}).join('');
		return '<h2 class="ac-acuerdo-canvas-subtitle">' + titulo + '</h2>' +
			'<table class="ac-table ac-table-bordered ac-table-print"><thead><tr><th>Segmento</th><th>Categoría</th><th>Marca</th><th class="ac-text-right">Pago Total</th></tr></thead><tbody>' + filas + '</tbody></table>';
	}

	function tablaPercha(lineas) {
		if (!lineas.length) return '';
		var filas = lineas.map(function (l) {
			return '<tr><td>' + l.marca + '</td><td class="ac-text-right">' + formatCurr(sumaValores(l.valores_mensuales)) + '</td></tr>';
		}).join('');
		return '<h2 class="ac-acuerdo-canvas-subtitle">3.c. Espacio: Perchas</h2>' +
			'<table class="ac-table ac-table-bordered ac-table-print"><thead><tr><th>Marca</th><th class="ac-text-right">Pago Total</th></tr></thead><tbody>' + filas + '</tbody></table>';
	}

	function construirCanvas(acuerdo) {
		var cantidadMeses = acuerdo.mes_fin - acuerdo.mes_inicio + 1;
		var fecha = acuerdo.fecha_generacion ? new Date(acuerdo.fecha_generacion + 'T00:00:00').toLocaleDateString() : '—';

		var html = '';
		html += '<header class="ac-acuerdo-canvas-header">' +
			'<div class="ac-acuerdo-canvas-doc"><span class="ac-field-label">Documento No:</span>' +
			'<span class="ac-acuerdo-canvas-doc-no">' + acuerdo.documento_no + '</span></div>' +
			'<h1>Acuerdo de Desarrollo de Negocios Canal Directo</h1></header>';
		html += '<section class="ac-acuerdo-canvas-meta">' +
			'<div><span class="ac-field-label">Estimado(a)</span><strong>' + acuerdo.distribuidor + '</strong></div>' +
			'<div><span class="ac-field-label">Localidad</span><strong>' + acuerdo.localidad + '</strong></div>' +
			'<div><span class="ac-field-label">Fecha</span><strong>' + fecha + '</strong></div></section>';
		html += '<p class="ac-acuerdo-canvas-intro">JABONERÍA WILSON S.A. y ' + acuerdo.distribuidor + ' celebran el presente acuerdo de desarrollo de negocios para el fortalecimiento mutuo en el mercado regional.</p>';
		html += '<p><span class="ac-field-label">Periodo del acuerdo:</span> <strong>' + periodoLargo(acuerdo.mes_inicio, acuerdo.mes_fin, acuerdo.anio) + '</strong></p>';

		html += tablaMeta(acuerdo.lineas.meta_compra, acuerdo.mes_inicio, acuerdo.mes_fin);
		html += tablaSegCatMarca('3.a. Extravisibilidad: Cabeceras', acuerdo.lineas.cabecera, function (l) { return sumaValores(l.valores_mensuales); });
		html += tablaSegCatMarca('3.b. Espacio: Rumas', acuerdo.lineas.ruma, function (l) { return (parseFloat(l.valor_mensual_unico) || 0) * cantidadMeses; });
		html += tablaPercha(acuerdo.lineas.percha);

		html += '<section class="ac-acuerdo-canvas-condiciones"><h2>Consideraciones Generales</h2>' +
			'<p>Al cierre de cada mes, usted nos facilitará la información de su inventario. <strong>OBLIGATORIO</strong>.</p>' +
			'<p>La liquidación del acuerdo se realizará al finalizar el periodo. El pago total será reconocido a través de nota de crédito.</p></section>';
		html += '<section class="ac-acuerdo-canvas-firmas">' +
			'<div><div class="ac-acuerdo-firma-linea"></div><strong>Nombre: ________________________________________</strong><span class="ac-field-label">Ejecutivo Comercial</span></div>' +
			'<div><p class="ac-acuerdo-firma-titulo">Jabonería Wilson<br><strong>ACEPTACIÓN POR PARTE DEL CLIENTE</strong></p>' +
			'<p class="ac-field-hint">El CLIENTE declara que ha suscrito este Acuerdo a su entera satisfacción, por lo que nada tiene que reclamar sobre el contenido del mismo.</p>' +
			'<div class="ac-acuerdo-firma-linea"></div><span class="ac-field-label">Firma del Cliente</span></div></section>';

		canvas.innerHTML = html;
	}

	function abrirDetalle(id, imprimirAlAbrir) {
		fetch('getters/obtener_acuerdo.php?id=' + encodeURIComponent(id))
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) { alert(data.message || 'No se pudo cargar el acuerdo.'); return; }
				construirCanvas(data.acuerdo);
				vistaLista.classList.add('hidden');
				vistaPreview.classList.remove('hidden');
				window.scrollTo(0, 0);
				if (imprimirAlAbrir) setTimeout(function () { window.print(); }, 200);
			})
			.catch(function () { alert('Error de conexión. Intenta nuevamente.'); });
	}

	tbody.addEventListener('click', function (e) {
		var verBtn = e.target.closest('.hist-btn-ver');
		var descargarBtn = e.target.closest('.hist-btn-descargar');
		if (verBtn) abrirDetalle(verBtn.dataset.id, false);
		else if (descargarBtn) abrirDetalle(descargarBtn.dataset.id, true);
	});

	document.getElementById('hist-volver-lista').addEventListener('click', function () {
		vistaPreview.classList.add('hidden');
		vistaLista.classList.remove('hidden');
	});
	document.getElementById('hist-imprimir').addEventListener('click', function () {
		window.print();
	});

	renderPaginacionBtns(parseInt(paginacionEl.dataset.pagina, 10) || 1, parseInt(paginacionEl.dataset.totalPaginas, 10) || 1);
})();

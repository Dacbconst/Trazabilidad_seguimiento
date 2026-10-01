// Lista del Calendario de Activaciones: indicadores que filtran, período, canal, búsqueda y filas desplegables; todo en el navegador.
(function () {
	var raiz = document.getElementById('epCal');
	if (!raiz || !raiz.querySelector('.ep-cl-tabla')) return;

	var MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
	var MESES_LARGOS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
	var estado = { kpi: null, canal: '', q: '', desde: '', hasta: '' };

	function iso(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
	function corta(s) { var p = s.split('-'); return (+p[2]) + ' ' + MESES[+p[1] - 1]; }

	var cals = Array.prototype.map.call(raiz.querySelectorAll('.ep-cl-cal'), function (el) {
		var cal = {
			el: el,
			estado: el.dataset.estado,
			canal: el.dataset.canal,
			nombre: el.dataset.nombre,
			total: +el.dataset.total,
			toggle: el.querySelector('.ep-cl-toggle'),
			detalle: el.querySelector('.ep-cl-detalle'),
			chips: Array.prototype.slice.call(el.querySelectorAll('.ep-cl-detalle .ep-cl-chip')),
			vacio: el.querySelector('.ep-cl-f-vacio'),
			barra: el.querySelector('.ep-cl-barra-av span'),
			avTxt: el.querySelector('.ep-cl-av-txt'),
			chip: el.dataset.estado === 'completo' ? 'todas' : 'pendiente',
			visibles: [],
			filas: Array.prototype.map.call(el.querySelectorAll('.ep-cl-f[data-fecha]'), function (f) {
				return { el: f, fecha: f.dataset.fecha, estado: f.dataset.estado, busca: f.dataset.busca };
			}),
		};
		cal.toggle.addEventListener('click', function () { abrirCal(cal, cal.detalle.hidden); });
		cal.chips.forEach(function (b) {
			b.addEventListener('click', function () { cal.chip = b.dataset.f; pintarFilas(cal); });
		});
		return cal;
	});

	function abrirCal(cal, abrir) {
		cal.detalle.hidden = !abrir;
		cal.toggle.setAttribute('aria-expanded', abrir ? 'true' : 'false');
		cal.el.classList.toggle('ep-cl-abierto', abrir);
	}

	function pintarFilas(cal) {
		var cuentas = { todas: cal.visibles.length, cumplido: 0, pendiente: 0 };
		cal.visibles.forEach(function (f) { cuentas[f.estado]++; });
		cal.chips.forEach(function (b) {
			b.setAttribute('aria-pressed', b.dataset.f === cal.chip ? 'true' : 'false');
			b.querySelector('b').textContent = cuentas[b.dataset.f];
		});
		var mostradas = 0;
		cal.filas.forEach(function (f) {
			var ver = cal.visibles.indexOf(f) !== -1 && (cal.chip === 'todas' || f.estado === cal.chip);
			f.el.hidden = !ver;
			if (ver) mostradas++;
		});
		cal.vacio.hidden = mostradas > 0;
	}

	// Avance del período elegido; si el período deja fuera filas, se aclara el total del calendario.
	function pintarAvance(cal, enRango) {
		var hechas = enRango.filter(function (f) { return f.estado === 'cumplido'; }).length;
		var total = enRango.length;
		cal.barra.style.transform = 'scaleX(' + (total ? (hechas / total).toFixed(4) : 0) + ')';
		var txt = hechas + ' de ' + total;
		if (cal.estado === 'incompleto' && total > hechas) txt += ' · <em>faltaron ' + (total - hechas) + '</em>';
		if (total < cal.total) txt += ' <span class="ep-cl-de-total">(de ' + cal.total + ')</span>';
		cal.avTxt.innerHTML = txt;
	}

	var kpis = Array.prototype.slice.call(raiz.querySelectorAll('.ep-cl-kpi'));
	var resumen = document.getElementById('epClResumen');
	var sin = document.getElementById('epClSin');

	// Los indicadores cuentan lo que dejan pasar período, canal y búsqueda; el indicador activo solo decide qué se muestra.
	function aplicar() {
		var q = estado.q;
		var cuentas = { activo: 0, completo: 0, incompleto: 0 };
		var visibles = 0;
		cals.forEach(function (cal) {
			var enRango = cal.filas.filter(function (f) {
				return (!estado.desde || f.fecha >= estado.desde) && (!estado.hasta || f.fecha <= estado.hasta);
			});
			var pasa = !(estado.desde || estado.hasta) || enRango.length > 0;
			if (estado.canal && cal.canal !== estado.canal) pasa = false;
			var porNombre = !q || cal.nombre.indexOf(q) !== -1;
			var conBusqueda = porNombre ? enRango : enRango.filter(function (f) { return f.busca.indexOf(q) !== -1; });
			if (!porNombre && conBusqueda.length === 0) pasa = false;
			if (pasa) cuentas[cal.estado]++;
			var ver = pasa && (!estado.kpi || cal.estado === estado.kpi);
			cal.el.hidden = !ver;
			if (!ver) return;
			visibles++;
			cal.visibles = conBusqueda;
			// Si lo encontrado es un punto o promotor (no el nombre), se despliega con "Todas" para ver la coincidencia.
			if (!porNombre && cal.detalle.hidden) {
				cal.chip = 'todas';
				abrirCal(cal, true);
			}
			pintarAvance(cal, enRango);
			pintarFilas(cal);
		});
		kpis.forEach(function (k) {
			k.querySelector('.ep-cl-kpi-n').textContent = cuentas[k.dataset.kpi];
			k.setAttribute('aria-pressed', estado.kpi === k.dataset.kpi ? 'true' : 'false');
		});
		resumen.textContent = visibles + (visibles === 1 ? ' calendario' : ' calendarios') + (estado.desde || estado.hasta ? ' · ' + textoPeriodo() : '');
		sin.hidden = visibles > 0;
	}

	kpis.forEach(function (k) {
		k.addEventListener('click', function () {
			estado.kpi = estado.kpi === k.dataset.kpi ? null : k.dataset.kpi;
			aplicar();
		});
	});

	var espera = null;
	document.getElementById('epClBuscar').addEventListener('input', function (e) {
		clearTimeout(espera);
		espera = setTimeout(function () { estado.q = e.target.value.trim().toLowerCase(); aplicar(); }, 120);
	});

	// Paneles flotantes (período, canal, "Más"): uno abierto a la vez; se cierran al hacer clic afuera o con Escape.
	var abierto = null;
	function cerrarPanel() {
		if (!abierto) return;
		abierto.panel.hidden = true;
		abierto.boton.setAttribute('aria-expanded', 'false');
		abierto = null;
	}
	function conectar(boton, panel) {
		boton.addEventListener('click', function (e) {
			e.stopPropagation();
			var era = abierto && abierto.panel === panel;
			cerrarPanel();
			if (era) return;
			panel.hidden = false;
			boton.setAttribute('aria-expanded', 'true');
			abierto = { boton: boton, panel: panel };
		});
		panel.addEventListener('click', function (e) { e.stopPropagation(); });
	}
	document.addEventListener('click', cerrarPanel);
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && abierto) { var b = abierto.boton; cerrarPanel(); b.focus(); }
	});

	raiz.querySelectorAll('.ep-cl-pop-mas').forEach(function (pop) {
		var menu = pop.querySelector('.ep-cl-menu');
		conectar(pop.querySelector('.ep-cl-mas'), menu);
		menu.addEventListener('click', function (e) { if (e.target.closest('button')) cerrarPanel(); });
	});

	// Canal
	var canalBtn = document.getElementById('epClCanalBtn');
	var canalPanel = document.getElementById('epClCanal');
	conectar(canalBtn, canalPanel);
	canalPanel.querySelectorAll('[data-canal]').forEach(function (op) {
		op.addEventListener('click', function () {
			estado.canal = op.dataset.canal;
			canalPanel.querySelectorAll('[data-canal]').forEach(function (o) { o.setAttribute('aria-selected', o === op ? 'true' : 'false'); });
			document.getElementById('epClCanalTxt').textContent = op.textContent;
			canalBtn.classList.toggle('ep-cl-activo', !!estado.canal);
			cerrarPanel();
			aplicar();
		});
	});

	// Período: atajos, meses (uno o dos seguidos marcan un rango) y rango personalizado
	var perBtn = document.getElementById('epClPeriodoBtn');
	var perPanel = document.getElementById('epClPeriodo');
	var inDesde = document.getElementById('epClDesde');
	var inHasta = document.getElementById('epClHasta');
	var cajaMeses = document.getElementById('epClMeses');
	conectar(perBtn, perPanel);

	var hoy = new Date();
	var meses = [];
	for (var i = 7; i >= 0; i--) {
		var d = new Date(hoy.getFullYear(), hoy.getMonth() - i, 1);
		meses.push({ desde: iso(d), hasta: iso(new Date(d.getFullYear(), d.getMonth() + 1, 0)), anio: d.getFullYear(), mes: d.getMonth() });
	}
	var elegidos = [];
	meses.forEach(function (m, idx) {
		var b = document.createElement('button');
		b.type = 'button';
		b.textContent = MESES[m.mes] + (m.anio !== hoy.getFullYear() ? ' ' + String(m.anio).slice(2) : '');
		b.setAttribute('aria-label', MESES_LARGOS[m.mes] + ' ' + m.anio);
		b.setAttribute('aria-pressed', 'false');
		b.addEventListener('click', function () {
			elegidos = elegidos.length === 1 && elegidos[0] !== idx ? [Math.min(elegidos[0], idx), Math.max(elegidos[0], idx)] : [idx];
			inDesde.value = meses[elegidos[0]].desde;
			inHasta.value = meses[elegidos[elegidos.length - 1]].hasta;
			marcarMeses();
		});
		cajaMeses.appendChild(b);
	});
	function marcarMeses() {
		Array.prototype.forEach.call(cajaMeses.children, function (b, idx) {
			var dentro = elegidos.length && idx >= elegidos[0] && idx <= elegidos[elegidos.length - 1];
			b.setAttribute('aria-pressed', dentro ? 'true' : 'false');
		});
	}

	function textoPeriodo() {
		if (!estado.desde && !estado.hasta) return 'Todas las fechas';
		if (!estado.hasta) return 'Desde el ' + corta(estado.desde);
		if (!estado.desde) return 'Hasta el ' + corta(estado.hasta);
		var anio = estado.hasta.slice(0, 4) !== String(hoy.getFullYear()) || estado.desde.slice(0, 4) !== estado.hasta.slice(0, 4) ? ' ' + estado.hasta.slice(0, 4) : '';
		return corta(estado.desde) + ' – ' + corta(estado.hasta) + anio;
	}
	function fijarPeriodo(desde, hasta) {
		if (desde && hasta && desde > hasta) { var t = desde; desde = hasta; hasta = t; }
		estado.desde = desde || '';
		estado.hasta = hasta || '';
		inDesde.value = estado.desde;
		inHasta.value = estado.hasta;
		document.getElementById('epClPeriodoTxt').textContent = textoPeriodo();
		perBtn.classList.toggle('ep-cl-activo', !!(estado.desde || estado.hasta));
		cerrarPanel();
		aplicar();
	}

	perPanel.querySelectorAll('[data-atajo]').forEach(function (b) {
		b.addEventListener('click', function () {
			elegidos = [];
			marcarMeses();
			if (b.dataset.atajo === 'mes') fijarPeriodo(meses[7].desde, meses[7].hasta);
			else if (b.dataset.atajo === 'anterior') fijarPeriodo(meses[6].desde, meses[6].hasta);
			else fijarPeriodo(iso(new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate() - 29)), iso(hoy));
		});
	});
	[inDesde, inHasta].forEach(function (inp) { inp.addEventListener('input', function () { elegidos = []; marcarMeses(); }); });
	document.getElementById('epClAplicar').addEventListener('click', function () { fijarPeriodo(inDesde.value, inHasta.value); });
	document.getElementById('epClLimpiar').addEventListener('click', function () { elegidos = []; marcarMeses(); fijarPeriodo('', ''); });

	// ---- En vivo: la lista se actualiza sola cuando un promotor cumple una fila o cambia un calendario ----
	function actualizarEnVivo(d) {
		var mapa = {};
		d.cals.forEach(function (c) { mapa[c.id] = c; });
		var igual = d.cals.length === cals.length && cals.every(function (cal) {
			var c = mapa[cal.el.dataset.id];
			return c && c.vista === cal.estado && c.total === cal.total;
		});
		if (!igual) {
			// Un calendario nuevo, cerrado, reactivado o eliminado cambia botones y estados: se recarga, salvo que haya un modal abierto.
			var modal = document.getElementById('epCalModal');
			if (modal && !modal.classList.contains('hidden')) return false;
			window.location.reload();
			return true;
		}
		var cambio = false;
		cals.forEach(function (cal) {
			var nuevos = mapa[cal.el.dataset.id].filas;
			cal.filas.forEach(function (f) {
				var e = nuevos[f.el.dataset.filaId];
				if (!e || e === f.estado) return;
				f.estado = e;
				f.el.dataset.estado = e;
				cambio = true;
				[f.el, cal.avTxt, cal.el.querySelector('.ep-cl-nombre')].forEach(function (x) {
					if (!x) return;
					x.classList.remove('ep-cl-destello');
					void x.offsetWidth;
					x.classList.add('ep-cl-destello');
				});
			});
		});
		if (cambio) aplicar();
	}
	document.addEventListener('DOMContentLoaded', function () {
		if (window.epVivo) window.epVivo({ url: 'getters/calendario_vivo.php', indicador: 'epClVivo', cada: 4000, alCambiar: actualizarEnVivo });
	});

	aplicar();
})();

// Modal "Exportar a Excel" de Historial: fecha (única/rango), supervisor (admin, opcional), promotor (cascada del supervisor, opcional) y tipo de actividad.
(function () {
	var overlay = document.getElementById('epHexOverlay');
	var btnAbrir = document.getElementById('epHexAbrir');
	if (!overlay || !btnAbrir) return;

	var datos = { supervisores: [], promotoresPorSupervisor: {} };
	try { datos = JSON.parse(document.getElementById('epHexDatos').textContent); } catch (e) {}

	var pillUnica = document.getElementById('epHexPillUnica');
	var pillRango = document.getElementById('epHexPillRango');
	var fechaDesde = document.getElementById('epHexFechaDesde');
	var fechaHasta = document.getElementById('epHexFechaHasta');
	var fechaSep = document.getElementById('epHexFechaSep');
	var fechaCaja = document.querySelector('.ep-hex-fecha-caja');
	var actGrid = document.getElementById('epHexActGrid');
	var btnDescargar = document.getElementById('epHexDescargar');

	var estado = { modo: 'unica', tipo: '', supervisorId: '', supervisorNombre: '', promotor: '' };

	function abrir() {
		overlay.classList.remove('hidden');
		document.body.classList.add('ep-pdv-abierto');
	}
	function cerrar() {
		overlay.classList.add('hidden');
		document.body.classList.remove('ep-pdv-abierto');
	}
	btnAbrir.addEventListener('click', abrir);
	document.getElementById('epHexCerrar').addEventListener('click', cerrar);
	document.getElementById('epHexCancelar').addEventListener('click', cerrar);
	overlay.addEventListener('click', function (ev) { if (ev.target === overlay) cerrar(); });

	function ponerModo(modo) {
		estado.modo = modo;
		pillUnica.classList.toggle('active', modo === 'unica');
		pillRango.classList.toggle('active', modo === 'rango');
		fechaHasta.classList.toggle('hidden', modo === 'unica');
		fechaSep.classList.toggle('hidden', modo === 'unica');
	}
	pillUnica.addEventListener('click', function () { ponerModo('unica'); });
	pillRango.addEventListener('click', function () { ponerModo('rango'); });

	// Tipo de actividad: tarjetas de selección única.
	actGrid.addEventListener('click', function (ev) {
		var card = ev.target.closest('.ep-hex-act-card');
		if (!card) return;
		actGrid.querySelectorAll('.ep-hex-act-card').forEach(function (c) { c.classList.remove('selected'); });
		card.classList.add('selected');
		estado.tipo = card.dataset.tipo || '';
	});

	// Combo genérico (supervisor / promotor): abre su menú, pinta opciones, cierra los demás.
	function armarCombo(idCombo, idTrigger, idMenu, opciones, alElegir) {
		var combo = document.getElementById(idCombo);
		if (!combo) return null;
		var trigger = document.getElementById(idTrigger);
		var menu = document.getElementById(idMenu);
		function pintar() {
			menu.innerHTML = opciones().map(function (o) {
				return '<button type="button" class="ep-hex-combo-opt' + (o.sel ? ' selected' : '') + '" data-valor="' + o.valor + '">' + o.etiqueta + '</button>';
			}).join('');
		}
		trigger.addEventListener('click', function (ev) {
			ev.stopPropagation();
			var abrir2 = menu.classList.contains('hidden');
			document.querySelectorAll('.ep-hex-combo-menu').forEach(function (m) { m.classList.add('hidden'); });
			if (abrir2) { pintar(); menu.classList.remove('hidden'); }
		});
		menu.addEventListener('click', function (ev) {
			var b = ev.target.closest('.ep-hex-combo-opt');
			if (!b) return;
			alElegir(b.dataset.valor, b.textContent);
			menu.classList.add('hidden');
		});
		return { pintar: pintar };
	}

	var comboPromotor = armarCombo('epHexComboPromotor', 'epHexPromotorTrigger', 'epHexPromotorMenu', function () {
		var lista = estado.supervisorId && datos.promotoresPorSupervisor[estado.supervisorId] ? datos.promotoresPorSupervisor[estado.supervisorId] : [].concat.apply([], Object.keys(datos.promotoresPorSupervisor).map(function (k) { return datos.promotoresPorSupervisor[k]; }));
		var unicos = lista.filter(function (n, i) { return lista.indexOf(n) === i; }).sort();
		return [{ valor: '', etiqueta: 'Todos', sel: estado.promotor === '' }].concat(unicos.map(function (n) { return { valor: n, etiqueta: n, sel: estado.promotor === n }; }));
	}, function (valor, texto) {
		estado.promotor = valor;
		var trig = document.getElementById('epHexPromotorTrigger');
		trig.querySelector('span').textContent = texto;
		trig.classList.toggle('con-valor', !!valor);
	});

	if (document.getElementById('epHexComboSupervisor')) {
		armarCombo('epHexComboSupervisor', 'epHexSupervisorTrigger', 'epHexSupervisorMenu', function () {
			return [{ valor: '', etiqueta: 'Todos', sel: estado.supervisorId === '' }].concat(datos.supervisores.map(function (s) { return { valor: s.id, etiqueta: s.nombre, sel: String(estado.supervisorId) === String(s.id) }; }));
		}, function (valor, texto) {
			estado.supervisorId = valor;
			estado.promotor = '';
			var trig = document.getElementById('epHexSupervisorTrigger');
			trig.querySelector('span').textContent = texto;
			trig.classList.toggle('con-valor', !!valor);
			var trigProm = document.getElementById('epHexPromotorTrigger');
			trigProm.querySelector('span').textContent = 'Todos';
			trigProm.classList.remove('con-valor');
		});
	}
	document.addEventListener('click', function () {
		document.querySelectorAll('.ep-hex-combo-menu').forEach(function (m) { m.classList.add('hidden'); });
	});

	fechaDesde.addEventListener('input', function () { fechaCaja.classList.remove('error'); });
	fechaHasta.addEventListener('input', function () { fechaCaja.classList.remove('error'); });

	btnDescargar.addEventListener('click', function (ev) {
		// Fecha obligatoria: en rango, desde y hasta; en única, solo desde.
		if (!fechaDesde.value || (estado.modo === 'rango' && !fechaHasta.value)) {
			ev.preventDefault();
			fechaCaja.classList.add('error');
			return;
		}
		var params = new URLSearchParams();
		if (estado.tipo) params.set('tipo', estado.tipo);
		if (fechaDesde.value) params.set('desde', fechaDesde.value);
		if (estado.modo === 'rango' && fechaHasta.value) params.set('hasta', fechaHasta.value);
		if (estado.supervisorId) params.set('supervisor_id', estado.supervisorId);
		if (estado.promotor) params.set('promotor', estado.promotor);
		btnDescargar.href = 'getters/historial_excel.php?' + params.toString();
		cerrar();
	});
})();

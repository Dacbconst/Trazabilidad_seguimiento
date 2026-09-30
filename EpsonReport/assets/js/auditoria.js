// Auditoría: búsqueda, filtros por usuario y acción, periodos rápidos y rango de fechas (todo en el navegador).
(function () {
	var root = document.getElementById('epAu');
	var lista = document.getElementById('epAuLista');
	if (!root || !lista) return;

	var eventos = Array.prototype.slice.call(lista.querySelectorAll('.ep-au-ev'));
	var dias = Array.prototype.slice.call(lista.querySelectorAll('.ep-au-dia'));
	var sin = document.getElementById('epAuSin');
	var resumen = document.getElementById('epAuResumen');
	var rapidos = document.getElementById('epAuRapidos');
	var inpDesde = document.getElementById('epAuDesde');
	var inpHasta = document.getElementById('epAuHasta');
	var estado = { q: '', usuario: 'all', accion: 'all', desde: '', hasta: '' };

	function coincide(ev) {
		if (estado.usuario !== 'all' && ev.dataset.usuario !== estado.usuario) return false;
		if (estado.accion !== 'all' && ev.dataset.accion !== estado.accion) return false;
		if (estado.desde && ev.dataset.fecha < estado.desde) return false;
		if (estado.hasta && ev.dataset.fecha > estado.hasta) return false;
		return estado.q === '' || ev.dataset.busqueda.indexOf(estado.q) !== -1;
	}

	function aplicar() {
		var total = 0;
		eventos.forEach(function (ev) {
			var ok = coincide(ev);
			if (ok) total++;
			ev.classList.toggle('hidden', !ok);
			ev.classList.remove('ep-au-primero');
		});
		// Cada día muestra cuántos quedan y su primera fila visible pierde la línea de arriba.
		dias.forEach(function (d) {
			var visibles = d.querySelectorAll('.ep-au-ev:not(.hidden)');
			if (visibles.length) visibles[0].classList.add('ep-au-primero');
			d.classList.toggle('hidden', visibles.length === 0);
			d.querySelector('.ep-au-dia-head em').textContent = visibles.length + (visibles.length === 1 ? ' movimiento' : ' movimientos');
		});
		sin.classList.toggle('hidden', total > 0);
		resumen.textContent = total + (total === 1 ? ' movimiento' : ' movimientos') + ' · quién hizo qué y cuándo';
	}

	function opcionesDe(campo, textoTodos) {
		var cuentas = {};
		eventos.forEach(function (ev) { cuentas[ev.dataset[campo]] = (cuentas[ev.dataset[campo]] || 0) + 1; });
		var opciones = Object.keys(cuentas).sort(function (a, b) { return cuentas[b] - cuentas[a] || a.localeCompare(b); }).map(function (n) { return { valor: n, etiqueta: n, cuenta: cuentas[n] }; });
		return [{ valor: 'all', etiqueta: textoTodos, cuenta: eventos.length }].concat(opciones);
	}

	function elegir(campo, valor) {
		estado[campo] = valor;
		comboUsuario.pintar();
		comboAccion.pintar();
		aplicar();
	}

	var comboUsuario = epFiltros.crearCombo(root, 'epAuComboUsuario', function () { return opcionesDe('usuario', 'Todos los usuarios'); }, function () { return estado.usuario; }, function (v) { elegir('usuario', v); });
	var comboAccion = epFiltros.crearCombo(root, 'epAuComboAccion', function () { return opcionesDe('accion', 'Todas las acciones'); }, function () { return estado.accion; }, function (v) { elegir('accion', v); });

	// ---- Periodos rápidos y rango de fechas, igual que en Historial ----
	function isoLocal(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
	function rangoRapido(k) {
		var hoy = new Date();
		var desde = new Date(hoy);
		if (k === 'semana') desde.setDate(hoy.getDate() - ((hoy.getDay() + 6) % 7));
		else if (k === 'mes') desde.setDate(1);
		return k === 'todo' ? ['', ''] : [isoLocal(desde), isoLocal(hoy)];
	}
	function fijarFechas(desde, hasta, rapido) {
		estado.desde = desde;
		estado.hasta = hasta;
		inpDesde.value = desde;
		inpHasta.value = hasta;
		rapidos.querySelectorAll('button').forEach(function (b) { b.classList.toggle('selected', b.dataset.rapido === rapido); });
		aplicar();
	}

	rapidos.addEventListener('click', function (ev) {
		var b = ev.target.closest('button');
		if (!b) return;
		var r = rangoRapido(b.dataset.rapido);
		fijarFechas(r[0], r[1], b.dataset.rapido);
	});
	[inpDesde, inpHasta].forEach(function (inp) {
		inp.addEventListener('change', function () { fijarFechas(inpDesde.value, inpHasta.value, ''); });
	});
	var cajaFecha = document.getElementById('epAuComboFecha');
	cajaFecha.querySelector('.ep-fl-combo-btn').addEventListener('click', function () {
		var p = cajaFecha.querySelector('.ep-fl-combo-panel');
		var abrir = p.classList.contains('hidden');
		epFiltros.cerrarCombos(root, cajaFecha);
		p.classList.toggle('hidden', !abrir);
	});

	document.getElementById('epAuBuscar').addEventListener('input', function (ev) {
		estado.q = ev.target.value.trim().toLowerCase();
		aplicar();
	});

	comboUsuario.pintar();
	comboAccion.pintar();
	aplicar();
})();

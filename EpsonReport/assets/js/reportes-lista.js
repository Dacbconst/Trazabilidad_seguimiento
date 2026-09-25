// Lista de reportes guardados: búsqueda y filtros por actividad y mes (la descarga y el borrado viven en reportes.js).
(function () {
	var root = document.getElementById('epRp');
	var lista = document.getElementById('epRpLista');
	if (!root || !lista) return;

	var tarjetas = Array.prototype.slice.call(lista.querySelectorAll('.ep-rp-card'));
	var grupos = Array.prototype.slice.call(lista.querySelectorAll('.ep-rp-grupo'));
	var sin = document.getElementById('epRpSin');
	var resumen = document.getElementById('epRpResumen');
	var estado = { q: '', act: 'all', mes: 'all' };

	function coincide(t) {
		if (estado.act !== 'all' && t.dataset.actividad !== estado.act) return false;
		if (estado.mes !== 'all' && t.dataset.mes !== estado.mes) return false;
		return estado.q === '' || t.dataset.busqueda.indexOf(estado.q) !== -1;
	}

	function aplicar() {
		var total = 0;
		tarjetas.forEach(function (t) {
			var ok = coincide(t);
			if (ok) total++;
			t.classList.toggle('hidden', !ok);
		});
		grupos.forEach(function (g) {
			var n = 0;
			for (var sig = g.nextElementSibling; sig && !sig.classList.contains('ep-rp-grupo'); sig = sig.nextElementSibling) {
				if (sig.classList.contains('ep-rp-card') && !sig.classList.contains('hidden')) n++;
			}
			g.classList.toggle('hidden', n === 0);
			g.querySelector('em').textContent = n + (n === 1 ? ' reporte' : ' reportes');
		});
		sin.classList.toggle('hidden', total > 0);
		resumen.textContent = total + (total === 1 ? ' reporte' : ' reportes') + ' · listos para descargar en PowerPoint';
	}

	function elegir(campo, valor) {
		estado[campo] = valor;
		comboAct.pintar();
		comboMes.pintar();
		aplicar();
	}

	var comboAct = epFiltros.crearCombo(root, 'epRpComboAct', function () {
		var cuentas = {};
		var iconos = {};
		tarjetas.forEach(function (t) {
			cuentas[t.dataset.actividad] = (cuentas[t.dataset.actividad] || 0) + 1;
			iconos[t.dataset.actividad] = t.querySelector('.ep-fl-ico').innerHTML;
		});
		var opciones = Object.keys(cuentas).sort(function (a, b) { return cuentas[b] - cuentas[a] || a.localeCompare(b); }).map(function (n) { return { valor: n, etiqueta: n, cuenta: cuentas[n], ico: iconos[n] }; });
		return [{ valor: 'all', etiqueta: 'Todas las actividades', cuenta: tarjetas.length }].concat(opciones);
	}, function () { return estado.act; }, function (v) { elegir('act', v); });

	var comboMes = epFiltros.crearCombo(root, 'epRpComboMes', function () {
		var cuentas = {};
		tarjetas.forEach(function (t) { cuentas[t.dataset.mes] = (cuentas[t.dataset.mes] || 0) + 1; });
		var etiquetas = {};
		grupos.forEach(function (g) { etiquetas[g.dataset.mes] = g.dataset.mesLabel; });
		var opciones = Object.keys(cuentas).sort().reverse().map(function (m) { return { valor: m, etiqueta: etiquetas[m] || m, cuenta: cuentas[m] }; });
		return [{ valor: 'all', etiqueta: 'Todos los meses', cuenta: tarjetas.length }].concat(opciones);
	}, function () { return estado.mes; }, function (v) { elegir('mes', v); });

	document.getElementById('epRpBuscar').addEventListener('input', function (ev) {
		estado.q = ev.target.value.trim().toLowerCase();
		aplicar();
	});

	comboAct.pintar();
	comboMes.pintar();
	aplicar();
})();

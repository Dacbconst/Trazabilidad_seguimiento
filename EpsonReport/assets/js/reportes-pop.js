// Reporte mensual de Colocación de POP: tabla "POP recibido en bodega" del paso final (la usa reportes.js).
(function () {
	var caja = document.getElementById('epRpPopBodega');
	var cuerpo = document.getElementById('epRpPopFilas');
	if (!caja || !cuerpo) return;

	function esc(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; }

	// Agrupa lo entregado por campaña + material; el canal del punto de venta decide si suma a Retail o a Canales.
	function agrupar(registros) {
		var grupos = {};
		registros.forEach(function (r) {
			var retail = String(r.canal || '').toUpperCase() === 'RETAIL';
			(r.pop_entregas || []).forEach(function (e) {
				var clave = (r.campana || '') + '|' + e.material;
				var g = grupos[clave] || (grupos[clave] = { clave: clave, material: e.material, campana: r.campana || '', canales: 0, retail: 0 });
				g[retail ? 'retail' : 'canales'] += parseInt(e.cantidad, 10) || 0;
			});
		});
		return Object.keys(grupos).sort().map(function (k) { return grupos[k]; });
	}

	function recalcular(fila) {
		var bodega = parseInt(fila.querySelector('input').value, 10) || 0;
		var disponible = bodega - parseInt(fila.dataset.canales, 10) - parseInt(fila.dataset.retail, 10);
		var celda = fila.querySelector('.ep-rp-pop-disp');
		celda.textContent = disponible;
		celda.classList.toggle('ep-rp-pop-negativo', disponible < 0);
	}

	// Pinta la tabla para los registros elegidos; conserva lo ya escrito si se vuelve a este paso.
	function pintar(registros) {
		var previos = valores();
		cuerpo.innerHTML = agrupar(registros).map(function (g) {
			return '<tr data-clave="' + esc(g.clave) + '" data-canales="' + g.canales + '" data-retail="' + g.retail + '">'
				+ '<td>' + esc(g.material) + '</td><td>' + esc(g.campana) + '</td>'
				+ '<td><input type="number" min="0" class="ep-input" inputmode="numeric" placeholder="0" value="' + (previos[g.clave] != null ? previos[g.clave] : '') + '" aria-label="Bodega de ' + esc(g.material) + '"></td>'
				+ '<td>' + g.canales + '</td><td>' + g.retail + '</td><td class="ep-rp-pop-disp"></td></tr>';
		}).join('');
		cuerpo.querySelectorAll('tr').forEach(recalcular);
	}

	function valores() {
		var v = {};
		cuerpo.querySelectorAll('tr').forEach(function (fila) {
			var n = fila.querySelector('input').value;
			if (n !== '') v[fila.dataset.clave] = parseInt(n, 10) || 0;
		});
		return v;
	}

	// Todas las filas necesitan su número de bodega; marca en rojo las vacías.
	function faltantes() {
		var vacias = Array.prototype.filter.call(cuerpo.querySelectorAll('input'), function (i) { return i.value.trim() === ''; });
		vacias.forEach(function (i) { i.classList.add('ep-campo-error'); });
		return vacias.length;
	}

	cuerpo.addEventListener('input', function (ev) {
		var fila = ev.target.closest('tr');
		if (!fila) return;
		ev.target.classList.remove('ep-campo-error');
		recalcular(fila);
	});

	window.epRpPop = {
		mostrar: function (visible, registros) {
			caja.classList.toggle('hidden', !visible);
			if (visible) pintar(registros || []);
		},
		valores: valores,
		faltantes: faltantes,
		limpiar: function () { cuerpo.innerHTML = ''; }
	};
})();

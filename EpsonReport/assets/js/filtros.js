// Selectores con buscador (actividad, promotor, mes...) compartidos por Historial y Reportes mensuales.
(function () {
	function cerrarCombos(root, salvo) {
		root.querySelectorAll('.ep-fl-combo').forEach(function (c) {
			if (c === salvo) return;
			c.querySelector('.ep-fl-combo-panel').classList.add('hidden');
			c.querySelector('.ep-fl-combo-btn').setAttribute('aria-expanded', 'false');
		});
	}

	// opciones(): [{valor, etiqueta, cuenta, ico?}] con "all" primero; valorDe(): valor elegido; alElegir(valor).
	function crearCombo(root, id, opciones, valorDe, alElegir) {
		var caja = document.getElementById(id);
		if (!caja) return { pintar: function () {} };
		var boton = caja.querySelector('.ep-fl-combo-btn');
		var panel = caja.querySelector('.ep-fl-combo-panel');
		var lista = caja.querySelector('.ep-fl-combo-lista');
		var buscar = caja.querySelector('.ep-fl-combo-buscar input');
		var etiqueta = caja.querySelector('.ep-fl-combo-valor');
		var textoTodos = etiqueta ? etiqueta.textContent : '';

		function pintar() {
			var actual = valorDe();
			var todas = opciones();
			lista.innerHTML = '';
			todas.forEach(function (o) {
				var b = document.createElement('button');
				b.type = 'button';
				b.className = 'ep-fl-opt' + (o.valor === actual ? ' ep-fl-opt-sel' : '');
				b.dataset.valor = o.valor;
				b.dataset.q = o.etiqueta.toLowerCase();
				if (o.ico) {
					var ico = document.createElement('span');
					ico.className = 'ep-fl-ico ep-fl-opt-ico';
					ico.innerHTML = o.ico;
					b.appendChild(ico);
				}
				var txt = document.createElement('span');
				txt.textContent = o.etiqueta;
				b.appendChild(txt);
				var n = document.createElement('em');
				n.textContent = o.cuenta;
				b.appendChild(n);
				lista.appendChild(b);
			});
			var elegida = todas.filter(function (o) { return o.valor === actual; })[0];
			if (etiqueta) etiqueta.textContent = actual === 'all' || !elegida ? textoTodos : elegida.etiqueta;
		}

		function filtrar(q) {
			lista.querySelectorAll('.ep-fl-opt').forEach(function (b) { b.classList.toggle('hidden', q !== '' && b.dataset.q.indexOf(q) === -1); });
		}

		boton.addEventListener('click', function () {
			var abrir = panel.classList.contains('hidden');
			cerrarCombos(root, caja);
			panel.classList.toggle('hidden', !abrir);
			boton.setAttribute('aria-expanded', abrir ? 'true' : 'false');
			if (abrir && buscar) { buscar.value = ''; filtrar(''); buscar.focus(); }
		});
		if (buscar) buscar.addEventListener('input', function () { filtrar(buscar.value.trim().toLowerCase()); });
		lista.addEventListener('click', function (ev) {
			var b = ev.target.closest('.ep-fl-opt');
			if (!b) return;
			alElegir(b.dataset.valor);
			cerrarCombos(root, null);
		});
		return { pintar: pintar };
	}

	document.addEventListener('click', function (ev) {
		if (!ev.target.closest('.ep-fl-combo')) cerrarCombos(document, null);
	});

	window.epFiltros = { crearCombo: crearCombo, cerrarCombos: cerrarCombos };
})();

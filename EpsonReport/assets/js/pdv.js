// Selector de punto de venta: búsqueda, filtro de canal y teclado. Expone window.epPdv.elegido() para app.js.
window.epPdv = { elegido: function () { return null; } };
(function () {
	var raiz = document.getElementById('ep-pdv');
	if (!raiz) return;
	var MAX_FILAS = 60;
	var trigger = document.getElementById('ep-pdv-trigger');
	var panel = document.getElementById('ep-pdv-panel');
	var fondo = document.getElementById('ep-pdv-fondo');
	var buscador = document.getElementById('ep-pdv-buscar');
	var lista = document.getElementById('ep-pdv-lista');
	var pie = document.getElementById('ep-pdv-pie');
	var titulo = document.getElementById('ep-pdv-titulo');
	var sub = document.getElementById('ep-pdv-sub');
	var puntos = [];
	try { puntos = JSON.parse(document.getElementById('ep-pdv-datos').textContent); } catch (e) { puntos = []; }

	var estado = { elegido: null, canal: '', visibles: [], activo: -1 };
	window.epPdv.elegido = function () { return estado.elegido; };

	var ACENTOS = new RegExp('[' + String.fromCharCode(0x300) + '-' + String.fromCharCode(0x36f) + ']', 'g');
	function normalizar(t) { return String(t || '').toLowerCase().normalize('NFD').replace(ACENTOS, ''); }
	function escapar(t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	function nombreCanal(c) { return c ? c.charAt(0) + c.slice(1).toLowerCase() : ''; }
	var SEP = ' ' + String.fromCharCode(183) + ' ';
	function meta(p) { return [p.ciudad, p.cadena].filter(function (x) { return x && x !== '-'; }).join(SEP); }

	// Resalta lo que el usuario escribió dentro del nombre.
	function resaltar(texto, consulta) {
		var t = escapar(texto);
		var q = normalizar(consulta).trim();
		if (!q) return t;
		var i = normalizar(texto).indexOf(q);
		if (i < 0) return t;
		return escapar(texto.slice(0, i)) + '<mark>' + escapar(texto.slice(i, i + q.length)) + '</mark>' + escapar(texto.slice(i + q.length));
	}

	function filtrar() {
		var palabras = normalizar(buscador.value).split(/\s+/).filter(Boolean);
		estado.visibles = puntos.filter(function (p) {
			if (estado.canal && p.canal !== estado.canal) return false;
			var pajar = normalizar(p.nombre + ' ' + p.ciudad + ' ' + p.cadena);
			return palabras.every(function (w) { return pajar.indexOf(w) !== -1; });
		});
		estado.activo = estado.visibles.length ? 0 : -1;
	}

	function dibujar() {
		var mostrar = estado.visibles.slice(0, MAX_FILAS);
		if (!mostrar.length) {
			lista.innerHTML = '<div class="ep-pdv-vacio">No hay puntos que coincidan.<br><small>Prueba con otra palabra o cambia el canal.</small></div>';
			pie.hidden = true;
			return;
		}
		lista.innerHTML = mostrar.map(function (p, i) {
			var sel = estado.elegido && estado.elegido.pos_id === p.pos_id;
			return '<div class="ep-pdv-fila' + (i === estado.activo ? ' activa' : '') + (sel ? ' elegida' : '') + '" role="option" id="ep-pdv-op-' + i + '" data-i="' + i + '" aria-selected="' + (sel ? 'true' : 'false') + '">'
				+ '<span class="ep-pdv-fila-texto"><span class="ep-pdv-fila-nombre">' + resaltar(p.nombre, buscador.value) + '</span><span class="ep-pdv-fila-meta">' + escapar(meta(p) || 'Sin ciudad') + '</span></span>'
				+ '<span class="ep-pdv-etiqueta-canal">' + escapar(nombreCanal(p.canal)) + '</span></div>';
		}).join('');
		var total = estado.visibles.length;
		pie.textContent = total > MAX_FILAS ? 'Sigue escribiendo para acotar los resultados.' : '';
		pie.hidden = total <= MAX_FILAS;
		buscador.setAttribute('aria-activedescendant', estado.activo >= 0 ? 'ep-pdv-op-' + estado.activo : '');
	}

	function refrescar() { filtrar(); dibujar(); }

	function mover(delta) {
		var max = Math.min(estado.visibles.length, MAX_FILAS) - 1;
		if (max < 0) return;
		estado.activo = Math.min(max, Math.max(0, estado.activo + delta));
		dibujar();
		var fila = document.getElementById('ep-pdv-op-' + estado.activo);
		if (fila) fila.scrollIntoView({ block: 'nearest' });
	}

	function abrir() {
		panel.hidden = false;
		fondo.hidden = false;
		trigger.setAttribute('aria-expanded', 'true');
		document.body.classList.add('ep-pdv-abierto');
		refrescar();
		buscador.focus({ preventScroll: true });
	}

	function cerrar(devolverFoco) {
		panel.hidden = true;
		fondo.hidden = true;
		trigger.setAttribute('aria-expanded', 'false');
		document.body.classList.remove('ep-pdv-abierto');
		if (devolverFoco) trigger.focus();
	}

	function elegir(p) {
		estado.elegido = p;
		titulo.textContent = p.nombre;
		sub.textContent = [nombreCanal(p.canal), meta(p)].filter(Boolean).join(SEP);
		sub.hidden = false;
		trigger.classList.add('con-valor');
		trigger.classList.remove('ep-campo-error');
		cerrar(true);
	}

	trigger.addEventListener('click', function () { if (panel.hidden) abrir(); else cerrar(false); });
	fondo.addEventListener('click', function () { cerrar(false); });
	document.getElementById('ep-pdv-cerrar').addEventListener('click', function () { cerrar(true); });
	buscador.addEventListener('input', refrescar);
	lista.addEventListener('click', function (ev) {
		var fila = ev.target.closest('.ep-pdv-fila');
		if (fila) elegir(estado.visibles[+fila.dataset.i]);
	});
	lista.addEventListener('mousemove', function (ev) {
		var fila = ev.target.closest('.ep-pdv-fila');
		if (!fila || +fila.dataset.i === estado.activo) return;
		var previa = lista.querySelector('.activa');
		if (previa) previa.classList.remove('activa');
		estado.activo = +fila.dataset.i;
		fila.classList.add('activa');
	});
	var canales = document.getElementById('ep-pdv-canales');
	if (canales) canales.addEventListener('click', function (ev) {
		var b = ev.target.closest('.ep-pdv-canal');
		if (!b) return;
		canales.querySelectorAll('.ep-pdv-canal').forEach(function (x) { x.classList.toggle('activo', x === b); });
		estado.canal = b.dataset.canal;
		refrescar();
	});
	panel.addEventListener('keydown', function (ev) {
		if (ev.key === 'ArrowDown') { ev.preventDefault(); mover(1); }
		else if (ev.key === 'ArrowUp') { ev.preventDefault(); mover(-1); }
		else if (ev.key === 'Enter' && estado.activo >= 0) { ev.preventDefault(); elegir(estado.visibles[estado.activo]); }
		else if (ev.key === 'Escape') { ev.preventDefault(); cerrar(true); }
	});
	// Clic fuera del selector (escritorio): se cierra sin elegir.
	document.addEventListener('click', function (ev) { if (!panel.hidden && !raiz.contains(ev.target)) cerrar(false); });
})();

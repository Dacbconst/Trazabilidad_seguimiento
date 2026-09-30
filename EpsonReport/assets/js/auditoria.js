// Auditoría: búsqueda, usuario, tipo y periodo sobre la lista; el detalle del movimiento elegido va en el panel lateral.
(function () {
	var root = document.getElementById('epAu');
	var lista = document.getElementById('epAuLista');
	var datosEl = document.getElementById('epAuDatos');
	if (!root || !lista || !datosEl) return;

	var datos = JSON.parse(datosEl.textContent);
	var eventos = Array.prototype.slice.call(lista.querySelectorAll('.ep-au-ev'));
	var dias = Array.prototype.slice.call(lista.querySelectorAll('.ep-au-dia'));
	var sin = document.getElementById('epAuSin');
	var resumen = document.getElementById('epAuResumen');
	var rapidos = document.getElementById('epAuRapidos');
	var panel = document.getElementById('epAuDet');
	var panelVacio = document.getElementById('epAuDetVacio');
	var panelIn = document.getElementById('epAuDetIn');
	var estado = { q: '', usuario: '', tipo: '', desde: '', hasta: '' };
	var elegido = null;
	var iconos = {
		crea: '<path d="M12 5v14M5 12h14"/>',
		edita: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
		borra: '<path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-8 0l1 13a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2l1-13"/>',
		sis: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>'
	};

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
	}
	function svg(tipo, s) {
		return '<svg width="' + s + '" height="' + s + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + iconos[tipo] + '</svg>';
	}
	function iniciales(nombre) {
		return nombre.split(' ').filter(Boolean).slice(0, 2).map(function (w) { return w[0]; }).join('').toUpperCase();
	}
	function isoLocal(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }

	function coincide(ev) {
		if (estado.usuario && ev.dataset.usuario !== estado.usuario) return false;
		if (estado.tipo && ev.dataset.tipo !== estado.tipo) return false;
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
		});
		dias.forEach(function (d) {
			var n = d.querySelectorAll('.ep-au-ev:not(.hidden)').length;
			d.classList.toggle('hidden', n === 0);
			d.querySelector('.ep-au-dia-head span').textContent = n + (n === 1 ? ' movimiento' : ' movimientos');
		});
		sin.classList.toggle('hidden', total > 0);
		resumen.textContent = total + (total === 1 ? ' movimiento' : ' movimientos') + ' · quién hizo qué y cuándo';
		if (elegido && elegido.classList.contains('hidden')) cerrar();
	}

	function detalle(m) {
		var conCambio = m.detalle.filter(function (d) { return 'antes' in d; });
		var conValor = m.detalle.filter(function (d) { return !('antes' in d); });
		var vacio = '<i>vacío</i>';
		var cuerpo = '';
		if (conCambio.length) {
			cuerpo += '<div class="ep-au-sec"><h3>Qué cambió</h3>' + conCambio.map(function (d) {
				return '<div class="ep-au-cmp"><small>' + esc(d.campo) + '</small><div><div class="a"><label>Antes</label><span>' + (d.antes !== '' ? esc(d.antes) : vacio) + '</span></div><div class="d"><label>Después</label>' + (d.despues !== '' ? esc(d.despues) : vacio) + '</div></div></div>';
			}).join('') + '</div>';
		}
		if (conValor.length) {
			cuerpo += '<div class="ep-au-sec"><h3>Datos del movimiento</h3><dl class="ep-au-kv">' + conValor.map(function (d) {
				return '<dt>' + esc(d.campo) + '</dt><dd>' + esc(d.valor) + '</dd>';
			}).join('') + '</dl></div>';
		}
		var refs = '<dt>Sección</dt><dd>' + esc(m.seccion) + '</dd>' + (m.numero ? '<dt>Número</dt><dd>' + esc(m.numero) + '</dd>' : '') + '<dt>Dirección IP</dt><dd>' + (m.ip ? esc(m.ip) : 'No aplica') + '</dd>';
		var esSis = m.tipo === 'sis';
		panelIn.innerHTML =
			'<button type="button" class="ep-au-atras" id="epAuAtras"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>Movimientos</button>' +
			'<div class="ep-au-det-top"><span class="ep-au-det-tag ep-au-t-' + m.tipo + '">' + svg(m.tipo, 14) + esc(m.accion) + '</span><h2>' + esc(m.resumen) + '</h2><span class="ep-au-det-cuando">' + esc(m.cuando) + '</span></div>' +
			'<div class="ep-au-quien"><div class="ep-au-av' + (esSis ? ' sis' : '') + '">' + esc(esSis ? 'SI' : iniciales(m.quien)) + '</div><div><b>' + esc(m.quien) + '</b><span>' + (m.usuario ? 'Usuario ' + esc(m.usuario) : 'Acción automática, sin usuario') + '</span></div></div>' +
			cuerpo +
			'<div class="ep-au-refs"><h3>Referencia</h3><dl class="ep-au-meta">' + refs + '</dl></div>';
		document.getElementById('epAuAtras').addEventListener('click', cerrar);
	}

	function abrir(ev) {
		if (elegido) elegido.classList.remove('on');
		elegido = ev;
		ev.classList.add('on');
		detalle(datos[ev.dataset.i]);
		panelVacio.style.display = 'none';
		panelIn.classList.add('on');
		panel.classList.add('on');
		panel.scrollTop = 0;
	}
	function cerrar() {
		if (elegido) elegido.classList.remove('on');
		elegido = null;
		panelIn.classList.remove('on');
		panel.classList.remove('on');
		panelVacio.style.display = '';
	}

	lista.addEventListener('click', function (e) {
		var ev = e.target.closest('.ep-au-ev');
		if (ev) abrir(ev);
	});
	lista.addEventListener('keydown', function (e) {
		if (e.key !== 'Enter' && e.key !== ' ') return;
		var ev = e.target.closest('.ep-au-ev');
		if (ev) { e.preventDefault(); abrir(ev); }
	});
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && elegido) cerrar();
	});

	rapidos.addEventListener('click', function (e) {
		var b = e.target.closest('button');
		if (!b) return;
		var hoy = new Date();
		var desde = new Date(hoy);
		var k = b.dataset.rapido;
		if (k === 'semana') desde.setDate(hoy.getDate() - ((hoy.getDay() + 6) % 7));
		else if (k === 'mes') desde.setDate(1);
		estado.desde = k === 'todo' ? '' : isoLocal(desde);
		estado.hasta = k === 'todo' ? '' : isoLocal(hoy);
		rapidos.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); });
		aplicar();
	});
	document.getElementById('epAuTipos').addEventListener('click', function (e) {
		var b = e.target.closest('.ep-au-chip');
		if (!b) return;
		estado.tipo = b.dataset.tipo;
		this.querySelectorAll('.ep-au-chip').forEach(function (x) { x.classList.toggle('on', x === b); });
		aplicar();
	});
	document.getElementById('epAuUsuario').addEventListener('change', function () {
		estado.usuario = this.value;
		aplicar();
	});
	document.getElementById('epAuBuscar').addEventListener('input', function (e) {
		estado.q = e.target.value.trim().toLowerCase();
		aplicar();
	});

	aplicar();
})();

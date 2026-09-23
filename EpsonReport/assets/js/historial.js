// Historial: lista compacta + panel de detalle. Filtros por actividad, texto, promotor y fechas; visor de fotos.
(function () {
	var root = document.getElementById('epH2');
	if (!root) return;

	var filas = Array.prototype.slice.call(root.querySelectorAll('.ep-h2-reg'));
	var panel = document.getElementById('epH2Panel');
	var panelVacio = document.getElementById('epH2PanelVacio');
	var panelContenido = document.getElementById('epH2PanelContenido');
	var vacio = document.getElementById('epH2Vacio');
	var btnMas = document.getElementById('epH2Mas');
	var resumen = document.getElementById('epH2Resumen');
	var movil = window.matchMedia('(max-width: 900px)');
	var POR_TANDA = 40;
	var estado = { tipo: 'all', q: '', prom: 'all', desde: '', hasta: '', limite: POR_TANDA, sel: null };

	function coincide(f) {
		if (estado.tipo !== 'all' && f.dataset.tipo !== estado.tipo) return false;
		if (estado.prom !== 'all' && f.dataset.promotor !== estado.prom) return false;
		if (estado.q && f.dataset.busqueda.indexOf(estado.q) === -1) return false;
		if (estado.desde && f.dataset.fecha < estado.desde) return false;
		if (estado.hasta && f.dataset.fecha > estado.hasta) return false;
		return true;
	}

	function seleccionar(fila, abrirEnMovil) {
		if (estado.sel) estado.sel.classList.remove('ep-h2-sel');
		estado.sel = fila;
		if (!fila) {
			panelContenido.innerHTML = '';
			panelVacio.classList.remove('hidden');
			panel.classList.remove('ep-h2-panel-abierto');
			document.body.classList.remove('ep-h2-bloqueado');
			return;
		}
		fila.classList.add('ep-h2-sel');
		var plantilla = document.getElementById('epH2T-' + fila.dataset.idx);
		panelContenido.innerHTML = '';
		if (plantilla) panelContenido.appendChild(plantilla.content.cloneNode(true));
		panelVacio.classList.add('hidden');
		panel.scrollTop = 0;
		if (movil.matches && abrirEnMovil) {
			panel.classList.add('ep-h2-panel-abierto');
			document.body.classList.add('ep-h2-bloqueado');
		}
	}

	function cerrarPanelMovil() {
		panel.classList.remove('ep-h2-panel-abierto');
		document.body.classList.remove('ep-h2-bloqueado');
	}

	function aplicar() {
		var visibles = 0;
		var totalCoinciden = 0;
		filas.forEach(function (f) {
			var ok = coincide(f);
			if (ok) totalCoinciden++;
			var mostrar = ok && visibles < estado.limite;
			if (mostrar) visibles++;
			f.classList.toggle('hidden', !mostrar);
		});
		vacio.classList.toggle('hidden', totalCoinciden > 0);
		btnMas.classList.toggle('hidden', totalCoinciden <= visibles);
		resumen.textContent = totalCoinciden + (totalCoinciden === 1 ? ' registro' : ' registros');
		if (estado.sel && estado.sel.classList.contains('hidden')) seleccionar(null);
		if (!estado.sel && !movil.matches) {
			var primera = filas.filter(function (f) { return !f.classList.contains('hidden'); })[0];
			if (primera) seleccionar(primera, false);
		}
	}

	// Filtros
	root.querySelectorAll('.ep-h2-pill').forEach(function (b) {
		b.addEventListener('click', function () {
			root.querySelectorAll('.ep-h2-pill').forEach(function (x) { x.classList.remove('selected'); });
			b.classList.add('selected');
			estado.tipo = b.dataset.tipo;
			estado.limite = POR_TANDA;
			aplicar();
		});
	});
	var buscar = document.getElementById('epH2Buscar');
	if (buscar) buscar.addEventListener('input', function () { estado.q = buscar.value.trim().toLowerCase(); estado.limite = POR_TANDA; aplicar(); });
	var selProm = document.getElementById('epH2Promotor');
	if (selProm) selProm.addEventListener('change', function () { estado.prom = selProm.value; estado.limite = POR_TANDA; aplicar(); });
	var desde = document.getElementById('epH2Desde');
	var hasta = document.getElementById('epH2Hasta');
	if (desde) desde.addEventListener('change', function () { estado.desde = desde.value; estado.limite = POR_TANDA; aplicar(); });
	if (hasta) hasta.addEventListener('change', function () { estado.hasta = hasta.value; estado.limite = POR_TANDA; aplicar(); });
	btnMas.addEventListener('click', function () { estado.limite += POR_TANDA; aplicar(); });

	// Selección de registro
	root.addEventListener('click', function (ev) {
		var fila = ev.target.closest('.ep-h2-reg');
		if (fila) { seleccionar(fila, true); return; }
		if (ev.target.closest('.ep-h2-cerrar')) cerrarPanelMovil();
	});
	root.addEventListener('keydown', function (ev) {
		if ((ev.key === 'Enter' || ev.key === ' ') && ev.target.classList.contains('ep-h2-reg')) {
			ev.preventDefault();
			seleccionar(ev.target, true);
		}
	});

	// Visor de fotos con navegación
	var lb = document.getElementById('epH2Lightbox');
	var lbImg = document.getElementById('epH2LbImg');
	var lbPie = document.getElementById('epH2LbPie');
	var galeria = [];
	var pos = 0;
	function mostrarFoto() {
		var f = galeria[pos];
		lbImg.src = f.url;
		lbImg.alt = f.label;
		lbPie.textContent = f.label + '  ·  ' + (pos + 1) + ' de ' + galeria.length;
		lb.classList.toggle('ep-h2-lb-una', galeria.length < 2);
	}
	function cerrarLb() { lb.classList.add('hidden'); lbImg.src = ''; }
	panel.addEventListener('click', function (ev) {
		var b = ev.target.closest('.ep-h2-foto');
		if (!b || !b.dataset.url || bloqueoClick) return;
		var todas = Array.prototype.slice.call(b.closest('.ep-h2-fotos-grid').querySelectorAll('.ep-h2-foto')).filter(function (x) { return x.dataset.url; });
		galeria = todas.map(function (x) { return { url: x.dataset.url, label: x.dataset.label }; });
		pos = todas.indexOf(b);
		mostrarFoto();
		lb.classList.remove('hidden');
	});
	document.getElementById('epH2LbCerrar').addEventListener('click', cerrarLb);
	document.getElementById('epH2LbFondo').addEventListener('click', cerrarLb);
	document.getElementById('epH2LbPrev').addEventListener('click', function () { pos = (pos - 1 + galeria.length) % galeria.length; mostrarFoto(); });
	document.getElementById('epH2LbNext').addEventListener('click', function () { pos = (pos + 1) % galeria.length; mostrarFoto(); });
	document.addEventListener('keydown', function (ev) {
		if (lb.classList.contains('hidden')) return;
		if (ev.key === 'Escape') cerrarLb();
		if (ev.key === 'ArrowLeft') document.getElementById('epH2LbPrev').click();
		if (ev.key === 'ArrowRight') document.getElementById('epH2LbNext').click();
	});

	// Carrusel horizontal de tarjetas: arrastrar con el mouse, rueda con Shift y flechas.
	var arrastre = null;
	var bloqueoClick = false;
	function estadoFlechas(carril) {
		var caja = carril.parentNode;
		var prev = caja.querySelector('.ep-h2-car-prev');
		var next = caja.querySelector('.ep-h2-car-next');
		if (prev) prev.disabled = carril.scrollLeft <= 2;
		if (next) next.disabled = carril.scrollLeft + carril.clientWidth >= carril.scrollWidth - 2;
	}
	panel.addEventListener('pointerdown', function (ev) {
		var carril = ev.target.closest('.ep-h2-carril');
		if (!carril || ev.pointerType !== 'mouse' || ev.button !== 0) return;
		arrastre = { carril: carril, x: ev.clientX, izq: carril.scrollLeft, movio: false };
	});
	window.addEventListener('pointermove', function (ev) {
		if (!arrastre) return;
		var dx = ev.clientX - arrastre.x;
		if (Math.abs(dx) > 4) { arrastre.movio = true; arrastre.carril.classList.add('ep-h2-arrastrando'); }
		if (arrastre.movio) arrastre.carril.scrollLeft = arrastre.izq - dx;
	});
	window.addEventListener('pointerup', function () {
		if (!arrastre) return;
		arrastre.carril.classList.remove('ep-h2-arrastrando');
		if (arrastre.movio) { bloqueoClick = true; setTimeout(function () { bloqueoClick = false; }, 80); }
		arrastre = null;
	});
	panel.addEventListener('scroll', function (ev) { if (ev.target.classList && ev.target.classList.contains('ep-h2-carril')) estadoFlechas(ev.target); }, true);
	panel.addEventListener('wheel', function (ev) {
		var carril = ev.target.closest('.ep-h2-carril');
		if (carril && ev.shiftKey) { ev.preventDefault(); carril.scrollLeft += ev.deltaY || ev.deltaX; }
	}, { passive: false });
	panel.addEventListener('click', function (ev) {
		var b = ev.target.closest('.ep-h2-car-btn');
		if (!b) return;
		var carril = b.parentNode.querySelector('.ep-h2-carril');
		carril.scrollBy({ left: (b.classList.contains('ep-h2-car-prev') ? -1 : 1) * carril.clientWidth * 0.8, behavior: 'smooth' });
	});
	new MutationObserver(function () {
		var carril = panel.querySelector('.ep-h2-carril');
		if (carril) requestAnimationFrame(function () { estadoFlechas(carril); });
	}).observe(panelContenido, { childList: true });

	movil.addEventListener('change', function () { cerrarPanelMovil(); aplicar(); });
	aplicar();
})();

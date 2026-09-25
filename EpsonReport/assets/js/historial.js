// Historial: búsqueda, filtros, lista por día y panel de detalle; visor de fotos y refresco en vivo.
(function () {
	var root = document.getElementById('epH2');
	if (!root) return;

	var esAdmin = root.dataset.admin === '1';
	var contenedor = document.getElementById('epH2Filas');
	var panel = document.getElementById('epH2Panel');
	var panelVacio = document.getElementById('epH2PanelVacio');
	var panelContenido = document.getElementById('epH2PanelContenido');
	var vacio = document.getElementById('epH2Vacio');
	var sinCoincidencias = document.getElementById('epH2SinCoincidencias');
	var btnMas = document.getElementById('epH2Mas');
	var resumen = document.getElementById('epH2Resumen');
	var inpBuscar = document.getElementById('epH2Buscar');
	var inpDesde = document.getElementById('epH2Desde');
	var inpHasta = document.getElementById('epH2Hasta');
	var chips = document.getElementById('epH2Chips');
	var rapidos = document.getElementById('epH2Rapidos');
	var movil = window.matchMedia('(max-width: 900px)');
	var POR_TANDA = 40;
	var estado = { act: 'all', q: '', prom: 'all', desde: '', hasta: '', limite: POR_TANDA, sel: null };
	var filas = [];
	var dias = [];

	function leerFilas() {
		filas = Array.prototype.slice.call(root.querySelectorAll('.ep-h2-reg'));
		dias = Array.prototype.slice.call(root.querySelectorAll('.ep-h2-dia'));
	}

	function coincide(f) {
		if (estado.act !== 'all' && f.dataset.actividad !== estado.act) return false;
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

	function fechaTexto(iso) { return iso ? iso.split('-').reverse().join('/') : ''; }

	// Chips de los filtros activos, cada uno con su X, y "Limpiar todo".
	function pintarChips() {
		var lista = [];
		if (estado.act !== 'all') lista.push(['act', estado.act]);
		if (estado.prom !== 'all') lista.push(['prom', estado.prom]);
		if (estado.desde || estado.hasta) lista.push(['fecha', (estado.desde ? fechaTexto(estado.desde) : 'inicio') + ' al ' + (estado.hasta ? fechaTexto(estado.hasta) : 'hoy')]);
		chips.classList.toggle('hidden', !lista.length);
		chips.innerHTML = '';
		if (!lista.length) return;
		var rotulo = document.createElement('span');
		rotulo.textContent = 'Filtros:';
		chips.appendChild(rotulo);
		lista.forEach(function (c) {
			var chip = document.createElement('span');
			chip.className = 'ep-h2-chip';
			chip.appendChild(document.createTextNode(c[1]));
			var x = document.createElement('button');
			x.type = 'button';
			x.dataset.quitar = c[0];
			x.setAttribute('aria-label', 'Quitar filtro');
			x.textContent = '×';
			chip.appendChild(x);
			chips.appendChild(chip);
		});
		var limpiar = document.createElement('button');
		limpiar.type = 'button';
		limpiar.className = 'ep-h2-chips-limpiar';
		limpiar.dataset.quitar = 'todo';
		limpiar.textContent = 'Limpiar todo';
		chips.appendChild(limpiar);
	}

	function aplicar() {
		var visibles = 0;
		var coinciden = 0;
		var promotores = {};
		filas.forEach(function (f) {
			var ok = coincide(f);
			if (ok) { coinciden++; promotores[f.dataset.promotor] = true; }
			var mostrar = ok && visibles < estado.limite;
			if (mostrar) visibles++;
			f.classList.toggle('hidden', !mostrar);
		});
		dias.forEach(function (d) {
			var n = 0;
			for (var sig = d.nextElementSibling; sig && !sig.classList.contains('ep-h2-dia'); sig = sig.nextElementSibling) {
				if (sig.classList.contains('ep-h2-reg') && !sig.classList.contains('hidden')) n++;
			}
			d.classList.toggle('hidden', n === 0);
			d.querySelector('em').textContent = n + (n === 1 ? ' registro' : ' registros');
		});
		vacio.classList.toggle('hidden', filas.length > 0);
		sinCoincidencias.classList.toggle('hidden', filas.length === 0 || coinciden > 0);
		btnMas.classList.toggle('hidden', coinciden <= visibles);
		resumen.textContent = coinciden + (coinciden === 1 ? ' registro' : ' registros') + (esAdmin ? ' · ' + Object.keys(promotores).length + (Object.keys(promotores).length === 1 ? ' promotor' : ' promotores') : '');
		pintarChips();
		if (estado.sel && estado.sel.classList.contains('hidden')) seleccionar(null);
		if (!estado.sel && !movil.matches) {
			var primera = filas.filter(function (f) { return !f.classList.contains('hidden'); })[0];
			if (primera) seleccionar(primera, false);
		}
	}

	function reiniciarLimite() { estado.limite = POR_TANDA; }

	// ---- Selectores con buscador (actividad y promotor): componente compartido en filtros.js ----
	function contarPor(atributo) {
		var cuentas = {};
		filas.forEach(function (f) { cuentas[f.dataset[atributo]] = (cuentas[f.dataset[atributo]] || 0) + 1; });
		return cuentas;
	}

	var comboAct = epFiltros.crearCombo(root, 'epH2ComboAct', function () {
		var cuentas = contarPor('actividad');
		var iconos = {};
		filas.forEach(function (f) { iconos[f.dataset.actividad] = f.querySelector('.ep-fl-ico').innerHTML; });
		var lista = Object.keys(cuentas).sort(function (a, b) { return cuentas[b] - cuentas[a] || a.localeCompare(b); }).map(function (n) { return { valor: n, etiqueta: n, cuenta: cuentas[n], ico: iconos[n] }; });
		return [{ valor: 'all', etiqueta: 'Todas las actividades', cuenta: filas.length }].concat(lista);
	}, function () { return estado.act; }, function (v) { estado.act = v; reiniciarLimite(); refrescarCombos(); aplicar(); });

	var comboProm = epFiltros.crearCombo(root, 'epH2ComboProm', function () {
		var cuentas = contarPor('promotor');
		var lista = Object.keys(cuentas).sort(function (a, b) { return a.localeCompare(b); }).map(function (n) { return { valor: n, etiqueta: n, cuenta: cuentas[n] }; });
		return [{ valor: 'all', etiqueta: 'Todos los promotores', cuenta: filas.length }].concat(lista);
	}, function () { return estado.prom; }, function (v) { estado.prom = v; reiniciarLimite(); refrescarCombos(); aplicar(); });

	function refrescarCombos() { comboAct.pintar(); comboProm.pintar(); }

	// ---- Periodos rápidos y rango de fechas ----
	function isoLocal(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
	function rangoRapido(k) {
		var hoy = new Date();
		var desde = new Date(hoy);
		if (k === 'semana') desde.setDate(hoy.getDate() - ((hoy.getDay() + 6) % 7));
		else if (k === 'mes') desde.setDate(1);
		return k === 'todo' ? ['', ''] : [isoLocal(desde), isoLocal(hoy)];
	}
	function marcarRapido(k) {
		rapidos.querySelectorAll('button').forEach(function (b) { b.classList.toggle('selected', b.dataset.rapido === k); });
	}
	function fijarFechas(desde, hasta, rapido) {
		estado.desde = desde;
		estado.hasta = hasta;
		inpDesde.value = desde;
		inpHasta.value = hasta;
		marcarRapido(rapido);
		reiniciarLimite();
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
	var cajaFecha = document.getElementById('epH2ComboFecha');
	cajaFecha.querySelector('.ep-fl-combo-btn').addEventListener('click', function () {
		var p = cajaFecha.querySelector('.ep-fl-combo-panel');
		var abrir = p.classList.contains('hidden');
		epFiltros.cerrarCombos(root, cajaFecha);
		p.classList.toggle('hidden', !abrir);
	});

	inpBuscar.addEventListener('input', function () { estado.q = inpBuscar.value.trim().toLowerCase(); reiniciarLimite(); aplicar(); });
	btnMas.addEventListener('click', function () { estado.limite += POR_TANDA; aplicar(); });

	chips.addEventListener('click', function (ev) {
		var x = ev.target.closest('[data-quitar]');
		if (!x) return;
		var que = x.dataset.quitar;
		if (que === 'act' || que === 'todo') estado.act = 'all';
		if (que === 'prom' || que === 'todo') estado.prom = 'all';
		if (que === 'fecha' || que === 'todo') { fijarFechas('', '', 'todo'); }
		reiniciarLimite();
		refrescarCombos();
		aplicar();
	});

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

	// Refresco: se consulta una firma liviana y solo si cambió se piden las filas, conservando filtros y selección.
	var ultimoHtml = null;
	var ultimaFirma = null;
	function vigilar() {
		if (document.hidden) return;
		fetch('getters/historial_firma.php', { cache: 'no-store', credentials: 'same-origin' })
			.then(function (r) {
				if (r.status === 401) { window.location.href = 'login.php?error=sesion'; return null; }
				return r.ok ? r.json() : null;
			})
			.then(function (d) {
				if (!d || !d.firma || d.firma === ultimaFirma) return;
				ultimaFirma = d.firma;
				refrescar();
			})
			.catch(function () {});
	}
	function refrescar() {
		if (root.querySelector('.ep-h2-panel-abierto')) { ultimaFirma = null; return; }
		fetch('getters/historial_filas.php', { cache: 'no-store', credentials: 'same-origin' })
			.then(function (r) {
				if (r.status === 401) { window.location.href = 'login.php?error=sesion'; return null; }
				return r.ok ? r.text() : null;
			})
			.then(function (html) {
				if (html === null) return;
				var cambio = ultimoHtml === null ? contenedor.querySelectorAll('.ep-h2-reg').length !== (html.match(/class="ep-h2-fila ep-h2-reg"/g) || []).length : html !== ultimoHtml;
				ultimoHtml = html;
				if (!cambio) return;
				var codigoSel = estado.sel ? estado.sel.dataset.codigo : null;
				contenedor.innerHTML = html;
				leerFilas();
				estado.sel = null;
				refrescarCombos();
				aplicar();
				var previa = codigoSel && filas.filter(function (f) { return f.dataset.codigo === codigoSel && !f.classList.contains('hidden'); })[0];
				if (previa) seleccionar(previa, false);
			})
			.catch(function () {});
	}
	// En vivo cada 3 s solo para el admin; el promotor se actualiza al volver a la pestaña (menos carga en el servidor).
	if (esAdmin) setInterval(vigilar, 3000);
	document.addEventListener('visibilitychange', function () { if (!document.hidden) vigilar(); });

	movil.addEventListener('change', function () { cerrarPanelMovil(); aplicar(); });
	leerFilas();
	refrescarCombos();
	aplicar();
})();

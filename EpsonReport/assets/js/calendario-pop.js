// Tab de Colocación de POP: pestañas, carga de la tabla del mes y sus acciones (editar, cerrar, eliminar).
(function () {
	var panelAct = document.getElementById('epCalPanelAct');
	var panelPop = document.getElementById('epCalPanelPop');
	var btnCalNuevo = document.getElementById('epCalNuevo');
	if (!panelAct || !panelPop) return;

	function post(url, datos) {
		var form = new FormData();
		Object.keys(datos).forEach(function (k) { form.append(k, datos[k]); });
		return fetch(url, { method: 'POST', body: form, credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}
	function avisar(icono, titulo, texto) {
		if (window.Swal) Swal.fire({ icon: icono, title: titulo, text: texto || '' });
	}

	// ---------- Pestañas ----------
	document.querySelectorAll('.ep-cl-tab').forEach(function (tab) {
		tab.addEventListener('click', function () {
			var esPop = tab.dataset.tab === 'pop';
			document.querySelectorAll('.ep-cl-tab').forEach(function (t) {
				t.classList.toggle('activo', t === tab);
				t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
			});
			panelAct.classList.toggle('hidden', esPop);
			panelPop.classList.toggle('hidden', !esPop);
			// Cada pestaña tiene su propio botón de crear; el de Activaciones no aplica en POP.
			if (btnCalNuevo) btnCalNuevo.classList.toggle('hidden', esPop);
		});
	});

	// ---------- Modal de carga ----------
	var modal = document.getElementById('epPopModal');
	var filas = document.getElementById('epPopFilas');
	var inputMes = document.getElementById('epPopMes');
	var inputComentarios = document.getElementById('epPopComentarios');
	var btnGuardar = document.getElementById('epPopGuardar');
	var titulo = document.getElementById('epPopModalTitulo');
	var editandoId = 0;
	var abiertos = JSON.parse((document.getElementById('epPopDatos') || {}).textContent || '[]');

	var todosCanales = JSON.parse((document.getElementById('epPopCanales') || {}).textContent || '[]');
	var canalesActivos = [];
	var filasHead = document.getElementById('epPopFilasHead');
	var canalBtn = document.getElementById('epPopCanalBtn');
	var canalLista = document.getElementById('epPopCanalLista');

	function etiqueta(c) { return c.charAt(0) + c.slice(1).toLowerCase(); }
	function filaHTML() {
		return '<div class="ep-pop-fila">'
			+ '<input type="text" class="ep-input ep-pop-fila-material" maxlength="60" placeholder="Material (ej. Dangler)" autocomplete="off">'
			+ '<input type="text" class="ep-input ep-pop-fila-campana" maxlength="40" placeholder="Campaña" autocomplete="off">'
			+ '<input type="number" min="0" inputmode="numeric" class="ep-input ep-pop-fila-bodega" placeholder="Bodega">'
			+ canalesActivos.map(function (c) { return '<input type="number" min="0" inputmode="numeric" class="ep-input ep-pop-fila-canal" data-canal="' + c + '" placeholder="0">'; }).join('')
			+ '<span class="ep-pop-fila-disponible">0</span>'
			+ '<button type="button" class="ep-modelo-quitar" aria-label="Quitar material"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button>'
			+ '</div>';
	}
	// Encabezado y columnas siguen a los canales agregados; Disponible va siempre al final.
	function pintarEncabezado() {
		filasHead.innerHTML = '<span>Material</span><span>Campaña</span><span>Bodega</span>'
			+ canalesActivos.map(function (c) { return '<span>' + etiqueta(c) + '<button type="button" class="ep-pop-quitar-canal" data-canal="' + c + '" aria-label="Quitar canal ' + etiqueta(c) + '" title="Quitar canal">×</button></span>'; }).join('')
			+ '<span>Disponible</span><span></span>';
		var n = String(canalesActivos.length);
		filasHead.style.setProperty('--ep-pop-m', n);
		filas.querySelectorAll('.ep-pop-fila').forEach(function (f) { f.style.setProperty('--ep-pop-m', n); });
	}
	function recalcular(fila) {
		var bodega = parseInt(fila.querySelector('.ep-pop-fila-bodega').value, 10) || 0;
		var repartido = 0;
		fila.querySelectorAll('.ep-pop-fila-canal').forEach(function (i) { repartido += parseInt(i.value, 10) || 0; });
		var disp = fila.querySelector('.ep-pop-fila-disponible');
		disp.textContent = bodega - repartido;
		disp.classList.toggle('ep-pop-negativo', bodega - repartido < 0);
	}
	function agregarFila() {
		filas.insertAdjacentHTML('beforeend', filaHTML());
		var fila = filas.lastElementChild;
		fila.style.setProperty('--ep-pop-m', String(canalesActivos.length));
		return fila;
	}
	// Al sumar un canal se agrega una columna a todas las filas; al quitarlo se pierde lo escrito en ella.
	function agregarCanal(c) {
		if (canalesActivos.indexOf(c) !== -1) return;
		canalesActivos.push(c);
		filas.querySelectorAll('.ep-pop-fila').forEach(function (f) {
			var input = document.createElement('input');
			input.type = 'number'; input.min = '0'; input.inputMode = 'numeric'; input.placeholder = '0';
			input.className = 'ep-input ep-pop-fila-canal'; input.dataset.canal = c;
			f.insertBefore(input, f.querySelector('.ep-pop-fila-disponible'));
		});
		pintarEncabezado();
	}
	function quitarCanal(c) {
		canalesActivos = canalesActivos.filter(function (x) { return x !== c; });
		filas.querySelectorAll('.ep-pop-fila-canal').forEach(function (i) { if (i.dataset.canal === c) { var f = i.closest('.ep-pop-fila'); i.remove(); recalcular(f); } });
		pintarEncabezado();
	}
	function pintarMenuCanales() {
		var libres = todosCanales.filter(function (c) { return canalesActivos.indexOf(c) === -1; });
		canalLista.innerHTML = libres.length
			? libres.map(function (c) { return '<button type="button" role="menuitem" data-canal="' + c + '">' + etiqueta(c) + '</button>'; }).join('')
			: '<div class="ep-pop-canal-vacio">Ya agregaste todos los canales</div>';
	}
	function leerFilas() {
		return Array.prototype.slice.call(filas.querySelectorAll('.ep-pop-fila')).map(function (f) {
			var canales = {};
			f.querySelectorAll('.ep-pop-fila-canal').forEach(function (i) { canales[i.dataset.canal] = parseInt(i.value, 10) || 0; });
			return {
				material: f.querySelector('.ep-pop-fila-material').value.trim().toUpperCase(),
				campana: f.querySelector('.ep-pop-fila-campana').value.trim().toUpperCase(),
				bodega: parseInt(f.querySelector('.ep-pop-fila-bodega').value, 10) || 0,
				canales: canales
			};
		}).filter(function (f) { return f.material && f.campana; });
	}
	function abrirModal(pop) {
		editandoId = pop ? pop.id : 0;
		titulo.textContent = pop ? 'Corregir mes de POP' : 'Cargar mes de POP';
		btnGuardar.textContent = pop ? 'Guardar cambios' : 'Cargar mes';
		inputMes.value = pop ? pop.mes : new Date().toISOString().slice(0, 7);
		inputMes.disabled = !!pop;
		inputComentarios.value = pop ? pop.comentarios : '';
		filas.innerHTML = '';
		canalesActivos = [];
		if (pop) pop.filas.forEach(function (f) { Object.keys(f.canales || {}).forEach(function (c) { if (canalesActivos.indexOf(c) === -1) canalesActivos.push(c); }); });
		pintarEncabezado();
		if (pop && pop.filas.length) {
			pop.filas.forEach(function (f) {
				var fila = agregarFila();
				fila.querySelector('.ep-pop-fila-material').value = f.material;
				fila.querySelector('.ep-pop-fila-campana').value = f.campana;
				fila.querySelector('.ep-pop-fila-bodega').value = f.bodega;
				fila.querySelectorAll('.ep-pop-fila-canal').forEach(function (i) { var v = (f.canales || {})[i.dataset.canal]; if (v) i.value = v; });
				recalcular(fila);
			});
		} else {
			agregarFila();
		}
		modal.classList.remove('hidden');
	}
	function cerrarModal() { modal.classList.add('hidden'); canalLista.classList.add('hidden'); }

	canalBtn.addEventListener('click', function () {
		pintarMenuCanales();
		var abierto = canalLista.classList.toggle('hidden') === false;
		canalBtn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
	});
	canalLista.addEventListener('click', function (ev) {
		var b = ev.target.closest('button[data-canal]');
		if (!b) return;
		agregarCanal(b.dataset.canal);
		canalLista.classList.add('hidden');
	});
	filasHead.addEventListener('click', function (ev) {
		var q = ev.target.closest('.ep-pop-quitar-canal');
		if (q) quitarCanal(q.dataset.canal);
	});
	filas.addEventListener('input', function (ev) {
		if (ev.target.classList.contains('ep-pop-fila-bodega') || ev.target.classList.contains('ep-pop-fila-canal')) recalcular(ev.target.closest('.ep-pop-fila'));
	});

	var btnNuevo = document.getElementById('epPopNuevo');
	if (btnNuevo) btnNuevo.addEventListener('click', function () { abrirModal(null); });
	document.getElementById('epPopAgregar').addEventListener('click', function () { agregarFila().querySelector('input').focus(); });
	document.getElementById('epPopCancelar').addEventListener('click', cerrarModal);
	document.getElementById('epPopModalCerrar').addEventListener('click', cerrarModal);
	document.getElementById('epPopModalFondo').addEventListener('click', cerrarModal);
	// Siempre queda al menos una fila: la última solo se vacía.
	filas.addEventListener('click', function (ev) {
		if (!ev.target.closest('.ep-modelo-quitar')) return;
		if (filas.querySelectorAll('.ep-pop-fila').length > 1) ev.target.closest('.ep-pop-fila').remove();
		else { filas.querySelectorAll('input').forEach(function (i) { i.value = ''; }); recalcular(filas.querySelector('.ep-pop-fila')); }
	});

	btnGuardar.addEventListener('click', function () {
		var lista = leerFilas();
		if (!lista.length) {
			avisar('warning', 'Falta material', 'Agrega al menos un material con su campaña.');
			return;
		}
		var excedida = lista.filter(function (f) { var t = 0; Object.keys(f.canales).forEach(function (c) { t += f.canales[c]; }); return t > f.bodega; })[0];
		if (excedida) {
			avisar('warning', 'Revisa las cantidades', 'En ' + excedida.material + ' repartes más de lo que hay en bodega.');
			return;
		}
		btnGuardar.disabled = true;
		var url = editandoId ? 'getters/pop_editar.php' : 'getters/pop_crear.php';
		var datos = { filas: JSON.stringify(lista), comentarios: inputComentarios.value };
		if (editandoId) datos.id = editandoId; else datos.mes = inputMes.value;
		post(url, datos).then(function (r) {
			btnGuardar.disabled = false;
			if (r && r.ok) location.reload();
			else avisar('error', 'No se pudo guardar', (r && r.message) || 'Intenta de nuevo.');
		}).catch(function () {
			btnGuardar.disabled = false;
			avisar('error', 'No se pudo guardar', 'El servidor no respondió correctamente.');
		});
	});

	// ---------- Acciones de cada mes ----------
	function idDe(ev) {
		var mes = ev.target.closest('.ep-pop-mes');
		return mes ? parseInt(mes.dataset.popId, 10) : 0;
	}
	document.addEventListener('click', function (ev) {
		if (ev.target.closest('.ep-pop-editar')) {
			var id = idDe(ev);
			var pop = abiertos.filter(function (p) { return p.id === id; })[0];
			if (pop) abrirModal(pop);
			return;
		}
		if (ev.target.closest('.ep-pop-cerrar')) {
			confirmarCierre(idDe(ev), ev.target.closest('.ep-pop-mes'));
			return;
		}
		if (ev.target.closest('.ep-pop-eliminar')) confirmarEliminar(idDe(ev));
	});

	function confirmarCierre(id, seccion) {
		if (!window.Swal || !id) return;
		var avance = seccion ? (seccion.querySelector('.ep-pop-avance') || {}).textContent : '';
		Swal.fire({
			icon: 'warning',
			title: '¿Cerrar el mes?',
			html: '<p style="margin:0">' + (avance || '').trim() + '. Se genera el reporte con lo que los promotores ya reportaron y el mes deja de recibir.</p>',
			showCancelButton: true,
			cancelButtonText: 'Seguir abierto',
			confirmButtonText: 'Sí, cerrar el mes',
			confirmButtonColor: '#B25E00',
			focusCancel: true
		}).then(function (res) {
			if (!res.isConfirmed) return;
			post('getters/pop_cerrar.php', { id: id }).then(function (r) {
				if (r && r.ok && r.aviso) Swal.fire({ icon: 'warning', title: 'Cerrado sin reporte', text: r.aviso }).then(function () { location.reload(); });
				else if (r && r.ok) location.reload();
				else avisar('error', 'No se pudo cerrar', (r && r.message) || 'Intenta de nuevo.');
			}).catch(function () { avisar('error', 'No se pudo cerrar', 'El servidor no respondió correctamente.'); });
		});
	}

	function confirmarEliminar(id) {
		if (!window.Swal || !id) return;
		Swal.fire({
			icon: 'warning',
			title: '¿Eliminar este mes?',
			text: 'Los promotores dejarán de ver su material. Los registros que ya enviaron no se borran.',
			showCancelButton: true,
			cancelButtonText: 'Cancelar',
			confirmButtonText: 'Sí, eliminar',
			confirmButtonColor: '#C5221F',
			focusCancel: true
		}).then(function (res) {
			if (!res.isConfirmed) return;
			post('getters/pop_eliminar.php', { id: id }).then(function (r) {
				if (r && r.ok) location.reload();
				else avisar('error', 'No se pudo eliminar', (r && r.message) || 'Intenta de nuevo.');
			}).catch(function () { avisar('error', 'No se pudo eliminar', 'El servidor no respondió correctamente.'); });
		});
	}
})();

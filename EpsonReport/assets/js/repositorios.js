// Repositorio de POP: dos listas independientes (Material y Campaña), búsqueda, subir archivo o lista y quitar nombres.
(function () {
	var raiz = document.getElementById('epRepo');
	if (!raiz) return;

	var datos = JSON.parse(document.getElementById('epRepoDatos').textContent || '{}');
	var tipos = ['material', 'campana'];
	var etiquetas = { material: 'Material', campana: 'Campaña' };
	var vacios = { material: 'Todavía no hay materiales.', campana: 'Todavía no hay campañas.' };
	var buscar = document.getElementById('epRepoBuscar');
	var modal = document.getElementById('epRepoModal');
	var archivo = document.getElementById('epRepoArchivo');
	var archivoTxt = document.getElementById('epRepoArchivoTxt');
	var btnGuardar = document.getElementById('epRepoGuardar');
	var indicador = document.getElementById('epRepoIndicador');

	function escapar(t) {
		return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
	}
	function avisar(icono, titulo, mensaje) {
		if (window.Swal) Swal.fire({ icon: icono, title: titulo, text: mensaje || '' });
	}
	function post(url, form) {
		return fetch(url, { method: 'POST', body: form, credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}
	function formulario(campos) {
		var f = new FormData();
		Object.keys(campos).forEach(function (k) { f.append(k, campos[k]); });
		return f;
	}

	function posicionarIndicador() {
		var activa = raiz.querySelector('.ep-repo-tab.activo');
		if (!activa) return;
		indicador.style.transform = 'translateX(' + activa.offsetLeft + 'px) scaleX(' + activa.offsetWidth + ')';
	}

	function pintar() {
		var q = buscar.value.trim().toUpperCase();
		tipos.forEach(function (tipo) {
			var todos = datos[tipo] || [];
			var items = todos.filter(function (i) { return !q || i.nombre.indexOf(q) !== -1; });
			document.getElementById('epRepoTitulo' + (tipo === 'material' ? 'Material' : 'Campana')).textContent = etiquetas[tipo] + ' · ' + todos.length;
			document.getElementById('epRepoLista' + (tipo === 'material' ? 'Material' : 'Campana')).innerHTML = items.length
				? items.map(function (i) {
					var icono = function (clase, etiqueta, ruta) { return '<button type="button" class="ep-repo-accion ' + clase + '" aria-label="' + etiqueta + ' ' + escapar(i.nombre) + '" title="' + etiqueta + '"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + ruta + '</svg></button>'; };
					return '<div class="ep-repo-fila" data-id="' + i.id + '" data-tipo="' + tipo + '"><span class="ep-repo-nombre">' + escapar(i.nombre) + '</span>'
						+ '<span class="ep-repo-acciones-fila">' + icono('ep-repo-editar', 'Editar', '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>') + icono('ep-repo-quitar', 'Eliminar', '<path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-8 0l1 13a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2l1-13"/>') + '</span></div>';
				}).join('')
				: '<div class="ep-repo-vacio">' + (q ? 'Nada coincide con tu búsqueda.' : vacios[tipo]) + '</div>';
		});
		document.getElementById('epRepoCuenta').textContent = (datos.material || []).length + (datos.campana || []).length;
	}
	buscar.addEventListener('input', pintar);
	window.addEventListener('resize', posicionarIndicador);

	// ---------- Subir archivo o agregar uno: todo queda en una lista pendiente que se revisa antes de guardar ----------
	var pend = { material: [], campana: [] };
	var uno = { material: document.getElementById('epRepoUnoMaterial'), campana: document.getElementById('epRepoUnoCampana') };
	var prev = document.getElementById('epRepoPrev');
	var prevTabla = document.getElementById('epRepoPrevTabla');

	function normalizar(t) { return String(t).replace(/\s+/g, ' ').trim().toUpperCase(); }
	function existe(tipo, nombre) { return (datos[tipo] || []).some(function (i) { return i.nombre === nombre; }); }
	function sumar(tipo, nombres) {
		nombres.forEach(function (n) {
			var nombre = normalizar(n);
			if (nombre && pend[tipo].indexOf(nombre) === -1) pend[tipo].push(nombre);
		});
	}
	function celda(tipo, nombre) {
		if (!nombre) return '<span></span>';
		return existe(tipo, nombre) ? '<span class="ep-repo-prev-ya">' + escapar(nombre) + '<small>Ya existe</small></span>' : '<span>' + escapar(nombre) + '</span>';
	}
	function pintarPendiente() {
		var filas = Math.max(pend.material.length, pend.campana.length);
		var nuevos = 0;
		tipos.forEach(function (t) { pend[t].forEach(function (n) { if (!existe(t, n)) nuevos++; }); });
		prev.classList.toggle('hidden', !filas);
		btnGuardar.disabled = !nuevos;
		document.getElementById('epRepoPrevResumen').textContent = nuevos + (nuevos === 1 ? ' nombre nuevo' : ' nombres nuevos') + ' de ' + (pend.material.length + pend.campana.length);
		var html = '<div class="ep-repo-prev-fila ep-repo-prev-th"><span>Material</span><span>Campaña</span></div>';
		for (var i = 0; i < filas; i++) {
			html += '<div class="ep-repo-prev-fila">' + celda('material', pend.material[i]) + celda('campana', pend.campana[i]) + '</div>';
		}
		prevTabla.innerHTML = html;
	}
	function reiniciar() {
		pend = { material: [], campana: [] };
		archivo.value = '';
		archivoTxt.textContent = 'Subir archivo .xlsx, .csv o .txt';
		pintarPendiente();
	}
	function abrirModal() {
		uno.material.value = '';
		uno.campana.value = '';
		reiniciar();
		modal.classList.remove('hidden');
		uno.material.focus();
	}
	function cerrarModal() { modal.classList.add('hidden'); }
	document.getElementById('epRepoAgregar').addEventListener('click', abrirModal);
	document.getElementById('epRepoCancelar').addEventListener('click', cerrarModal);
	document.getElementById('epRepoModalCerrar').addEventListener('click', cerrarModal);
	document.getElementById('epRepoModalFondo').addEventListener('click', cerrarModal);
	document.getElementById('epRepoLimpiar').addEventListener('click', reiniciar);
	document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && !modal.classList.contains('hidden')) cerrarModal(); });

	document.getElementById('epRepoUno').addEventListener('submit', function (ev) {
		ev.preventDefault();
		if (!uno.material.value.trim() && !uno.campana.value.trim()) { avisar('warning', 'Escribe algo', 'Llena el material, la campaña o los dos.'); return; }
		sumar('material', [uno.material.value]);
		sumar('campana', [uno.campana.value]);
		uno.material.value = '';
		uno.campana.value = '';
		uno.material.focus();
		pintarPendiente();
	});

	archivo.addEventListener('change', function () {
		if (!archivo.files.length) return;
		archivoTxt.textContent = archivo.files[0].name;
		post('getters/repositorio_leer_archivo.php', formulario({ archivo: archivo.files[0] })).then(function (r) {
			if (!r || !r.ok) { avisar('error', 'No se pudo leer', (r && r.message) || 'Intenta de nuevo.'); return; }
			sumar('material', r.materiales);
			sumar('campana', r.campanas);
			pintarPendiente();
			if (!r.materiales.length && !r.campanas.length) avisar('info', 'Archivo vacío', 'No se encontraron nombres en las columnas A y B.');
		}).catch(function () { avisar('error', 'No se pudo leer', 'El servidor no respondió correctamente.'); });
	});

	btnGuardar.addEventListener('click', function () {
		btnGuardar.disabled = true;
		post('getters/repositorio_agregar.php', formulario({ materiales: pend.material.join('\n'), campanas: pend.campana.join('\n') })).then(function (r) {
			if (!r || !r.ok) { btnGuardar.disabled = false; avisar('error', 'No se pudo guardar', (r && r.message) || 'Intenta de nuevo.'); return; }
			datos.material = r.material;
			datos.campana = r.campana;
			pintar();
			cerrarModal();
			avisar(r.agregados ? 'success' : 'info', r.agregados ? 'Listo' : 'Sin cambios', r.agregados ? 'Se agregaron ' + r.agregados + ' nombres.' : 'Todos ya estaban en las listas.');
		}).catch(function () {
			btnGuardar.disabled = false;
			avisar('error', 'No se pudo guardar', 'El servidor no respondió correctamente.');
		});
	});

	// ---------- Editar y eliminar, cada uno con su advertencia ----------
	function buscarItem(fila) {
		var tipo = fila.dataset.tipo;
		var id = parseInt(fila.dataset.id, 10);
		return { tipo: tipo, id: id, item: (datos[tipo] || []).filter(function (i) { return i.id === id; })[0] };
	}
	function aplicar(r, fallo) {
		if (r && r.ok) { datos.material = r.material || datos.material; datos.campana = r.campana || datos.campana; pintar(); return; }
		avisar('error', fallo, (r && r.message) || 'Intenta de nuevo.');
	}
	raiz.addEventListener('click', function (ev) {
		var boton = ev.target.closest('.ep-repo-accion');
		if (!boton || !window.Swal) return;
		var sel = buscarItem(boton.closest('.ep-repo-fila'));
		if (!sel.item) return;
		if (boton.classList.contains('ep-repo-editar')) {
			Swal.fire({
				title: 'Editar ' + etiquetas[sel.tipo].toLowerCase(), input: 'text', inputValue: sel.item.nombre,
				inputAttributes: { maxlength: sel.tipo === 'material' ? 60 : 40, autocapitalize: 'characters' },
				text: 'Solo cambia el nombre en la lista. Los meses y reportes ya cargados conservan el nombre anterior.',
				showCancelButton: true, confirmButtonText: 'Guardar', cancelButtonText: 'Cancelar',
				inputValidator: function (v) { return v.trim() ? null : 'Escribe el nombre.'; }
			}).then(function (res) {
				if (!res.isConfirmed) return;
				post('getters/repositorio_editar.php', formulario({ id: sel.id, nombre: res.value })).then(function (r) { aplicar(r, 'No se pudo editar'); });
			});
			return;
		}
		Swal.fire({
			icon: 'warning', title: '¿Eliminar «' + sel.item.nombre + '»?',
			text: 'Dejará de aparecer en la lista al cargar un mes. Los meses y reportes ya cargados no se afectan.',
			showCancelButton: true, confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar'
		}).then(function (res) {
			if (!res.isConfirmed) return;
			post('getters/repositorio_quitar.php', formulario({ id: sel.id })).then(function (r) {
				if (r && r.ok) { datos[sel.tipo] = datos[sel.tipo].filter(function (i) { return i.id !== sel.id; }); pintar(); }
				else avisar('error', 'No se pudo eliminar', (r && r.message) || 'Intenta de nuevo.');
			});
		});
	});

	pintar();
	posicionarIndicador();
	if (document.fonts && document.fonts.ready) document.fonts.ready.then(posicionarIndicador);
})();

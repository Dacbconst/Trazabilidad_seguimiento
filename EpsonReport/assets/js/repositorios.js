// Repositorios de POP: pestañas con indicador deslizante, búsqueda, subir archivo o lista y quitar nombres.
(function () {
	var raiz = document.getElementById('epRepo');
	if (!raiz) return;

	var datos = JSON.parse(document.getElementById('epRepoDatos').textContent || '{}');
	var textos = {
		material: { plural: 'materiales', subtitulo: 'Materiales de los que se elige al cargar el mes de Colocación de POP.', vacio: 'Todavía no hay materiales. Sube el formato lleno o escribe la lista.' },
		campana: { plural: 'campañas', subtitulo: 'Campañas de las que se elige al cargar el mes de Colocación de POP.', vacio: 'Todavía no hay campañas. Sube el formato lleno o escribe la lista.' }
	};
	var tipo = 'material';
	var tabs = Array.prototype.slice.call(raiz.querySelectorAll('.ep-repo-tab'));
	var indicador = document.getElementById('epRepoIndicador');
	var lista = document.getElementById('epRepoLista');
	var buscar = document.getElementById('epRepoBuscar');
	var modal = document.getElementById('epRepoModal');
	var texto = document.getElementById('epRepoTexto');
	var archivo = document.getElementById('epRepoArchivo');
	var archivoTxt = document.getElementById('epRepoArchivoTxt');
	var btnGuardar = document.getElementById('epRepoGuardar');

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
		indicador.style.left = activa.offsetLeft + 'px';
		indicador.style.width = activa.offsetWidth + 'px';
	}

	function pintar() {
		var q = buscar.value.trim().toUpperCase();
		var items = (datos[tipo] || []).filter(function (i) { return !q || i.nombre.indexOf(q) !== -1; });
		document.getElementById('epRepoCuentaMaterial').textContent = (datos.material || []).length;
		document.getElementById('epRepoCuentaCampana').textContent = (datos.campana || []).length;
		if (!items.length) {
			lista.innerHTML = '<div class="ep-repo-vacio">' + (q ? 'Nada coincide con tu búsqueda.' : textos[tipo].vacio) + '</div>';
			return;
		}
		lista.innerHTML = items.map(function (i) {
			var uso = i.uso ? i.uso + (i.uso === 1 ? ' mes' : ' meses') : 'Sin usar';
			return '<div class="ep-repo-fila" data-id="' + i.id + '"><span class="ep-repo-nombre">' + escapar(i.nombre) + '</span><span class="ep-repo-uso">' + uso + '</span>'
				+ '<button type="button" class="ep-repo-quitar" aria-label="Quitar ' + escapar(i.nombre) + '"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-8 0l1 13a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2l1-13"/></svg></button></div>';
		}).join('');
	}

	function elegirTab(nuevo) {
		tipo = nuevo;
		tabs.forEach(function (t) {
			var on = t.dataset.tipo === tipo;
			t.classList.toggle('activo', on);
			t.setAttribute('aria-selected', on ? 'true' : 'false');
		});
		document.getElementById('epRepoSubtitulo').textContent = textos[tipo].subtitulo;
		document.getElementById('epRepoFormato').href = 'getters/repositorio_plantilla.php?tipo=' + tipo;
		buscar.value = '';
		posicionarIndicador();
		pintar();
	}
	tabs.forEach(function (t) { t.addEventListener('click', function () { elegirTab(t.dataset.tipo); }); });
	buscar.addEventListener('input', pintar);
	window.addEventListener('resize', posicionarIndicador);

	// ---------- Subir archivo o escribir la lista ----------
	function abrirModal() {
		document.getElementById('epRepoModalTitulo').textContent = 'Subir ' + textos[tipo].plural;
		texto.value = '';
		archivo.value = '';
		archivoTxt.textContent = 'Elegir archivo .xlsx, .csv o .txt';
		modal.classList.remove('hidden');
		texto.focus();
	}
	function cerrarModal() { modal.classList.add('hidden'); }
	document.getElementById('epRepoAgregar').addEventListener('click', abrirModal);
	document.getElementById('epRepoCancelar').addEventListener('click', cerrarModal);
	document.getElementById('epRepoModalCerrar').addEventListener('click', cerrarModal);
	document.getElementById('epRepoModalFondo').addEventListener('click', cerrarModal);

	archivo.addEventListener('change', function () {
		if (!archivo.files.length) return;
		archivoTxt.textContent = archivo.files[0].name;
		post('getters/repositorio_leer_archivo.php', formulario({ tipo: tipo, archivo: archivo.files[0] })).then(function (r) {
			if (r && r.ok) {
				texto.value = r.nombres.join('\n');
				if (!r.nombres.length) avisar('info', 'Archivo vacío', 'No se encontraron nombres en la primera columna.');
			} else {
				avisar('error', 'No se pudo leer', (r && r.message) || 'Intenta de nuevo.');
			}
		}).catch(function () { avisar('error', 'No se pudo leer', 'El servidor no respondió correctamente.'); });
	});

	btnGuardar.addEventListener('click', function () {
		if (!texto.value.trim()) {
			avisar('warning', 'Falta la lista', 'Sube un archivo o escribe al menos un nombre.');
			return;
		}
		btnGuardar.disabled = true;
		post('getters/repositorio_agregar.php', formulario({ tipo: tipo, texto: texto.value })).then(function (r) {
			btnGuardar.disabled = false;
			if (!r || !r.ok) { avisar('error', 'No se pudo guardar', (r && r.message) || 'Intenta de nuevo.'); return; }
			datos[tipo] = r.lista;
			pintar();
			cerrarModal();
			avisar(r.agregados ? 'success' : 'info', r.agregados ? 'Listo' : 'Sin cambios', r.agregados ? 'Se agregaron ' + r.agregados + ' a ' + textos[tipo].plural + '.' : 'Todos ya estaban en la lista.');
		}).catch(function () {
			btnGuardar.disabled = false;
			avisar('error', 'No se pudo guardar', 'El servidor no respondió correctamente.');
		});
	});

	// ---------- Quitar ----------
	lista.addEventListener('click', function (ev) {
		var b = ev.target.closest('.ep-repo-quitar');
		if (!b) return;
		var id = parseInt(b.closest('.ep-repo-fila').dataset.id, 10);
		var item = (datos[tipo] || []).filter(function (i) { return i.id === id; })[0];
		if (!item || !window.Swal) return;
		Swal.fire({
			icon: 'warning', title: '¿Quitar «' + item.nombre + '»?',
			text: 'Dejará de aparecer al cargar un mes. Los meses ya cargados no cambian.',
			showCancelButton: true, confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar'
		}).then(function (res) {
			if (!res.isConfirmed) return;
			post('getters/repositorio_quitar.php', formulario({ id: id })).then(function (r) {
				if (r && r.ok) { datos[tipo] = datos[tipo].filter(function (i) { return i.id !== id; }); pintar(); }
				else avisar('error', 'No se pudo quitar', (r && r.message) || 'Intenta de nuevo.');
			});
		});
	});

	pintar();
	posicionarIndicador();
	if (document.fonts && document.fonts.ready) document.fonts.ready.then(posicionarIndicador);
})();

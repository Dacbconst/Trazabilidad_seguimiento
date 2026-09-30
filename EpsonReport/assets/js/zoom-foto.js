// Vista ampliada de fotos de perfil: window.epZoomFoto(url, nombre) y clic en el avatar del menú lateral.
(function () {
	var capa = null;
	var cierre = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';

	function crear() {
		capa = document.createElement('div');
		capa.className = 'ep-zoom-foto';
		capa.setAttribute('role', 'dialog');
		capa.setAttribute('aria-modal', 'true');
		capa.setAttribute('aria-label', 'Foto de perfil ampliada');
		capa.innerHTML = '<button type="button" class="ep-zoom-foto-x" aria-label="Cerrar">' + cierre + '</button><figure><figcaption></figcaption></figure>';
		// La foto se crea aparte: su src se asigna recién al abrir.
		capa.querySelector('figure').insertBefore(document.createElement('img'), capa.querySelector('figcaption'));
		document.body.appendChild(capa);
		// Clic en el fondo o en la X cierra; clic sobre la foto no.
		capa.addEventListener('click', function (e) {
			if (!e.target.closest('img')) cerrar();
		});
	}

	function abrir(url, nombre) {
		if (!url) return;
		if (!capa) crear();
		var img = capa.querySelector('img');
		img.src = url;
		img.alt = nombre ? 'Foto de ' + nombre : 'Foto de perfil';
		capa.querySelector('figcaption').textContent = nombre || '';
		capa.classList.add('on');
		capa.querySelector('.ep-zoom-foto-x').focus();
	}

	function cerrar() {
		if (capa) capa.classList.remove('on');
	}

	window.epZoomFoto = abrir;

	// Esc cierra solo la vista ampliada, sin cerrar el panel que haya debajo.
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && capa && capa.classList.contains('on')) {
			e.stopPropagation();
			cerrar();
		}
	}, true);

	// Avatar del menú lateral con foto: se amplía en vez de plegar el menú.
	document.addEventListener('click', function (e) {
		var av = e.target.closest('.ep-sidebar-avatar[data-zoom]');
		if (!av) return;
		e.stopPropagation();
		abrir(av.dataset.zoom, av.dataset.nombre);
	}, true);
})();

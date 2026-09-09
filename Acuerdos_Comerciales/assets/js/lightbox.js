// Lightbox reusable, un solo overlay global (markup en index.php) que cualquier módulo abre con window.acAbrirLightbox(src). No implementa pinch-zoom a mano: el viewport nunca deshabilitó el zoom nativo, así que alcanza con mostrar la imagen grande.
(function () {
	var overlay = document.getElementById('acLightboxOverlay');
	var img = document.getElementById('acLightboxImg');
	var closeBtn = document.getElementById('acLightboxClose');
	if (!overlay || !img || !closeBtn) return;

	function abrir(src) {
		img.src = src;
		overlay.classList.add('ac-lightbox-open');
	}
	function cerrar() {
		overlay.classList.remove('ac-lightbox-open');
		img.src = '';
	}

	closeBtn.addEventListener('click', cerrar);
	overlay.addEventListener('click', function (e) {
		if (e.target === overlay) cerrar();
	});
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && overlay.classList.contains('ac-lightbox-open')) cerrar();
	});

	window.acAbrirLightbox = abrir;
	window.acCerrarLightbox = cerrar;
})();

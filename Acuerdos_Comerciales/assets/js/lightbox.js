// Lightbox de imágenes, reusable a nivel proyecto (2026-08-25) — pedido
// explícito para poder ver bien (con zoom) las fotos del Acta firmada en
// mobile. Un solo overlay global (markup en index.php,
// #acLightboxOverlay/#acLightboxImg/#acLightboxClose) — cualquier módulo
// lo abre con window.acAbrirLightbox(srcDeLaImagen). No implementa pinch-
// zoom a mano: el viewport de la app nunca deshabilitó el zoom nativo del
// navegador (sin user-scalable=no/maximum-scale en el <meta viewport>), así
// que alcanza con mostrar la imagen grande — el zoom real lo hace el
// navegador solo.
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

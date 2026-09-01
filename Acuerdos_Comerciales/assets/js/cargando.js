// Feedback de carga reusable: acBotonCargando(btn, bool) gira el ícono y deshabilita el botón; acMostrarCargando()/acOcultarCargando()
// ponen un overlay con spinner sobre cualquier contenedor con position:relative.
(function () {
	window.acBotonCargando = function (btn, cargando) {
		if (!btn) return;
		var icon = btn.querySelector('.material-symbols-outlined');
		if (cargando) {
			if (icon) icon.classList.add('ac-spin');
			btn.disabled = true;
		} else {
			if (icon) icon.classList.remove('ac-spin');
			btn.disabled = false;
		}
	};

	window.acMostrarCargando = function (contenedor) {
		if (!contenedor || contenedor.querySelector(':scope > .ac-cargando-overlay')) return;
		var overlay = document.createElement('div');
		overlay.className = 'ac-cargando-overlay';
		overlay.innerHTML = '<span class="material-symbols-outlined ac-spin">progress_activity</span>';
		contenedor.appendChild(overlay);
	};

	window.acOcultarCargando = function (contenedor) {
		if (!contenedor) return;
		var overlay = contenedor.querySelector(':scope > .ac-cargando-overlay');
		if (overlay) overlay.remove();
	};

	// Overlay centrado en la pantalla (no en un contenedor): acMostrarCargando() queda fuera de vista en formularios más altos que la pantalla.
	window.acMostrarCargandoPantalla = function (mensaje) {
		if (document.querySelector('.ac-cargando-pantalla')) return;
		var overlay = document.createElement('div');
		overlay.className = 'ac-cargando-pantalla';
		overlay.innerHTML =
			'<div class="ac-cargando-pantalla-caja">' +
				'<span class="material-symbols-outlined ac-spin ac-cargando-pantalla-icono">progress_activity</span>' +
				'<p class="ac-cargando-pantalla-texto">' + (mensaje || 'Cargando') +
					'<span class="ac-cargando-puntos"><span>.</span><span>.</span><span>.</span></span>' +
				'</p>' +
			'</div>';
		document.body.appendChild(overlay);
	};

	window.acOcultarCargandoPantalla = function () {
		var overlay = document.querySelector('.ac-cargando-pantalla');
		if (overlay) overlay.remove();
	};
})();

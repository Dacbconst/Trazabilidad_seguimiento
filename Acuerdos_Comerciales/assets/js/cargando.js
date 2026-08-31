// Feedback de carga reusable a nivel proyecto (2026-08-25) — pedido
// explícito tras reportar que "Actualizar" en Historial no daba ninguna
// señal visible de que algo estaba pasando. 2 utilidades chicas, sin
// dependencias, pensadas para llamarse desde CUALQUIER fetch() de
// cualquier módulo (no solo Historial):
//   acBotonCargando(btn, true/false) — ícono del botón gira (.ac-spin) y
//     el botón se deshabilita mientras carga.
//   acMostrarCargando(contenedor) / acOcultarCargando(contenedor) —
//     overlay semitransparente con spinner centrado sobre CUALQUIER
//     contenedor con position:relative (todas las .ac-card ya lo tienen,
//     ver style.css).
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

	// Overlay centrado en la PANTALLA, no en un contenedor (2026-08-31,
	// pedido explícito: "Previsualización" en Registrar deja el formulario
	// blanquecino sin ningún mensaje, porque acMostrarCargando() centra el
	// spinner dentro de acuerdoContainer — un formulario mucho más alto que
	// la pantalla, así que el spinner quedaba fuera de vista mientras se
	// generaba el PDF). `position:fixed` sobre `document.body`, con un
	// mensaje real (no solo un ícono) y puntos suspensivos animados — pensado
	// para reusarse en cualquier acción larga de cualquier módulo, no solo
	// Registrar.
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

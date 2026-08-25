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
})();

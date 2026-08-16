// Notificaciones toast compartidas por todo el proyecto (Registrar, Gestión
// de Usuarios, etc.) — un solo mostrarToast(mensaje, tipo) global en vez de
// que cada componente arme su propio mensaje inline (ac-form-msg viejo).
(function () {
	var ICONOS = {
		success: 'check_circle',
		error:   'cancel',
		warning: 'warning',
		info:    'info'
	};
	var DURACION_MS = 4000;

	var contenedor = null;
	function obtenerContenedor() {
		if (!contenedor) {
			contenedor = document.createElement('div');
			contenedor.className = 'ac-toast-container';
			document.body.appendChild(contenedor);
		}
		return contenedor;
	}

	// tipo: 'success' | 'error' | 'warning' | 'info' (default 'info' si no se reconoce).
	window.mostrarToast = function (mensaje, tipo) {
		if (!ICONOS[tipo]) tipo = 'info';

		var toast = document.createElement('div');
		toast.className = 'ac-toast ac-toast-' + tipo;
		toast.innerHTML =
			'<span class="ac-toast-icon"><span class="material-symbols-outlined">' + ICONOS[tipo] + '</span></span>' +
			'<span class="ac-toast-msg"></span>' +
			'<button type="button" class="ac-toast-close" aria-label="Cerrar"><span class="material-symbols-outlined">close</span></button>' +
			'<span class="ac-toast-bar"></span>';
		// textContent (no innerHTML) para el mensaje: puede traer texto del
		// backend (ej. errores de validación) que nunca debe interpretarse como HTML.
		toast.querySelector('.ac-toast-msg').textContent = mensaje;
		toast.querySelector('.ac-toast-bar').style.animationDuration = DURACION_MS + 'ms';

		var cerrado = false;
		function cerrar() {
			if (cerrado) return;
			cerrado = true;
			toast.classList.add('ac-toast-cerrando');
			setTimeout(function () { toast.remove(); }, 200);
		}

		toast.querySelector('.ac-toast-close').addEventListener('click', cerrar);
		setTimeout(cerrar, DURACION_MS);

		obtenerContenedor().appendChild(toast);
	};
})();

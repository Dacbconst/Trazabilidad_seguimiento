// Mi perfil: abre y cierra la ventana, guarda el correo y cambia la clave.
(function () {
	var capa = document.getElementById('epPerfil');
	var abrirBtn = document.getElementById('epPerfilAbrir');
	if (!capa || !abrirBtn) return;

	var hoja = capa.querySelector('.ep-perfil-hoja');
	var formCorreo = document.getElementById('epPerfilCorreo');
	var formClave = document.getElementById('epPerfilClave');

	function abrir() {
		capa.hidden = false;
		document.body.style.overflow = 'hidden';
		hoja.querySelector('.ep-perfil-x').focus();
	}

	function cerrar() {
		capa.hidden = true;
		document.body.style.overflow = '';
		abrirBtn.focus();
	}

	function mensaje(form, texto, tipo) {
		var p = form.querySelector('[data-msg]');
		p.textContent = texto;
		p.className = 'ep-perfil-msg' + (tipo ? ' ' + tipo : '');
	}

	// Envía el formulario por POST y devuelve la respuesta JSON; un error de red cuenta como no ok.
	function enviar(url, form) {
		var boton = form.querySelector('button[type="submit"]');
		boton.disabled = true;
		return fetch(url, { method: 'POST', body: new FormData(form) })
			.then(function (r) { return r.json(); })
			.catch(function () { return { ok: false, message: 'No hay conexión. Intenta de nuevo.' }; })
			.then(function (r) { boton.disabled = false; return r; });
	}

	abrirBtn.addEventListener('click', abrir);
	capa.addEventListener('click', function (ev) { if (ev.target.closest('[data-perfil-cerrar]')) cerrar(); });
	document.addEventListener('keydown', function (ev) {
		if (ev.key === 'Escape' && !capa.hidden) cerrar();
	});

	// Mantiene el Tab dentro de la ventana mientras está abierta.
	capa.addEventListener('keydown', function (ev) {
		if (ev.key !== 'Tab') return;
		var foco = Array.prototype.filter.call(hoja.querySelectorAll('button, input'), function (el) { return !el.disabled && el.offsetParent !== null; });
		if (!foco.length) return;
		var primero = foco[0];
		var ultimo = foco[foco.length - 1];
		if (ev.shiftKey && document.activeElement === primero) { ev.preventDefault(); ultimo.focus(); }
		else if (!ev.shiftKey && document.activeElement === ultimo) { ev.preventDefault(); primero.focus(); }
	});

	// Ojo de las claves: alterna entre ver y ocultar lo escrito.
	formClave.addEventListener('click', function (ev) {
		var ojo = ev.target.closest('.ep-perfil-ojo');
		if (!ojo) return;
		var campo = ojo.parentNode.querySelector('input');
		var ver = campo.type === 'password';
		campo.type = ver ? 'text' : 'password';
		ojo.setAttribute('aria-pressed', ver ? 'true' : 'false');
		ojo.setAttribute('aria-label', ver ? 'Ocultar clave' : 'Mostrar clave');
	});

	formCorreo.addEventListener('submit', function (ev) {
		ev.preventDefault();
		mensaje(formCorreo, '', '');
		enviar('getters/perfil_correo.php', formCorreo).then(function (r) {
			if (r.ok && typeof r.correo === 'string') formCorreo.elements.correo.value = r.correo;
			mensaje(formCorreo, r.message || 'Listo.', r.ok ? 'ok' : 'error');
		});
	});

	formClave.addEventListener('submit', function (ev) {
		ev.preventDefault();
		mensaje(formClave, '', '');
		enviar('getters/perfil_clave.php', formClave).then(function (r) {
			if (r.ok) formClave.reset();
			mensaje(formClave, r.message || 'Listo.', r.ok ? 'ok' : 'error');
		});
	});
})();

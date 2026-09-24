// Pantalla de acceso: mostrar contraseña, ingreso por fetch y registro del primer ingreso.
(function () {
	var script = document.currentScript;
	var iconoVer = script.dataset.ojo;
	var iconoOcultar = script.dataset.ojoOff;
	var formLogin = document.getElementById('epLoginForm');
	var formRegistro = document.getElementById('epRegistroForm');
	var errorRegistro = document.getElementById('epRegistroError');
	if (!formLogin || !formRegistro) return;

	var mensajesRegistro = {
		datos: 'Completa todos los campos.',
		correo: 'Escribe un correo válido.',
		no_coinciden: 'Las dos contraseñas no son iguales.',
		clave_corta: 'La contraseña debe tener al menos 6 caracteres.',
		cedula: 'La cédula no coincide con la registrada para este usuario.',
		sin_cedula: 'Tu usuario no tiene cédula registrada. Pide al administrador que habilite tu acceso.',
		ya_registrado: 'Este usuario ya tiene contraseña. Inicia sesión.',
		bloqueado: 'Demasiados intentos fallidos. Espera 15 minutos e intenta de nuevo.',
		servidor: 'No se pudo conectar con el servidor. Intenta de nuevo en unos minutos.',
		credenciales: 'No encontramos ese usuario en el equipo.'
	};

	function alternarVista(registro) {
		formLogin.classList.toggle('ep-login-oculto', registro);
		formRegistro.classList.toggle('ep-login-oculto', !registro);
		errorRegistro.classList.add('ep-login-oculto');
		var foco = registro ? document.getElementById('ep-reg-cedula') : document.getElementById('ep-usuario');
		if (foco) foco.focus();
	}

	function mostrarErrorRegistro(motivo) {
		errorRegistro.textContent = mensajesRegistro[motivo] || mensajesRegistro.servidor;
		errorRegistro.classList.remove('ep-login-oculto');
	}

	// Mostrar u ocultar cualquier contraseña marcada con data-alternar.
	document.querySelectorAll('[data-alternar]').forEach(function (boton) {
		boton.addEventListener('click', function () {
			var campo = document.getElementById(boton.dataset.alternar);
			var mostrar = campo.type === 'password';
			campo.type = mostrar ? 'text' : 'password';
			boton.innerHTML = mostrar ? iconoOcultar : iconoVer;
			boton.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
		});
	});

	// Ingreso: si la cuenta ya está abierta en otro dispositivo, se pregunta antes de cerrar esa sesión.
	var botonLogin = formLogin.querySelector('button[type="submit"]');
	formLogin.addEventListener('submit', function (e) {
		e.preventDefault();
		ingresar(formLogin, false);
	});

	function ingresar(form, forzar) {
		var datos = new FormData(form);
		datos.append('forzar', forzar ? '1' : '0');
		botonLogin.disabled = true;
		fetch('getters/procesar_login.php', { method: 'POST', body: datos })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data.ok) { window.location.href = data.redirect || 'index.php'; return; }
				botonLogin.disabled = false;
				if (data.motivo === 'registrar') { irARegistro(document.getElementById('ep-usuario').value.trim()); return; }
				avisarFallo(data.motivo, form);
			})
			.catch(function () {
				botonLogin.disabled = false;
				Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar. Intenta de nuevo.' });
			});
	}

	function avisarFallo(motivo, form) {
		if (motivo === 'sesion_activa') {
			Swal.fire({
				icon: 'warning',
				title: 'Ya tienes una sesión activa',
				text: 'Este usuario ya está conectado en otro dispositivo. ¿Deseas cerrar esa sesión para ingresar aquí?',
				showCancelButton: true,
				confirmButtonText: 'Cerrar esa sesión y entrar aquí',
				cancelButtonText: 'Cancelar'
			}).then(function (res) { if (res.isConfirmed) ingresar(form, true); });
		} else if (motivo === 'bloqueado') {
			Swal.fire({ icon: 'error', title: 'Cuenta bloqueada', text: 'Demasiados intentos fallidos. Espera 15 minutos e intenta de nuevo.' });
		} else if (motivo === 'servidor') {
			Swal.fire({ icon: 'error', title: 'Sin conexión', text: 'No se pudo conectar con el servidor. Intenta de nuevo en unos minutos.' });
		} else {
			Swal.fire({ icon: 'error', title: 'Datos incorrectos', text: 'Usuario o contraseña incorrectos.' });
		}
	}

	// Registro: se lleva el usuario ya escrito y, al crear la contraseña, entra directo.
	function irARegistro(usuario) {
		document.getElementById('ep-reg-usuario').value = usuario;
		alternarVista(true);
	}
	// Primera vez: se pide el usuario y solo si existe en el equipo se pasa a crear la contraseña.
	document.getElementById('epPrimeraVez').addEventListener('click', function () {
		Swal.fire({
			title: 'Primer ingreso',
			text: 'Escribe tu usuario para verificar que estás autorizado.',
			input: 'text',
			inputPlaceholder: 'nombre.apellido',
			inputValue: document.getElementById('ep-usuario').value.trim(),
			inputAttributes: { autocapitalize: 'off', autocomplete: 'off' },
			showCancelButton: true,
			confirmButtonText: 'Continuar',
			cancelButtonText: 'Cancelar',
			showLoaderOnConfirm: true,
			preConfirm: function (valor) {
				var usuario = (valor || '').trim();
				if (!usuario) { Swal.showValidationMessage('Escribe tu usuario.'); return false; }
				var datos = new FormData();
				datos.append('usuario', usuario);
				return fetch('getters/verificar_usuario.php', { method: 'POST', body: datos })
					.then(function (r) { return r.json(); })
					.then(function (data) {
						if (data.ok) return usuario;
						var mensajes = { no_autorizado: 'Usuario no autorizado.', ya_registrado: 'Este usuario ya tiene contraseña. Inicia sesión.' };
						Swal.showValidationMessage(mensajes[data.motivo] || 'No se pudo conectar. Intenta de nuevo.');
						return false;
					})
					.catch(function () { Swal.showValidationMessage('No se pudo conectar. Intenta de nuevo.'); return false; });
			}
		}).then(function (res) { if (res.isConfirmed && res.value) irARegistro(res.value); });
	});
	document.getElementById('epRegistroVolver').addEventListener('click', function () { alternarVista(false); });

	var botonRegistro = formRegistro.querySelector('button[type="submit"]');
	formRegistro.addEventListener('submit', function (e) {
		e.preventDefault();
		errorRegistro.classList.add('ep-login-oculto');
		botonRegistro.disabled = true;
		fetch('getters/procesar_registro.php', { method: 'POST', body: new FormData(formRegistro) })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) { botonRegistro.disabled = false; mostrarErrorRegistro(data.motivo); return; }
				// Contraseña creada: se entra con ella sin pedir nada más.
				document.getElementById('ep-usuario').value = document.getElementById('ep-reg-usuario').value;
				document.getElementById('ep-clave').value = document.getElementById('ep-reg-clave').value;
				alternarVista(false);
				botonRegistro.disabled = false;
				ingresar(formLogin, false);
			})
			.catch(function () { botonRegistro.disabled = false; mostrarErrorRegistro('servidor'); });
	});
})();

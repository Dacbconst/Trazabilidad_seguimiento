(function () {
	var buscarInput     = document.getElementById('us-buscar');
	var tbody           = document.getElementById('tabla-usuarios-body');
	var paginacionEl    = document.getElementById('paginacion-usuarios');
	var paginacionInfo  = document.getElementById('paginacion-info');
	var paginacionBtns  = document.getElementById('paginacion-btns');
	var formNuevo       = document.getElementById('form-nuevo-usuario');
	var nuMsg           = document.getElementById('nu-msg');
	var nuSubmit        = document.getElementById('nu-submit');
	var modalClave      = document.getElementById('modal-clave');
	var formClave       = document.getElementById('form-clave');
	var clMsg           = document.getElementById('cl-msg');
	var clSubmit        = document.getElementById('cl-submit');
	var modalRol        = document.getElementById('modal-rol');
	var formRol         = document.getElementById('form-rol');
	var rlMsg           = document.getElementById('rl-msg');
	var rlSubmit        = document.getElementById('rl-submit');
	var nuUsuario       = document.getElementById('nu-usuario');
	var nuSupervisor    = document.getElementById('nu-supervisor');
	var rlSupervisor    = document.getElementById('rl-supervisor');
	var buscarTimeout   = null;

	// ---------- Supervisor: 1 supervisor = 1 cuenta (ver functions.php) ----------
	// Autocompletar Nombre de Usuario con el supervisor elegido (el admin
	// puede editarlo después si quiere un usuario distinto); y mantener los
	// combos de supervisor sincronizados con quién ya lo tiene asignado.
	nuSupervisor.addEventListener('change', function () {
		nuUsuario.value = nuSupervisor.value || '';
	});

	function refrescarSupervisores() {
		fetch('getters/supervisores_disponibles.php')
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) return;
				pintarSupervisoresNuevo(data.supervisores);
				pintarSupervisoresEditar(data.supervisores);
			});
	}

	function pintarSupervisoresNuevo(supervisores) {
		var valorPrevio = nuSupervisor.value;
		var html = '<option value="">Sin asignar</option>';
		supervisores.forEach(function (s) {
			if (s.tomado_por) return; // ya tiene cuenta, no se puede repetir
			html += '<option value="' + escapeAttr(s.nombre) + '">' + escapeHtml(s.nombre) + '</option>';
		});
		nuSupervisor.innerHTML = html;
		nuSupervisor.value = valorPrevio; // si seguía disponible, se conserva; si no, vuelve a "Sin asignar"
	}

	function pintarSupervisoresEditar(supervisores) {
		var html = '<option value="">Sin asignar</option>';
		supervisores.forEach(function (s) {
			var tomadoAttr = s.tomado_por ? ' data-tomado-por="' + escapeAttr(s.tomado_por) + '"' : '';
			var etiqueta   = s.tomado_por ? s.nombre + ' (usado por ' + s.tomado_por + ')' : s.nombre;
			html += '<option value="' + escapeAttr(s.nombre) + '"' + tomadoAttr + '>' + escapeHtml(etiqueta) + '</option>';
		});
		rlSupervisor.innerHTML = html;
	}

	function escapeHtml(texto) {
		var div = document.createElement('div');
		div.textContent = texto;
		return div.innerHTML;
	}

	function escapeAttr(texto) {
		return escapeHtml(texto).replace(/"/g, '&quot;');
	}

	function claveValida(valor) {
		return valor.length >= 4;
	}

	function vincularToggleClave(inputId, iconId, btnId) {
		var btn = document.getElementById(btnId);
		var input = document.getElementById(inputId);
		var icon = document.getElementById(iconId);
		btn.addEventListener('click', function () {
			var showing = input.type === 'text';
			input.type = showing ? 'password' : 'text';
			icon.textContent = showing ? 'visibility' : 'visibility_off';
		});
	}

	function mostrarMensaje(el, texto, ok) {
		el.textContent = texto;
		el.className = 'ac-form-msg ' + (ok ? 'ac-form-msg-success' : 'ac-form-msg-error');
	}

	function cargarUsuarios(pagina) {
		var q   = buscarInput.value.trim();
		var url = 'getters/tabla_usuarios.php?q=' + encodeURIComponent(q) + '&pg=' + (pagina || 1);

		fetch(url)
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) return;
				tbody.innerHTML = data.filas;
				paginacionEl.dataset.pagina = data.pagina;
				paginacionEl.dataset.totalPaginas = data.total_paginas;
				paginacionInfo.textContent = 'Página ' + data.pagina + ' de ' + data.total_paginas;
				renderPaginacionBtns(data.pagina, data.total_paginas);
				vincularEventosFila();
			});
	}

	function renderPaginacionBtns(pagina, totalPaginas) {
		var html = '';
		html += '<button type="button" class="ac-page-btn" data-pg="' + (pagina - 1) + '" ' + (pagina <= 1 ? 'disabled' : '') + '>' +
			'<span class="material-symbols-outlined">chevron_left</span></button>';
		for (var i = 1; i <= totalPaginas; i++) {
			html += '<button type="button" class="ac-page-btn' + (i === pagina ? ' ac-page-btn-active' : '') + '" data-pg="' + i + '">' + i + '</button>';
		}
		html += '<button type="button" class="ac-page-btn" data-pg="' + (pagina + 1) + '" ' + (pagina >= totalPaginas ? 'disabled' : '') + '>' +
			'<span class="material-symbols-outlined">chevron_right</span></button>';
		paginacionBtns.innerHTML = html;

		Array.prototype.forEach.call(paginacionBtns.querySelectorAll('.ac-page-btn'), function (btn) {
			btn.addEventListener('click', function () {
				if (!btn.disabled) cargarUsuarios(parseInt(btn.dataset.pg, 10));
			});
		});
	}

	buscarInput.addEventListener('input', function () {
		clearTimeout(buscarTimeout);
		buscarTimeout = setTimeout(function () { cargarUsuarios(1); }, 350);
	});

	// ---------- Crear usuario ----------
	formNuevo.addEventListener('submit', function (e) {
		e.preventDefault();

		var password = document.getElementById('nu-password').value;
		if (!claveValida(password)) {
			mostrarMensaje(nuMsg, 'La clave debe tener al menos 4 caracteres.', false);
			return;
		}

		nuSubmit.disabled = true;
		fetch('getters/crear_usuario.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams(new FormData(formNuevo))
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				nuSubmit.disabled = false;
				mostrarMensaje(nuMsg, data.message, data.ok);
				if (data.ok) {
					formNuevo.reset();
					buscarInput.value = '';
					cargarUsuarios(1);
					refrescarSupervisores();
				}
			})
			.catch(function () {
				nuSubmit.disabled = false;
				mostrarMensaje(nuMsg, 'Error de conexión. Intenta nuevamente.', false);
			});
	});

	// ---------- Acciones por fila (toggle estado, clave, editar) ----------
	function vincularEventosFila() {
		Array.prototype.forEach.call(tbody.querySelectorAll('.ac-toggle-estado'), function (input) {
			input.addEventListener('change', function () {
				var fila = input.closest('tr');
				var nuevoStatus = input.checked ? 'activo' : 'inactivo';

				fetch('getters/actualizar_usuario.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({ id: input.dataset.id, status: nuevoStatus })
				})
					.then(function (r) { return r.json(); })
					.then(function (data) {
						if (!data.ok) {
							input.checked = !input.checked;
							alert(data.message);
							return;
						}
						fila.classList.toggle('ac-row-inactivo', !input.checked);
						refrescarSupervisores(); // desactivar/reactivar libera u ocupa su supervisor
					})
					.catch(function () {
						input.checked = !input.checked;
						alert('Error de conexión. Intenta nuevamente.');
					});
			});
		});

		Array.prototype.forEach.call(tbody.querySelectorAll('.ac-btn-clave'), function (btn) {
			btn.addEventListener('click', function () {
				abrirModalClave(btn.dataset.id, btn.dataset.usuario);
			});
		});

		Array.prototype.forEach.call(tbody.querySelectorAll('.ac-btn-editar'), function (btn) {
			btn.addEventListener('click', function () {
				abrirModalRol(btn.dataset.id, btn.dataset.usuario, btn.dataset.rol, btn.dataset.supervisor);
			});
		});
	}

	function configurarModal(modalEl, cerrarBtnId) {
		document.getElementById(cerrarBtnId).addEventListener('click', function () {
			modalEl.classList.remove('ac-modal-open');
		});
		modalEl.addEventListener('click', function (e) {
			if (e.target === modalEl) modalEl.classList.remove('ac-modal-open');
		});
	}

	// ---------- Modal "Modificar Clave": única responsabilidad, cambiar la contraseña ----------
	function abrirModalClave(id, usuario) {
		document.getElementById('cl-id').value = id;
		document.getElementById('cl-usuario-label').textContent = usuario;
		document.getElementById('cl-password').value = '';
		clMsg.textContent = '';
		clMsg.className = 'ac-form-msg';
		modalClave.classList.add('ac-modal-open');
		setTimeout(function () { document.getElementById('cl-password').focus(); }, 50);
	}

	configurarModal(modalClave, 'modal-clave-cerrar');

	formClave.addEventListener('submit', function (e) {
		e.preventDefault();

		var password = document.getElementById('cl-password').value;
		if (!claveValida(password)) {
			mostrarMensaje(clMsg, 'La clave debe tener al menos 4 caracteres.', false);
			return;
		}

		clSubmit.disabled = true;
		fetch('getters/actualizar_usuario.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({ id: document.getElementById('cl-id').value, contrasena: password })
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				clSubmit.disabled = false;
				mostrarMensaje(clMsg, data.message, data.ok);
				if (data.ok) {
					setTimeout(function () { modalClave.classList.remove('ac-modal-open'); }, 600);
				}
			})
			.catch(function () {
				clSubmit.disabled = false;
				mostrarMensaje(clMsg, 'Error de conexión. Intenta nuevamente.', false);
			});
	});

	// ---------- Modal "Editar Perfil": única responsabilidad, cambiar el rol ----------
	function abrirModalRol(id, usuario, rol, supervisor) {
		document.getElementById('rl-id').value = id;
		document.getElementById('rl-usuario-label').textContent = usuario;
		document.getElementById('rl-rol').value = rol;

		// El supervisor actual de ESTE usuario debe seguir seleccionable aunque
		// figure como "tomado" (es el suyo); los tomados por otros se deshabilitan.
		Array.prototype.forEach.call(rlSupervisor.options, function (opt) {
			var tomadoPor = opt.dataset.tomadoPor;
			opt.disabled = !!(tomadoPor && tomadoPor !== usuario);
		});
		rlSupervisor.value = supervisor || '';

		rlMsg.textContent = '';
		rlMsg.className = 'ac-form-msg';
		modalRol.classList.add('ac-modal-open');
	}

	configurarModal(modalRol, 'modal-rol-cerrar');

	formRol.addEventListener('submit', function (e) {
		e.preventDefault();

		rlSubmit.disabled = true;
		fetch('getters/actualizar_usuario.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({
				id: document.getElementById('rl-id').value,
				rol: document.getElementById('rl-rol').value,
				supervisor: document.getElementById('rl-supervisor').value
			})
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				rlSubmit.disabled = false;
				mostrarMensaje(rlMsg, data.message, data.ok);
				if (data.ok) {
					cargarUsuarios(parseInt(paginacionEl.dataset.pagina, 10) || 1);
					refrescarSupervisores();
					setTimeout(function () { modalRol.classList.remove('ac-modal-open'); }, 600);
				}
			})
			.catch(function () {
				rlSubmit.disabled = false;
				mostrarMensaje(rlMsg, 'Error de conexión. Intenta nuevamente.', false);
			});
	});

	// Estado inicial: pintar botones de paginación y enganchar filas ya renderizadas por PHP.
	renderPaginacionBtns(parseInt(paginacionEl.dataset.pagina, 10) || 1, parseInt(paginacionEl.dataset.totalPaginas, 10) || 1);
	vincularEventosFila();
	vincularToggleClave('nu-password', 'nu-pw-icon', 'nu-pw-toggle');
	vincularToggleClave('cl-password', 'cl-pw-icon', 'cl-pw-toggle');
})();

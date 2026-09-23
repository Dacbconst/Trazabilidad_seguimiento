<?php

require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/functions.php';
iniciar_sesion();

if (login_check()) {
	header('Location: index.php');
	exit;
}

$error = isset($_GET['error']);
$bloqueado = ($_GET['error'] ?? '') === 'bloqueado';
$sesionCerrada = ($_GET['error'] ?? '') === 'sesion';

// Cache-busting: mismo criterio que usa Proyectos/style.css.
$style_v = @filemtime(__DIR__.'/assets/css/style.css') ?: time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Acuerdos Comerciales — Iniciar sesión</title>
	<link rel="icon" href="assets/img/favicon.ico" sizes="any">
	<link rel="icon" type="image/png" href="assets/img/favicon-32x32.png">
	<link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=block" rel="stylesheet">
	<link rel="stylesheet" href="assets/css/style.css?v=<?= $style_v ?>">
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
</head>
<body>

	<header class="ac-header">
		<div class="ac-header-inner">
			<div class="ac-brand"><img src="assets/img/logo_alicorp.png" alt="Alicorp" class="ac-brand-logo"></div>
		</div>
	</header>

	<main class="ac-login-main">
		<div class="ac-login-card">
			<div class="ac-login-header">
				<div class="ac-login-icon"><span class="material-symbols-outlined">shield_person</span></div>
				<h1 class="ac-login-title">Iniciar Sesión</h1>
				<p class="ac-login-subtitle">Panel de Gestión Comercial</p>
			</div>

			<?php if ($bloqueado): ?>
			<div class="ac-alert-error">Cuenta bloqueada temporalmente por varios intentos fallidos. Intenta de nuevo en unos minutos.</div>
			<?php elseif ($sesionCerrada): ?>
			<div class="ac-alert-error">Tu sesión se cerró porque iniciaste sesión con este usuario en otro dispositivo.</div>
			<?php elseif ($error): ?>
			<div class="ac-alert-error">Usuario o contraseña incorrectos.</div>
			<?php endif; ?>

			<form id="acLoginForm" method="post" action="getters/procesar_acceso.php">
				<div class="ac-field">
					<label class="ac-field-label" for="usuario">Usuario</label>
					<div class="ac-input-wrap">
						<span class="material-symbols-outlined">person</span>
						<input class="ac-input" id="usuario" name="usuario" type="text" autocomplete="username" required>
					</div>
				</div>

				<div class="ac-field">
					<label class="ac-field-label" for="password">Contraseña</label>
					<div class="ac-input-wrap">
						<span class="material-symbols-outlined">lock</span>
						<input class="ac-input" id="password" name="password" type="password" autocomplete="current-password" required style="padding-right:40px;">
						<button class="ac-input-toggle" type="button" onclick="togglePassword()">
							<span class="material-symbols-outlined" id="pw-icon">visibility</span>
						</button>
					</div>
				</div>

				<button class="ac-btn-primary" type="submit">
					Ingresar a la Plataforma
					<span class="material-symbols-outlined">arrow_forward</span>
				</button>
			</form>
		</div>
	</main>

	<footer class="ac-footer">© PromoLucky <?= date('Y') ?></footer>

	<script>
		function togglePassword() {
			const pwInput = document.getElementById('password');
			const pwIcon = document.getElementById('pw-icon');
			const showing = pwInput.type === 'text';
			pwInput.type = showing ? 'password' : 'text';
			pwIcon.textContent = showing ? 'visibility' : 'visibility_off';
		}

		// Quita ?error= de la URL: al recargar, el aviso ya no reaparece.
		if (window.location.search.indexOf('error=') !== -1) history.replaceState(null, '', window.location.pathname);

		// Login por fetch (2026-09-24, pedido explícito): antes de cerrar una sesión activa en otro dispositivo, se pregunta.
		var acLoginForm = document.getElementById('acLoginForm');
		var acLoginBtn = acLoginForm.querySelector('button[type="submit"]');

		acLoginForm.addEventListener('submit', function (e) {
			e.preventDefault();
			enviarLogin(false);
		});

		function enviarLogin(forzar) {
			var usuario = document.getElementById('usuario').value;
			var password = document.getElementById('password').value;
			acLoginBtn.disabled = true;
			fetch('getters/procesar_acceso.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: 'usuario='+encodeURIComponent(usuario)+'&password='+encodeURIComponent(password)+'&forzar='+(forzar ? '1' : '0')
			})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data.ok) {
					window.location.href = 'index.php';
					return;
				}
				acLoginBtn.disabled = false;
				if (data.motivo === 'sesion_activa') {
					Swal.fire({
						icon: 'warning',
						title: 'Ya tienes una sesión activa',
						text: 'Este usuario ya está conectado en otro dispositivo. ¿Deseas cerrar esa sesión para ingresar aquí?',
						showCancelButton: true,
						confirmButtonText: 'Cerrar esa sesión y entrar aquí',
						cancelButtonText: 'Cancelar'
					}).then(function (res) {
						if (res.isConfirmed) enviarLogin(true);
					});
				} else if (data.motivo === 'bloqueado') {
					Swal.fire({ icon: 'error', title: 'Cuenta bloqueada', text: 'Cuenta bloqueada temporalmente por varios intentos fallidos. Intenta de nuevo en unos minutos.' });
				} else if (data.motivo === 'inactivo') {
					Swal.fire({ icon: 'error', title: 'Cuenta inactiva', text: 'Esta cuenta está desactivada. Avisa al administrador para que la reactive.' });
				} else {
					Swal.fire({ icon: 'error', title: 'Datos incorrectos', text: 'Usuario o contraseña incorrectos.' });
				}
			})
			.catch(function () {
				acLoginBtn.disabled = false;
				Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar. Intenta de nuevo.' });
			});
		}
	</script>
</body>
</html>

<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/functions.php';
iniciar_sesion();

if (login_check()) {
	header('Location: index.php');
	exit;
}

$error = isset($_GET['error']);

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
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=block" rel="stylesheet">
	<link rel="stylesheet" href="assets/css/style.css?v=<?= $style_v ?>">
</head>
<body>

	<header class="ac-header">
		<div class="ac-header-inner">
			<div class="ac-brand">Acuerdos Comerciales</div>
		</div>
	</header>

	<main class="ac-login-main">
		<div class="ac-login-card">
			<div class="ac-login-header">
				<div class="ac-login-icon"><span class="material-symbols-outlined">shield_person</span></div>
				<h1 class="ac-login-title">Iniciar Sesión</h1>
				<p class="ac-login-subtitle">Panel de Gestión Comercial</p>
			</div>

			<?php if ($error): ?>
			<div class="ac-alert-error">Usuario o contraseña incorrectos.</div>
			<?php endif; ?>

			<form method="post" action="getters/process_login.php">
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
	</script>
</body>
</html>

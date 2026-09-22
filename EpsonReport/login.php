<?php
require_once __DIR__.'/config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();

if (!empty($_SESSION['usuario'])) {
	header('Location: index.php');
	exit;
}

$error = isset($_GET['error']) ? 'Usuario o contraseña incorrectos.' : '';
require_once __DIR__.'/includes/functions.php';

// Foto del login en celular: poner el archivo en assets/img/login.png (o .jpg/.jpeg) — se detecta solo, sin tocar código.
$loginFotoUrl = null;
foreach (['png', 'jpg', 'jpeg'] as $ext) {
	if (file_exists(__DIR__.'/assets/img/login.'.$ext)) {
		$loginFotoUrl = 'assets/img/login.'.$ext.'?v='.filemtime(__DIR__.'/assets/img/login.'.$ext);
		break;
	}
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>EpsonReport — Iniciar sesión</title>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
	<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
</head>
<body class="ep-login-body">
	<div class="ep-login-wrap">
		<div class="ep-login-blob ep-login-blob-1"></div>
		<div class="ep-login-blob ep-login-blob-2"></div>
		<div class="ep-login-side">
			<div class="ep-brand">
				<div class="ep-brand-mark">ER</div>
				<span class="ep-brand-name">EPSON REPORT</span>
			</div>

			<div class="ep-login-mobile-only ep-login-photo-placeholder">
				<?php if ($loginFotoUrl): ?>
					<img src="<?= htmlspecialchars($loginFotoUrl) ?>" alt="">
				<?php else: ?>
					<span>Foto.png</span>
				<?php endif; ?>
			</div>

			<div class="ep-login-desktop-only" style="display:flex;flex-direction:column;gap:18px;">
				<h1>Trazabilidad de actividades en campo</h1>
				<p style="margin:0;font-size:15px;line-height:1.6;color:#C6CBEE;max-width:360px;">
					Registra, revisa y da seguimiento a las actividades de los técnicos, con historial de visibilidad y evidencia fotográfica.
				</p>
			</div>

			<div class="ep-login-desktop-only" style="display:flex;align-items:center;gap:10px;font-size:13px;color:#A9B0DE;">
				<?= ep_icon('lock', 16) ?>
				Acceso restringido al personal autorizado
			</div>
		</div>

		<div class="ep-login-form">
			<form class="ep-login-form-inner" method="post" action="getters/procesar_login.php">
				<div>
					<h2 style="font-size:26px;">Iniciar sesión</h2>
					<p style="margin:6px 0 0;font-size:14px;color:var(--color-text-muted);">Ingresa tus credenciales para continuar.</p>
				</div>

				<?php if ($error): ?>
					<div class="ep-login-error"><?= htmlspecialchars($error) ?></div>
				<?php endif; ?>

				<div style="display:flex;flex-direction:column;gap:16px;">
					<div style="display:flex;flex-direction:column;gap:6px;">
						<label class="ep-label" for="ep-usuario">Usuario</label>
						<input class="ep-input" id="ep-usuario" name="usuario" type="text" placeholder="nombre.apellido" required autofocus>
					</div>
					<div style="display:flex;flex-direction:column;gap:6px;">
						<label class="ep-label" for="ep-clave">Contraseña</label>
						<div class="ep-login-clave-wrap">
							<input class="ep-input" id="ep-clave" name="clave" type="password" placeholder="••••••••" required>
							<button type="button" class="ep-login-clave-toggle" id="ep-clave-toggle" aria-label="Mostrar contraseña">
								<?= ep_icon('eye', 18) ?>
							</button>
						</div>
					</div>
				</div>

				<button type="submit" class="ep-btn-primary">Ingresar</button>
			</form>
		</div>
	</div>
	<script>
		// Mostrar/ocultar contraseña — solo esta página, no necesita todo app.js.
		(function () {
			var iconoVer = <?= json_encode(ep_icon('eye', 18)) ?>;
			var iconoOcultar = <?= json_encode(ep_icon('eye-off', 18)) ?>;
			var input = document.getElementById('ep-clave');
			var toggle = document.getElementById('ep-clave-toggle');
			if (!input || !toggle) return;
			toggle.addEventListener('click', function () {
				var mostrar = input.type === 'password';
				input.type = mostrar ? 'text' : 'password';
				toggle.innerHTML = mostrar ? iconoOcultar : iconoVer;
				toggle.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
			});
		})();
	</script>
</body>
</html>

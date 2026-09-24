<?php
require_once __DIR__.'/config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();

require_once __DIR__.'/includes/functions.php';

if (ep_login_check()) {
	header('Location: index.php');
	exit;
}

$mensajesError = [
	'bloqueado' => 'Demasiados intentos fallidos. Espera 15 minutos e intenta de nuevo.',
	'sesion'    => 'Tu sesión se cerró porque iniciaste sesión en otro dispositivo.',
	'inactividad' => 'Tu sesión se cerró por 20 minutos de inactividad. Inicia sesión de nuevo.',
	'servidor'  => 'No se pudo conectar con el servidor. Intenta de nuevo en unos minutos.',
];
$error = isset($_GET['error']) ? ($mensajesError[$_GET['error']] ?? 'Usuario o contraseña incorrectos.') : '';
$redirect = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');
require_once __DIR__.'/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>EpsonReport — Iniciar sesión</title>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
	<?php foreach (['base', 'login'] as $hoja): ?>
	<link rel="stylesheet" href="assets/css/<?= $hoja ?>.css?v=<?= filemtime(__DIR__."/assets/css/$hoja.css") ?>">
	<?php endforeach; ?>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
</head>
<body class="ep-login-body">
	<div class="ep-login-wrap">
		<div class="ep-login-blob ep-login-blob-1"></div>
		<div class="ep-login-blob ep-login-blob-2"></div>

		<!-- Panel lateral corporativo (Escritorio) -->
		<div class="ep-login-side">
			<span></span>

			<div class="ep-login-side-main">
				<h1>Control y trazabilidad de actividades</h1>
				<p>Seguimiento operativo y visibilidad en punto de venta.</p>
			</div>

			<div class="ep-login-footer">
				<?= ep_icon('lock', 14) ?>
				<span>Acceso seguro autorizado</span>
			</div>
		</div>

		<!-- Formulario de acceso -->
		<div class="ep-login-form">
			<form class="ep-login-form-inner" id="epLoginForm" method="post" action="getters/procesar_login.php">
				<?php if ($redirect !== ''): ?>
					<input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
				<?php endif; ?>
				<div class="ep-login-header">
					<h2>Iniciar sesión</h2>
				</div>

				<?php if ($error): ?>
					<div class="ep-login-error"><?= htmlspecialchars($error) ?></div>
				<?php endif; ?>

				<div class="ep-login-fields">
					<div class="ep-login-field">
						<label class="ep-label" for="ep-usuario">Usuario</label>
						<input class="ep-input" id="ep-usuario" name="usuario" type="text" placeholder="nombre.apellido" required autofocus>
					</div>
					<div class="ep-login-field">
						<label class="ep-label" for="ep-clave">Contraseña</label>
						<div class="ep-login-clave-wrap">
							<input class="ep-input" id="ep-clave" name="clave" type="password" placeholder="••••••••" required>
							<button type="button" class="ep-login-clave-toggle" data-alternar="ep-clave" aria-label="Mostrar contraseña">
								<?= ep_icon('eye', 18) ?>
							</button>
						</div>
					</div>
				</div>

				<button type="submit" class="ep-btn-primary">Ingresar</button>
				<button type="button" class="ep-login-volver" id="epPrimeraVez">¿Primera vez aquí? Crea tu contraseña</button>
			</form>
			<?php include __DIR__.'/components/login/form_registro.php'; ?>
			<p class="ep-login-copy">© PromoLucky 2026</p>
		</div>
	</div>
	<script src="assets/js/login.js?v=<?= filemtime(__DIR__.'/assets/js/login.js') ?>" data-ojo="<?= htmlspecialchars(ep_icon('eye', 18)) ?>" data-ojo-off="<?= htmlspecialchars(ep_icon('eye-off', 18)) ?>"></script>
</body>
</html>

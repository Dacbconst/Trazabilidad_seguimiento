<?php
// Segundo paso del login: el mercaderista de Xplora crea su contraseña la primera vez.
require_once __DIR__.'/../../includes/login_datos.php';
?>
<form class="ep-login-form-inner ep-login-oculto" id="epRegistroForm" autocomplete="off">
	<div class="ep-login-header">
		<div class="ep-login-paso"><?= ep_icon('lock', 14) ?> Primer ingreso</div>
		<h2>Crea tu contraseña</h2>
		<p>Te encontramos en el equipo. Confirma tu identidad y elige la contraseña con la que vas a entrar.</p>
	</div>

	<div class="ep-login-error ep-login-oculto" id="epRegistroError"></div>

	<div class="ep-login-fields">
		<div class="ep-login-field">
			<label class="ep-label" for="ep-reg-usuario">Usuario</label>
			<input class="ep-input" id="ep-reg-usuario" name="usuario" type="text" readonly>
		</div>
		<div class="ep-login-field">
			<label class="ep-label" for="ep-reg-cedula">Cédula</label>
			<input class="ep-input" id="ep-reg-cedula" name="cedula" type="text" inputmode="numeric" maxlength="15" placeholder="Solo para confirmar que eres tú" required>
		</div>
		<div class="ep-login-field">
			<label class="ep-label" for="ep-reg-correo">Correo</label>
			<input class="ep-input" id="ep-reg-correo" name="correo" type="email" maxlength="150" placeholder="Aparecerá en tus reportes" required>
		</div>
		<div class="ep-login-field">
			<label class="ep-label" for="ep-reg-clave">Contraseña nueva</label>
			<div class="ep-login-clave-wrap">
				<input class="ep-input" id="ep-reg-clave" name="clave" type="password" minlength="<?= EP_CLAVE_MINIMA ?>" placeholder="Mínimo <?= EP_CLAVE_MINIMA ?> caracteres" required>
				<button type="button" class="ep-login-clave-toggle" data-alternar="ep-reg-clave" aria-label="Mostrar contraseña"><?= ep_icon('eye', 18) ?></button>
			</div>
		</div>
		<div class="ep-login-field">
			<label class="ep-label" for="ep-reg-clave2">Repite la contraseña</label>
			<input class="ep-input" id="ep-reg-clave2" name="clave2" type="password" placeholder="Escríbela otra vez" required>
		</div>
	</div>

	<button type="submit" class="ep-btn-primary">Crear contraseña y entrar</button>
	<button type="button" class="ep-login-volver" id="epRegistroVolver"><?= ep_icon('arrow-left', 14) ?> Volver al inicio de sesión</button>
</form>

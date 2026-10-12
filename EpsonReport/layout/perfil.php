<?php
// Ventana "Mi perfil": panel lateral con foto y datos de solo lectura, y a la derecha el correo y la clave; se abre desde el bloque de usuario del menú.
require_once __DIR__.'/../includes/perfil_datos.php';
$perfil = ep_perfil_obtener();
if (!$perfil) {
	return;
}
$h = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
// Campo de clave con el ojo para ver lo que se escribe.
$campoClave = fn(string $nombre, string $placeholder, string $auto) => '<span class="ep-perfil-clave-wrap"><input type="password" class="ep-input" name="'.$nombre.'" placeholder="'.$placeholder.'" autocomplete="'.$auto.'" aria-label="'.$placeholder.'"><button type="button" class="ep-perfil-ojo" aria-label="Mostrar clave" aria-pressed="false"><span class="ojo-ver">'.ep_icon('eye', 18).'</span><span class="ojo-ocultar">'.ep_icon('eye-off', 18).'</span></button></span>';
$datos = array_filter(['Usuario' => $perfil['usuario'], 'Ciudad' => $perfil['ciudad'], 'Canal' => $perfil['canal']]);
?>
<div class="ep-perfil" id="epPerfil" hidden>
	<div class="ep-perfil-fondo" data-perfil-cerrar></div>
	<div class="ep-perfil-hoja" role="dialog" aria-modal="true" aria-labelledby="epPerfilNombre">
		<button type="button" class="ep-perfil-x" data-perfil-cerrar aria-label="Cerrar"><?= ep_icon('close', 18) ?></button>

		<aside class="ep-perfil-lado">
			<span class="ep-perfil-foto"<?= $perfil['foto'] ? ' style="background-image:url(\''.$h($perfil['foto']).'\')"' : '' ?>><?= $perfil['foto'] ? '' : $h(mb_strtoupper(mb_substr($perfil['nombre'], 0, 1))) ?></span>
			<h2 id="epPerfilNombre"><?= $h($perfil['nombre']) ?></h2>
			<span class="ep-perfil-rol"><?= $h($perfil['rol_label']) ?></span>
			<dl class="ep-perfil-datos">
				<?php foreach ($datos as $campo => $valor): ?>
					<div><dt><?= $h($campo) ?></dt><dd><?= $h($valor) ?></dd></div>
				<?php endforeach; ?>
			</dl>
			<p class="ep-perfil-nota"><?= ep_icon('lock', 12) ?> Tu foto y tus datos los actualiza un administrador.</p>
		</aside>

		<div class="ep-perfil-cuerpo">
			<form class="ep-perfil-bloque" id="epPerfilCorreo" novalidate>
				<h3>Correo</h3>
				<p class="ep-perfil-ayuda">Va en los reportes PPT que se generan con tu nombre.</p>
				<div class="ep-perfil-fila">
					<input type="email" id="epPerfilCorreoInput" class="ep-input" name="correo" value="<?= $h($perfil['correo']) ?>" placeholder="nombre@empresa.com" autocomplete="email" maxlength="150" aria-label="Correo">
					<button type="submit" class="ep-perfil-btn">Guardar</button>
				</div>
				<p class="ep-perfil-msg" data-msg role="status"></p>
			</form>

			<form class="ep-perfil-bloque" id="epPerfilClave" novalidate>
				<h3>Cambiar clave</h3>
				<p class="ep-perfil-ayuda">Mínimo <?= (int) EP_CLAVE_MINIMA ?> caracteres. Usa una que no uses en otros sitios.</p>
				<div class="ep-perfil-clave">
					<?= $campoClave('actual', 'Clave actual', 'current-password') ?>
					<div class="ep-perfil-fila">
						<?= $campoClave('clave', 'Clave nueva', 'new-password') ?>
						<?= $campoClave('clave2', 'Repite la clave', 'new-password') ?>
					</div>
					<button type="submit" class="ep-perfil-btn ep-perfil-btn-ancho">Cambiar clave</button>
				</div>
				<p class="ep-perfil-msg" data-msg role="status"></p>
			</form>
		</div>
	</div>
</div>

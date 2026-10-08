<?php
// Usuarios (solo admin): lista con filtros y panel lateral para crear, editar, cambiar clave y activar o desactivar.
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../includes/usuarios_datos.php';

if (ep_rol_actual() !== 'admin') {
	echo '<main class="ep-content"><p>No tienes permiso para ver esta sección.</p></main>';
	return;
}
$usuarios = ep_usuarios_listar();
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$clave = EP_CLAVE_MINIMA;
?>
<main class="ep-content ep-us" id="epUs" data-clave-minima="<?= $clave ?>"<?= ep_usuarios_rutero_vigente() ? '' : ' data-rutero-pendiente="1"' ?>>

	<header class="ep-us-head">
		<div>
			<h1>Usuarios</h1>
			<p>Quién puede entrar a EpsonReport y con qué rol.</p>
		</div>
		<button type="button" class="ep-us-btn ep-us-btn-p" id="epUsNuevo"><?= ep_icon('plus', 18) ?>Nuevo usuario</button>
	</header>

	<div class="ep-us-tools">
		<label class="ep-us-buscar">
			<?= ep_icon('search', 18) ?>
			<input type="search" id="epUsBuscar" placeholder="Buscar por nombre, correo o usuario" autocomplete="off" aria-label="Buscar usuarios">
		</label>
		<select class="ep-us-sel" id="epUsRol" aria-label="Filtrar por rol">
			<option value="">Todos los roles</option>
			<?php foreach (EP_ROLES_USUARIO as $k => $txt): ?><option value="<?= $k ?>"><?= $h($txt) ?></option><?php endforeach; ?>
		</select>
		<select class="ep-us-sel" id="epUsEstado" aria-label="Filtrar por estado">
			<option value="">Todos los estados</option>
			<option value="1">Activos</option>
			<option value="0">Inactivos</option>
		</select>
		<span class="ep-us-cuenta" id="epUsCuenta"></span>
	</div>

	<section class="ep-us-panel" id="epUsLista" aria-label="Usuarios"></section>

	<div class="ep-us-scrim" id="epUsScrim"></div>
	<aside class="ep-us-drawer" id="epUsDrawer" aria-label="Editar usuario" aria-hidden="true">
		<div class="ep-us-d-head">
			<h2 id="epUsTitulo">Editar usuario</h2>
			<button type="button" class="ep-us-x" id="epUsCerrar" aria-label="Cerrar"><?= ep_icon('close', 20) ?></button>
		</div>
		<div class="ep-us-d-body">
			<div class="ep-us-foto" id="epUsFotoBloque">
				<div class="ep-us-av ep-us-av-grande" id="epUsAv"></div>
				<div>
					<button type="button" class="ep-us-btn" id="epUsFotoBtn">Cambiar foto</button>
					<small id="epUsFotoNota">JPG, PNG o WEBP</small>
					<input type="file" id="epUsFotoArchivo" accept="image/jpeg,image/png,image/webp" hidden>
				</div>
			</div>

			<div class="ep-us-fl" id="epUsWUsuario">
				<label for="epUsUsuario">Usuario de ingreso</label>
				<input id="epUsUsuario" autocomplete="off" maxlength="60">
				<small>Si el rol es Promotor, debe ser su usuario de Xplora.</small>
			</div>
			<div class="ep-us-fl">
				<label for="epUsNombre" class="ep-us-lk">Nombre completo <?= ep_icon('lock', 13) ?></label>
				<input id="epUsNombre" autocomplete="off" maxlength="150">
				<small id="epUsNombreNota">Viene de Xplora y el calendario lo usa para cruzar datos, por eso no se edita.</small>
			</div>
			<div class="ep-us-fl">
				<label for="epUsCorreo">Correo</label>
				<input id="epUsCorreo" type="email" autocomplete="off" maxlength="150">
				<small>Es el correo de contacto; el usuario de ingreso no cambia.</small>
			</div>
			<div class="ep-us-g2">
				<div class="ep-us-fl">
					<label for="epUsRolCampo">Rol</label>
					<select id="epUsRolCampo">
						<?php foreach (EP_ROLES_USUARIO as $k => $txt): ?><option value="<?= $k ?>"><?= $h($txt) ?></option><?php endforeach; ?>
					</select>
				</div>
				<div class="ep-us-fl" id="epUsWCiudad">
					<label for="epUsCiudad" class="ep-us-lk">Ciudad <?= ep_icon('lock', 13) ?></label>
					<input id="epUsCiudad" disabled>
				</div>
			</div>
			<div class="ep-us-sec" id="epUsWRuta">
				<h3>Ruta del promotor</h3>
				<div class="ep-us-fl">
					<label for="epUsCategorias">Puntos de venta que ve</label>
					<select id="epUsCategorias">
						<?php foreach (EP_CATEGORIAS_PDV as $k => $txt): ?><option value="<?= $h($k) ?>"><?= $h($txt) ?></option><?php endforeach; ?>
					</select>
					<small>Define qué puntos de venta le salen al registrar.</small>
				</div>
				<div class="ep-us-fl" id="epUsWSupCanales">
					<label for="epUsSupCanales">Supervisor de canales</label>
					<select id="epUsSupCanales"></select>
				</div>
				<div class="ep-us-fl" id="epUsWSupRetail">
					<label for="epUsSupRetail">Supervisor de retail</label>
					<select id="epUsSupRetail"></select>
					<small>Quien aprueba o devuelve sus registros de cada categoría.</small>
				</div>
			</div>
			<div class="ep-us-fl" id="epUsWCanal">
				<label for="epUsCanal" class="ep-us-lk">Canal <?= ep_icon('lock', 13) ?></label>
				<input id="epUsCanal" disabled>
				<small>Informativo: así reconoce la app a este promotor.</small>
			</div>

			<div class="ep-us-sec" id="epUsWClaveNueva">
				<h3>Clave inicial</h3>
				<div class="ep-us-fl">
					<label for="epUsC1">Clave</label>
					<div class="ep-us-pw"><input id="epUsC1" type="password" autocomplete="new-password"><button type="button" class="ep-us-eye" data-eye="epUsC1" aria-label="Mostrar clave"><?= ep_icon('eye', 18) ?></button></div>
					<small>Mínimo <?= $clave ?> caracteres.</small>
				</div>
				<div class="ep-us-fl">
					<label for="epUsC2">Confirmar clave</label>
					<div class="ep-us-pw"><input id="epUsC2" type="password" autocomplete="new-password"><button type="button" class="ep-us-eye" data-eye="epUsC2" aria-label="Mostrar clave"><?= ep_icon('eye', 18) ?></button></div>
				</div>
			</div>

			<div class="ep-us-sec" id="epUsAcceso">
				<h3>Acceso</h3>
				<div class="ep-us-acc">
					<p><b>Cambiar clave</b>Define la clave nueva de este usuario.</p>
					<button type="button" class="ep-us-btn" id="epUsClaveAbrir">Cambiar clave</button>
				</div>
				<div class="ep-us-pwform" id="epUsClaveForm">
					<div class="ep-us-fl">
						<label for="epUsP1">Nueva clave</label>
						<div class="ep-us-pw"><input id="epUsP1" type="password" autocomplete="new-password"><button type="button" class="ep-us-eye" data-eye="epUsP1" aria-label="Mostrar clave"><?= ep_icon('eye', 18) ?></button></div>
						<small>Mínimo <?= $clave ?> caracteres.</small>
					</div>
					<div class="ep-us-fl">
						<label for="epUsP2">Confirmar clave</label>
						<div class="ep-us-pw"><input id="epUsP2" type="password" autocomplete="new-password"><button type="button" class="ep-us-eye" data-eye="epUsP2" aria-label="Mostrar clave"><?= ep_icon('eye', 18) ?></button></div>
						<span class="ep-us-err" id="epUsPErr">Las claves no coinciden.</span>
						<span class="ep-us-ok" id="epUsPOk">Las claves coinciden.</span>
					</div>
					<div class="ep-us-btns">
						<button type="button" class="ep-us-btn" id="epUsClaveGenerar">Generar una</button>
						<button type="button" class="ep-us-btn" id="epUsClaveCancelar">Cancelar</button>
						<button type="button" class="ep-us-btn ep-us-btn-p" id="epUsClaveGuardar" disabled>Guardar clave</button>
					</div>
				</div>
				<div class="ep-us-acc">
					<p><b id="epUsEstadoTitulo">Desactivar usuario</b><span id="epUsEstadoTexto">No podrá iniciar sesión. Sus registros se conservan.</span></p>
					<button type="button" class="ep-us-btn" id="epUsEstadoBtn">Desactivar</button>
				</div>
			</div>
		</div>
		<div class="ep-us-d-foot">
			<button type="button" class="ep-us-btn" id="epUsCancelar">Cancelar</button>
			<button type="button" class="ep-us-btn ep-us-btn-p" id="epUsGuardar">Guardar cambios</button>
		</div>
	</aside>

	<script type="application/json" id="epUsDatos"><?= json_encode($usuarios, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</main>

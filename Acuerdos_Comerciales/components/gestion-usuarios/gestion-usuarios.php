<?php
// Se auto-incluye sus propias dependencias (require_once es idempotente) para
// poder funcionar tanto embebido en index.php como si se accediera directo.
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../db_connect.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	echo '<div class="ac-placeholder">Acceso restringido al rol Superdesarrollador.</div>';
	return;
}

$busqueda  = trim($_GET['q'] ?? '');
$pagina    = (int) ($_GET['pg'] ?? 1);
$resultado = listar_usuarios_acuerdos($mysqli, $busqueda, $pagina);
$usuarios  = $resultado['usuarios'];

$js_v = @filemtime(__DIR__.'/../../assets/js/gestion-usuarios.js') ?: time();
?>
<div class="ac-users">
	<div class="ac-users-header">
		<h1 class="ac-page-title">Gestión de Usuarios</h1>
		<p class="ac-page-subtitle">Administra el acceso y roles de los miembros de la plataforma.</p>
	</div>

	<div class="ac-users-grid">
		<!-- Panel Nuevo Usuario -->
		<section class="ac-card">
			<div class="ac-card-header">
				<span class="material-symbols-outlined">person_add</span>
				<h3>Nuevo Usuario</h3>
			</div>
			<form id="form-nuevo-usuario" class="ac-form" autocomplete="off">
				<div class="ac-field">
					<label class="ac-field-label" for="nu-usuario">Nombre de Usuario</label>
					<div class="ac-input-wrap">
						<span class="material-symbols-outlined">person</span>
						<input class="ac-input" id="nu-usuario" name="usuario" type="text" placeholder="Ej. juan.perez" maxlength="100" required>
					</div>
				</div>

				<div class="ac-field">
					<label class="ac-field-label" for="nu-password">Contraseña</label>
					<div class="ac-input-wrap">
						<span class="material-symbols-outlined">lock</span>
						<input class="ac-input" id="nu-password" name="contrasena" type="password" placeholder="••••••••" minlength="4" maxlength="100" required style="padding-right:40px;">
						<button class="ac-input-toggle" type="button" id="nu-pw-toggle">
							<span class="material-symbols-outlined" id="nu-pw-icon">visibility</span>
						</button>
					</div>
					<p class="ac-field-hint">La clave debe tener al menos 4 caracteres.</p>
				</div>

				<div class="ac-field">
					<label class="ac-field-label" for="nu-rol">Rol del Usuario</label>
					<select class="ac-select" id="nu-rol" name="rol" required>
						<option value="admin">Administrador</option>
						<option value="desarrollador">Desarrollador</option>
						<option value="superdesarrollador">Superdesarrollador</option>
					</select>
				</div>

				<p class="ac-form-msg" id="nu-msg"></p>

				<button class="ac-btn-primary" type="submit" id="nu-submit">
					<span class="material-symbols-outlined">add</span>
					Crear Usuario
				</button>
			</form>
		</section>

		<!-- Panel Usuarios Registrados -->
		<section class="ac-card ac-users-table-card">
			<div class="ac-card-header ac-card-header-split">
				<h3>Usuarios Registrados</h3>
				<div class="ac-input-wrap ac-search-wrap">
					<span class="material-symbols-outlined">search</span>
					<input class="ac-input ac-search-input" id="us-buscar" type="text" placeholder="Buscar por usuario..." value="<?= htmlspecialchars($busqueda) ?>">
				</div>
			</div>

			<div class="ac-table-scroll">
				<table class="ac-table" id="tabla-usuarios">
					<thead>
						<tr>
							<th>Usuario</th>
							<th>Rol</th>
							<th>Fecha de Creación</th>
							<th>Estado</th>
							<th class="ac-text-right">Acciones</th>
						</tr>
					</thead>
					<tbody id="tabla-usuarios-body">
						<?php if ($usuarios): ?>
							<?php foreach ($usuarios as $u): ?>
								<?= renderFilaUsuario($u, $_SESSION['user_id']) ?>
							<?php endforeach; ?>
						<?php else: ?>
							<tr><td colspan="5" class="ac-table-empty">No se encontraron usuarios.</td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<div class="ac-pagination" id="paginacion-usuarios" data-pagina="<?= $resultado['pagina'] ?>" data-total-paginas="<?= $resultado['total_paginas'] ?>">
				<span class="ac-pagination-info" id="paginacion-info">Página <?= $resultado['pagina'] ?> de <?= $resultado['total_paginas'] ?></span>
				<div class="ac-pagination-btns" id="paginacion-btns"></div>
			</div>
		</section>
	</div>
</div>

<!-- Modal Modificar Clave: única responsabilidad, cambiar la contraseña -->
<div class="ac-modal-overlay" id="modal-clave">
	<div class="ac-modal">
		<div class="ac-modal-header">
			<h3>Modificar Clave</h3>
			<button type="button" class="ac-modal-close" id="modal-clave-cerrar">
				<span class="material-symbols-outlined">close</span>
			</button>
		</div>
		<form id="form-clave" class="ac-form">
			<input type="hidden" id="cl-id" name="id">
			<p class="ac-modal-usuario" id="cl-usuario-label"></p>

			<div class="ac-field">
				<label class="ac-field-label" for="cl-password">Nueva Contraseña</label>
				<div class="ac-input-wrap">
					<span class="material-symbols-outlined">lock</span>
					<input class="ac-input" id="cl-password" name="contrasena" type="password" placeholder="Escribe la nueva clave" minlength="4" maxlength="100" required style="padding-right:40px;">
					<button class="ac-input-toggle" type="button" id="cl-pw-toggle">
						<span class="material-symbols-outlined" id="cl-pw-icon">visibility</span>
					</button>
				</div>
			</div>

			<p class="ac-form-msg" id="cl-msg"></p>

			<button class="ac-btn-primary" type="submit" id="cl-submit">Cambiar Clave</button>
		</form>
	</div>
</div>

<!-- Modal Editar Perfil: única responsabilidad, cambiar el rol -->
<div class="ac-modal-overlay" id="modal-rol">
	<div class="ac-modal">
		<div class="ac-modal-header">
			<h3>Editar Perfil</h3>
			<button type="button" class="ac-modal-close" id="modal-rol-cerrar">
				<span class="material-symbols-outlined">close</span>
			</button>
		</div>
		<form id="form-rol" class="ac-form">
			<input type="hidden" id="rl-id" name="id">
			<p class="ac-modal-usuario" id="rl-usuario-label"></p>

			<div class="ac-field">
				<label class="ac-field-label" for="rl-rol">Rol del Usuario</label>
				<select class="ac-select" id="rl-rol" name="rol" required>
					<option value="admin">Administrador</option>
					<option value="desarrollador">Desarrollador</option>
					<option value="superdesarrollador">Superdesarrollador</option>
				</select>
			</div>

			<p class="ac-form-msg" id="rl-msg"></p>

			<button class="ac-btn-primary" type="submit" id="rl-submit">Guardar Cambios</button>
		</form>
	</div>
</div>

<script src="assets/js/gestion-usuarios.js?v=<?= $js_v ?>"></script>

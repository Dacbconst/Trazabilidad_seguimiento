<?php
// Reportes mensuales (solo admin): lista de reportes guardados + asistente para armar uno nuevo.
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../includes/reportes_datos.php';

if (ep_rol_actual() !== 'admin') {
	echo '<main class="ep-content"><p>No tienes permiso para ver esta sección.</p></main>';
	return;
}
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$reportes = ep_reportes_listar();
$nombresTipo = ['activaciones' => 'Activaciones', 'capacitaciones' => 'Capacitaciones', 'colocacion-pop' => 'Colocación de POP', 'epson-day' => 'Epson Day', 'exhibiciones' => 'Exhibiciones', 'evento-ferias' => 'Evento o Ferias'];
$meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
?>
<main class="ep-content ep-rp" id="epRp">

	<header class="ep-rp-head">
		<div>
			<h1>Reportes mensuales</h1>
			<p>Arma el reporte de un mes eligiendo los registros que entran; se guarda la selección y la presentación se genera al descargar.</p>
		</div>
		<button type="button" class="ep-h2-btn-primario" id="epRpNuevo"><?= ep_icon('presentation', 14) ?> <span>Nuevo reporte</span></button>
	</header>

	<section class="ep-rp-lista">
		<?php if (empty($reportes)): ?>
			<?= ep_estado_vacio('presentation', 'Todavía no hay reportes', 'Crea el primero con "Nuevo reporte".') ?>
		<?php else: ?>
			<div class="ep-rp-fila ep-rp-fila-cab"><div>Reporte</div><div>Mes</div><div>Registros</div><div>Creado</div><div></div></div>
			<?php foreach ($reportes as $r):
				$anio = substr($r['mes'], 0, 4);
				$mesTxt = ($meses[(int) substr($r['mes'], 5, 2)] ?? '').' '.$anio;
				?>
				<div class="ep-rp-fila" data-id="<?= (int) $r['id'] ?>">
					<div><strong><?= $h($nombresTipo[$r['tipo']] ?? $r['tipo']) ?></strong><small><?= $h($r['titulo'] ?? '') ?></small></div>
					<div><?= $h($mesTxt) ?></div>
					<div><?= (int) $r['total_registros'] ?><?= $r['programadas'] !== null ? ' de '.(int) $r['programadas'].' programadas' : '' ?></div>
					<div><?= $h(date('d/m/Y H:i', strtotime($r['created_at']))) ?><small><?= $h($r['creador'] ?? '') ?></small></div>
					<div class="ep-rp-acciones">
						<button type="button" class="ep-h2-btn-ppt ep-rp-descargar" data-id="<?= (int) $r['id'] ?>"><?= ep_icon('presentation', 13) ?> <span>Descargar PPT</span></button>
						<button type="button" class="ep-rp-quitar" data-id="<?= (int) $r['id'] ?>" aria-label="Quitar reporte"><?= ep_icon('close', 13) ?></button>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</section>

	<!-- Asistente: mismo diseño que la ventana de descarga PPT (icono, título, cuerpo, pie con acciones) -->
	<div class="ep-modal-ppt hidden" id="epRpModal" role="dialog" aria-modal="true" aria-labelledby="epRpTitulo">
		<div class="ep-modal-ppt-backdrop" id="epRpFondo"></div>
		<div class="ep-modal-ppt-dialog ep-rp-dialog">

			<div class="ep-modal-ppt-head">
				<div style="display:flex;align-items:center;gap:10px;">
					<div class="ep-modal-ppt-icon"><?= ep_icon('presentation', 20) ?></div>
					<div>
						<h3 id="epRpTitulo" style="margin:0;font-size:16px;font-weight:700;color:var(--color-ink);">Nuevo reporte mensual</h3>
						<p style="margin:2px 0 0;font-size:12px;color:var(--color-text-muted);">Elige los registros del mes que entran en la presentación.</p>
					</div>
				</div>
				<button type="button" class="ep-modal-close-btn" id="epRpCerrar" aria-label="Cerrar"><?= ep_icon('close', 16) ?></button>
			</div>

			<div class="ep-modal-ppt-body">
				<ol class="ep-rp-stepper" id="epRpStepper">
					<li class="ep-rp-step activo" data-paso="1"><span>1</span> Tipo y mes</li>
					<li class="ep-rp-step" data-paso="2"><span>2</span> Calendario</li>
					<li class="ep-rp-step" data-paso="3"><span>3</span> Registros</li>
				</ol>

				<div class="ep-rp-paso" data-paso="1">
					<div class="ep-ppt-form-group">
						<label class="ep-label-compact" for="epRpTipo"><?= ep_icon('layers', 13) ?> <span>Tipo de actividad</span></label>
						<select id="epRpTipo" class="ep-input">
							<option value="activaciones">Activaciones</option>
							<?php foreach ($nombresTipo as $id => $nom): if ($id === 'activaciones') continue; ?>
								<option value="<?= $h($id) ?>" disabled><?= $h($nom) ?> (próximamente)</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="ep-ppt-form-group">
						<label class="ep-label-compact" for="epRpMes"><?= ep_icon('calendar', 13) ?> <span>Mes del reporte</span></label>
						<input type="month" id="epRpMes" class="ep-input" value="<?= date('Y-m') ?>">
					</div>
					<div class="ep-ppt-form-group">
						<label class="ep-label-compact" for="epRpTituloTxt"><?= ep_icon('file', 13) ?> <span>Título (opcional)</span></label>
						<input type="text" id="epRpTituloTxt" class="ep-input" maxlength="150" placeholder="Ej. Activaciones Retail">
					</div>
				</div>

				<div class="ep-rp-paso hidden" data-paso="2">
					<div class="ep-ppt-form-group">
						<label class="ep-label-compact" for="epRpCalendario"><?= ep_icon('camera', 13) ?> <span>Foto del calendario de activaciones</span></label>
						<label class="ep-foto-dropzone ep-rp-drop" id="epRpDrop" for="epRpCalendario">
							<input type="file" id="epRpCalendario" accept="image/jpeg,image/png" hidden>
							<img id="epRpCalPrev" class="ep-foto-preview ep-rp-prev hidden" alt="Vista previa del calendario">
							<span class="ep-foto-dropzone-vacio" id="epRpDropVacio">
								<span class="ep-foto-slot-icon"><?= ep_icon('camera', 26) ?></span>
								<span class="ep-foto-slot-action">Subir foto del calendario</span>
								<span class="ep-rp-drop-sub">Haz clic o arrastra la imagen aquí (JPG o PNG)</span>
							</span>
						</label>
						<button type="button" class="ep-rp-quitar-foto hidden" id="epRpQuitarFoto">Quitar imagen</button>
					</div>
					<div class="ep-ppt-form-group">
						<label class="ep-label-compact" for="epRpProgramadas"><?= ep_icon('bar-chart', 13) ?> <span>Actividades programadas (opcional)</span></label>
						<input type="number" id="epRpProgramadas" class="ep-input" min="0" inputmode="numeric" placeholder="Ej. 9" style="max-width:160px;">
					</div>
					<p class="ep-rp-nota">Si no subes el calendario, la diapositiva sale sin imagen. Si no escribes las programadas, se toman iguales a las ejecutadas.</p>
				</div>

				<div class="ep-rp-paso hidden" data-paso="3">
					<div class="ep-rp-sel-barra">
						<label class="ep-rp-check"><input type="checkbox" id="epRpTodos"> Seleccionar todos</label>
						<span id="epRpContador">0 seleccionados</span>
					</div>
					<div class="ep-rp-registros" id="epRpRegistros"></div>
				</div>
			</div>

			<div class="ep-modal-ppt-foot">
				<div class="ep-ppt-foot-meta-box"><span class="ep-ppt-foot-pill" id="epRpResumenPie">Activaciones</span></div>
				<div style="display:flex;align-items:center;gap:8px;">
					<button type="button" class="ep-btn-subtle-compact" id="epRpAtras">Atrás</button>
					<button type="button" class="ep-btn-ppt-cta" id="epRpSiguiente">
						<span id="epRpSigTxt">Siguiente</span>
					</button>
				</div>
			</div>

		</div>
	</div>
</main>

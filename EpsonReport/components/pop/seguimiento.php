<?php
// Pestaña "Seguimiento": período (año y mes) y, debajo, el saldo por material y los promotores agrupados por estado, en vivo; usa las variables de pop.php.
require_once __DIR__.'/../../includes/pop_seguimiento.php';
$mesesNombre = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
?>
<?php if (!$mesSup): ?>
	<?= ep_estado_vacio('tag', 'Todavía no te asignaron POP', 'Cuando Fabricio reparta el material de un mes, aquí verás lo que reporta tu equipo.') ?>
<?php else: ?>
	<?php
	$seg = ep_pop_seguimiento($mesSup, $yo);
	$anioSel = (int) substr($mesSup['mes'], 0, 4);
	$anios = [];
	foreach ($mesesSup as $m) {
		$anios[(int) substr($m['mes'], 0, 4)][] = $m;
	}
	krsort($anios);
	?>
	<div class="ep-sv-top">
		<div class="ep-sv-periodo">
			<?php if (count($anios) > 1): ?>
				<div class="ep-sv-anios" role="group" aria-label="Año">
					<?php foreach (array_keys($anios) as $a): ?><button type="button" class="ep-sv-anio<?= $a === $anioSel ? ' on' : '' ?>" data-anio="<?= $a ?>" aria-pressed="<?= $a === $anioSel ? 'true' : 'false' ?>"><?= $a ?></button><?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="ep-sv-meses" role="group" aria-label="Mes">
				<?php foreach ($anios as $a => $lista): ?>
					<?php foreach ($lista as $m): ?>
						<?php $n = (int) substr($m['mes'], 5, 2); ?>
						<button type="button" class="ep-sv-mes<?= (int) $m['id'] === (int) $mesSup['id'] ? ' on' : '' ?>" data-pop="<?= (int) $m['id'] ?>" data-anio="<?= $a ?>" aria-pressed="<?= (int) $m['id'] === (int) $mesSup['id'] ? 'true' : 'false' ?>"<?= $a === $anioSel ? '' : ' hidden' ?> title="<?= $h($mesesNombre[$n].' '.$a) ?>"><?= $h($mesesNombre[$n]) ?><?= $m['estado'] === 'activo' ? '<i class="ep-sv-abierto" title="Mes abierto"></i>' : '' ?></button>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</div>
		</div>
		<span class="ep-vivo" id="epPopVivo" title="Se actualiza sola cada pocos segundos"><i></i><span>En vivo</span></span>
	</div>
	<div class="ep-sv" id="epPopSeg" data-pop="<?= (int) $mesSup['id'] ?>"><?php require __DIR__.'/seguimiento_vista.php'; ?></div>
<?php endif; ?>

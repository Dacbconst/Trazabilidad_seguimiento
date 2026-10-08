<?php
// Tab de Colocación de POP: Fabricio carga el mes y lo reparte a los supervisores; cada supervisor lo reparte a su equipo.
require_once __DIR__.'/../../includes/pop_datos.php';
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$meses = ep_pop_listar();
$hayAbierto = (bool) array_filter($meses, fn($m) => $m['estado'] === 'activo');
$esDueno = ep_pop_es_dueno();
$esSup = ep_es_supervisor();
$yo = (int) ($_SESSION['usuario_id'] ?? 0);
$supervisores = $esDueno ? ep_pop_supervisores() : [];
$partes = [];
foreach ($meses as $m) {
	$partes[(int) $m['id']] = $esSup ? ep_pop_parte_supervisor((int) $m['id'], $yo) : [];
}
?>
<link rel="stylesheet" href="assets/css/calendario-pop.css?v=<?= filemtime(__DIR__.'/../../assets/css/calendario-pop.css') ?>">

<div class="ep-pop-head">
	<p class="ep-pop-head-txt"><?= $esDueno ? 'Carga el material que llegó a bodega y repártelo entre los supervisores. Cada uno lo reparte a su equipo y los promotores solo reportan lo que les asignaron.' : 'Aquí ves el POP que te tocó. Repártelo entre tus promotores: no puedes pasar de lo que recibiste.' ?></p>
	<?php if ($esDueno): ?>
		<button type="button" class="ep-rp-nuevo" id="epPopNuevo"<?= $hayAbierto ? ' disabled title="Ya hay un mes abierto; ciérralo antes de abrir otro."' : '' ?>><?= ep_icon('plus', 16) ?> <span>Cargar mes</span></button>
	<?php endif; ?>
</div>

<?php if (empty($meses)): ?>
	<?= $esDueno ? ep_estado_vacio('tag', 'Todavía no hay material POP cargado', 'Carga el primer mes con "Cargar mes".') : ep_estado_vacio('tag', 'Todavía no te repartieron POP', 'Cuando Fabricio reparta el material de este mes, lo verás aquí.') ?>
<?php else: ?>
	<?php foreach ($meses as $m): ?>
		<?php
		$abierto = $m['estado'] === 'activo';
		$bodega = array_sum(array_column($m['filas'], 'bodega'));
		$colocado = array_sum(array_column($m['filas'], 'colocado'));
		$sinReporte = !$abierto && !$m['reporte_mensual_id'];
		$parte = $partes[(int) $m['id']];
		?>
		<section class="ep-pop-mes" data-pop-id="<?= (int) $m['id'] ?>">
			<header class="ep-pop-mes-head">
				<div class="ep-pop-mes-id">
					<strong><?= $h(ep_pop_mes_texto($m['mes'])) ?></strong>
					<?php if ($abierto): ?>
						<span class="ep-pop-badge ep-pop-badge-abierto">Abierto</span>
					<?php elseif ($sinReporte): ?>
						<span class="ep-pop-badge ep-pop-badge-aviso">Cerrado sin reporte</span>
					<?php else: ?>
						<span class="ep-pop-badge ep-pop-badge-cerrado">Cerrado</span>
					<?php endif; ?>
					<span class="ep-pop-mes-meta"><?= count($m['filas']) ?> materiales · <?= $h($m['creador'] ?? '') ?></span>
				</div>
				<?php if ($esDueno): ?>
					<div class="ep-pop-mes-acciones">
						<span class="ep-pop-avance"><strong><?= $colocado ?></strong> de <?= $bodega ?> colocados</span>
						<?php if ($abierto): ?>
							<button type="button" class="ep-pop-btn ep-pop-editar">Editar</button>
							<button type="button" class="ep-pop-btn ep-pop-cerrar">Cerrar mes y generar</button>
						<?php elseif ($m['reporte_mensual_id']): ?>
							<a class="ep-pop-btn ep-pop-btn-pri" href="getters/reporte_descargar.php?id=<?= (int) $m['reporte_mensual_id'] ?>">Descargar PPT</a>
						<?php endif; ?>
						<button type="button" class="ep-pop-btn ep-pop-eliminar" aria-label="Eliminar mes"><?= ep_icon('trash', 14) ?></button>
					</div>
				<?php endif; ?>
			</header>
			<?php if ($esDueno): ?>
				<div class="ep-pop-tabla" role="table" style="--ep-pop-n: <?= count($supervisores) ?>">
					<div class="ep-pop-tr ep-pop-th" role="row">
						<span role="columnheader">Material</span>
						<span role="columnheader" class="ep-pop-celda-extra">Campaña</span>
						<span role="columnheader">Bodega</span>
						<?php foreach ($supervisores as $sup): ?><span role="columnheader" class="ep-pop-celda-extra"><?= $h($sup['nombre']) ?></span><?php endforeach; ?>
						<span role="columnheader">Sin repartir</span>
						<span role="columnheader" class="ep-pop-celda-extra">Colocado</span>
						<span role="columnheader">Disponible</span>
					</div>
					<?php foreach ($m['filas'] as $f): ?>
						<div class="ep-pop-tr" role="row">
							<span role="cell"><?= $h($f['material']) ?></span>
							<span role="cell" class="ep-pop-campana ep-pop-celda-extra"><?= $h($f['campana']) ?></span>
							<span role="cell"><?= (int) $f['bodega'] ?></span>
							<?php foreach ($supervisores as $sup): ?><span role="cell" class="ep-pop-celda-extra"><?= (int) ($f['asignado'][$sup['id']] ?? 0) ?></span><?php endforeach; ?>
							<span role="cell" class="<?= $f['sin_repartir'] < 0 ? 'ep-pop-negativo' : '' ?>"><?= (int) $f['sin_repartir'] ?></span>
							<span role="cell" class="ep-pop-celda-extra"><?= (int) $f['colocado'] ?></span>
							<span role="cell"><?= (int) $f['disponible'] ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ($parte): ?>
				<div class="ep-pop-parte">
					<div class="ep-pop-parte-head">
						<strong>Tu parte</strong>
						<?php if ($abierto): ?><button type="button" class="ep-pop-btn ep-pop-btn-pri ep-pop-repartir"><?= ep_icon('users', 14) ?> Repartir a mi equipo</button><?php endif; ?>
					</div>
					<div class="ep-pop-tabla" role="table" style="--ep-pop-n: 0">
						<div class="ep-pop-tr ep-pop-th" role="row">
							<span role="columnheader">Material</span>
							<span role="columnheader" class="ep-pop-celda-extra">Campaña</span>
							<span role="columnheader">Recibido</span>
							<span role="columnheader">Repartido</span>
							<span role="columnheader">Por repartir</span>
						</div>
						<?php foreach ($m['filas'] as $f): ?>
							<?php $p = $parte[(int) $f['id']] ?? null; ?>
							<?php if ($p): ?>
								<div class="ep-pop-tr" role="row">
									<span role="cell"><?= $h($f['material']) ?></span>
									<span role="cell" class="ep-pop-campana ep-pop-celda-extra"><?= $h($f['campana']) ?></span>
									<span role="cell"><?= (int) $p['recibido'] ?></span>
									<span role="cell"><?= (int) $p['repartido'] ?></span>
									<span role="cell" class="<?= $p['repartido'] < $p['recibido'] ? 'ep-pop-pendiente' : '' ?>"><?= (int) ($p['recibido'] - $p['repartido']) ?></span>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>
<?php endif; ?>

<?php if ($esDueno): ?>
<!-- Modal para cargar o corregir el mes: material, bodega y reparto a cada supervisor -->
<div class="ep-modal-ppt hidden" id="epPopModal" role="dialog" aria-modal="true" aria-labelledby="epPopModalTitulo">
	<div class="ep-modal-ppt-backdrop" id="epPopModalFondo"></div>
	<div class="ep-modal-ppt-dialog ep-rp-dialog ep-pop-dialog">
		<div class="ep-modal-ppt-head">
			<div class="ep-modal-ppt-head-left">
				<div class="ep-modal-ppt-icon"><?= ep_icon('tag', 20) ?></div>
				<div>
					<h2 id="epPopModalTitulo">Cargar mes de POP</h2>
					<p>Material que llegó a bodega y cuánto va a cada supervisor.</p>
				</div>
			</div>
			<button type="button" class="ep-modal-close-btn" id="epPopModalCerrar" aria-label="Cerrar"><?= ep_icon('close', 16) ?></button>
		</div>
		<div class="ep-modal-ppt-body">
			<label class="ep-pop-campo">
				<span>Mes del reporte</span>
				<input type="month" class="ep-input" id="epPopMes" value="<?= date('Y-m') ?>">
			</label>
			<div class="ep-pop-herramientas">
				<button type="button" class="ep-btn-agregar-fila" id="epPopAgregar"><?= ep_icon('plus', 14) ?> Agregar material</button>
				<div class="ep-pop-sup-menu">
					<button type="button" class="ep-btn-agregar-fila" id="epPopSupBtn" aria-haspopup="true" aria-expanded="false"><?= ep_icon('plus', 14) ?> Agregar supervisor</button>
					<div class="ep-pop-sup-lista hidden" id="epPopSupLista" role="menu"></div>
				</div>
			</div>
			<div class="ep-pop-scroll">
				<div class="ep-pop-filas-head" id="epPopFilasHead"></div>
				<div class="ep-pop-filas" id="epPopFilas"></div>
			</div>
			<label class="ep-pop-campo">
				<span>Comentarios del reporte (opcional)</span>
				<textarea rows="3" class="ep-input" id="epPopComentarios" placeholder="Un comentario por línea..."></textarea>
			</label>
		</div>
		<div class="ep-modal-ppt-foot ep-rp-foot">
			<div class="ep-rp-foot-actions" style="margin-left:auto;">
				<button type="button" class="ep-btn-subtle-compact" id="epPopCancelar">Cancelar</button>
				<button type="button" class="ep-btn-ppt-cta" id="epPopGuardar">Cargar mes</button>
			</div>
		</div>
	</div>
</div>
<script type="application/json" id="epPopSupervisores"><?= json_encode($supervisores, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script type="application/json" id="epPopDatos">
	<?= json_encode(array_values(array_map(fn($m) => ['id' => (int) $m['id'], 'mes' => $m['mes'], 'comentarios' => (string) ($m['comentarios'] ?? ''), 'filas' => array_map(fn($f) => ['id' => (int) $f['id'], 'material' => $f['material'], 'campana' => $f['campana'], 'bodega' => (int) $f['bodega'], 'reparto' => (object) $f['asignado']], $m['filas'])], array_filter($meses, fn($m) => $m['estado'] === 'activo'))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>
</script>
<?php endif; ?>

<?php
// Reparto del supervisor a su equipo: solo del mes abierto en el que tiene material por repartir.
$miReparto = null;
foreach ($meses as $m) {
	if ($esSup && $m['estado'] === 'activo' && $partes[(int) $m['id']]) {
		$reportado = [];
		foreach ($equipo = ep_pop_equipo($yo) as $e) {
			$reportado[$e['id']] = ep_pop_reportado_usuario($e['id'], $m);
		}
		$miReparto = ['id' => (int) $m['id'], 'equipo' => array_map(fn($e) => ['id' => $e['id'], 'nombre' => $e['nombre'], 'reportado' => (object) $reportado[$e['id']]], $equipo), 'materiales' => []];
		foreach ($m['filas'] as $f) {
			$p = $partes[(int) $m['id']][(int) $f['id']] ?? null;
			if ($p) {
				$miReparto['materiales'][] = ['fila_id' => (int) $f['id'], 'material' => $f['material'], 'campana' => $f['campana'], 'recibido' => $p['recibido'], 'reparto' => (object) $p['reparto']];
			}
		}
	}
}
?>
<?php if ($miReparto): ?>
<!-- Modal del supervisor: reparte lo que recibió entre sus promotores -->
<div class="ep-modal-ppt hidden" id="epPopRepModal" role="dialog" aria-modal="true" aria-labelledby="epPopRepTitulo">
	<div class="ep-modal-ppt-backdrop" id="epPopRepFondo"></div>
	<div class="ep-modal-ppt-dialog ep-rp-dialog ep-pop-dialog">
		<div class="ep-modal-ppt-head">
			<div class="ep-modal-ppt-head-left">
				<div class="ep-modal-ppt-icon"><?= ep_icon('users', 20) ?></div>
				<div>
					<h2 id="epPopRepTitulo">Repartir a mi equipo</h2>
					<p>Cada promotor solo podrá reportar lo que le asignes. No puedes pasar de lo que recibiste.</p>
				</div>
			</div>
			<button type="button" class="ep-modal-close-btn" id="epPopRepCerrar" aria-label="Cerrar"><?= ep_icon('close', 16) ?></button>
		</div>
		<div class="ep-modal-ppt-body">
			<?php if (empty($miReparto['equipo'])): ?>
				<p class="ep-pop-head-txt">No tienes promotores a tu cargo. Pídele al administrador que te los asigne en Usuarios.</p>
			<?php endif; ?>
			<div class="ep-pop-scroll"><div class="ep-pop-rep" id="epPopRepTabla"></div></div>
		</div>
		<div class="ep-modal-ppt-foot ep-rp-foot">
			<div class="ep-rp-foot-actions" style="margin-left:auto;">
				<button type="button" class="ep-btn-subtle-compact" id="epPopRepCancelar">Cancelar</button>
				<button type="button" class="ep-btn-ppt-cta" id="epPopRepGuardar"<?= empty($miReparto['equipo']) ? ' disabled' : '' ?>>Guardar reparto</button>
			</div>
		</div>
	</div>
</div>
<script type="application/json" id="epPopMiReparto"><?= json_encode($miReparto, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php endif; ?>
<script src="assets/js/calendario-pop.js?v=<?= filemtime(__DIR__.'/../../assets/js/calendario-pop.js') ?>"></script>

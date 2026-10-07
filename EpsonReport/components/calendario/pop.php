<?php
// Tab de Colocación de POP: el gestor carga lo que llegó a bodega y ve cómo los promotores lo dan de baja durante el mes.
require_once __DIR__.'/../../includes/pop_datos.php';
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$meses = ep_pop_listar();
$hayAbierto = (bool) array_filter($meses, fn($m) => $m['estado'] === 'activo');
?>
<link rel="stylesheet" href="assets/css/calendario-pop.css?v=<?= filemtime(__DIR__.'/../../assets/css/calendario-pop.css') ?>">

<div class="ep-pop-head">
	<p class="ep-pop-head-txt">Carga el material que llegó a bodega. Los promotores eligen de esa lista y el saldo baja solo.</p>
	<button type="button" class="ep-rp-nuevo" id="epPopNuevo"<?= $hayAbierto ? ' disabled title="Ya hay un mes abierto; ciérralo antes de abrir otro."' : '' ?>><?= ep_icon('plus', 16) ?> <span>Cargar mes</span></button>
</div>

<?php if (empty($meses)): ?>
	<?= ep_estado_vacio('tag', 'Todavía no hay material POP cargado', 'Carga el primer mes con "Cargar mes".') ?>
<?php else: ?>
	<?php foreach ($meses as $m): ?>
		<?php
		$abierto = $m['estado'] === 'activo';
		$bodega = array_sum(array_column($m['filas'], 'bodega'));
		$colocado = array_sum(array_column($m['filas'], 'canales')) + array_sum(array_column($m['filas'], 'retail'));
		$sinReporte = !$abierto && !$m['reporte_mensual_id'];
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
			</header>
			<div class="ep-pop-tabla" role="table" style="--ep-pop-n: <?= count($m['canales']) ?>">
				<div class="ep-pop-tr ep-pop-th" role="row">
					<span role="columnheader">Material</span>
					<span role="columnheader" class="ep-pop-celda-extra">Campaña</span>
					<span role="columnheader">Bodega</span>
					<?php foreach ($m['canales'] as $c): ?><span role="columnheader" class="ep-pop-celda-extra"><?= $h(ucfirst(mb_strtolower($c, 'UTF-8'))) ?></span><?php endforeach; ?>
					<span role="columnheader">Disponible</span>
				</div>
				<?php foreach ($m['filas'] as $f): ?>
					<div class="ep-pop-tr" role="row">
						<span role="cell"><?= $h($f['material']) ?></span>
						<span role="cell" class="ep-pop-campana ep-pop-celda-extra"><?= $h($f['campana']) ?></span>
						<span role="cell"><?= (int) $f['bodega'] ?></span>
						<?php foreach ($m['canales'] as $c): ?><span role="cell" class="ep-pop-celda-extra"><?= (int) ($f['asignado'][$c] ?? 0) ?></span><?php endforeach; ?>
						<span role="cell" class="<?= $f['disponible'] < 0 ? 'ep-pop-negativo' : '' ?>"><?= (int) $f['disponible'] ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endforeach; ?>
<?php endif; ?>

<!-- Modal para cargar o corregir la tabla del mes -->
<div class="ep-modal-ppt hidden" id="epPopModal" role="dialog" aria-modal="true" aria-labelledby="epPopModalTitulo">
	<div class="ep-modal-ppt-backdrop" id="epPopModalFondo"></div>
	<div class="ep-modal-ppt-dialog ep-rp-dialog">
		<div class="ep-modal-ppt-head">
			<div class="ep-modal-ppt-head-left">
				<div class="ep-modal-ppt-icon"><?= ep_icon('tag', 20) ?></div>
				<div>
					<h2 id="epPopModalTitulo">Cargar mes de POP</h2>
					<p>Material que llegó a bodega. Los promotores eligen de esta lista.</p>
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
				<div class="ep-pop-canal-menu">
					<button type="button" class="ep-btn-agregar-fila" id="epPopCanalBtn" aria-haspopup="true" aria-expanded="false"><?= ep_icon('plus', 14) ?> Agregar canal</button>
					<div class="ep-pop-canal-lista hidden" id="epPopCanalLista" role="menu"></div>
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

<script type="application/json" id="epPopCanales"><?= json_encode(EP_POP_CANALES, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script type="application/json" id="epPopDatos">
	<?= json_encode(array_values(array_map(fn($m) => ['id' => (int) $m['id'], 'mes' => $m['mes'], 'comentarios' => (string) ($m['comentarios'] ?? ''), 'filas' => array_map(fn($f) => ['material' => $f['material'], 'campana' => $f['campana'], 'bodega' => (int) $f['bodega'], 'canales' => (object) $f['asignado']], $m['filas'])], array_filter($meses, fn($m) => $m['estado'] === 'activo'))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>
</script>
<script src="assets/js/calendario-pop.js?v=<?= filemtime(__DIR__.'/../../assets/js/calendario-pop.js') ?>"></script>

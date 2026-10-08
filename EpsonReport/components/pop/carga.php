<?php
// Pestaña "Carga de POP" (admin y Fabricio): cargar el mes, ver su tabla (bodega, supervisores, colocado) y cerrarlo; usa las variables de pop.php.
?>
<div class="ep-pop-head">
	<p class="ep-pop-head-txt">Carga el material que llegó a bodega y repártelo entre los supervisores. Cada uno lo reparte a su equipo y los promotores solo reportan lo que les asignaron.</p>
	<?php if ($esDueno): ?>
		<button type="button" class="ep-rp-nuevo" id="epPopNuevo"<?= $hayAbierto ? ' disabled title="Ya hay un mes abierto; ciérralo antes de abrir otro."' : '' ?><?= $sinCatalogo ? ' data-sin-catalogo="1"' : '' ?>><?= ep_icon('plus', 16) ?> <span>Cargar mes</span></button>
	<?php endif; ?>
</div>

<?php if (empty($meses)): ?>
	<?= ep_estado_vacio('tag', 'Todavía no hay material POP cargado', 'Carga el primer mes con "Cargar mes".') ?>
<?php else: ?>
	<?php foreach ($meses as $m): ?>
		<?php
		$abierto = $m['estado'] === 'activo';
		$bodega = array_sum(array_column($m['filas'], 'bodega'));
		$colocado = array_sum(array_column($m['filas'], 'colocado'));
		$sinRepartir = array_sum(array_map(fn($f) => max(0, (int) $f['sin_repartir']), $m['filas']));
		$sinReporte = !$abierto && !$m['reporte_mensual_id'];
		$pct = $bodega > 0 ? min(100, (int) round($colocado / $bodega * 100)) : 0;
		$n = count($supervisores);
		?>
		<details class="ep-as-mes ep-pop-mes ep-cg-mes" data-pop-id="<?= (int) $m['id'] ?>"<?= $abierto ? ' open' : '' ?>>
			<summary class="ep-as-sum ep-cg-sum">
				<svg class="ep-as-chev" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
				<strong><?= $h(ep_pop_mes_texto($m['mes'])) ?></strong>
				<?php if ($abierto): ?>
					<span class="ep-pop-badge ep-pop-badge-abierto">Abierto</span>
				<?php elseif ($sinReporte): ?>
					<span class="ep-pop-badge ep-pop-badge-aviso">Cerrado sin reporte</span>
				<?php else: ?>
					<span class="ep-pop-badge ep-pop-badge-cerrado">Cerrado</span>
				<?php endif; ?>
				<span class="ep-as-meta"><span class="ep-as-pb" aria-hidden="true"><i style="width: <?= $pct ?>%"></i></span><span class="ep-pop-avance"><strong><?= (int) $colocado ?></strong> de <?= (int) $bodega ?> colocados</span> · <?= count($m['filas']) ?> <?= count($m['filas']) === 1 ? 'material' : 'materiales' ?></span>
				<?php if ($abierto && $sinRepartir > 0): ?><span class="ep-as-aviso"><?= ep_icon('users', 13) ?>Faltan <?= (int) $sinRepartir ?> por repartir</span><?php endif; ?>
				<?php if ($esDueno): ?>
					<span class="ep-cg-acc">
						<?php if ($abierto): ?>
							<button type="button" class="ep-pop-btn ep-pop-editar"><?= ep_icon('pencil', 14) ?> Editar</button>
							<button type="button" class="ep-pop-btn ep-pop-btn-pri ep-pop-cerrar"><?= ep_icon('check', 14) ?> Cerrar mes y generar</button>
						<?php elseif ($m['reporte_mensual_id']): ?>
							<a class="ep-pop-btn ep-pop-btn-pri" href="getters/reporte_descargar.php?id=<?= (int) $m['reporte_mensual_id'] ?>"><?= ep_icon('download', 14) ?> Descargar PPT</a>
						<?php endif; ?>
						<button type="button" class="ep-pop-btn ep-pop-eliminar" aria-label="Eliminar mes" title="Eliminar mes"><?= ep_icon('trash', 14) ?></button>
					</span>
				<?php endif; ?>
			</summary>
			<?php if ($esDueno): ?>
				<div class="ep-pop-tabla ep-cg-tabla" role="table" style="--ep-pop-n: <?= $n ?>">
					<div class="ep-pop-tr ep-pop-grp" aria-hidden="true">
						<span style="grid-column: 1 / span 3"></span>
						<?php if ($n > 0): ?><span class="ep-pop-grp-c" style="grid-column: 4 / span <?= $n ?>">Asignado a supervisores</span><?php endif; ?>
						<span style="grid-column: <?= $n + 4 ?>"></span>
						<span class="ep-pop-grp-c" style="grid-column: <?= $n + 5 ?> / span 2">Reportado por promotores</span>
					</div>
					<div class="ep-pop-tr ep-pop-th" role="row">
						<span role="columnheader"><?= ep_icon('tag', 13) ?>Material</span>
						<span role="columnheader" class="ep-pop-celda-extra"><?= ep_icon('megaphone', 13) ?>Campaña</span>
						<span role="columnheader"><?= ep_icon('shelves', 13) ?>Bodega</span>
						<?php foreach ($supervisores as $sup): ?><span role="columnheader" class="ep-pop-celda-extra"><?= $h($sup['nombre']) ?></span><?php endforeach; ?>
						<span role="columnheader"><?= ep_icon('layers', 13) ?>Disponible</span>
						<span role="columnheader" class="ep-pop-celda-extra"><?= ep_icon('check', 13) ?>Colocado</span>
						<span role="columnheader"><?= ep_icon('clock', 13) ?>Por colocar</span>
					</div>
					<?php foreach ($m['filas'] as $f): ?>
						<?php $pf = (int) $f['bodega'] > 0 ? min(100, (int) round($f['colocado'] / $f['bodega'] * 100)) : 0; ?>
						<div class="ep-pop-tr" role="row">
							<span role="cell" class="ep-cg-mat"><?= $h($f['material']) ?></span>
							<span role="cell" class="ep-pop-campana ep-pop-celda-extra"><?= $h($f['campana']) ?></span>
							<span role="cell"><?= (int) $f['bodega'] ?></span>
							<?php foreach ($supervisores as $sup): ?><span role="cell" class="ep-pop-celda-extra"><?= (int) ($f['asignado'][$sup['id']] ?? 0) ?></span><?php endforeach; ?>
							<span role="cell" class="<?= $f['sin_repartir'] < 0 ? 'ep-pop-negativo' : ($f['sin_repartir'] > 0 ? 'ep-pop-pendiente' : '') ?>"><?= (int) $f['sin_repartir'] ?></span>
							<span role="cell" class="ep-pop-celda-extra ep-cg-col"><b><?= (int) $f['colocado'] ?></b><span class="ep-cg-mini" aria-hidden="true"><i style="width: <?= $pf ?>%"></i></span></span>
							<span role="cell"><?= (int) $f['disponible'] ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</details>
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
<script type="application/json" id="epPopCatalogo"><?= json_encode(['materiales' => array_column($catalogo['materiales'], 'nombre'), 'campanas' => array_column($catalogo['campanas'], 'nombre')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script type="application/json" id="epPopSupervisores"><?= json_encode($supervisores, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script type="application/json" id="epPopDatos">
	<?= json_encode(array_values(array_map(fn($m) => ['id' => (int) $m['id'], 'mes' => $m['mes'], 'comentarios' => (string) ($m['comentarios'] ?? ''), 'filas' => array_map(fn($f) => ['id' => (int) $f['id'], 'material' => $f['material'], 'campana' => $f['campana'], 'bodega' => (int) $f['bodega'], 'reparto' => (object) $f['asignado']], $m['filas'])], array_filter($meses, fn($m) => $m['estado'] === 'activo'))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>
</script>
<?php endif; ?>

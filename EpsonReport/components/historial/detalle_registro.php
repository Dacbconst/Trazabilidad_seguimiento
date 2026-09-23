<?php
// Detalle de un registro (panel derecho del Historial). Estadísticas con las mismas tarjetas del formulario (ep-stat-*).
// Variables del contexto: $r (registro), $esAdmin, $h (escape HTML).
$tipo = $r['tipo'] ?? 'activaciones';
$pct = function ($v): string { return (int) round((float) $v) . '%'; };
$emb = $r['embudo'] ?? $r['feria'] ?? null;
$cob = $r['cobertura'] ?? null;
$cum = $r['cumplimiento'] ?? null;
$modelos = $r['modelos'] ?? [];
usort($modelos, fn($a, $b) => ($b['cantidad'] ?? 0) <=> ($a['cantidad'] ?? 0));
$comentarios = array_values(array_filter((array) ($r['comentarios'] ?? [])));
$fotos = $r['fotos'] ?? [];
$conFoto = count(array_filter($fotos, fn($f) => !empty($f['url'])));
?>
<div class="ep-h2-det-head">
	<div class="ep-h2-det-titulo">
		<strong><?= $h($r['actividad_label'] ?? 'Actividad') ?></strong>
		<span><?= $esAdmin ? $h($r['promotor'] ?? '') . ' · ' : '' ?><?= $h($r['fecha_texto'] ?? '') ?>, <?= $h($r['hora'] ?? '') ?></span>
	</div>
	<div class="ep-h2-det-acciones">
		<?php if ($esAdmin): ?>
			<button type="button" class="ep-btn-record-ppt ep-h2-btn-ppt" data-id="<?= $h($r['id'] ?? '') ?>" data-tipo="<?= $h($tipo) ?>" data-promotor="<?= $h($r['promotor'] ?? '') ?>" data-fecha="<?= $h($r['fecha_iso'] ?? '') ?>">
				<?= ep_icon('presentation', 13) ?> <span>Slide PPT</span>
			</button>
		<?php endif; ?>
		<button type="button" class="ep-h2-cerrar" aria-label="Cerrar detalle"><?= ep_icon('close', 14) ?></button>
	</div>
</div>

<div class="ep-h2-det-cuerpo">
	<div class="ep-stats-panel ep-h2-stats">

	<?php if ($tipo === 'activaciones' || $tipo === 'epson-day' || $tipo === 'evento-ferias'): ?>
		<div class="ep-stats-row-3">
			<?php if ($cob): ?>
				<div class="ep-stat-card">
					<div class="ep-stat-card-header"><?= ep_icon('store', 13) ?> Cobertura</div>
					<div class="ep-stat-card-body">
						<div class="ep-stat-card-pct"><?= $pct($cob['pct'] ?? 0) ?></div>
						<div class="ep-stat-card-detalle">
							<div class="ep-stat-card-fila"><span>Nacional</span><strong><?= (int) ($cob['nacional'] ?? 0) ?></strong></div>
							<div class="ep-stat-card-fila"><span>Coberturadas</span><strong><?= (int) ($cob['coberturadas'] ?? 0) ?></strong></div>
						</div>
					</div>
				</div>
			<?php endif; ?>
			<?php if ($emb): ?>
				<div class="ep-stat-card">
					<div class="ep-stat-card-header"><?= ep_icon('users', 13) ?> Interacciones</div>
					<div class="ep-stat-card-body">
						<div class="ep-stat-card-pct"><?= $pct($emb['tasa_interaccion_pct'] ?? 0) ?></div>
						<div class="ep-stat-card-detalle">
							<div class="ep-stat-card-fila"><span>Visitaron</span><strong><?= (int) ($emb['visitaron'] ?? 0) ?></strong></div>
							<div class="ep-stat-card-fila"><span>Interactuaron</span><strong><?= (int) ($emb['interactuaron'] ?? 0) ?></strong></div>
						</div>
					</div>
				</div>
				<div class="ep-stat-card">
					<div class="ep-stat-card-header"><?= ep_icon('check', 13) ?> Ventas</div>
					<div class="ep-stat-card-body">
						<div class="ep-stat-card-pct"><?= $pct($emb['tasa_conversion_pct'] ?? 0) ?></div>
						<div class="ep-stat-card-detalle">
							<div class="ep-stat-card-fila"><span>Realizadas</span><strong><?= (int) ($emb['compraron'] ?? 0) ?></strong></div>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<?php if ($emb): $maxEmb = max(1, (int) ($emb['visitaron'] ?? 0)); ?>
			<div class="ep-stat-card-plano">
				<div class="ep-stat-card-titulo"><?= ep_icon('arrow-right', 15) ?> Embudo de Clientes</div>
				<div class="ep-stat-bars-vert">
					<?php foreach ([['Visitaron', 'visitaron'], ['Interactuaron', 'interactuaron'], ['Compraron', 'compraron']] as [$lbl, $k]): $v = (int) ($emb[$k] ?? 0); ?>
						<div class="ep-stat-bar-vert">
							<span class="ep-stat-bar-vert-valor"><?= $v ?></span>
							<div class="ep-stat-bar-vert-fill" style="height:<?= max(3, round($v / $maxEmb * 70)) ?>px;"></div>
							<span class="ep-stat-bar-vert-label"><?= $lbl ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if (!empty($modelos)): $totalUds = array_sum(array_map(fn($m) => (int) ($m['cantidad'] ?? 0), $modelos)); $maxCant = max(1, (int) ($modelos[0]['cantidad'] ?? 1)); $menor = $modelos[count($modelos) - 1]; ?>
			<div class="ep-stats-row-2">
				<div class="ep-stats-col">
					<div class="ep-stat-card-mini">
						<span class="ep-stat-card-mini-pct"><?= $totalUds > 0 ? $pct(($modelos[0]['cantidad'] ?? 0) / $totalUds * 100) : '0%' ?></span>
						<span class="ep-stat-card-mini-nombre"><?= $h($modelos[0]['modelo'] ?? '') ?></span>
						<span class="ep-stat-card-mini-caption">SKU con mayor venta</span>
					</div>
					<div class="ep-stat-card-mini">
						<span class="ep-stat-card-mini-pct"><?= $totalUds > 0 ? $pct(($menor['cantidad'] ?? 0) / $totalUds * 100) : '0%' ?></span>
						<span class="ep-stat-card-mini-nombre"><?= $h($menor['modelo'] ?? '') ?></span>
						<span class="ep-stat-card-mini-caption">SKU con menor venta</span>
					</div>
				</div>
				<div class="ep-stat-card-plano">
					<div class="ep-stat-card-titulo"><?= ep_icon('store', 15) ?> Detalle de Ventas</div>
					<?php foreach ($modelos as $m): ?>
						<div class="ep-venta-fila">
							<span class="ep-venta-nombre"><?= $h($m['modelo'] ?? '') ?></span>
							<div class="ep-venta-barra-track"><div class="ep-venta-barra-fill" style="width:<?= round(((int) ($m['cantidad'] ?? 0)) / $maxCant * 100) ?>%;"></div></div>
							<span class="ep-venta-valor"><?= (int) ($m['cantidad'] ?? 0) ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>


	<?php elseif ($tipo === 'capacitaciones' && !empty($r['capacitacion'])): $c = $r['capacitacion']; ?>
		<div class="ep-stats-row-3">
			<div class="ep-stat-card">
				<div class="ep-stat-card-header"><?= ep_icon('users', 13) ?> Asistentes</div>
				<div class="ep-stat-card-body"><div class="ep-stat-card-pct"><?= (int) ($c['asistentes'] ?? 0) ?></div></div>
			</div>
			<div class="ep-stat-card">
				<div class="ep-stat-card-header"><?= ep_icon('check', 13) ?> Aprobación</div>
				<div class="ep-stat-card-body">
					<div class="ep-stat-card-pct"><?= $pct($c['pct_aprobacion'] ?? 0) ?></div>
					<div class="ep-stat-card-detalle"><div class="ep-stat-card-fila"><span>Aprobados</span><strong><?= (int) ($c['aprobados'] ?? 0) ?></strong></div></div>
				</div>
			</div>
			<div class="ep-stat-card">
				<div class="ep-stat-card-header"><?= ep_icon('bar-chart', 13) ?> Duración</div>
				<div class="ep-stat-card-body"><div class="ep-stat-card-pct"><?= (int) ($c['horas'] ?? 0) ?> h</div></div>
			</div>
		</div>
		<?php if (!empty($c['temas'])): ?>
			<div class="ep-stat-card-plano"><div class="ep-stat-card-titulo"><?= ep_icon('file', 15) ?> Temas</div><div class="ep-stat-comentarios"><div><?= $h($c['temas']) ?></div></div></div>
		<?php endif; ?>

	<?php elseif ($tipo === 'exhibiciones' && !empty($r['exhibiciones'])): $x = $r['exhibiciones']; ?>
		<div class="ep-stats-row-3">
			<?php foreach ([['Muebles', 'muebles'], ['Rumas', 'rumas'], ['Cabeceras', 'cabeceras']] as [$lbl, $k]): ?>
				<div class="ep-stat-card">
					<div class="ep-stat-card-header"><?= ep_icon('store', 13) ?> <?= $lbl ?></div>
					<div class="ep-stat-card-body">
						<div class="ep-stat-card-pct"><?= (int) ($x[$k] ?? 0) ?></div>
						<div class="ep-stat-card-detalle"><div class="ep-stat-card-fila"><span>Del total</span><strong><?= $h($x[$k . '_pct'] ?? '0%') ?></strong></div></div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

	<?php elseif ($tipo === 'colocacion-pop' && !empty($r['pop_materiales'])): ?>
		<div class="ep-stat-card-plano">
			<div class="ep-stat-card-titulo"><?= ep_icon('file', 15) ?> Material POP</div>
			<div class="ep-h2-tabla-wrap">
				<table class="ep-h2-tabla">
					<thead><tr><th>Material</th><th>Bodega</th><th>Canales</th><th>Retail</th><th>Disp.</th></tr></thead>
					<tbody>
					<?php foreach ($r['pop_materiales'] as $p): ?>
						<tr><td><?= $h($p['material'] ?? '') ?></td><td><?= (int) ($p['bodega'] ?? 0) ?></td><td><?= (int) ($p['canales'] ?? 0) ?></td><td><?= (int) ($p['retail'] ?? 0) ?></td><td><?= (int) ($p['disponible'] ?? 0) ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endif; ?>

	<?php if ($cum || !empty($comentarios)): ?>
		<div class="ep-stats-row-2<?= ($cum && !empty($comentarios)) ? '' : ' ep-h2-fila-unica' ?>">
		<?php if ($cum): ?>
			<div class="ep-stat-card">
				<div class="ep-stat-card-header"><?= ep_icon('bar-chart', 13) ?> Cumplimiento</div>
				<div class="ep-stat-card-body">
					<div class="ep-stat-card-pct"><?= $pct($cum['pct'] ?? 0) ?></div>
					<div class="ep-stat-card-detalle">
						<div class="ep-stat-card-fila"><span>Activaciones Programadas</span><strong><?= (int) ($cum['programadas'] ?? 0) ?></strong></div>
						<div class="ep-stat-card-fila"><span>Activaciones Ejecutadas</span><strong><?= (int) ($cum['realizadas'] ?? 0) ?></strong></div>
					</div>
				</div>
			</div>
		<?php endif; ?>
		<?php if (!empty($comentarios)): ?>
			<div class="ep-stat-card-plano">
				<div class="ep-stat-card-titulo"><?= ep_icon('file', 15) ?> Comentarios</div>
				<div class="ep-stat-comentarios">
					<?php foreach ($comentarios as $c): ?><div><?= $h($c) ?></div><?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
		</div>
	<?php endif; ?>

	</div>

	<div class="ep-h2-fotos-sec">
		<div class="ep-h2-fotos-titulo"><?= ep_icon('camera', 13) ?> Evidencia fotográfica <span>(<?= $conFoto ?>/<?= count($fotos) ?>)</span></div>
		<?php if (empty($fotos)): ?>
			<div class="ep-stat-comentarios-vacio">Este registro no tiene fotos.</div>
		<?php else: ?>
			<div class="ep-h2-carrusel">
			<button type="button" class="ep-h2-car-btn ep-h2-car-prev" aria-label="Anterior" disabled>&#8249;</button>
			<button type="button" class="ep-h2-car-btn ep-h2-car-next" aria-label="Siguiente">&#8250;</button>
			<div class="ep-h2-fotos-grid ep-h2-carril">
				<?php foreach ($fotos as $f): ?>
					<button type="button" class="ep-h2-foto" data-url="<?= $h($f['url'] ?? '') ?>" data-label="<?= $h($f['label'] ?? 'Foto') ?>">
						<?php if (!empty($f['url'])): ?>
							<img src="<?= $h($f['url']) ?>" alt="<?= $h($f['label'] ?? 'Foto') ?>" loading="lazy">
						<?php else: ?>
							<span class="ep-h2-foto-vacia"><?= ep_icon('camera', 18) ?></span>
						<?php endif; ?>
						<span class="ep-h2-foto-lbl"><?= $h($f['label'] ?? 'Foto') ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			</div>
		<?php endif; ?>
	</div>
</div>

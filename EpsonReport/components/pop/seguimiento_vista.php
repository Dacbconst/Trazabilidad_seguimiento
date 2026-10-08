<?php
// Vista del seguimiento (saldo por material y promotores agrupados por estado); la pintan la pestaña y el refresco en vivo. Recibe $seg de ep_pop_seguimiento().
$h ??= fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$iniciales = fn(string $n) => mb_strtoupper(implode('', array_map(fn($p) => mb_substr($p, 0, 1), array_slice(preg_split('/\s+/', trim($n)), 0, 2))), 'UTF-8');
$recibidoTotal = array_sum(array_column($seg['materiales'], 'recibido'));
$reportadoTotal = array_sum(array_column($seg['materiales'], 'reportado'));
$pctTotal = $recibidoTotal > 0 ? min(100, (int) round($reportadoTotal / $recibidoTotal * 100)) : 0;
$porMaterial = array_column($seg['materiales'], 'material', 'id');
$icoAviso = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>';
$grupos = [
	'sin' => ['Sin reportar', '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>'],
	'rep' => ['Reportando', '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#1E7B4D" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-9"/></svg>'],
];
?>
<?php if (!$seg['promotores']): ?>
	<?= ep_estado_vacio('users', 'Todavía no eliges a tu equipo en este mes', 'Ve a Colocación asignada, agrega promotores y marca qué material reporta cada uno.') ?>
<?php else: ?>
<div class="ep-sv-saldo" role="group" aria-label="Filtrar por material">
	<button type="button" class="ep-sv-mat on" data-fila="0" aria-pressed="true">
		<span class="ep-sv-nm"><strong>Todos</strong><em>los materiales</em></span>
		<span class="ep-sv-num"><b><?= (int) $reportadoTotal ?></b><span>de <?= (int) $recibidoTotal ?> reportadas</span></span>
		<span class="ep-sv-barra"><i style="width: <?= $pctTotal ?>%"></i></span>
		<span class="ep-sv-est"><?= $reportadoTotal > 0 ? 'En curso · '.$pctTotal.' %' : 'Sin avance' ?></span>
	</button>
	<?php foreach ($seg['materiales'] as $m): ?>
		<?php $pct = $m['recibido'] > 0 ? min(100, (int) round($m['reportado'] / $m['recibido'] * 100)) : 0; $ag = $m['disponible'] <= 0; ?>
		<button type="button" class="ep-sv-mat" data-fila="<?= (int) $m['id'] ?>" aria-pressed="false">
			<span class="ep-sv-nm"><strong><?= $h($m['material']) ?></strong><em><?= $h($m['campana']) ?></em></span>
			<span class="ep-sv-num"><b><?= (int) $m['reportado'] ?></b><span>de <?= (int) $m['recibido'] ?> reportadas</span></span>
			<span class="ep-sv-barra<?= $ag ? ' ag' : '' ?>"><i style="width: <?= $pct ?>%"></i></span>
			<span class="ep-sv-est<?= $ag ? ' ag' : '' ?>"><?= $ag ? 'Agotado' : ($m['reportado'] > 0 ? 'Quedan '.(int) $m['disponible'] : 'Sin avance') ?></span>
		</button>
	<?php endforeach; ?>
</div>

<?php foreach ($grupos as $clave => [$titulo, $icono]): ?>
	<?php $lista = array_values(array_filter($seg['promotores'], fn($p) => $p['estado'] === $clave)); ?>
	<?php if (!$lista) { continue; } ?>
	<details class="ep-sv-grupo ep-sv-<?= $clave ?>" data-grupo="<?= $clave ?>" open>
		<summary class="ep-sv-gh">
			<svg class="ep-sv-chevg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
			<?= $icono ?><?= $h($titulo) ?><span class="ep-sv-cnt"><?= count($lista) ?></span>
		</summary>
		<?php foreach ($lista as $p): ?>
			<?php
			$conReporte = array_keys(array_filter($p['celdas'], fn($c) => $c['reportado'] > 0));
			if ($clave === 'rep') {
				$viejo = $p['ultimo_min'] !== null && $p['ultimo_min'] >= 4320;
				$cuando = $viejo ? 'Sin reportar '.ep_pop_hace($p['ultimo_min']) : 'Último reporte '.ep_pop_hace((int) $p['ultimo_min']);
			} else {
				$viejo = $p['desde_min'] >= 4320;
				$cuando = $p['desde_min'] < 1440 ? 'En el equipo desde hoy' : 'En el equipo '.ep_pop_hace($p['desde_min']);
			}
			?>
			<div class="ep-sv-fila" data-id="<?= (int) $p['id'] ?>" data-filas="<?= $h(implode(' ', $conReporte)) ?>">
				<button type="button" class="ep-sv-sm" aria-expanded="false"<?= $p['detalle'] ? '' : ' disabled' ?>>
					<span class="ep-sv-who"><span class="ep-sg-av"><?php if ($p['foto']): ?><img src="<?= $h($p['foto']) ?>" alt="" width="24" height="24" loading="lazy" decoding="async" data-ini="<?= $h($iniciales($p['nombre'])) ?>"><?php else: ?><?= $h($iniciales($p['nombre'])) ?><?php endif; ?></span><b><?= $h($p['nombre']) ?></b></span>
					<span class="ep-sv-chips">
						<?php foreach ($seg['materiales'] as $m): ?>
							<?php $c = $p['celdas'][$m['id']]; if (!$c['asignado']) { continue; } ?>
							<span class="ep-sv-chip<?= $c['reportado'] > 0 ? '' : ' z' ?>" data-fila="<?= (int) $m['id'] ?>"><?= $h($m['material']) ?> <b><?= (int) $c['reportado'] ?></b></span>
						<?php endforeach; ?>
					</span>
					<span class="ep-sv-cuando<?= $viejo ? ' at' : '' ?>"><?= $viejo ? $icoAviso : '' ?><?= $h($cuando) ?></span>
					<svg class="ep-sv-chev" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
				</button>
				<?php if ($p['detalle']): ?>
					<div class="ep-sg-dt">
						<div class="ep-sg-dr ep-sg-dh"><span>Punto de venta</span><span>Canal</span><span>Material</span><span>Cantidad</span><span>Actualizado</span></div>
						<?php foreach ($p['detalle'] as $e): ?>
							<div class="ep-sg-dr" data-fila="<?= (int) $e['fila_id'] ?>">
								<span><?= $h($e['punto']) ?></span>
								<span><?= mb_strtoupper($e['canal'], 'UTF-8') === 'RETAIL' ? '<span class="ep-sg-chip ep-sg-chip-rt">Retail</span>' : '<span class="ep-sg-chip ep-sg-chip-mu">Canales</span>' ?></span>
								<span><?= $h($porMaterial[$e['fila_id']] ?? $e['material']) ?></span>
								<span><?= (int) $e['cantidad'] ?></span>
								<span class="ep-sg-mu"><?= $h(date('d/m/Y H:i', strtotime($e['actualizado']))) ?></span>
							</div>
						<?php endforeach; ?>
						<div class="ep-sg-dr ep-sg-tt" data-todos="1"><span>Total de <?= $h($p['nombre']) ?></span><span><?= (int) $p['total'] ?></span></div>
						<?php foreach ($conReporte as $filaId): ?>
							<div class="ep-sg-dr ep-sg-tt ep-sg-tf" data-fila="<?= (int) $filaId ?>" hidden><span>Total <?= $h($porMaterial[$filaId] ?? '') ?> de <?= $h($p['nombre']) ?></span><span><?= (int) $p['celdas'][$filaId]['reportado'] ?></span></div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</details>
<?php endforeach; ?>
<div class="ep-sv-vacio" hidden>Nadie ha reportado este material todavía.</div>
<?php endif; ?>

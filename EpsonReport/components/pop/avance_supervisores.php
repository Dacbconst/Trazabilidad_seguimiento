<?php
// Avance por supervisor de un mes de POP (filas compactas): lo que recibió, lo que repartió a su equipo, lo que su equipo reportó y en qué eslabón va; usa $m, $h y $supervisores de carga.php.
require_once __DIR__.'/../../includes/pop_seguimiento.php';

$iniciales = fn(string $n) => mb_strtoupper(implode('', array_map(fn($p) => mb_substr($p, 0, 1), array_slice(preg_split('/\s+/', trim($n)), 0, 2))), 'UTF-8');
// Foto redonda (o las iniciales si no hay o no carga), igual que en el Seguimiento.
$avatar = fn(string $nombre, string $foto) => '<span class="ep-sg-av">'.($foto ? '<img src="'.$h($foto).'" alt="" width="24" height="24" loading="lazy" decoding="async" data-ini="'.$h($iniciales($nombre)).'">' : $h($iniciales($nombre))).'</span>';
$avance = [];
foreach ($supervisores as $sup) {
	$parte = ep_pop_parte_supervisor($m, $sup['id']);
	if (!$parte) {
		continue;
	}
	$recibio = array_sum(array_column($parte, 'recibido'));
	$repartio = array_sum(array_column($parte, 'repartido'));
	$reporto = array_sum(array_column($parte, 'validado'));
	$porValidar = array_sum(array_column($parte, 'reportado')) - $reporto;
	if ($repartio < $recibio) {
		[$estado, $texto] = ['falta', 'Falta repartir '.($recibio - $repartio)];
	} elseif ($reporto >= $recibio) {
		[$estado, $texto] = ['ok', 'Completo'];
	} elseif ($reporto === 0 && $porValidar === 0) {
		[$estado, $texto] = ['espera', 'Repartido, sin reportar'];
	} else {
		[$estado, $texto] = ['curso', $reporto > 0 ? 'Faltan '.($recibio - $reporto).' por reportar' : 'Sin validar aún'];
	}
	if ($porValidar > 0 && $estado !== 'ok') {
		$texto .= ' · '.$porValidar.' por validar';
	}
	$avance[] = ['sup' => $sup, 'recibio' => $recibio, 'repartio' => $repartio, 'reporto' => $reporto, 'estado' => $estado, 'texto' => $texto, 'parte' => $parte];
}
if (!$avance) {
	return;
}
?>
<div class="ep-av">
	<h3 class="ep-av-tit">Avance por supervisor</h3>
	<div class="ep-av-r ep-av-h" aria-hidden="true"><span></span><span>Supervisor</span><span>Avance</span><span>Recibió</span><span>Repartió</span><span>Reportó</span><span>Estado</span></div>
	<?php foreach ($avance as $a): ?>
		<?php
		$pRep = $a['recibio'] > 0 ? min(100, (int) round($a['repartio'] / $a['recibio'] * 100)) : 0;
		$pRpt = $a['recibio'] > 0 ? min(100, (int) round($a['reporto'] / $a['recibio'] * 100)) : 0;
		$seg = ep_pop_seguimiento($m, $a['sup']['id']);
		$cantidades = ep_pop_equipo_cantidades((int) $m['id'], $a['sup']['id']);
		?>
		<details class="ep-av-fila">
			<summary class="ep-av-r">
				<svg class="ep-av-chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
				<span class="ep-av-nom"><?= $avatar($a['sup']['nombre'], $a['sup']['foto']) ?><b><?= $h($a['sup']['nombre']) ?></b></span>
				<span class="ep-av-bar" title="<?= $pRpt ?>% reportado de lo que recibió"><i class="ep-av-rep" style="width: <?= $pRep ?>%"></i><i class="ep-av-rpt" style="width: <?= $pRpt ?>%"></i></span>
				<span class="ep-av-n"><?= (int) $a['recibio'] ?></span>
				<span class="ep-av-n"><?= (int) $a['repartio'] ?></span>
				<span class="ep-av-n"><?= (int) $a['reporto'] ?></span>
				<span class="ep-av-est ep-av-<?= $a['estado'] ?>"><?= $h($a['texto']) ?></span>
			</summary>
			<div class="ep-av-det">
				<?php if (!$seg['promotores']): ?>
					<p class="ep-av-vacio">Todavía no eligió a su equipo.</p>
				<?php else: ?>
					<div class="ep-av-dr ep-av-dh"><span>Promotor</span><?php foreach ($seg['materiales'] as $mat): ?><span><?= $h($mat['material']) ?></span><?php endforeach; ?><span>Total</span></div>
					<?php foreach ($seg['promotores'] as $p): ?>
						<div class="ep-av-dr">
							<span class="ep-av-nom"><?= $avatar($p['nombre'], $p['foto']) ?><?= $h($p['nombre']) ?></span>
							<?php foreach ($seg['materiales'] as $mat): ?>
								<?php $asig = (int) ($cantidades[$p['id']][$mat['id']] ?? 0); $rpt = (int) $p['celdas'][$mat['id']]['reportado']; $pv = (int) $p['celdas'][$mat['id']]['por_validar']; ?>
								<span><?= $asig > 0 || $rpt > 0 || $pv > 0 ? $rpt.' de '.$asig.($pv > 0 ? ' (+'.$pv.' por validar)' : '') : '·' ?></span>
							<?php endforeach; ?>
							<span><b><?= (int) $p['total'] ?></b></span>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</details>
	<?php endforeach; ?>
</div>

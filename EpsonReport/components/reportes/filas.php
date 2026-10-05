<?php
// Tarjetas de reportes mensuales agrupadas por mes; usa $reportes, $h, $nombresTipo, $meses del contexto que lo incluye.
usort($reportes, fn($a, $b) => strcmp($b['mes'].$b['created_at'], $a['mes'].$a['created_at']));
$mesActual = null;
foreach ($reportes as $r):
	$mesTxt = ucfirst($meses[(int) substr($r['mes'], 5, 2)] ?? '').' '.substr($r['mes'], 0, 4);
	$actividad = $nombresTipo[$r['tipo']] ?? $r['tipo'];
	$nombre = trim((string) ($r['titulo'] ?? '')) ?: $actividad;
	$rango = preg_match('/"desde":"\d{4}-(\d{2})-(\d{2})","hasta":"\d{4}-(\d{2})-(\d{2})"/', (string) ($r['snapshot_ini'] ?? ''), $m) ? $m[2].'/'.$m[1].' al '.$m[4].'/'.$m[3] : '';
	$busqueda = mb_strtolower($nombre.' '.$actividad.' '.($r['creador'] ?? ''), 'UTF-8');
	if ($r['mes'] !== $mesActual):
		$mesActual = $r['mes']; ?>
		<div class="ep-rp-grupo" data-mes="<?= $h($r['mes']) ?>" data-mes-label="<?= $h($mesTxt) ?>"><h3><?= $h($mesTxt) ?></h3><em></em></div>
	<?php endif; ?>
	<article class="ep-rp-card" data-id="<?= (int) $r['id'] ?>" data-actividad="<?= $h($actividad) ?>" data-mes="<?= $h($r['mes']) ?>" data-busqueda="<?= $h($busqueda) ?>">
		<span class="ep-fl-ico ep-rp-ico"><?= ep_icon(ep_icono_tipo($r['tipo']), 22) ?></span>
		<div class="ep-rp-c-main">
			<strong><?= $h($nombre) ?></strong>
			<span><?= $h($actividad) ?> · <?= (int) $r['total_registros'] ?> <?= (int) $r['total_registros'] === 1 ? 'registro' : 'registros' ?><?= $rango !== '' ? ' · '.$h($rango) : '' ?></span>
		</div>
		<div class="ep-rp-c-creador"><small>Creado por</small><b><?= $h($r['creador'] ?? '') ?></b><small><?= $h(date('d/m/Y H:i', strtotime($r['created_at']))) ?></small></div>
		<div class="ep-rp-acciones">
			<button type="button" class="ep-rp-descargar" data-id="<?= (int) $r['id'] ?>"><?= ep_icon('download', 15) ?> <span>Descargar</span></button>
			<button type="button" class="ep-rp-quitar" data-id="<?= (int) $r['id'] ?>" aria-label="Quitar reporte"><?= ep_icon('trash', 15) ?></button>
		</div>
	</article>
<?php endforeach; ?>
<div class="ep-rp-sin hidden" id="epRpSin"><?= ep_estado_vacio('search', 'Sin resultados', 'Ningún reporte coincide con la búsqueda. Prueba con otra actividad o mes.') ?></div>

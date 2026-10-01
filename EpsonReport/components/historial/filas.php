<?php
// Registros agrupados por día, con detalle en <template>; usa $todosRegistros y $esAdmin del contexto que la incluye.
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$mesesCortos = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$diasSemana = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

$diaDe = fn($r) => substr((string) ($r['fecha_actividad'] ?? ($r['fecha_iso'] ?? '')), 0, 10);
// Los días van del más reciente al más antiguo y, dentro de cada día, por orden de llegada (el último en enviarse primero), no por la hora de la actividad.
$claveOrden = fn($r) => $diaDe($r).str_pad((string) ($r['db_id'] ?? 0), 10, '0', STR_PAD_LEFT);
uasort($todosRegistros, fn($a, $b) => strcmp($claveOrden($b), $claveOrden($a)));

// "Hoy", "Ayer" o el nombre del día; y la fecha corta al lado.
$tituloDia = function (string $dia) use ($diasSemana, $mesesCortos): array {
	$ts = strtotime($dia);
	$corta = $diasSemana[(int) date('w', $ts)].' '.(int) date('j', $ts).' '.$mesesCortos[(int) date('n', $ts)];
	if ($dia === date('Y-m-d')) {
		return ['Hoy', $corta];
	}
	if ($dia === date('Y-m-d', strtotime('-1 day'))) {
		return ['Ayer', $corta];
	}
	return [ucfirst($diasSemana[(int) date('w', $ts)]), (int) date('j', $ts).' '.$mesesCortos[(int) date('n', $ts)].' '.date('Y', $ts)];
};

$diaActual = null;
foreach ($todosRegistros as $i => $r):
	$tipo = $r['tipo'] ?? 'activaciones';
	$dia = $diaDe($r);
	$fotos = $r['fotos'] ?? [];
	$conFoto = count(array_filter($fotos, fn($f) => !empty($f['url'])));
	$hora = !empty($r['hora_inicio']) ? $r['hora_inicio'] : ($r['hora'] ?? '');
	$etiqueta = $r['actividad_label'] ?? 'Actividad';
	$punto = trim((string) ($r['punto_venta'] ?? '')) ?: $etiqueta;
	$busqueda = mb_strtolower(implode(' ', [$r['id'] ?? '', $r['promotor'] ?? '', $r['punto_venta'] ?? '', $etiqueta, $r['tipo_actividad'] ?? '', $r['ciudad'] ?? '']), 'UTF-8');
	if ($dia !== $diaActual):
		$diaActual = $dia;
		[$titulo, $fechaCorta] = $tituloDia($dia);
		?>
		<div class="ep-h2-dia" data-dia="<?= $h($dia) ?>"><h3><?= $h($titulo) ?></h3><span><?= $h($fechaCorta) ?></span><em></em></div>
	<?php endif; ?>
	<div class="ep-h2-fila ep-h2-reg" tabindex="0" role="button"
		data-idx="<?= $i ?>" data-estado="<?= $h($r['estado'] ?? 'Aprobado') ?>" data-codigo="<?= $h($r['id'] ?? '') ?>" data-tipo="<?= $h($tipo) ?>" data-actividad="<?= $h($etiqueta) ?>" data-fecha="<?= $h($dia) ?>"
		data-promotor="<?= $h($r['promotor'] ?? '') ?>" data-busqueda="<?= $h($busqueda) ?>">
		<?php if ($esAdmin): ?>
			<input type="checkbox" class="ep-h2-check" data-codigo="<?= $h($r['id'] ?? '') ?>" data-tipo="<?= $h($tipo) ?>" aria-label="Seleccionar para descarga consolidada">
		<?php endif; ?>
		<span class="ep-fl-ico"><?= ep_icon(ep_icono_tipo($tipo), 20) ?></span>
		<div class="ep-h2-c-main">
			<strong><?= $h($punto) ?></strong>
			<span><?= $esAdmin ? $h($r['promotor'] ?? '').' · ' : '' ?><?= $h($hora) ?></span>
		</div>
		<div class="ep-h2-c-meta">
			<b><?= $h($etiqueta) ?></b>
			<?php $estReg = $r['estado'] ?? 'Aprobado'; if ($estReg !== 'Aprobado' || !$esAdmin): $claseEst = ['Pendiente' => 'pend', 'Devuelto' => 'dev', 'Aprobado' => 'ok'][$estReg] ?? 'ok'; ?>
				<span class="ep-h2-est ep-h2-est-<?= $claseEst ?>"><?= $estReg === 'Pendiente' ? 'Pendiente de aprobación' : $h($estReg) ?></span>
			<?php endif; ?>
			<small><?= $conFoto ?> / <?= count($fotos) ?> fotos</small>
		</div>
	</div>
	<template id="epH2T-<?= $i ?>"><?php include __DIR__.'/detalle_registro.php'; ?></template>
<?php endforeach; ?>

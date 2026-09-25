<?php
// Registros del historial agrupados por día de la actividad (tarjetas) y las plantillas de su detalle; se usa al cargar la página y al refrescar en vivo.
// Variables del contexto: $todosRegistros y $esAdmin.
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$mesesCortos = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$diasSemana = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

$diaDe = fn($r) => substr((string) ($r['fecha_actividad'] ?? ($r['fecha_iso'] ?? '')), 0, 10);
$claveOrden = fn($r) => $diaDe($r).($r['hora_inicio'] ?? ($r['hora'] ?? ''));
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
		data-idx="<?= $i ?>" data-codigo="<?= $h($r['id'] ?? '') ?>" data-tipo="<?= $h($tipo) ?>" data-actividad="<?= $h($etiqueta) ?>" data-fecha="<?= $h($dia) ?>"
		data-promotor="<?= $h($r['promotor'] ?? '') ?>" data-busqueda="<?= $h($busqueda) ?>">
		<span class="ep-fl-ico"><?= ep_icon(ep_icono_tipo($tipo), 20) ?></span>
		<div class="ep-h2-c-main">
			<strong><?= $h($punto) ?></strong>
			<span><?= $esAdmin ? $h($r['promotor'] ?? '').' · ' : '' ?><?= $h($hora) ?></span>
		</div>
		<div class="ep-h2-c-meta">
			<b><?= $h($etiqueta) ?></b>
			<small><?= $conFoto ?> / <?= count($fotos) ?> fotos</small>
		</div>
	</div>
	<template id="epH2T-<?= $i ?>"><?php include __DIR__.'/detalle_registro.php'; ?></template>
<?php endforeach; ?>

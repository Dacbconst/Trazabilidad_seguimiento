<?php
// Filas del historial y sus plantillas de detalle; se usa al cargar la página y al refrescar en vivo.
// Variables del contexto: $todosRegistros y $esAdmin.
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$mesesCortos = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
foreach ($todosRegistros as $i => $r):
	$tipo = $r['tipo'] ?? 'activaciones';
	$ts = strtotime($r['fecha_iso'] ?? 'now');
	$fotos = $r['fotos'] ?? [];
	$conFoto = count(array_filter($fotos, fn($f) => !empty($f['url'])));
	$tsAct = strtotime($r['fecha_actividad'] ?? ($r['fecha_iso'] ?? 'now'));
	$horario = !empty($r['hora_inicio']) ? $r['hora_inicio'].' – '.$r['hora_fin'] : ($r['hora'] ?? '');
	$registrado = (int) date('j', $ts).' '.$mesesCortos[(int) date('n', $ts)].', '.($r['hora'] ?? '');
	?>
	<div class="ep-h2-fila ep-h2-reg" tabindex="0" role="button"
		data-idx="<?= $i ?>" data-codigo="<?= $h($r['id'] ?? '') ?>" data-tipo="<?= $h($tipo) ?>" data-fecha="<?= $h($r['fecha_iso'] ?? '') ?>"
		data-promotor="<?= $h($r['promotor'] ?? '') ?>" data-busqueda="">
		<div class="ep-h2-c-fecha"><strong><?= (int) date('j', $tsAct) ?> <?= $mesesCortos[(int) date('n', $tsAct)] ?></strong><small><?= $h($horario) ?></small></div>
		<div class="ep-h2-c-act"><span class="ep-h2-tipo-ico"><?= ep_icon(ep_icono_tipo($tipo), 15) ?></span><div class="ep-h2-c-act-texto"><strong><?= $h($r['actividad_label'] ?? 'Actividad') ?></strong><small>Registrado <?= $h($registrado) ?></small></div></div>
		<div class="ep-h2-c-prom ep-h2-col-prom"><?= $h($r['promotor'] ?? '') ?></div>
		<div class="ep-h2-c-fotos"><?= $conFoto ?> / <?= count($fotos) ?></div>
	</div>
	<template id="epH2T-<?= $i ?>"><?php include __DIR__.'/detalle_registro.php'; ?></template>
<?php endforeach; ?>

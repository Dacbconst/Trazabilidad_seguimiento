<?php
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../includes/fotos_datos.php';
require_once __DIR__.'/../../includes/registros_datos.php';

$esAdmin = (ep_rol_actual() === 'admin');
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

// Registros reales desde la base; el promotor solo ve los suyos.
$todosRegistros = ep_registros_datos();
$usuarioSesion = $_SESSION['usuario'] ?? '';
if (!$esAdmin) {
	$todosRegistros = array_values(array_filter($todosRegistros, fn($r) => strcasecmp($r['promotor_usuario'] ?? '', $usuarioSesion) === 0));
}

$tiposFiltro = [
	'activaciones'   => 'Activaciones',
	'capacitaciones' => 'Capacitaciones',
	'colocacion-pop' => 'Colocación de POP',
	'epson-day'      => 'Epson Day',
	'exhibiciones'   => 'Exhibiciones',
	'evento-ferias'  => 'Evento o Ferias',
];
$conteo = array_fill_keys(array_keys($tiposFiltro), 0);
foreach ($todosRegistros as $r) {
	if (isset($conteo[$r['tipo'] ?? ''])) {
		$conteo[$r['tipo']]++;
	}
}
$totalRegistros = count($todosRegistros);
$listaPromotores = array_values(array_unique(array_filter(array_column($todosRegistros, 'promotor'))));
sort($listaPromotores);
$mesesCortos = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
?>
<main class="ep-content ep-h2<?= $esAdmin ? '' : ' ep-h2-sin-promotor' ?>" id="epH2" data-admin="<?= $esAdmin ? '1' : '0' ?>">

	<header class="ep-h2-head">
		<div>
			<h1>Historial de registros <span class="ep-rol-chip <?= $esAdmin ? 'ep-rol-chip-admin' : 'ep-rol-chip-user' ?>"><?= $esAdmin ? 'Admin' : 'Promotor' ?></span></h1>
			<p id="epH2Resumen"><?= $totalRegistros ?> <?= $totalRegistros === 1 ? 'registro' : 'registros' ?></p>
		</div>
		<div class="ep-h2-head-acciones">
			<div class="ep-h2-fechas">
				<input type="date" id="epH2Desde" title="Desde">
				<span>–</span>
				<input type="date" id="epH2Hasta" title="Hasta">
			</div>
			<?php if ($esAdmin): ?>
				<button type="button" class="ep-h2-btn-primario" id="epBtnAbrirExportadorPPT" title="Descargar reporte en PowerPoint">
					<?= ep_icon('presentation', 14) ?> <span>Descargar PPT</span>
				</button>
			<?php endif; ?>
		</div>
	</header>

	<div class="ep-h2-filtros">
		<div class="ep-h2-pills" id="epH2Pills">
			<button type="button" class="ep-h2-pill selected" data-tipo="all">Todas <span><?= $totalRegistros ?></span></button>
			<?php foreach ($tiposFiltro as $id => $label): ?>
				<button type="button" class="ep-h2-pill" data-tipo="<?= $h($id) ?>"><?= $h($label) ?> <span><?= (int) $conteo[$id] ?></span></button>
			<?php endforeach; ?>
		</div>
		<div class="ep-h2-buscador">
			<?php if ($esAdmin): ?>
				<select id="epH2Promotor" title="Filtrar por promotor">
					<option value="all">Todos los usuarios</option>
					<?php foreach ($listaPromotores as $p): ?>
						<option value="<?= $h($p) ?>"><?= $h($p) ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
		</div>
	</div>

	<div class="ep-h2-cuerpo">
		<section class="ep-h2-lista" aria-label="Registros">
			<div class="ep-h2-fila ep-h2-fila-cab">
				<div>Fecha</div><div>Actividad</div><div class="ep-h2-col-prom">Promotor</div><div>Fotos</div>
			</div>
			<div id="epH2Filas">
				<?php foreach ($todosRegistros as $i => $r):
					$tipo = $r['tipo'] ?? 'activaciones';
					$ts = strtotime($r['fecha_iso'] ?? 'now');
					$fotos = $r['fotos'] ?? [];
					$conFoto = count(array_filter($fotos, fn($f) => !empty($f['url'])));
					$busq = '';
					?>
					<div class="ep-h2-fila ep-h2-reg" tabindex="0" role="button"
						data-idx="<?= $i ?>" data-tipo="<?= $h($tipo) ?>" data-fecha="<?= $h($r['fecha_iso'] ?? '') ?>"
						data-promotor="<?= $h($r['promotor'] ?? '') ?>" data-busqueda="<?= $h($busq) ?>">
						<div class="ep-h2-c-fecha"><strong><?= (int) date('j', $ts) ?> <?= $mesesCortos[(int) date('n', $ts)] ?></strong><small><?= $h($r['hora'] ?? '') ?></small></div>
						<div class="ep-h2-c-act"><strong><?= $h($r['actividad_label'] ?? 'Actividad') ?></strong><small><?= $h($r['id'] ?? '') ?></small></div>
						<div class="ep-h2-c-prom ep-h2-col-prom"><?= $h($r['promotor'] ?? '') ?></div>
						<div class="ep-h2-c-fotos"><?= $conFoto ?> / <?= count($fotos) ?></div>
					</div>
					<template id="epH2T-<?= $i ?>"><?php include __DIR__.'/detalle_registro.php'; ?></template>
				<?php endforeach; ?>
			</div>
			<div class="ep-h2-vacio<?= $totalRegistros ? ' hidden' : '' ?>" id="epH2Vacio">
				<?= $totalRegistros ? 'Ningún registro coincide con los filtros.' : 'Todavía no hay registros. Cuando envíes uno desde Actividades aparecerá aquí.' ?>
			</div>
			<button type="button" class="ep-h2-mas hidden" id="epH2Mas">Mostrar más</button>
		</section>

		<aside class="ep-h2-panel" id="epH2Panel" aria-label="Detalle del registro">
			<div class="ep-h2-panel-vacio" id="epH2PanelVacio">Selecciona un registro para ver su detalle.</div>
			<div id="epH2PanelContenido"></div>
		</aside>
	</div>

	<div id="epH2Lightbox" class="ep-h2-lightbox hidden" role="dialog" aria-modal="true">
		<div class="ep-h2-lightbox-fondo" id="epH2LbFondo"></div>
		<div class="ep-h2-lightbox-caja">
			<button type="button" class="ep-h2-lb-cerrar" id="epH2LbCerrar" aria-label="Cerrar"><?= ep_icon('close', 16) ?></button>
			<button type="button" class="ep-h2-lb-nav ep-h2-lb-prev" id="epH2LbPrev" aria-label="Anterior">&#8249;</button>
			<img id="epH2LbImg" alt="">
			<button type="button" class="ep-h2-lb-nav ep-h2-lb-next" id="epH2LbNext" aria-label="Siguiente">&#8250;</button>
			<div class="ep-h2-lb-pie" id="epH2LbPie"></div>
		</div>
	</div>

	<?php if ($esAdmin): ?>
		<?php include __DIR__ . '/modal_exportar_ppt.php'; ?>
	<?php endif; ?>

</main>

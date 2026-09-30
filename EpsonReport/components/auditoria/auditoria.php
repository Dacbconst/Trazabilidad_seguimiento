<?php
// Auditoría (solo admin): bitácora de lo que hicieron los admins, agrupada por día, con antes → después.
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../includes/auditoria_datos.php';

if (ep_rol_actual() !== 'admin') {
	echo '<main class="ep-content"><p>No tienes permiso para ver esta sección.</p></main>';
	return;
}
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$eventos = ep_auditoria_listar();
$acciones = ep_auditoria_acciones();
$iconosEntidad = ['calendario' => 'calendar', 'registro' => 'file', 'reporte' => 'presentation', 'actividad' => 'grid'];
$dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$hoy = date('Y-m-d');
$ayer = date('Y-m-d', strtotime('-1 day'));
$etiquetaDia = function (string $fecha) use ($hoy, $ayer, $dias, $meses): string {
	if ($fecha === $hoy) {
		return 'Hoy';
	}
	if ($fecha === $ayer) {
		return 'Ayer';
	}
	$t = strtotime($fecha);
	return ucfirst($dias[(int) date('w', $t)]).' '.(int) date('j', $t).' de '.$meses[(int) date('n', $t)].(date('Y', $t) !== date('Y') ? ' de '.date('Y', $t) : '');
};
$grupos = [];
foreach ($eventos as $e) {
	$grupos[substr($e['created_at'], 0, 10)][] = $e;
}
?>
<main class="ep-content ep-au" id="epAu">

	<header class="ep-au-head">
		<div>
			<h1>Auditoría</h1>
			<p id="epAuResumen"><?= count($eventos) ?> <?= count($eventos) === 1 ? 'movimiento' : 'movimientos' ?> · quién hizo qué y cuándo</p>
		</div>
		<?php if ($eventos): ?>
		<div class="ep-h2-rapidos" id="epAuRapidos" role="group" aria-label="Periodo">
			<button type="button" class="selected" data-rapido="todo">Todo</button>
			<button type="button" data-rapido="hoy">Hoy</button>
			<button type="button" data-rapido="semana">Semana</button>
			<button type="button" data-rapido="mes">Mes</button>
		</div>
		<?php endif; ?>
	</header>

	<?php if (empty($eventos)): ?>
		<?= ep_estado_vacio('shield', 'Todavía no hay movimientos', 'Cada vez que un admin cree, cambie, reactive o elimine algo, quedará anotado aquí con su nombre y la hora.') ?>
	<?php else: ?>
	<section class="ep-fl-barra">
		<label class="ep-fl-buscar">
			<?= ep_icon('search', 18) ?>
			<input type="search" id="epAuBuscar" placeholder="Buscar por calendario, código, punto de venta, promotor…" autocomplete="off">
		</label>
		<div class="ep-fl-combo" id="epAuComboUsuario">
			<button type="button" class="ep-fl-combo-btn" aria-haspopup="listbox" aria-expanded="false">Usuario <span class="ep-fl-combo-valor">Todos</span><?= ep_icon('chevron', 16) ?></button>
			<div class="ep-fl-combo-panel hidden">
				<label class="ep-fl-combo-buscar"><?= ep_icon('search', 16) ?><input type="search" placeholder="Buscar usuario…" autocomplete="off"></label>
				<div class="ep-fl-combo-lista" role="listbox"></div>
			</div>
		</div>
		<div class="ep-fl-combo" id="epAuComboAccion">
			<button type="button" class="ep-fl-combo-btn" aria-haspopup="listbox" aria-expanded="false">Acción <span class="ep-fl-combo-valor">Todas</span><?= ep_icon('chevron', 16) ?></button>
			<div class="ep-fl-combo-panel hidden">
				<label class="ep-fl-combo-buscar"><?= ep_icon('search', 16) ?><input type="search" placeholder="Buscar acción…" autocomplete="off"></label>
				<div class="ep-fl-combo-lista" role="listbox"></div>
			</div>
		</div>
		<div class="ep-fl-combo" id="epAuComboFecha">
			<button type="button" class="ep-fl-combo-btn" aria-haspopup="dialog" aria-expanded="false"><?= ep_icon('calendar', 16) ?> Rango<?= ep_icon('chevron', 16) ?></button>
			<div class="ep-fl-combo-panel ep-fl-combo-fechas hidden">
				<label>Desde<input type="date" id="epAuDesde"></label>
				<label>Hasta<input type="date" id="epAuHasta"></label>
			</div>
		</div>
	</section>

	<section class="ep-au-lista" id="epAuLista" aria-label="Movimientos">
		<?php foreach ($grupos as $fecha => $delDia): ?>
			<div class="ep-au-dia" data-fecha="<?= $h($fecha) ?>">
				<div class="ep-au-dia-head"><h3><?= $h($etiquetaDia($fecha)) ?></h3><em></em></div>
				<ol class="ep-au-eventos">
					<?php foreach ($delDia as $e):
						$accion = $acciones[$e['accion']] ?? $e['accion'];
						$esSistema = $e['usuario_id'] === null;
						$quien = $esSistema ? 'Sistema' : ($e['usuario_nombre'] ?: ($e['usuario'] ?? ''));
						$tono = $esSistema ? ' ep-au-ico-sistema' : (str_ends_with($e['accion'], '_eliminar') ? ' ep-au-ico-borra' : '');
						$textoDetalle = implode(' ', array_map(fn($d) => ($d['campo'] ?? '').' '.($d['antes'] ?? '').' '.($d['despues'] ?? '').' '.($d['valor'] ?? ''), $e['detalle']));
						$busqueda = mb_strtolower($e['resumen'].' '.$quien.' '.($e['usuario'] ?? '').' '.$accion.' '.$textoDetalle, 'UTF-8');
					?>
						<li class="ep-au-ev" data-fecha="<?= $h($fecha) ?>" data-usuario="<?= $h($quien) ?>" data-accion="<?= $h($accion) ?>" data-busqueda="<?= $h($busqueda) ?>">
							<time class="ep-au-hora" datetime="<?= $h(str_replace(' ', 'T', $e['created_at'])) ?>"><?= $h(date('H:i', strtotime($e['created_at']))) ?></time>
							<span class="ep-au-ico<?= $tono ?>"><?= ep_icon($esSistema ? 'clock' : ($iconosEntidad[$e['entidad']] ?? 'file'), 16) ?></span>
							<div class="ep-au-cuerpo">
								<p class="ep-au-resumen"><?= $h($e['resumen']) ?></p>
								<p class="ep-au-meta">
									<b><?= $h($quien) ?></b><?php if (!$esSistema && !empty($e['usuario']) && $e['usuario'] !== $quien): ?> <span>(<?= $h($e['usuario']) ?>)</span><?php endif; ?>
								</p>
								<?php if ($e['detalle']): ?>
									<dl class="ep-au-detalle">
										<?php foreach ($e['detalle'] as $d): ?>
											<dt><?= $h($d['campo'] ?? '') ?></dt>
											<?php if (array_key_exists('antes', $d)): ?>
												<dd class="ep-au-cambio"><?= $d['antes'] !== '' ? '<del>'.$h($d['antes']).'</del>' : '<i>vacío</i>' ?><?= ep_icon('arrow-right', 13) ?><?= $d['despues'] !== '' ? '<ins>'.$h($d['despues']).'</ins>' : '<i>vacío</i>' ?></dd>
											<?php else: ?>
												<dd><?= $h($d['valor'] ?? '') ?></dd>
											<?php endif; ?>
										<?php endforeach; ?>
									</dl>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
		<?php endforeach; ?>
		<div class="ep-au-sin hidden" id="epAuSin"><?= ep_estado_vacio('search', 'Sin resultados', 'Ningún movimiento coincide con los filtros. Prueba con otro usuario, acción o rango.') ?></div>
		<?php if (count($eventos) >= EP_AUDITORIA_LIMITE): ?>
			<p class="ep-au-tope">Se muestran los <?= EP_AUDITORIA_LIMITE ?> movimientos más recientes.</p>
		<?php endif; ?>
	</section>
	<?php endif; ?>
</main>

<?php
// Panel de avisos del promotor y del supervisor (campana del menú y del encabezado móvil); $avisos viene de index.php.
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$fechaCorta = fn(string $f): string => (int) date('j', strtotime($f)).' '.$meses[(int) date('n', strtotime($f))];
$plural = fn(int $n, string $uno, string $varios): string => $n.' '.($n === 1 ? $uno : $varios);
?>
<div class="ep-avisos-scrim" id="epAvisosScrim"></div>
<section class="ep-avisos" id="epAvisos" role="dialog" aria-label="Avisos de activaciones" aria-hidden="true">
	<header class="ep-avisos-head">
		<div>
			<h2>Avisos</h2>
			<p><?= $avisos['total'] ? $plural($avisos['total'], 'pendiente', 'pendientes') : 'Estás al día' ?></p>
		</div>
		<button type="button" class="ep-avisos-x" id="epAvisosCerrar" aria-label="Cerrar"><?= ep_icon('close', 18) ?></button>
	</header>
	<div class="ep-avisos-cuerpo">
		<?php foreach ($avisos['devueltos'] as $d): ?>
			<article class="ep-avisos-cal urgente">
				<div class="ep-avisos-cal-top">
					<h3><?= $h($d['punto']) ?></h3>
					<span class="ep-avisos-etq urgente">Devuelto<?= $d['nuevo'] ? ' · nuevo' : '' ?></span>
				</div>
				<p class="ep-avisos-sub"><?= $d['revisor'] ? 'Por '.$h($d['revisor']).': ' : '' ?><?= $h($d['motivo']) ?></p>
				<a class="ep-avisos-ir" href="index.php?vista=actividades&amp;corregir=<?= urlencode($d['codigo']) ?>">Corregir y reenviar</a>
			</article>
		<?php endforeach; ?>
		<?php if ($avisos['pop']): ?>
			<article class="ep-avisos-cal<?= $avisos['pop']['urgente'] ? ' urgente' : '' ?>">
				<div class="ep-avisos-cal-top">
					<h3>Colocación de POP · <?= $h($avisos['pop']['mes']) ?></h3>
					<span class="ep-avisos-etq<?= $avisos['pop']['urgente'] ? ' urgente' : '' ?>"><?= $h($avisos['pop']['ultimo_dia']) ?></span>
				</div>
				<p class="ep-avisos-sub">Reporta el material POP que colocaste<?= $avisos['pop']['nuevo'] ? ' · nuevo' : '' ?></p>
				<a class="ep-avisos-ir" href="index.php?vista=actividades">Reportar colocación</a>
			</article>
		<?php endif; ?>
		<?php if ($avisos['pop_reparto']): ?>
			<article class="ep-avisos-cal<?= $avisos['pop_reparto']['urgente'] ? ' urgente' : '' ?>">
				<div class="ep-avisos-cal-top">
					<h3>Colocación de POP · <?= $h($avisos['pop_reparto']['mes']) ?></h3>
					<span class="ep-avisos-etq<?= $avisos['pop_reparto']['urgente'] ? ' urgente' : '' ?>"><?= $h($avisos['pop_reparto']['ultimo_dia']) ?></span>
				</div>
				<p class="ep-avisos-sub">Te asignaron <?= $plural($avisos['pop_reparto']['materiales'], 'material', 'materiales') ?>. Elige los promotores que lo reportan<?= $avisos['pop_reparto']['nuevo'] ? ' · nuevo' : '' ?></p>
				<a class="ep-avisos-ir" href="index.php?vista=pop#asignada">Elegir mi equipo</a>
			</article>
		<?php endif; ?>
		<?php if (!$avisos['calendarios'] && !$avisos['devueltos'] && !$avisos['pop'] && !$avisos['pop_reparto']): ?>
			<div class="ep-avisos-vacio"><?= ep_icon('check', 22) ?><strong><?= ep_es_supervisor() ? 'No tienes nada por elegir' : 'No tienes activaciones pendientes' ?></strong><span><?= ep_es_supervisor() ? 'Cuando te asignen material POP, te aparecerá aquí.' : 'Cuando te programen una, te aparecerá aquí.' ?></span></div>
		<?php endif; ?>
		<?php foreach ($avisos['calendarios'] as $c): ?>
			<article class="ep-avisos-cal<?= $c['urgente'] ? ' urgente' : '' ?>">
				<div class="ep-avisos-cal-top">
					<h3><?= $h($c['nombre']) ?></h3>
					<span class="ep-avisos-etq<?= $c['urgente'] ? ' urgente' : '' ?>"><?= $h($c['ultimo_dia']) ?></span>
				</div>
				<p class="ep-avisos-sub"><?= $plural(count($c['filas']), 'activación pendiente', 'activaciones pendientes') ?><?= $c['nuevos'] ? ' · '.$plural($c['nuevos'], 'nueva', 'nuevas') : '' ?></p>
				<ul class="ep-avisos-filas">
					<?php foreach ($c['filas'] as $f): ?>
						<li<?= $f['nueva'] ? ' class="nueva"' : '' ?>>
							<span class="ep-avisos-fecha"><?= $h($fechaCorta($f['fecha'])) ?></span>
							<span class="ep-avisos-punto"><?= $h($f['punto']) ?><?php if ($f['ciudad']): ?><em><?= $h($f['ciudad']) ?></em><?php endif; ?></span>
							<?php if ($f['nueva']): ?><b class="ep-avisos-nueva">Nueva</b><?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</article>
		<?php endforeach; ?>
	</div>
	<?php if ($avisos['calendarios']): ?>
	<footer class="ep-avisos-pie">
		<a class="ep-avisos-ir" href="index.php?vista=actividades">Registrar una activación</a>
	</footer>
	<?php endif; ?>
</section>
<script type="application/json" id="epAvisosDatos"><?= json_encode(['total' => $avisos['total'], 'nuevos' => $avisos['nuevos'], 'urgentes' => $avisos['urgentes'], 'devueltosNuevos' => (int) ($avisos['devueltos_nuevos'] ?? 0), 'devueltos' => count($avisos['devueltos']), 'urgentesTxt' => array_values(array_merge(array_map(fn($d) => 'Registro devuelto: '.$d['punto'], $avisos['devueltos']), array_map(fn($c) => $c['nombre'].' ('.mb_strtolower($c['ultimo_dia']).')', array_filter($avisos['calendarios'], fn($c) => $c['urgente'])), ($avisos['pop'] && $avisos['pop']['urgente']) ? ['Colocación de POP ('.mb_strtolower($avisos['pop']['ultimo_dia']).')'] : [], ($avisos['pop_reparto'] && $avisos['pop_reparto']['urgente']) ? ['POP: elige tu equipo ('.mb_strtolower($avisos['pop_reparto']['ultimo_dia']).')'] : []))], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

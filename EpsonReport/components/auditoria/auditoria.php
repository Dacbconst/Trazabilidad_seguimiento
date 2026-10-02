<?php
// Auditoría (solo admin): bitácora de lo que hicieron los admins, una línea por movimiento y detalle al costado.
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../includes/auditoria_datos.php';

if (ep_rol_actual() !== 'admin') {
	echo '<main class="ep-content"><p>No tienes permiso para ver esta sección.</p></main>';
	return;
}
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$eventos = ep_auditoria_listar();
$acciones = ep_auditoria_acciones();
$entidades = ['calendario' => 'Calendario', 'registro' => 'Registro', 'reporte' => 'Reporte mensual', 'actividad' => 'Actividad', 'usuario' => 'Usuario'];
$iconoTipo = ['crea' => 'plus', 'edita' => 'pencil', 'borra' => 'trash', 'aviso' => 'shield', 'sis' => 'clock'];
$nombreTipo = ['crea' => 'Creaciones', 'edita' => 'Cambios', 'borra' => 'Eliminaciones', 'aviso' => 'Alertas', 'sis' => 'Sistema'];
$dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$hoy = date('Y-m-d');
$ayer = date('Y-m-d', strtotime('-1 day'));
$fechaLarga = fn(int $t): string => $dias[(int) date('w', $t)].' '.(int) date('j', $t).' de '.$meses[(int) date('n', $t)].(date('Y', $t) !== date('Y') ? ' de '.date('Y', $t) : '');
$etiquetaDia = function (string $fecha) use ($hoy, $ayer, $fechaLarga): string {
	if ($fecha === $hoy) {
		return 'Hoy';
	}
	return $fecha === $ayer ? 'Ayer' : ucfirst($fechaLarga(strtotime($fecha)));
};
$tipoDe = function (array $e): string {
	// Van primero: son alertas aunque las haya disparado el Sistema (sin usuario), no un simple registro de rutina.
	if (str_ends_with($e['accion'], '_duplicado') || str_ends_with($e['accion'], '_sin_reporte')) {
		return 'aviso';
	}
	if ($e['usuario_id'] === null) {
		return 'sis';
	}
	if (str_ends_with($e['accion'], '_eliminar')) {
		return 'borra';
	}
	return str_ends_with($e['accion'], '_crear') ? 'crea' : 'edita';
};

// Datos ya resueltos por movimiento: la lista los pinta y el panel de detalle los lee del JSON
$movs = [];
$conteoTipo = ['crea' => 0, 'edita' => 0, 'borra' => 0, 'aviso' => 0, 'sis' => 0];
$usuarios = [];
foreach ($eventos as $i => $e) {
	$tipo = $tipoDe($e);
	$esSistema = $tipo === 'sis';
	$quien = $esSistema ? 'Sistema' : ($e['usuario_nombre'] ?: ($e['usuario'] ?? ''));
	$t = strtotime($e['created_at']);
	$cambios = count(array_filter($e['detalle'], fn($d) => array_key_exists('antes', $d)));
	$conteoTipo[$tipo]++;
	$usuarios[$quien] = ($usuarios[$quien] ?? 0) + 1;
	$movs[] = [
		'id' => (int) $e['id'],
		'fecha' => substr($e['created_at'], 0, 10),
		'hora' => date('H:i', $t),
		'tipo' => $tipo,
		'accion' => $acciones[$e['accion']] ?? $e['accion'],
		'resumen' => $e['resumen'],
		'quien' => $quien,
		'usuario' => $esSistema ? '' : (string) ($e['usuario'] ?? ''),
		'afectado' => (string) ($e['afectado'] ?? ''),
		// Las direcciones internas del balanceador (169.254.x) no sirven de nada: no se muestran.
		'ip' => preg_match('/^(169\.254\.|127\.)/', (string) ($e['ip'] ?? '')) ? '' : (string) ($e['ip'] ?? ''),
		'cuando' => $fechaLarga($t).', '.date('H:i', $t),
		'cambios' => $cambios,
		'detalle' => $e['detalle'],
	];
}
arsort($usuarios);
$grupos = [];
foreach ($movs as $i => $m) {
	$grupos[$m['fecha']][$i] = $m;
}
$plural = fn(int $n, string $uno, string $varios): string => $n.' '.($n === 1 ? $uno : $varios);
?>
<main class="ep-content ep-au" id="epAu">

	<div class="ep-au-top">
		<header class="ep-au-head">
			<div>
				<h1>Auditoría <span class="ep-vivo" id="epAuVivo" title="Se actualiza sola cada pocos segundos"><i></i><span>En vivo</span></span></h1>
				<p id="epAuResumen"><?= $plural(count($movs), 'movimiento', 'movimientos') ?> · quién hizo qué y cuándo</p>
			</div>
			<?php if ($movs): ?>
			<div class="ep-au-seg" id="epAuRapidos" role="group" aria-label="Periodo">
				<button type="button" class="on" data-rapido="todo">Todo</button>
				<button type="button" data-rapido="hoy">Hoy</button>
				<button type="button" data-rapido="semana">Semana</button>
				<button type="button" data-rapido="mes">Mes</button>
			</div>
			<?php endif; ?>
		</header>

		<?php if ($movs): ?>
		<div class="ep-au-tools">
			<label class="ep-au-buscar">
				<?= ep_icon('search', 18) ?>
				<input type="search" id="epAuBuscar" placeholder="Buscar por calendario, código, punto de venta, promotor" autocomplete="off" aria-label="Buscar movimientos">
			</label>
			<select class="ep-au-sel" id="epAuUsuario" aria-label="Filtrar por usuario">
				<option value="">Todos los usuarios</option>
				<?php foreach ($usuarios as $nombre => $n): ?>
					<option value="<?= $h($nombre) ?>"><?= $h($nombre) ?> (<?= $n ?>)</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="ep-au-chips" id="epAuTipos" role="group" aria-label="Tipo de movimiento">
			<button type="button" class="ep-au-chip on" data-tipo=""><i class="ep-au-pt ep-au-pt-todo"></i>Todo <em><?= count($movs) ?></em></button>
			<?php foreach ($nombreTipo as $k => $txt): ?>
				<button type="button" class="ep-au-chip" data-tipo="<?= $k ?>"><i class="ep-au-pt ep-au-pt-<?= $k ?>"></i><?= $txt ?> <em><?= $conteoTipo[$k] ?></em></button>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>

	<?php if (empty($movs)): ?>
		<?= ep_estado_vacio('shield', 'Todavía no hay movimientos', 'Cada vez que un admin cree, cambie, reactive o elimine algo, quedará anotado aquí con su nombre y la hora.') ?>
	<?php else: ?>
	<div class="ep-au-work">
		<section class="ep-au-feed" id="epAuLista" aria-label="Movimientos">
			<?php foreach ($grupos as $fecha => $delDia): ?>
				<div class="ep-au-dia" data-fecha="<?= $h($fecha) ?>">
					<div class="ep-au-dia-head"><h3><?= $h($etiquetaDia($fecha)) ?></h3><span></span></div>
					<?php foreach ($delDia as $i => $m):
						$busqueda = mb_strtolower($m['resumen'].' '.$m['quien'].' '.$m['usuario'].' '.$m['accion'].' '.implode(' ', array_map(fn($d) => ($d['campo'] ?? '').' '.($d['antes'] ?? '').' '.($d['despues'] ?? '').' '.($d['valor'] ?? ''), $m['detalle'])), 'UTF-8');
					?>
						<div class="ep-au-ev" tabindex="0" role="button" data-i="<?= $i ?>" data-id="<?= (int) $m['id'] ?>" data-fecha="<?= $h($fecha) ?>" data-usuario="<?= $h($m['quien']) ?>" data-tipo="<?= $m['tipo'] ?>" data-busqueda="<?= $h($busqueda) ?>">
							<time class="ep-au-hora"><?= $h($m['hora']) ?></time>
							<span class="ep-au-ico ep-au-t-<?= $m['tipo'] ?>"><?= ep_icon($iconoTipo[$m['tipo']], 16) ?></span>
							<div class="ep-au-tx">
								<b><?= $h($m['resumen']) ?></b>
								<span><u><?= $h($m['quien']) ?></u> · <?= $h($m['accion']) ?></span>
							</div>
							<?php if ($m['cambios']): ?><span class="ep-au-cambios"><?= $plural($m['cambios'], 'cambio', 'cambios') ?></span><?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
			<div class="ep-au-sin hidden" id="epAuSin"><?= ep_estado_vacio('search', 'Sin resultados', 'Ningún movimiento coincide con los filtros. Prueba con otro usuario, tipo o periodo.') ?></div>
			<?php if (count($movs) >= EP_AUDITORIA_LIMITE): ?>
				<p class="ep-au-tope">Se muestran los <?= EP_AUDITORIA_LIMITE ?> movimientos más recientes.</p>
			<?php endif; ?>
		</section>

		<aside class="ep-au-det" id="epAuDet" aria-label="Detalle del movimiento">
			<div class="ep-au-det-vacio" id="epAuDetVacio"><?= ep_icon('shield', 32) ?><p>Elige un movimiento para ver quién lo hizo, cuándo y qué cambió exactamente.</p></div>
			<div class="ep-au-det-in" id="epAuDetIn"></div>
		</aside>
	</div>
	<script type="application/json" id="epAuDatos"><?= json_encode($movs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
	<?php endif; ?>
</main>

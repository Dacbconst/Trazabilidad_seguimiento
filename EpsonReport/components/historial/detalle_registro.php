<?php
// Detalle de un registro (panel derecho del Historial). Estadísticas con las mismas tarjetas del formulario (ep-stat-*).
// Variables del contexto: $r (registro), $esAdmin, $h (escape HTML).
$tipo = $r['tipo'] ?? 'activaciones';
$pct = function ($v): string { return (int) round((float) $v) . '%'; };
$emb = $r['embudo'] ?? $r['feria'] ?? null;
$cob = $r['cobertura'] ?? null;
$cum = $r['cumplimiento'] ?? null;
$modelos = $r['modelos'] ?? [];
usort($modelos, fn($a, $b) => ($b['cantidad'] ?? 0) <=> ($a['cantidad'] ?? 0));
$comentarios = array_values(array_filter((array) ($r['comentarios'] ?? [])));
$fotos = $r['fotos'] ?? [];
// Competencia con varios puntos de venta: no hay una sola grilla de fotos, se juntan las de todos los puntos con el punto en la etiqueta.
$puntosCompetencia = $r['puntos'] ?? [];
if ($puntosCompetencia && is_array($puntosCompetencia)) {
	$fotos = [];
	foreach ($puntosCompetencia as $punto) {
		foreach ($punto['fotos'] ?? [] as $f) {
			$f['label'] = trim((string) ($punto['punto_venta'] ?? '')).' — '.($f['label'] ?? '');
			$fotos[] = $f;
		}
	}
}
$conFoto = count(array_filter($fotos, fn($f) => !empty($f['url'])));
$estadoReg = $r['estado'] ?? 'Aprobado';
$enModoAprobacion = !empty($modoAprobacion);
?>
<div class="ep-h2-det-head">
	<span class="ep-h2-det-ico"><?= ep_icon(ep_icono_tipo($tipo), 22) ?></span>
	<?php $fechaPlan = $r['fecha_actividad'] ?? ($r['fecha_iso'] ?? ''); ?>
	<div class="ep-h2-det-titulo">
		<strong title="<?= $h($r['id'] ?? '') ?>"><?= $h(trim((string) ($r['punto_venta'] ?? '')) ?: ($r['actividad_label'] ?? 'Actividad')) ?><?= count($puntosCompetencia) > 1 ? ' + '.(count($puntosCompetencia) - 1).' punto'.(count($puntosCompetencia) > 2 ? 's' : '').' más' : '' ?></strong>
		<small><?= $h($r['actividad_label'] ?? 'Actividad') ?><?= !empty($r['tipo_actividad']) ? ' · '.$h($r['tipo_actividad']) : '' ?></small>
		<div class="ep-h2-det-meta">
			<?php if ($fechaPlan): ?><span><?= ep_icon('calendar', 13) ?> <?= $h(date('d/m/Y', strtotime($fechaPlan))) ?></span><?php endif; ?>
			<?php if (!empty($r['hora_inicio'])): ?><span><?= ep_icon('clock', 13) ?> <?= $h($r['hora_inicio'].' – '.$r['hora_fin']) ?></span><?php endif; ?>
			<?php if ($esAdmin && !empty($r['promotor'])): ?><span><?= ep_icon('users', 13) ?> <?= $h($r['promotor']) ?></span><?php endif; ?>
		</div>
	</div>
	<div class="ep-h2-det-acciones">
		<?php if ($esAdmin): ?>
			<button type="button" class="ep-btn-record-ppt ep-h2-btn-ppt" data-id="<?= $h($r['id'] ?? '') ?>" data-tipo="<?= $h($tipo) ?>" data-promotor="<?= $h($r['promotor'] ?? '') ?>" data-fecha="<?= $h($r['fecha_iso'] ?? '') ?>">
				<?= ep_icon('download', 15) ?> <span>Descargar Slide</span>
			</button>
			<button type="button" class="ep-h2-btn-eliminar" data-codigo="<?= $h($r['id'] ?? '') ?>" aria-label="Eliminar registro" title="Eliminar registro"><?= ep_icon('trash', 15) ?></button>
		<?php endif; ?>
		<button type="button" class="ep-h2-cerrar" aria-label="Cerrar detalle"><?= ep_icon('close', 14) ?></button>
	</div>
</div>

<?php if ($estadoReg === 'Devuelto'): ?>
	<div class="ep-h2-banner ep-h2-banner-dev">
		<strong><?= ep_icon('info', 15) ?> Devuelto<?= !empty($r['revisor']) ? ' por '.$h($r['revisor']) : '' ?><?= !empty($r['revisado_en']) ? ' · '.$h(date('d/m H:i', strtotime($r['revisado_en']))) : '' ?></strong>
		<p><?= $h($r['motivo_devolucion'] ?? '') ?></p>
		<?php if (!$esAdmin): ?><a class="ep-h2-btn-corregir" href="index.php?vista=actividades&corregir=<?= urlencode((string) ($r['id'] ?? '')) ?>">Corregir y reenviar</a><?php endif; ?>
	</div>
<?php elseif ($estadoReg === 'Pendiente' && !$esAdmin): ?>
	<div class="ep-h2-banner ep-h2-banner-pend"><strong><?= ep_icon('clock', 15) ?> Pendiente de aprobación</strong><p>Tu supervisor lo revisará pronto. Si lo devuelve, te lo avisamos en la campana.</p></div>
<?php endif; ?>

<div class="ep-h2-det-cuerpo">
	<div class="ep-stats-panel ep-h2-stats">

	<?php if ($tipo === 'activaciones' || $tipo === 'epson-day' || $tipo === 'evento-ferias'): ?>
		<div class="ep-stats-row-3">
			<?php if ($cob): ?>
				<div class="ep-stat-card">
					<div class="ep-stat-card-header"><?= ep_icon('store', 13) ?> Cobertura</div>
					<div class="ep-stat-card-body">
						<div class="ep-stat-card-pct"><?= $pct($cob['pct'] ?? 0) ?></div>
						<div class="ep-stat-card-detalle">
							<div class="ep-stat-card-fila"><span>Nacional</span><strong><?= (int) ($cob['nacional'] ?? 0) ?></strong></div>
							<div class="ep-stat-card-fila"><span>Coberturadas</span><strong><?= (int) ($cob['coberturadas'] ?? 0) ?></strong></div>
						</div>
					</div>
				</div>
			<?php endif; ?>
			<?php if ($emb): ?>
				<div class="ep-stat-card">
					<div class="ep-stat-card-header"><?= ep_icon('users', 13) ?> Interacciones</div>
					<div class="ep-stat-card-body">
						<div class="ep-stat-card-pct"><?= $pct($emb['tasa_interaccion_pct'] ?? 0) ?></div>
						<div class="ep-stat-card-detalle">
							<div class="ep-stat-card-fila"><span>Visitaron</span><strong><?= (int) ($emb['visitaron'] ?? 0) ?></strong></div>
							<div class="ep-stat-card-fila"><span>Interactuaron</span><strong><?= (int) ($emb['interactuaron'] ?? 0) ?></strong></div>
						</div>
					</div>
				</div>
				<div class="ep-stat-card">
					<div class="ep-stat-card-header"><?= ep_icon('check', 13) ?> Ventas</div>
					<div class="ep-stat-card-body">
						<div class="ep-stat-card-pct"><?= $pct($emb['tasa_conversion_pct'] ?? 0) ?></div>
						<div class="ep-stat-card-detalle">
							<div class="ep-stat-card-fila"><span>Realizadas</span><strong><?= (int) ($emb['compraron'] ?? 0) ?></strong></div>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<?php if ($emb): $maxEmb = max(1, (int) ($emb['visitaron'] ?? 0)); ?>
			<div class="ep-stat-card-plano">
				<div class="ep-stat-card-titulo"><?= ep_icon('arrow-right', 15) ?> Embudo de Clientes</div>
				<div class="ep-stat-bars-vert">
					<?php foreach ([['Visitaron', 'visitaron'], ['Interactuaron', 'interactuaron'], ['Compraron', 'compraron']] as [$lbl, $k]): $v = (int) ($emb[$k] ?? 0); ?>
						<div class="ep-stat-bar-vert">
							<span class="ep-stat-bar-vert-valor"><?= $v ?></span>
							<div class="ep-stat-bar-vert-fill" style="height:<?= max(3, round($v / $maxEmb * 70)) ?>px;"></div>
							<span class="ep-stat-bar-vert-label"><?= $lbl ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php $totalUds = array_sum(array_map(fn($m) => (int) ($m['cantidad'] ?? 0), $modelos)); $maxCant = max(1, (int) ($modelos[0]['cantidad'] ?? 1)); $mayor = $modelos[0] ?? ['modelo' => 'Sin datos', 'cantidad' => 0]; $menor = !empty($modelos) ? $modelos[count($modelos) - 1] : $mayor; ?>
			<div class="ep-stats-row-2">
				<div class="ep-stats-col">
					<div class="ep-stat-card-mini">
						<span class="ep-stat-card-mini-pct"><?= $totalUds > 0 ? $pct(($mayor['cantidad'] ?? 0) / $totalUds * 100) : '0%' ?></span>
						<span class="ep-stat-card-mini-nombre"><?= $h($mayor['modelo'] ?? '') ?></span>
						<span class="ep-stat-card-mini-caption">SKU con mayor venta</span>
					</div>
					<div class="ep-stat-card-mini">
						<span class="ep-stat-card-mini-pct"><?= $totalUds > 0 ? $pct(($menor['cantidad'] ?? 0) / $totalUds * 100) : '0%' ?></span>
						<span class="ep-stat-card-mini-nombre"><?= $h($menor['modelo'] ?? '') ?></span>
						<span class="ep-stat-card-mini-caption">SKU con menor venta</span>
					</div>
				</div>
				<div class="ep-stat-card-plano">
					<div class="ep-stat-card-titulo"><?= ep_icon('store', 15) ?> Detalle de Ventas</div>
					<?php foreach ($modelos as $m): ?>
						<div class="ep-venta-fila">
							<span class="ep-venta-nombre"><?= $h($m['modelo'] ?? '') ?></span>
							<div class="ep-venta-barra-track"><div class="ep-venta-barra-fill" style="width:<?= round(((int) ($m['cantidad'] ?? 0)) / $maxCant * 100) ?>%;"></div></div>
							<span class="ep-venta-valor"><?= (int) ($m['cantidad'] ?? 0) ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<?php
			// Ingresos por modelo: solo si algún modelo trae precio (Activaciones y Epson Day; Evento o Ferias no pide precio).
			$modelosConPrecio = array_values(array_filter($modelos, fn($m) => ((float) ($m['precio'] ?? 0)) > 0));
			if (!empty($modelosConPrecio)):
				usort($modelosConPrecio, fn($a, $b) => ($b['cantidad'] * $b['precio']) <=> ($a['cantidad'] * $a['precio']));
				$maxIngreso = max(1, $modelosConPrecio[0]['cantidad'] * $modelosConPrecio[0]['precio']);
			?>
				<div class="ep-stats-row-2" style="grid-template-columns:minmax(0,1fr);">
					<div class="ep-stat-card-plano">
						<div class="ep-stat-card-titulo"><?= ep_icon('bar-chart', 15) ?> Ingresos por Modelo</div>
						<?php foreach ($modelosConPrecio as $m): $ingreso = $m['cantidad'] * $m['precio']; ?>
							<div class="ep-venta-fila ep-venta-fila-dinero">
								<span class="ep-venta-nombre"><?= $h($m['modelo'] ?? '') ?></span>
								<div class="ep-venta-barra-track"><div class="ep-venta-barra-fill" style="width:<?= round($ingreso / $maxIngreso * 100) ?>%;"></div></div>
								<span class="ep-venta-valor">$<?= number_format($ingreso, 2) ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>


	<?php elseif ($tipo === 'capacitaciones' && !empty($r['capacitacion'])): $c = $r['capacitacion'];
		$cargos = [['Vendedores', (int) ($c['vendedores'] ?? 0)], ['Jefe de tienda', (int) ($c['jefe_tienda'] ?? 0)], ['Asistente de jefe', (int) ($c['asistente_jefe'] ?? 0)]];
		$totalAsist = array_sum(array_column($cargos, 1));
		$inter = (int) ($c['interacciones'] ?? 0); ?>
		<div class="ep-stats-row-3">
			<div class="ep-stat-card">
				<div class="ep-stat-card-header"><?= ep_icon('users', 13) ?> Asistentes</div>
				<div class="ep-stat-card-body"><div class="ep-stat-card-pct"><?= $totalAsist ?></div></div>
			</div>
			<div class="ep-stat-card">
				<div class="ep-stat-card-header"><?= ep_icon('check', 13) ?> Interacciones</div>
				<div class="ep-stat-card-body">
					<div class="ep-stat-card-pct"><?= $pct($totalAsist > 0 ? $inter / $totalAsist * 100 : 0) ?></div>
					<div class="ep-stat-card-detalle"><div class="ep-stat-card-fila"><span>Interacciones</span><strong><?= $inter ?></strong></div></div>
				</div>
			</div>
		</div>
		<div class="ep-stat-card-plano">
			<div class="ep-stat-card-titulo"><?= ep_icon('users', 15) ?> Detalle de Asistentes</div>
			<?php foreach ($cargos as [$nombreCargo, $cantidadCargo]): ?>
				<div class="ep-venta-fila">
					<span class="ep-venta-nombre"><?= $h($nombreCargo) ?></span>
					<div class="ep-venta-barra-track"><div class="ep-venta-barra-fill" style="width:<?= $totalAsist > 0 ? round($cantidadCargo / $totalAsist * 100) : 0 ?>%;"></div></div>
					<span class="ep-venta-valor"><?= $cantidadCargo ?></span>
				</div>
			<?php endforeach; ?>
		</div>

	<?php elseif ($tipo === 'exhibiciones' && !empty($r['exhibiciones'])): $x = $r['exhibiciones']; ?>
		<div class="ep-stats-row-3">
			<?php foreach ([['Cabeceras', 'cabeceras'], ['Rumas', 'rumas'], ['Muebles', 'muebles'], ['Exh. regular', 'exh_regular'], ['Otras', 'otras']] as [$lbl, $k]): ?>
				<div class="ep-stat-card">
					<div class="ep-stat-card-header"><?= ep_icon('store', 13) ?> <?= $lbl ?></div>
					<div class="ep-stat-card-body">
						<div class="ep-stat-card-pct"><?= (int) ($x[$k] ?? 0) ?></div>
						<div class="ep-stat-card-detalle"><div class="ep-stat-card-fila"><span>Del total</span><strong><?= $h($x[$k . '_pct'] ?? '0%') ?></strong></div></div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

	<?php elseif ($tipo === 'colocacion-pop' && !empty($r['pop_entregas'])): ?>
		<div class="ep-stat-card-plano">
			<div class="ep-stat-card-titulo"><?= ep_icon('tag', 15) ?> Material POP entregado · Campaña <?= $h($r['campana'] ?? '') ?></div>
			<div class="ep-h2-tabla-wrap">
				<table class="ep-h2-tabla">
					<thead><tr><th>Material</th><th>Cantidad</th></tr></thead>
					<tbody>
					<?php foreach ($r['pop_entregas'] as $p): ?>
						<tr><td><?= $h($p['material'] ?? '') ?></td><td><?= (int) ($p['cantidad'] ?? 0) ?></td></tr>
					<?php endforeach; ?>
						<tr><td><strong>Total</strong></td><td><strong><?= array_sum(array_column($r['pop_entregas'], 'cantidad')) ?></strong></td></tr>
					</tbody>
				</table>
			</div>
		</div>
	<?php endif; ?>

	<?php if ($cum || !empty($comentarios)): ?>
		<div class="ep-stats-row-2<?= ($cum && !empty($comentarios)) ? '' : ' ep-h2-fila-unica' ?>">
		<?php if ($cum): ?>
			<div class="ep-stat-card">
				<div class="ep-stat-card-header"><?= ep_icon('bar-chart', 13) ?> Cumplimiento</div>
				<div class="ep-stat-card-body">
					<div class="ep-stat-card-pct"><?= $pct($cum['pct'] ?? 0) ?></div>
					<div class="ep-stat-card-detalle">
						<div class="ep-stat-card-fila"><span>Activaciones Programadas</span><strong><?= (int) ($cum['programadas'] ?? 0) ?></strong></div>
						<div class="ep-stat-card-fila"><span>Activaciones Ejecutadas</span><strong><?= (int) ($cum['realizadas'] ?? 0) ?></strong></div>
					</div>
				</div>
			</div>
		<?php endif; ?>
		<?php if (!empty($comentarios)): ?>
			<div class="ep-stat-card-plano">
				<div class="ep-stat-card-titulo"><?= ep_icon('file', 15) ?> Comentarios</div>
				<div class="ep-stat-comentarios">
					<?php foreach ($comentarios as $c): ?><div><?= $h($c) ?></div><?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
		</div>
	<?php endif; ?>

	</div>

	<div class="ep-h2-fotos-sec">
		<div class="ep-h2-fotos-titulo"><?= ep_icon('camera', 13) ?> Evidencia fotográfica <span>(<?= $conFoto ?>/<?= count($fotos) ?>)</span></div>
		<?php if (empty($fotos)): ?>
			<div class="ep-stat-comentarios-vacio">Este registro no tiene fotos.</div>
		<?php else: ?>
			<div class="ep-h2-carrusel">
			<button type="button" class="ep-h2-car-btn ep-h2-car-prev" aria-label="Anterior" disabled>&#8249;</button>
			<button type="button" class="ep-h2-car-btn ep-h2-car-next" aria-label="Siguiente">&#8250;</button>
			<div class="ep-h2-fotos-grid ep-h2-carril">
				<?php foreach ($fotos as $f):
					// Competencia: la descripción que escribió el promotor dice más que la etiqueta de la casilla.
					$textoFoto = ($f['descripcion'] ?? '') !== '' ? $f['descripcion'] : ($f['label'] ?? 'Foto');
				?>
					<button type="button" class="ep-h2-foto" data-url="<?= $h($f['url'] ?? '') ?>" data-label="<?= $h($textoFoto) ?>">
						<?php if (!empty($f['url'])): ?>
							<img src="<?= $h($f['url']) ?>" alt="<?= $h($textoFoto) ?>" loading="lazy">
						<?php else: ?>
							<span class="ep-h2-foto-vacia"><?= ep_icon('camera', 18) ?></span>
						<?php endif; ?>
						<span class="ep-h2-foto-lbl<?= ($f['descripcion'] ?? '') !== '' ? ' ep-h2-foto-desc' : '' ?>" title="<?= $h($textoFoto) ?>"><?= $h($textoFoto) ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			</div>
		<?php endif; ?>
	</div>
</div>
<?php if ($enModoAprobacion && $estadoReg === 'Pendiente'): ?>
<div class="ep-h2-det-aprobar">
	<button type="button" class="ep-h2-btn-devolver" data-codigo="<?= $h($r['id'] ?? '') ?>" data-punto="<?= $h($r['punto_venta'] ?? '') ?>"><?= ep_icon('arrow-left', 15) ?> Devolver</button>
	<button type="button" class="ep-h2-btn-aprobar" data-codigo="<?= $h($r['id'] ?? '') ?>"><?= ep_icon('check', 15) ?> Aprobar</button>
</div>
<?php endif; ?>

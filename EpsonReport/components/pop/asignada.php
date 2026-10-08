<?php
// Pestaña "Colocación asignada": una tarjeta desplegable por mes con la matriz promotor x material (el saldo va en el encabezado de cada material); usa las variables de pop.php.
$iconoAviso = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>';
?>
<?php if (!$mesesSup): ?>
	<?= ep_estado_vacio('tag', 'Todavía no te asignaron POP', 'Cuando Fabricio reparta el material de un mes, lo verás aquí.') ?>
<?php else: ?>
	<?php foreach ($mesesSup as $mes): ?>
		<?php
		$abierto = $mes['estado'] === 'activo';
		$parteMes = ep_pop_parte_supervisor($mes, $yo);
		$asignados = ep_pop_equipo_de((int) $mes['id'], $yo);
		$materiales = [];
		foreach ($mes['filas'] as $f) {
			if ($p = $parteMes[(int) $f['id']] ?? null) {
				$materiales[] = ['id' => (int) $f['id'], 'material' => $f['material'], 'campana' => $f['campana'], 'recibido' => $p['recibido'], 'reportado' => $p['reportado'], 'disponible' => $p['disponible']];
			}
		}
		$recibidoTotal = array_sum(array_column($materiales, 'recibido'));
		$reportadoTotal = array_sum(array_column($materiales, 'reportado'));
		$pct = $recibidoTotal > 0 ? min(100, (int) round($reportadoTotal / $recibidoTotal * 100)) : 0;
		?>
		<details class="ep-as-mes" data-pop-id="<?= (int) $mes['id'] ?>"<?= $abierto ? ' open' : '' ?>>
			<summary class="ep-as-sum">
				<svg class="ep-as-chev" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
				<strong><?= $h(ep_pop_mes_texto($mes['mes'])) ?></strong>
				<span class="ep-pop-badge <?= $abierto ? 'ep-pop-badge-abierto' : 'ep-pop-badge-cerrado' ?>"><?= $abierto ? 'Abierto' : 'Cerrado' ?></span>
				<span class="ep-as-meta"><span class="ep-as-pb" aria-hidden="true"><i style="width: <?= $pct ?>%"></i></span><?= (int) $reportadoTotal ?> de <?= (int) $recibidoTotal ?> unidades reportadas · <?= count($asignados) ?> <?= count($asignados) === 1 ? 'promotor' : 'promotores' ?></span>
				<?php if ($abierto && !$asignados): ?><span class="ep-as-aviso"><?= $iconoAviso ?>Falta elegir tu equipo</span><?php endif; ?>
			</summary>
			<?php if ($abierto): ?>
				<?php
				$reportado = [];
				foreach ($asignados as $usuarioId => $filas) {
					$hecho = ep_pop_reportado_usuario($usuarioId, $mes);
					foreach ($filas as $filaId) {
						if (($hecho[$filaId] ?? 0) > 0) {
							$reportado[$usuarioId][$filaId] = $hecho[$filaId];
						}
					}
				}
				?>
				<div class="ep-eq-in" id="epEq">
					<div class="ep-eq-bar">
						<h3>Mi equipo<span>Marca qué material reporta cada promotor</span></h3>
						<div class="ep-eq-acciones">
						<button type="button" class="ep-eq-btn ep-eq-btn-p" id="epEqEditar" hidden><?= ep_icon('pencil', 14) ?> Editar equipo</button>
						<div class="ep-eq-menu">
							<button type="button" class="ep-eq-btn ep-eq-btn-o" id="epEqAgregar" aria-haspopup="true" aria-expanded="false"><?= ep_icon('plus', 15) ?> Agregar promotor</button>
							<div class="ep-eq-pop hidden" id="epEqPop">
								<input type="search" class="ep-eq-buscar" id="epEqBuscar" placeholder="Buscar promotor" autocomplete="off" aria-label="Buscar promotor">
								<button type="button" class="ep-eq-todo" id="epEqTodo"><?= ep_icon('users', 14) ?> Agregar todo mi equipo</button>
								<div class="ep-eq-lista" id="epEqLista"></div>
							</div>
						</div>
						</div>
					</div>
					<div class="ep-eq-scroll"><div id="epEqMatriz"></div></div>
					<div class="ep-eq-sucio" id="epEqSucio" role="status" aria-live="polite" hidden>
						<span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg><span id="epEqCuenta"></span></span>
						<div class="ep-eq-bt">
							<button type="button" class="ep-eq-btn" id="epEqDescartar">Descartar</button>
							<button type="button" class="ep-eq-btn ep-eq-btn-p" id="epEqGuardar">Guardar equipo</button>
						</div>
					</div>
				</div>
				<script type="application/json" id="epEqDatos"><?= json_encode(['id' => (int) $mes['id'], 'materiales' => $materiales, 'equipo' => ep_pop_equipo($yo), 'asignados' => (object) $asignados, 'reportado' => (object) array_map(fn($x) => (object) $x, $reportado)], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
			<?php elseif (!$asignados): ?>
				<p class="ep-eq-cerrado">En este mes no marcaste a ningún promotor.</p>
			<?php else: ?>
				<?php
				$nombres = array_column(ep_pop_equipo($yo), 'nombre', 'id');
				$cols = 'minmax(210px, 1.2fr) repeat('.count($materiales).', minmax(190px, 1fr))';
				?>
				<div class="ep-eq-scroll"><div class="ep-eq-solo">
					<div class="ep-eq-r ep-eq-h" style="grid-template-columns: <?= $cols ?>">
						<span class="ep-eq-lbl">Promotor</span>
						<?php foreach ($materiales as $m): ?>
							<div class="ep-eq-mh"><span class="ep-eq-nm"><strong><?= $h($m['material']) ?></strong><em><?= $h($m['campana']) ?></em></span><span class="ep-eq-sd"><b><?= (int) $m['reportado'] ?></b> reportadas de <?= (int) $m['recibido'] ?></span></div>
						<?php endforeach; ?>
					</div>
					<?php foreach ($asignados as $usuarioId => $filas): ?>
						<?php $hecho = ep_pop_reportado_usuario($usuarioId, $mes); ?>
						<div class="ep-eq-r ep-eq-fila" style="grid-template-columns: <?= $cols ?>">
							<span class="ep-eq-who"><b><?= $h($nombres[$usuarioId] ?? 'Promotor #'.$usuarioId) ?></b></span>
							<?php foreach ($materiales as $m): ?>
								<span class="ep-eq-c">
									<?php if (!in_array($m['id'], $filas, true)): ?><span class="ep-sg-no" title="Sin este material">·</span>
									<?php else: $n = (int) ($hecho[$m['id']] ?? 0); ?><span class="ep-sg-n<?= $n > 0 ? '' : ' z' ?>"><?= $n ?></span><?php endif; ?>
								</span>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div></div>
			<?php endif; ?>
		</details>
	<?php endforeach; ?>
<?php endif; ?>

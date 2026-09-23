<?php
// Detalle del Formulario Diligenciado según la plantilla real oficial de Epson
// Variables disponibles desde el contexto: $r (registro), $tipo, $detalleId
?>
<div id="<?= $detalleId ?>" class="ep-hist-record-detalle">

	<div class="ep-expediente-seccion-titulo">
		<?= ep_icon('file', 13) ?>
		<span>Datos del formulario</span>
	</div>

	<!-- 1. ACTIVACIONES & EPSON DAY -->
	<?php if ($tipo === 'activaciones' || $tipo === 'epson-day'): ?>
		<div class="ep-form-diligenciado-steps">

			<!-- Paso 1: Cobertura -->
			<?php if (isset($r['cobertura'])): ?>
				<div class="ep-form-step-box">
					<div class="ep-form-step-num">1</div>
					<div class="ep-form-step-content">
						<div class="ep-form-step-titulo">Paso 1: Cobertura</div>
						<div class="ep-form-grid-2" style="margin-top:8px;">
							<div class="ep-data-field">
								<span class="ep-data-field-lbl">Tiendas a Nivel Nacional</span>
								<strong class="ep-data-field-val"><?= (int) $r['cobertura']['nacional'] ?></strong>
							</div>
							<div class="ep-data-field">
								<span class="ep-data-field-lbl">Tiendas Coberturadas</span>
								<strong class="ep-data-field-val"><?= (int) $r['cobertura']['coberturadas'] ?></strong>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<!-- Paso 2: Embudo -->
			<?php if (isset($r['embudo'])): ?>
				<div class="ep-form-step-box">
					<div class="ep-form-step-num">2</div>
					<div class="ep-form-step-content">
						<div class="ep-form-step-titulo">Paso 2: Embudo de Clientes</div>
						<div class="ep-funnel-diligenciado" style="margin-top:8px;">
							<div class="ep-funnel-col">
								<span class="ep-funnel-lbl">Visitaron</span>
								<span class="ep-funnel-num"><?= (int) $r['embudo']['visitaron'] ?></span>
							</div>
							<div class="ep-funnel-arrow"><?= ep_icon('arrow-right', 14) ?></div>
							<div class="ep-funnel-col">
								<span class="ep-funnel-lbl">Interactuaron</span>
								<span class="ep-funnel-num"><?= (int) $r['embudo']['interactuaron'] ?></span>
							</div>
							<div class="ep-funnel-arrow"><?= ep_icon('arrow-right', 14) ?></div>
							<div class="ep-funnel-col exitoso">
								<span class="ep-funnel-lbl">Compraron</span>
								<span class="ep-funnel-num"><?= (int) $r['embudo']['compraron'] ?></span>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<!-- Paso 3: Modelos Activados -->
			<?php if (!empty($r['modelos'])): ?>
				<div class="ep-form-step-box">
					<div class="ep-form-step-num">3</div>
					<div class="ep-form-step-content">
						<div class="ep-form-step-titulo">Paso 3: Modelos Activados</div>
						<div class="ep-reg-tabla-wrap" style="margin-top:8px;">
							<table class="ep-reg-tabla">
								<thead>
									<tr>
										<th>Modelo EcoTank</th>
										<th style="text-align:right;">Cantidad</th>
									</tr>
								</thead>
								<tbody>
									<?php $totalUds = 0; foreach ($r['modelos'] as $m): $cant = (int) ($m['cantidad'] ?? $m['unidades'] ?? 0); $totalUds += $cant; ?>
										<tr>
											<td style="font-weight:600;color:var(--color-ink);"><?= htmlspecialchars($m['modelo']) ?></td>
											<td style="text-align:right;font-weight:700;color:var(--color-primary);"><?= $cant ?> uds</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
								<tfoot>
									<tr>
										<th>Total</th>
										<th style="text-align:right;font-weight:800;color:var(--color-primary);"><?= $totalUds ?> uds</th>
									</tr>
								</tfoot>
							</table>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<!-- Paso 4: Cumplimiento (Solo en activaciones) -->
			<?php if (isset($r['cumplimiento'])): ?>
				<div class="ep-form-step-box">
					<div class="ep-form-step-num">4</div>
					<div class="ep-form-step-content">
						<div class="ep-form-step-titulo">Paso 4: Cumplimiento de Activaciones</div>
						<div class="ep-form-grid-2" style="margin-top:8px;">
							<div class="ep-data-field">
								<span class="ep-data-field-lbl">Programadas</span>
								<strong class="ep-data-field-val"><?= (int) $r['cumplimiento']['programadas'] ?></strong>
							</div>
							<div class="ep-data-field">
								<span class="ep-data-field-lbl">Realizadas</span>
								<strong class="ep-data-field-val"><?= (int) $r['cumplimiento']['realizadas'] ?></strong>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>

		</div>
	<?php endif; ?>

	<!-- 2. CAPACITACIONES (Campos auténticos del formulario) -->
	<?php if ($tipo === 'capacitaciones'): $cap = $r['capacitacion'] ?? []; ?>
		<div class="ep-form-diligenciado-steps">
			<div class="ep-form-step-box">
				<div class="ep-form-step-num">1</div>
				<div class="ep-form-step-content">
					<div class="ep-form-step-titulo">Paso 1: Asistentes por Cargo</div>
					<div class="ep-form-grid-2" style="margin-top:8px;">
						<div class="ep-data-field">
							<span class="ep-data-field-lbl">Asistente de Jefe Tienda</span>
							<strong class="ep-data-field-val"><?= (int) ($cap['asistente_jefe'] ?? 1) ?></strong>
						</div>
						<div class="ep-data-field">
							<span class="ep-data-field-lbl">Jefe de Tienda</span>
							<strong class="ep-data-field-val"><?= (int) ($cap['jefe_tienda'] ?? 1) ?></strong>
						</div>
						<div class="ep-data-field">
							<span class="ep-data-field-lbl">Vendedores</span>
							<strong class="ep-data-field-val"><?= (int) ($cap['vendedores'] ?? 10) ?></strong>
						</div>
						<div class="ep-data-field">
							<span class="ep-data-field-lbl">Total Asistentes</span>
							<strong class="ep-data-field-val" style="color:var(--color-primary);"><?= (int) ($cap['total_asistentes'] ?? 12) ?></strong>
						</div>
					</div>
				</div>
			</div>

			<div class="ep-form-step-box">
				<div class="ep-form-step-num">2</div>
				<div class="ep-form-step-content">
					<div class="ep-form-step-titulo">Paso 2: Interacciones</div>
					<div class="ep-form-grid-2" style="margin-top:8px;">
						<div class="ep-data-field">
							<span class="ep-data-field-lbl">Interacciones Logradas</span>
							<strong class="ep-data-field-val"><?= (int) ($cap['interacciones'] ?? 8) ?></strong>
						</div>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- 3. COLOCACIÓN DE POP -->
	<?php if ($tipo === 'colocacion-pop' && !empty($r['pop_materiales'])): ?>
		<div class="ep-form-diligenciado-steps">
			<div class="ep-form-step-box">
				<div class="ep-form-step-num">1</div>
				<div class="ep-form-step-content">
					<div class="ep-form-step-titulo">Paso 1: POP Recibido</div>
					<div class="ep-reg-tabla-wrap" style="margin-top:8px;">
						<table class="ep-reg-tabla">
							<thead>
								<tr>
									<th>Material</th>
									<th style="text-align:center;">Bodega</th>
									<th style="text-align:center;">Canales</th>
									<th style="text-align:center;">Retail</th>
									<th style="text-align:center;">Disponible</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($r['pop_materiales'] as $p): ?>
									<tr>
										<td style="font-weight:600;"><?= htmlspecialchars($p['item'] ?? $p['material'] ?? 'Material') ?></td>
										<td style="text-align:center;"><?= (int) ($p['bodega'] ?? 0) ?></td>
										<td style="text-align:center;"><?= (int) ($p['canales'] ?? 0) ?></td>
										<td style="text-align:center;font-weight:600;"><?= (int) ($p['retail'] ?? 0) ?></td>
										<td style="text-align:center;font-weight:700;color:var(--color-primary);"><?= (int) ($p['disponible'] ?? 0) ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- 4. EXHIBICIONES -->
	<?php if ($tipo === 'exhibiciones' && isset($r['exhibiciones'])): $ex = $r['exhibiciones']; ?>
		<div class="ep-form-diligenciado-steps">
			<div class="ep-form-step-box">
				<div class="ep-form-step-num">1</div>
				<div class="ep-form-step-content">
					<div class="ep-form-step-titulo">Paso 1: Detalle de Exhibiciones</div>
					<div class="ep-form-grid-2" style="margin-top:8px;">
						<div class="ep-data-field">
							<span class="ep-data-field-lbl">Muebles</span>
							<strong class="ep-data-field-val"><?= (int) ($ex['muebles'] ?? 0) ?></strong>
						</div>
						<div class="ep-data-field">
							<span class="ep-data-field-lbl">Rumas</span>
							<strong class="ep-data-field-val"><?= (int) ($ex['rumas'] ?? 0) ?></strong>
						</div>
						<div class="ep-data-field">
							<span class="ep-data-field-lbl">Cabeceras</span>
							<strong class="ep-data-field-val"><?= (int) ($ex['cabeceras'] ?? 0) ?></strong>
						</div>
						<div class="ep-data-field">
							<span class="ep-data-field-lbl">Total Exhibiciones</span>
							<strong class="ep-data-field-val" style="color:var(--color-primary);"><?= (int) ($ex['total'] ?? 0) ?></strong>
						</div>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- 5. EVENTO O FERIAS -->
	<?php if ($tipo === 'evento-ferias' && isset($r['feria'])): $fe = $r['feria']; ?>
		<div class="ep-form-diligenciado-steps">
			<div class="ep-form-step-box">
				<div class="ep-form-step-num">1</div>
				<div class="ep-form-step-content">
					<div class="ep-form-step-titulo">Paso 1: Embudo de Clientes</div>
					<div class="ep-funnel-diligenciado" style="margin-top:8px;">
						<div class="ep-funnel-col">
							<span class="ep-funnel-lbl">Visitaron</span>
							<span class="ep-funnel-num"><?= (int) ($fe['visitaron'] ?? 0) ?></span>
						</div>
						<div class="ep-funnel-arrow"><?= ep_icon('arrow-right', 14) ?></div>
						<div class="ep-funnel-col">
							<span class="ep-funnel-lbl">Interactuaron</span>
							<span class="ep-funnel-num"><?= (int) ($fe['interactuaron'] ?? 0) ?></span>
						</div>
						<div class="ep-funnel-arrow"><?= ep_icon('arrow-right', 14) ?></div>
						<div class="ep-funnel-col exitoso">
							<span class="ep-funnel-lbl">Compraron</span>
							<span class="ep-funnel-num"><?= (int) ($fe['compraron'] ?? 0) ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- Comentarios del promotor -->
	<?php if (!empty($r['comentarios'])): ?>
		<div class="ep-expediente-seccion">
			<div class="ep-expediente-seccion-titulo">
				<?= ep_icon('file', 13) ?>
				<span>Comentarios</span>
			</div>
			<div style="font-size:12px;color:var(--color-ink);background:#F8FAFD;padding:8px 12px;border-radius:6px;border:1px solid #E5EBF5;">
				<?php foreach ((array)$r['comentarios'] as $com): ?>
					<p style="margin:2px 0;"><?= htmlspecialchars($com) ?></p>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- Evidencias fotográficas -->
	<?php if (!empty($r['fotos'])): ?>
		<div class="ep-expediente-seccion">
			<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
				<div class="ep-expediente-seccion-titulo" style="margin:0;">
					<?= ep_icon('camera', 13) ?>
					<span>Evidencias Fotográficas (<?= count($r['fotos']) ?>)</span>
				</div>
			</div>
			<div class="ep-reg-fotos-grid" style="margin-top:8px;">
				<?php foreach ($r['fotos'] as $idx => $f): ?>
					<div class="ep-reg-foto-card"
						data-foto-label="<?= htmlspecialchars($f['label'] ?? 'Foto') ?>"
						data-foto-tienda="<?= htmlspecialchars($r['punto_venta'] ?? '') ?>"
						data-foto-promotor="<?= htmlspecialchars($r['promotor'] ?? '') ?>"
						data-foto-hora="<?= htmlspecialchars($f['hora'] ?? '') ?>">
						<div class="ep-reg-foto-preview">
							<div class="ep-reg-foto-placeholder">
								<?= ep_icon('camera', 18) ?>
								<span style="font-size:10px;"><?= htmlspecialchars($f['label'] ?? 'Foto') ?></span>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

</div>

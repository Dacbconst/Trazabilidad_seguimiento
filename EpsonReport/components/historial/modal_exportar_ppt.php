<?php
// components/historial/modal_exportar_ppt.php
// Modal de exportación y selección de plantillas PowerPoint (.pptx) para el Administrador
?>
<div id="epModalExportarPPT" class="ep-modal-ppt hidden" role="dialog" aria-modal="true" aria-labelledby="epModalPptTitulo">
	<div class="ep-modal-ppt-backdrop" id="epModalPptBackdrop"></div>
	<div class="ep-modal-ppt-dialog">

		<!-- Encabezado del Modal -->
		<div class="ep-modal-ppt-head">
			<div style="display:flex;align-items:center;gap:10px;">
				<div class="ep-modal-ppt-icon">
					<?= ep_icon('presentation', 20) ?>
				</div>
				<div>
					<h3 id="epModalPptTitulo" style="margin:0;font-size:16px;font-weight:700;color:var(--color-ink);">
						Descargar Reporte en PowerPoint (.pptx)
					</h3>
					<p style="margin:2px 0 0;font-size:12px;color:var(--color-text-muted);">
						Generador de diapositivas corporativas de campo por día y promotor.
					</p>
				</div>
			</div>
			<button type="button" class="ep-modal-close-btn" id="epModalPptCerrar" aria-label="Cerrar modal">
				<?= ep_icon('close', 16) ?>
			</button>
		</div>

		<!-- Cuerpo del Modal: Mecánica de Selección y Previsualización -->
		<div class="ep-modal-ppt-body">
			
			<div class="ep-ppt-config-grid">
				
				<!-- Columna 1: Controles de Configuración -->
				<div class="ep-ppt-config-col">

					<!-- 1. Selección de Usuario / Promotor -->
					<div class="ep-ppt-form-group">
						<label class="ep-label-compact" for="epPptSelectUsuario">
							<?= ep_icon('users', 13) ?>
							<span>1. Promotor / Usuario:</span>
						</label>
						<select id="epPptSelectUsuario" class="ep-input-compact" style="width:100%;">
							<option value="all">Todos los usuarios (Consolidado general)</option>
							<?php foreach ($listaPromotores as $p): ?>
								<option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
							<?php endforeach; ?>
						</select>
						<span class="ep-field-hint-compact">Descarga las actividades del promotor elegido o de todo el equipo.</span>
					</div>

					<!-- 2. Selección de Día / Fecha -->
					<div class="ep-ppt-form-group">
						<div style="display:flex;align-items:center;justify-content:space-between;">
							<label class="ep-label-compact" for="epPptSelectFecha">
								<?= ep_icon('calendar', 13) ?>
								<span>2. Mes del reporte:</span>
							</label>
							<span class="ep-field-hint-compact">Incluye todos los registros del mes</span>
						</div>
						<div class="ep-ppt-date-row">
							<input type="month" id="epPptSelectFecha" class="ep-input-compact ep-ppt-input-date" value="<?= date('Y-m') ?>">
						</div>
					</div>

					<!-- 3. Selección de Plantilla PPT según la Actividad -->
					<div class="ep-ppt-form-group">
						<div style="display:flex;align-items:center;justify-content:space-between;">
							<label class="ep-label-compact">
								<?= ep_icon('layers', 13) ?>
								<span>3. Plantilla PPTX (Diferente por tipo de actividad):</span>
							</label>
							<span class="ep-ppt-tpl-counter-badge">7 Diseños Oficiales</span>
						</div>
						
						<div class="ep-ppt-templates-grid" id="epPptTemplatesList">
							<!-- Plantilla Activaciones -->
							<div class="ep-ppt-tpl-option selected" data-template="activaciones" tabindex="0" role="button">
								<input type="radio" name="epPptTemplate" value="activaciones" checked>
								<div class="ep-ppt-tpl-top">
									<span class="ep-ppt-tpl-badge ep-badge-activaciones">ACT</span>
									<span class="ep-ppt-tpl-tag">1 Diapositiva</span>
								</div>
								<div class="ep-ppt-tpl-info">
									<strong>Activaciones de Campo</strong>
									<span>Cobertura · Embudo · Evidencia</span>
								</div>
								<span class="ep-ppt-tpl-check"><?= ep_icon('check', 11) ?></span>
							</div>

							<!-- Plantilla Capacitaciones -->
							<div class="ep-ppt-tpl-option" data-template="capacitaciones" tabindex="0" role="button">
								<input type="radio" name="epPptTemplate" value="capacitaciones">
								<div class="ep-ppt-tpl-top">
									<span class="ep-ppt-tpl-badge ep-badge-capacitaciones">CAP</span>
									<span class="ep-ppt-tpl-tag">1 Diapositiva</span>
								</div>
								<div class="ep-ppt-tpl-info">
									<strong>Capacitaciones</strong>
									<span>Asistentes por cargo · Fotos aula</span>
								</div>
								<span class="ep-ppt-tpl-check"><?= ep_icon('check', 11) ?></span>
							</div>

							<!-- Plantilla Epson Day -->
							<div class="ep-ppt-tpl-option" data-template="epson-day" tabindex="0" role="button">
								<input type="radio" name="epPptTemplate" value="epson-day">
								<div class="ep-ppt-tpl-top">
									<span class="ep-ppt-tpl-badge ep-badge-epson-day">EPD</span>
									<span class="ep-ppt-tpl-tag">1 Diapositiva</span>
								</div>
								<div class="ep-ppt-tpl-info">
									<strong>Epson Day</strong>
									<span>Activación VIP · EcoTank</span>
								</div>
								<span class="ep-ppt-tpl-check"><?= ep_icon('check', 11) ?></span>
							</div>

							<!-- Plantilla Colocación de POP -->
							<div class="ep-ppt-tpl-option" data-template="colocacion-pop" tabindex="0" role="button">
								<input type="radio" name="epPptTemplate" value="colocacion-pop">
								<div class="ep-ppt-tpl-top">
									<span class="ep-ppt-tpl-badge ep-badge-colocacion-pop">POP</span>
									<span class="ep-ppt-tpl-tag">1 Diapositiva</span>
								</div>
								<div class="ep-ppt-tpl-info">
									<strong>Colocación de POP</strong>
									<span>Balance inventario entregado</span>
								</div>
								<span class="ep-ppt-tpl-check"><?= ep_icon('check', 11) ?></span>
							</div>

							<!-- Plantilla Exhibiciones -->
							<div class="ep-ppt-tpl-option" data-template="exhibiciones" tabindex="0" role="button">
								<input type="radio" name="epPptTemplate" value="exhibiciones">
								<div class="ep-ppt-tpl-top">
									<span class="ep-ppt-tpl-badge ep-badge-exhibiciones">EXH</span>
									<span class="ep-ppt-tpl-tag">1 Diapositiva</span>
								</div>
								<div class="ep-ppt-tpl-info">
									<strong>Exhibiciones</strong>
									<span>Muebles · Rumas · Cabeceras</span>
								</div>
								<span class="ep-ppt-tpl-check"><?= ep_icon('check', 11) ?></span>
							</div>

							<!-- Plantilla Evento o Ferias -->
							<div class="ep-ppt-tpl-option" data-template="evento-ferias" tabindex="0" role="button">
								<input type="radio" name="epPptTemplate" value="evento-ferias">
								<div class="ep-ppt-tpl-top">
									<span class="ep-ppt-tpl-badge ep-badge-evento-ferias">EVT</span>
									<span class="ep-ppt-tpl-tag">1 Diapositiva</span>
								</div>
								<div class="ep-ppt-tpl-info">
									<strong>Evento o Ferias</strong>
									<span>Demostraciones · Tráfico ferial</span>
								</div>
								<span class="ep-ppt-tpl-check"><?= ep_icon('check', 11) ?></span>
							</div>

							<!-- Consolidado Multislide (Ancho completo) -->
							<div class="ep-ppt-tpl-option ep-ppt-tpl-full" data-template="consolidado" tabindex="0" role="button">
								<input type="radio" name="epPptTemplate" value="consolidado">
								<div class="ep-ppt-tpl-top">
									<span class="ep-ppt-tpl-badge" style="background:#0B1863;color:#fff;">ALL</span>
									<span class="ep-ppt-tpl-tag" style="background:#EBF1FD;color:#0B1863;font-weight:700;">Multi-Slide Pack</span>
								</div>
								<div class="ep-ppt-tpl-info">
									<strong>Consolidado Diario Completo</strong>
									<span>Todas las actividades del día organizadas en un único archivo PowerPoint</span>
								</div>
								<span class="ep-ppt-tpl-check"><?= ep_icon('check', 11) ?></span>
							</div>
						</div>
					</div>

				</div>

				<!-- Columna 2: Previsualización Maqueta del Slide PowerPoint -->
				<div class="ep-ppt-preview-col">
					<div class="ep-ppt-preview-header">
						<span>Previsualización de Diapositiva</span>
						<span class="ep-ppt-aspect-tag">PowerPoint PPTX</span>
					</div>

					<!-- Marco de la Diapositiva -->
					<div class="ep-ppt-slide-canvas" id="epPptSlideCanvas">
						<!-- Barra superior corporativa de la diapositiva -->
						<div class="ep-slide-header-bar">
							<div class="ep-slide-brand">
								<strong>EPSON</strong>
								<span style="opacity:0.8;font-size:9px;">· LUCKY ECUADOR</span>
							</div>
							<div class="ep-slide-meta" id="epSlidePreviewFecha">24 OCTUBRE 2024</div>
						</div>

						<!-- Título de la diapositiva -->
						<div class="ep-slide-title-area">
							<div class="ep-slide-actividad-tag" id="epSlidePreviewTag">ACTIVACIONES DE CAMPO</div>
							<h4 class="ep-slide-punto-venta" id="epSlidePreviewTienda">Sukasa - Mall del Sol</h4>
							<div class="ep-slide-promotor" id="epSlidePreviewPromotor">Promotor: Carlos Mendoza</div>
						</div>

						<!-- Contenido dinámico del Slide según la plantilla seleccionada -->
						<div class="ep-slide-dynamic-content" id="epSlideDynamicContent">
							<!-- 1. ACTIVACIONES (DEFAULT) -->
							<div class="ep-slide-tpl-view ep-slide-view-activaciones">
								<div class="ep-slide-grid-2">
									<div class="ep-slide-box-metrics">
										<div class="ep-slide-metric-row">
											<span>Cobertura Nacional:</span>
											<strong>18 de 24 tiendas (75%)</strong>
										</div>
										<div class="ep-slide-funnel-mini">
											<div class="ep-slide-funnel-item">
												<small>Visitaron</small>
												<strong>120</strong>
											</div>
											<span>&rarr;</span>
											<div class="ep-slide-funnel-item">
												<small>Interactuaron</small>
												<strong>48</strong>
											</div>
											<span>&rarr;</span>
											<div class="ep-slide-funnel-item highlight">
												<small>Compraron</small>
												<strong>20</strong>
											</div>
										</div>
										<div class="ep-slide-modelos-mini">
											<span>Modelos:</span>
											<small>L3250 (10 uds) · L4260 (6 uds) · L5590 (4 uds)</small>
										</div>
									</div>
									<div class="ep-slide-box-photos">
										<div class="ep-slide-photo-slot">
											<?= ep_icon('camera', 16) ?>
											<span>Stand & Material POP</span>
										</div>
										<div class="ep-slide-photo-slot">
											<?= ep_icon('camera', 16) ?>
											<span>Interacción con Cliente</span>
										</div>
										<div class="ep-slide-photo-slot">
											<?= ep_icon('camera', 16) ?>
											<span>Venta Concluida</span>
										</div>
									</div>
								</div>
							</div>

							<!-- 2. CAPACITACIONES -->
							<div class="ep-slide-tpl-view ep-slide-view-capacitaciones hidden">
								<div class="ep-slide-grid-2">
									<div class="ep-slide-box-metrics">
										<div class="ep-slide-metric-row">
											<span>Asistentes Convocados:</span>
											<strong>16 personas</strong>
										</div>
										<div class="ep-slide-metric-sub">
											<small>Jefe Tienda: 1 · Asistente: 1 · Vendedores: 14</small>
										</div>
										<div class="ep-slide-metric-row" style="margin-top:6px;">
											<span>Interacciones Realizadas:</span>
											<strong>11 consultas resueltas</strong>
										</div>
										<div class="ep-slide-metric-row" style="margin-top:6px;">
											<span>Temas:</span>
											<small>Cabezal PrecisionCore vs Térmico y Costo por página EcoTank</small>
										</div>
									</div>
									<div class="ep-slide-box-photos">
										<div class="ep-slide-photo-slot">
											<?= ep_icon('camera', 16) ?>
											<span>Equipo Epson Explicado</span>
										</div>
										<div class="ep-slide-photo-slot">
											<?= ep_icon('camera', 16) ?>
											<span>Instrucción al Personal</span>
										</div>
									</div>
								</div>
							</div>

							<!-- 3. EPSON DAY -->
							<div class="ep-slide-tpl-view ep-slide-view-epson-day hidden">
								<div class="ep-slide-grid-2">
									<div class="ep-slide-box-metrics">
										<div class="ep-slide-metric-row">
											<span>Jornada Epson Day:</span>
											<strong>Impacto en Piso de Venta</strong>
										</div>
										<div class="ep-slide-funnel-mini">
											<div class="ep-slide-funnel-item"><small>Tráfico</small><strong>150</strong></div>
											<span>&rarr;</span>
											<div class="ep-slide-funnel-item"><small>Demos</small><strong>65</strong></div>
											<span>&rarr;</span>
											<div class="ep-slide-funnel-item highlight"><small>Ventas</small><strong>28</strong></div>
										</div>
									</div>
									<div class="ep-slide-box-photos">
										<div class="ep-slide-photo-slot"><?= ep_icon('camera', 16) ?><span>Zona de Experiencia</span></div>
										<div class="ep-slide-photo-slot"><?= ep_icon('camera', 16) ?><span>Demostración EcoTank</span></div>
									</div>
								</div>
							</div>

							<!-- 4. COLOCACIÓN DE POP -->
							<div class="ep-slide-tpl-view ep-slide-view-colocacion-pop hidden">
								<div class="ep-slide-grid-2">
									<div class="ep-slide-box-metrics">
										<div class="ep-slide-metric-row">
											<span>Balance Material POP:</span>
											<strong>Entregado a Retail</strong>
										</div>
										<table class="ep-slide-table-mini">
											<tr><th>Material</th><th>Entregado</th><th>Disp.</th></tr>
											<tr><td>Vibrin</td><td>10 uds</td><td>5 uds</td></tr>
											<tr><td>Hablador</td><td>8 uds</td><td>4 uds</td></tr>
											<tr><td>Rompe Tráfico</td><td>6 uds</td><td>2 uds</td></tr>
										</table>
									</div>
									<div class="ep-slide-box-photos">
										<div class="ep-slide-photo-slot"><?= ep_icon('camera', 16) ?><span>Material Instalado</span></div>
										<div class="ep-slide-photo-slot"><?= ep_icon('camera', 16) ?><span>Cabecera de Góndola</span></div>
									</div>
								</div>
							</div>

							<!-- 5. EXHIBICIONES -->
							<div class="ep-slide-tpl-view ep-slide-view-exhibiciones hidden">
								<div class="ep-slide-grid-2">
									<div class="ep-slide-box-metrics">
										<div class="ep-slide-metric-row">
											<span>Espacios Ganados:</span>
											<strong>Exhibiciones que Inspiran</strong>
										</div>
										<div style="font-size:10px;color:var(--color-ink);margin-top:4px;">
											<div>• Muebles dedicados: <strong>2</strong></div>
											<div>• Rumas de producto: <strong>4</strong></div>
											<div>• Cabeceras de pasillo: <strong>1</strong></div>
										</div>
									</div>
									<div class="ep-slide-box-photos">
										<div class="ep-slide-photo-slot"><?= ep_icon('camera', 16) ?><span>Panorámica de Mueble</span></div>
										<div class="ep-slide-photo-slot"><?= ep_icon('camera', 16) ?><span>Ruma EcoTank</span></div>
									</div>
								</div>
							</div>

							<!-- 6. EVENTO O FERIAS -->
							<div class="ep-slide-tpl-view ep-slide-view-evento-ferias hidden">
								<div class="ep-slide-grid-2">
									<div class="ep-slide-box-metrics">
										<div class="ep-slide-metric-row">
											<span>Stand Ferial:</span>
											<strong>ExpoTecnología Ecuador</strong>
										</div>
										<div class="ep-slide-funnel-mini">
											<div class="ep-slide-funnel-item"><small>Visitantes</small><strong>320</strong></div>
											<span>&rarr;</span>
											<div class="ep-slide-funnel-item highlight"><small>Cotizaciones</small><strong>85</strong></div>
										</div>
									</div>
									<div class="ep-slide-box-photos">
										<div class="ep-slide-photo-slot"><?= ep_icon('camera', 16) ?><span>Stand Epson</span></div>
										<div class="ep-slide-photo-slot"><?= ep_icon('camera', 16) ?><span>Atención al Público</span></div>
									</div>
								</div>
							</div>

							<!-- 7. CONSOLIDADO MULTI-SLIDE -->
							<div class="ep-slide-tpl-view ep-slide-view-consolidado hidden">
								<div style="text-align:center;padding:18px 10px;">
									<div style="font-size:24px;margin-bottom:4px;">📑</div>
									<strong style="font-size:12px;color:var(--color-primary);">Paquete Completo Multi-Diapositiva</strong>
									<p style="font-size:10px;color:var(--color-text-muted);margin:4px auto 0;max-width:260px;">
										Compila todas las actividades reportadas en la fecha usando la plantilla oficial correspondiente para cada una.
									</p>
								</div>
							</div>
						</div>

						<!-- Pie de la diapositiva -->
						<div class="ep-slide-footer-bar">
							<span>Epson Ecuador · Trazabilidad y Control de Campo</span>
							<span>Diapositiva 1 / 1</span>
						</div>
					</div>

					<div class="ep-ppt-preview-note">
						<?= ep_icon('eye', 12) ?>
						<span>Vista previa generada dinámicamente según la plantilla de actividad seleccionada.</span>
					</div>
				</div>

			</div>

			<!-- Estado de descarga simulada / feedback -->
			<div id="epPptDescargaStatus" class="ep-ppt-status-box hidden">
				<div class="ep-ppt-spinner"></div>
				<div class="ep-ppt-status-text">
					<strong id="epPptStatusTitulo">Compilando presentación PowerPoint...</strong>
					<span id="epPptStatusSub">Procesando diapositivas con la plantilla oficial Epson...</span>
				</div>
			</div>

		</div>

		<!-- Pie del Modal: Metadatos y Acciones Ejecutivas -->
		<div class="ep-modal-ppt-foot">
			<div class="ep-ppt-foot-meta-box">
				<span class="ep-ppt-foot-pill" id="epPptFootMeta">1 Slide · Activaciones de Campo · Carlos Mendoza · 24 Oct 2024</span>
			</div>
			<div style="display:flex;align-items:center;gap:8px;">
				<button type="button" class="ep-btn-subtle-compact" id="epModalPptCancelar">
					Cancelar
				</button>
				<button type="button" class="ep-btn-ppt-cta" id="epBtnEjecutarDescargaPPT">
					<span class="ep-btn-ppt-cta-icon"><?= ep_icon('download', 14) ?></span>
					<span id="epBtnDescargaPptTexto">Descargar Presentación (.pptx)</span>
					<span class="ep-btn-ppt-cta-format">PPTX</span>
				</button>
			</div>
		</div>

	</div>
</div>

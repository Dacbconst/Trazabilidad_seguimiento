<?php
// Reportes mensuales (solo admin): lista de reportes guardados + asistente para armar uno nuevo.
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../includes/reportes_datos.php';
require_once __DIR__.'/../../includes/actividades_datos.php';

if (!ep_es_gestor()) {
	echo '<main class="ep-content"><p>No tienes permiso para ver esta sección.</p></main>';
	return;
}
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$reportes = ep_reportes_listar();
$actividadesActivas = ep_actividades_activas();
// Activaciones ya no se arma a mano: su reporte lo genera el Calendario de Activaciones al cerrarse.
$actividadesManual = array_values(array_filter($actividadesActivas, fn($a) => ($a['plantilla'] ?? '') !== 'activaciones'));
$subtitulosTipo = [
	'activaciones'   => 'Cobertura, embudo y modelos',
	'capacitaciones' => 'Asistentes por cargo en tienda',
	'epson-day'      => 'Jornada especial de impulso',
	'evento-ferias'  => 'Stands y ferias tecnológicas',
	'exhibiciones'   => 'Auditoría de espacios físicos',
	'colocacion-pop' => 'Entrega de material POP',
	'informe-fotografico' => 'Fotos por punto de venta',
	'competencia'    => 'Hallazgos con su descripción',
];
$nombresTipo = ['activaciones' => 'Activaciones', 'capacitaciones' => 'Capacitaciones', 'colocacion-pop' => 'Colocación de POP', 'epson-day' => 'Epson Day', 'exhibiciones' => 'Exhibiciones', 'evento-ferias' => 'Evento o Ferias', 'informe-fotografico' => 'Informe Fotográfico', 'competencia' => 'Competencia'];
$meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
?>
<main class="ep-content ep-rp" id="epRp">

	<header class="ep-rp-head">
		<div>
			<h1>Reportes mensuales <span class="ep-vivo" id="epRpVivo" title="Se actualiza sola cada pocos segundos"><i></i><span>En vivo</span></span></h1>
			<p id="epRpResumen"><?= count($reportes) ?> <?= count($reportes) === 1 ? 'reporte guardado' : 'reportes guardados' ?> · listos para descargar en PowerPoint</p>
		</div>
		<button type="button" class="ep-rp-nuevo" id="epRpNuevo"><?= ep_icon('plus', 16) ?> <span>Nuevo reporte</span></button>
	</header>

	<?php if (empty($reportes)): ?>
		<?= ep_estado_vacio('presentation', 'Todavía no hay reportes', 'Crea el primero con "Nuevo reporte".') ?>
	<?php else: ?>
	<section class="ep-fl-barra">
		<label class="ep-fl-buscar">
			<?= ep_icon('search', 18) ?>
			<input type="search" id="epRpBuscar" placeholder="Buscar reporte por nombre, actividad o quien lo creó…" autocomplete="off">
		</label>
		<div class="ep-fl-combo" id="epRpComboAct">
			<button type="button" class="ep-fl-combo-btn" aria-haspopup="listbox" aria-expanded="false">Actividad <span class="ep-fl-combo-valor">Todas</span><?= ep_icon('chevron', 16) ?></button>
			<div class="ep-fl-combo-panel hidden">
				<label class="ep-fl-combo-buscar"><?= ep_icon('search', 16) ?><input type="search" placeholder="Buscar actividad…" autocomplete="off"></label>
				<div class="ep-fl-combo-lista" role="listbox"></div>
			</div>
		</div>
		<div class="ep-fl-combo" id="epRpComboMes">
			<button type="button" class="ep-fl-combo-btn" aria-haspopup="listbox" aria-expanded="false"><?= ep_icon('calendar', 16) ?> Mes <span class="ep-fl-combo-valor">Todos</span><?= ep_icon('chevron', 16) ?></button>
			<div class="ep-fl-combo-panel hidden">
				<div class="ep-fl-combo-lista" role="listbox"></div>
			</div>
		</div>
	</section>

	<section class="ep-rp-lista" id="epRpLista">
		<?php require __DIR__.'/filas.php'; ?>
	</section>
	<?php endif; ?>

	<!-- Asistente: mismo diseño que la ventana de descarga PPT (icono, título, cuerpo, pie con acciones) -->
	<div class="ep-modal-ppt hidden" id="epRpModal" role="dialog" aria-modal="true" aria-labelledby="epRpTitulo">
		<div class="ep-modal-ppt-backdrop" id="epRpFondo"></div>
		<div class="ep-modal-ppt-dialog ep-rp-dialog">

			<div class="ep-modal-ppt-head">
				<div style="display:flex;align-items:center;gap:10px;">
					<div class="ep-modal-ppt-icon"><?= ep_icon('presentation', 20) ?></div>
					<div>
						<h3 id="epRpTitulo" style="margin:0;font-size:16px;font-weight:700;color:var(--color-ink);">Nuevo reporte mensual</h3>
						<p style="margin:2px 0 0;font-size:12px;color:var(--color-text-muted);">Elige los registros del mes que entran en la presentación.</p>
					</div>
				</div>
				<button type="button" class="ep-modal-close-btn" id="epRpCerrar" aria-label="Cerrar"><?= ep_icon('close', 16) ?></button>
			</div>

			<div class="ep-modal-ppt-body">
				<!-- VISTA 1: Selector de actividades con buscador y grilla de 6 botones en 2 columnas -->
				<div class="ep-rp-paso" id="epRpPasoActividades" data-paso="1">
					<div class="ep-rp-act-section">
						<div class="ep-rp-act-header">
							<label class="ep-label-compact"><?= ep_icon('layers', 13) ?> <span>Tipo de actividad</span></label>
							<span class="ep-rp-act-count" id="epRpActCount"><?= count($actividadesManual) ?> activas</span>
						</div>

						<!-- Buscador en la parte superior -->
						<div class="ep-rp-search-box">
							<span class="ep-rp-search-icon"><?= ep_icon('search', 15) ?></span>
							<input type="text" id="epRpBuscarActividad" class="ep-rp-search-input" placeholder="Buscar actividad..." autocomplete="off">
							<button type="button" id="epRpBuscarLimpiar" class="ep-rp-search-clear hidden" aria-label="Limpiar búsqueda">
								<?= ep_icon('close', 13) ?>
							</button>
						</div>

						<!-- Grilla de actividades: 6 botones en 2 columnas (scrolleable si aparecen más) -->
						<div class="ep-rp-act-grid" id="epRpGridActividades" role="radiogroup" aria-label="Tipo de actividad">
							<?php foreach ($actividadesManual as $idx => $act):
								$plantilla = $act['plantilla'] ?? 'generico';
								$icono = ep_icono_tipo($plantilla);
								$sub = $subtitulosTipo[$plantilla] ?? 'Plantilla oficial de campo';
								$esSeleccionado = ($idx === 0);
							?>
								<button type="button" 
									class="ep-rp-act-card<?= $esSeleccionado ? ' selected' : '' ?>" 
									data-tipo="<?= $h($plantilla) ?>" 
									data-id="<?= (int) $act['id'] ?>"
									data-label="<?= $h($act['label']) ?>"
									data-sub="<?= $h($sub) ?>"
									role="radio"
									aria-checked="<?= $esSeleccionado ? 'true' : 'false' ?>">
									<div class="ep-rp-act-icon">
										<?= ep_icon($icono, 18) ?>
									</div>
									<div class="ep-rp-act-info">
										<div class="ep-rp-act-title-row">
											<strong class="ep-rp-act-title"><?= $h($act['label']) ?></strong>
											<?php if (!empty($act['badge'])): ?>
												<span class="ep-rp-act-badge"><?= $h($act['badge']) ?></span>
											<?php endif; ?>
										</div>
										<span class="ep-rp-act-sub"><?= $h($sub) ?></span>
									</div>
									<div class="ep-rp-act-radio">
										<?= ep_icon('check', 11) ?>
									</div>
								</button>
							<?php endforeach; ?>
						</div>

						<!-- Mensaje cuando la búsqueda no coincide -->
						<div class="ep-rp-act-vacio hidden" id="epRpActVacio">
							<?= ep_icon('search', 20) ?>
							<span>No se encontraron actividades para esa búsqueda.</span>
						</div>

						<input type="hidden" id="epRpTipo" value="<?= $h($actividadesManual[0]['plantilla'] ?? '') ?>" data-label="<?= $h($actividadesManual[0]['label'] ?? '') ?>">
					</div>
				</div>

				<!-- VISTA 2: Mecánica de Activaciones (2 columnas: dropzone + KPIs + seleccionados / filtros + tabla dual) -->
				<?php require_once __DIR__.'/mecanica_activaciones.php'; ?>

				<!-- VISTA 3: Mecánica de Capacitaciones (Seleccionados + filtros + tabla dual con previsualización) -->
				<?php require_once __DIR__.'/mecanica_capacitaciones.php'; ?>

				<!-- VISTA 4: nombre, mes y resumen antes de guardar -->
				<?php require_once __DIR__.'/paso_final.php'; ?>
			</div>

			<div class="ep-modal-ppt-foot ep-rp-foot">
				<!-- Botón Atrás a la izquierda absoluta -->
				<button type="button" class="ep-rp-btn-atras hidden" id="epRpAtras">
					<?= ep_icon('arrow-left', 14) ?>
					<span>Atrás</span>
				</button>
				<div class="ep-rp-foot-info hidden" id="epRpFootInfo">
				
				</div>
				<div class="ep-rp-foot-actions">
					<button type="button" class="ep-btn-subtle-compact ep-rp-btn-cancelar" id="epRpCancelarModal">
						Cancelar
					</button>
					<button type="button" class="ep-btn-ppt-cta ep-rp-btn-sig" id="epRpSiguiente">
						<span id="epRpSigTxt">Siguiente</span>
						<?= ep_icon('arrow-right', 14) ?>
					</button>
					<button type="button" class="ep-btn-ppt-cta ep-act-btn-guardar hidden" id="epActBtnGuardar">
						<span>Guardar Reporte</span>
						<?= ep_icon('arrow-right', 14) ?>
					</button>
				</div>
			</div>

		</div>
	</div>

	<!-- Modal Lightbox compartido de previsualización ampliada de la Diapositiva 1 oficial -->
	<div class="ep-act-lightbox hidden" id="epActLightbox" role="dialog" aria-modal="true">
		<div class="ep-act-lightbox-backdrop" id="epActLightboxFondo"></div>
		<div class="ep-act-lightbox-card">
			<div class="ep-act-lightbox-head">
				<div class="ep-act-lightbox-head-info">
					<div class="ep-act-lightbox-badge-row">
						<strong class="ep-act-lightbox-code" id="epLbCodigo">REG-000</strong>
						<span class="ep-act-lightbox-status" id="epLbEstado">Activo</span>
					</div>
					<span class="ep-act-lightbox-desc" id="epLbDesc">Detalle del registro</span>
				</div>
				<button type="button" class="ep-modal-close-btn" id="epLbCerrar" aria-label="Cerrar"><?= ep_icon('close', 16) ?></button>
			</div>
			<div class="ep-act-lightbox-body" id="epLbBody">
				<!-- Se inyecta dinámicamente la primera diapositiva oficial del PPTX del registro -->
			</div>
			<div class="ep-act-lightbox-foot">
				<div class="ep-act-lightbox-meta">
					<span class="ep-act-lightbox-pdv"><?= ep_icon('store', 13) ?> <span id="epLbPdv">Punto de venta</span></span>
					<span class="ep-act-lightbox-hora"><?= ep_icon('clock', 13) ?> <span id="epLbFecha">Fecha y hora</span></span>
				</div>
				<div style="display:flex;align-items:center;gap:8px;">
					<button type="button" class="ep-btn-subtle-compact" id="epLbCerrarBtn">Cerrar</button>
				</div>
			</div>
		</div>
	</div>
</main>

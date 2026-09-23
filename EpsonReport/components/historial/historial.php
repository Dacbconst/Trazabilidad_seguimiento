<?php
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../includes/fotos_datos.php';
require_once __DIR__.'/../../includes/registros_datos.php';

$todosRegistros = ep_registros_datos();
if (empty($todosRegistros)) {
	if (function_exists('ep_registros_datos_semilla')) {
		$todosRegistros = ep_registros_datos_semilla();
	} else {
		$pathJson = dirname(dirname(__DIR__)) . '/data/registros_guardados.json';
		if (file_exists($pathJson)) {
			$todosRegistros = json_decode(file_get_contents($pathJson), true) ?: [];
		}
	}
}

// Agrupación por día
$gruposPorDia = [];
foreach ($todosRegistros as $r) {
	$dia = $r['grupo_dia'] ?? 'Registros recientes';
	if (!isset($gruposPorDia[$dia])) {
		$gruposPorDia[$dia] = [
			'titulo'    => $dia,
			'registros' => [],
		];
	}
	$gruposPorDia[$dia]['registros'][] = $r;
}

// Estadísticas para pills de actividades
$conteoActividades = [
	'all'             => count($todosRegistros),
	'activaciones'    => 0,
	'capacitaciones'  => 0,
	'colocacion-pop'  => 0,
	'epson-day'       => 0,
	'exhibiciones'    => 0,
	'evento-ferias'   => 0,
];
foreach ($todosRegistros as $r) {
	$t = $r['tipo'] ?? 'activaciones';
	if (isset($conteoActividades[$t])) {
		$conteoActividades[$t]++;
	}
}

$actividadesFiltro = [
	['id' => 'all',            'label' => 'Todas las actividades', 'count' => $conteoActividades['all']],
	['id' => 'activaciones',   'label' => 'Activaciones',          'count' => $conteoActividades['activaciones']],
	['id' => 'capacitaciones', 'label' => 'Capacitaciones',        'count' => $conteoActividades['capacitaciones']],
	['id' => 'colocacion-pop', 'label' => 'Colocación de POP',     'count' => $conteoActividades['colocacion-pop']],
	['id' => 'epson-day',      'label' => 'Epson Day',             'count' => $conteoActividades['epson-day']],
	['id' => 'exhibiciones',   'label' => 'Exhibiciones',          'count' => $conteoActividades['exhibiciones']],
	['id' => 'evento-ferias',  'label' => 'Evento o Ferias',       'count' => $conteoActividades['evento-ferias']],
];

// Estadísticas globales de cabecera
$totalRegistros = count($todosRegistros);
$tiendasUnicas = count(array_unique(array_column($todosRegistros, 'punto_venta')));
$promotoresUnicos = count(array_unique(array_column($todosRegistros, 'promotor')));
$uid = 0;
?>
<main class="ep-content ep-registros-main" style="display:flex;flex-direction:column;gap:24px;">

	<!-- Encabezado con información del módulo y estadísticas rápidas -->
	<header class="ep-registros-header">
		<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px;">
			<div>
				<div class="ep-eyebrow" style="color:var(--color-primary);font-weight:700;display:flex;align-items:center;gap:6px;">
					<?= ep_icon('clock', 14) ?>
					EXPEDIENTES DE CAMPO & AUDITORÍA EN TIEMPO REAL
				</div>
				<h1 style="font-size:24px;margin-top:4px;color:var(--color-ink);">Registros de Actividades</h1>
				<p style="font-size:13px;color:var(--color-text-muted);margin:4px 0 0;max-width:700px;">
					Consolidado de formularios recolectados en puntos de venta para Activaciones, Capacitaciones, Epson Day, Material POP y Exhibiciones.
				</p>
			</div>

			<!-- Botones de rango temporal rápido -->
			<div class="ep-hist-rango" id="epRangoBotones">
				<button type="button" class="ep-hist-rango-btn ep-hist-rango-activo" data-rango="all">Todo</button>
				<button type="button" class="ep-hist-rango-btn" data-rango="hoy">Hoy</button>
				<button type="button" class="ep-hist-rango-btn" data-rango="ayer">Ayer</button>
				<button type="button" class="ep-hist-rango-btn" data-rango="7dias">Últimos 7 días</button>
			</div>
		</div>

		<!-- Tira de métricas ejecutivas de campo -->
		<div class="ep-registros-stats-strip">
			<div class="ep-reg-stat-card">
				<div class="ep-reg-stat-icon" style="background:#EBF1FD;color:var(--color-primary);"><?= ep_icon('file', 18) ?></div>
				<div class="ep-reg-stat-info">
					<span class="ep-reg-stat-val" id="epStatTotalRegistros"><?= $totalRegistros ?></span>
					<span class="ep-reg-stat-label">Formularios registrados</span>
				</div>
			</div>
			<div class="ep-reg-stat-card">
				<div class="ep-reg-stat-icon" style="background:#E7F7ED;color:var(--color-success);"><?= ep_icon('store', 18) ?></div>
				<div class="ep-reg-stat-info">
					<span class="ep-reg-stat-val"><?= $tiendasUnicas ?></span>
					<span class="ep-reg-stat-label">Puntos de venta auditados</span>
				</div>
			</div>
			<div class="ep-reg-stat-card">
				<div class="ep-reg-stat-icon" style="background:#F2EDFE;color:#6C3CE9;"><?= ep_icon('users', 18) ?></div>
				<div class="ep-reg-stat-info">
					<span class="ep-reg-stat-val"><?= $promotoresUnicos ?></span>
					<span class="ep-reg-stat-label">Promotores activos</span>
				</div>
			</div>
			<div class="ep-reg-stat-card">
				<div class="ep-reg-stat-icon" style="background:#FFF3D6;color:#A06C00;"><?= ep_icon('camera', 18) ?></div>
				<div class="ep-reg-stat-info">
					<span class="ep-reg-stat-val">100%</span>
					<span class="ep-reg-stat-label">Evidencias fotográficas válidas</span>
				</div>
			</div>
		</div>
	</header>

	<!-- Barra de herramientas: Filtros por píldora, buscador en vivo y estado -->
	<div class="ep-registros-toolbar ep-card" style="padding:16px 20px;display:flex;flex-direction:column;gap:14px;">
		<!-- Píldoras horizontales de tipos de actividad -->
		<div class="ep-registros-pills" id="epPillsActividades">
			<?php foreach ($actividadesFiltro as $i => $af): ?>
				<button type="button" class="ep-reg-pill<?= $i === 0 ? ' selected' : '' ?>" data-tipo="<?= htmlspecialchars($af['id']) ?>">
					<span class="ep-reg-pill-nombre"><?= htmlspecialchars($af['label']) ?></span>
					<span class="ep-reg-pill-count"><?= (int) $af['count'] ?></span>
				</button>
			<?php endforeach; ?>
		</div>

		<!-- Fila de búsqueda y controles -->
		<div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;">
			<div class="ep-search-wrap" style="flex:1 1 300px;max-width:540px;">
				<?= ep_icon('search', 16) ?>
				<input class="ep-input" type="text" id="epBuscarRegistro" placeholder="Buscar por punto de venta, cadena, promotor, ciudad o modelo..." autocomplete="off">
			</div>

			<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
				<div style="display:flex;align-items:center;gap:6px;">
					<label for="epFiltroEstado" style="font-size:12px;font-weight:600;color:var(--color-text-muted);">Estado:</label>
					<select id="epFiltroEstado" class="ep-input" style="height:38px;padding:0 28px 0 10px;font-size:13px;width:auto;">
						<option value="all">Todos los estados</option>
						<option value="Aprobado">Aprobados</option>
						<option value="En revisión">En revisión</option>
					</select>
				</div>

				<button type="button" id="ep-hist-expandir-todos" class="ep-hist-expandir-todos" data-estado="cerrar" style="height:38px;padding:0 12px;border:1px solid var(--color-border);border-radius:8px;background:var(--color-surface);">
					<?= ep_icon('chevron-up', 15) ?>
					<span id="epExpandirTodosTexto">Plegar todos</span>
				</button>
			</div>
		</div>

		<!-- Resumen de resultados filtrados -->
		<div style="display:flex;align-items:center;justify-content:space-between;font-size:12px;color:var(--color-text-muted);border-top:1px solid var(--color-border);padding-top:10px;">
			<span id="ep-hist-resumen">Mostrando <?= $totalRegistros ?> formularios recolectados</span>
			<button type="button" id="epLimpiarFiltros" class="hidden" style="border:none;background:transparent;color:var(--color-primary);font-weight:600;cursor:pointer;font-size:12px;">
				Limpiar filtros
			</button>
		</div>
	</div>

	<!-- Listado de registros agrupados por fecha -->
	<div id="ep-hist-grupos" style="display:flex;flex-direction:column;gap:20px;">
		<?php foreach ($gruposPorDia as $g): ?>
			<section class="ep-hist-day">
				<div class="ep-hist-day-header">
					<div style="display:flex;align-items:center;gap:10px;">
						<span class="ep-hist-day-dot"></span>
						<h2 style="font-size:15px;font-weight:700;color:var(--color-ink);margin:0;"><?= htmlspecialchars($g['titulo']) ?></h2>
						<span class="ep-hist-day-count"><?= count($g['registros']) ?> formularios</span>
					</div>
					<span style="font-size:12px;color:var(--color-text-muted);font-weight:600;">Epson Ecuador · Lucky</span>
				</div>

				<div class="ep-hist-day-list" style="display:flex;flex-direction:column;gap:16px;margin-top:16px;">
					<?php foreach ($g['registros'] as $r): $uid++; $detalleId = 'ep-reg-det-'.$uid;
						$tipo = $r['tipo'] ?? 'activaciones';
						$busqTexto = strtolower(($r['punto_venta'] ?? '').' '.($r['cadena'] ?? '').' '.($r['ciudad'] ?? '').' '.($r['promotor'] ?? '').' '.($r['actividad_label'] ?? '').' '.($r['actividad_badge'] ?? ''));
						if (!empty($r['modelos'])) {
							foreach ($r['modelos'] as $m) { $busqTexto .= ' '.strtolower($m['modelo'] ?? ''); }
						}
					?>
						<article class="ep-hist-record ep-hist-record-abierto ep-expediente-card"
							data-tipo="<?= htmlspecialchars($tipo) ?>"
							data-estado="<?= htmlspecialchars($r['estado'] ?? 'Aprobado') ?>"
							data-fecha="<?= htmlspecialchars($r['fecha_iso'] ?? '') ?>"
							data-busqueda="<?= htmlspecialchars($busqTexto) ?>">

							<!-- Cabecera Oficial del Expediente -->
							<div class="ep-hist-record-cabecera">
								<!-- Lado izquierdo: Código, Actividad, Tienda, Promotor -->
								<div style="display:flex;align-items:flex-start;gap:14px;min-width:0;flex:1 1 450px;">
									<div class="ep-expediente-hora-badge">
										<span class="ep-expediente-hora"><?= htmlspecialchars($r['hora'] ?? '00:00') ?></span>
										<small class="ep-expediente-duracion"><?= htmlspecialchars($r['duracion'] ?? '1h') ?></small>
									</div>

									<div style="min-width:0;flex-grow:1;">
										<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
											<!-- Badge institucional por actividad -->
											<span class="ep-activity-type-badge ep-badge-<?= htmlspecialchars($tipo) ?>">
												<?= htmlspecialchars($r['actividad_label'] ?? 'Actividad') ?>
											</span>
											<!-- Código oficial de auditoría -->
											<span class="ep-hist-badge ep-expediente-id-badge"><?= htmlspecialchars($r['id'] ?? '') ?></span>

											<?php if (!empty($r['actividad_badge'])): ?>
												<span class="ep-hist-badge"><?= htmlspecialchars($r['actividad_badge']) ?></span>
											<?php endif; ?>

											<span class="ep-hist-badge <?= ($r['estado_tipo'] ?? 'ok') === 'ok' ? 'ep-hist-badge-ok' : 'ep-hist-badge-alerta' ?>">
												<?= ep_icon('check', 12) ?>
												<?= htmlspecialchars($r['estado'] ?? 'Aprobado') ?>
											</span>
										</div>

										<h3 style="font-size:16px;font-weight:700;color:var(--color-ink);margin:6px 0 3px;">
											<?= htmlspecialchars($r['punto_venta'] ?? 'Punto de Venta') ?>
										</h3>

										<div class="ep-reg-meta-sub">
											<span style="display:flex;align-items:center;gap:4px;font-weight:600;color:var(--color-text);">
												<?= ep_icon('store', 13) ?>
												<?= htmlspecialchars($r['ciudad'] ?? 'Ecuador') ?>
												<?php if (!empty($r['canal'])): ?>
													<span class="ep-canal-pill"><?= htmlspecialchars($r['canal']) ?></span>
												<?php endif; ?>
											</span>
											<span>·</span>
											<span style="display:flex;align-items:center;gap:5px;">
												<span class="ep-reg-avatar-mini"><?= htmlspecialchars($r['promotor_avatar'] ?? 'PR') ?></span>
												<strong><?= htmlspecialchars($r['promotor'] ?? 'Promotor') ?></strong>
											</span>
										</div>
									</div>
								</div>

								<!-- Lado derecho: Métricas primarias y botón expandir -->
								<div style="display:flex;align-items:center;gap:12px;flex-shrink:0;flex-wrap:wrap;">
									<div class="ep-reg-kpis-strip">
										<?php if (isset($r['cobertura'])): ?>
											<div class="ep-reg-kpi-chip destacado">
												<span class="ep-reg-kpi-lbl">Cobertura</span>
												<strong class="ep-reg-kpi-val"><?= $r['cobertura']['pct'] ?>% (<?= $r['cobertura']['coberturadas'] ?>/<?= $r['cobertura']['nacional'] ?>)</strong>
											</div>
										<?php endif; ?>

										<?php if (isset($r['embudo'])): ?>
											<div class="ep-reg-kpi-chip">
												<span class="ep-reg-kpi-lbl">Embudo Clientes</span>
												<strong class="ep-reg-kpi-val"><?= $r['embudo']['visitaron'] ?> → <?= $r['embudo']['interactuaron'] ?> → <?= $r['embudo']['compraron'] ?></strong>
											</div>
										<?php endif; ?>

										<?php if (isset($r['capacitacion'])): ?>
											<div class="ep-reg-kpi-chip destacado">
												<span class="ep-reg-kpi-lbl">Aprobados</span>
												<strong class="ep-reg-kpi-val"><?= $r['capacitacion']['pct_aprobacion'] ?>% (<?= $r['capacitacion']['aprobados'] ?>/<?= $r['capacitacion']['asistentes'] ?>)</strong>
											</div>
										<?php endif; ?>

										<?php if (isset($r['pop_materiales'])): ?>
											<div class="ep-reg-kpi-chip destacado">
												<span class="ep-reg-kpi-lbl">Material POP</span>
												<strong class="ep-reg-kpi-val"><?= count($r['pop_materiales']) ?> ítems auditados</strong>
											</div>
										<?php endif; ?>

										<?php if (isset($r['exhibiciones'])): ?>
											<div class="ep-reg-kpi-chip destacado">
												<span class="ep-reg-kpi-lbl">Espacios</span>
												<strong class="ep-reg-kpi-val"><?= $r['exhibiciones']['total'] ?> exhibiciones</strong>
											</div>
										<?php endif; ?>

										<?php if (isset($r['feria'])): ?>
											<div class="ep-reg-kpi-chip destacado">
												<span class="ep-reg-kpi-lbl">Afluencia Stand</span>
												<strong class="ep-reg-kpi-val"><?= $r['feria']['visitaron'] ?> visitantes (<?= $r['feria']['compraron'] ?> ventas)</strong>
											</div>
										<?php endif; ?>
									</div>

									<button type="button" class="ep-hist-toggle-btn"
										aria-expanded="true"
										aria-controls="<?= $detalleId ?>"
										title="Alternar detalle completo del formulario">
										<span class="ep-hist-toggle-label">Ver formulario</span>
										<?= ep_icon('chevron', 16) ?>
									</button>
								</div>
							</div>

							<!-- Detalle Completo: EL FORMULARIO DILIGENCIADO PASO A PASO -->
							<div id="<?= $detalleId ?>" class="ep-hist-record-detalle">

								<div class="ep-expediente-seccion-titulo">
									<?= ep_icon('file', 14) ?>
									<span>DATOS RECOLECTADOS EN EL FORMULARIO OFICIAL</span>
								</div>

								<!-- 1. ACTIVACIONES / EPSON DAY: Cobertura, Embudo, Modelos, Cumplimiento -->
								<?php if ($tipo === 'activaciones' || $tipo === 'epson-day'): ?>
									<div class="ep-form-diligenciado-steps">

										<!-- Paso 1: Cobertura -->
										<?php if (isset($r['cobertura'])): ?>
											<div class="ep-form-step-box">
												<div class="ep-form-step-num">1</div>
												<div class="ep-form-step-content">
													<div class="ep-form-step-titulo">Paso 1: Cobertura de Tiendas</div>
													<div class="ep-form-grid-2" style="margin-top:10px;">
														<div class="ep-data-field">
															<span class="ep-data-field-lbl">Tiendas a Nivel Nacional</span>
															<strong class="ep-data-field-val"><?= (int) $r['cobertura']['nacional'] ?></strong>
														</div>
														<div class="ep-data-field">
															<span class="ep-data-field-lbl">Tiendas Coberturadas</span>
															<strong class="ep-data-field-val"><?= (int) $r['cobertura']['coberturadas'] ?></strong>
														</div>
													</div>
													<div class="ep-progress-bar-wrap">
														<div class="ep-progress-bar-fill" style="width:<?= min(100, $r['cobertura']['pct']) ?>%;"></div>
													</div>
													<span class="ep-progress-label">Logrado: <strong><?= $r['cobertura']['pct'] ?>% de cobertura nacional</strong></span>
												</div>
											</div>
										<?php endif; ?>

										<!-- Paso 2: Embudo de Clientes -->
										<?php if (isset($r['embudo'])): ?>
											<div class="ep-form-step-box">
												<div class="ep-form-step-num">2</div>
												<div class="ep-form-step-content">
													<div class="ep-form-step-titulo">Paso 2: Embudo de Clientes (Visitaron → Interactuaron → Compraron)</div>
													<div class="ep-funnel-diligenciado">
														<div class="ep-funnel-col">
															<span class="ep-funnel-lbl">Visitaron</span>
															<span class="ep-funnel-num"><?= (int) $r['embudo']['visitaron'] ?></span>
															<span class="ep-funnel-sub">Tráfico inicial</span>
														</div>
														<div class="ep-funnel-arrow"><?= ep_icon('arrow-right', 18) ?></div>
														<div class="ep-funnel-col">
															<span class="ep-funnel-lbl">Interactuaron</span>
															<span class="ep-funnel-num"><?= (int) $r['embudo']['interactuaron'] ?></span>
															<span class="ep-funnel-sub"><?= $r['embudo']['tasa_interaccion_pct'] ?>% interacción</span>
														</div>
														<div class="ep-funnel-arrow"><?= ep_icon('arrow-right', 18) ?></div>
														<div class="ep-funnel-col exitoso">
															<span class="ep-funnel-lbl">Compraron</span>
															<span class="ep-funnel-num"><?= (int) $r['embudo']['compraron'] ?></span>
															<span class="ep-funnel-sub"><?= $r['embudo']['tasa_conversion_pct'] ?>% conversión</span>
														</div>
													</div>
													<div class="ep-kpi-subnote">
														Tasa efectiva global: <strong><?= $r['embudo']['conversion_global_pct'] ?>% de compradores finales</strong> sobre visitantes atendidos.
													</div>
												</div>
											</div>
										<?php endif; ?>

										<!-- Paso 3: Modelos Activados -->
										<?php if (!empty($r['modelos'])): ?>
											<div class="ep-form-step-box">
												<div class="ep-form-step-num">3</div>
												<div class="ep-form-step-content">
													<div class="ep-form-step-titulo">Paso 3: Modelos Activados y Desglose de Unidades</div>
													<div class="ep-reg-tabla-wrap" style="margin-top:10px;">
														<table class="ep-reg-tabla">
															<thead>
																<tr>
																	<th>Modelo EcoTank</th>
																	<th style="text-align:right;">Cantidad Vendida</th>
																	<th style="text-align:right;">Participación</th>
																</tr>
															</thead>
															<tbody>
																<?php $totalUnidades = 0; foreach ($r['modelos'] as $m): $totalUnidades += (int) $m['cantidad']; ?>
																	<tr>
																		<td style="font-weight:600;color:var(--color-ink);"><?= htmlspecialchars($m['modelo']) ?></td>
																		<td style="text-align:right;font-weight:700;color:var(--color-primary);"><?= (int) $m['cantidad'] ?> uds</td>
																		<td style="text-align:right;font-weight:600;color:var(--color-text-muted);"><?= htmlspecialchars($m['pct']) ?></td>
																	</tr>
																<?php endforeach; ?>
															</tbody>
															<tfoot>
																<tr>
																	<th style="font-weight:700;">Total Unidades</th>
																	<th style="text-align:right;font-weight:800;color:var(--color-primary);"><?= $totalUnidades ?> uds</th>
																	<th style="text-align:right;">100%</th>
																</tr>
															</tfoot>
														</table>
													</div>
												</div>
											</div>
										<?php endif; ?>

										<!-- Paso 4: Cumplimiento de Activaciones (solo si existe) -->
										<?php if (isset($r['cumplimiento'])): ?>
											<div class="ep-form-step-box">
												<div class="ep-form-step-num">4</div>
												<div class="ep-form-step-content">
													<div class="ep-form-step-titulo">Paso 4: Cumplimiento de Activaciones</div>
													<div class="ep-form-grid-2" style="margin-top:10px;">
														<div class="ep-data-field">
															<span class="ep-data-field-lbl">Activaciones Programadas</span>
															<strong class="ep-data-field-val"><?= (int) $r['cumplimiento']['programadas'] ?></strong>
														</div>
														<div class="ep-data-field">
															<span class="ep-data-field-lbl">Activaciones Realizadas</span>
															<strong class="ep-data-field-val"><?= (int) $r['cumplimiento']['realizadas'] ?></strong>
														</div>
													</div>
													<span class="ep-progress-label" style="margin-top:8px;">Cumplimiento Operativo: <strong><?= $r['cumplimiento']['pct'] ?>% ejecutado</strong></span>
												</div>
											</div>
										<?php endif; ?>

									</div>
								<?php endif; ?>

								<!-- 2. CAPACITACIONES: Asistentes, Aprobados, Horas y Temas -->
								<?php if ($tipo === 'capacitaciones' && isset($r['capacitacion'])): $cap = $r['capacitacion']; ?>
									<div class="ep-form-diligenciado-steps">
										<div class="ep-form-step-box">
											<div class="ep-form-step-num">1</div>
											<div class="ep-form-step-content">
												<div class="ep-form-step-titulo">Evaluación de la Fuerza de Ventas</div>
												<div class="ep-stats-row-3" style="margin-top:12px;">
													<div class="ep-data-field">
														<span class="ep-data-field-lbl">Asistentes Convocados</span>
														<strong class="ep-data-field-val"><?= (int) $cap['asistentes'] ?></strong>
													</div>
													<div class="ep-data-field">
														<span class="ep-data-field-lbl">Vendedores Aprobados</span>
														<strong class="ep-data-field-val" style="color:var(--color-success);"><?= (int) $cap['aprobados'] ?></strong>
													</div>
													<div class="ep-data-field">
														<span class="ep-data-field-lbl">Tasa de Aprobación</span>
														<strong class="ep-data-field-val" style="color:var(--color-primary);"><?= $cap['pct_aprobacion'] ?>%</strong>
													</div>
												</div>
												<div style="margin-top:14px;padding:12px;background:var(--color-surface-muted);border-radius:8px;">
													<span class="ep-data-field-lbl">Horas de Instrucción:</span>
													<strong style="color:var(--color-ink);"><?= (int) $cap['horas'] ?> horas prácticas</strong>
												</div>
												<div style="margin-top:12px;">
													<span class="ep-data-field-lbl">Temas Clave Evaluados:</span>
													<p style="margin:4px 0 0;font-size:13px;line-height:1.5;color:var(--color-ink);font-weight:500;">
														<?= htmlspecialchars($cap['temas']) ?>
													</p>
												</div>
											</div>
										</div>
									</div>
								<?php endif; ?>

								<!-- 3. COLOCACIÓN DE POP: Balances de Inventario -->
								<?php if ($tipo === 'colocacion-pop' && !empty($r['pop_materiales'])): ?>
									<div class="ep-form-diligenciado-steps">
										<div class="ep-form-step-box">
											<div class="ep-form-step-num">1</div>
											<div class="ep-form-step-content">
												<div class="ep-form-step-titulo">Paso 1: Control y Balance de Materiales POP en Punto de Venta</div>
												<div class="ep-reg-tabla-wrap" style="margin-top:12px;">
													<table class="ep-reg-tabla">
														<thead>
															<tr>
																<th>Material POP</th>
																<th style="text-align:center;">Bodega</th>
																<th style="text-align:center;">Canales</th>
																<th style="text-align:center;color:var(--color-success);">Retail</th>
																<th style="text-align:center;color:var(--color-primary);background:var(--color-surface-muted);">Saldo Disponible</th>
															</tr>
														</thead>
														<tbody>
															<?php foreach ($r['pop_materiales'] as $p): ?>
																<tr>
																	<td style="font-weight:600;color:var(--color-ink);"><?= htmlspecialchars($p['material']) ?></td>
																	<td style="text-align:center;"><?= (int) $p['bodega'] ?></td>
																	<td style="text-align:center;"><?= (int) $p['canales'] ?></td>
																	<td style="text-align:center;font-weight:700;color:var(--color-success);"><?= (int) $p['retail'] ?></td>
																	<td style="text-align:center;font-weight:700;color:var(--color-primary);background:var(--color-surface-muted);"><?= (int) $p['disponible'] ?></td>
																</tr>
															<?php endforeach; ?>
														</tbody>
													</table>
												</div>
											</div>
										</div>
									</div>
								<?php endif; ?>

								<!-- 4. EXHIBICIONES: Espacios Preferenciales -->
								<?php if ($tipo === 'exhibiciones' && isset($r['exhibiciones'])): $ex = $r['exhibiciones']; ?>
									<div class="ep-form-diligenciado-steps">
										<div class="ep-form-step-box">
											<div class="ep-form-step-num">1</div>
											<div class="ep-form-step-content">
												<div class="ep-form-step-titulo">Paso 1: Espacios Preferenciales Negociados e Implementados</div>
												<div class="ep-stats-row-3" style="margin-top:12px;">
													<div class="ep-data-field">
														<span class="ep-data-field-lbl">Muebles Dedicados</span>
														<strong class="ep-data-field-val"><?= (int) $ex['muebles'] ?></strong>
														<small style="color:var(--color-text-muted);"><?= $ex['muebles_pct'] ?></small>
													</div>
													<div class="ep-data-field">
														<span class="ep-data-field-lbl">Rumas de Producto</span>
														<strong class="ep-data-field-val"><?= (int) $ex['rumas'] ?></strong>
														<small style="color:var(--color-text-muted);"><?= $ex['rumas_pct'] ?></small>
													</div>
													<div class="ep-data-field">
														<span class="ep-data-field-lbl">Cabeceras de Tecnología</span>
														<strong class="ep-data-field-val" style="color:var(--color-primary);"><?= (int) $ex['cabeceras'] ?></strong>
														<small style="color:var(--color-text-muted);"><?= $ex['cabeceras_pct'] ?></small>
													</div>
												</div>
												<div class="ep-kpi-subnote" style="margin-top:12px;">
													Total de espacios logrados en piso de venta: <strong><?= (int) $ex['total'] ?> exhibiciones preferenciales</strong>.
												</div>
											</div>
										</div>
									</div>
								<?php endif; ?>

								<!-- 5. EVENTO O FERIAS: Stand y Conversión -->
								<?php if ($tipo === 'evento-ferias' && isset($r['feria'])): $fe = $r['feria']; ?>
									<div class="ep-form-diligenciado-steps">
										<div class="ep-form-step-box">
											<div class="ep-form-step-num">1</div>
											<div class="ep-form-step-content">
												<div class="ep-form-step-titulo">Afluencia y Demostración en Stand Ferial</div>
												<div class="ep-funnel-diligenciado" style="margin-top:12px;">
													<div class="ep-funnel-col">
														<span class="ep-funnel-lbl">Visitantes al Stand</span>
														<span class="ep-funnel-num"><?= (int) $fe['visitaron'] ?></span>
														<span class="ep-funnel-sub">Asistentes</span>
													</div>
													<div class="ep-funnel-arrow"><?= ep_icon('arrow-right', 18) ?></div>
													<div class="ep-funnel-col">
														<span class="ep-funnel-lbl">Interactuaron / Demos</span>
														<span class="ep-funnel-num"><?= (int) $fe['interactuaron'] ?></span>
														<span class="ep-funnel-sub"><?= $fe['tasa_interaccion_pct'] ?>%</span>
													</div>
													<div class="ep-funnel-arrow"><?= ep_icon('arrow-right', 18) ?></div>
													<div class="ep-funnel-col exitoso">
														<span class="ep-funnel-lbl">Ventas Confirmadas</span>
														<span class="ep-funnel-num"><?= (int) $fe['compraron'] ?></span>
														<span class="ep-funnel-sub"><?= $fe['tasa_conversion_pct'] ?>%</span>
													</div>
												</div>
											</div>
										</div>

										<?php if (!empty($r['modelos'])): ?>
											<div class="ep-form-step-box">
												<div class="ep-form-step-num">2</div>
												<div class="ep-form-step-content">
													<div class="ep-form-step-titulo">Modelos Comercializados en Feria</div>
													<div class="ep-reg-tabla-wrap" style="margin-top:10px;">
														<table class="ep-reg-tabla">
															<thead>
																<tr>
																	<th>Solución Epson</th>
																	<th style="text-align:right;">Unidades Vendidas</th>
																	<th style="text-align:right;">Participación</th>
																</tr>
															</thead>
															<tbody>
																<?php foreach ($r['modelos'] as $m): ?>
																	<tr>
																		<td style="font-weight:600;color:var(--color-ink);"><?= htmlspecialchars($m['modelo']) ?></td>
																		<td style="text-align:right;font-weight:700;color:var(--color-primary);"><?= (int) $m['cantidad'] ?> uds</td>
																		<td style="text-align:right;"><?= htmlspecialchars($m['pct']) ?></td>
																	</tr>
																<?php endforeach; ?>
															</tbody>
														</table>
													</div>
												</div>
											</div>
										<?php endif; ?>
									</div>
								<?php endif; ?>

								<!-- 6. EVIDENCIAS FOTOGRÁFICAS REQUERIDAS DE LA ACTIVIDAD -->
								<?php if (!empty($r['fotos'])): ?>
									<div class="ep-expediente-seccion">
										<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
											<div class="ep-expediente-seccion-titulo" style="margin:0;">
												<?= ep_icon('camera', 14) ?>
												<span>EVIDENCIAS FOTOGRÁFICAS REQUERIDAS (<?= count($r['fotos']) ?>)</span>
											</div>
											<span style="font-size:11px;color:var(--color-text-muted);">Clic en cualquier evidencia para ampliar</span>
										</div>

										<div class="ep-reg-fotos-grid" style="margin-top:12px;">
											<?php foreach ($r['fotos'] as $idx => $f): ?>
												<div class="ep-reg-foto-card"
													data-foto-label="<?= htmlspecialchars($f['label'] ?? 'Foto') ?>"
													data-foto-tienda="<?= htmlspecialchars($r['punto_venta'] ?? '') ?>"
													data-foto-promotor="<?= htmlspecialchars($r['promotor'] ?? '') ?>"
													data-foto-hora="<?= htmlspecialchars($f['hora'] ?? $r['hora']) ?>">
													<div class="ep-reg-foto-preview">
														<div class="ep-reg-foto-placeholder">
															<?= ep_icon('camera', 22) ?>
															<span style="font-size:11px;"><?= htmlspecialchars($f['label'] ?? 'Foto') ?></span>
														</div>
														<div class="ep-reg-foto-hover-overlay">
															<?= ep_icon('search', 16) ?>
															<span>Inspeccionar</span>
														</div>
													</div>
													<div class="ep-reg-foto-footer">
														<span class="ep-reg-foto-lbl" title="<?= htmlspecialchars($f['label'] ?? '') ?>"><?= htmlspecialchars($f['label'] ?? '') ?></span>
														<div style="display:flex;align-items:center;justify-content:space-between;margin-top:3px;">
															<span style="font-size:10px;color:var(--color-text-muted);"><?= htmlspecialchars($f['hora'] ?? '') ?></span>
															<span class="ep-hist-badge ep-hist-badge-ok" style="font-size:9px;padding:1px 6px;">Verificada</span>
														</div>
													</div>
												</div>
											<?php endforeach; ?>
										</div>
									</div>
								<?php endif; ?>

								<!-- 7. COMENTARIOS Y OBSERVACIONES DE CAMPO -->
								<?php if (!empty($r['comentarios'])): ?>
									<div class="ep-expediente-seccion">
										<div class="ep-expediente-seccion-titulo">
											<?= ep_icon('file', 14) ?>
											<span>OBSERVACIONES REPORTADAS EN PUNTO DE VENTA</span>
										</div>
										<ul class="ep-reg-comentarios-lista" style="margin-top:8px;">
											<?php foreach ($r['comentarios'] as $c): ?>
												<li>
													<span class="ep-reg-comentario-dot"></span>
													<span><?= htmlspecialchars($c) ?></span>
												</li>
											<?php endforeach; ?>
										</ul>
									</div>
								<?php endif; ?>

								<!-- 8. BARRA DE ACCIONES DE AUDITORÍA -->
								<div class="ep-expediente-foot-bar">
									<div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--color-text-muted);">
										<?= ep_icon('check', 14) ?>
										<span>Formulario auditado y conforme con lineamientos corporativos Epson</span>
									</div>
									<div style="display:flex;align-items:center;gap:10px;">
										<button type="button" class="ep-btn ep-btn-outline ep-btn-imprimir-expediente" onclick="window.print()" style="font-size:12px;padding:6px 12px;gap:6px;">
											<?= ep_icon('file', 13) ?>
											<span>Imprimir Expediente</span>
										</button>
									</div>
								</div>

							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>

	<!-- Modal Lightbox para Inspección de Fotos -->
	<div id="epLightboxModal" class="ep-lightbox-modal hidden" role="dialog" aria-modal="true" aria-label="Visor de Evidencia Fotográfica">
		<div class="ep-lightbox-backdrop" id="epLightboxBackdrop"></div>
		<div class="ep-lightbox-dialog">
			<div class="ep-lightbox-head">
				<div>
					<span class="ep-eyebrow" style="color:var(--color-primary);" id="epLightboxEyebrow">Evidencia Fotográfica</span>
					<h3 id="epLightboxTitulo" style="font-size:16px;margin:2px 0 0;color:var(--color-ink);">Título de la foto</h3>
					<span id="epLightboxSub" style="font-size:12px;color:var(--color-text-muted);">Punto de venta y promotor</span>
				</div>
				<button type="button" class="ep-lightbox-close" id="epLightboxCerrar" aria-label="Cerrar visor">
					<?= ep_icon('close', 18) ?>
				</button>
			</div>

			<div class="ep-lightbox-body">
				<div class="ep-lightbox-canvas">
					<div class="ep-lightbox-sim-photo">
						<div class="ep-lightbox-watermark">
							<?= ep_icon('camera', 36) ?>
							<span id="epLightboxWatermarkText">EPSON REPORT · EVIDENCIA AUDITADA</span>
							<small id="epLightboxWatermarkMeta">Verificado por Lucky Ecuador</small>
						</div>
					</div>
				</div>
			</div>

			<div class="ep-lightbox-foot">
				<span class="ep-hist-badge ep-hist-badge-ok">Evidencia Válida</span>
				<span style="font-size:12px;color:var(--color-text-muted);">Auditoría conforme con requerimientos de visibilidad Epson</span>
			</div>
		</div>
	</div>

</main>

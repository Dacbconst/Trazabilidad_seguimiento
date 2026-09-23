<?php
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../includes/fotos_datos.php';
require_once __DIR__.'/../../includes/registros_datos.php';

// 1. EVALUACIÓN DE ROL Y USUARIO EN SESIÓN (Usuario vs Admin)
$rol = ep_rol_actual();
if (isset($_GET['rol']) && in_array($_GET['rol'], ['admin', 'usuario'], true)) {
	$rol = $_GET['rol'];
	$_SESSION['rol'] = $rol;
}
$esAdmin = ($rol === 'admin');

// Cargar todos los registros guardados
$todosRegistros = ep_registros_datos();
if (empty($todosRegistros)) {
	$pathJson = dirname(dirname(__DIR__)) . '/data/registros_guardados.json';
	if (file_exists($pathJson)) {
		$todosRegistros = json_decode(file_get_contents($pathJson), true) ?: [];
	}
	if (empty($todosRegistros) && function_exists('ep_registros_datos_semilla')) {
		$todosRegistros = ep_registros_datos_semilla();
	}
}

// 2. SEGREGACIÓN POR ROL: El usuario normal SOLO ve sus propios registros
$usuarioSesion = $_SESSION['usuario'] ?? 'carlos.mendoza';
if ($usuarioSesion === 'usuario' || $usuarioSesion === 'admin') {
	$usuarioSesion = 'carlos.mendoza';
}
$promotorNombreActual = ucwords(str_replace('.', ' ', $usuarioSesion));

if (!$esAdmin) {
	// Filtro estricto para vista de usuario
	$registrosUsuario = array_values(array_filter($todosRegistros, function($r) use ($promotorNombreActual, $usuarioSesion) {
		return (strcasecmp($r['promotor'] ?? '', $promotorNombreActual) === 0)
			|| (strcasecmp($r['promotor_usuario'] ?? '', $usuarioSesion) === 0);
	}));

	if (empty($registrosUsuario) && !empty($todosRegistros)) {
		// Fallback amigable si el usuario no tiene registros propios para demo
		$primerProm = $todosRegistros[0]['promotor'] ?? 'Carlos Mendoza';
		$registrosUsuario = array_values(array_filter($todosRegistros, fn($r) => ($r['promotor'] ?? '') === $primerProm));
		$promotorNombreActual = $primerProm;
	}
	$todosRegistros = $registrosUsuario;
}

// 3. AGRUPACIÓN POR USUARIO (Para vista de Admin: "agrupado pro usuaurios")
$gruposPorUsuario = [];
foreach ($todosRegistros as $r) {
	$prom = $r['promotor'] ?? 'Sin asignar';
	if (!isset($gruposPorUsuario[$prom])) {
		$gruposPorUsuario[$prom] = [
			'promotor'  => $prom,
			'avatar'    => $r['promotor_avatar'] ?? 'PR',
			'registros' => [],
		];
	}
	$gruposPorUsuario[$prom]['registros'][] = $r;
}
ksort($gruposPorUsuario);

// 4. AGRUPACIÓN POR DÍA (Para vista de usuario o vista cronológica de admin)
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

// Estadísticas de actividades para píldoras
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
	['id' => 'all',            'label' => 'Todas',               'count' => $conteoActividades['all']],
	['id' => 'activaciones',   'label' => 'Activaciones',        'count' => $conteoActividades['activaciones']],
	['id' => 'capacitaciones', 'label' => 'Capacitaciones',      'count' => $conteoActividades['capacitaciones']],
	['id' => 'colocacion-pop', 'label' => 'Colocación de POP',   'count' => $conteoActividades['colocacion-pop']],
	['id' => 'epson-day',      'label' => 'Epson Day',           'count' => $conteoActividades['epson-day']],
	['id' => 'exhibiciones',   'label' => 'Exhibiciones',        'count' => $conteoActividades['exhibiciones']],
	['id' => 'evento-ferias',  'label' => 'Evento o Ferias',     'count' => $conteoActividades['evento-ferias']],
];

// Métricas de resumen ligero
$totalRegistros = count($todosRegistros);
$tiendasUnicas = count(array_unique(array_column($todosRegistros, 'punto_venta')));
$listaPromotores = array_values(array_unique(array_filter(array_column($todosRegistros, 'promotor'))));
sort($listaPromotores);
$promotoresUnicos = count($listaPromotores);
$uid = 0;
?>
<main class="ep-content ep-registros-main" style="display:flex;flex-direction:column;gap:8px;">

	<!-- Encabezado compacto y ligero -->
	<header class="ep-registros-header-compact">
		<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
			<div>
				<div style="display:flex;align-items:center;gap:6px;">
					<h1 style="font-size:15px;font-weight:700;color:var(--color-ink);margin:0;letter-spacing:-0.2px;">
						<?= $esAdmin ? 'Registros de Actividades' : 'Mis Registros de Actividades' ?>
					</h1>
					<span class="ep-rol-chip <?= $esAdmin ? 'ep-rol-chip-admin' : 'ep-rol-chip-user' ?>">
						<?= $esAdmin ? 'Admin' : 'Promotor' ?>
					</span>
				</div>
				<p style="font-size:11px;color:var(--color-text-muted);margin:1px 0 0;">
					<?= $esAdmin 
						? 'Supervisión de formularios recolectados por promotores en puntos de venta.' 
						: 'Historial de formularios enviados por ' . htmlspecialchars($promotorNombreActual) . '.' 
					?>
				</p>
			</div>

			<!-- Barra de herramientas superior: Selector de rol de prueba y fechas -->
			<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
				<!-- Switcher para probar ambas versiones al instante -->
				<div class="ep-rol-switcher" style="display:inline-flex;padding:1px;background:#F1F4FA;border-radius:5px;gap:2px;">
					<a href="index.php?vista=historial&rol=admin" class="ep-rol-btn <?= $esAdmin ? 'ep-rol-btn-activo' : '' ?>" title="Ver como Administrador">Admin</a>
					<a href="index.php?vista=historial&rol=usuario" class="ep-rol-btn <?= !$esAdmin ? 'ep-rol-btn-activo' : '' ?>" title="Ver como Usuario / Promotor">Promotor</a>
				</div>

				<!-- Filtro de fechas Desde / Hasta -->
				<div class="ep-fechas-filtro-compact">
					<span style="font-size:10px;color:var(--color-text-muted);font-weight:600;">Desde</span>
					<input type="date" id="epFechaDesde" class="ep-input-date-compact" title="Filtrar desde fecha">
					<span style="font-size:10px;color:var(--color-text-muted);font-weight:600;">Hasta</span>
					<input type="date" id="epFechaHasta" class="ep-input-date-compact" title="Filtrar hasta fecha">
				</div>
			</div>
		</div>

		<!-- Tira de métricas ejecutivas en UNA sola línea ligera (~24px) -->
		<div class="ep-stats-summary-bar">
			<span class="ep-stats-item">
				<?= ep_icon('file', 12) ?>
				<strong><?= $totalRegistros ?></strong> <?= $esAdmin ? 'formularios' : 'mis formularios' ?>
			</span>
			<?php if ($esAdmin): ?>
				<span class="ep-stats-sep">·</span>
				<span class="ep-stats-item">
					<?= ep_icon('users', 12) ?>
					<strong><?= $promotoresUnicos ?></strong> usuarios
				</span>
			<?php endif; ?>
			<span class="ep-stats-sep">·</span>
			<span class="ep-stats-item">
				<?= ep_icon('store', 12) ?>
				<strong><?= $tiendasUnicas ?></strong> puntos de venta
			</span>
		</div>
	</header>

	<!-- Barra de herramientas compacta: Filtros por píldora, buscador y opciones -->
	<div class="ep-registros-toolbar-compact ep-card">
		<!-- Píldoras horizontales de actividades -->
		<div class="ep-registros-pills-compact" id="epPillsActividades">
			<?php foreach ($actividadesFiltro as $i => $af): ?>
				<button type="button" class="ep-reg-pill-compact<?= $i === 0 ? ' selected' : '' ?>" data-tipo="<?= htmlspecialchars($af['id']) ?>">
					<span class="ep-reg-pill-nombre"><?= htmlspecialchars($af['label']) ?></span>
					<span class="ep-reg-pill-count"><?= (int) $af['count'] ?></span>
				</button>
			<?php endforeach; ?>
		</div>

		<!-- Fila de búsqueda y controles de visualización -->
		<div class="ep-toolbar-controls-row">
			<!-- Cluster Izquierdo: Búsqueda, Vistas y Filtros de Segmentación -->
			<div class="ep-toolbar-left-cluster">
				<div class="ep-search-wrap-compact" style="flex:1 1 180px;max-width:300px;">
					<?= ep_icon('search', 13) ?>
					<input class="ep-input-compact" type="text" id="epBuscarRegistro" placeholder="Buscar por punto de venta, usuario o modelo..." autocomplete="off">
				</div>

				<!-- Selector de modo de vista: Fichas vs Tabla -->
				<div class="ep-view-switcher-compact" role="group" aria-label="Modo de visualización">
					<button type="button" class="ep-view-btn-compact ep-view-btn-activo" id="epBtnVistaFichas" data-vista="fichas" title="Vista de Fichas">
						<?= ep_icon('list', 12) ?>
						<span>Fichas</span>
					</button>
					<button type="button" class="ep-view-btn-compact" id="epBtnVistaTabla" data-vista="tabla" title="Vista de Tabla / Data Grid">
						<?= ep_icon('table', 12) ?>
						<span>Tabla</span>
					</button>
				</div>

				<?php if ($esAdmin): ?>
					<!-- Selector de Agrupación (Solo Admin): Por Usuario vs Por Fecha -->
					<div class="ep-group-switcher-compact" role="group" aria-label="Modo de agrupación">
						<button type="button" class="ep-group-btn-compact ep-group-btn-activo" id="epBtnAgruparUsuario" data-agrupacion="usuario" title="Agrupar por Usuario">
							<?= ep_icon('users', 12) ?>
							<span>Por Usuario</span>
						</button>
						<button type="button" class="ep-group-btn-compact" id="epBtnAgruparFecha" data-agrupacion="fecha" title="Agrupar por Fecha">
							<?= ep_icon('calendar', 12) ?>
							<span>Por Fecha</span>
						</button>
					</div>

					<!-- Filtro por Usuario / Promotor (Solo Admin) -->
					<div class="ep-toolbar-user-select-wrap">
						<select id="epFiltroPromotor" class="ep-input-compact" style="width:auto;max-width:150px;" title="Filtrar por promotor">
							<option value="all">Todos los usuarios (<?= count($listaPromotores) ?>)</option>
							<?php foreach ($listaPromotores as $p): ?>
								<option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>
			</div>

			<!-- Cluster Derecho: Acciones Ejecutivas de Exportación y Utilidades -->
			<div class="ep-toolbar-right-cluster">
				<?php if ($esAdmin): ?>
					<!-- Botón Principal de Descarga PPT (Solo Admin) -->
					<button type="button" class="ep-btn-ppt-export" id="epBtnAbrirExportadorPPT" title="Descargar reporte en diapositivas PowerPoint (.pptx)">
						<span class="ep-btn-ppt-icon"><?= ep_icon('presentation', 12) ?></span>
						<span class="ep-btn-ppt-label">Descargar PPT</span>
						<span class="ep-btn-ppt-badge">PPTX</span>
					</button>

					<div class="ep-toolbar-vsep"></div>
				<?php endif; ?>

				<!-- Selector de registros por página -->
				<div style="display:flex;align-items:center;gap:4px;">
					<select id="epPorPagina" class="ep-input-compact" style="width:auto;" title="Cantidad de registros por página">
						<option value="15" selected>15 / pág</option>
						<option value="25">25 / pág</option>
						<option value="50">50 / pág</option>
						<option value="all">Todos</option>
					</select>
				</div>

				<!-- Botón Expandir / Plegar todos -->
				<button type="button" id="ep-hist-expandir-todos" class="ep-btn-subtle-compact" data-estado="abrir" title="Expandir o plegar todos los registros">
					<?= ep_icon('chevron', 12) ?>
					<span id="epExpandirTodosTexto">Expandir</span>
				</button>
			</div>
		</div>

		<!-- Resumen de resultados filtrados -->
		<div style="display:flex;align-items:center;justify-content:space-between;font-size:10.5px;color:var(--color-text-muted);border-top:1px solid #EDF2FA;padding-top:4px;">
			<span id="ep-hist-resumen">Mostrando <?= $totalRegistros ?> formularios</span>
			<button type="button" id="epLimpiarFiltros" class="hidden" style="border:none;background:transparent;color:var(--color-primary);font-weight:600;cursor:pointer;font-size:10.5px;">
				Limpiar filtros
			</button>
		</div>
	</div>

	<!-- ============================================================== -->
	<!-- MODO A: VISTA AGRUPADA POR USUARIO (Default para Administrador) -->
	<!-- ============================================================== -->
	<?php if ($esAdmin): ?>
		<div id="ep-hist-modo-usuarios" class="ep-hist-modo-container" style="display:flex;flex-direction:column;gap:5px;">
			<?php foreach ($gruposPorUsuario as $promotor => $gUser): ?>
				<section class="ep-user-group-card" data-promotor="<?= htmlspecialchars($promotor) ?>">
					<!-- Encabezado de Usuario: Ligero (~30px) y desplegable -->
					<div class="ep-user-group-header" tabindex="0" role="button" aria-expanded="false">
						<div style="display:flex;align-items:center;gap:6px;min-width:0;">
							<span class="ep-reg-avatar-mini"><?= htmlspecialchars($gUser['avatar']) ?></span>
							<strong class="ep-user-group-name"><?= htmlspecialchars($promotor) ?></strong>
							<span class="ep-user-group-count"><?= count($gUser['registros']) ?></span>
						</div>
						<div class="ep-user-group-meta-cluster">
							<button type="button" class="ep-btn-user-ppt" data-promotor="<?= htmlspecialchars($promotor) ?>" title="Descargar paquete de diapositivas PPT del día para este usuario">
								<span class="ep-btn-user-ppt-icon"><?= ep_icon('presentation', 11) ?></span>
								<span class="ep-btn-user-ppt-label">PPT Diario</span>
							</button>
							<span class="ep-user-group-chevron"><?= ep_icon('chevron', 12) ?></span>
						</div>
					</div>

					<!-- Lista de registros del usuario (colapsada por defecto) -->
					<div class="ep-user-group-body">
						<div class="ep-user-records-list" style="display:flex;flex-direction:column;gap:4px;padding:4px 6px 6px;">
							<?php foreach ($gUser['registros'] as $r): $uid++; $detalleId = 'ep-reg-det-'.$uid;
								$tipo = $r['tipo'] ?? 'activaciones';
								$busqTexto = strtolower(($r['punto_venta'] ?? '').' '.($r['cadena'] ?? '').' '.($r['promotor'] ?? '').' '.($r['actividad_label'] ?? ''));
								if (!empty($r['modelos'])) {
									foreach ($r['modelos'] as $m) { $busqTexto .= ' '.strtolower($m['modelo'] ?? ''); }
								}
							?>
								<article class="ep-hist-record ep-expediente-card"
									id="<?= $detalleId ?>-card"
									data-tipo="<?= htmlspecialchars($tipo) ?>"
									data-fecha="<?= htmlspecialchars($r['fecha_iso'] ?? '') ?>"
									data-promotor="<?= htmlspecialchars($r['promotor'] ?? '') ?>"
									data-busqueda="<?= htmlspecialchars($busqTexto) ?>">

									<!-- Fila de cabecera ultra ligera (~32px) -->
									<div class="ep-hist-record-cabecera">
										<div style="display:flex;align-items:center;gap:8px;min-width:0;flex:1 1 auto;">
											<!-- Fecha sutil -->
											<div class="ep-expediente-fecha-badge-compact">
												<span class="ep-expediente-dia-compact"><?= date('d', strtotime($r['fecha_iso'] ?? 'now')) ?></span>
												<span class="ep-expediente-mes-compact"><?= strtoupper(date('M', strtotime($r['fecha_iso'] ?? 'now'))) ?></span>
											</div>

											<div style="min-width:0;flex-grow:1;">
												<div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;">
													<span class="ep-activity-type-badge ep-badge-<?= htmlspecialchars($tipo) ?>">
														<?= htmlspecialchars($r['actividad_label'] ?? 'Actividad') ?>
													</span>
													<span class="ep-expediente-id-compact"><?= htmlspecialchars($r['id'] ?? '') ?></span>
												</div>
												<div class="ep-hist-record-pdv">
													<?= htmlspecialchars($r['punto_venta'] ?? 'Punto de Venta') ?>
												</div>
											</div>
										</div>

										<div class="ep-record-actions-cluster">
											<button type="button" class="ep-btn-record-ppt" data-id="<?= htmlspecialchars($r['id'] ?? '') ?>" data-tipo="<?= htmlspecialchars($tipo) ?>" data-promotor="<?= htmlspecialchars($r['promotor'] ?? '') ?>" data-fecha="<?= htmlspecialchars($r['fecha_iso'] ?? '') ?>" data-tienda="<?= htmlspecialchars($r['punto_venta'] ?? '') ?>" title="Descargar diapositiva oficial de esta actividad">
												<span class="ep-record-ppt-icon"><?= ep_icon('presentation', 10) ?></span>
												<span class="ep-record-ppt-label">Slide PPT</span>
											</button>
											<div class="ep-accordion-indicator-compact" aria-hidden="true">
												<?= ep_icon('chevron', 12) ?>
											</div>
										</div>
									</div>

									<!-- Detalle del formulario -->
									<?php include __DIR__ . '/detalle_formulario.php'; ?>
								</article>
							<?php endforeach; ?>
						</div>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<!-- ============================================================== -->
	<!-- MODO B: VISTA AGRUPADA POR DÍA / FECHA (Para Usuario o Admin)  -->
	<!-- ============================================================== -->
	<div id="ep-hist-modo-fechas" class="ep-hist-modo-container <?= $esAdmin ? 'hidden' : '' ?>" style="display:flex;flex-direction:column;gap:8px;">
		<?php foreach ($gruposPorDia as $g): ?>
			<section class="ep-hist-day">
				<div class="ep-hist-day-header-compact">
					<div style="display:flex;align-items:center;gap:6px;">
						<span class="ep-hist-day-dot"></span>
						<h2 style="font-size:12px;font-weight:700;color:var(--color-ink);margin:0;"><?= htmlspecialchars($g['titulo']) ?></h2>
						<span class="ep-hist-day-count-compact"><?= count($g['registros']) ?></span>
					</div>
				</div>

				<div class="ep-hist-day-list" style="display:flex;flex-direction:column;gap:4px;margin-top:4px;">
					<?php foreach ($g['registros'] as $r): $uid++; $detalleId = 'ep-reg-det-'.$uid;
						$tipo = $r['tipo'] ?? 'activaciones';
						$busqTexto = strtolower(($r['punto_venta'] ?? '').' '.($r['cadena'] ?? '').' '.($r['promotor'] ?? '').' '.($r['actividad_label'] ?? ''));
						if (!empty($r['modelos'])) {
							foreach ($r['modelos'] as $m) { $busqTexto .= ' '.strtolower($m['modelo'] ?? ''); }
						}
					?>
						<article class="ep-hist-record ep-expediente-card"
							id="<?= $detalleId ?>-card"
							data-tipo="<?= htmlspecialchars($tipo) ?>"
							data-fecha="<?= htmlspecialchars($r['fecha_iso'] ?? '') ?>"
							data-promotor="<?= htmlspecialchars($r['promotor'] ?? '') ?>"
							data-busqueda="<?= htmlspecialchars($busqTexto) ?>">

							<div class="ep-hist-record-cabecera">
								<div style="display:flex;align-items:center;gap:8px;min-width:0;flex:1 1 auto;">
									<div class="ep-expediente-fecha-badge-compact">
										<span class="ep-expediente-dia-compact"><?= date('d', strtotime($r['fecha_iso'] ?? 'now')) ?></span>
										<span class="ep-expediente-mes-compact"><?= strtoupper(date('M', strtotime($r['fecha_iso'] ?? 'now'))) ?></span>
									</div>

									<div style="min-width:0;flex-grow:1;">
										<div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;">
											<span class="ep-activity-type-badge ep-badge-<?= htmlspecialchars($tipo) ?>">
												<?= htmlspecialchars($r['actividad_label'] ?? 'Actividad') ?>
											</span>
											<span class="ep-expediente-id-compact"><?= htmlspecialchars($r['id'] ?? '') ?></span>
											<?php if ($esAdmin): ?>
												<span style="font-size:10px;color:var(--color-text-muted);font-weight:600;">· <?= htmlspecialchars($r['promotor'] ?? '') ?></span>
											<?php endif; ?>
										</div>
										<div class="ep-hist-record-pdv">
											<?= htmlspecialchars($r['punto_venta'] ?? 'Punto de Venta') ?>
										</div>
									</div>
								</div>

								<div class="ep-record-actions-cluster">
									<?php if ($esAdmin): ?>
										<button type="button" class="ep-btn-record-ppt" data-id="<?= htmlspecialchars($r['id'] ?? '') ?>" data-tipo="<?= htmlspecialchars($tipo) ?>" data-promotor="<?= htmlspecialchars($r['promotor'] ?? '') ?>" data-fecha="<?= htmlspecialchars($r['fecha_iso'] ?? '') ?>" data-tienda="<?= htmlspecialchars($r['punto_venta'] ?? '') ?>" title="Descargar diapositiva oficial de esta actividad">
											<span class="ep-record-ppt-icon"><?= ep_icon('presentation', 10) ?></span>
											<span class="ep-record-ppt-label">Slide PPT</span>
										</button>
									<?php endif; ?>
									<div class="ep-accordion-indicator-compact" aria-hidden="true">
										<?= ep_icon('chevron', 12) ?>
									</div>
								</div>
							</div>

							<!-- Detalle del formulario -->
							<?php include __DIR__ . '/detalle_formulario.php'; ?>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>

	<!-- ============================================================== -->
	<!-- MODO C: VISTA TABLA / DATA GRID DE ALTA DENSIDAD              -->
	<!-- ============================================================== -->
	<div id="ep-hist-tabla-container" class="ep-hist-tabla-container hidden">
		<div class="ep-card ep-datagrid-card" style="overflow:hidden;border:1px solid #D6E0F2;">
			<div style="overflow-x:auto;">
				<table class="ep-datagrid-table">
					<thead>
						<tr>
							<th style="width:110px;">ID</th>
							<th style="width:100px;">Fecha</th>
							<th style="width:130px;">Actividad</th>
							<th>Punto de Venta</th>
							<?php if ($esAdmin): ?>
								<th style="width:170px;">Usuario</th>
							<?php endif; ?>
							<th style="width:<?= $esAdmin ? '130px' : '70px' ?>;text-align:right;">Acciones</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$rowId = 0;
						foreach ($todosRegistros as $r):
							$rowId++;
							$tRow = $r['tipo'] ?? 'activaciones';
							$busqRow = strtolower(($r['punto_venta'] ?? '').' '.($r['cadena'] ?? '').' '.($r['promotor'] ?? '').' '.($r['actividad_label'] ?? ''));
							if (!empty($r['modelos'])) {
								foreach ($r['modelos'] as $m) { $busqRow .= ' '.strtolower($m['modelo'] ?? ''); }
							}
							$cardTargetId = 'ep-reg-det-'.$rowId.'-card';
						?>
						<tr class="ep-datagrid-row"
							data-tipo="<?= htmlspecialchars($tRow) ?>"
							data-fecha="<?= htmlspecialchars($r['fecha_iso'] ?? '') ?>"
							data-promotor="<?= htmlspecialchars($r['promotor'] ?? '') ?>"
							data-busqueda="<?= htmlspecialchars($busqRow) ?>"
							data-target-card="<?= $cardTargetId ?>">
							<td>
								<span class="ep-expediente-id-compact"><?= htmlspecialchars($r['id'] ?? '') ?></span>
							</td>
							<td>
								<span style="font-weight:600;font-size:12px;color:var(--color-ink);white-space:nowrap;">
									<?= htmlspecialchars($r['fecha_formateada'] ?? ($r['fecha_iso'] ?? '')) ?>
								</span>
							</td>
							<td>
								<span class="ep-activity-type-badge ep-badge-<?= htmlspecialchars($tRow) ?>">
									<?= htmlspecialchars($r['actividad_label'] ?? 'Actividad') ?>
								</span>
							</td>
							<td>
								<div style="font-weight:600;font-size:12px;color:var(--color-ink);"><?= htmlspecialchars($r['punto_venta'] ?? 'Punto de Venta') ?></div>
							</td>
							<?php if ($esAdmin): ?>
								<td>
									<div style="display:flex;align-items:center;gap:5px;">
										<span class="ep-reg-avatar-mini" style="width:20px;height:20px;font-size:9px;"><?= htmlspecialchars($r['promotor_avatar'] ?? 'PR') ?></span>
										<span style="font-size:12px;font-weight:500;color:var(--color-ink);white-space:nowrap;"><?= htmlspecialchars($r['promotor'] ?? 'Promotor') ?></span>
									</div>
								</td>
							<?php endif; ?>
							<td style="text-align:right;white-space:nowrap;">
								<div style="display:inline-flex;align-items:center;gap:4px;justify-content:flex-end;">
									<?php if ($esAdmin): ?>
										<button type="button" class="ep-btn-record-ppt" data-id="<?= htmlspecialchars($r['id'] ?? '') ?>" data-tipo="<?= htmlspecialchars($tRow) ?>" data-promotor="<?= htmlspecialchars($r['promotor'] ?? '') ?>" data-fecha="<?= htmlspecialchars($r['fecha_iso'] ?? '') ?>" data-tienda="<?= htmlspecialchars($r['punto_venta'] ?? '') ?>" title="Descargar diapositiva PPT de esta actividad">
											<span class="ep-record-ppt-icon"><?= ep_icon('presentation', 11) ?></span>
											<span class="ep-record-ppt-label">Slide</span>
										</button>
									<?php endif; ?>
									<button type="button" class="ep-datagrid-ver-btn" data-target-card="<?= $cardTargetId ?>" title="Inspeccionar formulario">
										<?= ep_icon('eye', 12) ?>
										<span>Ver</span>
									</button>
								</div>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- Barra de Paginación Inteligente -->
	<div class="ep-paginacion-wrap ep-card" id="epPaginacionWrap" style="padding:6px 12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
		<div class="ep-paginacion-info" id="epPaginacionInfo" style="font-size:10.5px;color:var(--color-text-muted);">
			Mostrando <strong id="epPaginaRango" style="color:var(--color-ink);">1–15</strong> de <strong id="epPaginaTotal" style="color:var(--color-ink);"><?= $totalRegistros ?></strong>
		</div>
		<div class="ep-paginacion-controles" id="epPaginacionControles" style="display:flex;align-items:center;gap:4px;">
			<!-- Los botones numéricos y anterior/siguiente se generan con JS -->
		</div>
	</div>

	<!-- Modal de Inspección Fotográfica -->
	<div id="epModalFotoEvidencia" class="ep-modal-evidencia hidden" role="dialog" aria-modal="true" aria-labelledby="epModalFotoTitulo">
		<div class="ep-modal-evidencia-backdrop" id="epModalFotoBackdrop"></div>
		<div class="ep-modal-evidencia-dialog">
			<div class="ep-modal-evidencia-head">
				<div style="display:flex;align-items:center;gap:8px;">
					<?= ep_icon('camera', 16) ?>
					<h3 id="epModalFotoTitulo" style="margin:0;font-size:14px;font-weight:700;color:var(--color-ink);">Inspección de Evidencia</h3>
				</div>
				<button type="button" class="ep-modal-close-btn" id="epModalFotoCerrar" aria-label="Cerrar modal">
					<?= ep_icon('close', 14) ?>
				</button>
			</div>
			<div class="ep-modal-evidencia-body">
				<div class="ep-modal-img-frame">
					<div class="ep-modal-img-placeholder">
						<?= ep_icon('camera', 40) ?>
						<p id="epModalFotoDesc" style="margin:8px 0 0;font-size:12px;color:var(--color-text-muted);"></p>
					</div>
				</div>
				<div class="ep-modal-meta-bar" style="margin-top:12px;display:flex;gap:14px;font-size:11px;color:var(--color-text-muted);border-top:1px solid var(--color-border);padding-top:10px;">
					<div><strong>Punto de venta:</strong> <span id="epModalFotoTienda">-</span></div>
					<div><strong>Promotor:</strong> <span id="epModalFotoPromotor">-</span></div>
				</div>
			</div>
		</div>
	</div>

	<?php if ($esAdmin): ?>
		<!-- Modal de Selección de Plantillas y Descarga PPT (Solo Administrador) -->
		<?php include __DIR__ . '/modal_exportar_ppt.php'; ?>
	<?php endif; ?>

</main>

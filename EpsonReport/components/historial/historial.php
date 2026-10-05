<?php
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../includes/fotos_datos.php';
require_once __DIR__.'/../../includes/registros_datos.php';

$esAdmin = ep_es_gestor();
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

// Modo Aprobaciones: lo pendiente y lo devuelto (el supervisor solo recibe lo que le toca). Historial: el gestor ve lo aprobado; el promotor ve lo suyo en cualquier estado.
$modoAprobacion = !empty($modoAprobacion);
if ($modoAprobacion) {
	$todosRegistros = ep_registros_datos(1000, [], ['Pendiente', 'Devuelto']);
} elseif ($esAdmin) {
	$todosRegistros = ep_registros_datos(1000, [], ['Aprobado']);
} else {
	$todosRegistros = ep_registros_datos();
}
$usuarioSesion = $_SESSION['usuario'] ?? '';
if (!$esAdmin) {
	$todosRegistros = array_values(array_filter($todosRegistros, fn($r) => strcasecmp($r['promotor_usuario'] ?? '', $usuarioSesion) === 0));
}
$totalRegistros = count($todosRegistros);
$totalPromotores = count(array_unique(array_filter(array_column($todosRegistros, 'promotor'))));

// Datos para el modal "Exportar a Excel": lista de actividades y promotores, en cascada por supervisor (solo el admin ve el filtro de Supervisor).
$epExcelEsAdminReal = ep_es_admin();
$epExcelActividades = array_map(fn($l) => ['tipo' => $l['plantilla'], 'nombre' => $l['label']], ep_logicas());
$epExcelSupervisores = [];
$epExcelPromotoresPorSupervisor = [];
if ($esAdmin) {
	$dbExcel = ep_db();
	if ($dbExcel) {
		if ($epExcelEsAdminReal) {
			$resSup = $dbExcel->query("SELECT id, nombre FROM repositorio_usuarios_reporte WHERE rol = 'supervisor' AND status = 'activo' ORDER BY nombre");
			$epExcelSupervisores = $resSup ? $resSup->fetch_all(MYSQLI_ASSOC) : [];
		}
		// El admin ve el equipo de cada supervisor; un supervisor solo el suyo (misma consulta, se filtra abajo por su propio id).
		$resProm = $dbExcel->query("SELECT nombre, supervisor_canales_id, supervisor_retail_id FROM repositorio_usuarios_reporte WHERE rol = 'promotor' AND status = 'activo' ORDER BY nombre");
		foreach ($resProm ? $resProm->fetch_all(MYSQLI_ASSOC) : [] as $p) {
			foreach (array_filter([$p['supervisor_canales_id'], $p['supervisor_retail_id']]) as $supId) {
				if ($epExcelEsAdminReal || (int) $supId === (int) ($_SESSION['usuario_id'] ?? 0)) {
					$epExcelPromotoresPorSupervisor[(int) $supId][] = $p['nombre'];
				}
			}
		}
	}
}
?>
<main class="ep-content ep-h2" id="epH2" data-admin="<?= $esAdmin ? '1' : '0' ?>" data-modo="<?= $modoAprobacion ? 'aprobacion' : 'historial' ?>">

	<header class="ep-h2-head">
		<div>
			<?php if ($modoAprobacion): ?>
			<h1>Aprobaciones<?php if ($esAdmin): ?> <span class="ep-vivo" id="epH2Vivo" title="Se actualiza sola cada pocos segundos"><i></i><span>En vivo</span></span><?php endif; ?></h1>
<?php else: ?>
			<h1>Historial de registros <span class="ep-rol-chip <?= $esAdmin ? 'ep-rol-chip-admin' : 'ep-rol-chip-user' ?>"><?= ep_es_admin() ? 'Admin' : (ep_es_supervisor() ? 'Supervisor' : 'Promotor') ?></span><?php if ($esAdmin): ?> <span class="ep-vivo" id="epH2Vivo" title="Se actualiza sola cada pocos segundos"><i></i><span>En vivo</span></span><?php endif; ?></h1>
<?php endif; ?>
			<p id="epH2Resumen"></p>
		</div>
		<div class="ep-h2-rapidos" id="epH2Rapidos" role="group" aria-label="Periodo">
			<button type="button" class="selected" data-rapido="todo">Todo</button>
			<button type="button" data-rapido="hoy">Hoy</button>
			<button type="button" data-rapido="semana">Semana</button>
			<button type="button" data-rapido="mes">Mes</button>
		</div>
		<?php if ($esAdmin && !$modoAprobacion): ?>
			<button type="button" class="ep-h2-btn-excel" id="epHexAbrir" title="Elige fecha y actividad; descarga un CSV sin fotos">
				<svg width="15" height="15" viewBox="0 0 24 24" fill="none"><rect x="3" y="2" width="18" height="20" rx="2" fill="#1D7A3C"/><rect x="6.2" y="6" width="11.6" height="2.3" fill="#FFFFFF"/><rect x="6.2" y="10.3" width="11.6" height="2.3" fill="#FFFFFF"/><rect x="6.2" y="14.6" width="5.3" height="2.3" fill="#FFFFFF"/><rect x="12.4" y="14.6" width="5.4" height="2.3" fill="#1D7A3C"/></svg>
				Exportar a Excel
			</button>
		<?php endif; ?>
	</header>

	<?php if ($esAdmin && !$modoAprobacion): ?>
	<div class="ep-hex-overlay hidden" id="epHexOverlay">
		<div class="ep-hex-dialog" role="dialog" aria-modal="true" aria-label="Exportar a Excel">
			<div class="ep-hex-head">
				<span class="ep-hex-head-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><rect x="3" y="2" width="18" height="20" rx="2" fill="#1D7A3C"/><rect x="6.2" y="6" width="11.6" height="2.3" fill="#FFFFFF"/><rect x="6.2" y="10.3" width="11.6" height="2.3" fill="#FFFFFF"/><rect x="6.2" y="14.6" width="5.3" height="2.3" fill="#FFFFFF"/><rect x="12.4" y="14.6" width="5.4" height="2.3" fill="#1D7A3C"/></svg></span>
				<div class="ep-hex-head-info">
					<h2>Exportar a Excel</h2>
					<p><?= $epExcelEsAdminReal ? 'Supervisor y Promotor son opcionales: sin elegir ninguno, trae todo.' : 'Promotor es opcional: sin elegir uno, trae todo tu equipo.' ?></p>
				</div>
				<button type="button" class="ep-hex-cerrar" id="epHexCerrar" aria-label="Cerrar"><?= ep_icon('close', 15) ?></button>
			</div>
			<div class="ep-hex-body">
				<div class="ep-hex-filtros<?= $epExcelEsAdminReal ? '' : ' ep-hex-filtros-sin-sup' ?>" id="epHexFiltros">
					<div class="ep-hex-filtro">
						<div class="ep-hex-filtro-cab">
							<span>Fecha *</span>
							<div class="ep-hex-pills" role="group" aria-label="Modo de fecha">
								<button type="button" class="ep-hex-pill active" id="epHexPillUnica" data-modo="unica">Única</button>
								<button type="button" class="ep-hex-pill" id="epHexPillRango" data-modo="rango">Rango</button>
							</div>
						</div>
						<div class="ep-hex-fecha-caja">
							<?= ep_icon('calendar', 13) ?>
							<input type="date" id="epHexFechaDesde" aria-label="Fecha">
							<span class="ep-hex-fecha-sep hidden" id="epHexFechaSep">–</span>
							<input type="date" id="epHexFechaHasta" class="hidden" aria-label="Fecha hasta">
						</div>
					</div>
					<?php if ($epExcelEsAdminReal): ?>
					<div class="ep-hex-filtro" id="epHexColSupervisor">
						<span>Supervisor</span>
						<div class="ep-hex-combo" id="epHexComboSupervisor">
							<button type="button" class="ep-hex-combo-trigger" id="epHexSupervisorTrigger"><span>Todos</span><?= ep_icon('chevron', 13) ?></button>
							<div class="ep-hex-combo-menu hidden" id="epHexSupervisorMenu"></div>
						</div>
					</div>
					<?php endif; ?>
					<div class="ep-hex-filtro">
						<span>Promotor</span>
						<div class="ep-hex-combo" id="epHexComboPromotor">
							<button type="button" class="ep-hex-combo-trigger" id="epHexPromotorTrigger"><span>Todos</span><?= ep_icon('chevron', 13) ?></button>
							<div class="ep-hex-combo-menu hidden" id="epHexPromotorMenu"></div>
						</div>
					</div>
				</div>

				<div class="ep-hex-act-cab">
					<span>Tipo de actividad</span>
				</div>
				<div class="ep-hex-act-grid" id="epHexActGrid">
					<button type="button" class="ep-hex-act-card selected" data-tipo="">
						<span class="ep-hex-act-ico"><?= ep_icon('layers', 16) ?></span>
						<span class="ep-hex-act-nombre">Todas las actividades</span>
						<span class="ep-hex-act-radio"></span>
					</button>
					<?php foreach ($epExcelActividades as $act): ?>
					<button type="button" class="ep-hex-act-card" data-tipo="<?= $h($act['tipo']) ?>">
						<span class="ep-hex-act-ico"><?= ep_icon(ep_icono_tipo($act['tipo']), 16) ?></span>
						<span class="ep-hex-act-nombre"><?= $h($act['nombre']) ?></span>
						<span class="ep-hex-act-radio"></span>
					</button>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="ep-hex-pie">
				<button type="button" class="ep-hex-btn-cancelar" id="epHexCancelar">Cancelar</button>
				<a class="ep-hex-btn-descargar" id="epHexDescargar" href="#">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round"/><polyline points="7 10 12 15 17 10" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/><line x1="12" y1="15" x2="12" y2="3" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round"/></svg>
					<span>Descargar Excel</span>
				</a>
			</div>
		</div>
	</div>
	<script type="application/json" id="epHexDatos"><?= json_encode(['supervisores' => $epExcelSupervisores, 'promotoresPorSupervisor' => $epExcelPromotoresPorSupervisor], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
	<?php endif; ?>

	<?php if ($modoAprobacion): $cuentas = ep_aprobaciones_contar(); ?>
	<div class="ep-h2-tabs-est" id="epH2Estados" role="group" aria-label="Estado">
		<button type="button" class="ep-h2-tab-est on" data-estado="Pendiente">Pendientes <b><?= (int) $cuentas['Pendiente'] ?></b></button>
		<button type="button" class="ep-h2-tab-est" data-estado="Devuelto">Devueltos <b><?= (int) $cuentas['Devuelto'] ?></b></button>
		<a class="ep-h2-tab-link" href="index.php?vista=historial">Ver aprobados</a>
	</div>
	<?php endif; ?>

	<section class="ep-fl-barra">
		<label class="ep-fl-buscar">
			<?= ep_icon('search', 18) ?>
			<input type="search" id="epH2Buscar" placeholder="Buscar por código, promotor, punto de venta…" autocomplete="off">
		</label>

		<div class="ep-fl-combo" id="epH2ComboAct">
			<button type="button" class="ep-fl-combo-btn" aria-haspopup="listbox" aria-expanded="false">Actividad <span class="ep-fl-combo-valor">Todas</span><?= ep_icon('chevron', 16) ?></button>
			<div class="ep-fl-combo-panel hidden">
				<label class="ep-fl-combo-buscar"><?= ep_icon('search', 16) ?><input type="search" placeholder="Buscar actividad…" autocomplete="off"></label>
				<div class="ep-fl-combo-lista" role="listbox"></div>
			</div>
		</div>

		<?php if ($esAdmin): ?>
			<div class="ep-fl-combo" id="epH2ComboProm">
				<button type="button" class="ep-fl-combo-btn" aria-haspopup="listbox" aria-expanded="false">Promotor <span class="ep-fl-combo-valor">Todos</span><?= ep_icon('chevron', 16) ?></button>
				<div class="ep-fl-combo-panel hidden">
					<label class="ep-fl-combo-buscar"><?= ep_icon('search', 16) ?><input type="search" placeholder="Buscar promotor…" autocomplete="off"></label>
					<div class="ep-fl-combo-lista" role="listbox"></div>
				</div>
			</div>
		<?php endif; ?>

		<div class="ep-fl-combo" id="epH2ComboFecha">
			<button type="button" class="ep-fl-combo-btn" aria-haspopup="dialog" aria-expanded="false"><?= ep_icon('calendar', 16) ?> Rango<?= ep_icon('chevron', 16) ?></button>
			<div class="ep-fl-combo-panel ep-fl-combo-fechas hidden">
				<label>Desde<input type="date" id="epH2Desde"></label>
				<label>Hasta<input type="date" id="epH2Hasta"></label>
			</div>
		</div>
	</section>

	<div class="ep-h2-chips hidden" id="epH2Chips"></div>

	<?php if ($esAdmin): ?>
	<div class="ep-h2-multi hidden" id="epH2Multi">
		<span id="epH2MultiTexto">0 seleccionados</span>
		<div class="ep-h2-multi-acciones">
			<button type="button" class="ep-btn-subtle-compact" id="epH2MultiCancelar">Cancelar</button>
			<button type="button" class="ep-btn-ppt-cta" id="epH2MultiDescargar"><?= ep_icon('download', 14) ?> Descargar consolidado</button>
		</div>
	</div>
	<?php endif; ?>

	<div class="ep-h2-cuerpo">
		<section class="ep-h2-lista" aria-label="Registros">
			<div id="epH2Filas">
				<?php include __DIR__.'/filas.php'; ?>
			</div>
			<div class="ep-h2-vacio hidden" id="epH2Vacio"><?= ep_estado_vacio('file', 'Todavía no hay registros', 'Cuando se envíe uno desde Actividades aparecerá aquí.') ?></div>
			<div class="ep-h2-vacio hidden" id="epH2SinCoincidencias"><?= ep_estado_vacio('search', 'Sin resultados', 'Ningún registro coincide con los filtros. Prueba quitando alguno.') ?></div>
			<button type="button" class="ep-h2-mas hidden" id="epH2Mas">Mostrar más</button>
		</section>

		<aside class="ep-h2-panel" id="epH2Panel" aria-label="Detalle del registro">
			<div class="ep-h2-panel-vacio" id="epH2PanelVacio"><?= ep_estado_vacio('list', 'Elige un registro', 'Selecciona uno de la lista para ver su detalle.') ?></div>
			<div id="epH2PanelContenido"></div>
		</aside>
	</div>

	<div id="epH2Lightbox" class="ep-h2-lightbox hidden" role="dialog" aria-modal="true">
		<div class="ep-h2-lightbox-fondo" id="epH2LbFondo"></div>
		<div class="ep-h2-lightbox-caja">
			<button type="button" class="ep-h2-lb-cerrar" id="epH2LbCerrar" aria-label="Cerrar"><?= ep_icon('close', 16) ?></button>
			<button type="button" class="ep-h2-lb-nav ep-h2-lb-prev" id="epH2LbPrev" aria-label="Anterior">&#8249;</button>
			<img id="epH2LbImg" alt="">
			<button type="button" class="ep-h2-lb-nav ep-h2-lb-next" id="epH2LbNext" aria-label="Siguiente">&#8250;</button>
			<div class="ep-h2-lb-pie" id="epH2LbPie"></div>
		</div>
	</div>

</main>

<?php
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../db_connect.php';
iniciar_sesion();

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	echo '<div class="ac-placeholder">Acceso restringido.</div>';
	return;
}

$busqueda  = trim($_GET['q'] ?? '');
// Filtro de período (2026-08-20): reemplaza al selector de mes suelto — el
// Período del Acuerdo es siempre un trimestre fijo (ver trimestreABounds()
// en functions.php), así que filtrar por Q1-Q4 + Año calza exacto con cómo
// se guardan los Acuerdos, en vez de un mes cualquiera dentro del rango.
$trimestre = (int) ($_GET['trimestre'] ?? 0);
$rolUsuario = $_SESSION['rol'] ?? '';
// "Ver todo" + filtro de Canal (2026-08-31, pedido explícito): con un solo
// superdesarrollador en total, esa cuenta necesita ver Actas de Directo Y
// Distribuidor a la vez (antes cada usuario veía un solo canal, derivado de
// su supervisor real) — ver el mockup aprobado por el usuario ("Opción A":
// pastillas Total/Directo/Distribuidor arriba de los stat tiles). Un
// desarrollador normal nunca ve esta pastilla (sigue viendo solo lo suyo,
// como siempre), así que $canal solo tiene efecto real para superdesarrollador.
$esSuperdev = $rolUsuario === 'superdesarrollador';
$canal = in_array($_GET['canal'] ?? '', ['directo', 'distribuidor'], true) ? $_GET['canal'] : 'total';
$aniosDisponibles = listar_anios_disponibles($mysqli, $_SESSION['user_id'] ?? null, $rolUsuario);
// Año: si no vino explícito por query, se autoselecciona el año en curso
// (2026-08-28, pedido explícito) — pero solo si ese año realmente tiene
// Acuerdos del usuario; si no, se queda en "Todos los años" en vez de
// mostrar una tabla vacía por defecto.
if (isset($_GET['anio'])) {
	$anio = (int) $_GET['anio'];
} else {
	$anioActual = (int) date('Y');
	$anio = in_array($anioActual, $aniosDisponibles, true) ? $anioActual : 0;
}
// Filtro de firma (2026-08-21): activado desde los stat tiles de arriba, no
// un <select> — ver obtener_stats_historial()/listar_historial_acuerdos().
$filtroFirma = in_array($_GET['firma'] ?? '', ['firmadas', 'pendientes'], true) ? $_GET['firma'] : 'todos';
$pagina    = (int) ($_GET['pg'] ?? 1);
$usuarioId = $_SESSION['user_id'] ?? null;
$resultado = listar_historial_acuerdos($mysqli, $busqueda, $trimestre, $anio, $filtroFirma, $pagina, $usuarioId, 10, $rolUsuario, $canal);
$acuerdos  = $resultado['acuerdos'];
$stats     = obtener_stats_historial($mysqli, $busqueda, $trimestre, $anio, $usuarioId, $rolUsuario, $canal);
// Solo alimentan el ancho de las barras — el % y "más antigua" ya no se
// muestran como texto (pedido explícito: quitarlos, dejar solo el número).
$pctFirmadas = $stats['total'] > 0 ? round($stats['firmadas'] / $stats['total'] * 100) : 0;
$pctPendientes = $stats['total'] > 0 ? round($stats['pendientes'] / $stats['total'] * 100) : 0;

$js_v = @filemtime(__DIR__.'/../../assets/js/historial.js') ?: time();
?>
<div class="ac-historial" id="ac-historial-lista">
	<div class="ac-users-header ac-hist-header">
		<div>
			<h1 class="ac-page-title">Historial de Acuerdos</h1>
			<p class="ac-page-subtitle">Gestiona y descarga los acuerdos de desarrollo de negocios generados.</p>
		</div>
		<div class="ac-btn-group">
			<button type="button" class="ac-btn-outline ac-btn-inline" id="hist-actualizar" title="Actualizar">
				<span class="material-symbols-outlined">refresh</span> <span class="ac-btn-text">Actualizar</span>
			</button>
			<button type="button" class="ac-btn-outline ac-btn-inline" id="hist-abrir-borradores" title="Mis Borradores">
				<span class="material-symbols-outlined">draft</span> <span class="ac-btn-text">Mis Borradores</span>
			</button>
			<button type="button" class="ac-btn-primary ac-btn-inline" id="hist-nuevo-acuerdo">
				<span class="material-symbols-outlined">add</span>
				Nuevo Acuerdo
			</button>
		</div>
	</div>

	<?php if ($esSuperdev): ?>
	<!-- Filtro de Canal (2026-08-31, pedido explícito, mockup "Opción A"
	     aprobado por el usuario) — solo el superdesarrollador lo ve: es la
	     única cuenta que necesita mezclar Actas de Directo y Distribuidor a
	     la vez, un desarrollador normal sigue viendo un solo canal siempre
	     (derivado de su supervisor real), sin este bloque ni cambio alguno.
	     Mismas pastillas .ac-seg-pill que ya usan Cumplimiento/Seguimiento de
	     Equipo — cero CSS nuevo. -->
	<div class="ac-seg-periodo" style="margin: var(--space-md) 0 0;">
		<span style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--color-on-surface-variant);">Vista</span>
		<div class="ac-seg-pill-group" id="hist-canal-group">
			<button type="button" class="ac-seg-pill<?= $canal === 'total' ? ' ac-seg-pill-activo' : '' ?>" data-canal="total">Total</button>
			<button type="button" class="ac-seg-pill<?= $canal === 'directo' ? ' ac-seg-pill-activo' : '' ?>" data-canal="directo">Directo</button>
			<button type="button" class="ac-seg-pill<?= $canal === 'distribuidor' ? ' ac-seg-pill-activo' : '' ?>" data-canal="distribuidor">Distribuidor</button>
		</div>
	</div>
	<p class="ac-field-hint">Acá se ven las Actas de los 2 canales. Cada asesor sigue viendo únicamente las de su propio canal.</p>
	<?php endif; ?>

	<!-- Banner de vencimiento (2026-08-25, del concepto "Sala de Alertas",
	     aprobado por el usuario tal cual) — aparece solo si el usuario tiene
	     Actas propias por vencer (mismo umbral/datos que la campanita del
	     header, ver getters/alertas_firma.php), oculto el resto del tiempo.
	     No es un <select> más de filtro: es un aviso, con su propio color de
	     urgencia y una acción directa. -->
	<div class="ac-hist-banner" id="hist-banner" hidden>
		<span class="material-symbols-outlined ac-hist-banner-icon">warning</span>
		<span class="ac-hist-banner-text" id="hist-banner-text"></span>
		<button type="button" class="ac-hist-banner-cta" id="hist-banner-cta"></button>
	</div>

	<!-- Stat tiles (2026-08-21): también son filtro — click en "Firmadas" o
	     "Pendientes de Firma" filtra la tabla a ese subconjunto; click de
	     nuevo en el que ya está activo vuelve a "todos". Los números respetan
	     búsqueda/período/año actuales, no el filtro de firma (ver
	     obtener_stats_historial()). -->
	<div class="ac-hist-stats" id="hist-stats">
		<button type="button" class="ac-hist-stat" id="hist-stat-total" data-filtro="todos">
			<span class="ac-hist-stat-icon"><span class="material-symbols-outlined">description</span></span>
			<span class="ac-hist-stat-body">
				<p class="ac-stat-label">Acuerdos Generados</p>
				<p class="ac-stat-value" id="hist-stat-total-valor"><?= $stats['total'] ?></p>
			</span>
		</button>
		<button type="button" class="ac-hist-stat ac-hist-stat-ok<?= $filtroFirma === 'firmadas' ? ' ac-hist-stat-activo' : '' ?>" id="hist-stat-firmadas" data-filtro="firmadas">
			<span class="ac-hist-stat-icon"><span class="material-symbols-outlined">task_alt</span></span>
			<span class="ac-hist-stat-body">
				<p class="ac-stat-label">Firmadas</p>
				<p class="ac-stat-value" id="hist-stat-firmadas-valor"><?= $stats['firmadas'] ?></p>
				<span class="ac-stat-bar"><span class="ac-stat-bar-fill ac-stat-bar-fill-ok" id="hist-stat-firmadas-bar" style="width:<?= $pctFirmadas ?>%"></span></span>
			</span>
		</button>
		<button type="button" class="ac-hist-stat ac-hist-stat-warn<?= $filtroFirma === 'pendientes' ? ' ac-hist-stat-activo' : '' ?>" id="hist-stat-pendientes" data-filtro="pendientes">
			<span class="ac-hist-stat-icon"><span class="material-symbols-outlined">schedule</span></span>
			<span class="ac-hist-stat-body">
				<p class="ac-stat-label">Pendientes de Firma</p>
				<p class="ac-stat-value" id="hist-stat-pendientes-valor"><?= $stats['pendientes'] ?></p>
				<span class="ac-stat-bar"><span class="ac-stat-bar-fill ac-stat-bar-fill-warn" id="hist-stat-pendientes-bar" style="width:<?= $pctPendientes ?>%"></span></span>
			</span>
		</button>
	</div>

	<section class="ac-card ac-hist-filtros-card">
		<div class="ac-hist-filtros">
			<div class="ac-input-wrap ac-hist-search-wrap">
				<span class="material-symbols-outlined">search</span>
				<input type="text" class="ac-input" id="hist-buscar" placeholder="Buscar por distribuidor..." value="<?= htmlspecialchars($busqueda) ?>">
			</div>
			<select class="ac-select ac-hist-periodo ac-select-bonito-auto" id="hist-trimestre">
				<option value="0">Todos los períodos</option>
				<option value="1" <?= $trimestre === 1 ? 'selected' : '' ?>>Q1 (Ene-Mar)</option>
				<option value="2" <?= $trimestre === 2 ? 'selected' : '' ?>>Q2 (Abr-Jun)</option>
				<option value="3" <?= $trimestre === 3 ? 'selected' : '' ?>>Q3 (Jul-Sep)</option>
				<option value="4" <?= $trimestre === 4 ? 'selected' : '' ?>>Q4 (Oct-Dic)</option>
			</select>
			<select class="ac-select ac-hist-anio ac-select-bonito-auto" id="hist-anio">
				<option value="0">Todos los años</option>
				<?php foreach ($aniosDisponibles as $a): ?>
					<option value="<?= $a ?>" <?= $anio === $a ? 'selected' : '' ?>><?= $a ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="ac-btn-outline ac-btn-inline" id="hist-buscar-btn">
				<span class="material-symbols-outlined">search</span>
				Buscar
			</button>
			<?php if ($esSuperdev): ?>
			<!-- "Descargar Excel" ahora deja elegir el formato (2026-08-31,
			     pedido explícito: "el excel debería tener una opción para
			     descargar el de directo o distribuidor") — reserva 100% del
			     módulo Excel al superdesarrollador, ver getters/exportar_cuota_categoria.php.
			     Mismo botón-que-se-expande-en-2-opciones que ya usa "Exportar"
			     en Repositorios (.ac-repo-exportar, cero CSS/animación nueva). -->
			<div class="ac-repo-exportar" id="hist-exportar-wrap">
				<button type="button" class="ac-btn-outline ac-btn-inline ac-repo-exportar-btn" id="hist-exportar-btn">
					<span class="material-symbols-outlined">download</span>
					Descargar Excel
				</button>
				<div class="ac-repo-exportar-opciones-outer">
					<div class="ac-repo-exportar-opciones">
						<a class="ac-repo-exportar-opcion" id="hist-exportar-directo" href="getters/exportar_cuota_categoria.php?canal=directo" target="_blank">
							<span class="material-symbols-outlined">store</span>
							Formato Directo
						</a>
						<a class="ac-repo-exportar-opcion" id="hist-exportar-distribuidor" href="getters/exportar_cuota_categoria.php?canal=distribuidor" target="_blank">
							<span class="material-symbols-outlined">local_shipping</span>
							Formato Distribuidor
						</a>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</section>

	<section class="ac-card">
		<!-- Paginación arriba Y abajo (2026-08-25, pedido explícito: "tengo
		     que bajar para poder cambiar de página" — con la tabla llena, los
		     controles de abajo quedan fuera de vista). Ver renderPaginacionBtns()
		     en historial.js, que ahora escribe en las 2 a la vez. -->
		<div class="ac-pagination ac-pagination-top" id="hist-paginacion-top" data-pagina="<?= $resultado['pagina'] ?>" data-total-paginas="<?= $resultado['total_paginas'] ?>">
			<p class="ac-pagination-info" id="hist-paginacion-info-top">
				Mostrando <strong><?= count($acuerdos) ?></strong> de <strong><?= $resultado['total'] ?></strong> acuerdos
			</p>
			<div class="ac-pagination-btns" id="hist-paginacion-btns-top"></div>
		</div>

		<div class="ac-table-scroll">
			<table class="ac-table" id="hist-tabla">
				<thead>
					<tr>
						<th>ID</th>
						<th>Distribuidor</th>
						<th>Localidad</th>
						<?php if ($esSuperdev): ?><th>Canal</th><?php endif; ?>
						<th class="ac-text-center">Periodo</th>
						<th class="ac-text-center">Firma</th>
						<th class="ac-text-right">Fecha Generada</th>
						<th class="ac-text-right">Acciones</th>
					</tr>
				</thead>
				<tbody id="hist-tabla-body">
					<?php if ($acuerdos): ?>
						<?php foreach ($acuerdos as $a): ?>
							<?= renderFilaHistorial($a, $esSuperdev) ?>
						<?php endforeach; ?>
					<?php else: ?>
						<tr><td colspan="<?= $esSuperdev ? 8 : 7 ?>" class="ac-table-empty">No se encontraron acuerdos.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="ac-pagination" id="hist-paginacion" data-pagina="<?= $resultado['pagina'] ?>" data-total-paginas="<?= $resultado['total_paginas'] ?>">
			<p class="ac-pagination-info" id="hist-paginacion-info">
				Mostrando <strong><?= count($acuerdos) ?></strong> de <strong><?= $resultado['total'] ?></strong> acuerdos
			</p>
			<div class="ac-pagination-btns" id="hist-paginacion-btns"></div>
		</div>
	</section>
</div>

<!-- Modal "Subir Acta Firmada" (2026-08-21): 2 paneles lado a lado — el Acta
     generada (izquierda, referencia) y el archivo firmado (derecha, el que
     ya está subido o el que se está por subir) — para poder comparar visual
     antes de guardar. Un solo modal para "ver la firma ya subida" y "subir
     una nueva/reemplazar" — mismo componente, cambia el estado inicial del
     panel derecho según si ya había firma o no. -->
<div class="ac-modal-overlay ac-firma-modal-overlay" id="hist-firma-modal-overlay">
	<div class="ac-modal ac-firma-modal">
		<div class="ac-firma-modal-bar no-print">
			<h3 class="ac-firma-modal-title" id="hist-firma-modal-title">Acta Firmada</h3>
			<button type="button" class="ac-modal-close" id="hist-firma-modal-close" aria-label="Cerrar">
				<span class="material-symbols-outlined">close</span>
			</button>
		</div>
		<div class="ac-firma-modal-body">
			<div class="ac-firma-panel">
				<p class="ac-firma-panel-label">Acta Generada</p>
				<iframe id="hist-firma-original-frame" class="ac-firma-panel-frame" title="Acta generada"></iframe>
				<button type="button" class="ac-icon-btn ac-firma-panel-ampliar" id="hist-firma-ampliar-original" title="Ampliar">
					<span class="material-symbols-outlined">open_in_full</span>
				</button>
			</div>
			<div class="ac-firma-panel">
				<p class="ac-firma-panel-label">Acta Firmada</p>
				<div class="ac-firma-preview-area" id="hist-firma-preview-area">
					<div class="ac-firma-preview-vacio" id="hist-firma-preview-vacio">
						<span class="material-symbols-outlined">add_a_photo</span>
						<p>Selecciona una foto o PDF del Acta firmada para compararla acá</p>
					</div>
				</div>
				<button type="button" class="ac-icon-btn ac-firma-panel-ampliar hidden" id="hist-firma-ampliar-firmada" title="Ampliar">
					<span class="material-symbols-outlined">open_in_full</span>
				</button>
			</div>
		</div>
		<div class="ac-firma-modal-footer no-print">
			<p class="ac-firma-modal-hint" id="hist-firma-modal-hint"></p>
			<div class="ac-firma-modal-botones">
				<button type="button" class="ac-btn-outline ac-btn-inline" id="hist-firma-elegir-btn">
					<span class="material-symbols-outlined">upload_file</span> Elegir Archivo
				</button>
				<button type="button" class="ac-btn-primary ac-btn-inline" id="hist-firma-guardar-btn" disabled>
					<span class="material-symbols-outlined">save</span> Guardar Acta Firmada
				</button>
			</div>
		</div>
	</div>
</div>
<input type="file" id="hist-firma-file-input" accept="image/jpeg,image/png,image/webp,application/pdf" hidden>

<!-- Detalle de un acuerdo del historial: mismo PDF real que genera Registrar
     (getters/generar_acta_pdf.php), no una segunda maqueta HTML a mantener
     sincronizada. -->
<div class="ac-historial-preview hidden" id="ac-historial-preview">
	<div class="ac-acuerdo-preview-bar no-print">
		<button type="button" class="ac-btn-outline" id="hist-volver-lista">
			<span class="material-symbols-outlined">arrow_back</span> Volver al Historial
		</button>
		<a class="ac-btn-primary ac-btn-inline" id="hist-descargar-pdf" target="_blank">
			<span class="material-symbols-outlined">download</span> Descargar / Imprimir PDF
		</a>
	</div>
	<iframe id="hist-pdf-frame" class="ac-acta-pdf-frame" title="Vista previa del Acta"></iframe>
</div>

<!-- Modal: Mis Borradores — borradores propios (creado_por = usuario de la
     sesión, ver listar_borradores_usuario() en functions.php). "Continuar
     editando" cambia a la pestaña Registrar y carga el borrador ahí (ver
     window.acRegistrarCargarBorrador en registrar.js). -->
<div class="ac-modal-overlay" id="hist-borradores-modal-overlay">
	<div class="ac-modal ac-borradores-modal">
		<div class="ac-modal-header">
			<h3>Mis Borradores</h3>
			<button type="button" class="ac-modal-close" id="hist-borradores-modal-close" aria-label="Cerrar">
				<span class="material-symbols-outlined">close</span>
			</button>
		</div>
		<div class="ac-borradores-modal-body">
			<div class="ac-table-scroll">
			<table class="ac-table">
				<thead>
					<tr><th>Documento</th><th>Distribuidor</th><th>Periodo</th><th>Actualizado</th><th></th></tr>
				</thead>
				<tbody id="hist-borradores-body"></tbody>
			</table>
			</div>
		</div>
	</div>
</div>

<script src="assets/js/historial.js?v=<?= $js_v ?>"></script>

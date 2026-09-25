<?php
require_once __DIR__.'/../../includes/functions.php';
require_once __DIR__.'/../../includes/fotos_datos.php';
require_once __DIR__.'/../../includes/registros_datos.php';

$esAdmin = (ep_rol_actual() === 'admin');
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

// Registros reales desde la base; el promotor solo ve los suyos.
$todosRegistros = ep_registros_datos();
$usuarioSesion = $_SESSION['usuario'] ?? '';
if (!$esAdmin) {
	$todosRegistros = array_values(array_filter($todosRegistros, fn($r) => strcasecmp($r['promotor_usuario'] ?? '', $usuarioSesion) === 0));
}
$totalRegistros = count($todosRegistros);
$totalPromotores = count(array_unique(array_filter(array_column($todosRegistros, 'promotor'))));
?>
<main class="ep-content ep-h2" id="epH2" data-admin="<?= $esAdmin ? '1' : '0' ?>">

	<header class="ep-h2-head">
		<div>
			<h1>Historial de registros <span class="ep-rol-chip <?= $esAdmin ? 'ep-rol-chip-admin' : 'ep-rol-chip-user' ?>"><?= $esAdmin ? 'Admin' : 'Promotor' ?></span></h1>
			<p id="epH2Resumen"></p>
		</div>
		<div class="ep-h2-rapidos" id="epH2Rapidos" role="group" aria-label="Periodo">
			<button type="button" class="selected" data-rapido="todo">Todo</button>
			<button type="button" data-rapido="hoy">Hoy</button>
			<button type="button" data-rapido="semana">Semana</button>
			<button type="button" data-rapido="mes">Mes</button>
		</div>
	</header>

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

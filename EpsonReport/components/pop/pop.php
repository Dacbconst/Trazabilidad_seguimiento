<?php
// Módulo Colocación de POP: "Carga de POP" (admin y Fabricio), "Colocación asignada" y "Seguimiento" (quien recibe POP como supervisor); luego vendrán reporte y analítica.
require_once __DIR__.'/../../includes/functions.php';

if (!ep_es_gestor()) {
	echo '<main class="ep-content"><p>No tienes permiso para ver esta sección.</p></main>';
	return;
}
require_once __DIR__.'/../../includes/pop_datos.php';
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$esDueno = ep_pop_es_dueno();
$esSup = ep_es_supervisor();
$yo = (int) ($_SESSION['usuario_id'] ?? 0);
$meses = $esDueno ? ep_pop_listar() : [];
$hayAbierto = (bool) array_filter($meses, fn($m) => $m['estado'] === 'activo');
$supervisores = $esDueno ? ep_pop_supervisores() : [];
$catalogo = $esDueno ? ep_pop_catalogo() : ['materiales' => [], 'campanas' => []];
$sinCatalogo = $esDueno && (!$catalogo['materiales'] || !$catalogo['campanas']);
$mesesSup = $esSup ? ep_pop_meses_del_supervisor($yo) : [];
$mesSup = $mesesSup[0] ?? null;
// El punto rojo avisa que ya recibió POP y todavía no marcó a ningún promotor.
$sinEquipo = $mesSup && $mesSup['estado'] === 'activo' && !ep_pop_equipo_de((int) $mesSup['id'], $yo);
?>
<main class="ep-content ep-popm">
	<header class="ep-popm-head">
		<h1>Colocación de POP</h1>
		<p>El material llega a bodega, se reparte a los supervisores y ellos lo reparten a sus promotores.</p>
	</header>

	<div class="ep-popm-tabs" role="tablist" aria-label="Colocación de POP">
		<?php if ($esDueno): ?><button type="button" class="ep-popm-tab" role="tab" data-tab="carga"><?= ep_icon('tag', 16) ?>Carga de POP</button><?php endif; ?>
		<button type="button" class="ep-popm-tab" role="tab" data-tab="asignada"><?= ep_icon('users', 16) ?>Colocación asignada<?php if ($sinEquipo): ?><span class="ep-popm-punto" title="Tienes material asignado y aún no eliges a tu equipo"></span><?php endif; ?></button>
		<button type="button" class="ep-popm-tab" role="tab" data-tab="seguimiento"><?= ep_icon('clock', 16) ?>Seguimiento</button>
		<div class="ep-popm-indicador" id="epPopmIndicador"></div>
	</div>

	<?php if ($esDueno): ?><section class="ep-popm-panel" id="epPopmCarga" data-tab="carga"><?php require __DIR__.'/carga.php'; ?></section><?php endif; ?>
	<section class="ep-popm-panel" id="epPopmAsignada" data-tab="asignada"><?php require __DIR__.'/asignada.php'; ?></section>
	<section class="ep-popm-panel" id="epPopmSeguimiento" data-tab="seguimiento"><?php require __DIR__.'/seguimiento.php'; ?></section>
</main>

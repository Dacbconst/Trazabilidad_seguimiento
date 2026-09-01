<?php
/**
 * Agenda de visitas estilo Google Calendar. Markup en partials/, lógica en assets/agenda.js.
 */
$modulo_base = basename((string) $cuenta_dir);
$agenda_dir = __DIR__;
$agenda_assets = $modulo_base.'/components/agendamiento/assets';

require_once $cuenta_dir.'/mapbox_config.php';

// Cache-busting: sin esto el navegador sigue sirviendo agenda.css/agenda.js
// viejos en caché aunque se suban archivos nuevos al servidor — el query
// param solo cambia cuando el archivo realmente cambia.
$agenda_css_v = @filemtime($agenda_dir.'/assets/agenda.css') ?: time();
$agenda_js_v = @filemtime($agenda_dir.'/assets/agenda.js') ?: time();
$agenda_crear_js_v = @filemtime($agenda_dir.'/assets/agenda-crear.js') ?: time();
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.17/index.global.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= htmlspecialchars($agenda_assets, ENT_QUOTES) ?>/agenda.css?v=<?= $agenda_css_v ?>">

<div id="agendaApp"
     data-getters-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>/getters/"
     data-mapbox-token="<?= htmlspecialchars(MAPBOX_TOKEN, ENT_QUOTES) ?>">

    <?php include $agenda_dir.'/partials/filtros.php'; ?>

    <div class="agenda-layout">
        <?php include $agenda_dir.'/partials/sidebar.php'; ?>
        <?php include $agenda_dir.'/partials/calendario.php'; ?>
    </div>

    <?php include $agenda_dir.'/partials/modal-edicion.php'; ?>
    <?php include $agenda_dir.'/partials/modal-crear.php'; ?>

</div>

<script src="<?= htmlspecialchars($agenda_assets, ENT_QUOTES) ?>/agenda.js?v=<?= $agenda_js_v ?>"></script>
<script src="<?= htmlspecialchars($agenda_assets, ENT_QUOTES) ?>/agenda-crear.js?v=<?= $agenda_crear_js_v ?>"></script>

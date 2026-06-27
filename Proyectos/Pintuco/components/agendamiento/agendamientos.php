<?php
/**
 * COMPONENTE: agendamiento/agendamientos.php
 * Agenda de visitas estilo Google Calendar: sidebar (Crear + mini-calendario +
 * Agendas pendientes) y calendario semanal a la derecha, con mapa colapsable
 * que empuja el layout (no se sobrepone) al desplegarse.
 * Datos reales de insert_proyectos_contacto vía Pintuco/getters/get_agenda.php
 * y Pintuco/getters/update_agenda.php. $cuenta_dir/$cuenta_actual vienen del
 * index.php que incluye este componente.
 *
 * El markup vive separado en partials/ (filtros, sidebar, calendario, modal)
 * y la lógica de UI en assets/agenda.js — este archivo solo arma la página
 * y pasa la base de los getters al JS vía data-attribute.
 */
$modulo_base = basename((string) $cuenta_dir);
$agenda_dir = __DIR__;
$agenda_assets = $modulo_base.'/components/agendamiento/assets';

// Cache-busting: sin esto el navegador sigue sirviendo agenda.css/agenda.js
// viejos en caché aunque se suban archivos nuevos al servidor — el query
// param solo cambia cuando el archivo realmente cambia.
$agenda_css_v = @filemtime($agenda_dir.'/assets/agenda.css') ?: time();
$agenda_js_v = @filemtime($agenda_dir.'/assets/agenda.js') ?: time();
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.17/index.global.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="<?= htmlspecialchars($agenda_assets, ENT_QUOTES) ?>/agenda.css?v=<?= $agenda_css_v ?>">

<div id="agendaApp" data-getters-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>/getters/">

    <?php include $agenda_dir.'/partials/filtros.php'; ?>

    <div class="agenda-layout">
        <?php include $agenda_dir.'/partials/sidebar.php'; ?>
        <?php include $agenda_dir.'/partials/calendario.php'; ?>
    </div>

    <?php include $agenda_dir.'/partials/modal-edicion.php'; ?>

</div>

<script src="<?= htmlspecialchars($agenda_assets, ENT_QUOTES) ?>/agenda.js?v=<?= $agenda_js_v ?>"></script>

<?php
/**
 * COMPONENTE: contactados/contactados.php
 * Directorio de TODOS los contactos capturados de insert_proyectos_contacto
 * (incluye los que todavía no tienen fecha_agendamiento — a diferencia de
 * Agendamientos, que solo muestra los ya agendados). $cuenta_dir viene del
 * index.php que incluye este componente.
 */
$modulo_base = basename((string) $cuenta_dir);
$contactados_dir = __DIR__;
$contactados_assets = $modulo_base.'/components/contactados/assets';
// Cache-busting por filemtime: sin esto el navegador sirve copias viejas de
// CSS/JS en caché aunque se suban archivos nuevos al servidor (mismo bug ya
// corregido en Agendamientos — ver memoria del proyecto).
$contactados_css_v = @filemtime($contactados_dir.'/assets/contactados.css') ?: time();
$contactados_js_v = @filemtime($contactados_dir.'/assets/contactados.js') ?: time();
?>
<link rel="stylesheet" href="<?= htmlspecialchars($contactados_assets, ENT_QUOTES) ?>/contactados.css?v=<?= $contactados_css_v ?>">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<div id="contactadosApp" data-getters-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>/getters/">
    <div class="contactados-card">
        <div class="contactados-topbar">Contactos</div>

        <div class="contactados-scroll">
            <table class="contactados-table">
                <thead>
                    <tr>
                        <!-- PDV oculto a pedido del usuario (2026-06-30), no se quiere ver por ahora -->
                        <!-- <th>PDV</th> -->
                        <th>Local</th>
                        <th>Promotor</th>
                        <th>Contacto</th>
                        <th>Empresa</th>
                        <th>Correo</th>
                        <th>Teléfono</th>
                        <th>Registrado</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="contactadosTbody">
                    <tr><td colspan="8" class="contactados-vacio">Cargando...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="contactados-paginacion">
            <span class="contactados-paginacion-info" id="contactadosPaginacionInfo"></span>
            <div class="contactados-paginacion-controles">
                <button type="button" class="contactados-paginacion-btn" id="contactadosPagAnterior">&laquo; Anterior</button>
                <span class="contactados-paginacion-pagina" id="contactadosPaginaActual"></span>
                <button type="button" class="contactados-paginacion-btn" id="contactadosPagSiguiente">Siguiente &raquo;</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($contactados_assets, ENT_QUOTES) ?>/contactados.js?v=<?= $contactados_js_v ?>"></script>

<?php
/**
 * COMPONENTE: contactados/contactados.php
 * Directorio de TODOS los contactos capturados de insert_proyectos_contacto.
 * $cuenta_dir viene del index.php que incluye este componente.
 */
$modulo_base = basename((string) $cuenta_dir);
$contactados_dir = __DIR__;
$contactados_assets = $modulo_base.'/components/contactados/assets';
$contactados_css_v = @filemtime($contactados_dir.'/assets/contactados.css') ?: time();
$contactados_js_v = @filemtime($contactados_dir.'/assets/contactados.js') ?: time();
?>
<link rel="stylesheet" href="<?= htmlspecialchars($contactados_assets, ENT_QUOTES) ?>/contactados.css?v=<?= $contactados_css_v ?>">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<div id="contactadosApp" data-getters-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>/getters/">

    <!-- Filtros — misma barra unificada que Agendamiento y Principal -->
    <div class="mod-filtros">
        <div class="filter-group is-busqueda">
            <label>PDV, empresa o contacto</label>
            <div class="input-group">
                <input type="text" class="form-control" id="contactadosBusqueda" placeholder="Buscar PDV, empresa o contacto...">
                <span class="input-group-addon"><i class="glyphicon glyphicon-search"></i></span>
            </div>
        </div>
        <div class="filter-group">
            <label>Estado de gestión</label>
            <select class="form-control" id="contactadosEstado">
                <option value="">Todos los estados</option>
                <option value="pendiente">Nuevo (sin gestionar)</option>
                <option value="confirmado">Visita confirmada</option>
                <option value="reagendada">Reagendada</option>
                <option value="vencida">Vencida</option>
                <option value="cancelada">Cancelada</option>
                <option value="completada">Completada</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Mercaderista</label>
            <select class="form-control" id="contactadosMercaderista">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="mod-filtros-extra">
            <button type="button" class="btn contactados-btn-excel" id="contactadosExportarExcel">
                <i class="glyphicon glyphicon-save"></i> Descargar Excel
            </button>
        </div>
    </div>

    <!-- Tabla de contactos -->
    <div class="contactados-card">
        <div class="contactados-topbar">Contactos</div>
        <div class="contactados-scroll">
            <table class="contactados-table">
                <thead>
                    <tr>
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

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
<!-- Inter ya se carga global en style.css; solo falta IBM Plex Mono para
     los datos tabulares (teléfonos, horas) del rediseño pedido. -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500&display=swap">
<link rel="stylesheet" href="<?= htmlspecialchars($contactados_assets, ENT_QUOTES) ?>/contactados.css?v=<?= $contactados_css_v ?>">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<div id="contactadosApp" data-getters-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>/getters/">

    <!-- Barra de filtros propia (paleta oklch pedida para este rediseño) -->
    <div class="ctc-filtros">
        <div class="ctc-filtro-group ctc-filtro-busqueda">
            <label>Buscar empresa o contacto</label>
            <div class="ctc-filtro-input-wrap">
                <i class="glyphicon glyphicon-search"></i>
                <input type="text" id="contactadosBusqueda" placeholder="Buscar empresa o contacto...">
            </div>
        </div>
        <div class="ctc-filtro-group">
            <label>Promotor</label>
            <select id="contactadosMercaderista">
                <option value="">Todos los mercaderistas</option>
            </select>
        </div>

        <button type="button" class="btn-actualizar" id="contactadosActualizar">
            <i class="glyphicon glyphicon-refresh"></i> Actualizar
        </button>
        <!-- Siempre visible (no aparece/desaparece): estado "apagado" hasta
             que haya al menos 1 fila marcada — ver contactados.js. -->
        <button type="button" class="ctc-btn ctc-btn-descarga is-seleccion" id="contactadosDescargarSeleccion" disabled>
            <i class="glyphicon glyphicon-save"></i>
            <span id="contactadosSeleccionTexto">Descargar selección</span>
        </button>
        <button type="button" class="ctc-btn ctc-btn-descarga is-todo" id="contactadosDescargarTodo">
            <i class="glyphicon glyphicon-save"></i> Descargar todo
        </button>
    </div>

    <!-- Tabla de contactos -->
    <div class="ctc-card">
        <div class="ctc-card-header">
            <div class="ctc-card-header-left">
                <span class="ctc-card-title">Contactos</span>
                <span class="ctc-card-count" id="contactadosCount">0 registros</span>
            </div>
            <span class="ctc-seleccion-info" id="contactadosSeleccionInfo"></span>
        </div>

        <div class="ctc-scroll">
            <table class="ctc-table">
                <thead>
                    <tr>
                        <th class="ctc-th-check">
                            <input type="checkbox" id="contactadosCheckTodo" title="Seleccionar todo lo filtrado">
                        </th>
                        <th>Empresa</th>
                        <th>Contacto</th>
                        <th>Dirección empresa</th>
                        <th>Correo / Teléfono</th>
                        <th>Promotor</th>
                        <th>PDV</th>
                        <th>Registrado</th>
                    </tr>
                </thead>
                <tbody id="contactadosTbody">
                    <tr><td colspan="8" class="ctc-vacio">Cargando...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="ctc-paginacion">
            <span class="ctc-paginacion-info" id="contactadosPaginacionInfo"></span>
            <div class="ctc-paginacion-controles">
                <button type="button" class="ctc-paginacion-btn" id="contactadosPagAnterior">&laquo; Anterior</button>
                <span class="ctc-paginacion-pagina" id="contactadosPaginaActual"></span>
                <button type="button" class="ctc-paginacion-btn" id="contactadosPagSiguiente">Siguiente &raquo;</button>
            </div>
        </div>
    </div>

</div>

<script src="<?= htmlspecialchars($contactados_assets, ENT_QUOTES) ?>/contactados.js?v=<?= $contactados_js_v ?>"></script>

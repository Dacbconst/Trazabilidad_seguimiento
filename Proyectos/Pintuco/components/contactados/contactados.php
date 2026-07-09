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
        <div class="ctc-filtro-group">
            <label>Estado</label>
            <select id="contactadosEstado">
                <option value="">Todos los estados</option>
                <option value="is-facturado">Facturado</option>
                <option value="is-negociando">Negociando</option>
                <option value="is-agendado">Agendado</option>
                <option value="is-sin-agendar">Sin agendar</option>
                <option value="is-cancelado">Cancelado</option>
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
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="contactadosTbody">
                    <tr><td colspan="9" class="ctc-vacio">Cargando...</td></tr>
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

    <!-- Modal "Gestión de Contacto": historial de cotizaciones (ciclos de
         proforma) del contacto de la fila clicada — ver contactados.js. -->
    <div class="ctc-gestion-overlay" id="ctcGestionOverlay">
        <div class="ctc-gestion-card">
            <div class="ctc-gestion-header">
                <div>
                    <div class="ctc-gestion-titulo">Gestión de Contacto</div>
                    <div class="ctc-gestion-sub" id="ctcGestionSub"></div>
                </div>
                <button type="button" class="ctc-gestion-close" id="ctcGestionClose" aria-label="Cerrar">&times;</button>
            </div>

            <div class="ctc-gestion-tabs">
                <span class="ctc-gestion-tab is-activa">Historial de Cotizaciones (mes actual)</span>
            </div>

            <div class="ctc-gestion-body">
                <div class="ctc-gestion-scroll">
                    <table class="ctc-gestion-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Monto Cotizado</th>
                                <th>Monto Facturado</th>
                            </tr>
                        </thead>
                        <tbody id="ctcGestionTbody">
                            <tr><td colspan="3" class="ctc-vacio">Cargando...</td></tr>
                        </tbody>
                        <tfoot>
                            <tr class="ctc-gestion-subtotal">
                                <td>SUBTOTAL</td>
                                <td id="ctcGestionSubtotalCotizado">—</td>
                                <td id="ctcGestionSubtotalFacturado">—</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="ctc-gestion-footer">
                <!-- Abre la misma card "Visita Técnica" del módulo de Agendamiento
                     (agenda-crear.js expone window.AgendaAbrirCrear), prellenada
                     con los datos de este contacto — ver contactados.js. -->
                <button type="button" class="ctc-btn ctc-gestion-btn-nuevo" id="ctcGestionBtnNuevo">
                    <i class="glyphicon glyphicon-plus"></i> Registrar nuevo agendamiento
                </button>
                <button type="button" class="ctc-gestion-btn-cerrar" id="ctcGestionCerrar">Cerrar</button>
            </div>
        </div>
    </div>

</div>

<script src="<?= htmlspecialchars($contactados_assets, ENT_QUOTES) ?>/contactados.js?v=<?= $contactados_js_v ?>"></script>

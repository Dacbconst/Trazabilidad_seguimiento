<?php
/**
 * COMPONENTE: proforma/proforma.php
 * Auditoría de Proformas (Paso 3 del flujo comercial) — bandeja master-detail
 * para que el analista revise la evidencia subida desde el celular y
 * enrute el registro a Negociación, Aprobada o Rechazada.
 */
$modulo_base = basename((string) $cuenta_dir);
$proforma_dir = __DIR__;
$proforma_assets = $modulo_base.'/components/proforma/assets';

$proforma_css_v = @filemtime($proforma_dir.'/assets/proforma.css') ?: time();
$proforma_js_v = @filemtime($proforma_dir.'/assets/proforma.js') ?: time();
?>
<link rel="stylesheet" href="<?= htmlspecialchars($proforma_assets, ENT_QUOTES) ?>/proforma.css?v=<?= $proforma_css_v ?>">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<div id="proformaApp"
     data-getters-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>/getters/"
     data-modulo-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>">

    <div class="proforma-header">
        <div>
            <h2>Auditoría de Proformas</h2>
            <p>Centro de revisión de evidencias y enrutamiento comercial.</p>
        </div>
        <span class="proforma-actualizado">Actualizado: <span id="proformaActualizado">—</span></span>
    </div>

    <div class="proforma-kpis">
        <div class="proforma-kpi">
            <span class="proforma-kpi-label">Pendientes de revisión</span>
            <span class="proforma-kpi-valor" id="proformaKpiPendientes">0</span>
        </div>
        <div class="proforma-kpi">
            <span class="proforma-kpi-label">En negociación</span>
            <span class="proforma-kpi-valor is-ambar" id="proformaKpiNegociacion">0</span>
        </div>
        <div class="proforma-kpi">
            <span class="proforma-kpi-label">Aprobadas hoy</span>
            <span class="proforma-kpi-valor is-verde" id="proformaKpiAprobadasHoy">0</span>
        </div>
        <div class="proforma-kpi">
            <span class="proforma-kpi-label">Monto validado en trámite</span>
            <span class="proforma-kpi-valor is-verde" id="proformaKpiMonto">$0</span>
        </div>
    </div>

    <!-- Filtros unificados (.mod-filtros definido en style.css global) -->
    <div class="mod-filtros">
        <div class="filter-group is-busqueda">
            <label>PDV o cliente</label>
            <div class="input-group">
                <input type="text" class="form-control" id="proformaBusqueda" placeholder="Buscar PDV o cliente...">
                <span class="input-group-addon"><i class="glyphicon glyphicon-search"></i></span>
            </div>
        </div>
        <div class="filter-group">
            <label>Mercaderista</label>
            <select class="form-control" id="proformaFiltroPromotor">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Período</label>
            <select class="form-control" id="proformaFiltroPeriodo">
                <option value="">Cualquier fecha</option>
                <option value="mes_actual">Este mes</option>
                <option value="mes_anterior">Mes anterior</option>
                <option value="ultimos_3">Últimos 3 meses</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Estado auditoría</label>
            <select class="form-control" id="proformaFiltroEstado">
                <option value="">Todos</option>
                <option value="en_proceso">Pendiente revisión</option>
                <option value="en_negociacion">En negociación</option>
                <option value="aprobado">Aprobada</option>
                <option value="rechazado">Rechazada</option>
            </select>
        </div>
        <div class="mod-filtros-extra">
            <button type="button" class="proforma-btn-exportar" id="proformaExportar">
                <i class="glyphicon glyphicon-save"></i> Exportar Reporte
            </button>
        </div>
    </div>

    <div class="proforma-scroll">
        <table class="proforma-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Cliente / Punto de venta</th>
                    <th>Promotor asignado</th>
                    <th>Fecha visita</th>
                    <th>KPI tiempos</th>
                    <th>Estado auditoría</th>
                </tr>
            </thead>
            <tbody id="proformaTbody">
                <tr><td colspan="6" class="proforma-vacio">Cargando...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="proforma-toast" id="proformaToast"></div>
</div>

<script src="<?= htmlspecialchars($proforma_assets, ENT_QUOTES) ?>/proforma.js?v=<?= $proforma_js_v ?>"></script>

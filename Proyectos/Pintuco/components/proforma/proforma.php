<?php
/**
 * COMPONENTE: proforma/proforma.php
 * Auditoría de Proformas (Paso 3 del flujo comercial) — bandeja master-detail
 * para que el analista revise la evidencia subida desde el celular y
 * enrute el registro a Negociación, Aprobada o Rechazada.
 * $cuenta_dir/$cuenta_actual vienen del index.php que incluye este componente.
 */
$modulo_base = basename((string) $cuenta_dir);
$proforma_dir = __DIR__;
$proforma_assets = $modulo_base.'/components/proforma/assets';

// Cache-busting por filemtime (mismo bug ya corregido en Agendamientos —
// ver memoria del proyecto): sin esto el navegador sirve copias viejas de
// CSS/JS en caché aunque se suban archivos nuevos al servidor.
$proforma_css_v = @filemtime($proforma_dir.'/assets/proforma.css') ?: time();
$proforma_js_v = @filemtime($proforma_dir.'/assets/proforma.js') ?: time();
?>
<link rel="stylesheet" href="<?= htmlspecialchars($proforma_assets, ENT_QUOTES) ?>/proforma.css?v=<?= $proforma_css_v ?>">

<div id="proformaApp"
     data-getters-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>/getters/"
     data-modulo-base="<?= htmlspecialchars($modulo_base, ENT_QUOTES) ?>">

    <div class="proforma-header">
        <div>
            <h2>Auditoría de Proformaseaasfasdsadasd</h2>
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

    <div class="proforma-toolbar">
        <div class="proforma-buscar">
            <i class="glyphicon glyphicon-search"></i>
            <input type="text" id="proformaBusqueda" placeholder="Buscar PDV o cliente...">
        </div>
        <select class="form-control input-sm" id="proformaFiltroPromotor">
            <option value="">Todos los promotores</option>
        </select>
        <select class="form-control input-sm" id="proformaFiltroEstado">
            <option value="">Estado: Todos</option>
            <option value="en_proceso">Pendiente revisión</option>
            <option value="en_negociacion">En negociación</option>
            <option value="aprobado">Aprobada</option>
            <option value="rechazado">Rechazada</option>
        </select>
        <div class="proforma-toolbar-derecha">
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

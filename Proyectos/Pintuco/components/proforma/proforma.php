<?php
/**
 * COMPONENTE: proforma/proforma.php
 * Auditoría de Proformas (Paso 3 del flujo comercial) — bandeja master-detail
 * para que el analista revise la evidencia subida desde el celular y
 * enrute el registro a Negociación, Aprobada o Cerrada (sin llegar a factura).
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

    <!-- Filtros unificados (.mod-filtros definido en style.css global) -->
    <div class="mod-filtros">
        <div class="filter-group">
            <label>Promotor</label>
            <select class="form-control" id="proformaFiltroPromotor">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="filter-group">
            <!-- Solo los PDV con registros en este módulo (allRows), no el
                 catálogo completo del canal — mismo criterio que Factura/
                 Estado de Flujo. -->
            <label>PDV</label>
            <select class="form-control" id="proformaFiltroPdv">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Empresa</label>
            <select class="form-control" id="proformaFiltroEmpresa">
                <option value="">Todas</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Período</label>
            <!-- Se llena en JS (poblarSelectorPeriodo) con los meses/año que
                 realmente tienen datos, igual que Contactados y Facturación
                 — pedido explícito del usuario, en vez de "mes actual/
                 anterior/últimos 3" fijos. -->
            <select class="form-control" id="proformaFiltroPeriodo">
                <option value="">Cualquier fecha</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Estado auditoría</label>
            <select class="form-control" id="proformaFiltroEstado">
                <option value="">Todos</option>
                <option value="en_proceso">Pendiente revisión</option>
                <option value="en_negociacion">En negociación</option>
                <option value="correccion_solicitada">Corrección solicitada</option>
                <option value="aprobado">Aprobada</option>
                <option value="rechazado">Cerrada</option>
            </select>
        </div>
        <div class="mod-filtros-extra">
            <button type="button" class="btn btn-actualizar" id="proformaActualizar">
                <i class="glyphicon glyphicon-refresh"></i> Actualizar
            </button>
            <button type="button" class="proforma-btn-exportar" id="proformaExportar">
                <i class="glyphicon glyphicon-save"></i> Exportar Reporte
            </button>
        </div>
    </div>

    <div class="proforma-grupos" id="proformaGrupos">
        <div class="proforma-vacio">Cargando...</div>
    </div>

    <div class="proforma-toast" id="proformaToast"></div>

    <div class="proforma-foto-overlay" id="proformaFotoOverlay">
        <button type="button" class="proforma-foto-cerrar" id="proformaFotoCerrar" aria-label="Cerrar">&times;</button>
        <img class="proforma-foto-grande" id="proformaFotoGrande" alt="">
    </div>
</div>

<script src="<?= htmlspecialchars($proforma_assets, ENT_QUOTES) ?>/proforma.js?v=<?= $proforma_js_v ?>"></script>

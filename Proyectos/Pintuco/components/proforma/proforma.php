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
                <option value="correccion_solicitada">Corrección solicitada</option>
                <option value="aprobado">Aprobada</option>
                <option value="rechazado">Rechazada</option>
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

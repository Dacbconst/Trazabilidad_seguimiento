<div class="mod-filtros" id="agendaFiltros">
    <div class="filter-group">
        <label>Promotor</label>
        <select class="form-control" id="agendaFiltroPromotor">
            <option value="">Todos</option>
        </select>
    </div>
    <div class="filter-group">
        <!-- Sus opciones se recalculan cada vez que cambia Promotor (ver
             cargarOpcionesTecnico en agenda.js): sin promotor elegido salen
             TODOS los técnicos que han tenido agendamiento, con promotor
             elegido se acota a los técnicos que han trabajado con ese
             promotor — en ambos casos contando todos los estados, el
             filtro de Estado no interviene acá a propósito. -->
        <label>Técnico asignado</label>
        <select class="form-control" id="agendaFiltroTecnico">
            <option value="">Todos</option>
        </select>
    </div>
    <div class="filter-group">
        <!-- Mismo catálogo de locales que usa "Crear visita" (get_pdvs.php) -->
        <label>PDV</label>
        <select class="form-control" id="agendaFiltroPdv">
            <option value="">Todos</option>
        </select>
    </div>
    <div class="filter-group">
        <label>Empresa</label>
        <select class="form-control" id="agendaFiltroEmpresa">
            <option value="">Todas</option>
        </select>
    </div>
    <div class="filter-group">
        <label>Estado</label>
        <select class="form-control" id="agendaFiltroEstado">
            <option value="">Todos</option>
            <option value="pendiente">Pendiente técnico</option>
            <option value="confirmado">Técnico confirmado</option>
            <option value="reagendada">Reagendada</option>
            <option value="vencida">Vencida</option>
            <option value="cancelada">Cancelada</option>
            <option value="completada">Completada</option>
        </select>
    </div>

    <!-- Elegir un filtro NO dispara la búsqueda solo — recarga únicamente
         al apretar este botón (o "Actualizar"), pedido explícito del
         usuario (2026-07-16). -->
    <div class="filter-group agenda-filtro-buscar-wrap">
        <label>&nbsp;</label>
        <button type="button" class="btn btn-actualizar" id="agendaBtnBuscar">
            <i class="glyphicon glyphicon-search"></i> Buscar
        </button>
    </div>

    <div class="mod-filtros-extra">
        <div class="agenda-legend">
            <span class="agenda-legend-item"><span class="agenda-legend-dot is-pendiente"></span><strong id="agendaCountPendientes">0</strong> Pendientes</span>
            <span class="agenda-legend-item"><span class="agenda-legend-dot is-confirmado"></span><strong id="agendaCountConfirmadas">0</strong> Confirmadas</span>
            <span class="agenda-legend-item"><span class="agenda-legend-dot is-reagendada"></span><strong id="agendaCountReagendadas">0</strong> Reagendadas</span>
            <span class="agenda-legend-item"><span class="agenda-legend-dot is-vencida"></span><strong id="agendaCountVencidas">0</strong> Vencidas</span>
            <span class="agenda-legend-item"><span class="agenda-legend-dot is-cancelada"></span><strong id="agendaCountCanceladas">0</strong> Canceladas</span>
        </div>
        <button type="button" class="btn btn-actualizar" id="agendaBtnActualizar">Actualizar</button>
    </div>
</div>

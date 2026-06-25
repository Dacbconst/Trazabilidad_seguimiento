<div class="agenda-filters" id="agendaFiltros">
    <div class="filter-group">
        <label>Búsqueda rápida</label>
        <div class="input-group">
            <input type="text" class="form-control" id="agendaBusqueda" placeholder="Buscar...">
            <span class="input-group-addon"><i class="glyphicon glyphicon-search"></i></span>
        </div>
    </div>
    <div class="filter-group">
        <label>Promotor</label>
        <select class="form-control" id="agendaFiltroPromotor">
            <option value="">Todos</option>
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

    <div class="agenda-filters-legend">
        <span class="agenda-legend-item"><span class="agenda-legend-dot is-pendiente"></span><strong id="agendaCountPendientes">0</strong> Pendientes</span>
        <span class="agenda-legend-item"><span class="agenda-legend-dot is-confirmado"></span><strong id="agendaCountConfirmadas">0</strong> Confirmadas</span>
        <span class="agenda-legend-item"><span class="agenda-legend-dot is-reagendada"></span><strong id="agendaCountReagendadas">0</strong> Reagendadas</span>
        <span class="agenda-legend-item"><span class="agenda-legend-dot is-vencida"></span><strong id="agendaCountVencidas">0</strong> Vencidas</span>
        <span class="agenda-legend-item"><span class="agenda-legend-dot is-cancelada"></span><strong id="agendaCountCanceladas">0</strong> Canceladas</span>
    </div>

    <button type="button" class="btn btn-actualizar" id="agendaBtnActualizar">Actualizar</button>
</div>

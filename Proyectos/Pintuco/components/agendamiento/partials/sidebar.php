<aside class="gcal-sidebar">
    <button type="button" class="gcal-crear-btn" id="agendaCrearBtn">
        <i class="glyphicon glyphicon-plus"></i>
        <span>Crear</span>
        <i class="glyphicon glyphicon-triangle-bottom gcal-crear-caret"></i>
    </button>

    <div class="gcal-mini-calendar-wrap" id="agendaMiniCalendarWrap">
        <button type="button" class="gcal-mini-header-bar" id="agendaMiniToggle" title="Mostrar/ocultar mini-calendario">
            <i class="glyphicon glyphicon-calendar"></i>
            <span id="agendaMiniHeaderLabel">Calendario</span>
            <i class="glyphicon glyphicon-chevron-up gcal-mini-header-chevron"></i>
        </button>
        <div id="agendaMiniCalendar" class="gcal-mini-calendar"></div>
        <div class="gcal-mini-yearpicker" id="agendaMiniYearPicker">
            <div class="gcal-mini-yearpicker-header">
                <button type="button" class="gcal-mini-yearpicker-arrow" id="agendaMiniYearPrev"><i class="glyphicon glyphicon-chevron-up"></i></button>
                <span id="agendaMiniYearLabel">2026</span>
                <button type="button" class="gcal-mini-yearpicker-arrow" id="agendaMiniYearNext"><i class="glyphicon glyphicon-chevron-down"></i></button>
            </div>
            <div class="gcal-mini-yearpicker-grid" id="agendaMiniYearGrid"></div>
        </div>
    </div>

    <div class="gcal-pendientes">
        <div class="gcal-pendientes-title">
            Agendas pendientes
            <span class="gcal-pendientes-count" id="agendaPendientesCount">0</span>
        </div>
        <ul class="gcal-pendientes-list" id="agendaPendientesList">
            <li class="gcal-pendientes-empty">Sin agendas pendientes</li>
        </ul>
    </div>
</aside>

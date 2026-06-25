<div class="agenda-edit-overlay" id="agendaEditOverlay">
    <div class="agenda-edit-card">
        <button type="button" class="agenda-edit-close" id="agendaEditClose" aria-label="Cerrar">&times;</button>

        <div class="agenda-edit-header">
            <h4 class="agenda-edit-title" id="agendaEditTitulo"></h4>
            <span class="agenda-edit-badge" id="agendaEditBadge">Pendiente</span>
        </div>

        <div class="agenda-edit-registro" id="agendaEditRegistro"></div>

        <div class="agenda-edit-alert" id="agendaEditAlerta" style="display:none">
            <i class="glyphicon glyphicon-warning-sign"></i>
            <span id="agendaEditAlertaTexto"></span>
        </div>

        <div class="agenda-edit-divider"></div>

        <div class="agenda-edit-info">
            <div class="agenda-edit-info-row">
                <i class="glyphicon glyphicon-briefcase"></i>
                <div class="agenda-edit-info-text">
                    <span class="agenda-edit-info-label">Promotor</span>
                    <span class="agenda-edit-info-value" id="agendaEditPromotor">—</span>
                </div>
            </div>
            <div class="agenda-edit-info-row">
                <i class="glyphicon glyphicon-home"></i>
                <div class="agenda-edit-info-text">
                    <span class="agenda-edit-info-label">Local</span>
                    <span class="agenda-edit-info-value" id="agendaEditLocal">—</span>
                </div>
            </div>
            <div class="agenda-edit-info-row">
                <i class="glyphicon glyphicon-building"></i>
                <div class="agenda-edit-info-text">
                    <span class="agenda-edit-info-label">Empresa</span>
                    <span class="agenda-edit-info-value" id="agendaEditEmpresa">—</span>
                </div>
            </div>
            <div class="agenda-edit-info-row">
                <i class="glyphicon glyphicon-envelope"></i>
                <div class="agenda-edit-info-text">
                    <span class="agenda-edit-info-label">Correo</span>
                    <span class="agenda-edit-info-value" id="agendaEditMail">—</span>
                </div>
            </div>
            <div class="agenda-edit-info-row">
                <i class="glyphicon glyphicon-map-marker"></i>
                <div class="agenda-edit-info-text">
                    <span class="agenda-edit-info-label">Dirección</span>
                    <span class="agenda-edit-info-value" id="agendaEditDireccion">—</span>
                </div>
            </div>
            <div class="agenda-edit-info-row">
                <i class="glyphicon glyphicon-earphone"></i>
                <div class="agenda-edit-info-text">
                    <span class="agenda-edit-info-label">Teléfono</span>
                    <span class="agenda-edit-info-value" id="agendaEditTelefono">—</span>
                </div>
            </div>
        </div>

        <div class="agenda-edit-divider"></div>

        <div class="agenda-edit-row">
            <i class="glyphicon glyphicon-calendar"></i>
            <input type="date" class="form-control" id="agendaEditFecha">
        </div>
        <div class="agenda-edit-row">
            <i class="glyphicon glyphicon-time"></i>
            <input type="time" class="form-control" id="agendaEditHora">
        </div>
        <div class="agenda-edit-row">
            <i class="glyphicon glyphicon-user"></i>
            <input type="text" class="form-control" id="agendaEditTecnico" placeholder="Técnico asignado">
        </div>

        <div class="agenda-edit-actions">
            <div class="agenda-edit-actions-left">
                <button type="button" class="agenda-edit-cancelar-visita" id="agendaEditCancelarVisita">Cancelar visita</button>
                <button type="button" class="agenda-edit-eliminar" id="agendaEditEliminar"><i class="glyphicon glyphicon-trash"></i> Eliminar</button>
            </div>
            <div class="agenda-edit-actions-right">
                <button type="button" class="btn" id="agendaEditCancelar">Cerrar</button>
                <button type="button" class="btn btn-actualizar" id="agendaEditGuardar">Guardar</button>
            </div>
        </div>
    </div>
</div>

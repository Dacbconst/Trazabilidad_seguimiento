<div class="agenda-edit-overlay" id="agendaEditOverlay">
    <div class="agenda-edit-card">
        <button type="button" class="agenda-edit-close" id="agendaEditClose" aria-label="Cerrar">&times;</button>

        <div class="agenda-edit-header">
            <h4 class="agenda-edit-title" id="agendaEditTitulo"></h4>
            <span class="agenda-edit-badge" id="agendaEditBadge">Pendiente</span>
        </div>

        <div class="agenda-edit-body">

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

            <div class="agenda-edit-fecha-label" id="agendaEditFechaLabel">Fecha agendada</div>
            <div class="agenda-edit-row" data-campo="fecha">
                <i class="glyphicon glyphicon-calendar"></i>
                <span class="agenda-edit-row-texto" id="agendaEditFechaTexto"></span>
                <input type="date" class="form-control" id="agendaEditFecha">
                <button type="button" class="agenda-edit-row-lapiz" data-campo="fecha" title="Editar fecha"><i class="glyphicon glyphicon-pencil"></i></button>
            </div>
            <div class="agenda-edit-row" data-campo="hora">
                <i class="glyphicon glyphicon-time"></i>
                <span class="agenda-edit-row-texto" id="agendaEditHoraTexto"></span>
                <div class="agenda-edit-hora-dropdown" id="agendaEditHora">
                    <button type="button" class="form-control agenda-edit-hora-trigger" id="agendaEditHoraTrigger">Selecciona una hora</button>
                    <div class="agenda-edit-hora-lista" id="agendaEditHoraLista"></div>
                </div>
                <button type="button" class="agenda-edit-row-lapiz" data-campo="hora" title="Editar hora"><i class="glyphicon glyphicon-pencil"></i></button>
            </div>
            <div class="agenda-edit-row" data-campo="tecnico">
                <i class="glyphicon glyphicon-user"></i>
                <span class="agenda-edit-row-texto" id="agendaEditTecnicoTexto"></span>
                <input type="text" class="form-control" id="agendaEditTecnico" placeholder="Técnico asignado">
                <button type="button" class="agenda-edit-row-lapiz" data-campo="tecnico" title="Editar técnico"><i class="glyphicon glyphicon-pencil"></i></button>
            </div>

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

<div class="agenda-conflicto-overlay" id="agendaConflictoOverlay">
    <div class="agenda-conflicto-card">
        <h4>¡Oh no! Tienes una visita a esa hora</h4>

        <div class="agenda-conflicto-mini">
            <span class="agenda-conflicto-mini-fecha" id="agendaConflictoMiniFecha"></span>
            <div class="agenda-conflicto-mini-evento" id="agendaConflictoMiniEvento">
                <div class="gcal-event-content">
                    <div class="gcal-event-title" id="agendaConflictoMiniTitulo"></div>
                    <div class="gcal-event-time" id="agendaConflictoMiniHora"></div>
                </div>
            </div>
        </div>

        <p>Selecciona otra hora disponible.</p>

        <button type="button" class="btn" id="agendaConflictoCerrar">Cerrar</button>
    </div>
</div>

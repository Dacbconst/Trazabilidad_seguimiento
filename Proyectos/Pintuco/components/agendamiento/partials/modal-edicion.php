<div class="agenda-edit-overlay" id="agendaEditOverlay">
    <div class="agenda-edit-card" id="agendaEditCard">
        <button type="button" class="agenda-edit-close" id="agendaEditClose" aria-label="Cerrar">&times;</button>

        <div class="agenda-edit-body">
            <div class="agenda-edit-form-col">

                <div class="agenda-edit-header">
                    <div>
                        <h4 class="agenda-edit-title" id="agendaEditTitulo"></h4>
                        <div class="agenda-edit-registro" id="agendaEditRegistro"></div>
                    </div>
                    <label class="agenda-edit-mode-switch">
                        <input type="checkbox" id="agendaEditModoEdicion">
                        <span class="agenda-edit-mode-slider"></span>
                        <span id="agendaEditModoTexto">Modo edición</span>
                    </label>
                </div>

                <div class="agenda-edit-badge-row">
                    <span class="agenda-edit-badge" id="agendaEditBadge">
                        <span class="agenda-edit-badge-dot"></span>
                        <span id="agendaEditBadgeTexto">Pendiente</span>
                    </span>
                </div>

                <div class="agenda-edit-alert" id="agendaEditAlerta" style="display:none">
                    <i class="glyphicon glyphicon-warning-sign"></i>
                    <span id="agendaEditAlertaTexto"></span>
                </div>

                <div class="agenda-edit-divider"></div>

                <!-- Promotor y Local: siempre solo texto, sin importar el
                     switch de edición — están fuera de alcance a propósito. -->
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
                </div>

                <div class="agenda-edit-divider"></div>

                <!-- Grid con grid-area nombrada: el orden visual de estos 5
                     campos cambia por completo entre modo vista y modo
                     edición (ver agenda.css .agenda-edit-contacto-grid), sin
                     duplicar HTML — cada celda vive en su grid-area fija y el
                     contenedor es el que reordena. -->
                <div class="agenda-edit-contacto-grid">
                    <div class="agenda-edit-campo-v2" data-area="empresa" data-campo="empresa">
                        <div class="agenda-edit-campo-v2-header">
                            <i class="glyphicon glyphicon-briefcase"></i>
                            <span>Empresa</span>
                        </div>
                        <span class="agenda-edit-campo-v2-valor" id="agendaEditEmpresaTexto">—</span>
                        <input type="text" class="agenda-edit-campo-v2-input" id="agendaEditEmpresa" placeholder="Nombre de la empresa" maxlength="80">
                    </div>
                    <div class="agenda-edit-campo-v2" data-area="correo" data-campo="mail">
                        <div class="agenda-edit-campo-v2-header">
                            <i class="glyphicon glyphicon-envelope"></i>
                            <span>Correo</span>
                        </div>
                        <span class="agenda-edit-campo-v2-valor" id="agendaEditMailTexto">—</span>
                        <input type="email" class="agenda-edit-campo-v2-input" id="agendaEditMail" placeholder="correo@dominio.com">
                    </div>
                    <div class="agenda-edit-campo-v2" data-area="celular" data-campo="celular">
                        <div class="agenda-edit-campo-v2-header">
                            <i class="glyphicon glyphicon-earphone"></i>
                            <span>Celular</span>
                        </div>
                        <span class="agenda-edit-campo-v2-valor" id="agendaEditCelularTexto">—</span>
                        <input type="text" class="agenda-edit-campo-v2-input" id="agendaEditCelular" placeholder="0987654321" maxlength="10">
                    </div>
                    <div class="agenda-edit-campo-v2" data-area="direccion" data-campo="direccion">
                        <div class="agenda-edit-campo-v2-header">
                            <i class="glyphicon glyphicon-map-marker"></i>
                            <span>Dirección</span>
                        </div>
                        <span class="agenda-edit-campo-v2-valor" id="agendaEditDireccionTexto">—</span>
                        <input type="text" class="agenda-edit-campo-v2-input" id="agendaEditDireccion" placeholder="Se completa al confirmar el pin">
                    </div>
                    <div class="agenda-edit-campo-v2" data-area="convencional" data-campo="convencional">
                        <div class="agenda-edit-campo-v2-header">
                            <i class="glyphicon glyphicon-phone"></i>
                            <span>Convencional</span>
                            <span class="agenda-edit-campo-v2-opcional">(opcional)</span>
                        </div>
                        <span class="agenda-edit-campo-v2-valor" id="agendaEditConvencionalTexto">—</span>
                        <input type="text" class="agenda-edit-campo-v2-input" id="agendaEditConvencional" placeholder="022345678" maxlength="9">
                    </div>
                </div>

                <div class="agenda-edit-divider"></div>

                <div class="agenda-edit-agendar-titulo">
                    Agendar visita
                    <span class="agenda-edit-fecha-label" id="agendaEditFechaLabel">Fecha agendada</span>
                </div>
                <div class="agenda-edit-agendar-grid">
                    <label class="agenda-edit-agendar-campo" data-campo="fecha">
                        <i class="glyphicon glyphicon-calendar"></i>
                        <input type="date" id="agendaEditFecha">
                    </label>
                    <div class="agenda-edit-agendar-campo" data-campo="hora">
                        <i class="glyphicon glyphicon-time"></i>
                        <div class="agenda-edit-hora-dropdown" id="agendaEditHora">
                            <button type="button" class="agenda-edit-hora-trigger" id="agendaEditHoraTrigger">Selecciona una hora</button>
                            <div class="agenda-edit-hora-lista" id="agendaEditHoraLista"></div>
                        </div>
                    </div>
                </div>
                <label class="agenda-edit-agendar-campo agenda-edit-agendar-tecnico" data-campo="tecnico">
                    <i class="glyphicon glyphicon-user"></i>
                    <input type="text" id="agendaEditTecnico" placeholder="Técnico asignado">
                </label>

                <div class="agenda-edit-actions">
                    <div class="agenda-edit-actions-left">
                        <button type="button" class="agenda-edit-eliminar" id="agendaEditEliminar"><i class="glyphicon glyphicon-trash"></i> Eliminar</button>
                        <button type="button" class="agenda-edit-cancelar-visita" id="agendaEditCancelarVisita">Cancelar visita</button>
                    </div>
                    <div class="agenda-edit-actions-right">
                        <button type="button" class="btn" id="agendaEditCancelar">Cerrar</button>
                        <button type="button" class="btn btn-actualizar" id="agendaEditGuardar">Guardar</button>
                    </div>
                </div>
            </div>

            <!-- Columna de mapa: misma UX que "Crear visita" (Leaflet + pin
                 fijo + reverse-geocode Mapbox). Solo visible cuando la card
                 tiene la clase is-editando (ver agenda.css). Sin badge de
                 estado aquí a propósito (se quitó por pedido explícito). -->
            <div class="agenda-edit-mapa-col" id="agendaEditMapaCol">
                <label>Ubicación en el mapa</label>
                <div class="agenda-edit-mapa-wrap">
                    <div class="agenda-edit-mapa-pin" id="agendaEditMapaPin"></div>
                    <div class="agenda-crear-mapa-pin-fijo"><i class="glyphicon glyphicon-map-marker"></i></div>
                </div>
                <button type="button" class="agenda-crear-btn-secundario" id="agendaEditConfirmarPin">Confirmar pin</button>
                <span class="agenda-crear-mapa-hint">Navega el mapa hasta ubicar el sitio exacto, luego confirma para sincronizar la dirección.</span>
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

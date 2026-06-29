<div class="agenda-edit-overlay" id="agendaCrearOverlay">
    <div class="agenda-crear-card">
        <button type="button" class="agenda-edit-close" id="agendaCrearClose" aria-label="Cerrar">&times;</button>

        <!-- HEADER -->
        <div class="agenda-crear-header">
            <h4 class="agenda-edit-title">Visita Tecnica</h4>
            <span class="agenda-crear-fecha-registro" id="agendaCrearFechaRegistro"></span>
        </div>

        <!-- BODY: 2 columnas (flex-row desktop / flex-col móvil, ver
             media query en agenda.css). Izquierda = formulario en 3
             bloques lógicos separados por <hr>; derecha = mapa, panel
             fijo que NO se mete entre los campos. -->
        <div class="agenda-crear-body">

            <div class="agenda-crear-form-col">

                <div class="agenda-crear-bloque agenda-crear-grid">
                    <div class="agenda-crear-campo">
                        <label>Promotor *</label>
                        <select class="form-control" id="agendaCrearPromotor">
                            <option value="">Seleccione un promotor</option>
                        </select>
                        <span class="agenda-crear-error" id="agendaCrearErrPromotor"></span>
                    </div>
                    <div class="agenda-crear-campo">
                        <label>Punto de venta (PDV) *</label>
                        <select class="form-control" id="agendaCrearPdv">
                            <option value="">Seleccione un PDV</option>
                        </select>
                        <span class="agenda-crear-error" id="agendaCrearErrPdv"></span>
                    </div>
                    <div class="agenda-crear-campo">
                        <label>Contacto *</label>
                        <input type="text" class="form-control" id="agendaCrearContacto" placeholder="Nombre del contacto" maxlength="80">
                        <span class="agenda-crear-error" id="agendaCrearErrContacto"></span>
                    </div>
                    <div class="agenda-crear-campo">
                        <label>Empresa *</label>
                        <input type="text" class="form-control" id="agendaCrearEmpresa" placeholder="Nombre de la empresa" maxlength="80">
                        <span class="agenda-crear-error" id="agendaCrearErrEmpresa"></span>
                    </div>
                </div>

                <hr class="agenda-crear-divisor">

                <div class="agenda-crear-bloque agenda-crear-grid">
                    <div class="agenda-crear-campo agenda-crear-campo-full">
                        <label>Correo *</label>
                        <input type="email" class="form-control" id="agendaCrearMail" placeholder="correo@dominio.com" autocomplete="off">
                        <div class="agenda-crear-mail-sugerencias" id="agendaCrearMailSugerencias"></div>
                        <span class="agenda-crear-error" id="agendaCrearErrMail"></span>
                    </div>
                    <div class="agenda-crear-campo">
                        <label>Celular *</label>
                        <input type="text" class="form-control" id="agendaCrearCelular" placeholder="0987654321" maxlength="10">
                        <span class="agenda-crear-error" id="agendaCrearErrCelular"></span>
                    </div>
                    <div class="agenda-crear-campo">
                        <label>Teléfono convencional</label>
                        <input type="text" class="form-control" id="agendaCrearConvencional" placeholder="022345678 (opcional)" maxlength="9">
                        <span class="agenda-crear-error" id="agendaCrearErrConvencional"></span>
                    </div>
                    <div class="agenda-crear-campo agenda-crear-campo-full">
                        <label>Dirección *</label>
                        <input type="text" class="form-control" id="agendaCrearDireccion" placeholder="Se completa al confirmar el pin" maxlength="160">
                        <span class="agenda-crear-error" id="agendaCrearErrDireccion"></span>
                    </div>
                </div>

                <hr class="agenda-crear-divisor">

                <div class="agenda-crear-bloque agenda-crear-grid">
                    <div class="agenda-crear-campo">
                        <label>Fecha de agendamiento *</label>
                        <input type="date" class="form-control" id="agendaCrearFechaAgenda">
                        <span class="agenda-crear-error" id="agendaCrearErrFechaAgenda"></span>
                    </div>
                    <div class="agenda-crear-campo">
                        <label>Hora *</label>
                        <input type="time" class="form-control" id="agendaCrearHora">
                        <span class="agenda-crear-error" id="agendaCrearErrHora"></span>
                    </div>
                    <div class="agenda-crear-campo agenda-crear-campo-full">
                        <label>Técnico *</label>
                        <input type="text" class="form-control" id="agendaCrearTecnico" placeholder="Técnico asignado" maxlength="60">
                        <span class="agenda-crear-error" id="agendaCrearErrTecnico"></span>
                    </div>
                </div>

            </div>

            <div class="agenda-crear-mapa-col">
                <label>Ubicación en el mapa *</label>
                <!-- El pin NO se arrastra — está fijo en el centro de la
                     vista. El analista navega el mapa (paneo/zoom/buscar
                     visualmente) hasta dejar el lugar exacto debajo del
                     pin; así nunca "se pierde" el pin al alejar el zoom
                     para ubicarse. -->
                <div class="agenda-crear-mapa-wrap">
                    <div class="agenda-crear-mapa-pin" id="agendaCrearMapaPin"></div>
                    <div class="agenda-crear-mapa-pin-fijo"><i class="glyphicon glyphicon-map-marker"></i></div>
                </div>
                <button type="button" class="agenda-crear-btn-secundario" id="agendaCrearConfirmarPin">Confirmar pin</button>
                <span class="agenda-crear-mapa-hint">Navega el mapa hasta ubicar el sitio exacto debajo del pin y confirma.</span>
            </div>

        </div>

        <!-- FOOTER -->
        <div class="agenda-crear-footer">
            <button type="button" class="agenda-crear-btn-cancelar" id="agendaCrearCancelar">Cancelar</button>
            <button type="button" class="btn btn-actualizar" id="agendaCrearGuardar">Guardar</button>
        </div>

        <div class="agenda-crear-toast" id="agendaCrearToast"></div>
    </div>
</div>

<nav id="sidebar">
    <div class="sidebar-header" style="padding:10px 15px;">
        <h3 style="margin:0; font-size:16px;">Filtros</h3>
    </div>

    <ul class="list-unstyled components" style="padding:6px 0; border-bottom:1px solid #47748b;">
        <li>
            <div class="form-group" style="margin:0 5% 5px;">
                <label style="font-size:11px; margin-bottom:2px; color:#ddd;">Fecha Inicio</label>
                <input type="date" class="form-control input-sm fecha-limitada"
                       id="fechaInicio" name="fechaInicio" required>
            </div>
        </li>
        <li>
            <div class="form-group" style="margin:0 5% 5px;">
                <label style="font-size:11px; margin-bottom:2px; color:#ddd;">Fecha Fin</label>
                <input type="date" class="form-control input-sm fecha-limitada"
                       id="fechaFin" name="fechaFin" required>
            </div>
        </li>
        <li>
            <div class="form-group" style="margin:0 5% 5px;">
                <label style="font-size:11px; margin-bottom:2px; color:#ddd;">Reporte</label>
                <select class="form-control input-sm" id="reportes" name="reportes" required>
                    <option value="Seleccione">Seleccione</option>
                    <option value="vi_evidencias">Antes y Después</option>
                    <option value="exhibiciones">Exhibiciones</option>
                </select>
            </div>
        </li>
        <!-- RQFOTOGRAFICODACB: Tipo solo visible para Exhibiciones -->
        <li id="li-tipo" style="display:none;">
            <div class="form-group" style="margin:0 5% 5px;">
                <label style="font-size:11px; margin-bottom:2px; color:#ddd;">Tipo</label>
                <select class="form-control input-sm" id="tipos" name="tipos">
                    <option value=".">Todos</option>
                </select>
            </div>
        </li>
        <li>
            <div class="form-group" style="margin:0 5% 5px;">
                <label style="font-size:11px; margin-bottom:2px; color:#ddd;">Supervisor</label>
                <select class="form-control input-sm" id="supervisores" name="supervisores">
                    <option value=".">Todos</option>
                </select>
            </div>
        </li>
        <li>
            <div class="form-group" style="margin:0 5% 5px;">
                <label style="font-size:11px; margin-bottom:2px; color:#ddd;">Gestor</label>
                <select class="form-control input-sm" id="mercaderistas" name="mercaderistas">
                    <option value=".">Todos</option>
                </select>
            </div>
        </li>
        <!-- Categoría solo visible para Exhibiciones -->
        <li id="li-categoria" style="display:none;">
            <div class="form-group" style="margin:0 5% 5px;">
                <label style="font-size:11px; margin-bottom:2px; color:#ddd;">Categoría</label>
                <select class="form-control input-sm" id="categorias" name="categorias">
                    <option value=".">Todas</option>
                </select>
            </div>
        </li>
        <li>
            <div class="form-group" style="margin:0 5% 5px;">
                <label style="font-size:11px; margin-bottom:2px; color:#ddd;">Cadena</label>
                <select class="form-control input-sm" id="cadenas" name="cadenas">
                    <option value=".">Todas</option>
                </select>
            </div>
        </li>
        <li>
            <div class="form-group" style="margin:0 5% 5px;">
                <label style="font-size:11px; margin-bottom:2px; color:#ddd;">Ciudad</label>
                <select class="form-control input-sm" id="ciudades" name="ciudades">
                    <option value=".">Todas</option>
                </select>
            </div>
        </li>
        <li>
            <div class="form-group" style="margin:0 5% 5px;">
                <label style="font-size:11px; margin-bottom:2px; color:#ddd;">Local</label>
                <select class="form-control input-sm" id="locales" name="locales">
                    <option value=".">Todos</option>
                </select>
            </div>
        </li>
    </ul>

    <!-- Botón Aplicar -->
    <ul class="list-unstyled CTAs" style="padding:8px 15px 4px;">
        <li>
            <a href="#" class="download" id="filter" name="filter"
               style="font-size:13px; padding:7px 10px; border-radius:4px; display:block;
                      text-align:center; background:#fff; color:#7386D5; font-weight:600;">
                <i class="glyphicon glyphicon-search"></i> Aplicar Filtros
            </a>
        </li>
    </ul>

</nav>

<script>
    var checkAllButton = '<button id="checkAll" class="btn btn-default" style="margin-left:6px;">Seleccionar todo</button>' +
                         '<button id="clearAll" class="btn btn-default" style="margin-left:6px;display:none;">Deseleccionar todo</button>';
    var divParent = $('#download2');
    var optionTodas = '<option value=".">Todas</option>';
    var optionTodos = '<option value=".">Todos</option>';

    function safe(v) {
        return (v === undefined || v === null) ? "" : String(v);
    }

    // Reportes simples (vi_evidencias, etc.): al marcar/desmarcar una card a mano,
    // actualizar el botón "Deseleccionar todo" y el badge contador.
    $(document).on('change', '#data .evid-main-cb', function() {
        $(this).closest('.card').toggleClass('card-selected', this.checked);
        if (typeof window.exhUpdateClearBtn === 'function') window.exhUpdateClearBtn();
        if (typeof window.evidUpdateSimpleCounter === 'function') window.evidUpdateSimpleCounter();
    });

    function fechaCompleta(v) { return /^\d{4}-\d{2}-\d{2}$/.test(v) && parseInt(v) >= 2000; }

    // Validacion de rango de fechas
    document.getElementById('fechaFin').addEventListener('change', function() {
        var ini = document.getElementById('fechaInicio').value;
        var fin = this.value;
        if (!fechaCompleta(ini) || !fechaCompleta(fin)) return;
        if (new Date(fin) < new Date(ini)) {
            alert("La fecha FIN no puede ser menor que la fecha INICIO.");
            this.value = "";
            this.focus();
        }
    });

    document.querySelectorAll('.fecha-limitada').forEach(function(input) {
        input.addEventListener('input', function() {
            var partes = this.value.split('-');
            if (partes.length < 3) return;
            if (partes[0].length > 4) {
                partes[0] = partes[0].slice(0, 4);
                this.value = partes.join('-');
            }
        });
    });

    // RQFOTOGRAFICODACB: getTipos solo para Exhibiciones
    function getTipos(fecha_inicio, fecha_fin) {
        $.ajax({
            type: "POST",
            url: "getters/getTipos.php",
            data: "&fecha_inicio=" + fecha_inicio + "&fecha_fin=" + fecha_fin,
            success: function(data) { $("#tipos").html(data); }
        });
    }

    function getSupervisores(fecha_inicio, fecha_fin, reporte, tipo) {
        $.ajax({
            type: "POST",
            url: "getters/getSupervisores.php",
            data: "&fecha_inicio=" + fecha_inicio + "&fecha_fin=" + fecha_fin + "&reporte=" + reporte + "&tipo=" + encodeURIComponent(tipo || '.'),
            success: function(data) { $("#supervisores").html(data); }
        });
    }

    function getMercaderistas(fecha_inicio, fecha_fin, reporte, supervisor, tipo) {
        $.ajax({
            type: "POST",
            url: "getters/getMercaderistas.php",
            data: "&fecha_inicio=" + fecha_inicio + "&fecha_fin=" + fecha_fin + "&reporte=" + reporte + "&supervisor=" + (supervisor || '.') + "&tipo=" + encodeURIComponent(tipo || '.'),
            success: function(data) { $("#mercaderistas").html(data); }
        });
    }

    function getCategorias(fecha_inicio, fecha_fin, supervisor, gestor, tipo) {
        $.ajax({
            type: "POST",
            url: "getters/getCategorias.php",
            data: "&fecha_inicio=" + fecha_inicio + "&fecha_fin=" + fecha_fin + "&supervisor=" + (supervisor || '.') + "&gestor=" + (gestor || '.') + "&tipo=" + encodeURIComponent(tipo || '.'),
            success: function(data) { $("#categorias").html(data); }
        });
    }

    function getCadenas(fecha_inicio, fecha_fin, reporte, supervisor, gestor, categoria, tipo) {
        $.ajax({
            type: "POST",
            url: "getters/getCadenas.php",
            data: "&fecha_inicio=" + fecha_inicio + "&fecha_fin=" + fecha_fin + "&reporte=" + reporte + "&supervisor=" + supervisor + "&gestor=" + gestor + "&categoria=" + encodeURIComponent(categoria || '.') + "&tipo=" + encodeURIComponent(tipo || '.'),
            success: function(data) { $("#cadenas").html(data); }
        });
    }

    function getCiudades(fecha_inicio, fecha_fin, reporte, supervisor, gestor, cadena, categoria, tipo) {
        $.ajax({
            type: "POST",
            url: "getters/getCiudades.php",
            data: "&fecha_inicio=" + fecha_inicio + "&fecha_fin=" + fecha_fin + "&reporte=" + reporte + "&supervisor=" + supervisor + "&gestor=" + gestor + "&cadena=" + cadena + "&categoria=" + encodeURIComponent(categoria || '.') + "&tipo=" + encodeURIComponent(tipo || '.'),
            success: function(data) { $("#ciudades").html(data); }
        });
    }

    function getLocales(fecha_inicio, fecha_fin, reporte, supervisor, gestor, cadena, ciudad, categoria, tipo) {
        $.ajax({
            type: "POST",
            url: "getters/getLocales.php",
            data: "&fecha_inicio=" + fecha_inicio + "&fecha_fin=" + fecha_fin + "&reporte=" + reporte + "&supervisor=" + supervisor + "&gestor=" + gestor + "&cadena=" + cadena + "&ciudad=" + ciudad + "&categoria=" + encodeURIComponent(categoria || '.') + "&tipo=" + encodeURIComponent(tipo || '.'),
            success: function(data) { $("#locales").html(data); }
        });
    }

    function getData(reporte, fecha_inicio, fecha_fin, supervisor, gestor, categoria, cadena, ciudad, local, tipo) {
        var formdata = "&fecha_inicio=" + fecha_inicio + "&fecha_fin=" + fecha_fin +
            "&reporte=" + reporte + "&supervisor=" + supervisor + "&mercaderista=" + gestor +
            "&categoria=" + encodeURIComponent(categoria || '.') +
            "&cadena=" + cadena + "&ciudad=" + ciudad + "&local=" + local +
            "&tipo=" + encodeURIComponent(tipo || '.');

        var check = false;
        var url = (reporte === 'exhibiciones') ? 'getters/getDataExhibiciones.php' : 'getters/getData.php';

        $.ajax({
            type: "GET",
            url: url,
            data: formdata,
            dataType: "json",
            beforeSend: function() {
                $('#checkAll, #clearAll').remove();
                check = false;
                if (typeof exhState !== 'undefined') exhState = {};
                var badge = document.getElementById('selection-count');
                if (badge) { badge.textContent = ''; badge.style.display = 'none'; }
            },
            success: function(response) {
                if (!response.count) {
                    $("#data").html(
                        "<tr><td colspan='3'>" +
                        "<div style='display:flex; flex-direction:column; align-items:center; justify-content:center; height:300px; color:#aaa;'>" +
                        "<i class='glyphicon glyphicon-search' style='font-size:48px; margin-bottom:16px;'></i>" +
                        "<h3 style='margin:0 0 8px; color:#999;'>Sin resultados</h3>" +
                        "<p style='margin:0; font-size:13px;'>No se encontraron resultados</p>" +
                        "</div></td></tr>"
                    );

                    var infoBoxEmpty = document.querySelector('#info_resultados');
                    infoBoxEmpty.innerHTML = "ℹ️ Resultados encontrados: 0 registros";
                    infoBoxEmpty.classList.add("info-highlight");
                    setTimeout(function() { infoBoxEmpty.classList.remove("info-highlight"); }, 950);
                    return;
                }

                $("#data").html(response.html);
                if (reporte === 'exhibiciones') {
                    exhInitAll();
                } else {
                    initLazyImages();
                    exhSetupPagination();
                }
                divParent.after(checkAllButton);

                // Habilitar botones en header principal
                document.querySelector('#download2').disabled = false;

                var infoBox = document.querySelector('#info_resultados');
                infoBox.innerHTML = "ℹ️ Resultados encontrados: " + response.count + " registros";
                infoBox.classList.add("info-highlight");
                setTimeout(function() { infoBox.classList.remove("info-highlight"); }, 950);

                // Badge contador para reportes simples (vi_evidencias, etc.)
                function updateSimpleCounter() {
                    var total = document.querySelectorAll('#data .evid-main-cb:checked').length;
                    var badge = document.getElementById('selection-count');
                    if (badge) {
                        badge.textContent  = total > 0 ? total + ' local' + (total !== 1 ? 'es' : '') + ' seleccionado' + (total !== 1 ? 's' : '') : '';
                        badge.style.display = total > 0 ? 'inline-flex' : 'none';
                    }
                }

                // Selecciona/deselecciona todas las cards actualizando exhState
                function updateClearBtn() {
                    var haySeleccion;
                    if (document.querySelectorAll('.exh-card').length > 0) {
                        haySeleccion = Object.keys(exhState).some(function(id) {
                            return exhState[id] && exhState[id].selected.size > 0;
                        });
                    } else {
                        haySeleccion = document.querySelectorAll('#data .evid-main-cb:checked').length > 0;
                    }
                    var btn = document.getElementById('clearAll');
                    if (btn) btn.style.display = haySeleccion ? '' : 'none';
                }

                function toggleAll() {
                    var selecting = !check;
                    check = selecting;

                    if (document.querySelectorAll('.exh-card').length > 0) {
                        document.querySelectorAll('.exh-card').forEach(function(card) {
                            var cardId = card.id;
                            if (!cardId || !exhState[cardId]) return;
                            var images = JSON.parse(card.dataset.images || '[]');
                            var cardCb = card.querySelector('.exh-main-cb');
                            if (selecting) {
                                exhState[cardId].selected = new Set(images.map(function(_, i) { return i; }));
                                if (cardCb) { cardCb.checked = true; cardCb.indeterminate = false; }
                                card.classList.add('exh-active');
                                images.forEach(function(_, i) {
                                    var pcb = document.getElementById('photo_cb_' + cardId + '_' + i);
                                    if (pcb) pcb.checked = true;
                                });
                            } else {
                                exhState[cardId].selected.clear();
                                if (cardCb) { cardCb.checked = false; cardCb.indeterminate = false; }
                                card.classList.remove('exh-active');
                                images.forEach(function(_, i) {
                                    var pcb = document.getElementById('photo_cb_' + cardId + '_' + i);
                                    if (pcb) pcb.checked = false;
                                });
                            }
                        });
                        // Actualizar el badge contador del header
                        if (typeof exhActualizarContador === 'function') exhActualizarContador();
                    } else {
                        document.querySelectorAll('#data .evid-main-cb').forEach(function(cb) {
                            cb.checked = selecting;
                            var card = cb.closest('.card');
                            if (card) card.classList.toggle('card-selected', selecting);
                        });
                        updateSimpleCounter();
                    }

                    updateClearBtn();
                }

                function deselectAll() {
                    check = true; // para que toggleAll lo flipee a false
                    toggleAll();
                }

                $("#checkAll").click(function(e) { e.preventDefault(); toggleAll(); });
                $(document).off('click', '#clearAll').on('click', '#clearAll', function(e) {
                    e.preventDefault(); deselectAll();
                });

                // Exponer updateClearBtn/updateSimpleCounter para usarlas al cambiar selección manualmente
                window.exhUpdateClearBtn = updateClearBtn;
                window.evidUpdateSimpleCounter = updateSimpleCounter;
            }
        });
    }

    function initLazyImages() {
        var imgs = document.querySelectorAll('img.lazy-img');
        if (!imgs.length) return;
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                            img.style.background = '';
                        }
                        observer.unobserve(img);
                    }
                });
            }, { rootMargin: '200px 0px' });
            imgs.forEach(function(img) { observer.observe(img); });
        } else {
            imgs.forEach(function(img) {
                if (img.dataset.src) { img.src = img.dataset.src; img.removeAttribute('data-src'); }
            });
        }
    }

    $(document).ready(function() {

        // Al cambiar cualquier fecha: resetear y recargar cascade completo desde supervisores
        function recargarCascade() {
            var fecha_inicio = $('#fechaInicio').val();
            var fecha_fin    = $('#fechaFin').val();
            var reporte      = $('#reportes').val();
            if (!fechaCompleta(fecha_inicio) || !fechaCompleta(fecha_fin) || reporte === 'Seleccione') return;
            var tipo = $('#tipos').val() || '.';

            $('#supervisores').empty().append(optionTodos);
            $('#mercaderistas').empty().append(optionTodos);
            $('#categorias').html('<option value=".">Todas</option>');
            $('#cadenas').empty().append(optionTodas);
            $('#ciudades').empty().append(optionTodas);
            $('#locales').empty().append(optionTodos);

            getSupervisores(fecha_inicio, fecha_fin, reporte, tipo);
            getMercaderistas(fecha_inicio, fecha_fin, reporte, '.', tipo);
            if (reporte === 'exhibiciones') getCategorias(fecha_inicio, fecha_fin, '.', '.', tipo);
            getCadenas(fecha_inicio, fecha_fin, reporte, '.', '.', '.', tipo);
            getCiudades(fecha_inicio, fecha_fin, reporte, '.', '.', '.', '.', tipo);
            getLocales(fecha_inicio, fecha_fin, reporte, '.', '.', '.', '.', '.', tipo);
        }
        $('#fechaInicio').change(recargarCascade);
        $('#fechaFin').change(recargarCascade);

        // Al cambiar reporte: mostrar/ocultar Tipo y Categoría, recargar cascade
        $("#reportes").change(function() {
            var reporte = $(this).val();
            var fecha_inicio = $('#fechaInicio').val();
            var fecha_fin = $('#fechaFin').val();

            if (reporte === 'exhibiciones') {
                $('#li-tipo').show();
                $('#li-categoria').show();
                if (fecha_inicio && fecha_fin) getTipos(fecha_inicio, fecha_fin);
            } else {
                $('#li-tipo').hide();
                $('#tipos').html('<option value=".">Todos</option>');
                $('#li-categoria').hide();
                $('#categorias').html('<option value=".">Todas</option>');
            }

            $('#supervisores').empty().append(optionTodos);
            $('#mercaderistas').empty().append(optionTodos);
            $('#cadenas').empty().append(optionTodas);
            $('#ciudades').empty().append(optionTodas);
            $('#locales').empty().append(optionTodos);

            if (reporte !== 'Seleccione' && fecha_inicio && fecha_fin) {
                var tipo = $('#tipos').val() || '.';
                getSupervisores(fecha_inicio, fecha_fin, reporte, tipo);
                getMercaderistas(fecha_inicio, fecha_fin, reporte, '.', tipo);
                if (reporte === 'exhibiciones') getCategorias(fecha_inicio, fecha_fin, '.', '.', tipo);
                getCadenas(fecha_inicio, fecha_fin, reporte, '.', '.', '.', tipo);
                getCiudades(fecha_inicio, fecha_fin, reporte, '.', '.', '.', '.', tipo);
                getLocales(fecha_inicio, fecha_fin, reporte, '.', '.', '.', '.', '.', tipo);
            }
        });

        // Al cambiar tipo: recargar cascade desde Supervisor + recargar categorías
        $("#tipos").change(function() {
            var tipo = $(this).val();
            var fecha_inicio = $('#fechaInicio').val();
            var fecha_fin = $('#fechaFin').val();
            var reporte = $('#reportes').val();
            var gestor = $('#mercaderistas').val() || '.';
            var supervisor = $('#supervisores').val() || '.';

            $('#supervisores').empty().append(optionTodos);
            $('#mercaderistas').empty().append(optionTodos);
            $('#categorias').html('<option value=".">Todas</option>');
            $('#cadenas').empty().append(optionTodas);
            $('#ciudades').empty().append(optionTodas);
            $('#locales').empty().append(optionTodos);

            getSupervisores(fecha_inicio, fecha_fin, reporte, tipo);
            getCategorias(fecha_inicio, fecha_fin, supervisor, gestor, tipo);
        });

        // Al cambiar supervisor: cargar mercaderistas + toda la cascada con gestor='.'
        $("#supervisores").change(function() {
            var supervisor = $(this).val();
            var fecha_inicio = $('#fechaInicio').val();
            var fecha_fin = $('#fechaFin').val();
            var reporte = $('#reportes').val();
            var tipo = $('#tipos').val() || '.';

            $('#mercaderistas').empty().append(optionTodos);
            $('#categorias').html('<option value=".">Todas</option>');
            $('#cadenas').empty().append(optionTodas);
            $('#ciudades').empty().append(optionTodas);
            $('#locales').empty().append(optionTodos);

            getMercaderistas(fecha_inicio, fecha_fin, reporte, supervisor, tipo);
            if (reporte === 'exhibiciones') getCategorias(fecha_inicio, fecha_fin, supervisor, '.', tipo);
            getCadenas(fecha_inicio, fecha_fin, reporte, supervisor, '.', '.', tipo);
            getCiudades(fecha_inicio, fecha_fin, reporte, supervisor, '.', '.', '.', tipo);
            getLocales(fecha_inicio, fecha_fin, reporte, supervisor, '.', '.', '.', '.', tipo);
        });

        // Al cambiar gestor: cargar categorías + toda la cascada inferior con cadena/ciudad='.'
        $("#mercaderistas").change(function() {
            var gestor = $(this).val();
            var fecha_inicio = $('#fechaInicio').val();
            var fecha_fin = $('#fechaFin').val();
            var reporte = $('#reportes').val();
            var supervisor = $('#supervisores').val();
            var tipo = $('#tipos').val() || '.';

            $('#categorias').html('<option value=".">Todas</option>');
            $('#cadenas').empty().append(optionTodas);
            $('#ciudades').empty().append(optionTodas);
            $('#locales').empty().append(optionTodos);

            if (reporte === 'exhibiciones') getCategorias(fecha_inicio, fecha_fin, supervisor, gestor, tipo);
            getCadenas(fecha_inicio, fecha_fin, reporte, supervisor, gestor, '.', tipo);
            getCiudades(fecha_inicio, fecha_fin, reporte, supervisor, gestor, '.', '.', tipo);
            getLocales(fecha_inicio, fecha_fin, reporte, supervisor, gestor, '.', '.', '.', tipo);
        });

        // Al cambiar categoría: recargar cadena + ciudad + local con selección actual
        $("#categorias").change(function() {
            var categoria = $(this).val();
            var fecha_inicio = $('#fechaInicio').val();
            var fecha_fin = $('#fechaFin').val();
            var reporte = $('#reportes').val();
            var supervisor = $('#supervisores').val();
            var gestor = $('#mercaderistas').val();
            var tipo = $('#tipos').val() || '.';

            $('#cadenas').empty().append(optionTodas);
            $('#ciudades').empty().append(optionTodas);
            $('#locales').empty().append(optionTodos);

            getCadenas(fecha_inicio, fecha_fin, reporte, supervisor, gestor, categoria, tipo);
            getCiudades(fecha_inicio, fecha_fin, reporte, supervisor, gestor, '.', categoria, tipo);
            getLocales(fecha_inicio, fecha_fin, reporte, supervisor, gestor, '.', '.', categoria, tipo);
        });

        // Al cambiar cadena: cargar ciudades + locales
        $("#cadenas").change(function() {
            var cadena = $(this).val();
            var fecha_inicio = $('#fechaInicio').val();
            var fecha_fin = $('#fechaFin').val();
            var reporte = $('#reportes').val();
            var supervisor = $('#supervisores').val();
            var gestor = $('#mercaderistas').val();
            var categoria = $('#categorias').val() || '.';
            var tipo = $('#tipos').val() || '.';

            $('#ciudades').empty().append(optionTodas);
            $('#locales').empty().append(optionTodos);

            getCiudades(fecha_inicio, fecha_fin, reporte, supervisor, gestor, cadena, categoria, tipo);
            getLocales(fecha_inicio, fecha_fin, reporte, supervisor, gestor, cadena, '.', categoria, tipo);
        });

        // Al cambiar ciudad: cargar locales
        $("#ciudades").change(function() {
            var ciudad = $(this).val();
            var fecha_inicio = $('#fechaInicio').val();
            var fecha_fin = $('#fechaFin').val();
            var reporte = $('#reportes').val();
            var supervisor = $('#supervisores').val();
            var gestor = $('#mercaderistas').val();
            var cadena = $('#cadenas').val();
            var categoria = $('#categorias').val() || '.';
            var tipo = $('#tipos').val() || '.';

            $('#locales').empty().append(optionTodos);
            getLocales(fecha_inicio, fecha_fin, reporte, supervisor, gestor, cadena, ciudad, categoria, tipo);
        });

        // Aplicar filtros
        $("#filter").click(function() {
            var fecha_inicio = $('#fechaInicio').val();
            var fecha_fin    = $('#fechaFin').val();
            var reporte      = $('#reportes').val();
            var supervisor   = $('#supervisores').val();
            var gestor       = $('#mercaderistas').val();
            var categoria    = $('#categorias').val() || '.';
            var cadena       = $('#cadenas').val();
            var ciudad       = $('#ciudades').val();
            var local        = $('#locales').val();
            var tipo         = $('#tipos').val() || '.';

            if (!fecha_inicio) { alert('Ingrese una fecha inicio'); return; }
            if (!fecha_fin)    { alert('Ingrese una fecha fin'); return; }
            if (reporte === 'Seleccione') { alert('Seleccione un tipo de reporte'); return; }

            getData(reporte, fecha_inicio, fecha_fin, supervisor, gestor, categoria, cadena, ciudad, local, tipo);
        });
    });
</script>

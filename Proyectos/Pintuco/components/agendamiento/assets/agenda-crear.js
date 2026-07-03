(function () {
    var app = document.getElementById('agendaApp');
    var GETTERS_BASE = app.dataset.gettersBase;
    var MAPBOX_TOKEN = app.dataset.mapboxToken;

    var DOMINIOS_COMUNES = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com'];

    var pdvsCache = null;
    var promotoresCache = null;
    var pinMap = null;
    var coordenadas = null; // { lat, lng } del centro del mapa al confirmar
    var toastTimer = null;

    // ---------------------------------------------------------------
    // Helpers de UI
    // ---------------------------------------------------------------
    function hoyFormateado() {
        var hoy = new Date();
        return String(hoy.getDate()).padStart(2, '0') + '/' +
            String(hoy.getMonth() + 1).padStart(2, '0') + '/' +
            hoy.getFullYear();
    }

    function hoyISO() {
        var hoy = new Date();
        return hoy.getFullYear() + '-' + String(hoy.getMonth() + 1).padStart(2, '0') + '-' + String(hoy.getDate()).padStart(2, '0');
    }

    function mostrarToast(mensaje, esError) {
        var toast = document.getElementById('agendaCrearToast');
        toast.textContent = mensaje;
        toast.classList.toggle('is-error', !!esError);
        toast.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toast.classList.remove('is-visible');
        }, 2500);
    }

    function marcarError(idCampo, idError, mensaje) {
        var input = document.getElementById(idCampo);
        var error = document.getElementById(idError);
        if (mensaje) {
            input.classList.add('is-invalid');
            error.textContent = mensaje;
            error.classList.add('is-visible');
        } else {
            input.classList.remove('is-invalid');
            error.textContent = '';
            error.classList.remove('is-visible');
        }
        return !mensaje;
    }

    function limpiarErrores() {
        document.querySelectorAll('#agendaCrearOverlay .agenda-crear-error').forEach(function (el) {
            el.textContent = '';
            el.classList.remove('is-visible');
        });
        document.querySelectorAll('#agendaCrearOverlay .is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
    }

    // Borde rojo + mensaje EN VIVO mientras se escribe (no solo al enviar
    // el formulario) — mientras el campo está vacío no molesta con "es
    // obligatorio" (eso solo se ve al salir del campo o al guardar);
    // apenas hay algo escrito, valida formato de inmediato.
    function vigilarValidacionEnVivo(idCampo, idError, validador, requerido) {
        var input = document.getElementById(idCampo);
        function validar(mostrarRequerido) {
            var valor = input.value.trim();
            if (!valor) {
                marcarError(idCampo, idError, (requerido && mostrarRequerido) ? 'Este campo es obligatorio.' : '');
                return;
            }
            marcarError(idCampo, idError, validador(valor));
        }
        input.addEventListener('input', function () { validar(false); });
        input.addEventListener('blur', function () { validar(true); });
    }

    // ---------------------------------------------------------------
    // Selects: Promotor (get_promotores.php) y PDV (get_pdvs.php) — ambos
    // se piden una sola vez por carga de página y se reusan en cada
    // apertura del modal, ninguno de los dos cambia mientras se trabaja.
    // ---------------------------------------------------------------
    function poblarPromotores() {
        var select = document.getElementById('agendaCrearPromotor');

        function pintar(promotores) {
            select.innerHTML = '<option value="">Seleccione un promotor</option>';
            promotores.forEach(function (nombre) {
                var opt = document.createElement('option');
                opt.value = nombre;
                opt.textContent = nombre;
                select.appendChild(opt);
            });
        }

        if (promotoresCache) {
            pintar(promotoresCache);
            return;
        }
        select.innerHTML = '<option value="">Cargando promotores...</option>';
        fetch(GETTERS_BASE + 'get_promotores.php')
            .then(function (resp) { return resp.json(); })
            .then(function (json) {
                promotoresCache = json.data || [];
                pintar(promotoresCache);
            });
    }

    function poblarPdvs() {
        var select = document.getElementById('agendaCrearPdv');

        function pintar(pdvs) {
            select.innerHTML = '<option value="">Seleccione un PDV</option>';
            pdvs.forEach(function (p) {
                var opt = document.createElement('option');
                opt.value = p.pos_id;
                opt.textContent = p.pos_name;
                opt.dataset.nombre = p.pos_name;
                select.appendChild(opt);
            });
        }

        if (pdvsCache) {
            pintar(pdvsCache);
            return;
        }
        select.innerHTML = '<option value="">Cargando PDV...</option>';
        fetch(GETTERS_BASE + 'get_pdvs.php')
            .then(function (resp) { return resp.json(); })
            .then(function (json) {
                pdvsCache = json.data || [];
                pintar(pdvsCache);
            });
    }

    // ---------------------------------------------------------------
    // Abrir / cerrar / limpiar
    // ---------------------------------------------------------------
    function limpiarFormulario() {
        document.getElementById('agendaCrearFechaRegistro').textContent = 'Registro: ' + hoyFormateado();
        document.getElementById('agendaCrearContacto').value = '';
        document.getElementById('agendaCrearEmpresa').value = '';
        document.getElementById('agendaCrearMail').value = '';
        document.getElementById('agendaCrearDireccion').value = '';
        document.getElementById('agendaCrearCelular').value = '';
        document.getElementById('agendaCrearConvencional').value = '';
        document.getElementById('agendaCrearFechaAgenda').value = '';
        document.getElementById('agendaCrearFechaAgenda').min = hoyISO();
        document.getElementById('agendaCrearHora').value = '';
        document.getElementById('agendaCrearTecnico').value = '';
        document.getElementById('agendaCrearMailSugerencias').innerHTML = '';
        limpiarErrores();
        reiniciarPin();
    }

    function abrirModal() {
        limpiarFormulario();
        poblarPromotores();
        poblarPdvs();
        document.getElementById('agendaCrearOverlay').classList.add('active');
        inicializarMapaPin();
    }

    function cerrarModal() {
        document.getElementById('agendaCrearOverlay').classList.remove('active');
    }

    // ---------------------------------------------------------------
    // Normalización en vivo: mayúsculas + sin espacios dobles
    // ---------------------------------------------------------------
    function normalizarMayusculas(input) {
        input.addEventListener('input', function () {
            var inicio = input.selectionStart;
            var antes = input.value.length;
            input.value = input.value.toUpperCase().replace(/ {2,}/g, ' ');
            var diff = input.value.length - antes;
            if (inicio != null) input.setSelectionRange(inicio + diff, inicio + diff);
        });
    }

    // ---------------------------------------------------------------
    // Mapa con pin FIJO al centro (Leaflet + OpenStreetMap, sin
    // restricción de país — se quitó porque bloqueaba búsquedas
    // válidas). El pin no se arrastra: es un ícono puesto con CSS
    // encima del mapa (ver .agenda-crear-mapa-pin-fijo) que nunca se
    // mueve de ahí — es el MAPA el que se desplaza por debajo al
    // navegar/hacer zoom, así el pin nunca "se pierde" de la vista
    // mientras el analista busca su ubicación. Mapbox solo se consulta
    // UNA vez, al hacer clic en "Confirmar pin" (geocodificación
    // inversa con las coordenadas del centro), no en cada movimiento.
    // ---------------------------------------------------------------
    var PUNTO_INICIAL = [-2.170998, -79.922359];

    function inicializarMapaPin() {
        var contenedor = document.getElementById('agendaCrearMapaPin');
        if (pinMap) {
            setTimeout(function () { pinMap.invalidateSize(); }, 80);
            return;
        }
        pinMap = L.map(contenedor).setView(PUNTO_INICIAL, 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19
        }).addTo(pinMap);
        setTimeout(function () { pinMap.invalidateSize(); }, 80);
    }

    function reiniciarPin() {
        coordenadas = null;
        if (pinMap) pinMap.setView(PUNTO_INICIAL, 13);
    }

    // Un Plus Code de Google ("PRR7+GCR") no es una dirección legible —
    // se rechaza si llega a ser lo único que la geocodificación inversa
    // devuelve, o si el analista lo escribe a mano en el campo de texto.
    var PATRON_PLUS_CODE = /^[23456789CFGHJMPQRVWX]{4,8}\+[23456789CFGHJMPQRVWX]{2,3}$/i;

    function esPlusCode(texto) {
        return PATRON_PLUS_CODE.test((texto || '').trim());
    }

    // ÚNICA consulta a Mapbox de este flujo: se dispara solo al hacer clic
    // en "Confirmar pin", nunca mientras se navega el mapa. Las
    // coordenadas ya son válidas apenas se confirma (vienen del centro
    // del mapa, no de la respuesta); la geocodificación inversa solo
    // sirve para autocompletar el texto.
    function confirmarPin() {
        var pos = pinMap.getCenter();
        coordenadas = { lat: pos.lat, lng: pos.lng };
        marcarError('agendaCrearDireccion', 'agendaCrearErrDireccion', '');

        if (!MAPBOX_TOKEN) {
            mostrarToast('Pin fijado. Falta el token de Mapbox para autocompletar la calle — escríbela a mano.', true);
            return;
        }

        var url = 'https://api.mapbox.com/search/geocode/v6/reverse'
            + '?longitude=' + pos.lng + '&latitude=' + pos.lat
            + '&language=es&access_token=' + MAPBOX_TOKEN;

        var input = document.getElementById('agendaCrearDireccion');
        fetch(url)
            .then(function (resp) { return resp.json(); })
            .then(function (json) {
                var feature = (json.features || [])[0];
                var nombre = feature && (feature.properties.full_address || feature.properties.name);
                if (!nombre || esPlusCode(nombre)) {
                    mostrarToast('Pin fijado, pero no se pudo leer una calle legible ahí — escríbela a mano.', true);
                    return;
                }
                input.value = nombre;
                mostrarToast('Pin confirmado y dirección autocompletada.');
            })
            .catch(function () {
                mostrarToast('Pin fijado, pero no se pudo consultar la calle (revisa tu conexión).', true);
            });
    }

    // ---------------------------------------------------------------
    // Sugerencias de dominio de correo
    // ---------------------------------------------------------------
    function vigilarSugerenciasMail() {
        var input = document.getElementById('agendaCrearMail');
        var contenedor = document.getElementById('agendaCrearMailSugerencias');
        input.addEventListener('input', function () {
            contenedor.innerHTML = '';
            var arroba = input.value.indexOf('@');
            if (arroba === -1) return;
            var local = input.value.slice(0, arroba);
            var dominioParcial = input.value.slice(arroba + 1).toLowerCase();
            if (!local) return;
            DOMINIOS_COMUNES
                .filter(function (d) { return d.indexOf(dominioParcial) === 0 && d !== dominioParcial; })
                .forEach(function (d) {
                    var chip = document.createElement('span');
                    chip.className = 'agenda-crear-mail-chip';
                    chip.textContent = local + '@' + d;
                    chip.addEventListener('click', function () {
                        input.value = local + '@' + d;
                        contenedor.innerHTML = '';
                    });
                    contenedor.appendChild(chip);
                });
        });
    }

    // ---------------------------------------------------------------
    // Validación (mismas reglas que ya usa la app móvil del lado de
    // datos — contacto/empresa/mail/dirección/teléfonos/fecha).
    // ---------------------------------------------------------------
    var RE_CONTACTO = /^[A-Za-zÁÉÍÓÚÑáéíóúñ' -]+$/;
    var RE_EMPRESA = /^[A-Za-z0-9ÁÉÍÓÚÑáéíóúñ.\-&' ]+$/;
    var RE_SOLO_DIGITOS = /^\d+$/;

    function contarLetrasReales(texto) {
        return (texto.match(/[A-Za-zÁÉÍÓÚÑáéíóúñ]/g) || []).length;
    }

    function validarEmail(valor) {
        var partes = valor.split('@');
        if (partes.length !== 2) return false;
        var local = partes[0], dominio = partes[1];
        if (local.length < 2) return false;
        if (local.charAt(0) === '.' || local.charAt(local.length - 1) === '.') return false;
        if (valor.indexOf('..') !== -1) return false;
        if (!/^[^\s@]+$/.test(local)) return false;
        if (!/^[^\s@]+\.[^\s@]+$/.test(dominio)) return false;
        return true;
    }

    // El celular puede empezar con cualquier dígito (no se exige el "09"
    // de antes) — lo único que de verdad importa es que sean solo números
    // y que no se guarde con menos de 10 dígitos.
    function errorCelular(valor) {
        if (!/^\d+$/.test(valor)) return 'Solo se permiten números.';
        if (valor.length < 10) return 'Debe tener 10 dígitos (van ' + valor.length + ').';
        if (valor.length > 10) return 'No debe tener más de 10 dígitos.';
        return null;
    }

    function validarFormulario() {
        limpiarErrores();
        var ok = true;

        var promotor = document.getElementById('agendaCrearPromotor').value;
        var pdvSelect = document.getElementById('agendaCrearPdv');
        var contacto = document.getElementById('agendaCrearContacto').value.trim();
        var empresa = document.getElementById('agendaCrearEmpresa').value.trim();
        var mail = document.getElementById('agendaCrearMail').value.trim();
        var direccion = document.getElementById('agendaCrearDireccion').value.trim();
        var celular = document.getElementById('agendaCrearCelular').value.trim();
        var convencional = document.getElementById('agendaCrearConvencional').value.trim();
        var fechaAgenda = document.getElementById('agendaCrearFechaAgenda').value;
        var hora = document.getElementById('agendaCrearHora').value;
        var tecnico = document.getElementById('agendaCrearTecnico').value.trim();

        // Formulario completamente vacío (incluyendo el PDV) → un solo
        // error general, no 10 campos en rojo a la vez.
        var todoVacio = !promotor && !pdvSelect.value && !contacto && !empresa && !mail
            && !direccion && !celular && !convencional && !fechaAgenda && !hora && !tecnico;
        if (todoVacio) {
            mostrarToast('El formulario está vacío.', true);
            return false;
        }

        if (!promotor) ok = marcarError('agendaCrearPromotor', 'agendaCrearErrPromotor', 'Selecciona un promotor.') && ok;
        if (!pdvSelect.value) ok = marcarError('agendaCrearPdv', 'agendaCrearErrPdv', 'Selecciona un PDV de la lista.') && ok;

        if (!contacto) {
            ok = marcarError('agendaCrearContacto', 'agendaCrearErrContacto', 'El contacto es obligatorio.') && ok;
        } else if (!RE_CONTACTO.test(contacto) || contarLetrasReales(contacto) < 2) {
            ok = marcarError('agendaCrearContacto', 'agendaCrearErrContacto', 'Solo letras, espacios, apóstrofes y guiones (mínimo 2 letras).') && ok;
        } else {
            marcarError('agendaCrearContacto', 'agendaCrearErrContacto', '');
        }

        if (!empresa) {
            ok = marcarError('agendaCrearEmpresa', 'agendaCrearErrEmpresa', 'La empresa es obligatoria.') && ok;
        } else if (!RE_EMPRESA.test(empresa) || empresa.replace(/[^A-Za-z0-9ÁÉÍÓÚÑáéíóúñ]/g, '').length < 2) {
            ok = marcarError('agendaCrearEmpresa', 'agendaCrearErrEmpresa', 'Letras, números, espacios, puntos, guiones, & y apóstrofes (mínimo 2 caracteres).') && ok;
        } else {
            marcarError('agendaCrearEmpresa', 'agendaCrearErrEmpresa', '');
        }

        if (!mail) {
            ok = marcarError('agendaCrearMail', 'agendaCrearErrMail', 'El correo es obligatorio.') && ok;
        } else if (!validarEmail(mail)) {
            ok = marcarError('agendaCrearMail', 'agendaCrearErrMail', 'Correo inválido.') && ok;
        } else {
            marcarError('agendaCrearMail', 'agendaCrearErrMail', '');
        }

        if (!direccion) {
            ok = marcarError('agendaCrearDireccion', 'agendaCrearErrDireccion', 'La dirección es obligatoria.') && ok;
        } else if (esPlusCode(direccion)) {
            ok = marcarError('agendaCrearDireccion', 'agendaCrearErrDireccion', 'Esa es un código Plus Code — escribe una dirección más específica.') && ok;
        } else if (!coordenadas) {
            ok = marcarError('agendaCrearDireccion', 'agendaCrearErrDireccion', 'Mueve el pin en el mapa y haz clic en "Confirmar pin" antes de guardar.') && ok;
        } else {
            marcarError('agendaCrearDireccion', 'agendaCrearErrDireccion', '');
        }

        if (!celular) {
            ok = marcarError('agendaCrearCelular', 'agendaCrearErrCelular', 'El teléfono es obligatorio.') && ok;
        } else {
            ok = marcarError('agendaCrearCelular', 'agendaCrearErrCelular', errorCelular(celular)) && ok;
        }

        if (convencional && !RE_SOLO_DIGITOS.test(convencional)) {
            ok = marcarError('agendaCrearConvencional', 'agendaCrearErrConvencional', 'Solo dígitos.') && ok;
        } else {
            marcarError('agendaCrearConvencional', 'agendaCrearErrConvencional', '');
        }

        if (!fechaAgenda) {
            ok = marcarError('agendaCrearFechaAgenda', 'agendaCrearErrFechaAgenda', 'La fecha de agendamiento es obligatoria.') && ok;
        } else if (fechaAgenda < hoyISO()) {
            ok = marcarError('agendaCrearFechaAgenda', 'agendaCrearErrFechaAgenda', 'No se permiten fechas pasadas.') && ok;
        } else {
            marcarError('agendaCrearFechaAgenda', 'agendaCrearErrFechaAgenda', '');
        }

        if (!hora) ok = marcarError('agendaCrearHora', 'agendaCrearErrHora', 'La hora es obligatoria.') && ok;
        else marcarError('agendaCrearHora', 'agendaCrearErrHora', '');

        if (!tecnico) ok = marcarError('agendaCrearTecnico', 'agendaCrearErrTecnico', 'El técnico es obligatorio.') && ok;
        else marcarError('agendaCrearTecnico', 'agendaCrearErrTecnico', '');

        if (!ok) mostrarToast('Revisa los campos marcados en rojo.', true);
        return ok;
    }

    // ---------------------------------------------------------------
    // Guardar
    // ---------------------------------------------------------------
    function guardar() {
        if (!validarFormulario()) return;

        var pdvSelect = document.getElementById('agendaCrearPdv');
        var pdvOpcion = pdvSelect.options[pdvSelect.selectedIndex];

        var body = new URLSearchParams();
        body.set('usuario', document.getElementById('agendaCrearPromotor').value);
        body.set('codigo_pdv', pdvSelect.value);
        body.set('pdv', pdvOpcion ? pdvOpcion.dataset.nombre : '');
        body.set('contacto', document.getElementById('agendaCrearContacto').value.trim());
        body.set('empresa', document.getElementById('agendaCrearEmpresa').value.trim());
        body.set('mail', document.getElementById('agendaCrearMail').value.trim());
        body.set('direccion', document.getElementById('agendaCrearDireccion').value.trim());
        body.set('latitud', coordenadas ? coordenadas.lat : '');
        body.set('longitud', coordenadas ? coordenadas.lng : '');
        body.set('telefono', document.getElementById('agendaCrearCelular').value.trim());
        body.set('telefono_convencional', document.getElementById('agendaCrearConvencional').value.trim());
        body.set('fecha_agendamiento', document.getElementById('agendaCrearFechaAgenda').value);
        body.set('hora', document.getElementById('agendaCrearHora').value);
        body.set('tecnico', document.getElementById('agendaCrearTecnico').value.trim());

        // Se leen ANTES de cerrar/limpiar el modal — limpiarFormulario()
        // vacía estos mismos inputs en la próxima apertura.
        var fechaGuardada = document.getElementById('agendaCrearFechaAgenda').value;
        var horaGuardada = document.getElementById('agendaCrearHora').value;

        var guardarBtn = document.getElementById('agendaCrearGuardar');
        guardarBtn.disabled = true;

        fetch(GETTERS_BASE + 'insert_contacto.php', { method: 'POST', body: body })
            .then(function (resp) { return resp.json(); })
            .then(function (json) {
                guardarBtn.disabled = false;
                if (json.success) {
                    cerrarModal();
                    // Sin esto, una visita creada para una fecha distinta a
                    // la semana/día que el calendario está mostrando en ese
                    // momento queda guardada en la BD pero invisible hasta
                    // navegar ahí a mano — recargar() + resaltar() es lo
                    // mismo que ya hace guardarEdicion() en agenda.js.
                    if (window.AgendaRecargar) {
                        window.AgendaRecargar().then(function () {
                            if (window.AgendaResaltar) window.AgendaResaltar(json.id, fechaGuardada, horaGuardada);
                        });
                    }
                } else if (json.conflicto && window.AgendaMostrarConflicto) {
                    // El modal de creación se queda abierto (igual que hace
                    // guardarEdicion() en agenda.js) — el analista solo
                    // cierra el diálogo de conflicto y cambia la hora, sin
                    // perder el resto de los datos ya escritos.
                    window.AgendaMostrarConflicto(json.conflicto, fechaGuardada);
                } else {
                    mostrarToast(json.message || 'No se pudo registrar la visita.', true);
                }
            })
            .catch(function () {
                guardarBtn.disabled = false;
                mostrarToast('No se pudo conectar con el servidor.', true);
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('agendaCrearBtn').addEventListener('click', abrirModal);
        document.getElementById('agendaCrearClose').addEventListener('click', cerrarModal);
        document.getElementById('agendaCrearCancelar').addEventListener('click', cerrarModal);
        document.getElementById('agendaCrearGuardar').addEventListener('click', guardar);
        document.getElementById('agendaCrearConfirmarPin').addEventListener('click', confirmarPin);

        normalizarMayusculas(document.getElementById('agendaCrearContacto'));
        normalizarMayusculas(document.getElementById('agendaCrearEmpresa'));
        vigilarSugerenciasMail();

        vigilarValidacionEnVivo('agendaCrearCelular', 'agendaCrearErrCelular', errorCelular, true);
        vigilarValidacionEnVivo('agendaCrearConvencional', 'agendaCrearErrConvencional', function (v) {
            return /^\d+$/.test(v) ? null : 'Solo se permiten números.';
        }, false);
        vigilarValidacionEnVivo('agendaCrearMail', 'agendaCrearErrMail', function (v) {
            return validarEmail(v) ? null : 'Correo inválido.';
        }, true);
        vigilarValidacionEnVivo('agendaCrearContacto', 'agendaCrearErrContacto', function (v) {
            return (RE_CONTACTO.test(v) && contarLetrasReales(v) >= 2)
                ? null : 'Solo letras, espacios, apóstrofes y guiones (mínimo 2 letras).';
        }, true);
        vigilarValidacionEnVivo('agendaCrearEmpresa', 'agendaCrearErrEmpresa', function (v) {
            return (RE_EMPRESA.test(v) && v.replace(/[^A-Za-z0-9ÁÉÍÓÚÑáéíóúñ]/g, '').length >= 2)
                ? null : 'Letras, números, espacios, puntos, guiones, & y apóstrofes (mínimo 2 caracteres).';
        }, true);
    });
})();

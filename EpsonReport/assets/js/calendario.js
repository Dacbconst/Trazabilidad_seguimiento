// Calendario de Activaciones: modal de crear/editar y acciones de cada calendario.
(function () {
	var modal = document.getElementById('epCalModal');
	if (!modal) return;

	function leerJson(id, porDefecto) {
		try { return JSON.parse(document.getElementById(id).textContent); } catch (e) { return porDefecto; }
	}
	var RUTERO = {};
	var CIUDADES = {};
	var PDVDATA = {};
	var CALENDARIOS = leerJson('epCalDatosCalendarios', []);

	// Rutero, ciudades y PDV pesan cientos de KB: se piden aparte, en segundo plano, y solo los usa el modal.
	var catalogo = null;
	function cargarCatalogo() {
		if (!catalogo) {
			catalogo = fetch('getters/calendario_catalogo.php', { credentials: 'same-origin' })
				.then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
				.then(function (d) {
					RUTERO = d.rutero || {};
					CIUDADES = d.ciudades || {};
					PDVDATA = d.pdv || {};
					if (modo === 'crear') filasEditor.querySelectorAll('.ep-cal-fila-editor').forEach(function (f) { if (f.refrescar) f.refrescar(); });
				})
				.catch(function (e) { catalogo = null; throw e; });
		}
		return catalogo;
	}
	// Abre el modal cuando los datos ya están; si todavía vienen en camino, el botón queda en espera.
	function conCatalogo(boton, abrir) {
		if (boton) { boton.disabled = true; boton.setAttribute('aria-busy', 'true'); }
		cargarCatalogo().then(abrir, function () {
			avisar('error', 'No se pudo cargar', 'No llegaron las ciudades y puntos de venta. Revisa tu conexión e intenta de nuevo.');
		}).then(function () {
			if (boton) { boton.disabled = false; boton.removeAttribute('aria-busy'); }
		});
	}

	function post(url, datos) {
		var form = new FormData();
		Object.keys(datos).forEach(function (k) { form.append(k, datos[k]); });
		return fetch(url, { method: 'POST', body: form, credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}
	function avisar(icono, titulo, texto) {
		if (window.Swal) Swal.fire({ icon: icono, title: titulo, text: texto || '' });
	}
	function errorServidor(titulo) {
		return function () { avisar('error', titulo, 'El servidor no respondió correctamente. Intenta de nuevo.'); };
	}

	var btnNuevo = document.getElementById('epCalNuevo');
	var btnCancelar = document.getElementById('epCalCancelar');
	var btnCerrar = document.getElementById('epCalModalCerrar');
	var fondo = document.getElementById('epCalModalFondo');
	var btnAgregarFila = document.getElementById('epCalAgregarFila');
	var btnGuardar = document.getElementById('epCalCrear');
	var filasEditor = document.getElementById('epCalFilasEditor');
	var canalSelect = document.getElementById('epCalCanal');
	var rangoAuto = document.getElementById('epCalRangoAuto');
	var inpNombre = document.getElementById('epCalNombre');
	var inpPlazo = document.getElementById('epCalPlazo');
	var titulo = document.getElementById('epCalModalTitulo');
	var aviso = document.getElementById('epCalAvisoEdicion');
	// Textarea oculto que comentarios.js convierte en lista numerada; guarda un comentario por línea.
	var inpComentarios = document.getElementById('epCal-comentarios');
	var comentariosOriginales = '';
	function fijarComentarios(texto) {
		inpComentarios.value = texto || '';
		if (inpComentarios.epFijarComentarios) inpComentarios.epFijarComentarios(texto || '');
	}
	var editorWrap = filasEditor.closest('.ep-cal-filas-editor');
	// Copia limpia de la fila (sin listeners) para armar filas nuevas en crear y en editar.
	var plantillaFila = filasEditor.firstElementChild.cloneNode(true);
	var canalInicial = canalSelect.value;
	var MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
	// El ejemplo del nombre sigue al canal elegido y al mes en curso.
	function ponerEjemploNombre() {
		var o = opcionesCanal.find(function (x) { return x.valor === canalSelect.value; });
		inpNombre.placeholder = 'Activaciones ' + (o ? o.texto : '') + ' · ' + MESES[new Date().getMonth()];
	}
	var modo = 'crear';
	var editandoId = 0;

	function mostrarModal() { modal.classList.remove('hidden'); }
	function cerrar() { modal.classList.add('hidden'); }
	if (btnCerrar) btnCerrar.addEventListener('click', cerrar);
	if (btnCancelar) btnCancelar.addEventListener('click', cerrar);
	if (fondo) fondo.addEventListener('click', cerrar);

	// Supervisor al ancho del nombre más largo (140-320px), igual en filas y encabezado.
	function ajustarAnchoSupervisor() {
		editorWrap.style.setProperty('--ep-cal-sup-w', '140px');
		var ancho = 140;
		filasEditor.querySelectorAll('.ep-cal-supervisor-auto:not(.hidden), .ep-cal-supervisor-select:not(.hidden)').forEach(function (el) {
			ancho = Math.max(ancho, el.scrollWidth + 2);
		});
		editorWrap.style.setProperty('--ep-cal-sup-w', Math.min(ancho, 320) + 'px');
	}

	function fmtFecha(iso) {
		var p = iso.split('-');
		return p[2] + '/' + p[1] + '/' + p[0];
	}
	function recalcularRango() {
		var fechas = Array.prototype.slice.call(filasEditor.querySelectorAll('.ep-cal-fecha-input'))
			.map(function (i) { return i.value; }).filter(Boolean).sort();
		if (!fechas.length) {
			rangoAuto.textContent = 'Se calcula al agregar filas';
			rangoAuto.classList.remove('con-valor');
			return;
		}
		rangoAuto.textContent = fmtFecha(fechas[0]) + ' al ' + fmtFecha(fechas[fechas.length - 1]);
		rangoAuto.classList.add('con-valor');
	}

	// Combobox genérico con buscador: solo acepta una opción de la lista (si no, queda marcado en rojo al validar).
	function iniciarCombo(combo, etiquetaVacia) {
		var trigger = combo.querySelector('.ep-combo-trigger');
		var texto = trigger.querySelector('.ep-combo-trigger-texto');
		var panel = combo.querySelector('.ep-combo-panel');
		var buscador = combo.querySelector('.ep-combo-buscador');
		var lista = combo.querySelector('.ep-combo-opciones');

		function pintar(filtro) {
			var q = (filtro || '').toLowerCase();
			var visibles = (combo._opciones || []).filter(function (o) { return (o.texto + ' ' + (o.sub || '')).toLowerCase().indexOf(q) !== -1; });
			lista.innerHTML = visibles.length
				? visibles.map(function (o, i) { return '<div class="ep-combo-opcion" data-i="' + i + '">' + o.texto + (o.sub ? '<small>' + o.sub + '</small>' : '') + '</div>'; }).join('')
				: '<div class="ep-combo-vacio">Sin resultados</div>';
			lista.dataset.visibles = JSON.stringify(visibles);
		}
		// El modal hace scroll interno y recorta un panel absolute: se posiciona "fixed" para que flote sin cortarse.
		function ubicarPanel() {
			var r = trigger.getBoundingClientRect();
			panel.style.position = 'fixed';
			panel.style.top = (r.bottom + 4) + 'px';
			panel.style.left = r.left + 'px';
			panel.style.width = r.width + 'px';
		}
		function cerrarPanel() { panel.classList.add('hidden'); }
		trigger.addEventListener('click', function (e) {
			e.stopPropagation();
			if (trigger.disabled) return;
			var abierto = !panel.classList.contains('hidden');
			document.querySelectorAll('.ep-combo-panel').forEach(function (p) { p.classList.add('hidden'); });
			if (!abierto) { ubicarPanel(); panel.classList.remove('hidden'); pintar(''); buscador.value = ''; buscador.focus(); }
		});
		buscador.addEventListener('input', function () { pintar(buscador.value); });
		lista.addEventListener('click', function (e) {
			var fila = e.target.closest('.ep-combo-opcion');
			if (!fila) return;
			var visibles = JSON.parse(lista.dataset.visibles || '[]');
			var o = visibles[+fila.dataset.i];
			if (!o) return;
			texto.textContent = o.texto;
			trigger.dataset.valor = o.valor;
			trigger.classList.remove('ep-campo-error');
			cerrarPanel();
			combo.dispatchEvent(new CustomEvent('elegido', { detail: o }));
		});
		document.addEventListener('click', cerrarPanel);
		var scrollContenedor = combo.closest('.ep-modal-ppt-body');
		if (scrollContenedor) scrollContenedor.addEventListener('scroll', cerrarPanel);

		combo.habilitar = function (opciones, etiqueta) {
			combo._opciones = opciones;
			trigger.disabled = false;
			trigger.classList.remove('ep-combo-desactivado');
			texto.textContent = etiqueta || etiquetaVacia;
			trigger.dataset.valor = '';
		};
		combo.deshabilitar = function (etiqueta) {
			combo._opciones = [];
			trigger.disabled = true;
			trigger.classList.add('ep-combo-desactivado');
			texto.textContent = etiqueta;
			trigger.dataset.valor = '';
		};
		// Deja un valor elegido sin pasar por la lista (al cargar un calendario para editar).
		combo.fijar = function (valor, etiqueta) {
			texto.textContent = etiqueta;
			trigger.dataset.valor = valor;
			trigger.classList.remove('ep-campo-error');
		};
		// Bloquea conservando el valor visible (a diferencia de deshabilitar, que lo borra).
		combo.bloquear = function (bloqueado) {
			trigger.disabled = bloqueado;
			trigger.classList.toggle('ep-combo-desactivado', bloqueado);
		};
		combo.valor = function () { return trigger.dataset.valor; };
		combo.validar = function () {
			var ok = !trigger.disabled && !!trigger.dataset.valor;
			trigger.classList.toggle('ep-campo-error', !ok);
			return ok;
		};
	}

	// Fila: el Promotor se busca directo, la Ciudad habilita los PDV del canal y el Supervisor sale del promotor.
	function iniciarFila(fila) {
		var inpFecha = fila.querySelector('.ep-cal-fecha-input');
		var comboCiudad = fila.querySelector('.ep-cal-combo-ciudad');
		var comboPromotor = fila.querySelector('.ep-cal-combo-promotor');
		var comboPdv = fila.querySelector('.ep-cal-combo-pdv');
		var supervisorTexto = fila.querySelector('.ep-cal-supervisor-auto');
		var supervisorSelect = fila.querySelector('.ep-cal-supervisor-select');
		var btnQuitar = fila.querySelector('.ep-modelo-quitar');
		var candado = fila.querySelector('.ep-cal-fila-candado');

		iniciarCombo(comboCiudad, 'Ciudad');
		iniciarCombo(comboPromotor, 'Promotor');
		iniciarCombo(comboPdv, 'Punto de venta');

		function mostrarSupervisor(texto) {
			supervisorSelect.classList.add('hidden');
			supervisorTexto.classList.remove('hidden');
			supervisorTexto.textContent = texto;
			supervisorTexto.title = texto;
			ajustarAnchoSupervisor();
		}
		// Si el promotor tiene más de un supervisor en el rutero, no se adivina: se muestra un selector para elegir.
		function mostrarSupervisorAmbiguo(nombres) {
			supervisorTexto.classList.add('hidden');
			supervisorSelect.classList.remove('hidden');
			supervisorSelect.innerHTML = '<option value="">Elige supervisor</option>' + nombres.map(function (n) { return '<option value="' + n + '">' + n + '</option>'; }).join('');
			ajustarAnchoSupervisor();
		}

		function datosRutero() { return RUTERO[canalSelect.value] || []; }
		function datosPdv() { return PDVDATA[canalSelect.value] || []; }
		function opcionesCiudad(ciudades) { return ciudades.map(function (c) { return { texto: c, valor: c }; }); }
		// Promotores del canal (para un supervisor, solo los de su equipo); la ciudad y el punto de venta no dependen del promotor.
		function opcionesPromotor() {
			var unicos = {};
			datosRutero().forEach(function (r) {
				unicos[r.promotor_id] = unicos[r.promotor_id] || { texto: r.promotor_nombre, valor: String(r.promotor_id) };
			});
			return Object.keys(unicos).map(function (id) { return unicos[id]; })
				.sort(function (a, b) { return a.texto.localeCompare(b.texto); });
		}
		// Todos los puntos del canal en esa ciudad (repositorio_locales_dtt2), sin importar el rutero del promotor.
		function cargarPdv(ciudad) {
			var vistos = {};
			comboPdv.habilitar(datosPdv().filter(function (p) { return p.ciudad === ciudad && !vistos[p.pos_id] && (vistos[p.pos_id] = 1); }).map(function (p) { return { texto: p.punto_venta, valor: p.pos_id }; }), 'Punto de venta');
		}
		function refrescarCiudades() {
			comboCiudad.habilitar(opcionesCiudad(CIUDADES[canalSelect.value] || []));
			comboPromotor.habilitar(opcionesPromotor());
			comboPdv.deshabilitar('Elige ciudad');
			mostrarSupervisor('—');
		}
		comboCiudad.addEventListener('elegido', function (e) { cargarPdv(e.detail.valor); });
		// El supervisor sale del promotor (cualquier punto de su rutero), no del punto de venta elegido.
		comboPromotor.addEventListener('elegido', function (e) {
			var promotorId = +e.detail.valor;
			var supervisores = Array.from(new Set(datosRutero().filter(function (r) { return r.promotor_id === promotorId; }).map(function (r) { return r.supervisor; }).filter(Boolean)));
			if (supervisores.length > 1) mostrarSupervisorAmbiguo(supervisores);
			else mostrarSupervisor(supervisores[0] || 'Sin asignar');
		});
		inpFecha.addEventListener('change', recalcularRango);

		fila.refrescar = refrescarCiudades;
		// Carga una fila guardada; la fecha nunca se edita y una fila no editable queda bloqueada.
		fila.cargar = function (d) {
			inpFecha.value = d.fecha;
			inpFecha.disabled = true;
			comboPromotor.habilitar(opcionesPromotor());
			comboPromotor.fijar(String(d.promotor_id), d.promotor);
			comboCiudad.habilitar(opcionesCiudad(CIUDADES[canalSelect.value] || []));
			comboCiudad.fijar(d.ciudad || '', d.ciudad || '—');
			cargarPdv(d.ciudad || '');
			comboPdv.fijar(d.pos_id, d.pdv);
			mostrarSupervisor(d.supervisor || 'Sin asignar');
			fila.dataset.filaId = d.fila_id;
			fila.dataset.original = d.pos_id + '|' + d.promotor_id;
			btnQuitar.classList.add('hidden');
			if (!d.editable) {
				[comboCiudad, comboPromotor, comboPdv].forEach(function (c) { c.bloquear(true); });
				fila.classList.add('ep-cal-fila-bloqueada');
				candado.classList.remove('hidden');
				candado.title = d.estado === 'cumplido' ? 'Ya cumplida: tiene un registro enviado' : 'La fecha ya pasó';
			}
		};
		refrescarCiudades();
	}

	function nuevaFila() {
		var fila = plantillaFila.cloneNode(true);
		filasEditor.appendChild(fila);
		iniciarFila(fila);
		return fila;
	}

	// Canal con el mismo combo que el resto; el valor vive en el input oculto #epCalCanal.
	var comboCanal = document.getElementById('epCalCanalCombo');
	var opcionesCanal = [];
	function etiquetaCanal(valor) {
		var o = opcionesCanal.find(function (x) { return x.valor === valor; });
		return o ? o.texto : valor;
	}
	if (comboCanal) {
		iniciarCombo(comboCanal, 'Canal');
		opcionesCanal = JSON.parse(comboCanal.dataset.opciones || '[]');
		comboCanal.habilitar(opcionesCanal);
		comboCanal.fijar(canalSelect.value, etiquetaCanal(canalSelect.value));
		// Un supervisor con una sola categoría no puede cambiarla.
		if (opcionesCanal.length === 1) comboCanal.bloquear(true);
		ponerEjemploNombre();
		comboCanal.addEventListener('elegido', function (e) {
			if (canalSelect.value === e.detail.valor) return;
			canalSelect.value = e.detail.valor;
			ponerEjemploNombre();
			filasEditor.querySelectorAll('.ep-cal-fila-editor').forEach(function (f) { if (f.refrescar) f.refrescar(); });
		});
	}
	filasEditor.innerHTML = '';
	nuevaFila();

	// Crear: vuelve a blanco solo si antes se estaba editando.
	function abrirCrear() {
		if (modo === 'editar') {
			modo = 'crear';
			editandoId = 0;
			modal.classList.remove('ep-cal-modal-editando');
			titulo.textContent = 'Crear Calendario de Activaciones';
			btnGuardar.textContent = 'Crear y Activar Calendario';
			aviso.classList.add('hidden');
			btnAgregarFila.classList.remove('hidden');
			inpNombre.disabled = false;
			inpNombre.value = '';
			inpPlazo.disabled = false;
			inpPlazo.value = 5;
			fijarComentarios('');
			canalSelect.value = canalInicial;
			comboCanal.bloquear(opcionesCanal.length === 1);
			comboCanal.fijar(canalInicial, etiquetaCanal(canalInicial));
			ponerEjemploNombre();
			filasEditor.innerHTML = '';
			nuevaFila();
			recalcularRango();
		}
		mostrarModal();
	}

	// Editar: mismo modal con los datos cargados y solo lo editable abierto.
	function abrirEditar(cal) {
		modo = 'editar';
		editandoId = cal.id;
		modal.classList.add('ep-cal-modal-editando');
		titulo.textContent = 'Editar Calendario de Activaciones';
		btnGuardar.textContent = 'Guardar cambios';
		aviso.classList.remove('hidden');
		btnAgregarFila.classList.add('hidden');
		inpNombre.value = cal.nombre || '';
		inpNombre.disabled = true;
		inpPlazo.value = cal.plazo_dias;
		inpPlazo.disabled = true;
		fijarComentarios(cal.comentarios || '');
		comentariosOriginales = inpComentarios.value;
		canalSelect.value = cal.canal;
		comboCanal.fijar(cal.canal, etiquetaCanal(cal.canal));
		comboCanal.bloquear(true);
		filasEditor.innerHTML = '';
		cal.filas.forEach(function (d) { nuevaFila().cargar(d); });
		recalcularRango();
		mostrarModal();
		// Con el modal oculto los nombres miden 0: el ancho del Supervisor se mide recién ya visible.
		ajustarAnchoSupervisor();
	}

	if (btnNuevo) btnNuevo.addEventListener('click', function () { conCatalogo(btnNuevo, abrirCrear); });
	document.querySelectorAll('.ep-cal-editar').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var cal = CALENDARIOS.find(function (c) { return c.id === +btn.dataset.id; });
			if (cal) conCatalogo(btn, function () { abrirEditar(cal); });
		});
	});
	// Se piden apenas la página queda libre, así al abrir el modal normalmente ya están.
	(window.requestIdleCallback || function (f) { setTimeout(f, 300); })(function () { cargarCatalogo().catch(function () {}); });

	if (btnAgregarFila) {
		btnAgregarFila.addEventListener('click', nuevaFila);
		filasEditor.addEventListener('click', function (e) {
			var quitar = e.target.closest('.ep-modelo-quitar');
			if (quitar && filasEditor.children.length > 1) {
				quitar.closest('.ep-cal-fila-editor').remove();
				recalcularRango();
				ajustarAnchoSupervisor();
			}
		});
	}

	function combosDe(fila) {
		return {
			ciudad: fila.querySelector('.ep-cal-combo-ciudad'),
			promotor: fila.querySelector('.ep-cal-combo-promotor'),
			pdv: fila.querySelector('.ep-cal-combo-pdv'),
		};
	}

	function guardarNuevo() {
		var valido = true;
		var filas = [];
		filasEditor.querySelectorAll('.ep-cal-fila-editor').forEach(function (fila) {
			var fecha = fila.querySelector('.ep-cal-fecha-input').value;
			var c = combosDe(fila);
			if (!fecha) valido = false;
			if (!c.ciudad.validar()) valido = false;
			if (!c.promotor.validar()) valido = false;
			if (!c.pdv.validar()) valido = false;
			filas.push({ fecha: fecha, pos_id: c.pdv.valor(), promotor_id: c.promotor.valor() });
		});
		if (!valido) {
			avisar('warning', 'Faltan datos', 'Completa fecha, ciudad, promotor y punto de venta en cada fila (elegidos de la lista).');
			return;
		}
		if (!window.Swal) return;
		Swal.fire({
			icon: 'question', title: '¿Activar este calendario?',
			text: 'No hay borrador: al guardar queda activo de una vez y arranca el plazo.',
			showCancelButton: true, confirmButtonText: 'Sí, crear y activar', cancelButtonText: 'Seguir editando',
		}).then(function (res) {
			if (!res.isConfirmed) return;
			post('getters/calendario_crear.php', {
				nombre: inpNombre.value.trim(),
				canal: canalSelect.value,
				plazo_dias: inpPlazo.value,
				comentarios: inpComentarios.value,
				filas: JSON.stringify(filas),
			}).then(function (r) {
				if (r.ok) location.reload();
				else avisar('error', 'No se pudo crear', r.message);
			}).catch(errorServidor('No se pudo crear'));
		});
	}

	// Solo viajan las filas que cambiaron, para no marcar como editadas las que nadie tocó.
	function guardarEdicion() {
		var valido = true;
		var cambios = [];
		filasEditor.querySelectorAll('.ep-cal-fila-editor:not(.ep-cal-fila-bloqueada)').forEach(function (fila) {
			var c = combosDe(fila);
			if (!c.ciudad.validar()) valido = false;
			if (!c.promotor.validar()) valido = false;
			if (!c.pdv.validar()) valido = false;
			if (c.pdv.valor() + '|' + c.promotor.valor() !== fila.dataset.original) {
				cambios.push({ fila_id: fila.dataset.filaId, pos_id: c.pdv.valor(), promotor_id: c.promotor.valor() });
			}
		});
		if (!valido) {
			avisar('warning', 'Faltan datos', 'Completa ciudad, promotor y punto de venta en cada fila editable (elegidos de la lista).');
			return;
		}
		var cambioComentarios = inpComentarios.value !== comentariosOriginales;
		var total = cambios.length + (cambioComentarios ? 1 : 0);
		if (!total) {
			avisar('info', 'Sin cambios', 'No modificaste ninguna fila ni los comentarios.');
			return;
		}
		if (!window.Swal) return;
		Swal.fire({
			icon: 'question', title: '¿Guardar ' + total + (total === 1 ? ' cambio?' : ' cambios?'),
			text: 'Queda registrado quién hizo el cambio y cuándo.',
			showCancelButton: true, confirmButtonText: 'Sí, guardar', cancelButtonText: 'Seguir editando',
		}).then(function (res) {
			if (!res.isConfirmed) return;
			var datos = { id: editandoId, filas: JSON.stringify(cambios) };
			if (cambioComentarios) datos.comentarios = inpComentarios.value;
			post('getters/calendario_editar.php', datos).then(function (r) {
				if (r.ok) location.reload();
				else avisar('error', 'No se pudo guardar', r.message);
			}).catch(errorServidor('No se pudo guardar'));
		});
	}

	if (btnGuardar) {
		btnGuardar.addEventListener('click', function () {
			if (modo === 'editar') guardarEdicion();
			else guardarNuevo();
		});
	}

	// Acciones de cada tarjeta: confirmar y recargar.
	function accionTarjeta(selector, confirmacion, url, tituloError) {
		document.querySelectorAll(selector).forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!window.Swal) return;
				var config = typeof confirmacion === 'function' ? confirmacion(btn) : confirmacion;
				Swal.fire(config).then(function (res) {
					if (!res.isConfirmed) return;
					var datos = { id: btn.dataset.id };
					if (config.input) datos.motivo = res.value;
					post(url, datos).then(function (r) {
						if (!r.ok) { avisar('error', tituloError, r.message); return; }
						// Se cerró, pero sin reporte (registros ya ocupados en otro reporte): no se puede decir que salió bien sin más.
						if (r.aviso && window.Swal) Swal.fire({ icon: 'warning', title: 'Cerrado sin reporte', text: r.aviso }).then(function () { location.reload(); });
						else location.reload();
					}).catch(errorServidor(tituloError));
				});
			});
		});
	}
	// Un calendario activo siempre está incompleto (al completarse se cierra solo), así que cerrarlo a mano se avisa con las cifras reales.
	accionTarjeta('.ep-cal-generar-ahora', function (btn) {
		var tarjeta = btn.closest('.ep-cl-cal');
		var total = tarjeta.querySelectorAll('.ep-cl-f[data-fila-id]').length;
		var cumplidas = tarjeta.querySelectorAll('.ep-cl-f[data-estado="cumplido"]').length;
		var faltan = total - cumplidas;
		var consecuencia = cumplidas === 0 ? 'No se generará reporte.' : 'El reporte incluirá solo las cumplidas.';
		return {
			icon: 'warning', title: 'Cerrar sin completar',
			html: '<b>' + cumplidas + ' de ' + total + '</b> filas cumplidas, faltan <b>' + faltan + '</b>. ' + consecuencia,
			showCancelButton: true, focusCancel: true, reverseButtons: true,
			confirmButtonText: 'Sí, cerrar incompleto', cancelButtonText: 'Seguir esperando', confirmButtonColor: '#B25E00',
		};
	}, 'getters/calendario_generar.php', 'No se pudo generar');
	accionTarjeta('.ep-cal-reactivar', {
		icon: 'warning', title: '¿Reactivar este calendario?',
		text: 'Volverá a aceptar registros con un plazo nuevo desde hoy.',
		input: 'textarea', inputLabel: 'Motivo de la reactivación', inputPlaceholder: 'Escribe por qué se reactiva este calendario', inputAttributes: { maxlength: 300 },
		inputValidator: function (v) { return !v || v.trim().length < 8 ? 'Escribe el motivo (mínimo 8 caracteres).' : null; },
		showCancelButton: true, confirmButtonText: 'Sí, reactivar', cancelButtonText: 'Cancelar',
	}, 'getters/calendario_reactivar.php', 'No se pudo reactivar');
	accionTarjeta('.ep-cal-eliminar', {
		icon: 'warning', title: '¿Eliminar este calendario?',
		text: 'Deja de aparecer en la lista y ya no cruza registros nuevos. Si ya generó un reporte mensual, ese reporte se conserva.',
		showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#C5221F',
	}, 'getters/calendario_eliminar.php', 'No se pudo eliminar');
})();

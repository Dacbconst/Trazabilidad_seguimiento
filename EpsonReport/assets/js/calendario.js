// Calendario de Activaciones: cascada Ciudad -> Promotor -> Punto de venta, acciones y edición de fila, todo real.
(function () {
	var modal = document.getElementById('epCalModal');
	if (!modal) return;

	var RUTERO = { RETAIL: [], CANALES: [] };
	try { RUTERO = JSON.parse(document.getElementById('epCalDatosRutero').textContent); } catch (e) { /* sin datos: queda vacío */ }

	function post(url, datos) {
		var form = new FormData();
		Object.keys(datos).forEach(function (k) { form.append(k, datos[k]); });
		return fetch(url, { method: 'POST', body: form, credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}
	function avisar(icono, titulo, texto) {
		if (window.Swal) Swal.fire({ icon: icono, title: titulo, text: texto || '' });
	}

	var btnNuevo = document.getElementById('epCalNuevo');
	var btnCancelar = document.getElementById('epCalCancelar');
	var btnCerrar = document.getElementById('epCalModalCerrar');
	var fondo = document.getElementById('epCalModalFondo');
	var btnAgregarFila = document.getElementById('epCalAgregarFila');
	var filasEditor = document.getElementById('epCalFilasEditor');
	var canalSelect = document.getElementById('epCalCanal');
	var rangoAuto = document.getElementById('epCalRangoAuto');

	function abrir() { modal.classList.remove('hidden'); }
	function cerrar() { modal.classList.add('hidden'); }
	if (btnNuevo) btnNuevo.addEventListener('click', abrir);
	if (btnCerrar) btnCerrar.addEventListener('click', cerrar);
	if (btnCancelar) btnCancelar.addEventListener('click', cerrar);
	if (fondo) fondo.addEventListener('click', cerrar);

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
			var visibles = (combo._opciones || []).filter(function (o) { return o.texto.toLowerCase().indexOf(q) !== -1; });
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
		combo.validar = function () {
			var ok = !trigger.disabled && !!trigger.dataset.valor;
			trigger.classList.toggle('ep-campo-error', !ok);
			return ok;
		};
	}

	// Cascada de una fila: Ciudad -> Promotor -> Punto de venta, con Supervisor mostrado aparte (nunca elegible a mano).
	function iniciarFila(fila) {
		var comboCiudad = fila.querySelector('.ep-cal-combo-ciudad');
		var comboPromotor = fila.querySelector('.ep-cal-combo-promotor');
		var comboPdv = fila.querySelector('.ep-cal-combo-pdv');
		var supervisorTexto = fila.querySelector('.ep-cal-supervisor-auto');
		var supervisorSelect = fila.querySelector('.ep-cal-supervisor-select');

		iniciarCombo(comboCiudad, 'Ciudad');
		iniciarCombo(comboPromotor, 'Promotor');
		iniciarCombo(comboPdv, 'Punto de venta');

		function mostrarSupervisor(texto) {
			supervisorSelect.classList.add('hidden');
			supervisorTexto.classList.remove('hidden');
			supervisorTexto.textContent = texto;
			supervisorTexto.title = texto;
		}
		// Si el promotor tiene más de un supervisor en el rutero, no se adivina: se muestra un selector para elegir.
		function mostrarSupervisorAmbiguo(nombres) {
			supervisorTexto.classList.add('hidden');
			supervisorSelect.classList.remove('hidden');
			supervisorSelect.innerHTML = '<option value="">Elige supervisor</option>' + nombres.map(function (n) { return '<option value="' + n + '">' + n + '</option>'; }).join('');
		}

		function datos() { return RUTERO[canalSelect.value] || []; }
		function refrescarCiudades() {
			var ciudades = Array.from(new Set(datos().map(function (r) { return r.ciudad; }))).sort();
			comboCiudad.habilitar(ciudades.map(function (c) { return { texto: c, valor: c }; }));
			comboPromotor.deshabilitar('Elige ciudad');
			comboPdv.deshabilitar('Elige promotor');
			mostrarSupervisor('—');
		}
		comboCiudad.addEventListener('elegido', function (e) {
			var ciudad = e.detail.valor;
			var promotores = datos().filter(function (r) { return r.ciudad === ciudad; });
			var unicos = {};
			promotores.forEach(function (r) { unicos[r.promotor_id] = r.promotor_nombre; });
			var opciones = Object.keys(unicos).map(function (id) { return { texto: unicos[id], valor: id }; });
			comboPromotor.habilitar(opciones);
			comboPdv.deshabilitar('Elige promotor');
			mostrarSupervisor('—');
		});
		comboPromotor.addEventListener('elegido', function (e) {
			var ciudad = comboCiudad.querySelector('.ep-combo-trigger').dataset.valor;
			var promotorId = +e.detail.valor;
			var puntos = datos().filter(function (r) { return r.ciudad === ciudad && r.promotor_id === promotorId; });
			comboPdv.habilitar(puntos.map(function (p) { return { texto: p.punto_venta, valor: p.pos_id }; }));
			var supervisores = Array.from(new Set(puntos.map(function (p) { return p.supervisor; }).filter(Boolean)));
			if (supervisores.length > 1) mostrarSupervisorAmbiguo(supervisores);
			else mostrarSupervisor(supervisores[0] || 'Sin asignar');
		});
		// Al elegir el punto de venta ya no hay ambigüedad: ese punto solo tiene un supervisor real.
		comboPdv.addEventListener('elegido', function (e) {
			var ciudad = comboCiudad.querySelector('.ep-combo-trigger').dataset.valor;
			var promotorId = +comboPromotor.querySelector('.ep-combo-trigger').dataset.valor;
			var punto = datos().find(function (r) { return r.ciudad === ciudad && r.promotor_id === promotorId && r.pos_id === e.detail.valor; });
			mostrarSupervisor((punto && punto.supervisor) || 'Sin asignar');
		});
		fila.querySelector('.ep-cal-fecha-input').addEventListener('change', recalcularRango);
		refrescarCiudades();
		if (canalSelect) canalSelect.addEventListener('change', refrescarCiudades);
	}
	filasEditor.querySelectorAll('.ep-cal-fila-editor').forEach(iniciarFila);

	if (btnAgregarFila) {
		btnAgregarFila.addEventListener('click', function () {
			var fila = filasEditor.firstElementChild.cloneNode(true);
			fila.querySelectorAll('input[type="date"]').forEach(function (i) { i.value = ''; });
			filasEditor.appendChild(fila);
			iniciarFila(fila);
		});
		filasEditor.addEventListener('click', function (e) {
			var quitar = e.target.closest('.ep-modelo-quitar');
			if (quitar && filasEditor.children.length > 1) {
				quitar.closest('.ep-cal-fila-editor').remove();
				recalcularRango();
			}
		});
	}

	var btnCrear = document.getElementById('epCalCrear');
	if (btnCrear) {
		btnCrear.addEventListener('click', function () {
			var filasEl = filasEditor.querySelectorAll('.ep-cal-fila-editor');
			var valido = true;
			var filas = [];
			filasEl.forEach(function (fila) {
				var fecha = fila.querySelector('.ep-cal-fecha-input').value;
				var comboPromotor = fila.querySelector('.ep-cal-combo-promotor');
				var comboPdv = fila.querySelector('.ep-cal-combo-pdv');
				if (!fecha) valido = false;
				if (!fila.querySelector('.ep-cal-combo-ciudad').validar()) valido = false;
				if (!comboPromotor.validar()) valido = false;
				if (!comboPdv.validar()) valido = false;
				filas.push({
					fecha: fecha,
					pos_id: comboPdv.querySelector('.ep-combo-trigger').dataset.valor,
					promotor_id: comboPromotor.querySelector('.ep-combo-trigger').dataset.valor,
				});
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
					nombre: document.getElementById('epCalNombre').value.trim(),
					canal: canalSelect.value,
					plazo_dias: document.getElementById('epCalPlazo').value,
					filas: JSON.stringify(filas),
				}).then(function (r) {
					if (r.ok) location.reload();
					else avisar('error', 'No se pudo crear', r.message);
				});
			});
		});
	}

	document.querySelectorAll('.ep-cal-generar-ahora').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!window.Swal) return;
			Swal.fire({
				icon: 'question', title: '¿Generar el reporte ahora?',
				text: 'El calendario se cierra con lo que se haya cumplido hasta ahora y pasa automáticamente a Reportes mensuales.',
				showCancelButton: true, confirmButtonText: 'Sí, generar y cerrar', cancelButtonText: 'Cancelar',
			}).then(function (res) {
				if (!res.isConfirmed) return;
				post('getters/calendario_generar.php', { id: btn.dataset.id }).then(function (r) {
					if (r.ok) location.reload();
					else avisar('error', 'No se pudo generar', r.message);
				});
			});
		});
	});
	document.querySelectorAll('.ep-cal-reactivar').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!window.Swal) return;
			Swal.fire({
				icon: 'warning', title: '¿Reactivar este calendario?',
				text: 'Vuelve a aceptar registros con un plazo nuevo desde ahora. Esta acción queda registrada con tu usuario y la fecha.',
				showCancelButton: true, confirmButtonText: 'Sí, reactivar', cancelButtonText: 'Cancelar',
			}).then(function (res) {
				if (!res.isConfirmed) return;
				post('getters/calendario_reactivar.php', { id: btn.dataset.id }).then(function (r) {
					if (r.ok) location.reload();
					else avisar('error', 'No se pudo reactivar', r.message);
				});
			});
		});
	});

	// Edición inline de punto de venta / promotor en una fila ya creada (solo si es-editable: la fecha aún no pasó).
	document.querySelectorAll('.ep-cal-editar-campo').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var filaFila = btn.closest('.ep-cal-fila');
			var canal = filaFila.dataset.canal;
			var campo = btn.dataset.campo;
			var promotorIdActual = +filaFila.querySelector('.ep-cal-valor-promotor').dataset.promotorId;
			if (!window.Swal) return;

			var opciones;
			if (campo === 'promotor') {
				var vistos = {};
				opciones = (RUTERO[canal] || []).filter(function (r) { return !vistos[r.promotor_id] && (vistos[r.promotor_id] = 1); })
					.map(function (r) { return { value: r.promotor_id, text: r.promotor_nombre }; });
			} else {
				opciones = (RUTERO[canal] || []).filter(function (r) { return r.promotor_id === promotorIdActual; })
					.map(function (r) { return { value: r.pos_id, text: r.punto_venta }; });
			}
			var inputOptions = {};
			opciones.forEach(function (o) { inputOptions[o.value] = o.text; });
			Swal.fire({
				title: campo === 'pdv' ? 'Elegir nuevo punto de venta' : 'Elegir nuevo promotor',
				input: 'select', inputOptions: inputOptions, inputPlaceholder: 'Selecciona de la lista',
				showCancelButton: true, confirmButtonText: 'Guardar', cancelButtonText: 'Cancelar',
			}).then(function (res) {
				if (!res.isConfirmed || !res.value) return;
				var posId = campo === 'pdv' ? res.value : filaFila.querySelector('.ep-cal-valor-pdv').dataset.posId;
				var promotorId = campo === 'promotor' ? res.value : promotorIdActual;
				post('getters/calendario_fila_editar.php', { fila_id: filaFila.dataset.filaId, canal: canal, pos_id: posId, promotor_id: promotorId }).then(function (r) {
					if (r.ok) location.reload();
					else avisar('error', 'No se pudo editar', r.message);
				});
			});
		});
	});
})();

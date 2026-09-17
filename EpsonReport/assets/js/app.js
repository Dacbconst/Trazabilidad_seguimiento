// Mismos paths que ep_icon() en PHP, solo para los íconos que este archivo re-renderiza en JS.
function epIconMarkup(nombre, size) {
	var paths = {
		'chevron': '<path d="M6 9l6 6 6-6"/>',
		'chevron-up': '<path d="M18 15l-6-6-6 6"/>',
		'trash': '<path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-8 0l1 13a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2l1-13"/>',
	};
	return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' + (paths[nombre] || '') + '</svg>';
}

// Título estilo "Actividad 2": primera letra de cada palabra en mayúscula, sin importar cómo se tipeó.
function epFormatoTitulo(texto) {
	return (texto || '').trim().replace(/\s+/g, ' ').toLowerCase().replace(/(^|\s)([a-záéíóúñ])/g, function (m, sep, letra) {
		return sep + letra.toUpperCase();
	});
}

document.addEventListener('DOMContentLoaded', function () {
	// Selección de actividad (módulo Actividades)
	var listaActividades = document.getElementById('ep-lista-actividades');
	if (listaActividades) {
		var labelSeleccion = document.getElementById('ep-seleccion-label');
		listaActividades.addEventListener('click', function (ev) {
			var btn = ev.target.closest('.ep-activity-item');
			if (!btn) return;
			listaActividades.querySelectorAll('.ep-activity-item').forEach(function (el) {
				el.classList.remove('selected');
			});
			btn.classList.add('selected');
			if (labelSeleccion) labelSeleccion.textContent = btn.dataset.nombre || '';
			mostrarFormularioDeActividad(btn.dataset.id);
			// Elegir otra actividad mientras "Nueva actividad" está abierto vuelve al formulario normal.
			mostrarFormulario();
		});
	}

	// Cambia cuál de las plantillas (una por actividad, renderizadas todas en el servidor) se ve, formulario y estadísticas juntos.
	function mostrarFormularioDeActividad(id) {
		document.querySelectorAll('.ep-formulario-actividad, .ep-estadisticas-actividad').forEach(function (el) {
			el.classList.toggle('hidden', el.dataset.actividadId !== id);
		});
	}

	// Marca en amarillo un instante el campo que se acaba de topar, para que se note que el sistema lo corrigió solo.
	function destacarTope(campo) {
		campo.classList.remove('ep-input-tope-flash');
		void campo.offsetWidth; // fuerza el reflow para poder re-disparar la animación si se topa dos veces seguidas
		campo.classList.add('ep-input-tope-flash');
	}

	// Formulario de Activaciones: ningún paso del embudo puede superar al anterior (ni realizadas a programadas)
	function aplicarTope(base, dependiente) {
		function clamp() {
			var max = parseFloat(base.value) || 0;
			dependiente.max = max;
			if ((parseFloat(dependiente.value) || 0) > max) {
				dependiente.value = max;
				destacarTope(dependiente);
				dependiente.dispatchEvent(new Event('input')); // encadena el tope al siguiente campo, si lo tiene
			}
		}
		base.addEventListener('input', clamp);
		dependiente.addEventListener('input', clamp);
		clamp();
	}
	var actNacional = document.getElementById('ep-act-nacional');
	var actCoberturadas = document.getElementById('ep-act-coberturadas');
	var actVisitaron = document.getElementById('ep-act-visitaron');
	var actInteractuaron = document.getElementById('ep-act-interactuaron');
	var actCompraron = document.getElementById('ep-act-compraron');
	var actProgramadas = document.getElementById('ep-act-programadas');
	var actRealizadas = document.getElementById('ep-act-realizadas');

	if (actNacional && actCoberturadas) {
		// Coberturadas es automático (repositorio de puntos de venta), no se tipea, pero nunca debería superar al nacional.
		var coberturadasOriginal = parseFloat(actCoberturadas.dataset.valor) || 0;
		actNacional.addEventListener('input', function () {
			var nuevoValor = Math.min(coberturadasOriginal, parseFloat(actNacional.value) || 0);
			if (nuevoValor < parseFloat(actCoberturadas.textContent)) destacarTope(actCoberturadas.closest('.ep-input'));
			actCoberturadas.textContent = nuevoValor;
			actualizarEstadisticasActivaciones();
		});
	}
	if (actVisitaron && actInteractuaron) aplicarTope(actVisitaron, actInteractuaron);
	if (actInteractuaron && actCompraron) aplicarTope(actInteractuaron, actCompraron);
	if (actProgramadas && actRealizadas) aplicarTope(actProgramadas, actRealizadas);

	// Cualquier campo de Activaciones cambia algo del panel de estadísticas de al lado.
	[actVisitaron, actInteractuaron, actCompraron, actProgramadas, actRealizadas].forEach(function (input) {
		if (input) input.addEventListener('input', actualizarEstadisticasActivaciones);
	});
	var actComentarios = document.getElementById('ep-act-comentarios');
	if (actComentarios) actComentarios.addEventListener('input', actualizarEstadisticasActivaciones);

	// Formulario de Activaciones: filas de modelo dinámicas, cada una con un buscador (spinner) sobre el catálogo mock
	var modeloFilas = document.getElementById('ep-modelo-filas');
	var modeloAgregarBtn = document.getElementById('ep-modelo-agregar');
	var modeloTotalValor = document.getElementById('ep-modelo-total-valor');

	function filaModeloHTML() {
		return '<div class="ep-modelo-fila-nueva">'
			+ '<div class="ep-combo">'
			+ '<button type="button" class="ep-input ep-combo-trigger" data-valor="">'
			+ '<span class="ep-combo-trigger-texto">Elegir modelo...</span>'
			+ epIconMarkup('chevron', 14)
			+ '</button>'
			+ '<div class="ep-combo-panel hidden">'
			+ '<input type="text" class="ep-input ep-combo-buscador" placeholder="Buscar modelo..." autocomplete="off">'
			+ '<div class="ep-combo-opciones"></div>'
			+ '</div>'
			+ '</div>'
			+ '<input type="number" min="0" class="ep-input ep-modelo-cantidad" placeholder="Cantidad">'
			+ '<button type="button" class="ep-modelo-quitar" aria-label="Quitar modelo">' + epIconMarkup('trash', 14) + '</button>'
			+ '</div>';
	}

	function actualizarTotalModelos() {
		if (!modeloFilas || !modeloTotalValor) return;
		var total = 0;
		modeloFilas.querySelectorAll('.ep-modelo-cantidad').forEach(function (input) {
			total += parseFloat(input.value) || 0;
		});
		modeloTotalValor.textContent = total;
		actualizarEstadisticasActivaciones();
	}

	// Panel "Así se ve el reporte final": recalcula las cards del Excel en vivo con lo que hay en el formulario.
	function escapeHtml(texto) {
		var div = document.createElement('div');
		div.textContent = texto;
		return div.innerHTML;
	}
	function pctTexto(parte, total) {
		var t = parseFloat(total);
		if (!t) return '0%';
		return Math.round((parseFloat(parte) || 0) / t * 100) + '%';
	}
	function modelosCargados() {
		if (!modeloFilas) return [];
		var filas = [];
		modeloFilas.querySelectorAll('.ep-modelo-fila-nueva').forEach(function (fila) {
			var nombre = fila.querySelector('.ep-combo-trigger').dataset.valor;
			var cantidad = parseFloat(fila.querySelector('.ep-modelo-cantidad').value) || 0;
			if (nombre && cantidad > 0) filas.push({ nombre: nombre, cantidad: cantidad });
		});
		return filas;
	}
	function actualizarEstadisticasActivaciones() {
		var statCoberturaPct = document.getElementById('ep-stat-cobertura-pct');
		if (!statCoberturaPct) return; // esta actividad no tiene panel de estadísticas todavía

		var nacional = actNacional ? actNacional.value : 0;
		var coberturadas = actCoberturadas ? actCoberturadas.textContent : 0;
		var visitaron = actVisitaron ? actVisitaron.value : 0;
		var interactuaron = actInteractuaron ? actInteractuaron.value : 0;
		var compraron = actCompraron ? actCompraron.value : 0;
		var realizadas = actRealizadas ? actRealizadas.value : 0;

		statCoberturaPct.textContent = pctTexto(coberturadas, nacional);
		document.getElementById('ep-stat-nacional').textContent = nacional || 0;
		document.getElementById('ep-stat-coberturadas').textContent = coberturadas || 0;

		document.getElementById('ep-stat-interaccion-pct').textContent = pctTexto(interactuaron, visitaron);
		document.getElementById('ep-stat-visitaron').textContent = visitaron || 0;
		document.getElementById('ep-stat-interactuaron').textContent = interactuaron || 0;

		document.getElementById('ep-stat-ventas-pct').textContent = pctTexto(compraron, interactuaron);
		document.getElementById('ep-stat-ventas-realizadas').textContent = realizadas || 0;

		var maxEmbudo = Math.max(parseFloat(visitaron) || 0, 1);
		document.getElementById('ep-stat-bar-visitaron-valor').textContent = visitaron || 0;
		document.getElementById('ep-stat-bar-visitaron').style.height = pctTexto(visitaron, maxEmbudo);
		document.getElementById('ep-stat-bar-interactuaron-valor').textContent = interactuaron || 0;
		document.getElementById('ep-stat-bar-interactuaron').style.height = pctTexto(interactuaron, maxEmbudo);
		document.getElementById('ep-stat-bar-compraron-valor').textContent = compraron || 0;
		document.getElementById('ep-stat-bar-compraron').style.height = pctTexto(compraron, maxEmbudo);

		var detalleVentas = document.getElementById('ep-stat-detalle-ventas');
		var modelos = modelosCargados().sort(function (a, b) { return b.cantidad - a.cantidad; });
		var totalUnidades = modelos.reduce(function (s, m) { return s + m.cantidad; }, 0);

		if (!modelos.length) {
			detalleVentas.innerHTML = '<span class="ep-stat-comentarios-vacio">Todavía no cargaste modelos.</span>';
		} else {
			var maxCantidad = modelos[0].cantidad;
			detalleVentas.innerHTML = modelos.map(function (m) {
				return '<div class="ep-venta-fila">'
					+ '<span class="ep-venta-nombre">' + escapeHtml(m.nombre) + '</span>'
					+ '<div class="ep-venta-barra-track"><div class="ep-venta-barra-fill" style="width:' + Math.round(m.cantidad / maxCantidad * 100) + '%;"></div></div>'
					+ '<span class="ep-venta-valor">' + m.cantidad + '</span>'
					+ '</div>';
			}).join('');
		}

		var mayorPct = document.getElementById('ep-stat-mayor-pct');
		var mayorNombre = document.getElementById('ep-stat-mayor-nombre');
		var menorPct = document.getElementById('ep-stat-menor-pct');
		var menorNombre = document.getElementById('ep-stat-menor-nombre');
		if (modelos.length) {
			var mayorCant = modelos[0].cantidad;
			var menorCant = modelos[modelos.length - 1].cantidad;
			var mayores = modelos.filter(function (m) { return m.cantidad === mayorCant; }).map(function (m) { return m.nombre; });
			var menores = modelos.filter(function (m) { return m.cantidad === menorCant; }).map(function (m) { return m.nombre; });
			mayorPct.textContent = pctTexto(mayorCant, totalUnidades);
			mayorNombre.textContent = mayores.join(' / ');
			menorPct.textContent = pctTexto(menorCant, totalUnidades);
			menorNombre.textContent = menores.join(' / ');
		} else {
			mayorPct.textContent = '0%';
			mayorNombre.textContent = 'Sin datos';
			menorPct.textContent = '0%';
			menorNombre.textContent = 'Sin datos';
		}

		var comentariosTextarea = document.getElementById('ep-act-comentarios');
		var comentariosBox = document.getElementById('ep-stat-comentarios');
		var lineas = comentariosTextarea ? comentariosTextarea.value.split('\n').map(function (l) { return l.trim(); }).filter(Boolean) : [];
		comentariosBox.innerHTML = lineas.length
			? lineas.map(function (l) { return '<div>' + escapeHtml(l) + '</div>'; }).join('')
			: '<span class="ep-stat-comentarios-vacio">Sin comentarios todavía.</span>';
	}

	function cerrarPanelesCombo() {
		document.querySelectorAll('.ep-combo-panel').forEach(function (panel) { panel.classList.add('hidden'); });
	}

	function modelosElegidosEnOtrasFilas(comboActual) {
		var elegidos = [];
		modeloFilas.querySelectorAll('.ep-combo').forEach(function (combo) {
			if (combo === comboActual) return;
			var valor = combo.querySelector('.ep-combo-trigger').dataset.valor;
			if (valor) elegidos.push(valor);
		});
		return elegidos;
	}
	// El buscador vive DENTRO del panel del spinner — el campo visible (el trigger) nunca se tipea, solo se elige de la lista.
	function filtrarComboModelo(combo, texto) {
		var opciones = combo.querySelector('.ep-combo-opciones');
		var yaElegidos = modelosElegidosEnOtrasFilas(combo);
		var catalogo = (window.EP_CATALOGO_MODELOS || []).filter(function (m) { return yaElegidos.indexOf(m) === -1; });
		var coincidencias = catalogo.filter(function (m) { return m.toLowerCase().indexOf((texto || '').trim().toLowerCase()) !== -1; });

		opciones.innerHTML = coincidencias.length
			? coincidencias.map(function (m) { return '<button type="button" class="ep-combo-opcion" data-valor="' + m + '">' + m + '</button>'; }).join('')
			: '<div class="ep-combo-vacio">Ya elegiste todos los modelos disponibles, o no hay resultados</div>';
	}

	function abrirPanelCombo(combo) {
		cerrarPanelesCombo();
		var buscador = combo.querySelector('.ep-combo-buscador');
		buscador.value = '';
		filtrarComboModelo(combo, '');
		combo.querySelector('.ep-combo-panel').classList.remove('hidden');
		buscador.focus();
	}

	function agregarFilaModelo() {
		if (!modeloFilas) return;
		modeloFilas.insertAdjacentHTML('beforeend', filaModeloHTML());
	}

	if (modeloFilas) {
		agregarFilaModelo(); // arranca con una fila vacía, igual que el resto de tablas de la app

		modeloFilas.addEventListener('input', function (ev) {
			if (ev.target.classList.contains('ep-combo-buscador')) filtrarComboModelo(ev.target.closest('.ep-combo'), ev.target.value);
			if (ev.target.classList.contains('ep-modelo-cantidad')) actualizarTotalModelos();
		});
		modeloFilas.addEventListener('click', function (ev) {
			var trigger = ev.target.closest('.ep-combo-trigger');
			if (trigger) {
				var combo = trigger.closest('.ep-combo');
				var abierto = !combo.querySelector('.ep-combo-panel').classList.contains('hidden');
				cerrarPanelesCombo();
				if (!abierto) abrirPanelCombo(combo);
				return;
			}
			var opcion = ev.target.closest('.ep-combo-opcion');
			if (opcion) {
				var comboElegido = opcion.closest('.ep-combo');
				var triggerElegido = comboElegido.querySelector('.ep-combo-trigger');
				triggerElegido.dataset.valor = opcion.dataset.valor;
				triggerElegido.querySelector('.ep-combo-trigger-texto').textContent = opcion.dataset.valor;
				comboElegido.querySelector('.ep-combo-panel').classList.add('hidden');
				actualizarEstadisticasActivaciones();
				return;
			}
			var quitar = ev.target.closest('.ep-modelo-quitar');
			if (quitar) {
				quitar.closest('.ep-modelo-fila-nueva').remove();
				actualizarTotalModelos();
			}
		});
	}
	if (modeloAgregarBtn) modeloAgregarBtn.addEventListener('click', agregarFilaModelo);
	actualizarEstadisticasActivaciones(); // primer cálculo, con los valores de ejemplo que ya trae el formulario

	// Cierra cualquier panel de búsqueda abierto al hacer clic afuera.
	document.addEventListener('click', function (ev) {
		if (!ev.target.closest('.ep-combo')) cerrarPanelesCombo();
	});

	var buscarActividad = document.getElementById('ep-buscar-actividad');
	if (buscarActividad && listaActividades) {
		buscarActividad.addEventListener('input', function () {
			var texto = buscarActividad.value.trim().toLowerCase();
			listaActividades.querySelectorAll('.ep-activity-item').forEach(function (el) {
				var nombre = (el.dataset.nombre || '').toLowerCase();
				el.classList.toggle('hidden', texto !== '' && nombre.indexOf(texto) === -1);
			});
		});
	}

	// Historial: plegar/expandir un registro puntual
	var gruposHistorial = document.getElementById('ep-hist-grupos');
	function toggleRegistro(btn, forzarAbierto) {
		var detalle = document.getElementById(btn.dataset.target);
		if (!detalle) return;
		var abrir = forzarAbierto !== undefined ? forzarAbierto : detalle.classList.contains('hidden');
		detalle.classList.toggle('hidden', !abrir);
		btn.setAttribute('aria-expanded', abrir ? 'true' : 'false');
		btn.querySelector('.ep-hist-toggle-text').textContent = abrir ? 'Plegar' : 'Detalles';
		btn.querySelector('.ep-hist-toggle-icon-down').classList.toggle('hidden', abrir);
		btn.querySelector('.ep-hist-toggle-icon-up').classList.toggle('hidden', !abrir);
		var article = btn.closest('.ep-hist-record');
		if (article) article.classList.toggle('ep-hist-record-abierto', abrir);
	}
	if (gruposHistorial) {
		gruposHistorial.addEventListener('click', function (ev) {
			var btn = ev.target.closest('.ep-hist-toggle');
			if (!btn) return;
			toggleRegistro(btn);
		});
	}

	// Historial: "Plegar todos" / "Expandir todos"
	var expandirTodosBtn = document.getElementById('ep-hist-expandir-todos');
	if (expandirTodosBtn && gruposHistorial) {
		expandirTodosBtn.addEventListener('click', function () {
			var abrir = expandirTodosBtn.dataset.estado === 'abrir';
			gruposHistorial.querySelectorAll('.ep-hist-toggle').forEach(function (btn) {
				toggleRegistro(btn, abrir);
			});
			expandirTodosBtn.dataset.estado = abrir ? 'cerrar' : 'abrir';
			expandirTodosBtn.innerHTML = abrir
				? epIconMarkup('chevron-up', 15) + ' Plegar todos'
				: epIconMarkup('chevron', 15) + ' Expandir todos';
		});
	}

	// Historial: filtro por tipo de actividad (con chip activo + restablecer)
	var histTipoSelect = document.getElementById('ep-hist-tipo');
	var histChip = document.getElementById('ep-hist-chip');
	var histChipTexto = document.getElementById('ep-hist-chip-texto');
	var histChipCerrar = document.getElementById('ep-hist-chip-cerrar');
	var histRestablecer = document.getElementById('ep-hist-restablecer');
	var histResumen = document.getElementById('ep-hist-resumen');

	function aplicarFiltroHistorial() {
		if (!histTipoSelect || !gruposHistorial) return;
		var valor = histTipoSelect.value;
		var esTodas = valor === 'all';
		var visibles = 0;

		gruposHistorial.querySelectorAll('.ep-hist-day').forEach(function (dia) {
			var algunoVisible = false;
			dia.querySelectorAll('.ep-hist-record').forEach(function (registro) {
				var coincide = esTodas || registro.dataset.tipo === valor;
				registro.classList.toggle('hidden', !coincide);
				if (coincide) { algunoVisible = true; visibles++; }
			});
			dia.classList.toggle('hidden', !algunoVisible);
		});

		if (histResumen) histResumen.textContent = visibles + (visibles === 1 ? ' registro' : ' registros');
		if (histChip && histChipTexto) {
			histChip.classList.toggle('hidden', esTodas);
			if (!esTodas) histChipTexto.textContent = histTipoSelect.options[histTipoSelect.selectedIndex].text.replace(/\s*\(\d+\)$/, '');
		}
		if (histRestablecer) histRestablecer.classList.toggle('hidden', esTodas);
	}

	if (histTipoSelect) histTipoSelect.addEventListener('change', aplicarFiltroHistorial);
	function restablecerFiltroHistorial() {
		if (histTipoSelect) histTipoSelect.value = 'all';
		aplicarFiltroHistorial();
	}
	if (histChipCerrar) histChipCerrar.addEventListener('click', restablecerFiltroHistorial);
	if (histRestablecer) histRestablecer.addEventListener('click', restablecerFiltroHistorial);

	// Historial: pastillas de rango (Hoy / Últimos 7 días / Este mes) — visual, sin datos reales aún
	var histRango = document.querySelector('.ep-hist-rango');
	if (histRango) {
		histRango.addEventListener('click', function (ev) {
			var btn = ev.target.closest('.ep-hist-rango-btn');
			if (!btn) return;
			histRango.querySelectorAll('.ep-hist-rango-btn').forEach(function (b) {
				b.classList.remove('ep-hist-rango-activo');
			});
			btn.classList.add('ep-hist-rango-activo');
		});
	}

	// Toggle entre "Formulario" y "Nueva actividad" (mismo módulo, solo rol admin lo ve)
	var nuevaActividadBtn = document.getElementById('ep-nueva-actividad-btn');
	var cancelarBtn = document.getElementById('ep-cancelar-nueva-actividad');
	var panelFormulario = document.getElementById('ep-panel-formulario');
	var layoutActividad = document.querySelector('.ep-actividad-layout');
	var panelConstructor = document.getElementById('ep-panel-constructor');
	var headerFormulario = document.getElementById('ep-header-formulario');
	var headerConstructor = document.getElementById('ep-header-constructor');

	function mostrarConstructor() {
		if (!panelConstructor) return;
		if (layoutActividad) layoutActividad.classList.add('hidden');
		panelConstructor.classList.remove('hidden');
		if (headerFormulario) headerFormulario.classList.add('hidden');
		if (headerConstructor) headerConstructor.classList.remove('hidden');
		actualizarVistaPrevia();
	}
	function mostrarFormulario() {
		if (!panelConstructor) return;
		panelConstructor.classList.add('hidden');
		if (layoutActividad) layoutActividad.classList.remove('hidden');
		if (headerConstructor) headerConstructor.classList.add('hidden');
		if (headerFormulario) headerFormulario.classList.remove('hidden');
	}

	if (nuevaActividadBtn && panelFormulario && panelConstructor) {
		nuevaActividadBtn.addEventListener('click', mostrarConstructor);
	}
	if (cancelarBtn) {
		cancelarBtn.addEventListener('click', mostrarFormulario);
	}

	// Vista previa en vivo de "Nueva actividad": nombre tipeado + lógica elegida (solo rol admin)
	var nuevaNombre = document.getElementById('ep-nueva-nombre');
	var nuevaLogica = document.getElementById('ep-nueva-logica');
	var previewBotonLabel = document.getElementById('ep-preview-boton-label');
	var previewFormTitulo = document.getElementById('ep-preview-form-titulo');
	var previewFormCampos = document.getElementById('ep-preview-form-campos');

	function actualizarVistaPrevia() {
		if (!nuevaLogica || !window.EP_LOGICAS) return;
		var nombre = epFormatoTitulo(nuevaNombre.value) || 'Nombre de la actividad';
		var logica = window.EP_LOGICAS[nuevaLogica.value] || window.EP_LOGICAS[0];

		if (previewBotonLabel) previewBotonLabel.textContent = nombre;
		if (previewFormTitulo) previewFormTitulo.textContent = nombre;

		if (previewFormCampos) {
			previewFormCampos.innerHTML = '';
			logica.campos.forEach(function (campo) {
				var fila = document.createElement('div');
				fila.className = 'ep-preview-campo';
				var nota = campo.tipo === 'auto' ? 'Se completa automático' : 'Campo de esta lógica';
				fila.innerHTML = '<span style="font-weight:600;">' + campo.label + '</span><span style="color:var(--color-text-muted);">' + nota + '</span>';
				previewFormCampos.appendChild(fila);
			});
		}
	}

	if (nuevaNombre) {
		nuevaNombre.addEventListener('input', actualizarVistaPrevia);
		// Corrige el formato del campo recién al salir (no mientras se tipea, para no pelear con el cursor).
		nuevaNombre.addEventListener('blur', function () {
			nuevaNombre.value = epFormatoTitulo(nuevaNombre.value);
		});
	}
	if (nuevaLogica) nuevaLogica.addEventListener('change', actualizarVistaPrevia);

	// Gestión de actividades existentes (mockup, solo visual — sin borrado real todavía)
	var gestionLista = document.getElementById('ep-gestion-lista');
	if (gestionLista && listaActividades) {
		gestionLista.addEventListener('click', function (ev) {
			var btnEliminar = ev.target.closest('.ep-gestion-eliminar');
			if (!btnEliminar) return;
			var fila = btnEliminar.closest('.ep-gestion-fila');
			var nombre = fila.querySelector('.ep-gestion-nombre').textContent;
			if (!window.confirm('¿Eliminar "' + nombre + '"? Esta acción no se puede deshacer.')) return;

			var id = btnEliminar.dataset.id;
			var botonSidebar = listaActividades.querySelector('.ep-activity-item[data-id="' + id + '"]');
			if (botonSidebar) botonSidebar.remove();
			fila.remove();
		});

		gestionLista.addEventListener('change', function (ev) {
			var toggle = ev.target.closest('.ep-gestion-switch');
			if (!toggle) return;
			var fila = toggle.closest('.ep-gestion-fila');
			var id = fila.dataset.id;
			var botonSidebar = listaActividades.querySelector('.ep-activity-item[data-id="' + id + '"]');
			var activa = toggle.checked;

			fila.classList.toggle('ep-gestion-inactiva', !activa);
			if (botonSidebar) botonSidebar.classList.toggle('hidden', !activa);
		});
	}
});

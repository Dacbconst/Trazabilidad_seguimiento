// Mismos paths que ep_icon() en PHP, solo para los 2 íconos que este archivo re-renderiza en JS.
function epIconMarkup(nombre, size) {
	var paths = {
		'chevron': '<path d="M6 9l6 6 6-6"/>',
		'chevron-up': '<path d="M18 15l-6-6-6 6"/>',
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
			// Elegir otra actividad mientras "Nueva actividad" está abierto vuelve al formulario normal.
			mostrarFormulario();
		});
	}

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
	var panelConstructor = document.getElementById('ep-panel-constructor');
	var headerFormulario = document.getElementById('ep-header-formulario');
	var headerConstructor = document.getElementById('ep-header-constructor');

	function mostrarConstructor() {
		if (!panelConstructor) return;
		panelFormulario.classList.add('hidden');
		panelConstructor.classList.remove('hidden');
		if (headerFormulario) headerFormulario.classList.add('hidden');
		if (headerConstructor) headerConstructor.classList.remove('hidden');
		actualizarVistaPrevia();
	}
	function mostrarFormulario() {
		if (!panelConstructor) return;
		panelConstructor.classList.add('hidden');
		panelFormulario.classList.remove('hidden');
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
				fila.innerHTML = '<span style="font-weight:600;">' + campo + '</span><span style="color:var(--color-text-muted);">Campo de esta lógica</span>';
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

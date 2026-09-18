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
		document.querySelectorAll('.ep-formulario-actividad, .ep-estadisticas-actividad, .ep-evidencia-actividad').forEach(function (el) {
			el.classList.toggle('hidden', el.dataset.actividadId !== id);
		});
		var estadisticaVisible = document.querySelector('.ep-estadisticas-actividad[data-actividad-id="' + id + '"]');
		var layout = document.getElementById('ep-actividad-layout');
		if (layout && estadisticaVisible) {
			layout.classList.toggle('ep-actividad-layout-sin-stats', estadisticaVisible.dataset.sinEstadisticas === '1');
		}
	}

	// Evidencia fotográfica: genérico para cualquier actividad, reacciona a cualquier .ep-foto-input sin wiring por actividad.
	document.addEventListener('change', function (ev) {
		if (!ev.target.classList.contains('ep-foto-input')) return;
		var input = ev.target;
		var archivo = input.files && input.files[0];
		if (!archivo) return;
		var slot = input.closest('.ep-foto-slot');
		var preview = slot.querySelector('.ep-foto-preview');
		var vacio = slot.querySelector('.ep-foto-dropzone-vacio');
		var estado = slot.querySelector('.ep-foto-slot-estado');
		preview.src = URL.createObjectURL(archivo);
		preview.classList.remove('hidden');
		vacio.classList.add('hidden');
		slot.classList.add('ep-foto-slot-completa');
		estado.textContent = 'Cargada';
		estado.classList.add('ep-hist-badge-ok');

		var bloque = slot.closest('.ep-evidencia-bloque');
		var contador = bloque ? bloque.querySelector('.ep-evidencia-contador') : null;
		if (contador) contador.textContent = bloque.querySelectorAll('.ep-foto-slot-completa').length;
	});

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

	// Coberturadas se tipea a mano, igual que los demás — solo no puede superar a Nacional (pedido explícito 2026-09-17).
	if (actNacional && actCoberturadas) aplicarTope(actNacional, actCoberturadas);
	if (actVisitaron && actInteractuaron) aplicarTope(actVisitaron, actInteractuaron);
	if (actInteractuaron && actCompraron) aplicarTope(actInteractuaron, actCompraron);
	if (actProgramadas && actRealizadas) aplicarTope(actProgramadas, actRealizadas);

	// Cualquier campo de Activaciones cambia algo del panel de estadísticas de al lado.
	[actNacional, actCoberturadas, actVisitaron, actInteractuaron, actCompraron, actProgramadas, actRealizadas].forEach(function (input) {
		if (input) input.addEventListener('input', actualizarEstadisticasActivaciones);
	});
	var actComentarios = document.getElementById('ep-act-comentarios');
	if (actComentarios) actComentarios.addEventListener('input', actualizarEstadisticasActivaciones);

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
	function actualizarEstadisticasActivaciones() {
		var statCoberturaPct = document.getElementById('ep-stat-cobertura-pct');
		if (!statCoberturaPct) return; // esta actividad no tiene panel de estadísticas todavía

		var nacional = actNacional ? actNacional.value : 0;
		var coberturadas = actCoberturadas ? actCoberturadas.value : 0;
		var visitaron = actVisitaron ? actVisitaron.value : 0;
		var interactuaron = actInteractuaron ? actInteractuaron.value : 0;
		var compraron = actCompraron ? actCompraron.value : 0;
		var programadas = actProgramadas ? actProgramadas.value : 0;
		var ejecutadas = actRealizadas ? actRealizadas.value : 0;

		statCoberturaPct.textContent = pctTexto(coberturadas, nacional);
		document.getElementById('ep-stat-nacional').textContent = nacional || 0;
		document.getElementById('ep-stat-coberturadas').textContent = coberturadas || 0;

		document.getElementById('ep-stat-interaccion-pct').textContent = pctTexto(interactuaron, visitaron);
		document.getElementById('ep-stat-visitaron').textContent = visitaron || 0;
		document.getElementById('ep-stat-interactuaron').textContent = interactuaron || 0;

		document.getElementById('ep-stat-ventas-pct').textContent = pctTexto(compraron, interactuaron);
		document.getElementById('ep-stat-ventas-realizadas').textContent = compraron || 0;

		document.getElementById('ep-stat-cumplimiento-pct').textContent = pctTexto(ejecutadas, programadas);
		document.getElementById('ep-stat-programadas').textContent = programadas || 0;
		document.getElementById('ep-stat-ejecutadas').textContent = ejecutadas || 0;

		var maxEmbudo = Math.max(parseFloat(visitaron) || 0, 1);
		document.getElementById('ep-stat-bar-visitaron-valor').textContent = visitaron || 0;
		document.getElementById('ep-stat-bar-visitaron').style.height = pctTexto(visitaron, maxEmbudo);
		document.getElementById('ep-stat-bar-interactuaron-valor').textContent = interactuaron || 0;
		document.getElementById('ep-stat-bar-interactuaron').style.height = pctTexto(interactuaron, maxEmbudo);
		document.getElementById('ep-stat-bar-compraron-valor').textContent = compraron || 0;
		document.getElementById('ep-stat-bar-compraron').style.height = pctTexto(compraron, maxEmbudo);

		var detalleVentas = document.getElementById('ep-stat-detalle-ventas');
		var modelos = (actModelos ? actModelos.modelos() : []).sort(function (a, b) { return b.cantidad - a.cantidad; });
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

	var actModelos = crearGestorModelos('ep-modelo-filas', 'ep-modelo-agregar', 'ep-modelo-total-valor', actualizarEstadisticasActivaciones);
	actualizarEstadisticasActivaciones(); // primer cálculo, con los valores de ejemplo que ya trae el formulario

	// ---------- Capacitaciones ---------- Interacciones no puede superar el total de asistentes.
	var capAsistJefe = document.getElementById('ep-cap-asist-jefe');
	var capJefeTienda = document.getElementById('ep-cap-jefe-tienda');
	var capVendedores = document.getElementById('ep-cap-vendedores');
	var capInteracciones = document.getElementById('ep-cap-interacciones');
	var capComentarios = document.getElementById('ep-cap-comentarios');

	function actualizarTotalAsistentesCapacitaciones() {
		var total = (parseFloat(capAsistJefe && capAsistJefe.value) || 0)
			+ (parseFloat(capJefeTienda && capJefeTienda.value) || 0)
			+ (parseFloat(capVendedores && capVendedores.value) || 0);
		var total1 = document.getElementById('ep-cap-total-asistentes-1');
		var total2 = document.getElementById('ep-cap-total-asistentes-2');
		if (total1) total1.textContent = total;
		if (total2) total2.textContent = total;

		if (capInteracciones && (parseFloat(capInteracciones.value) || 0) > total) {
			capInteracciones.value = total;
			destacarTope(capInteracciones);
		}
		actualizarEstadisticasCapacitaciones();
	}

	function actualizarEstadisticasCapacitaciones() {
		var statAsistentes = document.getElementById('ep-cap-stat-asistentes');
		if (!statAsistentes) return; // esta actividad no tiene panel de estadísticas todavía

		var asistJefe = capAsistJefe ? (parseFloat(capAsistJefe.value) || 0) : 0;
		var jefeTienda = capJefeTienda ? (parseFloat(capJefeTienda.value) || 0) : 0;
		var vendedores = capVendedores ? (parseFloat(capVendedores.value) || 0) : 0;
		var total = asistJefe + jefeTienda + vendedores;
		var interacciones = capInteracciones ? (parseFloat(capInteracciones.value) || 0) : 0;

		statAsistentes.textContent = total;
		document.getElementById('ep-cap-stat-asist-jefe').textContent = asistJefe;
		document.getElementById('ep-cap-stat-jefe-tienda').textContent = jefeTienda;
		document.getElementById('ep-cap-stat-vendedores').textContent = vendedores;
		document.getElementById('ep-cap-stat-interacciones').textContent = interacciones;
		document.getElementById('ep-cap-stat-interaccion-pct').textContent = pctTexto(interacciones, total);

		var maxBarraCap = Math.max(total, 1);
		document.getElementById('ep-cap-stat-bar-asistentes-valor').textContent = total;
		document.getElementById('ep-cap-stat-bar-asistentes').style.height = pctTexto(total, maxBarraCap);
		document.getElementById('ep-cap-stat-bar-interacciones-valor').textContent = interacciones;
		document.getElementById('ep-cap-stat-bar-interacciones').style.height = pctTexto(interacciones, maxBarraCap);

		var detalleCargos = document.getElementById('ep-cap-stat-detalle-cargos');
		var cargos = [
			{ nombre: 'Vendedores', cantidad: vendedores },
			{ nombre: 'Jefe de Tienda', cantidad: jefeTienda },
			{ nombre: 'Asist. de Jefe Tienda', cantidad: asistJefe },
		].filter(function (c) { return c.cantidad > 0; }).sort(function (a, b) { return b.cantidad - a.cantidad; });

		if (!cargos.length) {
			detalleCargos.innerHTML = '<span class="ep-stat-comentarios-vacio">Todavía no cargaste asistentes.</span>';
		} else {
			var maxCargo = cargos[0].cantidad;
			detalleCargos.innerHTML = cargos.map(function (c) {
				return '<div class="ep-venta-fila">'
					+ '<span class="ep-venta-nombre">' + c.nombre + '</span>'
					+ '<div class="ep-venta-barra-track"><div class="ep-venta-barra-fill" style="width:' + Math.round(c.cantidad / maxCargo * 100) + '%;"></div></div>'
					+ '<span class="ep-venta-valor">' + c.cantidad + '</span>'
					+ '</div>';
			}).join('');
		}

		var comentariosBoxCap = document.getElementById('ep-cap-stat-comentarios');
		var lineasCap = capComentarios ? capComentarios.value.split('\n').map(function (l) { return l.trim(); }).filter(Boolean) : [];
		comentariosBoxCap.innerHTML = lineasCap.length
			? lineasCap.map(function (l) { return '<div>' + escapeHtml(l) + '</div>'; }).join('')
			: '<span class="ep-stat-comentarios-vacio">Sin comentarios todavía.</span>';
	}

	[capAsistJefe, capJefeTienda, capVendedores].forEach(function (input) {
		if (input) input.addEventListener('input', actualizarTotalAsistentesCapacitaciones);
	});
	if (capInteracciones) capInteracciones.addEventListener('input', actualizarEstadisticasCapacitaciones);
	if (capComentarios) capComentarios.addEventListener('input', actualizarEstadisticasCapacitaciones);
	actualizarEstadisticasCapacitaciones(); // primer cálculo

	// ---------- Exhibiciones que Inspiran ---------- solo detalle (barras) + comentarios, sin porcentajes ni topes.
	var exhMuebles = document.getElementById('ep-exh-muebles');
	var exhRumas = document.getElementById('ep-exh-rumas');
	var exhCabeceras = document.getElementById('ep-exh-cabeceras');
	var exhComentarios = document.getElementById('ep-exh-comentarios');

	function actualizarEstadisticasExhibiciones() {
		var statDetalle = document.getElementById('ep-exh-stat-detalle');
		if (!statDetalle) return; // esta actividad no tiene panel de estadísticas todavía

		var muebles = exhMuebles ? (parseFloat(exhMuebles.value) || 0) : 0;
		var rumas = exhRumas ? (parseFloat(exhRumas.value) || 0) : 0;
		var cabeceras = exhCabeceras ? (parseFloat(exhCabeceras.value) || 0) : 0;
		var total = muebles + rumas + cabeceras;

		var totalSpan = document.getElementById('ep-exh-total');
		if (totalSpan) totalSpan.textContent = total;

		var items = [
			{ nombre: 'Cabeceras', cantidad: cabeceras },
			{ nombre: 'Rumas', cantidad: rumas },
			{ nombre: 'Muebles', cantidad: muebles },
		].filter(function (i) { return i.cantidad > 0; }).sort(function (a, b) { return b.cantidad - a.cantidad; });

		if (!items.length) {
			statDetalle.innerHTML = '<span class="ep-stat-comentarios-vacio">Todavía no cargaste exhibiciones.</span>';
		} else {
			var maxItem = items[0].cantidad;
			statDetalle.innerHTML = items.map(function (i) {
				return '<div class="ep-venta-fila">'
					+ '<span class="ep-venta-nombre">' + i.nombre + '</span>'
					+ '<div class="ep-venta-barra-track"><div class="ep-venta-barra-fill" style="width:' + Math.round(i.cantidad / maxItem * 100) + '%;"></div></div>'
					+ '<span class="ep-venta-valor">' + i.cantidad + '</span>'
					+ '</div>';
			}).join('');
		}

		var comentariosBoxExh = document.getElementById('ep-exh-stat-comentarios');
		var lineasExh = exhComentarios ? exhComentarios.value.split('\n').map(function (l) { return l.trim(); }).filter(Boolean) : [];
		comentariosBoxExh.innerHTML = lineasExh.length
			? lineasExh.map(function (l) { return '<div>' + escapeHtml(l) + '</div>'; }).join('')
			: '<span class="ep-stat-comentarios-vacio">Sin comentarios todavía.</span>';
	}

	[exhMuebles, exhRumas, exhCabeceras].forEach(function (input) {
		if (input) input.addEventListener('input', actualizarEstadisticasExhibiciones);
	});
	if (exhComentarios) exhComentarios.addEventListener('input', actualizarEstadisticasExhibiciones);
	actualizarEstadisticasExhibiciones(); // primer cálculo

	// Fábrica de combo+cantidad de modelos: busca en vivo contra repositorio_productos (getters/repositorio_productos_buscar.php).
	function crearGestorModelos(idFilas, idAgregar, idTotal, onCambio) {
		var filas = document.getElementById(idFilas);
		if (!filas) return null;
		var agregarBtn = document.getElementById(idAgregar);
		var totalValor = document.getElementById(idTotal);
		var buscarReqId = 0;
		var buscarDebounce = null;

		function filaHTML() {
			return '<div class="ep-modelo-fila-nueva"><div class="ep-combo">'
				+ '<button type="button" class="ep-input ep-combo-trigger" data-valor="">'
				+ '<span class="ep-combo-trigger-texto">Elegir modelo...</span>' + epIconMarkup('chevron', 14) + '</button>'
				+ '<div class="ep-combo-panel hidden"><input type="text" class="ep-input ep-combo-buscador" placeholder="Buscar modelo..." autocomplete="off">'
				+ '<div class="ep-combo-opciones"></div></div></div>'
				+ '<input type="number" min="0" class="ep-input ep-modelo-cantidad" placeholder="Cantidad">'
				+ '<button type="button" class="ep-modelo-quitar" aria-label="Quitar modelo">' + epIconMarkup('trash', 14) + '</button></div>';
		}
		function elegidosEnOtrasFilas(comboActual) {
			var out = [];
			filas.querySelectorAll('.ep-combo').forEach(function (c) {
				if (c === comboActual) return;
				var v = c.querySelector('.ep-combo-trigger').dataset.valor;
				if (v) out.push(v);
			});
			return out;
		}
		function buscarModelos(combo, texto) {
			var opciones = combo.querySelector('.ep-combo-opciones');
			opciones.innerHTML = '<div class="ep-combo-vacio">Buscando...</div>';
			var miReqId = ++buscarReqId;
			fetch('getters/repositorio_productos_buscar.php?q=' + encodeURIComponent(texto || ''))
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (miReqId !== buscarReqId) return; // llegó una búsqueda más nueva antes que esta
					var ya = elegidosEnOtrasFilas(combo);
					var coincidencias = (data.ok ? data.productos : []).filter(function (m) { return ya.indexOf(m) === -1; });
					opciones.innerHTML = coincidencias.length
						? coincidencias.map(function (m) { return '<button type="button" class="ep-combo-opcion" data-valor="' + m + '">' + m + '</button>'; }).join('')
						: '<div class="ep-combo-vacio">Sin resultados</div>';
				})
				.catch(function () {
					if (miReqId !== buscarReqId) return;
					opciones.innerHTML = '<div class="ep-combo-vacio">Error al buscar, intenta de nuevo</div>';
				});
		}
		function filtrarCombo(combo, texto) {
			clearTimeout(buscarDebounce);
			buscarDebounce = setTimeout(function () { buscarModelos(combo, texto); }, 250);
		}
		function cerrarPaneles() { filas.querySelectorAll('.ep-combo-panel').forEach(function (p) { p.classList.add('hidden'); }); }
		function abrirPanel(combo) {
			cerrarPaneles();
			var buscador = combo.querySelector('.ep-combo-buscador');
			buscador.value = '';
			buscarModelos(combo, '');
			combo.querySelector('.ep-combo-panel').classList.remove('hidden');
			buscador.focus();
		}
		function actualizarTotal() {
			var total = 0;
			filas.querySelectorAll('.ep-modelo-cantidad').forEach(function (i) { total += parseFloat(i.value) || 0; });
			if (totalValor) totalValor.textContent = total;
			if (onCambio) onCambio();
		}
		function agregarFila() { filas.insertAdjacentHTML('beforeend', filaHTML()); }

		agregarFila(); // arranca con una fila vacía, igual que Activaciones
		filas.addEventListener('input', function (ev) {
			if (ev.target.classList.contains('ep-combo-buscador')) filtrarCombo(ev.target.closest('.ep-combo'), ev.target.value);
			if (ev.target.classList.contains('ep-modelo-cantidad')) actualizarTotal();
		});
		filas.addEventListener('click', function (ev) {
			var trigger = ev.target.closest('.ep-combo-trigger');
			if (trigger) {
				var combo = trigger.closest('.ep-combo');
				var abierto = !combo.querySelector('.ep-combo-panel').classList.contains('hidden');
				cerrarPaneles();
				if (!abierto) abrirPanel(combo);
				return;
			}
			var opcion = ev.target.closest('.ep-combo-opcion');
			if (opcion) {
				var comboElegido = opcion.closest('.ep-combo');
				var triggerElegido = comboElegido.querySelector('.ep-combo-trigger');
				triggerElegido.dataset.valor = opcion.dataset.valor;
				triggerElegido.querySelector('.ep-combo-trigger-texto').textContent = opcion.dataset.valor;
				comboElegido.querySelector('.ep-combo-panel').classList.add('hidden');
				if (onCambio) onCambio();
				return;
			}
			var quitar = ev.target.closest('.ep-modelo-quitar');
			if (quitar) { quitar.closest('.ep-modelo-fila-nueva').remove(); actualizarTotal(); }
		});
		if (agregarBtn) agregarBtn.addEventListener('click', agregarFila);
		document.addEventListener('click', function (ev) { if (!ev.target.closest('.ep-combo')) cerrarPaneles(); });

		return { modelos: function () {
			var out = [];
			filas.querySelectorAll('.ep-modelo-fila-nueva').forEach(function (fila) {
				var nombre = fila.querySelector('.ep-combo-trigger').dataset.valor;
				var cantidad = parseFloat(fila.querySelector('.ep-modelo-cantidad').value) || 0;
				if (nombre && cantidad > 0) out.push({ nombre: nombre, cantidad: cantidad });
			});
			return out;
		} };
	}

	// ---------- Epson Day ---------- igual que Activaciones pero sin Cumplimiento (no está en su Excel).
	var edayNacional = document.getElementById('ep-eday-nacional');
	var edayCoberturadas = document.getElementById('ep-eday-coberturadas');
	var edayVisitaron = document.getElementById('ep-eday-visitaron');
	var edayInteractuaron = document.getElementById('ep-eday-interactuaron');
	var edayCompraron = document.getElementById('ep-eday-compraron');
	var edayComentarios = document.getElementById('ep-eday-comentarios');
	if (edayNacional && edayCoberturadas) aplicarTope(edayNacional, edayCoberturadas);
	if (edayVisitaron && edayInteractuaron) aplicarTope(edayVisitaron, edayInteractuaron);
	if (edayInteractuaron && edayCompraron) aplicarTope(edayInteractuaron, edayCompraron);

	var edayModelos = crearGestorModelos('ep-eday-modelo-filas', 'ep-eday-modelo-agregar', 'ep-eday-modelo-total-valor', function () { actualizarEstadisticasEpsonDay(); });

	function actualizarEstadisticasEpsonDay() {
		var statPct = document.getElementById('ep-eday-stat-cobertura-pct');
		if (!statPct) return;

		var nacional = edayNacional ? edayNacional.value : 0;
		var coberturadas = edayCoberturadas ? edayCoberturadas.value : 0;
		var visitaron = edayVisitaron ? edayVisitaron.value : 0;
		var interactuaron = edayInteractuaron ? edayInteractuaron.value : 0;
		var compraron = edayCompraron ? edayCompraron.value : 0;

		statPct.textContent = pctTexto(coberturadas, nacional);
		document.getElementById('ep-eday-stat-nacional').textContent = nacional || 0;
		document.getElementById('ep-eday-stat-coberturadas').textContent = coberturadas || 0;
		document.getElementById('ep-eday-stat-interaccion-pct').textContent = pctTexto(interactuaron, visitaron);
		document.getElementById('ep-eday-stat-visitaron').textContent = visitaron || 0;
		document.getElementById('ep-eday-stat-interactuaron').textContent = interactuaron || 0;
		document.getElementById('ep-eday-stat-ventas-pct').textContent = pctTexto(compraron, interactuaron);
		document.getElementById('ep-eday-stat-compraron').textContent = compraron || 0;

		var maxEmbudo = Math.max(parseFloat(visitaron) || 0, 1);
		document.getElementById('ep-eday-stat-bar-visitaron-valor').textContent = visitaron || 0;
		document.getElementById('ep-eday-stat-bar-visitaron').style.height = pctTexto(visitaron, maxEmbudo);
		document.getElementById('ep-eday-stat-bar-interactuaron-valor').textContent = interactuaron || 0;
		document.getElementById('ep-eday-stat-bar-interactuaron').style.height = pctTexto(interactuaron, maxEmbudo);
		document.getElementById('ep-eday-stat-bar-compraron-valor').textContent = compraron || 0;
		document.getElementById('ep-eday-stat-bar-compraron').style.height = pctTexto(compraron, maxEmbudo);

		var detalleVentas = document.getElementById('ep-eday-stat-detalle-ventas');
		var modelos = (edayModelos ? edayModelos.modelos() : []).sort(function (a, b) { return b.cantidad - a.cantidad; });
		var totalUnidades = modelos.reduce(function (s, m) { return s + m.cantidad; }, 0);

		detalleVentas.innerHTML = !modelos.length
			? '<span class="ep-stat-comentarios-vacio">Todavía no cargaste modelos.</span>'
			: modelos.map(function (m) {
				return '<div class="ep-venta-fila"><span class="ep-venta-nombre">' + escapeHtml(m.nombre) + '</span>'
					+ '<div class="ep-venta-barra-track"><div class="ep-venta-barra-fill" style="width:' + Math.round(m.cantidad / modelos[0].cantidad * 100) + '%;"></div></div>'
					+ '<span class="ep-venta-valor">' + m.cantidad + '</span></div>';
			}).join('');

		var mayorPct = document.getElementById('ep-eday-stat-mayor-pct');
		var mayorNombre = document.getElementById('ep-eday-stat-mayor-nombre');
		var menorPct = document.getElementById('ep-eday-stat-menor-pct');
		var menorNombre = document.getElementById('ep-eday-stat-menor-nombre');
		if (modelos.length) {
			var mayorCant = modelos[0].cantidad;
			var menorCant = modelos[modelos.length - 1].cantidad;
			mayorPct.textContent = pctTexto(mayorCant, totalUnidades);
			mayorNombre.textContent = modelos.filter(function (m) { return m.cantidad === mayorCant; }).map(function (m) { return m.nombre; }).join(' / ');
			menorPct.textContent = pctTexto(menorCant, totalUnidades);
			menorNombre.textContent = modelos.filter(function (m) { return m.cantidad === menorCant; }).map(function (m) { return m.nombre; }).join(' / ');
		} else {
			mayorPct.textContent = '0%'; mayorNombre.textContent = 'Sin datos';
			menorPct.textContent = '0%'; menorNombre.textContent = 'Sin datos';
		}

		var comentariosBoxEday = document.getElementById('ep-eday-stat-comentarios');
		var lineasEday = edayComentarios ? edayComentarios.value.split('\n').map(function (l) { return l.trim(); }).filter(Boolean) : [];
		comentariosBoxEday.innerHTML = lineasEday.length
			? lineasEday.map(function (l) { return '<div>' + escapeHtml(l) + '</div>'; }).join('')
			: '<span class="ep-stat-comentarios-vacio">Sin comentarios todavía.</span>';
	}

	[edayNacional, edayCoberturadas, edayVisitaron, edayInteractuaron, edayCompraron].forEach(function (input) {
		if (input) input.addEventListener('input', actualizarEstadisticasEpsonDay);
	});
	if (edayComentarios) edayComentarios.addEventListener('input', actualizarEstadisticasEpsonDay);
	actualizarEstadisticasEpsonDay(); // primer cálculo

	// ---------- Evento o Ferias ---------- igual que Epson Day pero sin Cobertura (no está en su Excel).
	var eventoVisitaron = document.getElementById('ep-evento-visitaron');
	var eventoInteractuaron = document.getElementById('ep-evento-interactuaron');
	var eventoCompraron = document.getElementById('ep-evento-compraron');
	var eventoComentarios = document.getElementById('ep-evento-comentarios');
	if (eventoVisitaron && eventoInteractuaron) aplicarTope(eventoVisitaron, eventoInteractuaron);
	if (eventoInteractuaron && eventoCompraron) aplicarTope(eventoInteractuaron, eventoCompraron);

	var eventoModelos = crearGestorModelos('ep-evento-modelo-filas', 'ep-evento-modelo-agregar', 'ep-evento-modelo-total-valor', function () { actualizarEstadisticasEvento(); });

	function actualizarEstadisticasEvento() {
		var statPct = document.getElementById('ep-evento-stat-interaccion-pct');
		if (!statPct) return;

		var visitaron = eventoVisitaron ? eventoVisitaron.value : 0;
		var interactuaron = eventoInteractuaron ? eventoInteractuaron.value : 0;
		var compraron = eventoCompraron ? eventoCompraron.value : 0;

		statPct.textContent = pctTexto(interactuaron, visitaron);
		document.getElementById('ep-evento-stat-visitaron').textContent = visitaron || 0;
		document.getElementById('ep-evento-stat-interactuaron').textContent = interactuaron || 0;
		document.getElementById('ep-evento-stat-ventas-pct').textContent = pctTexto(compraron, interactuaron);
		document.getElementById('ep-evento-stat-compraron').textContent = compraron || 0;

		var maxEmbudo = Math.max(parseFloat(visitaron) || 0, 1);
		document.getElementById('ep-evento-stat-bar-visitaron-valor').textContent = visitaron || 0;
		document.getElementById('ep-evento-stat-bar-visitaron').style.height = pctTexto(visitaron, maxEmbudo);
		document.getElementById('ep-evento-stat-bar-interactuaron-valor').textContent = interactuaron || 0;
		document.getElementById('ep-evento-stat-bar-interactuaron').style.height = pctTexto(interactuaron, maxEmbudo);
		document.getElementById('ep-evento-stat-bar-compraron-valor').textContent = compraron || 0;
		document.getElementById('ep-evento-stat-bar-compraron').style.height = pctTexto(compraron, maxEmbudo);

		var detalleVentas = document.getElementById('ep-evento-stat-detalle-ventas');
		var modelos = (eventoModelos ? eventoModelos.modelos() : []).sort(function (a, b) { return b.cantidad - a.cantidad; });
		var totalUnidades = modelos.reduce(function (s, m) { return s + m.cantidad; }, 0);

		detalleVentas.innerHTML = !modelos.length
			? '<span class="ep-stat-comentarios-vacio">Todavía no cargaste modelos.</span>'
			: modelos.map(function (m) {
				return '<div class="ep-venta-fila"><span class="ep-venta-nombre">' + escapeHtml(m.nombre) + '</span>'
					+ '<div class="ep-venta-barra-track"><div class="ep-venta-barra-fill" style="width:' + Math.round(m.cantidad / modelos[0].cantidad * 100) + '%;"></div></div>'
					+ '<span class="ep-venta-valor">' + m.cantidad + '</span></div>';
			}).join('');

		var mayorPct = document.getElementById('ep-evento-stat-mayor-pct');
		var mayorNombre = document.getElementById('ep-evento-stat-mayor-nombre');
		var menorPct = document.getElementById('ep-evento-stat-menor-pct');
		var menorNombre = document.getElementById('ep-evento-stat-menor-nombre');
		if (modelos.length) {
			var mayorCant = modelos[0].cantidad;
			var menorCant = modelos[modelos.length - 1].cantidad;
			mayorPct.textContent = pctTexto(mayorCant, totalUnidades);
			mayorNombre.textContent = modelos.filter(function (m) { return m.cantidad === mayorCant; }).map(function (m) { return m.nombre; }).join(' / ');
			menorPct.textContent = pctTexto(menorCant, totalUnidades);
			menorNombre.textContent = modelos.filter(function (m) { return m.cantidad === menorCant; }).map(function (m) { return m.nombre; }).join(' / ');
		} else {
			mayorPct.textContent = '0%'; mayorNombre.textContent = 'Sin datos';
			menorPct.textContent = '0%'; menorNombre.textContent = 'Sin datos';
		}

		var comentariosBoxEvento = document.getElementById('ep-evento-stat-comentarios');
		var lineasEvento = eventoComentarios ? eventoComentarios.value.split('\n').map(function (l) { return l.trim(); }).filter(Boolean) : [];
		comentariosBoxEvento.innerHTML = lineasEvento.length
			? lineasEvento.map(function (l) { return '<div>' + escapeHtml(l) + '</div>'; }).join('')
			: '<span class="ep-stat-comentarios-vacio">Sin comentarios todavía.</span>';
	}

	[eventoVisitaron, eventoInteractuaron, eventoCompraron].forEach(function (input) {
		if (input) input.addEventListener('input', actualizarEstadisticasEvento);
	});
	if (eventoComentarios) eventoComentarios.addEventListener('input', actualizarEstadisticasEvento);
	actualizarEstadisticasEvento(); // primer cálculo

	// ---------- Colocación de POP ---------- sin panel de estadísticas, pedido explícito (esta actividad solo tiene formulario).
	var epPopMateriales = ['vibrin', 'hablador', 'rompe-trafico', 'bases', 'displays', 'cenefas'];
	var epPopEntregas = {}; // key -> [{ pdv, ciudad, cantidad }]

	function epPopFilaEntregaHTML() {
		return '<div class="ep-pop-entrega-fila">'
			+ '<input type="text" class="ep-input ep-pop-entrega-pdv" placeholder="PDV">'
			+ '<input type="text" class="ep-input ep-pop-entrega-ciudad" placeholder="Ciudad">'
			+ '<input type="number" min="0" class="ep-input ep-pop-entrega-cantidad" placeholder="Cant.">'
			+ '<button type="button" class="ep-modelo-quitar" aria-label="Quitar entrega">' + epIconMarkup('trash', 14) + '</button></div>';
	}

	function epPopLeerDisponible(key) {
		var bodega = parseFloat((document.getElementById('ep-pop-bodega-' + key) || {}).value) || 0;
		var canales = parseFloat((document.getElementById('ep-pop-canales-' + key) || {}).value) || 0;
		var retail = parseFloat((document.getElementById('ep-pop-retail-' + key) || {}).value) || 0;
		return { bodega: bodega, canales: canales, retail: retail, disponible: bodega - canales - retail };
	}

	function epPopActualizarFilaMaterial(key) {
		var d = epPopLeerDisponible(key);
		var span = document.getElementById('ep-pop-disponible-' + key);
		if (span) span.textContent = d.disponible;
		epPopActualizarTarjetaMaterial(key);
	}

	function epPopActualizarTarjetaMaterial(key) {
		var retail = epPopLeerDisponible(key).retail;
		var filas = epPopEntregas[key] || [];
		var entregado = filas.reduce(function (s, f) { return s + f.cantidad; }, 0);
		var headerRetail = document.getElementById('ep-pop-header-retail-' + key);
		var headerEntregado = document.getElementById('ep-pop-entregado-' + key);
		if (headerRetail) headerRetail.textContent = retail;
		if (headerEntregado) headerEntregado.textContent = entregado;

		var badge = document.getElementById('ep-pop-badge-' + key);
		if (badge) {
			badge.className = 'ep-hist-badge';
			if (retail <= 0 && entregado <= 0) { badge.textContent = 'Sin registrar'; }
			else if (entregado === retail) { badge.className += ' ep-hist-badge-ok'; badge.textContent = 'Completo'; }
			else if (entregado > retail) { badge.className += ' ep-hist-badge-danger'; badge.textContent = 'Excede el Retail'; }
			else { badge.className += ' ep-hist-badge-pendiente'; badge.textContent = 'Pendiente'; }
		}
	}

	function epPopCrearGestorEntregas(key) {
		var filasEl = document.getElementById('ep-pop-entregas-' + key);
		var agregarBtn = document.getElementById('ep-pop-entregas-agregar-' + key);
		if (!filasEl) return;
		epPopEntregas[key] = [];

		function leerFilas() {
			epPopEntregas[key] = [];
			filasEl.querySelectorAll('.ep-pop-entrega-fila').forEach(function (fila) {
				var pdv = fila.querySelector('.ep-pop-entrega-pdv').value.trim();
				var ciudad = fila.querySelector('.ep-pop-entrega-ciudad').value.trim();
				var cantidad = parseFloat(fila.querySelector('.ep-pop-entrega-cantidad').value) || 0;
				if (pdv && cantidad > 0) epPopEntregas[key].push({ pdv: pdv, ciudad: ciudad, cantidad: cantidad });
			});
			epPopActualizarTarjetaMaterial(key);
		}

		filasEl.addEventListener('input', leerFilas);
		filasEl.addEventListener('click', function (ev) {
			var quitar = ev.target.closest('.ep-modelo-quitar');
			if (quitar) { quitar.closest('.ep-pop-entrega-fila').remove(); leerFilas(); }
		});
		if (agregarBtn) agregarBtn.addEventListener('click', function () { filasEl.insertAdjacentHTML('beforeend', epPopFilaEntregaHTML()); });
	}

	epPopMateriales.forEach(function (key) {
		['ep-pop-bodega-', 'ep-pop-canales-', 'ep-pop-retail-'].forEach(function (prefijo) {
			var input = document.getElementById(prefijo + key);
			if (input) input.addEventListener('input', function () { epPopActualizarFilaMaterial(key); });
		});
		epPopCrearGestorEntregas(key);
		epPopActualizarFilaMaterial(key);
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

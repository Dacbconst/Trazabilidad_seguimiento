(function () {
	var vistaLista = document.getElementById('ac-liquidacion-lista');
	var vistaPendientes = document.getElementById('ac-liquidacion-pendientes');
	var vistaResumen = document.getElementById('ac-liquidacion-resumen');
	var tablaBody = document.getElementById('liq-tabla-body');
	var pendientesBody = document.getElementById('liq-pendientes-body');
	var resumenBody = document.getElementById('liq-resumen-body');
	var resumenSubtitulo = document.getElementById('liq-resumen-subtitulo');
	var resumenExportar = document.getElementById('liq-resumen-exportar');
	var resumenStats = document.getElementById('liq-resumen-stats');
	var resumenChart = document.getElementById('liq-resumen-chart');
	var resumenFiltroCedi = document.getElementById('liq-resumen-filtro-cedi');
	var resumenFiltroCediLabel = document.getElementById('liq-resumen-filtro-cedi-label');
	var resumenFiltroEstado = document.getElementById('liq-resumen-filtro-estado');
	// Colores fijos de la serie (validados con la skill de dataviz — par
	// categórico #2a78d6/#eb6834, ver referencia de la skill): Volumen y
	// Visibilidad SIEMPRE con estos colores, en este orden, en todo el chart.
	var COLOR_VOLUMEN = '#2a78d6';
	var COLOR_VISIBILIDAD = '#eb6834';
	var resumenDatos = []; // cache del último fetch, para filtrar en el cliente sin volver a pedir al servidor.

	function escapeHtml(texto) {
		var div = document.createElement('div');
		div.textContent = texto == null ? '' : String(texto);
		return div.innerHTML;
	}

	var mesesCorto = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
	var etiquetaEstado = { procesando: 'Procesando', completado: 'Completado', con_errores: 'Con errores' };
	var etiquetaCanal = { directa: 'Directa', distribuidor: 'Distribuidor' };

	// Mismo período detectado del archivo (no elegido a mano) — un solo mes
	// se muestra una vez, un rango se muestra "Abr - Jun".
	function periodoTexto(mesInicio, mesFin) {
		mesInicio = parseInt(mesInicio, 10); mesFin = parseInt(mesFin, 10);
		if (mesInicio === mesFin) return mesesCorto[mesInicio];
		return mesesCorto[mesInicio] + ' - ' + mesesCorto[mesFin];
	}

	function formatoMoneda(valor) {
		var n = parseFloat(valor) || 0;
		return '$' + n.toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	// ---------- Listado de importaciones ----------
	function cargarImportaciones() {
		tablaBody.innerHTML = '<tr><td colspan="7" class="ac-table-empty">Cargando...</td></tr>';
		fetch('getters/listar_liquidacion_importaciones.php')
			.then(function (r) { return r.json(); })
			.then(function (data) {
				var filas = (data.ok && data.importaciones) ? data.importaciones : [];
				if (!filas.length) {
					tablaBody.innerHTML = '<tr><td colspan="7" class="ac-table-empty">Todavía no se subió ningún Excel.</td></tr>';
					return;
				}
				tablaBody.innerHTML = filas.map(function (f) {
					var pendientes = parseInt(f.filas_pendientes, 10) || 0;
					var accionPendientes = pendientes > 0
						? '<button type="button" class="ac-link-id liq-btn-pendientes" data-id="' + f.id + '">Resolver (' + pendientes + ')</button>'
						: '<span class="ac-field-hint">Sin pendientes</span>';
					var accionResumen = '<button type="button" class="ac-link-id liq-btn-resumen" data-id="' + f.id + '">Resumen de Pagos</button>';
					return '<tr>' +
						'<td>' + (etiquetaCanal[f.canal] || escapeHtml(f.canal)) + '</td>' +
						'<td>' + periodoTexto(f.mes_inicio, f.mes_fin) + ' ' + f.anio + '</td>' +
						'<td>' + escapeHtml(f.nombre_archivo) + '</td>' +
						'<td class="ac-text-center"><span class="ac-badge ac-badge-desarrollador">' + (etiquetaEstado[f.estado] || escapeHtml(f.estado)) + '</span></td>' +
						'<td class="ac-text-right ac-tabular">' + f.total_filas + '</td>' +
						'<td class="ac-text-right ac-tabular">' + pendientes + '</td>' +
						'<td class="ac-text-right">' + accionPendientes + '<br>' + accionResumen + '</td>' +
						'</tr>';
				}).join('');
				Array.prototype.forEach.call(tablaBody.querySelectorAll('.liq-btn-pendientes'), function (btn) {
					btn.addEventListener('click', function () { abrirPendientes(parseInt(btn.dataset.id, 10)); });
				});
				Array.prototype.forEach.call(tablaBody.querySelectorAll('.liq-btn-resumen'), function (btn) {
					btn.addEventListener('click', function () { abrirResumen(parseInt(btn.dataset.id, 10)); });
				});
			})
			.catch(function () {
				tablaBody.innerHTML = '<tr><td colspan="7" class="ac-table-empty">Error al cargar las importaciones.</td></tr>';
			});
	}

	document.getElementById('liq-actualizar').addEventListener('click', cargarImportaciones);

	// ---------- Modal: Subir Excel ----------
	var subirModalOverlay = document.getElementById('liq-subir-modal-overlay');
	var formSubir = document.getElementById('liq-form-subir');
	var submitBtn = document.getElementById('liq-subir-submit');

	document.getElementById('liq-abrir-subir').addEventListener('click', function () {
		formSubir.reset();
		subirModalOverlay.classList.add('ac-modal-open');
	});
	document.getElementById('liq-subir-modal-close').addEventListener('click', function () {
		subirModalOverlay.classList.remove('ac-modal-open');
	});
	subirModalOverlay.addEventListener('click', function (e) {
		if (e.target === subirModalOverlay) subirModalOverlay.classList.remove('ac-modal-open');
	});

	formSubir.addEventListener('submit', function (e) {
		e.preventDefault();
		submitBtn.disabled = true;
		submitBtn.textContent = 'Procesando...';

		fetch('getters/importar_liquidacion.php', {
			method: 'POST',
			body: new FormData(formSubir),
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				submitBtn.disabled = false;
				submitBtn.textContent = 'Procesar Excel';
				mostrarToast(data.message, data.ok ? 'success' : 'error');
				if (data.ok) {
					subirModalOverlay.classList.remove('ac-modal-open');
					if (data.filas_pendientes > 0) {
						mostrarToast(data.filas_pendientes + ' filas quedaron pendientes de asignar a mano.', 'warning');
					}
					cargarImportaciones();
				}
			})
			.catch(function () {
				submitBtn.disabled = false;
				submitBtn.textContent = 'Procesar Excel';
				mostrarToast('Error de conexión al subir el archivo.', 'error');
			});
	});

	// ---------- Pendientes de Asignar ----------
	function abrirPendientes(importacionId) {
		vistaLista.classList.add('hidden');
		vistaPendientes.classList.remove('hidden');
		pendientesBody.innerHTML = '<tr><td colspan="5" class="ac-table-empty">Cargando...</td></tr>';
		window.scrollTo(0, 0);

		fetch('getters/liquidacion_pendientes.php?importacion_id=' + importacionId)
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) { mostrarToast(data.message || 'Error al cargar pendientes.', 'error'); return; }
				renderPendientes(data.pendientes);
			})
			.catch(function () {
				pendientesBody.innerHTML = '<tr><td colspan="5" class="ac-table-empty">Error al cargar pendientes.</td></tr>';
			});
	}

	document.getElementById('liq-volver-lista').addEventListener('click', function () {
		vistaPendientes.classList.add('hidden');
		vistaLista.classList.remove('hidden');
		cargarImportaciones();
	});

	function renderPendientes(filas) {
		if (!filas.length) {
			pendientesBody.innerHTML = '<tr><td colspan="5" class="ac-table-empty">No quedan filas pendientes — todo se resolvió.</td></tr>';
			return;
		}
		pendientesBody.innerHTML = filas.map(function (f) {
			var candidatosHtml = '';
			if (f.candidatos && f.candidatos.length) {
				candidatosHtml = f.candidatos.map(function (c) {
					return '<button type="button" class="ac-btn-outline ac-btn-inline liq-btn-candidato" style="margin:2px;" ' +
						'data-tabla="' + f.tabla + '" data-id="' + f.id + '" data-pos-id="' + escapeHtml(c.pos_id) + '">' +
						escapeHtml(c.pos_name) + ' <span class="ac-field-hint">(' + escapeHtml(c.pos_id) + ')</span></button>';
				}).join('');
			}
			// El estado se deriva de los candidatos recalculados AHORA (no del
			// estado_match guardado en el momento de importar) — si el maestro
			// de clientes cambió desde entonces, puede haber más o menos
			// candidatos que cuando se subió el Excel originalmente.
			var cantidadCandidatos = f.candidatos ? f.candidatos.length : 0;
			var etiquetaEstadoFila = cantidadCandidatos === 0
				? 'Sin candidatos'
				: 'Ambiguo (' + cantidadCandidatos + ' posibles)';
			return '<tr data-fila-tabla="' + f.tabla + '" data-fila-id="' + f.id + '">' +
				'<td>' + (f.tabla === 'cuota_categoria' ? 'Cuota/Venta' : 'Visibilidad') + '</td>' +
				'<td>' + escapeHtml(f.cedi_o_distribuidor) + '</td>' +
				'<td>' + escapeHtml(f.cliente_o_nombre) + '</td>' +
				'<td>' + etiquetaEstadoFila + '</td>' +
				'<td>' +
					'<div class="liq-candidatos">' + candidatosHtml + '</div>' +
					'<div class="ac-combo" style="margin-top:6px;">' +
						'<input type="text" class="ac-input ac-mini-input liq-busqueda-input" placeholder="Buscar cliente por nombre..." autocomplete="off">' +
					'</div>' +
					'<div class="liq-busqueda-resultados"></div>' +
					'<button type="button" class="ac-btn-outline ac-btn-inline liq-btn-sin-acta" style="margin-top:6px;" ' +
						'data-tabla="' + f.tabla + '" data-id="' + f.id + '">No tiene Acta (dato histórico)</button>' +
				'</td>' +
				'</tr>';
		}).join('');

		Array.prototype.forEach.call(pendientesBody.querySelectorAll('.liq-btn-candidato'), function (btn) {
			btn.addEventListener('click', function () {
				resolverFila(btn.dataset.tabla, btn.dataset.id, btn.dataset.posId, btn.closest('tr'), 'matchear');
			});
		});

		Array.prototype.forEach.call(pendientesBody.querySelectorAll('.liq-btn-sin-acta'), function (btn) {
			btn.addEventListener('click', function () {
				if (!confirm('¿Confirmás que esta fila es un dato histórico y no tiene ninguna Acta en el sistema? Esto no se puede deshacer desde acá.')) return;
				resolverFila(btn.dataset.tabla, btn.dataset.id, '', btn.closest('tr'), 'sin_acta');
			});
		});

		var buscarTimeout = null;
		Array.prototype.forEach.call(pendientesBody.querySelectorAll('.liq-busqueda-input'), function (input) {
			input.addEventListener('input', function () {
				clearTimeout(buscarTimeout);
				var tr = input.closest('tr');
				var contenedor = tr.querySelector('.liq-busqueda-resultados');
				var q = input.value.trim();
				if (q.length < 3) { contenedor.innerHTML = ''; return; }
				buscarTimeout = setTimeout(function () {
					fetch('getters/liquidacion_buscar_pos.php?q=' + encodeURIComponent(q))
						.then(function (r) { return r.json(); })
						.then(function (data) {
							var resultados = (data.ok && data.resultados) ? data.resultados : [];
							if (!resultados.length) {
								contenedor.innerHTML = '<p class="ac-field-hint">Sin resultados.</p>';
								return;
							}
							contenedor.innerHTML = resultados.map(function (r) {
								return '<button type="button" class="ac-btn-outline ac-btn-inline liq-btn-resultado-busqueda" style="margin:2px; display:block;" data-pos-id="' + escapeHtml(r.pos_id) + '">' +
									escapeHtml(r.pos_name) + ' <span class="ac-field-hint">(' + escapeHtml(r.pos_id) + (r.supervisor ? ' · ' + escapeHtml(r.supervisor) : '') + ')</span></button>';
							}).join('');
							Array.prototype.forEach.call(contenedor.querySelectorAll('.liq-btn-resultado-busqueda'), function (btn) {
								btn.addEventListener('click', function () {
									resolverFila(tr.dataset.filaTabla, tr.dataset.filaId, btn.dataset.posId, tr, 'matchear');
								});
							});
						});
				}, 350);
			});
		});
	}

	// ---------- Resumen de Pagos ----------
	function abrirResumen(importacionId) {
		vistaLista.classList.add('hidden');
		vistaResumen.classList.remove('hidden');
		resumenBody.innerHTML = '<tr><td colspan="7" class="ac-table-empty">Cargando...</td></tr>';
		resumenExportar.href = 'getters/liquidacion_resumen_pagos_export.php?importacion_id=' + importacionId;
		window.scrollTo(0, 0);

		fetch('getters/liquidacion_resumen_pagos.php?importacion_id=' + importacionId)
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) { mostrarToast(data.message || 'Error al cargar el resumen.', 'error'); return; }
				var imp = data.importacion;
				resumenSubtitulo.textContent = (etiquetaCanal[imp.canal] || imp.canal) + ' · ' + periodoTexto(imp.mes_inicio, imp.mes_fin) + ' ' + imp.anio;
				resumenFiltroCediLabel.textContent = imp.canal === 'distribuidor' ? 'Distribuidor' : 'CEDI';
				resumenDatos = data.filas || [];
				popularFiltroCedi(resumenDatos);
				resumenFiltroCedi.value = '';
				resumenFiltroEstado.value = '';
				aplicarFiltrosResumen();
			})
			.catch(function () {
				resumenBody.innerHTML = '<tr><td colspan="7" class="ac-table-empty">Error al cargar el resumen.</td></tr>';
			});
	}

	document.getElementById('liq-resumen-volver').addEventListener('click', function () {
		vistaResumen.classList.add('hidden');
		vistaLista.classList.remove('hidden');
		cargarImportaciones();
	});

	// Opciones del filtro CEDI/Distribuidor siempre desde el set COMPLETO (no
	// desde lo ya filtrado) — para que elegir "Revisar" en Estado no le borre
	// opciones al combo de al lado.
	function popularFiltroCedi(filas) {
		var vistos = {};
		var unicos = [];
		filas.forEach(function (f) {
			if (f.cedi_o_distribuidor && !vistos[f.cedi_o_distribuidor]) {
				vistos[f.cedi_o_distribuidor] = true;
				unicos.push(f.cedi_o_distribuidor);
			}
		});
		unicos.sort();
		resumenFiltroCedi.innerHTML = '<option value="">Todos</option>' + unicos.map(function (c) {
			return '<option value="' + escapeHtml(c) + '">' + escapeHtml(c) + '</option>';
		}).join('');
	}

	function aplicarFiltrosResumen() {
		var cedi = resumenFiltroCedi.value;
		var estado = resumenFiltroEstado.value;
		var filtradas = resumenDatos.filter(function (f) {
			if (cedi && f.cedi_o_distribuidor !== cedi) return false;
			if (estado && f.estado !== estado) return false;
			return true;
		});
		renderResumenStats(filtradas);
		renderResumenChart(filtradas);
		renderResumen(filtradas);
	}

	resumenFiltroCedi.addEventListener('change', aplicarFiltrosResumen);
	resumenFiltroEstado.addEventListener('change', aplicarFiltrosResumen);

	function renderResumenStats(filas) {
		var totalVolumen = 0, totalVisibilidad = 0, totalGeneral = 0, revisar = 0;
		filas.forEach(function (f) {
			totalVolumen += parseFloat(f.volumen) || 0;
			totalVisibilidad += parseFloat(f.visibilidad) || 0;
			totalGeneral += parseFloat(f.total) || 0;
			if (f.estado !== 'ok') revisar++;
		});
		var tiles = [
			{ label: 'Volumen', value: formatoMoneda(totalVolumen) },
			{ label: 'Visibilidad', value: formatoMoneda(totalVisibilidad) },
			{ label: 'Total general', value: formatoMoneda(totalGeneral) },
			{ label: 'Clientes', value: String(filas.length) },
			{ label: 'Por revisar', value: String(revisar), warn: revisar > 0 },
		];
		resumenStats.innerHTML = tiles.map(function (t) {
			return '<div class="ac-stat-tile' + (t.warn ? ' ac-stat-tile-warn' : '') + '">' +
				'<p class="ac-stat-label">' + t.label + '</p>' +
				'<p class="ac-stat-value">' + t.value + '</p>' +
				'</div>';
		}).join('');
	}

	// Gráfico de barras horizontales apiladas (Volumen + Visibilidad), top 10
	// por total — SVG a mano, sin librería de gráficos (mismo criterio del
	// proyecto de no sumar dependencias pesadas). Título/tooltip nativo (<title>
	// SVG) en cada segmento en vez de un tooltip HTML custom: para 10 barras en
	// una pantalla interna, es el punto justo de esfuerzo — el valor exacto
	// también queda siempre disponible en la tabla de abajo, nunca solo en el hover.
	function renderResumenChart(filas) {
		if (!filas.length) {
			resumenChart.innerHTML = '<p class="ac-field-hint">Sin datos para graficar.</p>';
			return;
		}
		var top = filas.slice().sort(function (a, b) { return b.total - a.total; }).slice(0, 10);
		var max = Math.max.apply(null, top.map(function (f) { return f.total; })) || 1;

		var filaAlto = 26, filaGap = 10, labelAncho = 190, chartAncho = 380, margenDer = 90;
		var anchoTotal = labelAncho + chartAncho + margenDer;
		var altoTotal = top.length * (filaAlto + filaGap);

		var svg = '<svg viewBox="0 0 ' + anchoTotal + ' ' + altoTotal + '" role="img" ' +
			'aria-label="Top clientes por total, volumen y visibilidad" style="width:100%;height:auto;display:block;">';
		top.forEach(function (f, i) {
			var y = i * (filaAlto + filaGap);
			var wVolumen = Math.max((f.volumen / max) * chartAncho, f.volumen > 0 ? 2 : 0);
			var wVisibilidad = Math.max((f.visibilidad / max) * chartAncho, f.visibilidad > 0 ? 2 : 0);
			var gapSeg = (wVolumen > 0 && wVisibilidad > 0) ? 2 : 0;
			var nombre = f.cliente_o_nombre.length > 28 ? f.cliente_o_nombre.slice(0, 26) + '…' : f.cliente_o_nombre;

			svg += '<text x="0" y="' + (y + filaAlto / 2 + 4) + '" class="ac-chart-label">' + escapeHtml(nombre) + '</text>';
			if (wVolumen > 0) {
				svg += '<rect class="ac-chart-bar-seg" x="' + labelAncho + '" y="' + y + '" width="' + wVolumen + '" height="' + filaAlto + '" rx="3" fill="' + COLOR_VOLUMEN + '">' +
					'<title>' + escapeHtml(f.cliente_o_nombre) + ' — Volumen: ' + formatoMoneda(f.volumen) + '</title></rect>';
			}
			if (wVisibilidad > 0) {
				svg += '<rect class="ac-chart-bar-seg" x="' + (labelAncho + wVolumen + gapSeg) + '" y="' + y + '" width="' + wVisibilidad + '" height="' + filaAlto + '" rx="3" fill="' + COLOR_VISIBILIDAD + '">' +
					'<title>' + escapeHtml(f.cliente_o_nombre) + ' — Visibilidad: ' + formatoMoneda(f.visibilidad) + '</title></rect>';
			}
			svg += '<text x="' + (labelAncho + wVolumen + wVisibilidad + gapSeg + 8) + '" y="' + (y + filaAlto / 2 + 4) + '" class="ac-chart-value">' + formatoMoneda(f.total) + '</text>';
		});
		svg += '</svg>';

		resumenChart.innerHTML =
			'<div class="ac-chart-legend">' +
				'<span class="ac-chart-legend-item"><span class="ac-chart-swatch" style="background:' + COLOR_VOLUMEN + ';"></span>Volumen</span>' +
				'<span class="ac-chart-legend-item"><span class="ac-chart-swatch" style="background:' + COLOR_VISIBILIDAD + ';"></span>Visibilidad</span>' +
			'</div>' + svg;
	}

	function renderResumen(filas) {
		if (!filas.length) {
			resumenBody.innerHTML = '<tr><td colspan="7" class="ac-table-empty">No hay filas con este filtro.</td></tr>';
			return;
		}
		resumenBody.innerHTML = filas.map(function (f) {
			var badge = f.estado === 'ok'
				? '<span class="ac-badge ac-badge-ok">OK</span>'
				: '<span class="ac-badge ac-badge-revisar">Revisar</span>';
			return '<tr>' +
				'<td>' + escapeHtml(f.cedi_o_distribuidor) + '</td>' +
				'<td>' + escapeHtml(f.cliente_o_nombre) + '</td>' +
				'<td>' + (f.documento_no ? escapeHtml(f.documento_no) : '<span class="ac-field-hint">Sin vincular</span>') + '</td>' +
				'<td class="ac-text-right ac-tabular">' + formatoMoneda(f.volumen) + '</td>' +
				'<td class="ac-text-right ac-tabular">' + formatoMoneda(f.visibilidad) + '</td>' +
				'<td class="ac-text-right ac-tabular">' + formatoMoneda(f.total) + '</td>' +
				'<td class="ac-text-center">' + badge + '</td>' +
				'</tr>';
		}).join('');
	}

	function resolverFila(tabla, id, posId, tr, accion) {
		fetch('getters/liquidacion_resolver_match.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({ tabla: tabla, id: id, pos_id: posId, accion: accion }),
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				mostrarToast(data.message, data.ok);
				if (data.ok && tr) tr.remove();
			})
			.catch(function () { mostrarToast('Error de conexión al resolver.', 'error'); });
	}

	cargarImportaciones();
})();

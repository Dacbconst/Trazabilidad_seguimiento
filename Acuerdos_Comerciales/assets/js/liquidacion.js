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
	var resumenFiltroTrimestre = document.getElementById('liq-resumen-filtro-trimestre');
	var resumenFiltroAnio = document.getElementById('liq-resumen-filtro-anio');
	var resumenCanalActual = 'directa'; // fijo mientras la pantalla de Resumen está abierta, ver abrirResumen().
	// Colores fijos de la serie: Volumen y Visibilidad SIEMPRE con estos colores, en este orden, en todo el chart.
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

	// Período detectado del archivo (no elegido a mano): un solo mes se muestra una vez, un rango se muestra "Abr - Jun".
	function periodoTexto(mesInicio, mesFin) {
		mesInicio = parseInt(mesInicio, 10); mesFin = parseInt(mesFin, 10);
		if (mesInicio === mesFin) return mesesCorto[mesInicio];
		return mesesCorto[mesInicio] + ' - ' + mesesCorto[mesFin];
	}

	// Solo mapea a un trimestre si el rango calza EXACTO (Ene-Mar, etc.); un rango raro abre el Resumen sin filtro de período en vez de adivinar mal.
	var TRIMESTRES_LIQ = [[0, 2], [3, 5], [6, 8], [9, 11]];
	function trimestreDeRango(mesInicio, mesFin) {
		mesInicio = parseInt(mesInicio, 10); mesFin = parseInt(mesFin, 10);
		for (var i = 0; i < TRIMESTRES_LIQ.length; i++) {
			if (mesInicio === TRIMESTRES_LIQ[i][0] && mesFin === TRIMESTRES_LIQ[i][1]) return i + 1;
		}
		return 0;
	}

	function formatoMoneda(valor) {
		var n = parseFloat(valor) || 0;
		return '$' + n.toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	// ---------- Listado de importaciones ----------
	var liqActualizarBtn = document.getElementById('liq-actualizar');
	var liqTablaCard = tablaBody.closest('.ac-card');
	function cargarImportaciones() {
		tablaBody.innerHTML = '<tr><td colspan="7" class="ac-table-empty">Cargando...</td></tr>';
		// Mismo feedback de carga reusable que Historial (assets/js/cargando.js): el ícono de Actualizar también anima, no solo el texto de la fila.
		acBotonCargando(liqActualizarBtn, true);
		acMostrarCargando(liqTablaCard);
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
					// El Resumen de Pagos ya no es por importación (ver liquidacion_resumen_pagos_unificado()): este botón abre la vista unificada del canal, pre-filtrada al período de esta fila si calza con un trimestre exacto; desde ahí se amplía a "Todos los períodos".
					var trimestreFila = trimestreDeRango(f.mes_inicio, f.mes_fin);
					var accionResumen = '<button type="button" class="ac-link-id liq-btn-resumen" ' +
						'data-canal="' + escapeHtml(f.canal) + '" data-trimestre="' + trimestreFila + '" data-anio="' + f.anio + '">Resumen de Pagos</button>';
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
					btn.addEventListener('click', function () {
						abrirResumen(btn.dataset.canal, parseInt(btn.dataset.trimestre, 10), parseInt(btn.dataset.anio, 10));
					});
				});
			})
			.catch(function () {
				tablaBody.innerHTML = '<tr><td colspan="7" class="ac-table-empty">Error al cargar las importaciones.</td></tr>';
			})
			.finally(function () {
				acBotonCargando(liqActualizarBtn, false);
				acOcultarCargando(liqTablaCard);
			});
	}

	document.getElementById('liq-actualizar').addEventListener('click', cargarImportaciones);

	// ---------- Modal: Subir Excel ----------
	var subirModalOverlay = document.getElementById('liq-subir-modal-overlay');
	var formSubir = document.getElementById('liq-form-subir');
	var submitBtn = document.getElementById('liq-subir-submit');
	var subirProgreso = document.getElementById('liq-subir-progreso');
	var subirProgresoFill = document.getElementById('liq-subir-progreso-fill');
	var subirProgresoTexto = document.getElementById('liq-subir-progreso-texto');

	document.getElementById('liq-abrir-subir').addEventListener('click', function () {
		formSubir.reset();
		ocultarProgresoSubirLiq();
		subirModalOverlay.classList.add('ac-modal-open');
	});
	document.getElementById('liq-subir-modal-close').addEventListener('click', function () {
		subirModalOverlay.classList.remove('ac-modal-open');
	});
	subirModalOverlay.addEventListener('click', function (e) {
		if (e.target === subirModalOverlay) subirModalOverlay.classList.remove('ac-modal-open');
	});

	function mostrarProgresoSubirLiq() {
		subirProgresoFill.style.width = '0%';
		subirProgresoTexto.textContent = 'Subiendo…';
		subirProgreso.classList.remove('hidden');
	}
	function ocultarProgresoSubirLiq() {
		subirProgreso.classList.add('hidden');
	}

	// XHR en vez de fetch() (mismo arreglo que Repositorios): fetch() no expone progreso de subida, un Excel pesado dejaría el botón mudo.
	formSubir.addEventListener('submit', function (e) {
		e.preventDefault();
		submitBtn.disabled = true;
		submitBtn.textContent = 'Procesando...';
		mostrarProgresoSubirLiq();

		var xhr = new XMLHttpRequest();
		xhr.open('POST', 'getters/importar_liquidacion.php');
		xhr.upload.addEventListener('progress', function (ev) {
			if (!ev.lengthComputable) return;
			var pct = Math.round((ev.loaded / ev.total) * 100);
			subirProgresoFill.style.width = pct + '%';
			subirProgresoTexto.textContent = 'Subiendo… ' + pct + '%';
		});
		xhr.addEventListener('load', function () {
			submitBtn.disabled = false;
			submitBtn.textContent = 'Procesar Excel';
			ocultarProgresoSubirLiq();
			var data;
			try { data = JSON.parse(xhr.responseText); } catch (err) {
				mostrarToast('Respuesta inválida del servidor.', 'error');
				return;
			}
			mostrarToast(data.message, data.ok ? 'success' : 'error');
			if (data.ok) {
				subirModalOverlay.classList.remove('ac-modal-open');
				if (data.filas_pendientes > 0) {
					mostrarToast(data.filas_pendientes + ' filas quedaron pendientes de asignar a mano.', 'warning');
				}
				cargarImportaciones();
			}
		});
		xhr.addEventListener('error', function () {
			submitBtn.disabled = false;
			submitBtn.textContent = 'Procesar Excel';
			ocultarProgresoSubirLiq();
			mostrarToast('Error de conexión al subir el archivo.', 'error');
		});
		xhr.send(new FormData(formSubir));
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

	// Formatea una fecha 'YYYY-MM-DD' (fecha_generacion) para mostrar en la lista de Actas candidatas — sin hora, es solo para distinguir cuál es cuál.
	function formatoFechaCorta(fecha) {
		if (!fecha) return 'sin fecha';
		var partes = fecha.split('-');
		return partes.length === 3 ? partes[2] + '/' + partes[1] + '/' + partes[0] : fecha;
	}

	function renderPendientes(filas) {
		if (!filas.length) {
			pendientesBody.innerHTML = '<tr><td colspan="5" class="ac-table-empty">No quedan filas pendientes — todo se resolvió.</td></tr>';
			return;
		}
		pendientesBody.innerHTML = filas.map(function (f) {
			// Ambigüedad de ACTA: el cliente ya resolvió a un solo pos_id, pero tiene 2+ Actas cuyo período+año se solapan. No se muestra el selector de cliente (ya resuelto), directo cuál Acta es, para no mostrar los dos pasos de match a la vez.
			if (f.actas_candidatas && f.actas_candidatas.length) {
				var actasHtml = f.actas_candidatas.map(function (a) {
					return '<button type="button" class="ac-btn-outline ac-btn-inline liq-btn-acta" style="margin:2px; display:block; text-align:left;" ' +
						'data-tabla="' + f.tabla + '" data-id="' + f.id + '" data-pos-id="' + escapeHtml(f.pos_id_resuelto) + '" data-acuerdo-id="' + a.id + '">' +
						'#' + escapeHtml(a.documento_no) + ' <span class="ac-field-hint">(' + formatoFechaCorta(a.fecha_generacion) + ' · ' + escapeHtml(a.estado) + ')</span></button>';
				}).join('');
				return '<tr data-fila-tabla="' + f.tabla + '" data-fila-id="' + f.id + '">' +
					'<td>' + (f.tabla === 'cuota_categoria' ? 'Cuota/Venta' : 'Visibilidad') + '</td>' +
					'<td>' + escapeHtml(f.cedi_o_distribuidor) + '</td>' +
					'<td>' + escapeHtml(f.cliente_o_nombre) + '</td>' +
					'<td>Cliente OK, Acta ambigua (' + f.actas_candidatas.length + ' posibles)</td>' +
					'<td>' +
						'<p class="ac-field-hint">Hay más de un Acta para este cliente en el mismo período — elige cuál es:</p>' +
						'<div class="liq-candidatos">' + actasHtml + '</div>' +
					'</td>' +
					'</tr>';
			}

			var candidatosHtml = '';
			if (f.candidatos && f.candidatos.length) {
				candidatosHtml = f.candidatos.map(function (c) {
					return '<button type="button" class="ac-btn-outline ac-btn-inline liq-btn-candidato" style="margin:2px;" ' +
						'data-tabla="' + f.tabla + '" data-id="' + f.id + '" data-pos-id="' + escapeHtml(c.pos_id) + '">' +
						escapeHtml(c.pos_name) + ' <span class="ac-field-hint">(' + escapeHtml(c.pos_id) + ')</span></button>';
				}).join('');
			}
			// El estado se deriva de los candidatos recalculados AHORA, no del estado_match guardado al importar: el maestro pudo cambiar desde entonces.
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

		Array.prototype.forEach.call(pendientesBody.querySelectorAll('.liq-btn-acta'), function (btn) {
			btn.addEventListener('click', function () {
				resolverFila(btn.dataset.tabla, btn.dataset.id, btn.dataset.posId, btn.closest('tr'), 'matchear', btn.dataset.acuerdoId);
			});
		});

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

	// ---------- Resumen de Pagos ---------- Unificado por canal: Trimestre/Año filtran server-side, CEDI/Estado client-side. Cada fila trae su propio período, nunca se suman entre trimestres.
	function abrirResumen(canal, trimestre, anio) {
		vistaLista.classList.add('hidden');
		vistaResumen.classList.remove('hidden');
		window.scrollTo(0, 0);
		resumenCanalActual = canal;
		resumenFiltroCediLabel.textContent = canal === 'distribuidor' ? 'Distribuidor' : 'CEDI';
		resumenFiltroTrimestre.value = String(trimestre || 0);
		resumenFiltroAnio.value = String(anio || 0);
		cargarResumen();
	}

	function cargarResumen() {
		var canal = resumenCanalActual;
		var trimestre = resumenFiltroTrimestre.value;
		var anio = resumenFiltroAnio.value;
		var qs = 'canal=' + encodeURIComponent(canal) + '&trimestre=' + encodeURIComponent(trimestre) + '&anio=' + encodeURIComponent(anio);

		resumenBody.innerHTML = '<tr><td colspan="8" class="ac-table-empty">Cargando...</td></tr>';
		resumenExportar.href = 'getters/liquidacion_resumen_pagos_export.php?' + qs;

		fetch('getters/liquidacion_resumen_pagos.php?' + qs)
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) { mostrarToast(data.message || 'Error al cargar el resumen.', 'error'); return; }
				var cantidadImportaciones = (data.importaciones || []).length;
				var etiquetaPeriodo = trimestre === '0'
					? 'Todos los períodos'
					: ('Q' + trimestre + (anio !== '0' ? ' ' + anio : ''));
				resumenSubtitulo.textContent = (etiquetaCanal[canal] || canal) + ' · ' + etiquetaPeriodo +
					' · ' + cantidadImportaciones + (cantidadImportaciones === 1 ? ' importación' : ' importaciones');
				resumenDatos = data.filas || [];
				popularFiltroCedi(resumenDatos);
				resumenFiltroCedi.value = '';
				resumenFiltroEstado.value = '';
				aplicarFiltrosResumen();
			})
			.catch(function () {
				resumenBody.innerHTML = '<tr><td colspan="8" class="ac-table-empty">Error al cargar el resumen.</td></tr>';
			});
	}

	resumenFiltroTrimestre.addEventListener('change', cargarResumen);
	resumenFiltroAnio.addEventListener('change', cargarResumen);

	document.getElementById('liq-resumen-volver').addEventListener('click', function () {
		vistaResumen.classList.add('hidden');
		vistaLista.classList.remove('hidden');
		cargarImportaciones();
	});

	// Opciones del filtro CEDI/Distribuidor siempre desde el set completo (no lo ya filtrado), para que "Revisar" en Estado no borre el combo de al lado.
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

	// Top 10 por total, HTML/CSS en vez de SVG: medir por cantidad de caracteres no sirve, el ancho real en píxeles varía y tapaba la barra. El label es un <span> con text-overflow:ellipsis real, nunca se tapa; el nombre completo queda en `title` además de en la tabla de abajo.
	function renderResumenChart(filas) {
		if (!filas.length) {
			resumenChart.innerHTML = '<p class="ac-field-hint">Sin datos para graficar.</p>';
			return;
		}
		var top = filas.slice().sort(function (a, b) { return b.total - a.total; }).slice(0, 10);
		var max = Math.max.apply(null, top.map(function (f) { return f.total; })) || 1;

		var filasHtml = top.map(function (f) {
			// Cliente + período en la etiqueta: el Resumen junta varios trimestres, el mismo cliente puede aparecer 2 veces (una por período).
			var etiqueta = f.cliente_o_nombre + ' (' + periodoTexto(f.mes_inicio, f.mes_fin) + ' ' + f.anio + ')';
			var pctVolumen = Math.max((f.volumen / max) * 100, f.volumen > 0 ? 1 : 0);
			var pctVisibilidad = Math.max((f.visibilidad / max) * 100, f.visibilidad > 0 ? 1 : 0);
			var segsHtml = '';
			if (pctVolumen > 0) {
				segsHtml += '<div class="ac-chart-seg" style="width:' + pctVolumen + '%; background:' + COLOR_VOLUMEN + ';" ' +
					'title="' + escapeHtml(etiqueta) + ' — Volumen: ' + formatoMoneda(f.volumen) + '"></div>';
			}
			if (pctVisibilidad > 0) {
				segsHtml += '<div class="ac-chart-seg" style="width:' + pctVisibilidad + '%; background:' + COLOR_VISIBILIDAD + ';" ' +
					'title="' + escapeHtml(etiqueta) + ' — Visibilidad: ' + formatoMoneda(f.visibilidad) + '"></div>';
			}
			return '<div class="ac-chart-row">' +
				'<span class="ac-chart-row-label" title="' + escapeHtml(etiqueta) + '">' + escapeHtml(etiqueta) + '</span>' +
				'<div class="ac-chart-track">' + segsHtml + '</div>' +
				'<span class="ac-chart-row-value">' + formatoMoneda(f.total) + '</span>' +
				'</div>';
		}).join('');

		resumenChart.innerHTML =
			'<div class="ac-chart-legend">' +
				'<span class="ac-chart-legend-item"><span class="ac-chart-swatch" style="background:' + COLOR_VOLUMEN + ';"></span>Volumen</span>' +
				'<span class="ac-chart-legend-item"><span class="ac-chart-swatch" style="background:' + COLOR_VISIBILIDAD + ';"></span>Visibilidad</span>' +
			'</div>' +
			'<div class="ac-chart-rows" role="img" aria-label="Top clientes por total, volumen y visibilidad">' + filasHtml + '</div>';
	}

	function renderResumen(filas) {
		if (!filas.length) {
			resumenBody.innerHTML = '<tr><td colspan="8" class="ac-table-empty">No hay filas con este filtro.</td></tr>';
			return;
		}
		resumenBody.innerHTML = filas.map(function (f) {
			var badge = f.estado === 'ok'
				? '<span class="ac-badge ac-badge-ok">OK</span>'
				: '<span class="ac-badge ac-badge-revisar">Revisar</span>';
			return '<tr>' +
				'<td>' + escapeHtml(f.cedi_o_distribuidor) + '</td>' +
				'<td>' + escapeHtml(f.cliente_o_nombre) + '</td>' +
				'<td>' + periodoTexto(f.mes_inicio, f.mes_fin) + ' ' + f.anio + '</td>' +
				'<td>' + (f.documento_no ? escapeHtml(f.documento_no) : '<span class="ac-field-hint">Sin vincular</span>') + '</td>' +
				'<td class="ac-text-right ac-tabular">' + formatoMoneda(f.volumen) + '</td>' +
				'<td class="ac-text-right ac-tabular">' + formatoMoneda(f.visibilidad) + '</td>' +
				'<td class="ac-text-right ac-tabular">' + formatoMoneda(f.total) + '</td>' +
				'<td class="ac-text-center">' + badge + '</td>' +
				'</tr>';
		}).join('');
	}

	function resolverFila(tabla, id, posId, tr, accion, acuerdoId) {
		var params = { tabla: tabla, id: id, pos_id: posId, accion: accion };
		// acuerdoId: solo cuando el cliente ya está resuelto pero hay 2+ Actas candidatas para el mismo período+año (ver renderPendientes).
		if (acuerdoId) params.acuerdo_id = acuerdoId;
		fetch('getters/liquidacion_resolver_match.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams(params),
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				mostrarToast(data.message, data.ok);
				if (data.ok && tr) tr.remove();
			})
			.catch(function () { mostrarToast('Error de conexión al resolver.', 'error'); });
	}

	// Aviso "en desarrollo" (SweetAlert2, un solo botón). Este script corre siempre al cargar index.php sin importar la pestaña activa, por eso no se dispara acá directo (saldría "de la nada"); se expone para que index.php lo llame al entrar de verdad a la sección, una vez por sesión.
	var avisoDesarrolloMostrado = false;
	window.acLiquidacionRefrescar = function () {
		if (!avisoDesarrolloMostrado) {
			avisoDesarrolloMostrado = true;
			Swal.fire({
				icon: 'info',
				title: 'Módulo en desarrollo',
				text: 'Liquidación todavía está en construcción — algunas partes pueden cambiar o no funcionar del todo todavía.',
				confirmButtonText: 'Entendido'
			});
		}
		cargarImportaciones();
	};

	cargarImportaciones();
})();

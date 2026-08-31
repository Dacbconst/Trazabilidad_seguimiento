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

	// Para el botón "Resumen de Pagos" de una fila puntual del listado de
	// importaciones: solo mapea a un trimestre si el rango de esa
	// importación calza EXACTO con uno (Ene-Mar, etc.) — si cubre un rango
	// raro (ej. un solo mes, o Feb-Abr), abre el Resumen sin filtro de
	// período (0 = todos) en vez de adivinar mal a cuál trimestre pertenece.
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
		// Mismo feedback de carga reusable que Historial (2026-08-25, ver
		// assets/js/cargando.js) — el texto "Cargando..." de la fila de
		// arriba ya estaba, pero el ícono de Actualizar se quedaba quieto.
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
					// El Resumen de Pagos ya no es por importación (ver
					// liquidacion_resumen_pagos_unificado() en
					// includes/liquidacion_import.php) — este botón abre la
					// vista unificada del canal de esta fila, pre-filtrada al
					// período de esta importación (si calza con un trimestre
					// exacto) para que el flujo de "click en esta fila para
					// ver su resumen" siga funcionando igual que antes; desde
					// ahí se puede ampliar el filtro a "Todos los períodos".
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

	// XHR en vez de fetch() (2026-08-24, mismo arreglo que Repositorios): fetch()
	// no expone progreso de subida, así que con un Excel pesado el botón
	// "Procesando..." se queda mudo sin dar ninguna señal de avance real.
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

	// Formatea una fecha 'YYYY-MM-DD' (fecha_generacion) para mostrar en la
	// lista de Actas candidatas — sin hora, es solo para distinguir cuál es cuál.
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
			// Ambigüedad de ACTA (2026-08-20): el cliente ya resolvió a un solo
			// pos_id, pero ese cliente tiene 2+ Actas cuyo período+año se
			// solapan (ej. dos Actas generadas para el mismo lugar en el mismo
			// trimestre) — acá NO se muestra el selector de cliente (ya está
			// resuelto), se muestra directo cuál Acta es, para no confundir
			// mostrando los dos pasos de match a la vez.
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

	// ---------- Resumen de Pagos ----------
	// Unificado por canal (2026-08-20): antes esta pantalla mostraba UNA
	// sola importación (un Excel puntual); ahora junta TODAS las
	// importaciones completadas de un canal — Trimestre/Año filtran del
	// lado del servidor (cambian QUÉ importaciones se incluyen), CEDI/Estado
	// siguen filtrando del lado del cliente sobre lo ya cargado (no cambian
	// eso). Cada fila trae su propio período — nunca se suman montos de
	// trimestres distintos en un solo número (decisión del usuario).
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
	// por total — HTML/CSS en vez de SVG a mano (2026-08-20, corregido: la
	// versión en SVG media el nombre por CANTIDAD DE CARACTERES para decidir
	// si truncar, pero el ancho real en píxeles de cada letra varía — un
	// nombre largo que "por caracteres" parecía entrar terminaba invadiendo
	// el área de la barra, y como la barra se dibuja DESPUÉS en el XML del
	// SVG, la tapaba a la mitad ("DISTRIBUIDORA SUPERALIANZA." cortado a la
	// mitad de una palabra, sin ningún "…"). El label ahora es un <span> con
	// `text-overflow:ellipsis` real del navegador — nunca se puede tapar con
	// otro elemento porque no comparten espacio, y el nombre completo queda
	// en el atributo `title` (tooltip nativo) además de en la fila de la
	// tabla de abajo. Título/tooltip nativo (`title`) en cada segmento en vez
	// de un tooltip HTML custom: para 10 barras en una pantalla interna, es
	// el punto justo de esfuerzo — el valor exacto también queda siempre
	// disponible en la tabla de abajo, nunca solo en el hover.
	function renderResumenChart(filas) {
		if (!filas.length) {
			resumenChart.innerHTML = '<p class="ac-field-hint">Sin datos para graficar.</p>';
			return;
		}
		var top = filas.slice().sort(function (a, b) { return b.total - a.total; }).slice(0, 10);
		var max = Math.max.apply(null, top.map(function (f) { return f.total; })) || 1;

		var filasHtml = top.map(function (f) {
			// Cliente + período en la etiqueta (2026-08-20): el Resumen ahora
			// junta varios trimestres — el mismo cliente puede aparecer 2
			// veces (una por período), así que el nombre solo ya no alcanza
			// para distinguir las barras.
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
		// acuerdoId: solo cuando el cliente ya está resuelto (1 pos_id) pero
		// hay 2+ Actas candidatas para el mismo período+año (ver renderPendientes
		// y liquidacion_resolver_match.php) — quién eligió cuál Acta es.
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

	// Aviso de "en desarrollo" (2026-08-20, pedido explícito) — mismo estilo
	// de ventanita SweetAlert2 que ya usa la confirmación de "Eliminar" en
	// Historial/Mis Borradores, acá como alerta informativa (un solo botón,
	// sin cancelar). Este script corre UNA sola vez al cargar index.php (como
	// todos los módulos, ver arquitectura de secciones en CLAUDE.md), pase lo
	// que pase esté o no activa la pestaña Liquidación — por eso NO se
	// dispara acá directo (salía "de la nada" en cualquier otro módulo con el
	// que arrancara la sesión). Se expone para que index.php lo llame recién
	// al entrar de verdad a esta sección (mismo patrón que
	// window.acHistorialRefrescar/window.acUsuariosRefrescar), y una sola vez
	// por sesión (avisoDesarrolloMostrado), no cada vez que se vuelve a
	// hacer click en la pestaña.
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

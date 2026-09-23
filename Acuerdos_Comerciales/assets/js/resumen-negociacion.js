(function () {
	var raiz = document.getElementById('ac-negociacion');
	if (!raiz) return;

	var trimestreGroup = document.getElementById('neg-trimestre-group');
	var anioSelect      = document.getElementById('neg-anio');
	var buscarInput     = document.getElementById('neg-buscar');
	var listaCont       = document.getElementById('neg-equipo-lista');
	var detalleCard     = document.getElementById('neg-detalle-card');
	var tarjetas        = raiz.querySelectorAll('.ac-card');
	var actualizarBtn   = document.getElementById('neg-actualizar');
	var kpisQuienEl     = document.getElementById('neg-kpis-quien');
	var kpisResetBtn    = document.getElementById('neg-kpis-reset');

	// filtroDetalle es LOCAL al usuario abierto (pedido explícito: clickear un KPI de arriba no debe reordenar/filtrar la lista de Equipo, solo el panel del usuario seleccionado). deseleccionExplicita distingue "todavía no elegiste a nadie" (auto-selecciona el primero) de "elegiste sacar tu selección a propósito" (se queda en Equipo completo aunque se refresque la lista).
	var estado = { canal: 'total', trimestre: 0, anio: parseInt(anioSelect.value, 10) || 0, busqueda: '', selectedId: null, filtroDetalle: 'todas', deseleccionExplicita: false };
	var equipoActual  = [];
	var statsActual   = { total: 0, rebate: 0, cabeceras: 0, rumas: 0, perchas: 0 };
	var filaActual    = null;
	var ultimoFetchKey = null;
	var ultimoDetalleActas = null;
	var resumenReqId = 0;
	var detalleReqId = 0;
	var cacheLineas = {};

	// clase: mismo criterio de color que .ac-hist-stat-ok/warn/bad (ver KPIs arriba) + purple nuevo para Rumas.
	var TIPO_META = {
		meta_compra: { label: 'Rebate',    icon: 'percent',     clase: 'ok' },
		cabecera:    { label: 'Cabeceras', icon: 'view_agenda', clase: 'warn' },
		ruma:        { label: 'Rumas',     icon: 'stacks',      clase: 'purple' },
		percha:      { label: 'Perchas',   icon: 'shelves',     clase: 'bad' }
	};
	var ORDEN_TIPOS = ['meta_compra', 'cabecera', 'ruma', 'percha'];
	// Mismo mapeo que $mapaTipos en listar_actas_negociacion_usuario() (functions.php).
	var FILTRO_A_TIPO = { rebate: 'meta_compra', cabeceras: 'cabecera', rumas: 'ruma', perchas: 'percha' };

	var SUBFILTROS = [
		{ key: 'todas',     label: 'Todas',      vacioTexto: 'Este usuario no tiene Acuerdos firmados todavía en este período.' },
		{ key: 'rebate',    label: 'Rebate',      vacioTexto: 'Este usuario no negoció Rebate todavía en este período.' },
		{ key: 'cabeceras', label: 'Cabeceras',  vacioTexto: 'Este usuario no negoció Cabeceras todavía en este período.' },
		{ key: 'rumas',     label: 'Rumas',       vacioTexto: 'Este usuario no negoció Rumas todavía en este período.' },
		{ key: 'perchas',   label: 'Perchas',     vacioTexto: 'Este usuario no negoció Perchas todavía en este período.' }
	];

	function esMobile() { return window.matchMedia('(max-width: 900px)').matches; }

	function escapeHtml(texto) {
		var div = document.createElement('div');
		div.textContent = texto == null ? '' : String(texto);
		return div.innerHTML;
	}

	// ---------- Equipo: siempre ordenado por Total, sin filtrar por tipo (eso ahora es local a cada usuario) ----------
	function computeFilasEquipo(equipo) {
		return equipo.map(function (u) {
			return {
				id: u.usuario_id, nombre: u.nombre, iniciales: u.iniciales,
				ringCss: 'conic-gradient(#00288e 0% 100%)',
				badgeText: u.total + (u.total === 1 ? ' Acuerdo' : ' Acuerdos'),
				sortKey: -u.total
			};
		}).sort(function (a, b) { return a.sortKey - b.sortKey; });
	}

	function aplicarBusqueda(filas, busqueda) {
		var q = (busqueda || '').trim().toLowerCase();
		if (!q) return filas;
		return filas.filter(function (f) { return f.nombre.toLowerCase().indexOf(q) !== -1; });
	}

	function buscarUsuarioEquipo(id) {
		return equipoActual.filter(function (u) { return u.usuario_id === id; })[0];
	}

	// Pedido explícito (2026-09-22): las tarjetas nunca reordenan Equipo, pero SÍ cambian de foco — equipo completo por default, o el usuario que tengas seleccionado en la lista.
	function actualizarKpis(datos, etiqueta) {
		['total', 'rebate', 'cabeceras', 'rumas', 'perchas'].forEach(function (k) {
			var el = raiz.querySelector('[data-kpi="' + k + '"]');
			if (el) el.textContent = datos && datos[k] != null ? datos[k] : 0;
		});
		if (kpisQuienEl) kpisQuienEl.textContent = etiqueta;
		if (kpisResetBtn) kpisResetBtn.classList.toggle('hidden', etiqueta === 'Equipo completo');
	}

	// Pedido explícito: poder sacar la selección de usuario y que los KPI vuelvan a Equipo completo. deseleccionExplicita evita que refrescarListaYDetalle() la pise auto-seleccionando al primero de nuevo.
	function deseleccionarUsuario() {
		estado.selectedId = null;
		estado.deseleccionExplicita = true;
		filaActual = null;
		ultimoFetchKey = null;
		Array.prototype.forEach.call(listaCont.querySelectorAll('.ac-seg-fila-usuario'), function (r) { r.classList.remove('is-selected'); });
		var abierto = document.getElementById(ID_DETALLE_INLINE);
		if (abierto) abierto.remove();
		detalleCard.innerHTML = '<div class="ac-seg-vacio-detalle"><span class="material-symbols-outlined">group</span><p>Seleccioná un usuario del Equipo para ver su detalle.</p></div>';
		actualizarKpis(statsActual, 'Equipo completo');
	}

	function avatarRingHtml(ringCss, iniciales, grande) {
		var claseTam = grande ? ' ac-seg-avatar-ring-lg' : '';
		return '<div class="ac-seg-avatar-ring' + claseTam + '" style="background:' + ringCss + ';">' +
			'<div class="ac-seg-avatar-gap"><div class="ac-avatar-initials">' + escapeHtml(iniciales) + '</div></div>' +
			'</div>';
	}

	function badgeHtml(claseExtra, texto) {
		var clase = 'ac-badge' + (claseExtra ? ' ' + claseExtra : '');
		return '<span class="' + clase + '">' + escapeHtml(texto) + '</span>';
	}

	var ID_DETALLE_INLINE = 'neg-fila-detalle-inline';
	function inlineDetalleHtml() {
		return '<div class="ac-seg-fila-detalle-inline" id="' + ID_DETALLE_INLINE + '"><div class="ac-seg-cargando">Cargando...</div></div>';
	}

	function renderLista(filas) {
		var mobile = esMobile();
		listaCont.innerHTML = filas.map(function (f) {
			var sel = f.id === estado.selectedId ? ' is-selected' : '';
			var filaHtml = '<div class="ac-seg-fila-usuario' + sel + '" data-id="' + f.id + '">' +
				avatarRingHtml(f.ringCss, f.iniciales, false) +
				'<div class="ac-seg-fila-info"><p class="ac-user-name">' + escapeHtml(f.nombre) + '</p></div>' +
				badgeHtml('', f.badgeText) +
				'<span class="material-symbols-outlined ac-seg-fila-chevron">expand_more</span>' +
				'</div>';
			if (mobile && f.id === estado.selectedId) {
				filaHtml += (ultimoFetchKey === claveDetalle(f.id) && ultimoDetalleActas)
					? '<div class="ac-seg-fila-detalle-inline" id="' + ID_DETALLE_INLINE + '">' + contenidoAcordeonHtml(ultimoDetalleActas) + '</div>'
					: inlineDetalleHtml();
			}
			return filaHtml;
		}).join('');
		Array.prototype.forEach.call(listaCont.querySelectorAll('.ac-seg-fila-usuario'), function (row) {
			row.addEventListener('click', function () {
				var id = parseInt(row.dataset.id, 10);
				// Tocar de nuevo al mismo usuario lo deselecciona — en desktop y mobile por igual (antes solo pasaba en mobile).
				if (id === estado.selectedId) {
					deseleccionarUsuario();
					return;
				}
				Array.prototype.forEach.call(listaCont.querySelectorAll('.ac-seg-fila-usuario'), function (r) {
					r.classList.toggle('is-selected', parseInt(r.dataset.id, 10) === id);
				});
				if (esMobile()) {
					var viejo = document.getElementById(ID_DETALLE_INLINE);
					if (viejo) viejo.remove();
					row.insertAdjacentHTML('afterend', inlineDetalleHtml());
				}
				estado.selectedId = id;
				estado.deseleccionExplicita = false;
				estado.filtroDetalle = 'todas';
				filaActual = filas.filter(function (f) { return f.id === id; })[0];
				cargarDetalle(filaActual);
				var uRaw = buscarUsuarioEquipo(id);
				if (uRaw) actualizarKpis(uRaw, uRaw.nombre);
			});
		});
	}

	function renderVacio(texto) {
		var html = '<div class="ac-seg-vacio"><span class="material-symbols-outlined">inbox</span><p>' + escapeHtml(texto) + '</p></div>';
		listaCont.innerHTML = html;
		detalleCard.innerHTML = '<div class="ac-seg-vacio-detalle"><span class="material-symbols-outlined">inbox</span><p>' + escapeHtml(texto) + '</p></div>';
	}

	function mostrarErrorGeneral() {
		mostrarToast('Error de conexión al cargar el resumen.', 'error');
		listaCont.innerHTML = '<div class="ac-seg-vacio"><span class="material-symbols-outlined">error</span><p>No se pudo cargar el equipo. Actualizá la página para reintentar.</p></div>';
		detalleCard.innerHTML = '<div class="ac-seg-vacio-detalle"><span class="material-symbols-outlined">error</span><p>No se pudo cargar el detalle.</p></div>';
	}

	// ---------- Droplist por Acta: nombre solo, expande a checklist + valores de cada tabla (fetch lazy, cacheado en cacheLineas) ----------
	function checklistHtml(tablas) {
		return '<div class="ac-neg-checklist">' + ORDEN_TIPOS.map(function (t) {
			var activo = tablas[t] && tablas[t].length > 0;
			var m = TIPO_META[t];
			return '<span class="ac-neg-check ac-neg-check-' + m.clase + (activo ? ' ac-neg-check-on' : '') + '"><span class="material-symbols-outlined">' + m.icon + '</span>' + m.label + '</span>';
		}).join('') + '</div>';
	}

	// Mismo formatCurr() que registrar.js (la fuente real de estos números): Distribuidor mide en Cajas (sin "$"), Directo en Dólares con separador de miles.
	function formatearNumero(v, formato) {
		var num = Number(v) || 0;
		return formato === 'numero'
			? num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
			: num.toLocaleString('en-US', { style: 'currency', currency: 'USD' });
	}

	// Columnas EXACTAS de cada tabla en acta_pdf.php (replicado, no una variante propia): Rebate = Categoría|meses|Total|Rebate%|Estimado. Cabecera/Ruma = Marca|meses|Total. Percha = Marca|Participación|Max Percha|meses|Total.
	function lineaTablaHtml(tipo, l, meses, formato) {
		var celdaLabel = '<td>' + escapeHtml(l.etiqueta || '—') + '</td>';
		var celdaParticipacion = tipo === 'percha' ? '<td>' + escapeHtml(l.participacion || '—') + '</td>' : '';
		var celdaMaxPercha = tipo === 'percha' ? '<td>' + (l.cantidad_max_percha != null ? l.cantidad_max_percha : '—') + '</td>' : '';
		var celdasMeses = (l.valores || []).map(function (v) { return '<td>' + formatearNumero(v, formato) + '</td>'; }).join('');
		// "Estimado" solo existe en Rebate (fórmula real de acta_pdf.php) — Total es SIEMPRE la suma mensual cruda, igual en las 4 tablas.
		var conEstimado = tipo === 'meta_compra' && l.estimado != null;
		var celdaTotal = '<td class="' + (conEstimado ? '' : 'ac-neg-mes-total') + '">' + formatearNumero(l.total, formato) + '</td>';
		var celdaRebate = tipo === 'meta_compra' && l.rebate_pct != null ? '<td>' + l.rebate_pct + '%</td>' : '';
		var celdaEstimado = conEstimado ? '<td class="ac-neg-mes-total">' + formatearNumero(l.estimado, formato) + '</td>' : '';
		return '<tr>' + celdaLabel + celdaParticipacion + celdaMaxPercha + celdasMeses + celdaTotal + celdaRebate + celdaEstimado + '</tr>';
	}

	// Igual que el <tfoot> real de acta_pdf.php (solo existe en Rebate): suma cada mes hacia abajo + Total/Estimado generales, columna Rebate en "—" (mismo placeholder que usa el PDF ahí, no hay un % general que sumar).
	function footerRebateHtml(lineas, meses, formato) {
		var mesesSuma = meses.map(function (m, i) {
			return lineas.reduce(function (acc, l) { return acc + (Number((l.valores || [])[i]) || 0); }, 0);
		});
		var totalSuma = lineas.reduce(function (acc, l) { return acc + Number(l.total || 0); }, 0);
		var estimadoSuma = lineas.reduce(function (acc, l) { return acc + Number(l.estimado || 0); }, 0);
		var celdasMeses = mesesSuma.map(function (v) { return '<td>' + formatearNumero(v, formato) + '</td>'; }).join('');
		return '<tr><td>Total</td>' + celdasMeses +
			'<td>' + formatearNumero(totalSuma, formato) + '</td>' +
			'<td>—</td>' +
			'<td class="ac-neg-mes-total">' + formatearNumero(estimadoSuma, formato) + '</td></tr>';
	}

	function theadCeldasHtml(t, meses) {
		var labelHead = '<th>' + (t === 'meta_compra' ? 'Categoría' : 'Marca') + '</th>';
		var extraPercha = t === 'percha' ? '<th>Participación</th><th>Max Percha</th>' : '';
		var mesesHead = meses.map(function (mes) { return '<th>' + escapeHtml(mes) + '</th>'; }).join('');
		var colaFinal = t === 'meta_compra' ? '<th>Total</th><th>Rebate</th><th>Estimado</th>' : '<th>Total</th>';
		return labelHead + extraPercha + mesesHead + colaFinal;
	}

	// Tabla real: el navegador alinea las columnas solo entre todas las filas (a diferencia de un grid por fila independiente, que no garantizaba esa alineación).
	function seccionTablaHtml(t, lineas, meses, formato) {
		var m = TIPO_META[t];
		return '<div class="ac-neg-tabla ac-neg-tabla-' + m.clase + '">' +
			'<p class="ac-neg-tabla-titulo"><span class="material-symbols-outlined">' + m.icon + '</span>' + m.label + '</p>' +
			'<div class="ac-neg-tabla-scroll"><table class="ac-neg-tabla-real">' +
			'<thead><tr>' + theadCeldasHtml(t, meses) + '</tr></thead>' +
			'<tbody>' + lineas.map(function (l) { return lineaTablaHtml(t, l, meses, formato); }).join('') + '</tbody>' +
			(t === 'meta_compra' ? '<tfoot>' + footerRebateHtml(lineas, meses, formato) + '</tfoot>' : '') +
			'</table></div>' +
			'</div>';
	}

	// Con un tipo filtrado (Rebate/Cabeceras/Rumas/Perchas) muestra SOLO esa tabla, con TODAS sus filas — nada de las otras 3, ni checklist (ya está implícito en cuál pastilla está activa).
	function detalleActaHtml(detalle) {
		var tablas = (detalle && detalle.tablas) || {};
		var meses = (detalle && detalle.meses) || [];
		var formato = (detalle && detalle.formato) || 'moneda';
		if (estado.filtroDetalle !== 'todas') {
			var tipoUnico = FILTRO_A_TIPO[estado.filtroDetalle];
			var lineas = tablas[tipoUnico] || [];
			return lineas.length ? seccionTablaHtml(tipoUnico, lineas, meses, formato) : '<p class="ac-table-empty">Sin líneas cargadas.</p>';
		}
		var presentes = ORDEN_TIPOS.filter(function (t) { return tablas[t] && tablas[t].length; });
		if (!presentes.length) return checklistHtml(tablas) + '<p class="ac-table-empty">Sin líneas cargadas.</p>';
		return checklistHtml(tablas) + presentes.map(function (t) { return seccionTablaHtml(t, tablas[t], meses, formato); }).join('');
	}

	function actasListHtml(actas) {
		if (!actas.length) {
			var v = SUBFILTROS.filter(function (f) { return f.key === estado.filtroDetalle; })[0];
			return '<div class="ac-table-empty">' + escapeHtml(v ? v.vacioTexto : 'Sin Acuerdos para este filtro.') + '</div>';
		}
		return actas.map(function (a) {
			return '<div class="ac-neg-acta">' +
				'<div class="ac-neg-acta-fila" data-toggle="' + a.id + '">' +
				'<span class="material-symbols-outlined ac-neg-acta-chevron">chevron_right</span>' +
				'<span class="ac-neg-acta-nombre">#' + escapeHtml(a.documento_no) + (a.cliente ? ' - ' + escapeHtml(a.cliente) : '') + '</span>' +
				// &ver=1: abre envuelto en HTML con <title> real (el número de Acta), no crudo — así la pestaña no muestra "descargar_acta_firmada.php".
				'<a class="ac-icon-btn ac-neg-acta-firma" href="getters/descargar_acta_firmada.php?id=' + encodeURIComponent(a.id) + '&ver=1" target="_blank" title="Ver Acta Firmada"><span class="material-symbols-outlined">verified</span></a>' +
				'</div>' +
				'<div class="ac-neg-acta-detalle hidden" id="neg-acta-detalle-' + a.id + '"></div>' +
				'</div>';
		}).join('');
	}

	// Pastillas de tipo, LOCALES a este usuario — mismo estilo que los períodos Q1-Q4 (.ac-seg-pill), no filtran nada fuera de este panel.
	function subFiltrosHtml() {
		return '<div class="ac-seg-pill-group ac-neg-subfiltros">' + SUBFILTROS.map(function (f) {
			var activo = f.key === estado.filtroDetalle ? ' ac-seg-pill-activo' : '';
			return '<button type="button" class="ac-seg-pill' + activo + '" data-subfiltro="' + f.key + '">' + f.label + '</button>';
		}).join('') + '</div>';
	}

	function renderDetalle(filaUsuario, actas) {
		detalleCard.innerHTML =
			'<div class="ac-seg-detalle-header">' +
			avatarRingHtml(filaUsuario.ringCss, filaUsuario.iniciales, true) +
			'<div><h2>' + escapeHtml(filaUsuario.nombre) + '</h2></div>' +
			'</div>' +
			subFiltrosHtml() +
			'<div class="ac-neg-lista">' + actasListHtml(actas) + '</div>';
	}

	function contenidoAcordeonHtml(actas) {
		return subFiltrosHtml() + '<div class="ac-neg-lista">' + actasListHtml(actas) + '</div>';
	}

	// Delegado en raiz: las filas se re-renderizan seguido (detalleCard/acordeón mobile), un listener por fila se perdería en cada render.
	raiz.addEventListener('click', function (e) {
		// Deja que el <a target="_blank"> abra su pestaña normal, sin que además dispare el toggle de la fila (el link vive adentro de .ac-neg-acta-fila).
		if (e.target.closest('.ac-neg-acta-firma')) return;
		var subBtn = e.target.closest('.ac-neg-subfiltros .ac-seg-pill');
		if (subBtn) {
			if (subBtn.dataset.subfiltro === estado.filtroDetalle || !filaActual) return;
			estado.filtroDetalle = subBtn.dataset.subfiltro;
			ultimoFetchKey = null;
			cargarDetalle(filaActual);
			return;
		}
		var fila = e.target.closest('.ac-neg-acta-fila');
		if (!fila) return;
		var id = fila.dataset.toggle;
		var cont = document.getElementById('neg-acta-detalle-' + id);
		if (!cont) return;
		if (!cont.classList.contains('hidden')) {
			cont.classList.add('hidden');
			fila.classList.remove('is-open');
			return;
		}
		fila.classList.add('is-open');
		cont.classList.remove('hidden');
		if (cacheLineas[id]) { cont.innerHTML = detalleActaHtml(cacheLineas[id]); return; }
		cont.innerHTML = '<div class="ac-seg-cargando">Cargando...</div>';
		fetch('getters/negociacion_acta_lineas.php?id=' + encodeURIComponent(id))
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) { cont.innerHTML = '<p class="ac-table-empty">Error al cargar.</p>'; return; }
				cacheLineas[id] = { meses: data.meses, tablas: data.tablas, formato: data.formato };
				cont.innerHTML = detalleActaHtml(cacheLineas[id]);
			})
			.catch(function () { cont.innerHTML = '<p class="ac-table-empty">Error de conexión.</p>'; });
	});

	function renderDetalleInline(actas) {
		var cont = document.getElementById(ID_DETALLE_INLINE);
		if (!cont) return;
		cont.innerHTML = contenidoAcordeonHtml(actas);
	}

	function claveDetalle(usuarioId) {
		return usuarioId + '|' + estado.filtroDetalle + '|' + estado.trimestre + '|' + estado.anio + '|' + estado.canal;
	}

	function cargarDetalle(filaUsuario) {
		if (!filaUsuario) return;
		var miReqId = ++detalleReqId;
		var key = claveDetalle(filaUsuario.id);
		detalleCard.innerHTML = '<div class="ac-seg-cargando">Cargando...</div>';
		var url = 'getters/negociacion_actas_usuario.php?usuario_id=' + filaUsuario.id +
			'&trimestre=' + estado.trimestre + '&anio=' + estado.anio + '&tipo=' + estado.filtroDetalle + '&canal=' + estado.canal;
		fetch(url)
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (miReqId !== detalleReqId) return;
				if (!data.ok) {
					ultimoFetchKey = null;
					ultimoDetalleActas = null;
					detalleCard.innerHTML = '<div class="ac-seg-vacio-detalle"><p>Error al cargar el detalle.</p></div>';
					var contErr = document.getElementById(ID_DETALLE_INLINE);
					if (contErr) contErr.innerHTML = '<p class="ac-table-empty">Error al cargar el detalle.</p>';
					return;
				}
				ultimoFetchKey = key;
				ultimoDetalleActas = data.actas;
				renderDetalle(filaUsuario, data.actas);
				if (esMobile()) renderDetalleInline(data.actas);
			})
			.catch(function () {
				if (miReqId !== detalleReqId) return;
				ultimoFetchKey = null;
				ultimoDetalleActas = null;
				detalleCard.innerHTML = '<div class="ac-seg-vacio-detalle"><p>Error de conexión.</p></div>';
				var contCatch = document.getElementById(ID_DETALLE_INLINE);
				if (contCatch) contCatch.innerHTML = '<p class="ac-table-empty">Error de conexión.</p>';
			});
	}

	function refrescarListaYDetalle() {
		var filasSinBusqueda = computeFilasEquipo(equipoActual);
		var filas = aplicarBusqueda(filasSinBusqueda, estado.busqueda);

		if (!filas.length) {
			var conBusqueda = !!estado.busqueda.trim() && filasSinBusqueda.length > 0;
			renderVacio(conBusqueda ? 'No se encontró a nadie con ese nombre.' : 'No hay Acuerdos firmados todavía en este período.');
			estado.selectedId = null;
			filaActual = null;
			ultimoFetchKey = null;
			return;
		}

		// Sin deseleccionExplicita, auto-selecciona el primero (carga inicial, o el seleccionado ya no está en la lista filtrada). Con deseleccionExplicita se queda en "Equipo completo" aunque se refresque/busque.
		var validIds = filas.map(function (f) { return f.id; });
		if (!estado.deseleccionExplicita && (estado.selectedId == null || validIds.indexOf(estado.selectedId) === -1)) {
			estado.selectedId = filas[0].id;
		}

		renderLista(filas);

		if (estado.selectedId == null) {
			filaActual = null;
			ultimoFetchKey = null;
			detalleCard.innerHTML = '<div class="ac-seg-vacio-detalle"><span class="material-symbols-outlined">group</span><p>Seleccioná un usuario del Equipo para ver su detalle.</p></div>';
			actualizarKpis(statsActual, 'Equipo completo');
			return;
		}

		filaActual = filas.filter(function (f) { return f.id === estado.selectedId; })[0];
		var uRaw = buscarUsuarioEquipo(estado.selectedId);
		if (uRaw) actualizarKpis(uRaw, uRaw.nombre);

		if (claveDetalle(estado.selectedId) !== ultimoFetchKey) {
			cargarDetalle(filaActual);
		}
	}

	function cargarResumen() {
		var miReqId = ++resumenReqId;
		acBotonCargando(actualizarBtn, true);
		Array.prototype.forEach.call(tarjetas, function (c) { acMostrarCargando(c); });
		var url = 'getters/negociacion_resumen.php?trimestre=' + estado.trimestre + '&anio=' + estado.anio + '&canal=' + estado.canal;
		fetch(url)
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (miReqId !== resumenReqId) return;
				if (!data.ok) { mostrarErrorGeneral(); return; }
				equipoActual = data.equipo;
				statsActual = data.stats;
				ultimoFetchKey = null;
				actualizarKpis(statsActual, 'Equipo completo');
				refrescarListaYDetalle();
			})
			.catch(function () {
				if (miReqId !== resumenReqId) return;
				mostrarErrorGeneral();
			})
			.finally(function () {
				if (miReqId !== resumenReqId) return;
				acBotonCargando(actualizarBtn, false);
				Array.prototype.forEach.call(tarjetas, function (c) { acOcultarCargando(c); });
			});
	}

	Array.prototype.forEach.call(trimestreGroup.querySelectorAll('.ac-seg-pill'), function (btn) {
		btn.addEventListener('click', function () {
			if (btn.classList.contains('ac-seg-pill-activo')) return;
			Array.prototype.forEach.call(trimestreGroup.querySelectorAll('.ac-seg-pill'), function (b) { b.classList.remove('ac-seg-pill-activo'); });
			btn.classList.add('ac-seg-pill-activo');
			estado.trimestre = parseInt(btn.dataset.trimestre, 10);
			estado.selectedId = null;
			estado.filtroDetalle = 'todas';
			cargarResumen();
		});
	});
	var canalGroup = document.getElementById('neg-canal-group');
	Array.prototype.forEach.call(canalGroup.querySelectorAll('.ac-seg-pill'), function (btn) {
		btn.addEventListener('click', function () {
			if (btn.classList.contains('ac-seg-pill-activo')) return;
			Array.prototype.forEach.call(canalGroup.querySelectorAll('.ac-seg-pill'), function (b) { b.classList.remove('ac-seg-pill-activo'); });
			btn.classList.add('ac-seg-pill-activo');
			estado.canal = btn.dataset.canal;
			estado.selectedId = null;
			estado.filtroDetalle = 'todas';
			cargarResumen();
		});
	});
	anioSelect.addEventListener('change', function () {
		estado.anio = parseInt(anioSelect.value, 10) || 0;
		estado.selectedId = null;
		estado.filtroDetalle = 'todas';
		cargarResumen();
	});

	buscarInput.addEventListener('input', function () {
		estado.busqueda = buscarInput.value;
		refrescarListaYDetalle();
	});

	actualizarBtn.addEventListener('click', cargarResumen);

	if (kpisResetBtn) kpisResetBtn.addEventListener('click', deseleccionarUsuario);

	cargarResumen();

	window.acNegociacionRefrescar = cargarResumen;
})();

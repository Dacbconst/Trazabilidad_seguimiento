(function () {
	var raiz = document.getElementById('ac-seguimiento');
	if (!raiz) return;

	var trimestreGroup = document.getElementById('seg-trimestre-group');
	var anioSelect      = document.getElementById('seg-anio');
	var filtrosCont     = document.getElementById('seg-filtros');
	var viendoTextoEl   = document.getElementById('seg-viendo-texto');
	var criterioTextoEl = document.getElementById('seg-criterio-texto');
	var ordenTextoEl    = document.getElementById('seg-orden-texto');
	var buscarInput     = document.getElementById('seg-buscar');
	var listaCont       = document.getElementById('seg-equipo-lista');
	var detalleCard     = document.getElementById('seg-detalle-card');
	var tarjetas        = raiz.querySelectorAll('.ac-card');
	var actualizarBtn   = document.getElementById('seg-actualizar');

	var estado = { trimestre: 0, anio: parseInt(anioSelect.value, 10) || 0, filtro: 'todas', busqueda: '', selectedId: null };
	var equipoActual  = [];
	var statsActual   = { total: 0, firmadas: 0, pendientes: 0, vencidas: 0 };
	var ultimoFetchKey = null;
	// Caché del último detalle cargado — sin esto, el acordeón mobile quedaba en "Cargando..." si la lista se re-renderizaba sin fetch nuevo.
	var ultimoDetalleActas = null;

	// Tokens de request en vuelo: evitan que una respuesta vieja pise a una más nueva (click rápido en A luego B, A responde después que B).
	var resumenReqId = 0;
	var detalleReqId = 0;

	// Copy de cada vista, texto corporativo sin jerga interna.
	var VISTAS = {
		todas:      { viendoTexto: 'Todas las Actas', criterioTexto: 'ordenadas por cantidad de Actas generadas', ordenTexto: 'Por cantidad de Actas', colEstado: 'Estado', vacioIcono: 'inbox', vacioTexto: 'No hay Actas generadas todavía en este período.' },
		firmadas:   { viendoTexto: 'Firmadas', criterioTexto: 'ordenadas por cantidad de Actas firmadas', ordenTexto: 'Por cantidad de Actas', colEstado: 'Estado', vacioIcono: 'task_alt', vacioTexto: 'Nadie tiene Actas firmadas todavía en este período.' },
		pendientes: { viendoTexto: 'Pendientes de Firma', criterioTexto: 'ordenadas por proximidad de vencimiento del plazo de firma', ordenTexto: 'Por proximidad de vencimiento', colEstado: 'Vence en', vacioIcono: 'task_alt', vacioTexto: 'Nadie tiene Actas pendientes de firma — el equipo está al día.' },
		vencidas:   { viendoTexto: 'Vencidas', criterioTexto: 'ordenadas por cantidad de Actas vencidas', ordenTexto: 'Por cantidad de Actas', colEstado: 'Estado', vacioIcono: 'celebration', vacioTexto: 'Nadie tiene Actas vencidas en este período.' }
	};

	// En mobile el detalle vive inline (acordeón), no en el panel separado #seg-detalle-card — mismo breakpoint que .ac-seg-grid.
	function esMobile() { return window.matchMedia('(max-width: 900px)').matches; }

	function escapeHtml(texto) {
		var div = document.createElement('div');
		div.textContent = texto == null ? '' : String(texto);
		return div.innerHTML;
	}

	function formatearFecha(iso) {
		if (!iso) return '—';
		var p = iso.split('-');
		return p.length === 3 ? (p[2] + '/' + p[1] + '/' + p[0]) : iso;
	}

	// ---------- Cálculo de urgencia (mismo criterio de 20 días que Historial: ≤5 días urgente, ≤1 crítico) ----------
	function tierPorDias(dias) {
		if (dias <= 1) return 'critico';
		if (dias <= 5) return 'urgente';
		return 'plain';
	}

	// className se aplica junto a ".ac-badge", nunca colores inline: así el badge hereda la animación de pulso de .ac-badge-critico (style.css).
	function badgeParaDias(dias, tier) {
		if (tier === 'critico') return { className: 'ac-badge-critico', text: dias <= 0 ? 'Vence hoy' : 'Vence en 1 día' };
		if (tier === 'urgente') return { className: 'ac-badge-urgente', text: 'Vence en ' + dias + ' días' };
		return { className: '', text: dias + ' días' };
	}

	function badgeParaActa(a) {
		if (a.tiene_firma) return { className: 'ac-badge-ok', text: 'Firmada' };
		if (a.estado === 'vencido') return { className: 'ac-badge-critico', text: 'Vencida' };
		// Antes exigía estado==='generado'/'enviado' — una Acta con estado='firmado' pero sin archivo real (dato inconsistente real, ver resumen_seguimiento_equipo()) caía siempre acá mostrando "Pendiente" seco en vez de la cuenta regresiva real.
		if (a.dias_restantes !== null && a.dias_restantes !== undefined) {
			return badgeParaDias(a.dias_restantes, tierPorDias(a.dias_restantes));
		}
		return { className: 'ac-badge-revisar', text: 'Pendiente' };
	}

	function ringGradient(pctVerde, tier) {
		var urg = tier === 'critico' ? '#ba1a1a' : (tier === 'urgente' ? '#c8531d' : '#c4c5d5');
		if (pctVerde >= 100) return 'conic-gradient(#1e9e5a 0% 100%)';
		if (pctVerde <= 0) return 'conic-gradient(' + urg + ' 0% 100%)';
		return 'conic-gradient(#1e9e5a 0% ' + pctVerde + '%, ' + urg + ' ' + pctVerde + '% 100%)';
	}

	// El anillo también refleja Vencidas, no solo Pendientes (un usuario con 0 pendientes pero vencidas no debe verse "neutral"). `dias_mas_proxima` puede venir null aunque pendientes>0; se chequea con `!= null` para no pasar null a tierPorDias() (pintaría crítico por error).
	function ringDeUsuario(u) {
		var pct = u.total > 0 ? Math.round((u.firmadas / u.total) * 100) : 0;
		var tier = 'plain';
		if (u.vencidas > 0) tier = 'critico';
		else if (u.pendientes > 0 && u.dias_mas_proxima != null) tier = tierPorDias(u.dias_mas_proxima);
		return ringGradient(pct, tier);
	}

	// ---------- Filas de "Equipo" según el filtro de estado activo ---------- `u.iniciales` viene calculado en el servidor (inicialesUsuario()): recalcularlo acá con otra regex divergía para nombres con punto.
	function computeFilasBase(equipo, filtro) {
		return equipo.map(function (u) {
			var f = { id: u.usuario_id, nombre: u.nombre, iniciales: u.iniciales, ringCss: ringDeUsuario(u) };
			if (filtro === 'firmadas') {
				f.incluir = u.firmadas > 0;
				if (f.incluir) {
					f.sortKey = -u.firmadas;
					f.badgeClass = 'ac-badge-ok'; f.badgeText = u.firmadas + (u.firmadas === 1 ? ' Firmada' : ' Firmadas');
					f.metaLabel = 'de ' + u.total + ' Actas en total';
				}
			} else if (filtro === 'pendientes') {
				f.incluir = u.pendientes > 0;
				if (f.incluir) {
					var tieneDias = u.dias_mas_proxima != null;
					f.sortKey = tieneDias ? u.dias_mas_proxima : 999999;
					var b = tieneDias ? badgeParaDias(u.dias_mas_proxima, tierPorDias(u.dias_mas_proxima)) : { className: '', text: 'Sin fecha' };
					f.badgeClass = b.className; f.badgeText = b.text;
					f.metaLabel = u.pendientes + (u.pendientes === 1 ? ' pendiente' : ' pendientes');
				}
			} else if (filtro === 'vencidas') {
				f.incluir = u.vencidas > 0;
				if (f.incluir) {
					f.sortKey = -u.vencidas;
					f.badgeClass = 'ac-badge-critico'; f.badgeText = u.vencidas + (u.vencidas === 1 ? ' Vencida' : ' Vencidas');
					f.metaLabel = 'de ' + u.total + ' Actas en total';
				}
			} else { // todas
				f.incluir = true;
				f.sortKey = -u.total;
				f.badgeClass = ''; f.badgeText = u.total + (u.total === 1 ? ' Acta' : ' Actas');
				f.metaLabel = u.firmadas + ' firmada' + (u.firmadas === 1 ? '' : 's') + ' · ' + u.pendientes + ' pendiente' + (u.pendientes === 1 ? '' : 's');
			}
			return f;
		}).filter(function (f) { return f.incluir; }).sort(function (a, b) { return a.sortKey - b.sortKey; });
	}

	function aplicarBusqueda(filas, busqueda) {
		var q = (busqueda || '').trim().toLowerCase();
		if (!q) return filas;
		return filas.filter(function (f) { return f.nombre.toLowerCase().indexOf(q) !== -1; });
	}

	// ---------- Render: filtros (contadores + estado activo) ----------
	function actualizarBotonesFiltro() {
		Array.prototype.forEach.call(filtrosCont.querySelectorAll('.ac-seg-filtro'), function (btn) {
			var key = btn.dataset.filtro;
			var activo = key === estado.filtro;
			btn.classList.toggle('ac-seg-filtro-activo', activo);
			btn.style.background = activo ? btn.dataset.color : '';
			btn.style.color = activo ? '#ffffff' : '';
			// El backend manda `total`, no `todas`: mapeo explícito acá, si no el botón "Todas" quedaba siempre en 0.
			var valor = key === 'todas' ? statsActual.total : statsActual[key];
			btn.querySelector('[data-valor="' + key + '"]').textContent = valor != null ? valor : 0;
		});
	}

	function actualizarTextosVista() {
		var v = VISTAS[estado.filtro];
		viendoTextoEl.textContent = v.viendoTexto;
		criterioTextoEl.textContent = v.criterioTexto;
		ordenTextoEl.textContent = v.ordenTexto;
	}

	// ---------- Render: lista de Equipo ----------
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

	// Contenedor inline del acordeón mobile, id fijo: uno solo a la vez, mismo criterio "un detalle visible" que el panel separado de desktop.
	var ID_DETALLE_INLINE = 'seg-fila-detalle-inline';
	function inlineDetalleHtml() {
		return '<div class="ac-seg-fila-detalle-inline" id="' + ID_DETALLE_INLINE + '"><div class="ac-seg-cargando">Cargando...</div></div>';
	}

	function renderLista(filas) {
		var mobile = esMobile();
		listaCont.innerHTML = filas.map(function (f) {
			var sel = f.id === estado.selectedId ? ' is-selected' : '';
			var filaHtml = '<div class="ac-seg-fila-usuario' + sel + '" data-id="' + f.id + '">' +
				avatarRingHtml(f.ringCss, f.iniciales, false) +
				'<div class="ac-seg-fila-info"><p class="ac-user-name">' + escapeHtml(f.nombre) + '</p><p class="ac-seg-fila-meta">' + escapeHtml(f.metaLabel) + '</p></div>' +
				badgeHtml(f.badgeClass, f.badgeText) +
				'<span class="material-symbols-outlined ac-seg-fila-chevron">expand_more</span>' +
				'</div>';
			// Solo el asesor seleccionado, y solo en mobile, lleva el acordeón ya en el HTML inicial (cacheado, o "Cargando...").
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
				var mobileAhora = esMobile();
				if (id === estado.selectedId) {
					// En mobile, tocar el mismo asesor ya expandido lo colapsa; en desktop no hace nada, el panel ya muestra a ese usuario.
					if (mobileAhora) {
						estado.selectedId = null;
						row.classList.remove('is-selected');
						var abierto = document.getElementById(ID_DETALLE_INLINE);
						if (abierto) abierto.remove();
					}
					return;
				}
				Array.prototype.forEach.call(listaCont.querySelectorAll('.ac-seg-fila-usuario'), function (r) {
					r.classList.toggle('is-selected', parseInt(r.dataset.id, 10) === id);
				});
				if (mobileAhora) {
					var viejo = document.getElementById(ID_DETALLE_INLINE);
					if (viejo) viejo.remove();
					row.insertAdjacentHTML('afterend', inlineDetalleHtml());
				}
				estado.selectedId = id;
				var filaSel = filas.filter(function (f) { return f.id === id; })[0];
				cargarDetalle(filaSel);
			});
		});
	}

	function renderVacio(icono, texto) {
		var html = '<div class="ac-seg-vacio"><span class="material-symbols-outlined">' + icono + '</span><p>' + escapeHtml(texto) + '</p></div>';
		listaCont.innerHTML = html;
		detalleCard.innerHTML = '<div class="ac-seg-vacio-detalle"><span class="material-symbols-outlined">' + icono + '</span><p>' + escapeHtml(texto) + '</p></div>';
	}

	// Estado de error genérico: a diferencia de un toast que desaparece solo, deja el panel en "no se pudo cargar" en vez de trabado en "Cargando...".
	function mostrarErrorGeneral() {
		mostrarToast('Error de conexión al cargar el seguimiento.', 'error');
		listaCont.innerHTML = '<div class="ac-seg-vacio"><span class="material-symbols-outlined">error</span><p>No se pudo cargar el equipo. Actualizá la página para reintentar.</p></div>';
		detalleCard.innerHTML = '<div class="ac-seg-vacio-detalle"><span class="material-symbols-outlined">error</span><p>No se pudo cargar el detalle.</p></div>';
	}

	// ---------- Render: panel de detalle (desktop) / acordeón inline (mobile), mismo contenido ---------- Link a la Acta firmada, mismo criterio visual que el link del Documento (#ac-seg-doc/-link) — la fecha es el texto del link. descargar_acta_firmada.php ya sirve el archivo con Content-Disposition:inline, así que target="_blank" lo MUESTRA, no lo descarga.
	function firmadaCeldaHtml(a) {
		if (!a.tiene_firma) return '<span class="ac-text-center ac-field-hint">—</span>';
		var fecha = a.acta_firmada_subido_en ? formatearFecha(a.acta_firmada_subido_en.split(' ')[0]) : '—';
		return '<span class="ac-text-center"><a class="ac-seg-doc ac-seg-doc-link" href="getters/descargar_acta_firmada.php?id=' + encodeURIComponent(a.id) + '" target="_blank" title="Ver Acta Firmada">' + escapeHtml(fecha) + '</a></span>';
	}

	function filasActasHtml(actas) {
		return actas.length
			? actas.map(function (a) {
				var b = badgeParaActa(a);
				// Hipervínculo directo al PDF (mismo endpoint que el resto de la app) con `download`, sin pasar por el modal de Historial.
				return '<div class="ac-seg-detalle-fila">' +
					'<a class="ac-seg-doc ac-seg-doc-link" href="getters/generar_acta_pdf.php?id=' + encodeURIComponent(a.id) + '" download title="Descargar PDF">#' + escapeHtml(a.documento_no) + '</a>' +
					'<span>' + escapeHtml(a.pos_name || '—') + '</span>' +
					'<span class="ac-text-right ac-tabular">' + escapeHtml(formatearFecha(a.fecha_generacion)) + '</span>' +
					'<span class="ac-text-center">' + badgeHtml(b.className, b.text) + '</span>' +
					firmadaCeldaHtml(a) +
					'</div>';
			}).join('')
			: '<div class="ac-table-empty">Sin Actas para este filtro.</div>';
	}

	// Encabezado compartido por el panel de desktop y el acordeón mobile — 5ta columna "Firmada" (fecha, o "—" si no tiene).
	function theadActasHtml(v) {
		return '<div class="ac-seg-detalle-thead"><span>Documento</span><span>Distribuidor</span><span class="ac-text-right">Fecha</span><span class="ac-text-center">' + escapeHtml(v.colEstado) + '</span><span class="ac-text-center">Firmada</span></div>';
	}

	function renderDetalle(filaUsuario, actas) {
		var v = VISTAS[estado.filtro];
		detalleCard.innerHTML =
			'<div class="ac-seg-detalle-header">' +
			avatarRingHtml(filaUsuario.ringCss, filaUsuario.iniciales, true) +
			'<div><span class="ac-seg-eyebrow">' + escapeHtml(v.viendoTexto) + '</span><h2>' + escapeHtml(filaUsuario.nombre) + '</h2></div>' +
			'<div class="ac-seg-detalle-stat"><span class="ac-seg-detalle-stat-num">' + actas.length + '</span><span class="ac-seg-detalle-stat-label">en esta vista</span></div>' +
			'</div>' +
			theadActasHtml(v) +
			'<div class="ac-seg-detalle-body">' + filasActasHtml(actas) + '</div>';
	}

	// Sin la cabecera de avatar/nombre (redundante con la fila del acordeón justo arriba), solo el thead + las Actas.
	function contenidoAcordeonHtml(actas) {
		var v = VISTAS[estado.filtro];
		return theadActasHtml(v) + '<div class="ac-seg-detalle-body">' + filasActasHtml(actas) + '</div>';
	}

	function renderDetalleInline(actas) {
		var cont = document.getElementById(ID_DETALLE_INLINE);
		if (!cont) return;
		cont.innerHTML = contenidoAcordeonHtml(actas);
	}

	// Clave del detalle actualmente cargado — usuario + filtro + período.
	function claveDetalle(usuarioId) {
		return usuarioId + '|' + estado.filtro + '|' + estado.trimestre + '|' + estado.anio;
	}

	// ultimoFetchKey se actualiza DESPUÉS de confirmar éxito: si se marcaba de entrada y el fetch fallaba, un refresco posterior saltaba el reintento. `miReqId` evita que una respuesta vieja pise el panel ya actualizado por una más nueva.
	function cargarDetalle(filaUsuario) {
		if (!filaUsuario) return;
		var miReqId = ++detalleReqId;
		var key = claveDetalle(filaUsuario.id);
		detalleCard.innerHTML = '<div class="ac-seg-cargando">Cargando...</div>';
		var url = 'getters/seguimiento_actas_usuario.php?usuario_id=' + filaUsuario.id +
			'&trimestre=' + estado.trimestre + '&anio=' + estado.anio + '&tipo=' + estado.filtro;
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
				// El panel de desktop se actualiza siempre (aunque esté oculto en mobile), para que agrandar la ventana sin recargar no lo deje viejo.
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

	// ---------- Orquestación: filtro/búsqueda cambian todo en memoria, sin red salvo para el detalle del usuario efectivamente seleccionado ----------
	function refrescarListaYDetalle() {
		actualizarTextosVista();
		var filasSinBusqueda = computeFilasBase(equipoActual, estado.filtro);
		var filas = aplicarBusqueda(filasSinBusqueda, estado.busqueda);

		if (!filas.length) {
			var conBusqueda = !!estado.busqueda.trim() && filasSinBusqueda.length > 0;
			var icono = conBusqueda ? 'person_search' : VISTAS[estado.filtro].vacioIcono;
			var texto = conBusqueda ? 'No se encontró a nadie con ese nombre.' : VISTAS[estado.filtro].vacioTexto;
			renderVacio(icono, texto);
			estado.selectedId = null;
			ultimoFetchKey = null;
			return;
		}

		// == null (no !estado.selectedId) a propósito: "falsy" también atrapa 0, que podría ser un id sintético real algún día.
		var validIds = filas.map(function (f) { return f.id; });
		if (estado.selectedId == null || validIds.indexOf(estado.selectedId) === -1) {
			estado.selectedId = filas[0].id;
		}

		renderLista(filas);

		if (claveDetalle(estado.selectedId) !== ultimoFetchKey) {
			cargarDetalle(filas.filter(function (f) { return f.id === estado.selectedId; })[0]);
		}
	}

	function cargarResumen() {
		var miReqId = ++resumenReqId;
		// Mismo feedback de "Actualizar" que Historial: ícono gira + overlay sobre las tarjetas mientras el fetch está en curso.
		acBotonCargando(actualizarBtn, true);
		Array.prototype.forEach.call(tarjetas, function (c) { acMostrarCargando(c); });
		var url = 'getters/seguimiento_resumen.php?trimestre=' + estado.trimestre + '&anio=' + estado.anio;
		fetch(url)
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (miReqId !== resumenReqId) return;
				if (!data.ok) { mostrarErrorGeneral(); return; }
				equipoActual = data.equipo;
				statsActual = data.stats;
				ultimoFetchKey = null;
				actualizarBotonesFiltro();
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

	// ---------- Filtro de período ----------
	Array.prototype.forEach.call(trimestreGroup.querySelectorAll('.ac-seg-pill'), function (btn) {
		btn.addEventListener('click', function () {
			if (btn.classList.contains('ac-seg-pill-activo')) return;
			Array.prototype.forEach.call(trimestreGroup.querySelectorAll('.ac-seg-pill'), function (b) { b.classList.remove('ac-seg-pill-activo'); });
			btn.classList.add('ac-seg-pill-activo');
			estado.trimestre = parseInt(btn.dataset.trimestre, 10);
			estado.selectedId = null;
			cargarResumen();
		});
	});
	anioSelect.addEventListener('change', function () {
		estado.anio = parseInt(anioSelect.value, 10) || 0;
		estado.selectedId = null;
		cargarResumen();
	});

	// ---------- Filtro de estado (Todas/Firmadas/Pendientes/Vencidas) ----------
	Array.prototype.forEach.call(filtrosCont.querySelectorAll('.ac-seg-filtro'), function (btn) {
		btn.addEventListener('click', function () {
			if (btn.dataset.filtro === estado.filtro) return;
			estado.filtro = btn.dataset.filtro;
			estado.selectedId = null;
			actualizarBotonesFiltro();
			refrescarListaYDetalle();
		});
	});

	// ---------- Buscador de usuario (en memoria, sin red) ----------
	buscarInput.addEventListener('input', function () {
		estado.busqueda = buscarInput.value;
		refrescarListaYDetalle();
	});

	// ---------- Botón "Actualizar" (mismo diseño/comportamiento que hist-actualizar) ----------
	actualizarBtn.addEventListener('click', cargarResumen);

	cargarResumen();

	// Expuesto para que index.php refresque este módulo al entrar por el sidebar (mismo patrón que window.acHistorialRefrescar/etc.).
	window.acSeguimientoRefrescar = cargarResumen;
})();

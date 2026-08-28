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

	var estado = { trimestre: 0, anio: parseInt(anioSelect.value, 10) || 0, filtro: 'todas', busqueda: '', selectedId: null };
	var equipoActual  = [];
	var statsActual   = { total: 0, firmadas: 0, pendientes: 0, vencidas: 0 };
	var ultimoFetchKey = null;

	// Copy de cada vista — texto corporativo, sin jerga interna ("ordenado
	// por cantidad" se rechazó explícitamente por sonar poco profesional).
	var VISTAS = {
		todas:      { viendoTexto: 'Todas las Actas', criterioTexto: 'ordenadas por cantidad de Actas generadas', ordenTexto: 'Por cantidad de Actas', colEstado: 'Estado', vacioIcono: 'inbox', vacioTexto: 'No hay Actas generadas todavía en este período.' },
		firmadas:   { viendoTexto: 'Firmadas', criterioTexto: 'ordenadas por cantidad de Actas firmadas', ordenTexto: 'Por cantidad de Actas', colEstado: 'Estado', vacioIcono: 'task_alt', vacioTexto: 'Nadie tiene Actas firmadas todavía en este período.' },
		pendientes: { viendoTexto: 'Pendientes de Firma', criterioTexto: 'ordenadas por proximidad de vencimiento del plazo de firma', ordenTexto: 'Por proximidad de vencimiento', colEstado: 'Vence en', vacioIcono: 'task_alt', vacioTexto: 'Nadie tiene Actas pendientes de firma — el equipo está al día.' },
		vencidas:   { viendoTexto: 'Vencidas', criterioTexto: 'ordenadas por cantidad de Actas vencidas', ordenTexto: 'Por cantidad de Actas', colEstado: 'Estado', vacioIcono: 'celebration', vacioTexto: 'Nadie tiene Actas vencidas en este período.' }
	};

	function escapeHtml(texto) {
		var div = document.createElement('div');
		div.textContent = texto == null ? '' : String(texto);
		return div.innerHTML;
	}

	function inicialesDe(nombre) {
		var partes = nombre.trim().split(/\s+/).filter(Boolean);
		if (partes.length >= 2) return (partes[0].charAt(0) + partes[1].charAt(0)).toUpperCase();
		return nombre.substring(0, 2).toUpperCase();
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

	function badgeParaDias(dias, tier) {
		if (tier === 'critico') return { bg: '#ffdad6', color: '#93000a', text: dias <= 0 ? 'Vence hoy' : 'Vence en 1 día' };
		if (tier === 'urgente') return { bg: '#ffdbce', color: '#802a00', text: 'Vence en ' + dias + ' días' };
		return { bg: '#eeedf7', color: '#444653', text: dias + ' días' };
	}

	function badgeParaActa(a) {
		if (a.tiene_firma) return { bg: '#d7f2db', color: '#1e5c26', text: 'Firmada' };
		if (a.estado === 'vencido') return { bg: '#ffdad6', color: '#93000a', text: 'Vencida' };
		var enPlazo = a.estado === 'generado' || a.estado === 'enviado';
		if (enPlazo && a.dias_restantes !== null && a.dias_restantes !== undefined) {
			return badgeParaDias(a.dias_restantes, tierPorDias(a.dias_restantes));
		}
		return { bg: '#eeedf7', color: '#444653', text: 'Pendiente' };
	}

	function ringGradient(pctVerde, tier) {
		var urg = tier === 'critico' ? '#ba1a1a' : (tier === 'urgente' ? '#c8531d' : '#c4c5d5');
		if (pctVerde >= 100) return 'conic-gradient(#1e9e5a 0% 100%)';
		if (pctVerde <= 0) return 'conic-gradient(' + urg + ' 0% 100%)';
		return 'conic-gradient(#1e9e5a 0% ' + pctVerde + '%, ' + urg + ' ' + pctVerde + '% 100%)';
	}

	function ringDeUsuario(u) {
		var pct = u.total > 0 ? Math.round((u.firmadas / u.total) * 100) : 0;
		var tier = u.pendientes > 0 ? tierPorDias(u.dias_mas_proxima) : 'plain';
		return ringGradient(pct, tier);
	}

	// ---------- Filas de "Equipo" según el filtro de estado activo ----------
	function computeFilasBase(equipo, filtro) {
		return equipo.map(function (u) {
			var f = { id: u.usuario_id, nombre: u.nombre, iniciales: inicialesDe(u.nombre), ringCss: ringDeUsuario(u) };
			if (filtro === 'firmadas') {
				f.incluir = u.firmadas > 0; f.sortKey = -u.firmadas;
				f.badgeBg = '#d7f2db'; f.badgeColor = '#1e5c26'; f.badgeText = u.firmadas + (u.firmadas === 1 ? ' Firmada' : ' Firmadas');
				f.metaLabel = 'de ' + u.total + ' Actas en total';
			} else if (filtro === 'pendientes') {
				f.incluir = u.pendientes > 0; f.sortKey = u.pendientes > 0 ? u.dias_mas_proxima : 999999;
				var b = u.pendientes > 0 ? badgeParaDias(u.dias_mas_proxima, tierPorDias(u.dias_mas_proxima)) : { bg: '', color: '', text: '' };
				f.badgeBg = b.bg; f.badgeColor = b.color; f.badgeText = b.text;
				f.metaLabel = 'de ' + u.total + ' Actas en total';
			} else if (filtro === 'vencidas') {
				f.incluir = u.vencidas > 0; f.sortKey = -u.vencidas;
				f.badgeBg = '#ffdad6'; f.badgeColor = '#93000a'; f.badgeText = u.vencidas + (u.vencidas === 1 ? ' Vencida' : ' Vencidas');
				f.metaLabel = 'de ' + u.total + ' Actas en total';
			} else { // todas
				f.incluir = true; f.sortKey = -u.total;
				f.badgeBg = '#d3e4fe'; f.badgeColor = '#0b1c30'; f.badgeText = u.total + (u.total === 1 ? ' Acta' : ' Actas');
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
			// El backend manda `total`, no `todas` (statsActual = {total,
			// firmadas, pendientes, vencidas}) — mapeo explícito acá, si no
			// el botón "Todas" quedaba siempre en 0 (bug real, encontrado
			// por el usuario probando en el navegador).
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

	function renderLista(filas) {
		listaCont.innerHTML = filas.map(function (f) {
			var sel = f.id === estado.selectedId ? ' is-selected' : '';
			return '<div class="ac-seg-fila-usuario' + sel + '" data-id="' + f.id + '">' +
				avatarRingHtml(f.ringCss, f.iniciales, false) +
				'<div class="ac-seg-fila-info"><p class="ac-user-name">' + escapeHtml(f.nombre) + '</p><p class="ac-seg-fila-meta">' + escapeHtml(f.metaLabel) + '</p></div>' +
				'<span class="ac-badge" style="background:' + f.badgeBg + ';color:' + f.badgeColor + ';">' + escapeHtml(f.badgeText) + '</span>' +
				'</div>';
		}).join('');
		Array.prototype.forEach.call(listaCont.querySelectorAll('.ac-seg-fila-usuario'), function (row) {
			row.addEventListener('click', function () {
				var id = parseInt(row.dataset.id, 10);
				if (id === estado.selectedId) return;
				estado.selectedId = id;
				Array.prototype.forEach.call(listaCont.querySelectorAll('.ac-seg-fila-usuario'), function (r) {
					r.classList.toggle('is-selected', parseInt(r.dataset.id, 10) === id);
				});
				var filaSel = computeFilasBase(equipoActual, estado.filtro).filter(function (f) { return f.id === id; })[0];
				cargarDetalle(filaSel);
			});
		});
	}

	function renderVacio(icono, texto) {
		var html = '<div class="ac-seg-vacio"><span class="material-symbols-outlined">' + icono + '</span><p>' + escapeHtml(texto) + '</p></div>';
		listaCont.innerHTML = html;
		detalleCard.innerHTML = '<div class="ac-seg-vacio-detalle"><span class="material-symbols-outlined">' + icono + '</span><p>' + escapeHtml(texto) + '</p></div>';
	}

	// ---------- Render: panel de detalle ----------
	function renderDetalle(filaUsuario, actas) {
		var v = VISTAS[estado.filtro];
		var filasHtml = actas.length
			? actas.map(function (a) {
				var b = badgeParaActa(a);
				return '<div class="ac-seg-detalle-fila">' +
					'<span class="ac-seg-doc">#' + escapeHtml(a.documento_no) + '</span>' +
					'<span>' + escapeHtml(a.pos_name || '—') + '</span>' +
					'<span class="ac-text-right ac-tabular">' + formatearFecha(a.fecha_generacion) + '</span>' +
					'<span class="ac-text-center"><span class="ac-badge" style="background:' + b.bg + ';color:' + b.color + ';">' + escapeHtml(b.text) + '</span></span>' +
					'</div>';
			}).join('')
			: '<div class="ac-table-empty">Sin Actas para este filtro.</div>';

		detalleCard.innerHTML =
			'<div class="ac-seg-detalle-header">' +
			avatarRingHtml(filaUsuario.ringCss, filaUsuario.iniciales, true) +
			'<div><span class="ac-seg-eyebrow">' + escapeHtml(v.viendoTexto) + '</span><h2>' + escapeHtml(filaUsuario.nombre) + '</h2></div>' +
			'<div class="ac-seg-detalle-stat"><span class="ac-seg-detalle-stat-num">' + actas.length + '</span><span class="ac-seg-detalle-stat-label">en esta vista</span></div>' +
			'</div>' +
			'<div class="ac-seg-detalle-thead"><span>Documento</span><span>Distribuidor</span><span class="ac-text-right">Fecha</span><span class="ac-text-center">' + escapeHtml(v.colEstado) + '</span></div>' +
			'<div class="ac-seg-detalle-body">' + filasHtml + '</div>';
	}

	function cargarDetalle(filaUsuario) {
		if (!filaUsuario) return;
		detalleCard.innerHTML = '<div class="ac-seg-cargando">Cargando...</div>';
		var url = 'getters/seguimiento_actas_usuario.php?usuario_id=' + filaUsuario.id +
			'&trimestre=' + estado.trimestre + '&anio=' + estado.anio + '&tipo=' + estado.filtro;
		fetch(url)
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) { detalleCard.innerHTML = '<div class="ac-seg-vacio-detalle"><p>Error al cargar el detalle.</p></div>'; return; }
				renderDetalle(filaUsuario, data.actas);
			})
			.catch(function () {
				detalleCard.innerHTML = '<div class="ac-seg-vacio-detalle"><p>Error de conexión.</p></div>';
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

		var validIds = filas.map(function (f) { return f.id; });
		if (!estado.selectedId || validIds.indexOf(estado.selectedId) === -1) {
			estado.selectedId = filas[0].id;
		}

		renderLista(filas);

		var key = estado.selectedId + '|' + estado.filtro + '|' + estado.trimestre + '|' + estado.anio;
		if (key !== ultimoFetchKey) {
			ultimoFetchKey = key;
			cargarDetalle(filas.filter(function (f) { return f.id === estado.selectedId; })[0]);
		}
	}

	function cargarResumen() {
		Array.prototype.forEach.call(tarjetas, function (c) { acMostrarCargando(c); });
		var url = 'getters/seguimiento_resumen.php?trimestre=' + estado.trimestre + '&anio=' + estado.anio;
		fetch(url)
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) return;
				equipoActual = data.equipo;
				statsActual = data.stats;
				ultimoFetchKey = null;
				actualizarBotonesFiltro();
				refrescarListaYDetalle();
			})
			.catch(function () {
				mostrarToast('Error de conexión al cargar el seguimiento.', 'error');
			})
			.finally(function () {
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

	cargarResumen();

	// Expuesto para que index.php refresque este módulo al entrar por el
	// sidebar (mismo patrón que window.acHistorialRefrescar/etc.).
	window.acSeguimientoRefrescar = cargarResumen;
})();

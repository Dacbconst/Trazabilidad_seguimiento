// Campanita de notificaciones: "Actas Asignadas" (precargadas pendientes) y "Actas Por Firmar" (plazo de 20 días, ver alertas_firma.php).
// Sin tiempo real (sin Firebase): la sensación de en vivo la da el refresco manual + el automático en cada cambio de módulo.
(function () {
	var btn = document.getElementById('acAlertasBtn');
	var badge = document.getElementById('acAlertasBadge');
	var panel = document.getElementById('acAlertasPanel');
	var refrescarBtn = document.getElementById('acAlertasRefrescarBtn');
	var tabAsignadas = document.getElementById('acAlertasTabAsignadas');
	var tabFirmar = document.getElementById('acAlertasTabFirmar');
	var bodyAsignadas = document.getElementById('acAlertasBodyAsignadas');
	var bodyFirmar = document.getElementById('acAlertasBodyFirmar');
	if (!btn || !panel || !bodyAsignadas || !bodyFirmar) return;

	// "Vence en N días" no decía QUÉ vence (podía leerse como que se cae el acuerdo, no que es la ventana para subir la firma).
	function diasCortos(dias) {
		dias = parseInt(dias, 10);
		if (dias <= 0) return 'hoy';
		if (dias === 1) return '1 día';
		return dias + ' días';
	}
	function accionTexto(dias) {
		return 'Subí la firma — ' + diasCortos(dias);
	}

	// Vistas: sin backend real, se guarda en localStorage — estado por navegador, no por usuario. Clave: id real o pos_id+trimestre+año.
	var VISTAS_KEY = 'ac_notif_vistas';
	var vistas = {};
	(function cargarVistas() {
		try {
			var raw = localStorage.getItem(VISTAS_KEY);
			(raw ? JSON.parse(raw) : []).forEach(function (k) { vistas[k] = true; });
		} catch (e) { /* localStorage no disponible (privado/bloqueado) — arranca todo como no visto, sin romper nada. */ }
	})();
	function guardarVistas() {
		try { localStorage.setItem(VISTAS_KEY, JSON.stringify(Object.keys(vistas))); } catch (e) {}
	}
	function claveFirmar(a) { return 'firmar:' + a.id; }
	// Incluye actualizado_en: si el cliente/trimestre se resube o reasigna, la clave cambia y vuelve a marcarse como no visto.
	function claveAsignada(p) { return 'asignadas:' + p.pos_id + ':' + p.trimestre + ':' + p.anio + ':' + p.actualizado_en; }
	// Último fetch en memoria: marcarTodoVisto() lo necesita sin pedir la data de nuevo.
	var ultimasMias = [];
	var ultimasPrecargadas = [];
	// Evita que una respuesta vieja pise a una más nueva (3 caminos de refresco a la vez pueden superponerse).
	var alertasReqId = 0;

	function irAHistorial(id) {
		var link = document.querySelector('.ac-sidebar-nav a[href="#sec-historial"]');
		if (link) link.click();
		cerrarPanel();
	}

	// Actas Precargadas: a diferencia de "Actas Por Firmar", el click va directo a Registrar con el formulario ya cargado.
	function irARegistrarConPrecarga(posId, trimestre, anio) {
		var link = document.querySelector('.ac-sidebar-nav a[href="#sec-registrar"]');
		if (link) link.click();
		cerrarPanel();
		if (window.acRegistrarCargarPrecarga) window.acRegistrarCargarPrecarga(posId, trimestre, anio);
	}

	// ---------- Pestaña "Actas Asignadas" (activity feed) ----------
	function renderAsignadas(precargadas) {
		if (!precargadas.length) {
			bodyAsignadas.innerHTML = '<p class="ac-alertas-vacio">No tenés Actas asignadas por completar.</p>';
			return;
		}
		bodyAsignadas.innerHTML = precargadas.map(function (p) {
			var puntito = vistas[claveAsignada(p)] ? '' : '<span class="ac-notif-dot" title="No visto"></span>';
			return '<div class="ac-activity-item" data-pos-id="' + p.pos_id + '" data-trimestre="' + p.trimestre + '" data-anio="' + p.anio + '">' +
				'<span class="ac-activity-icon"><span class="material-symbols-outlined">assignment</span></span>' +
				'<span class="ac-activity-texto">' +
					'<span class="ac-activity-titulo">' + puntito + escapeHtml(p.cliente_excel) + '</span>' +
					'<span class="ac-activity-meta">Q' + p.trimestre + ' ' + p.anio + ' · ' + p.categorias + ' categoría' + (p.categorias == 1 ? '' : 's') + ' por completar</span>' +
				'</span>' +
				'</div>';
		}).join('');
		Array.prototype.forEach.call(bodyAsignadas.querySelectorAll('.ac-activity-item'), function (el) {
			el.addEventListener('click', function () {
				irARegistrarConPrecarga(el.dataset.posId, parseInt(el.dataset.trimestre, 10), parseInt(el.dataset.anio, 10));
			});
		});
	}

	// ---------- Pestaña "Actas Por Firmar" (cajas de alerta) ----------
	function renderFirmar(mias) {
		if (!mias.length) {
			bodyFirmar.innerHTML = '<p class="ac-alertas-vacio">No tenés Actas por vencer en los próximos días.</p>';
			return;
		}
		bodyFirmar.innerHTML = mias.map(function (a) {
			var critico = parseInt(a.dias_restantes, 10) <= 1;
			var puntito = vistas[claveFirmar(a)] ? '' : '<span class="ac-notif-dot" title="No visto"></span>';
			return '<div class="ac-alertbox ' + (critico ? 'ac-alertbox-critica' : 'ac-alertbox-urgente') + '" data-id="' + a.id + '">' +
				'<span class="material-symbols-outlined ac-alertbox-icono">' + (critico ? 'error' : 'schedule') + '</span>' +
				'<span class="ac-alertbox-texto">' +
					'<span class="ac-alertbox-titulo">' + puntito + '#' + escapeHtml(a.documento_no) + '</span>' +
					'<span class="ac-alertbox-desc">' + accionTexto(a.dias_restantes) + '</span>' +
				'</span>' +
				'</div>';
		}).join('');
		Array.prototype.forEach.call(bodyFirmar.querySelectorAll('.ac-alertbox'), function (el) {
			el.addEventListener('click', function () { irAHistorial(el.dataset.id); });
		});
	}

	function escapeHtml(texto) {
		var div = document.createElement('div');
		div.textContent = texto == null ? '' : String(texto);
		return div.innerHTML;
	}

	// ---------- Pestañas ----------
	function activarTab(tab) {
		var esAsignadas = tab === 'asignadas';
		tabAsignadas.classList.toggle('ac-alertas-tab-activa', esAsignadas);
		tabFirmar.classList.toggle('ac-alertas-tab-activa', !esAsignadas);
		bodyAsignadas.hidden = !esAsignadas;
		bodyFirmar.hidden = esAsignadas;
	}
	tabAsignadas.addEventListener('click', function () { activarTab('asignadas'); });
	tabFirmar.addEventListener('click', function () { activarTab('firmar'); });

	// Notificación al iniciar sesión: reusa el toast global (toast.js), se dispara una sola vez con un flag booleano en memoria.
	var primeraCarga = true;
	function avisarAlInicio(mias, precargadas) {
		// 2 toasts separados (no combinados): son acciones distintas. El de precargadas va primero, es la más nueva de las dos.
		if (precargadas.length && window.mostrarToast) {
			var mensajePrecarga = precargadas.length === 1
				? '1 Acta asignada por completar — ' + precargadas[0].cliente_excel
				: precargadas.length + ' Actas asignadas por completar';
			window.mostrarToast(mensajePrecarga, 'warning');
		}
		if (!mias.length) return;
		var masUrgente = mias[0]; // ya vienen ordenados ASC por dias_restantes (ver listar_alertas_firma_propias()).
		var hayCritico = mias.some(function (a) { return parseInt(a.dias_restantes, 10) <= 1; });
		var mensaje = mias.length === 1
			? '1 Acta por vencer — #' + masUrgente.documento_no + ': ' + accionTexto(masUrgente.dias_restantes)
			: mias.length + ' Actas por vencer — la más próxima, #' + masUrgente.documento_no + ': ' + accionTexto(masUrgente.dias_restantes);
		if (window.mostrarToast) window.mostrarToast(mensaje, hayCritico ? 'error' : 'warning');
	}

	// El badge cuenta NO VISTOS, no el total — un ítem visto sigue en su pestaña, solo deja de sumar y pierde el puntito.
	function actualizarBadge() {
		var noVistos = ultimasMias.filter(function (a) { return !vistas[claveFirmar(a)]; });
		var noVistasPrecarga = ultimasPrecargadas.filter(function (p) { return !vistas[claveAsignada(p)]; });
		var total = noVistos.length + noVistasPrecarga.length;
		if (total > 0) {
			badge.textContent = total > 9 ? '9+' : String(total);
			badge.hidden = false;
		} else {
			badge.hidden = true;
		}
		// Pulso si hay Acta no vista en nivel crítico (0-1 día) o Acta asignada no vista (esta última es urgente desde que existe).
		var hayCritico = noVistos.some(function (a) { return parseInt(a.dias_restantes, 10) <= 1; }) || noVistasPrecarga.length > 0;
		badge.classList.toggle('ac-alertas-badge-critico', hayCritico);
	}

	// Se llama al abrir el panel: marca todo lo cargado (las 2 pestañas, no solo la activa) como visto de una vez.
	function marcarTodoVisto() {
		var cambio = false;
		ultimasMias.forEach(function (a) { var k = claveFirmar(a); if (!vistas[k]) { vistas[k] = true; cambio = true; } });
		ultimasPrecargadas.forEach(function (p) { var k = claveAsignada(p); if (!vistas[k]) { vistas[k] = true; cambio = true; } });
		if (!cambio) return;
		guardarVistas();
		actualizarBadge();
		renderAsignadas(ultimasPrecargadas);
		renderFirmar(ultimasMias);
	}

	function cargarAlertas() {
		var miReqId = ++alertasReqId;
		if (refrescarBtn) acBotonCargando(refrescarBtn, true);
		return fetch('getters/alertas_firma.php')
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (miReqId !== alertasReqId) return; // respuesta vieja, ya se disparó otro refresco — ignorar.
				if (!data.ok) return;
				ultimasMias = data.mias || [];
				ultimasPrecargadas = data.precargadas || [];
				actualizarBadge();
				renderAsignadas(ultimasPrecargadas);
				renderFirmar(ultimasMias);
				if (primeraCarga) { avisarAlInicio(ultimasMias, ultimasPrecargadas); primeraCarga = false; }
			})
			.catch(function () {
				if (miReqId !== alertasReqId) return;
				bodyAsignadas.innerHTML = '<p class="ac-alertas-vacio">No se pudo cargar.</p>';
				bodyFirmar.innerHTML = '<p class="ac-alertas-vacio">No se pudo cargar.</p>';
			})
			.finally(function () {
				if (miReqId !== alertasReqId) return;
				if (refrescarBtn) acBotonCargando(refrescarBtn, false);
			});
	}

	// El panel es position:fixed (escapa del overflow:hidden de .ac-header-inner), se posiciona acá con el rect real del botón.
	function posicionarPanel() {
		var r = btn.getBoundingClientRect();
		panel.style.top = (r.bottom + 8) + 'px';
		panel.style.right = (window.innerWidth - r.right) + 'px';
	}

	function abrirPanel() {
		posicionarPanel();
		panel.hidden = false;
		btn.setAttribute('aria-expanded', 'true');
		marcarTodoVisto();
	}
	function cerrarPanel() {
		panel.hidden = true;
		btn.setAttribute('aria-expanded', 'false');
	}

	btn.addEventListener('click', function (e) {
		e.stopPropagation();
		if (panel.hidden) abrirPanel(); else cerrarPanel();
	});
	document.addEventListener('click', function (e) {
		if (!panel.hidden && !panel.contains(e.target) && e.target !== btn) cerrarPanel();
	});
	panel.addEventListener('click', function (e) { e.stopPropagation(); });
	if (refrescarBtn) refrescarBtn.addEventListener('click', function (e) { e.stopPropagation(); cargarAlertas(); });

	cargarAlertas();
	// Sondeo cada 5 minutos como respaldo, se suma al refresco explícito (botón + cambio de módulo), no lo reemplaza.
	setInterval(cargarAlertas, 5 * 60 * 1000);
	// Expuesto para que index.php refresque la campanita en cualquier cambio de módulo, mismo patrón que window.acHistorialRefrescar.
	window.acAlertasFirmaRefrescar = cargarAlertas;
})();

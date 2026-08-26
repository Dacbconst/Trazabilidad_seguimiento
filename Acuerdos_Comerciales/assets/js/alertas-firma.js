// Campanita de notificaciones (2026-08-25) — widget global del header,
// visible en cualquier módulo (mismo espíritu que assets/js/lightbox.js).
// Rediseño a 2 pestañas (mismo día, pedido explícito: "esa mecánica de
// seguimiento de equipo quitémosla" + "usa el mismo diseño y mecánica" de
// "diseños ideas/code.html", tomando SOLO esa parte del mockup, no cómo
// armaron el resto de esa página de referencia — ver CLAUDE.md):
// - "Actas Asignadas" (activity feed): Actas precargadas del Repositorio
//   de Cuotas pendientes de completar.
// - "Actas Por Firmar" (cajas de alerta con franja de color): plazo de 20
//   días desde fecha_generacion, ver getters/alertas_firma.php.
// Sin conexión en tiempo real (sin Firebase ni similar, pedido explícito
// del usuario) — la "sensación de en vivo" la da el botón de refrescar del
// panel + el refresco automático en cada cambio de módulo (ver el listener
// de .ac-sidebar-nav en index.php), no un push real.
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

	// "Vence en N días" no decía QUÉ vence (podía leerse como que se cae el
	// acuerdo comercial, no que es la ventana para subir la firma) — ver
	// CLAUDE.md, concepto "Sala de Alertas" (2026-08-25).
	function diasCortos(dias) {
		dias = parseInt(dias, 10);
		if (dias <= 0) return 'hoy';
		if (dias === 1) return '1 día';
		return dias + ' días';
	}
	function accionTexto(dias) {
		return 'Sube la firma — ' + diasCortos(dias);
	}

	// ---------- Vistas (2026-08-26, pedido explícito: "un puntito al lado
	// de la que está pendiente de ver, que coincida con los numeritos, así
	// ya marco que la vi") — sin backend real para esto (no hay tabla de
	// "leídos" ni Firebase, mismo motivo que el resto de la campanita), se
	// guarda en localStorage: por eso es un estado POR NAVEGADOR, no por
	// usuario de verdad (si el mismo usuario entra desde otra compu, ve todo
	// como no visto de nuevo) — aceptable para lo pedido, no hay infra para
	// más. Clave por ítem: los "Por Firmar" tienen `id` propio; los
	// "Asignados" no (vienen de un GROUP BY en SQL, ver
	// listar_actas_precargadas_pendientes()), así que se arma con
	// pos_id+trimestre+año, su identidad real. ----------
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
	function claveAsignada(p) { return 'asignadas:' + p.pos_id + ':' + p.trimestre + ':' + p.anio; }
	// Último fetch en memoria — hace falta fuera de cargarAlertas() para que
	// marcarTodoVisto() (se llama al abrir el panel) sepa qué había sin
	// pedir la data de nuevo.
	var ultimasMias = [];
	var ultimasPrecargadas = [];

	function irAHistorial(id) {
		var link = document.querySelector('.ac-sidebar-nav a[href="#sec-historial"]');
		if (link) link.click();
		cerrarPanel();
	}

	// Actas Precargadas (Fase 2 del Repositorio de Cuotas) — a diferencia de
	// "Actas Por Firmar" (que solo lleva a Historial, el usuario elige qué
	// hacer ahí), acá el click va DIRECTO a Registrar con el formulario ya
	// cargado — no tiene sentido un paso intermedio para algo que todavía no
	// existe como Acuerdo real.
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

	// Notificación al iniciar sesión (2026-08-25, del concepto "Sala de
	// Alertas") — reusa el toast global del proyecto (assets/js/toast.js,
	// ya usado en Registrar/Gestión de Usuarios/etc.) en vez de inventar un
	// componente de notificación aparte. Se dispara UNA sola vez, en la
	// primera carga de esta sesión de navegador (no en cada refresco — eso
	// sería spam, no una alerta de inicio de sesión) — como este proyecto
	// renderiza todos los módulos una sola vez al loguearse, alcanza con un
	// flag booleano, sin necesidad de sessionStorage.
	var primeraCarga = true;
	function avisarAlInicio(mias, precargadas) {
		// 2 toasts separados (no un mensaje combinado) porque son 2 acciones
		// distintas con contexto distinto — mezclarlas en una sola frase
		// quedaría confuso. El de precargadas primero: es la más nueva/
		// desconocida de las dos, conviene que no quede tapada.
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

	// El badge cuenta NO VISTOS, no el total — un ítem ya marcado como visto
	// sigue apareciendo en su pestaña, solo deja de sumar acá y pierde el
	// puntito (pedido explícito: "que coincida con los numeritos").
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
		// Pulso si hay al menos una Acta NO VISTA en el nivel más urgente de
		// vencimiento (0-1 día) O al menos una Acta asignada NO VISTA — a
		// diferencia del vencimiento, una asignación no tiene un plazo que la
		// escale sola con los días, así que se trata como urgente desde que
		// existe (y hasta que se vea).
		var hayCritico = noVistos.some(function (a) { return parseInt(a.dias_restantes, 10) <= 1; }) || noVistasPrecarga.length > 0;
		badge.classList.toggle('ac-alertas-badge-critico', hayCritico);
	}

	// Se llama al abrir el panel — marca TODO lo que hay ahora mismo cargado
	// (las 2 pestañas, no solo la activa) como visto de una, mismo criterio
	// simple que pidió el usuario ("ya marco que la vi" al abrir, no al
	// cambiar de pestaña ítem por ítem).
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
		if (refrescarBtn) acBotonCargando(refrescarBtn, true);
		return fetch('getters/alertas_firma.php')
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) return;
				ultimasMias = data.mias || [];
				ultimasPrecargadas = data.precargadas || [];
				actualizarBadge();
				renderAsignadas(ultimasPrecargadas);
				renderFirmar(ultimasMias);
				if (primeraCarga) { avisarAlInicio(ultimasMias, ultimasPrecargadas); primeraCarga = false; }
			})
			.catch(function () {
				bodyAsignadas.innerHTML = '<p class="ac-alertas-vacio">No se pudo cargar.</p>';
				bodyFirmar.innerHTML = '<p class="ac-alertas-vacio">No se pudo cargar.</p>';
			})
			.finally(function () {
				if (refrescarBtn) acBotonCargando(refrescarBtn, false);
			});
	}

	// El panel es `position:fixed` (ver style.css — escapa del
	// `overflow:hidden` de .ac-header-inner) así que no se puede anclar por
	// CSS puro con top/right relativos al botón; se calcula acá cada vez
	// que se abre, a partir del rect real del botón en pantalla.
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
	// Sondeo cada 5 minutos como respaldo (sin conexión en tiempo real, ver
	// comentario de arriba) — se suma al refresco explícito (botón + cambio
	// de módulo), no lo reemplaza.
	setInterval(cargarAlertas, 5 * 60 * 1000);
	// Expuesto para que index.php refresque la campanita en CUALQUIER
	// cambio de módulo (pedido explícito), mismo patrón que
	// window.acHistorialRefrescar/etc.
	window.acAlertasFirmaRefrescar = cargarAlertas;
})();

// Campanita de alertas de firma (2026-08-25) — widget global del header,
// visible en cualquier módulo (mismo espíritu que assets/js/lightbox.js).
// "Mis Actas" para cualquier desarrollador/superdesarrollador; "Equipo"
// (seguimiento de pendientes de TODOS los usuarios, no solo urgentes) solo
// para superdesarrollador — ver getters/alertas_firma.php.
(function () {
	var btn = document.getElementById('acAlertasBtn');
	var badge = document.getElementById('acAlertasBadge');
	var panel = document.getElementById('acAlertasPanel');
	var body = document.getElementById('acAlertasBody');
	if (!btn || !panel || !body) return;

	// Texto rediseñado (2026-08-25, del concepto "Sala de Alertas", aprobado
	// por el usuario tal cual) — "Vence en N días" no decía QUÉ vence (podía
	// leerse como que se cae el acuerdo comercial, no que es la ventana para
	// subir la firma). "Subí la firma — N días" nombra la acción pendiente
	// en el badge de "Mis Actas" (es el usuario quien tiene que actuar); el
	// texto de "Equipo" se queda descriptivo ("vence en N días") porque ahí
	// se está informando sobre el pendiente de UN COLEGA, no pidiéndole una
	// acción a quien lee — ver diasCortos()/accionTexto() abajo.
	function diasCortos(dias) {
		dias = parseInt(dias, 10);
		if (dias <= 0) return 'hoy';
		if (dias === 1) return '1 día';
		return dias + ' días';
	}
	function accionTexto(dias) {
		return 'Subí la firma — ' + diasCortos(dias);
	}

	function irAHistorial() {
		var link = document.querySelector('.ac-sidebar-nav a[href="#sec-historial"]');
		if (link) link.click();
		cerrarPanel();
	}

	function renderPanel(data) {
		var mias = data.mias || [];
		var equipo = data.equipo || [];
		var html = '';

		html += '<p class="ac-alertas-seccion-titulo">Mis Actas por vencer</p>';
		if (!mias.length) {
			html += '<p class="ac-alertas-vacio">No tenés Actas por vencer en los próximos días.</p>';
		} else {
			html += '<ul class="ac-alertas-lista">' + mias.map(function (a) {
				var badgeClase = parseInt(a.dias_restantes, 10) <= 1 ? 'ac-badge-critico' : 'ac-badge-urgente';
				return '<li class="ac-alertas-item" data-id="' + a.id + '">' +
					'<span class="ac-alertas-item-doc">#' + a.documento_no + '</span>' +
					'<span class="ac-badge ' + badgeClase + '">' + accionTexto(a.dias_restantes) + '</span>' +
					'</li>';
			}).join('') + '</ul>';
		}

		if (equipo.length) {
			html += '<p class="ac-alertas-seccion-titulo ac-alertas-seccion-equipo">Equipo — pendientes de firma</p>';
			html += '<ul class="ac-alertas-lista">' + equipo.map(function (u) {
				var dias = u.dias_restantes_minimo;
				var proximo = (dias !== null && dias !== undefined) ? ('vence en ' + diasCortos(dias)) : 'sin fecha';
				return '<li class="ac-alertas-item ac-alertas-item-equipo">' +
					'<span class="ac-alertas-item-doc">' + u.usuario + '</span>' +
					'<span class="ac-alertas-item-meta">' + u.pendientes + ' pendiente' + (u.pendientes == 1 ? '' : 's') + ' · más próximo: ' + proximo + '</span>' +
					'</li>';
			}).join('') + '</ul>';
		}

		body.innerHTML = html;
		Array.prototype.forEach.call(body.querySelectorAll('.ac-alertas-item[data-id]'), function (li) {
			li.addEventListener('click', irAHistorial);
		});
	}

	// Notificación al iniciar sesión (2026-08-25, del concepto "Sala de
	// Alertas") — reusa el toast global del proyecto (assets/js/toast.js,
	// ya usado en Registrar/Gestión de Usuarios/etc.) en vez de inventar un
	// componente de notificación aparte. Se dispara UNA sola vez, en la
	// primera carga de esta sesión de navegador (no en cada sondeo de 5
	// minutos — eso sería spam, no una alerta de inicio de sesión) — como
	// este proyecto renderiza todos los módulos una sola vez al loguearse
	// (ver comentario de refrescoPorSeccion en index.php) alcanza con un
	// flag booleano, sin necesidad de sessionStorage.
	var primeraCarga = true;
	function avisarAlInicio(mias) {
		if (!mias.length) return;
		var masUrgente = mias[0]; // ya vienen ordenados ASC por dias_restantes (ver listar_alertas_firma_propias()).
		var hayCritico = mias.some(function (a) { return parseInt(a.dias_restantes, 10) <= 1; });
		var mensaje = mias.length === 1
			? '1 Acta por vencer — #' + masUrgente.documento_no + ': ' + accionTexto(masUrgente.dias_restantes)
			: mias.length + ' Actas por vencer — la más próxima, #' + masUrgente.documento_no + ': ' + accionTexto(masUrgente.dias_restantes);
		if (window.mostrarToast) window.mostrarToast(mensaje, hayCritico ? 'error' : 'warning');
	}

	function cargarAlertas() {
		fetch('getters/alertas_firma.php')
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) return;
				var mias = data.mias || [];
				var total = mias.length;
				if (total > 0) {
					badge.textContent = total > 9 ? '9+' : String(total);
					badge.hidden = false;
				} else {
					badge.hidden = true;
				}
				// Pulso solo si hay al menos una Acta en el nivel más urgente
				// (0-1 día) — mismo criterio de "escalar con el plazo real" que
				// ya aplica ac-badge-critico en style.css, no animar desde el
				// primer día de aviso.
				var hayCritico = mias.some(function (a) { return parseInt(a.dias_restantes, 10) <= 1; });
				badge.classList.toggle('ac-alertas-badge-critico', hayCritico);
				renderPanel(data);
				if (primeraCarga) { avisarAlInicio(mias); primeraCarga = false; }
			})
			.catch(function () {
				body.innerHTML = '<p class="ac-alertas-vacio">No se pudo cargar las alertas.</p>';
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

	cargarAlertas();
	// Sondeo cada 5 minutos — no hace falta más seguido, el plazo se mide en
	// días, no en minutos; suficiente para que el badge no quede desactualizado
	// en una sesión larga abierta.
	setInterval(cargarAlertas, 5 * 60 * 1000);
})();

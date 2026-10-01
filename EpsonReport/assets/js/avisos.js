// Avisos del promotor: panel de la campana, campana que "suena" mientras haya algo sin ver y cartel al entrar.
(function () {
	var panel = document.getElementById('epAvisos');
	var datosEl = document.getElementById('epAvisosDatos');
	if (!panel || !datosEl) return;

	var datos = JSON.parse(datosEl.textContent);
	var scrim = document.getElementById('epAvisosScrim');
	var botones = document.querySelectorAll('.ep-avisos-abrir');
	var abierto = false;

	function hoyClave() {
		var d = new Date();
		return d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate();
	}
	function leer(almacen, clave) { try { return window[almacen].getItem(clave); } catch (e) { return null; } }
	function guardar(almacen, clave, valor) { try { window[almacen].setItem(clave, valor); } catch (e) {} }

	// La campana suena si hay avisos nuevos, o un último día cerca que todavía no abrió hoy.
	var claveAbierto = 'ep_aviso_abierto_' + (document.querySelector('meta[name="ep-usuario"]') || {}).content;
	function debeSonar() {
		return datos.nuevos > 0 || (datos.urgentes > 0 && leer('localStorage', claveAbierto) !== hoyClave());
	}
	function pintarCampana() {
		var suena = debeSonar();
		botones.forEach(function (b) { b.classList.toggle('llamando', suena); });
	}

	function marcarVisto() {
		if (datos.nuevos) {
			datos.nuevos = 0;
			fetch('getters/avisos_visto.php', { method: 'POST', credentials: 'same-origin' }).catch(function () {});
		}
		guardar('localStorage', claveAbierto, hoyClave());
		pintarCampana();
	}

	function posicionar(disparador) {
		if (window.matchMedia('(max-width: 900px)').matches) { panel.style.left = panel.style.top = ''; return; }
		var r = disparador.getBoundingClientRect();
		panel.style.left = Math.round(r.right + 14) + 'px';
		var alto = panel.offsetHeight;
		panel.style.top = Math.max(16, Math.min(Math.round(r.top - 8), window.innerHeight - alto - 16)) + 'px';
	}

	// El botón visible según el tamaño de pantalla (menú lateral o encabezado móvil).
	function visible() {
		for (var i = 0; i < botones.length; i++) if (botones[i].getBoundingClientRect().width > 0) return botones[i];
		return botones[0];
	}

	function abrir(disparador) {
		abierto = true;
		panel.classList.add('on');
		scrim.classList.add('on');
		panel.setAttribute('aria-hidden', 'false');
		posicionar(disparador || visible());
		// Las marcas "Nueva" siguen visibles mientras el panel está abierto; abrirlo ya cuenta como visto.
		marcarVisto();
	}
	function cerrar() {
		abierto = false;
		panel.classList.remove('on');
		scrim.classList.remove('on');
		panel.setAttribute('aria-hidden', 'true');
	}

	botones.forEach(function (b) {
		b.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			if (abierto) cerrar(); else abrir(b);
		});
	});
	document.getElementById('epAvisosCerrar').addEventListener('click', cerrar);
	scrim.addEventListener('click', cerrar);
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && abierto) cerrar(); });

	pintarCampana();

	// ---- Cartel al entrar: una vez por novedad, y lo urgente una vez al día por sesión ----
	function esc(t) { return t.replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function cartel() {
		var claveUrg = hoyClave() + '|' + datos.urgentesTxt.join(',');
		var verNuevos = datos.nuevos > 0 && leer('sessionStorage', 'ep_aviso_nuevos') !== String(datos.nuevos);
		var verUrgentes = datos.urgentes > 0 && leer('sessionStorage', 'ep_aviso_urg') !== claveUrg;
		if (!verNuevos && !verUrgentes) return;
		if (!window.Swal) return false;

		// Los devueltos también cuentan como urgentes: se separan para que el título diga lo que de verdad hay.
		var devueltosUrg = datos.urgentesTxt.filter(function (t) { return t.indexOf('Registro devuelto') === 0; }).length;
		var plazos = datos.urgentes - devueltosUrg;
		var soloDevueltos = datos.nuevos - (datos.devueltosNuevos || 0) <= 0 && plazos <= 0;
		var lineas = '<p style="margin:0 0 10px">Revisa la campana de <b>Avisos</b> para ver ' + (soloDevueltos ? 'lo que debes corregir.' : 'tus activaciones programadas.') + '</p>';
		var programadas = datos.nuevos - (datos.devueltosNuevos || 0);
		if (verNuevos && datos.devueltosNuevos) lineas += '<p style="margin:0 0 8px"><b>' + datos.devueltosNuevos + (datos.devueltosNuevos === 1 ? ' registro devuelto' : ' registros devueltos') + '</b> para que lo corrijas y reenvíes.</p>';
		if (verNuevos && programadas > 0) lineas += '<p style="margin:0 0 8px">Te programaron <b>' + programadas + (programadas === 1 ? ' activación nueva' : ' activaciones nuevas') + '</b>.</p>';
		if (verUrgentes) {
			lineas += '<p style="margin:0 0 6px"><b>Pendiente de tu parte:</b></p><ul style="margin:0;padding-left:18px;text-align:left">' +
				datos.urgentesTxt.map(function (t) { return '<li>' + esc(t) + '</li>'; }).join('') + '</ul>';
		}
		if (verNuevos) guardar('sessionStorage', 'ep_aviso_nuevos', String(datos.nuevos));
		if (verUrgentes) guardar('sessionStorage', 'ep_aviso_urg', claveUrg);
		// El cartel no cuenta como visto: la campana sigue sonando hasta que la abra.
		Swal.fire({
			icon: verUrgentes ? 'warning' : 'info',
			title: datos.devueltosNuevos ? (datos.devueltosNuevos === 1 ? 'Te devolvieron un registro' : 'Te devolvieron registros') : (verUrgentes ? (plazos <= 0 ? (devueltosUrg === 1 ? 'Tienes un registro devuelto' : 'Tienes registros devueltos') : (devueltosUrg ? 'Tienes pendientes por atender' : 'Tienes un plazo por vencer')) : 'Tienes notificaciones nuevas'),
			html: lineas,
			confirmButtonText: 'Ver avisos',
			showCancelButton: true,
			cancelButtonText: 'Después',
			confirmButtonColor: '#513487'
		}).then(function (r) { if (r.isConfirmed) abrir(); });
		return true;
	}
	// SweetAlert carga diferido: se reintenta unos segundos antes de rendirse.
	var intentos = 0;
	var espera = setInterval(function () {
		intentos++;
		if (cartel() !== false || intentos > 12) clearInterval(espera);
	}, 500);
})();

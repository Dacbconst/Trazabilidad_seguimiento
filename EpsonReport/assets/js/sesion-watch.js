// Sesión única + inactividad: cada 15s pregunta al servidor si la sesión sigue viva. Si otro login la pisó, o pasaron 20 min sin actividad real, avisa con una ventana y manda al login.
// El ping NO cuenta como actividad: solo lo hace una interacción real (mouse, teclado, toque), que se informa con ?activo=1.
// Pide 2 fallos seguidos antes de avisar, para no botar a nadie por un hipo de red o de la base.
(function () {
	var fallosSeguidos = 0;
	var avisando = false;
	var hubo = false;
	['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click'].forEach(function (ev) {
		document.addEventListener(ev, function () { hubo = true; }, { passive: true });
	});
	function irAlLogin(motivo) { window.location.href = 'login.php?error=' + (motivo === 'inactividad' ? 'inactividad' : 'sesion'); }
	setInterval(function () {
		if (avisando) return;
		var conActividad = hubo;
		hubo = false;
		fetch('getters/sesion_verificar.php' + (conActividad ? '?activo=1' : ''))
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data.ok) { fallosSeguidos = 0; return; }
				fallosSeguidos++;
				if (fallosSeguidos < 2 && data.motivo !== 'inactividad') return;
				avisando = true;
				var inactividad = data.motivo === 'inactividad';
				if (window.Swal) {
					Swal.fire({
						icon: 'warning',
						title: inactividad ? 'Sesión cerrada por inactividad' : 'Tu sesión se cerró',
						text: inactividad ? 'Pasaron 20 minutos sin actividad. Inicia sesión de nuevo.' : 'Se inició sesión con esta cuenta en otro dispositivo.',
						confirmButtonText: 'Ir al inicio de sesión',
						allowOutsideClick: false
					}).then(function () { irAlLogin(data.motivo); });
				} else {
					irAlLogin(data.motivo);
				}
			})
			.catch(function () {});
	}, 15000);
})();

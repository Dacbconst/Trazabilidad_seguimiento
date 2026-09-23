// Sesión única (2026-09-22): si otro login pisa esta sesión, redirige solo al login en vez de dejar los módulos tirando "Error de conexión". A propósito NO es un interceptor de fetch() (ver intento revertido documentado en CLAUDE.md) — un ping propio e independiente no puede romper otro fetch de la app.
// 15s, no 1s: un ping por segundo generaba falsos positivos (PHP bloquea la sesión mientras la tiene abierta, un ping tan seguido choca con el resto de la app). Además pide 2 fallos seguidos antes de redirigir, para no botar a nadie por un hipo de red o de la base.
(function () {
	var fallosSeguidos = 0;
	setInterval(function () {
		fetch('getters/sesion_verificar.php')
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data.ok) { fallosSeguidos = 0; return; }
				fallosSeguidos++;
				if (fallosSeguidos >= 2) window.location.href = 'login.php?error=sesion';
			})
			.catch(function () {});
	}, 15000);
})();

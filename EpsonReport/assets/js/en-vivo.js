// En vivo: pregunta cada pocos segundos una firma liviana y avisa a la pantalla cuando cambió; se pausa con la pestaña oculta.
(function () {
	// op: url, indicador (id del rótulo "En vivo"), cada (ms), alCambiar(datos) que devuelve false si todavía no pudo actuar.
	window.epVivo = function (op) {
		var indicador = document.getElementById(op.indicador);
		var cada = op.cada || 4000;
		var ultima = null;
		var ocupado = false;

		function mostrar(ok) {
			if (!indicador) return;
			indicador.classList.toggle('sin-conexion', !ok);
			indicador.querySelector('span').textContent = ok ? 'En vivo' : 'Sin conexión';
		}

		function consultar() {
			if (ocupado || document.hidden) return;
			ocupado = true;
			fetch(op.url, { cache: 'no-store', credentials: 'same-origin' })
				.then(function (r) {
					if (r.status === 401) { window.location.href = 'login.php'; throw new Error('sesion'); }
					return r.json();
				})
				.then(function (d) {
					mostrar(true);
					if (!d || !d.firma) return;
					// La primera respuesta solo fija la referencia; si la pantalla no pudo actuar, se vuelve a intentar con el mismo cambio.
					if (ultima !== null && d.firma !== ultima && op.alCambiar(d) === false) return;
					ultima = d.firma;
				})
				.catch(function () { mostrar(false); })
				.then(function () { ocupado = false; });
		}

		setInterval(consultar, cada);
		document.addEventListener('visibilitychange', function () { if (!document.hidden) consultar(); });
		consultar();
		return { consultar: consultar };
	};
})();

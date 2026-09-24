// Comentarios como lista: cada comentario en su propia línea numerada. El textarea original queda oculto y conserva un comentario por línea, así el resto del código no cambia.
(function () {
	var MAXIMO = 5;
	var LIMITE_CARACTERES = 160;
	var ICONO_QUITAR = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>';
	var ICONO_AGREGAR = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>';

	function armar(original) {
		var caja = document.createElement('div');
		caja.className = 'ep-coment';
		caja.innerHTML = '<div class="ep-coment-lista"></div>' +
			'<div class="ep-coment-pie"><button type="button" class="ep-coment-agregar">' + ICONO_AGREGAR + ' Agregar comentario</button><span class="ep-coment-cuenta"></span></div>';
		original.style.display = 'none';
		original.parentNode.insertBefore(caja, original.nextSibling);
		var lista = caja.querySelector('.ep-coment-lista');
		var agregar = caja.querySelector('.ep-coment-agregar');
		var cuenta = caja.querySelector('.ep-coment-cuenta');

		function items() { return Array.prototype.slice.call(lista.querySelectorAll('.ep-coment-item')); }

		function ajustarAltura(campo) {
			campo.style.height = 'auto';
			campo.style.height = campo.scrollHeight + 'px';
		}

		// Pasa la lista al textarea original (un comentario por línea) y avisa a quien escuche.
		function sincronizar() {
			var filas = items();
			filas.forEach(function (fila, i) { fila.querySelector('.ep-coment-num').textContent = i + 1; });
			original.value = filas.map(function (f) { return f.querySelector('textarea').value.replace(/\s+/g, ' ').trim(); }).filter(Boolean).join('\n');
			original.dispatchEvent(new Event('input', { bubbles: true }));
			agregar.disabled = filas.length >= MAXIMO;
			cuenta.textContent = filas.length + ' de ' + MAXIMO;
			filas.forEach(function (f) { f.querySelector('.ep-coment-quitar').hidden = filas.length === 1; });
		}

		function nuevo(texto) {
			var fila = document.createElement('div');
			fila.className = 'ep-coment-item';
			fila.innerHTML = '<span class="ep-coment-num"></span>' +
				'<textarea class="ep-input" rows="1" maxlength="' + LIMITE_CARACTERES + '" placeholder="Escribe un comentario"></textarea>' +
				'<button type="button" class="ep-coment-quitar" aria-label="Quitar comentario">' + ICONO_QUITAR + '</button>';
			var campo = fila.querySelector('textarea');
			campo.value = texto || '';
			lista.appendChild(fila);
			ajustarAltura(campo);
			return campo;
		}

		lista.addEventListener('input', function (ev) {
			ajustarAltura(ev.target);
			sincronizar();
		});

		// Enter agrega el siguiente comentario; Retroceso en uno vacío lo quita.
		lista.addEventListener('keydown', function (ev) {
			var campo = ev.target;
			if (ev.key === 'Enter') {
				ev.preventDefault();
				if (items().length < MAXIMO && campo.value.trim()) {
					var siguiente = nuevo('');
					sincronizar();
					siguiente.focus();
				}
			} else if (ev.key === 'Backspace' && campo.value === '' && items().length > 1) {
				ev.preventDefault();
				quitar(campo.closest('.ep-coment-item'));
			}
		});

		lista.addEventListener('click', function (ev) {
			var boton = ev.target.closest('.ep-coment-quitar');
			if (boton) quitar(boton.closest('.ep-coment-item'));
		});

		function quitar(fila) {
			var todas = items();
			var pos = todas.indexOf(fila);
			fila.remove();
			sincronizar();
			var restantes = items();
			var foco = restantes[Math.max(0, pos - 1)];
			if (foco) foco.querySelector('textarea').focus();
		}

		agregar.addEventListener('click', function () {
			if (items().length >= MAXIMO) return;
			var campo = nuevo('');
			sincronizar();
			campo.focus();
		});

		var previos = original.value.split('\n').map(function (l) { return l.trim(); }).filter(Boolean).slice(0, MAXIMO);
		(previos.length ? previos : ['']).forEach(nuevo);
		sincronizar();
	}

	document.querySelectorAll('textarea[id$="-comentarios"]').forEach(armar);
})();

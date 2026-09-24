// Visor de fotos ya cargadas en Actividades: al tocar una foto se abre el carrusel con cambiar y quitar.
(function () {
	var visor = document.getElementById('epVisorFotos');
	if (!visor) return;

	var img = document.getElementById('epVisorImg');
	var titulo = document.getElementById('epVisorTitulo');
	var contador = document.getElementById('epVisorContador');
	var lista = [];
	var pos = 0;

	function conFoto() {
		return window.epFotos.slots().filter(function (s) { return s.classList.contains('ep-foto-slot-completa'); });
	}

	function mostrar() {
		var slot = lista[pos];
		var etiqueta = slot.querySelector('.ep-foto-slot-label');
		img.src = slot.querySelector('.ep-foto-preview').src;
		titulo.textContent = etiqueta ? etiqueta.textContent.trim() : 'Foto';
		contador.textContent = 'Foto ' + (pos + 1) + ' de ' + lista.length;
		visor.classList.toggle('ep-visor-una', lista.length < 2);
	}

	function abrir(slot) {
		lista = conFoto();
		pos = lista.indexOf(slot);
		if (pos < 0) return;
		visor.classList.remove('hidden');
		mostrar();
	}

	function cerrar() {
		visor.classList.add('hidden');
		img.removeAttribute('src');
	}

	function mover(paso) {
		pos = (pos + paso + lista.length) % lista.length;
		mostrar();
	}

	// Una foto ya cargada abre el visor en vez del selector de archivos (el input en sí sí puede abrirse).
	document.addEventListener('click', function (ev) {
		if (ev.target.classList.contains('ep-foto-input')) return;
		var zona = ev.target.closest('.ep-evidencia-actividad .ep-foto-dropzone');
		if (!zona) return;
		var slot = zona.closest('.ep-foto-slot');
		if (!slot.classList.contains('ep-foto-slot-completa')) return;
		ev.preventDefault();
		abrir(slot);
	}, true);

	visor.querySelectorAll('[data-visor-cerrar]').forEach(function (el) { el.addEventListener('click', cerrar); });
	document.getElementById('epVisorPrev').addEventListener('click', function () { mover(-1); });
	document.getElementById('epVisorNext').addEventListener('click', function () { mover(1); });

	document.getElementById('epVisorCambiar').addEventListener('click', function () {
		var input = lista[pos].querySelector('.ep-foto-input');
		if (input) input.click();
	});

	// Al elegir la foto nueva, el visor sigue en la misma casilla con la imagen actualizada.
	document.addEventListener('change', function (ev) {
		if (visor.classList.contains('hidden') || !ev.target.classList.contains('ep-foto-input')) return;
		var slot = ev.target.closest('.ep-foto-slot');
		lista = conFoto();
		pos = lista.indexOf(slot);
		if (pos < 0) { cerrar(); return; }
		mostrar();
	});

	document.getElementById('epVisorQuitar').addEventListener('click', function () {
		window.epFotos.quitar(lista[pos]);
		lista.splice(pos, 1);
		if (!lista.length) { cerrar(); return; }
		pos = Math.min(pos, lista.length - 1);
		mostrar();
	});

	document.addEventListener('keydown', function (ev) {
		if (visor.classList.contains('hidden')) return;
		if (ev.key === 'Escape') cerrar();
		if (ev.key === 'ArrowLeft' && lista.length > 1) mover(-1);
		if (ev.key === 'ArrowRight' && lista.length > 1) mover(1);
	});
})();

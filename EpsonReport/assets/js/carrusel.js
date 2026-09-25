// Carrusel horizontal: arrastrar con el mouse, flechas y estado de las flechas. Marcado: .ep-car > .ep-car-prev, .ep-car-next y .ep-car-carril.
window.epCarrusel = (function () {
	function iniciar(caja) {
		var carril = caja.querySelector('.ep-car-carril');
		var prev = caja.querySelector('.ep-car-prev');
		var next = caja.querySelector('.ep-car-next');
		var arrastre = null;

		function flechas() {
			prev.disabled = carril.scrollLeft <= 2;
			next.disabled = carril.scrollLeft + carril.clientWidth >= carril.scrollWidth - 2;
		}

		carril.addEventListener('scroll', flechas);
		window.addEventListener('resize', flechas);
		prev.addEventListener('click', function () { carril.scrollBy({ left: -carril.clientWidth * 0.8, behavior: 'smooth' }); });
		next.addEventListener('click', function () { carril.scrollBy({ left: carril.clientWidth * 0.8, behavior: 'smooth' }); });

		carril.addEventListener('pointerdown', function (ev) {
			if (ev.pointerType !== 'mouse' || ev.button !== 0) return;
			arrastre = { x: ev.clientX, izq: carril.scrollLeft, movio: false };
		});
		window.addEventListener('pointermove', function (ev) {
			if (!arrastre) return;
			var dx = ev.clientX - arrastre.x;
			if (Math.abs(dx) > 4) { arrastre.movio = true; carril.classList.add('ep-car-arrastrando'); }
			if (arrastre.movio) carril.scrollLeft = arrastre.izq - dx;
		});
		window.addEventListener('pointerup', function () {
			if (!arrastre) return;
			carril.classList.remove('ep-car-arrastrando');
			arrastre = null;
		});
		requestAnimationFrame(flechas);
	}
	return { iniciar: iniciar };
})();

// Corregir y reenviar: abre Actividades con la actividad, el punto de venta y la fecha del registro devuelto y muestra el motivo.
(function () {
	var c = window.EP_CORREGIR;
	if (!c) return;

	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (x) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[x]; }); }

	var PREFIJOS = { activaciones: 'act', capacitaciones: 'cap', 'epson-day': 'eday', 'evento-ferias': 'evento', exhibiciones: 'exh', 'colocacion-pop': 'pop' };

	function poner(id, valor) {
		var el = document.getElementById(id);
		if (!el || valor == null || valor === '') return;
		el.value = valor;
		el.dispatchEvent(new Event('input', { bubbles: true }));
	}

	// Deja el formulario como el promotor lo envió: campos, modelos, entregas, comentarios y fotos ya subidas.
	function rellenar(tipo, d) {
		var p = PREFIJOS[tipo];
		if (p) {
			poner('ep-' + p + '-tipo', d.tipo_actividad);
			poner('ep-' + p + '-hora-inicio', d.hora_inicio);
			poner('ep-' + p + '-hora-fin', d.hora_fin);
		}
		if (d.cobertura) { poner('ep-' + p + '-nacional', d.cobertura.nacional); poner('ep-' + p + '-coberturadas', d.cobertura.coberturadas); }
		if (d.embudo) { poner('ep-' + p + '-visitaron', d.embudo.visitaron); poner('ep-' + p + '-interactuaron', d.embudo.interactuaron); poner('ep-' + p + '-compraron', d.embudo.compraron); }
		if (d.modelos && window.epRelleno) window.epRelleno.modelos(p, d.modelos);
		if (d.capacitacion) {
			poner('ep-cap-asist-jefe', d.capacitacion.asistente_jefe); poner('ep-cap-jefe-tienda', d.capacitacion.jefe_tienda);
			poner('ep-cap-vendedores', d.capacitacion.vendedores); poner('ep-cap-interacciones', d.capacitacion.interacciones);
		}
		if (d.exhibiciones) {
			poner('ep-exh-cabeceras', d.exhibiciones.cabeceras); poner('ep-exh-rumas', d.exhibiciones.rumas); poner('ep-exh-muebles', d.exhibiciones.muebles);
			poner('ep-exh-regular', d.exhibiciones.exh_regular); poner('ep-exh-otras', d.exhibiciones.otras);
		}
		if (d.pop_entregas && d.pop_entregas.length) {
			var cont = document.getElementById('ep-pop-entregas');
			var agregar = document.getElementById('ep-pop-entregas-agregar');
			d.pop_entregas.forEach(function (e, i) {
				if (i > 0 && agregar) agregar.click();
				var fila = cont && cont.querySelectorAll('.ep-pop-entrega-fila')[i];
				if (!fila) return;
				// El material es el combo del proyecto: se deja elegido, no se escribe.
				var trigger = fila.querySelector('.ep-pop-entrega-material');
				trigger.dataset.valor = e.material;
				trigger.querySelector('.ep-combo-trigger-texto').textContent = e.material;
				fila.querySelector('.ep-pop-entrega-cantidad').value = e.cantidad;
			});
		}
		var coment = document.querySelector('.ep-formulario-actividad:not(.hidden) textarea[id$="-comentarios"]');
		if (d.comentarios && d.comentarios.length && coment) poner(coment.id, d.comentarios.join('\n'));
		rellenarFotos(d.fotos || [], d.descripciones || {});
	}

	function rellenarFotos(fotos, descripciones) {
		var bloque = document.querySelector('.ep-evidencia-actividad:not(.hidden)');
		if (!bloque || !window.epRelleno) return;
		fotos.forEach(function (f) {
			if (!f.ruta) return;
			var slot = bloque.querySelector('.ep-foto-slot[data-foto-id="' + f.id + '"]');
			// Las casillas sumadas con "Agregar foto" se vuelven a crear hasta llegar a la que tenía la foto.
			var btn = bloque.querySelector('.ep-btn-agregar-foto');
			for (var n = 0; !slot && btn && n < 30; n++) {
				btn.click();
				slot = bloque.querySelector('.ep-foto-slot[data-foto-id="' + f.id + '"]');
			}
			if (!slot) return;
			window.epRelleno.foto(slot, f.url, f.ruta);
			var desc = slot.querySelector('.ep-foto-descripcion');
			if (desc && descripciones[f.id]) desc.value = descripciones[f.id];
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var item = document.querySelector('.ep-activity-item[data-plantilla="' + c.tipo + '"]');
		if (item) item.click();
		if (window.epPdv && window.epPdv.elegirPorId) window.epPdv.elegirPorId(c.pos_id);
		var fecha = document.querySelector('.ep-formulario-actividad:not(.hidden) input[id$="-fecha"]');
		if (fecha && c.fecha) { fecha.value = c.fecha; fecha.dispatchEvent(new Event('input', { bubbles: true })); }

		rellenar(c.tipo, c.datos || {});

		var cont = document.querySelector('main.ep-content');
		if (!cont) return;
		var banner = document.createElement('div');
		banner.className = 'ep-corregir';
		banner.innerHTML = '<strong>Corrige y reenvía este registro</strong><span>' + esc(c.punto) + (c.revisor ? ' · devuelto por ' + esc(c.revisor) : '') + '</span><p>' + esc(c.motivo) + '</p>';
		cont.insertBefore(banner, cont.firstChild);
	});
})();

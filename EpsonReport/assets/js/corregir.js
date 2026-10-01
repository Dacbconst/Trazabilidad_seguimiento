// Corregir y reenviar: abre Actividades con la actividad, el punto de venta y la fecha del registro devuelto y muestra el motivo.
(function () {
	var c = window.EP_CORREGIR;
	if (!c) return;

	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (x) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[x]; }); }

	document.addEventListener('DOMContentLoaded', function () {
		var item = document.querySelector('.ep-activity-item[data-plantilla="' + c.tipo + '"]');
		if (item) item.click();
		if (window.epPdv && window.epPdv.elegirPorId) window.epPdv.elegirPorId(c.pos_id);
		var fecha = document.querySelector('.ep-formulario-actividad:not(.hidden) input[id$="-fecha"]');
		if (fecha && c.fecha) { fecha.value = c.fecha; fecha.dispatchEvent(new Event('input', { bubbles: true })); }

		var cont = document.querySelector('main.ep-content');
		if (!cont) return;
		var banner = document.createElement('div');
		banner.className = 'ep-corregir';
		banner.innerHTML = '<strong>Corrige y reenvía este registro</strong><span>' + esc(c.punto) + (c.revisor ? ' · devuelto por ' + esc(c.revisor) : '') + '</span><p>' + esc(c.motivo) + '</p>';
		cont.insertBefore(banner, cont.firstChild);
	});
})();

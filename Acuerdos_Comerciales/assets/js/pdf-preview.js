// Dibuja un PDF real como imagen en un <canvas> con PDF.js — un PDF en <iframe> no renderiza en Chrome de Android real. Compartido por historial.js y registrar.js.
(function () {
	var CDN_BASE = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/';
	var cargaPromesa = null;

	function cargarPdfJs() {
		if (window.pdfjsLib) return Promise.resolve(window.pdfjsLib);
		if (cargaPromesa) return cargaPromesa;
		cargaPromesa = new Promise(function (resolve, reject) {
			var script = document.createElement('script');
			script.src = CDN_BASE + 'pdf.min.js';
			script.onload = function () {
				window.pdfjsLib.GlobalWorkerOptions.workerSrc = CDN_BASE + 'pdf.worker.min.js';
				resolve(window.pdfjsLib);
			};
			script.onerror = function () { reject(new Error('No se pudo cargar el visor de PDF.')); };
			document.head.appendChild(script);
		});
		return cargaPromesa;
	}

	// opciones.zoomPct escala directo por ese %; sin eso, opciones.contenedor define el ancho y la página se ajusta a él.
	function renderizarPdfEnCanvas(url, canvas, opciones) {
		opciones = opciones || {};
		return cargarPdfJs()
			.then(function (pdfjsLib) { return pdfjsLib.getDocument(url).promise; })
			.then(function (pdf) { return pdf.getPage(1); })
			.then(function (page) {
				var dpr = window.devicePixelRatio || 1;
				var viewportBase = page.getViewport({ scale: 1 });
				var escala;
				if (opciones.zoomPct) {
					escala = (opciones.zoomPct / 100) * dpr;
				} else {
					var anchoDisponible = (opciones.contenedor && opciones.contenedor.clientWidth) || 320;
					escala = (anchoDisponible / viewportBase.width) * dpr;
				}
				var viewport = page.getViewport({ scale: escala });
				canvas.width = viewport.width;
				canvas.height = viewport.height;
				canvas.style.width = (viewport.width / dpr) + 'px';
				canvas.style.height = (viewport.height / dpr) + 'px';
				return page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
			});
	}

	window.acCargarPdfJs = cargarPdfJs;
	window.acRenderizarPdfEnCanvas = renderizarPdfEnCanvas;
})();

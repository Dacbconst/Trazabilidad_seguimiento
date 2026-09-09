(function () {
	var vistaLista     = document.getElementById('ac-historial-lista');
	var vistaPreview   = document.getElementById('ac-historial-preview');
	var pdfFrame       = document.getElementById('hist-pdf-frame');
	var descargarBtn   = document.getElementById('hist-descargar-pdf');
	var detalleCanvasWrap   = document.getElementById('hist-detalle-canvas-wrap');
	var detalleCanvas       = document.getElementById('hist-detalle-canvas');
	var detalleCanvasEstado = document.getElementById('hist-detalle-canvas-estado');

	var buscarInput     = document.getElementById('hist-buscar');
	var trimestreSelect = document.getElementById('hist-trimestre');
	var anioSelect      = document.getElementById('hist-anio');
	var buscarBtn       = document.getElementById('hist-buscar-btn');

	// "Descargar Excel" solo existe para superdesarrollador — estos 4 elementos son null para un desarrollador normal (ver if (exportarWrap)).
	var exportarWrap = document.getElementById('hist-exportar-wrap');
	var exportarBtn = document.getElementById('hist-exportar-btn');
	var exportarDirectoLink = document.getElementById('hist-exportar-directo');
	var exportarDistribuidorLink = document.getElementById('hist-exportar-distribuidor');
	var exportarLinks = [exportarDirectoLink, exportarDistribuidorLink].filter(Boolean);

	if (exportarWrap) {
		// Mismo mecanismo que "Exportar" en Repositorios (.ac-repo-exportar). Con un canal ya elegido, descarga directo sin abrir el picker.
		exportarBtn.addEventListener('click', function () {
			if (canalFiltroActual === 'directo' && exportarDirectoLink) {
				exportarDirectoLink.click();
				return;
			}
			if (canalFiltroActual === 'distribuidor' && exportarDistribuidorLink) {
				exportarDistribuidorLink.click();
				return;
			}
			exportarWrap.classList.add('ac-repo-exportar-abierto');
		});
		var cerrarExportar = function () { exportarWrap.classList.remove('ac-repo-exportar-abierto'); };
		document.addEventListener('click', function (e) {
			if (!exportarWrap.contains(e.target)) cerrarExportar();
		});

		// Aviso ANTES de descargar: con "Todos los períodos/años" el Excel mezclaría trimestres — el getter ya lo rechaza, esto avisa antes del click.
		exportarLinks.forEach(function (link) {
			link.addEventListener('click', function (e) {
				var faltaTrimestre = trimestreSelect.value === '0';
				var faltaAnio = anioSelect.value === '0';
				if (!faltaTrimestre && !faltaAnio) { setTimeout(cerrarExportar, 150); return; }

				e.preventDefault();
				var queFalta = faltaTrimestre && faltaAnio ? 'el período y el año' : (faltaTrimestre ? 'el período' : 'el año');
				Swal.fire({
					icon: 'warning',
					title: 'Elige el período antes de descargar',
					html: 'Este archivo se genera para un trimestre y año específicos.<br><br>Elige <strong>' + queFalta + '</strong> en el filtro de arriba antes de descargar.',
					confirmButtonText: 'Ir al filtro',
					confirmButtonColor: '#00288e'
				}).then(function () {
					resaltarFiltroPeriodo(faltaTrimestre, faltaAnio);
				});
			});
		});
	}

	// Sube el scroll a la tarjeta de filtros y agrega un aro pulsante (.ac-filtro-resaltado) al/los select que faltan — sigue hasta el change real (ver actualizarEstadoFiltroPeriodo() más abajo), no se apaga solo con timeout.
	function resaltarFiltroPeriodo(marcarTrimestre, marcarAnio) {
		var filtrosCard = document.querySelector('.ac-hist-filtros-card');
		if (filtrosCard && filtrosCard.scrollIntoView) {
			filtrosCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
		[marcarTrimestre ? trimestreSelect : null, marcarAnio ? anioSelect : null].forEach(function (select) {
			if (!select) return;
			// "Select bonito" envuelve el <select> real oculto — hay que resaltar el wrapper visible, no el nativo invisible.
			var objetivo = select.closest('.ac-select-bonito') || select;
			objetivo.classList.remove('ac-filtro-confirmado');
			objetivo.classList.remove('ac-filtro-resaltado');
			// Forzar reflow para reiniciar la animación si se clickea "Descargar Excel" dos veces seguidas sin corregir nada.
			void objetivo.offsetWidth;
			objetivo.classList.add('ac-filtro-resaltado');
		});
	}

	// Apaga el pulso azul y lo reemplaza por flash verde de confirmación; al completar período+año, brilla "Descargar Excel". Volver a "Todos" no vuelve a poner verde. setTimeout (no "animationend") porque .ac-excel-brillo anima 2 elementos y el evento se duplicaría.
	var exportCompletoAntes = trimestreSelect.value !== '0' && anioSelect.value !== '0';
	function actualizarEstadoFiltroPeriodo() {
		var completoAhora = trimestreSelect.value !== '0' && anioSelect.value !== '0';
		// Recién ahora quedaron los 2 elegidos a la vez: confirma en verde también el otro campo, aunque nunca haya estado pulsando.
		var recienCompleto = completoAhora && !exportCompletoAntes;

		[trimestreSelect, anioSelect].forEach(function (select) {
			if (select.value === '0') return; // sigue faltando, no tocar nada
			var objetivo = select.closest('.ac-select-bonito') || select;
			var estabaPulsando = objetivo.classList.contains('ac-filtro-resaltado');
			if (!estabaPulsando && !recienCompleto) return;
			objetivo.classList.remove('ac-filtro-resaltado');
			objetivo.classList.add('ac-filtro-confirmado');
			setTimeout(function () { objetivo.classList.remove('ac-filtro-confirmado'); }, 1200);
		});

		if (recienCompleto && exportarBtn) {
			exportarBtn.classList.remove('ac-excel-brillo');
			void exportarBtn.offsetWidth;
			exportarBtn.classList.add('ac-excel-brillo');
			setTimeout(function () { exportarBtn.classList.remove('ac-excel-brillo'); }, 1400);
		}
		exportCompletoAntes = completoAhora;
	}
	trimestreSelect.addEventListener('change', actualizarEstadoFiltroPeriodo);
	anioSelect.addEventListener('change', actualizarEstadoFiltroPeriodo);

	// Apaga cualquier pulso/flash/brillo activo al cambiar de módulo: Historial nunca se destruye al cambiar de pestaña (solo se oculta con CSS), así que sin esto un pulso `infinite` seguiría animando en segundo plano. Expuesta para que index.php la llame en cada navegación del sidebar.
	function limpiarResaltadoFiltroPeriodo() {
		[trimestreSelect, anioSelect].forEach(function (select) {
			var objetivo = select.closest('.ac-select-bonito') || select;
			objetivo.classList.remove('ac-filtro-resaltado', 'ac-filtro-confirmado');
		});
		if (exportarBtn) exportarBtn.classList.remove('ac-excel-brillo');
	}
	window.acHistorialLimpiarResaltadoFiltro = limpiarResaltadoFiltroPeriodo;
	var tbody           = document.getElementById('hist-tabla-body');
	var tablaCard       = tbody.closest('.ac-card');
	var actualizarBtn   = document.getElementById('hist-actualizar');
	// paginacionEl sigue siendo la ÚNICA fuente de verdad del estado (data-pagina/data-total-paginas); el bloque de arriba es solo visual.
	var paginacionEl    = document.getElementById('hist-paginacion');
	var paginacionInfoEls = [document.getElementById('hist-paginacion-info-top'), document.getElementById('hist-paginacion-info')];
	var paginacionBtnsEls = [document.getElementById('hist-paginacion-btns-top'), document.getElementById('hist-paginacion-btns')];
	var buscarTimeout   = null;
	// Tokens de request en vuelo: evitan que una respuesta vieja pise a una más nueva (tipear rápido dispara 2 fetch, el 1ro puede responder después).
	var historialReqId = 0;
	var bannerReqId     = 0;
	var histBanner      = document.getElementById('hist-banner');
	var histBannerText  = document.getElementById('hist-banner-text');
	var histBannerCta   = document.getElementById('hist-banner-cta');

	function escapeHtml(texto) {
		var div = document.createElement('div');
		div.textContent = texto == null ? '' : String(texto);
		return div.innerHTML;
	}

	// Compartido entre Historial y "Mis Borradores"; eliminar_acuerdo.php nunca hace DELETE físico, marca estado='anulado'. La diferencia entre los 2 usos es qué pasa con la fila después (recargar todo vs. sacarla con animación), por eso queda a cargo de onOk.
	function confirmarYEliminarAcuerdo(id, documentoNo, onOk) {
		Swal.fire({
			icon: 'warning',
			title: '¿Eliminar acuerdo?',
			text: 'Se eliminará el acuerdo #' + documentoNo + '. Esta acción no se puede deshacer desde aquí.',
			showCancelButton: true,
			confirmButtonText: 'Sí, eliminar',
			cancelButtonText: 'Cancelar',
			confirmButtonColor: '#ba1a1a'
		}).then(function (resultado) {
			if (!resultado.isConfirmed) return;

			fetch('getters/eliminar_acuerdo.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({ id: id })
			})
				.then(function (r) { return r.json(); })
				.then(function (data) {
					mostrarToast(data.message, data.ok ? 'success' : 'error');
					if (data.ok && onOk) onOk();
				})
				.catch(function () { mostrarToast('Error de conexión. Intenta nuevamente.', 'error'); });
		});
	}

	// Fade + slide en vez de desaparecer de golpe, y deja el placeholder de "vacío" si era la última fila (mismo patrón para Mis Borradores).
	function animarYQuitarFila(fila, colspanVacio, mensajeVacio) {
		fila.classList.add('ac-fila-eliminando');
		fila.addEventListener('transitionend', function () {
			var tbodyDeLaFila = fila.parentElement;
			fila.remove();
			if (tbodyDeLaFila && !tbodyDeLaFila.querySelector('tr')) {
				tbodyDeLaFila.innerHTML = '<tr><td colspan="' + colspanVacio + '" class="ac-table-empty">' + mensajeVacio + '</td></tr>';
			}
		}, { once: true });
	}

	// ---------- Stat tiles = también filtro de firma ---------- "todos" | "firmadas" | "pendientes": click en un tile ya activo vuelve a "todos" (toggle), no queda un estado sin salida.
	var firmaFiltroActual = 'todos';
	var statTiles = {
		firmadas:   document.getElementById('hist-stat-firmadas'),
		pendientes: document.getElementById('hist-stat-pendientes')
	};

	// Solo alimentan el ancho de las barras: el % y "más antigua" ya no se muestran como texto.
	function renderStats(stats) {
		var pctFirmadas = stats.total > 0 ? Math.round(stats.firmadas / stats.total * 100) : 0;
		var pctPendientes = stats.total > 0 ? Math.round(stats.pendientes / stats.total * 100) : 0;
		document.getElementById('hist-stat-total-valor').textContent = stats.total;
		document.getElementById('hist-stat-firmadas-valor').textContent = stats.firmadas;
		document.getElementById('hist-stat-firmadas-bar').style.width = pctFirmadas + '%';
		document.getElementById('hist-stat-pendientes-valor').textContent = stats.pendientes;
		document.getElementById('hist-stat-pendientes-bar').style.width = pctPendientes + '%';
	}

	function actualizarTilesActivos() {
		statTiles.firmadas.classList.toggle('ac-hist-stat-activo', firmaFiltroActual === 'firmadas');
		statTiles.pendientes.classList.toggle('ac-hist-stat-activo', firmaFiltroActual === 'pendientes');
	}

	Object.keys(statTiles).forEach(function (clave) {
		statTiles[clave].addEventListener('click', function () {
			firmaFiltroActual = (firmaFiltroActual === statTiles[clave].dataset.filtro) ? 'todos' : statTiles[clave].dataset.filtro;
			actualizarTilesActivos();
			cargarHistorial(1);
		});
	});
	document.getElementById('hist-stat-total').addEventListener('click', function () {
		firmaFiltroActual = 'todos';
		actualizarTilesActivos();
		cargarHistorial(1);
	});

	// ---------- Pastillas de Canal (solo superdesarrollador) ---------- "total" | "directo" | "distribuidor": mismo mecanismo simple que Cumplimiento/Seguimiento (clase .ac-seg-pill-activo a mano, sin componente nuevo).
	var canalGroup = document.getElementById('hist-canal-group');
	var canalFiltroActual = 'total';
	// Refleja en el título del botón qué va a pasar al click: con un canal puntual elegido ya no hay picker, el botón dice solo qué formato descarga.
	var actualizarTituloExportar = function () {
		if (!exportarBtn) return;
		if (canalFiltroActual === 'directo') exportarBtn.title = 'Descarga el Excel de canal Directo';
		else if (canalFiltroActual === 'distribuidor') exportarBtn.title = 'Descarga el Excel de canal Distribuidor';
		else exportarBtn.title = 'Elige el formato a descargar';
	};
	if (canalGroup) {
		// Arranca con la pastilla que el servidor ya marcó activa (ej. ?canal=directo en la URL); sin esto la variable quedaba en 'total' igual.
		var pillActiva = canalGroup.querySelector('.ac-seg-pill-activo');
		if (pillActiva) canalFiltroActual = pillActiva.dataset.canal;
		actualizarTituloExportar();
		Array.prototype.forEach.call(canalGroup.querySelectorAll('.ac-seg-pill'), function (btn) {
			btn.addEventListener('click', function () {
				if (btn.dataset.canal === canalFiltroActual) return;
				canalFiltroActual = btn.dataset.canal;
				Array.prototype.forEach.call(canalGroup.querySelectorAll('.ac-seg-pill'), function (b) {
					b.classList.toggle('ac-seg-pill-activo', b === btn);
				});
				actualizarTituloExportar();
				cargarHistorial(1);
			});
		});
	} else {
		actualizarTituloExportar();
	}

	// ---------- Listado: búsqueda + filtro de período (trimestre + año + firma) + paginación ----------
	function cargarHistorial(pagina) {
		var miReqId = ++historialReqId;
		var q          = buscarInput.value.trim();
		var trimestre  = trimestreSelect.value;
		var anio       = anioSelect.value;
		var filtrosQs  = '&trimestre=' + encodeURIComponent(trimestre) + '&anio=' + encodeURIComponent(anio) +
			'&canal=' + encodeURIComponent(canalFiltroActual);
		var url = 'getters/listar_historial.php?q=' + encodeURIComponent(q) + filtrosQs +
			'&firma=' + encodeURIComponent(firmaFiltroActual) + '&pg=' + (pagina || 1);

		// Los 2 links de export apuntan a lo filtrado en pantalla, salvo firma (el Excel no distingue firmada) y canal (cada link elige el suyo propio).
		if (exportarDirectoLink) exportarDirectoLink.href = 'getters/exportar_cuota_categoria.php?canal=directo&q=' + encodeURIComponent(q) + '&trimestre=' + encodeURIComponent(trimestre) + '&anio=' + encodeURIComponent(anio);
		if (exportarDistribuidorLink) exportarDistribuidorLink.href = 'getters/exportar_cuota_categoria.php?canal=distribuidor&q=' + encodeURIComponent(q) + '&trimestre=' + encodeURIComponent(trimestre) + '&anio=' + encodeURIComponent(anio);

		// Feedback de carga: ícono de Actualizar gira + overlay sobre la tabla mientras el fetch está en curso, sin importar qué lo haya disparado.
		acBotonCargando(actualizarBtn, true);
		acMostrarCargando(tablaCard);

		fetch(url)
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (miReqId !== historialReqId) return; // respuesta vieja, ya se disparó otra búsqueda/filtro/página — ignorar.
				if (!data.ok) return;
				tbody.innerHTML = data.filas;
				paginacionEl.dataset.pagina = data.pagina;
				paginacionEl.dataset.totalPaginas = data.total_paginas;
				var infoHtml = 'Mostrando <strong>' + data.mostrando + '</strong> de <strong>' + data.total + '</strong> acuerdos';
				paginacionInfoEls.forEach(function (el) { if (el) el.innerHTML = infoHtml; });
				renderPaginacionBtns(data.pagina, data.total_paginas);
				if (data.stats) renderStats(data.stats);
			})
			.catch(function () {
				if (miReqId !== historialReqId) return;
				mostrarToast('Error de conexión al cargar el historial.', 'error');
			})
			.finally(function () {
				if (miReqId !== historialReqId) return;
				acBotonCargando(actualizarBtn, false);
				acOcultarCargando(tablaCard);
			});
	}

	function renderPaginacionBtns(pagina, totalPaginas) {
		var html = '';
		html += '<button type="button" class="ac-page-btn" data-pg="' + (pagina - 1) + '" ' + (pagina <= 1 ? 'disabled' : '') + '>' +
			'<span class="material-symbols-outlined">chevron_left</span></button>';
		for (var i = 1; i <= totalPaginas; i++) {
			html += '<button type="button" class="ac-page-btn' + (i === pagina ? ' ac-page-btn-active' : '') + '" data-pg="' + i + '">' + i + '</button>';
		}
		html += '<button type="button" class="ac-page-btn" data-pg="' + (pagina + 1) + '" ' + (pagina >= totalPaginas ? 'disabled' : '') + '>' +
			'<span class="material-symbols-outlined">chevron_right</span></button>';

		paginacionBtnsEls.forEach(function (contenedor) {
			if (!contenedor) return;
			contenedor.innerHTML = html;
			Array.prototype.forEach.call(contenedor.querySelectorAll('.ac-page-btn'), function (btn) {
				btn.addEventListener('click', function () {
					if (!btn.disabled) cargarHistorial(parseInt(btn.dataset.pg, 10));
				});
			});
		});
	}

	buscarInput.addEventListener('input', function () {
		clearTimeout(buscarTimeout);
		buscarTimeout = setTimeout(function () { cargarHistorial(1); }, 350);
	});
	trimestreSelect.addEventListener('change', function () { cargarHistorial(1); });
	anioSelect.addEventListener('change', function () { cargarHistorial(1); });
	buscarBtn.addEventListener('click', function () { cargarHistorial(1); });

	// Reusa la misma data que la campanita del header, pero solo "mías". No se llama desde cargarHistorial() (se dispara en cada tecla) a propósito, sería una consulta de más por cada una; se llama solo al entrar al módulo y al refrescar.
	function diasCortosHist(dias) {
		dias = parseInt(dias, 10);
		if (dias <= 0) return 'hoy';
		if (dias === 1) return '1 día';
		return dias + ' días';
	}
	function cargarBannerVencimiento() {
		var miReqId = ++bannerReqId;
		fetch('getters/alertas_firma.php')
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (miReqId !== bannerReqId) return;
				var mias = (data.ok && data.mias) ? data.mias : [];
				if (!mias.length) { histBanner.hidden = true; return; }

				var masUrgente = mias[0]; // ya vienen ordenados ASC por dias_restantes.
				var hayCritico = mias.some(function (a) { return parseInt(a.dias_restantes, 10) <= 1; });
				histBanner.hidden = false;
				histBanner.classList.toggle('ac-hist-banner-critica', hayCritico);
				histBanner.classList.toggle('ac-hist-banner-urgente', !hayCritico);
				histBannerText.textContent = mias.length === 1
					? '#' + masUrgente.documento_no + ' — Sube la firma: quedan ' + diasCortosHist(masUrgente.dias_restantes) + '.'
					: mias.length + ' Actas por vencer — la más próxima, #' + masUrgente.documento_no + ', quedan ' + diasCortosHist(masUrgente.dias_restantes) + '.';
				histBannerCta.textContent = mias.length === 1 ? 'Ver Acta' : 'Ver todas';
				histBannerCta.onclick = mias.length === 1
					? function () { abrirDetalle(masUrgente.id); }
					: function () {
						firmaFiltroActual = 'pendientes';
						actualizarTilesActivos();
						cargarHistorial(1);
					};
			})
			.catch(function () {
				if (miReqId !== bannerReqId) return;
				histBanner.hidden = true;
			});
	}
	cargarBannerVencimiento();

	// A diferencia de "Nuevo Acuerdo", esto no reinicia nada: solo vuelve a pedir los mismos datos por si algo cambió (otra pestaña/sesión).
	function refrescarHistorial() {
		cargarHistorial(parseInt(paginacionEl.dataset.pagina, 10) || 1);
		cargarBannerVencimiento();
	}
	document.getElementById('hist-actualizar').addEventListener('click', refrescarHistorial);
	// Expuesto para que index.php refresque este módulo al navegar hacia él desde el sidebar.
	window.acHistorialRefrescar = refrescarHistorial;

	function irARegistrar() {
		var link = document.querySelector('.ac-sidebar-nav a[href="#sec-registrar"]');
		if (link) link.click();
	}

	document.getElementById('hist-nuevo-acuerdo').addEventListener('click', irARegistrar);

	// ---------- Mis Borradores ---------- El listado y el modal viven acá; cargar el borrador en el formulario lo hace registrar.js (el estado de las 4 tablas vive ahí).
	var borraModalOverlay = document.getElementById('hist-borradores-modal-overlay');
	var borraBody = document.getElementById('hist-borradores-body');

	function abrirModalBorradores() {
		borraBody.innerHTML = '<tr><td colspan="5" class="ac-table-empty">Cargando...</td></tr>';
		borraModalOverlay.classList.add('ac-modal-open');
		fetch('getters/listar_borradores.php')
			.then(function (r) { return r.json(); })
			.then(function (data) {
				var borradores = (data.ok && data.borradores) ? data.borradores : [];
				if (!borradores.length) {
					borraBody.innerHTML = '<tr><td colspan="5" class="ac-table-empty">No tenés borradores guardados.</td></tr>';
					return;
				}
				borraBody.innerHTML = borradores.map(function (b) {
					var fecha = new Date(b.updated_at.replace(' ', 'T')).toLocaleString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
					return '<tr>' +
						'<td>#' + escapeHtml(b.documento_no) + '</td>' +
						'<td>' + escapeHtml(b.distribuidor) + '</td>' +
						'<td>' + escapeHtml(b.periodo) + ' ' + b.anio + '</td>' +
						'<td class="ac-tabular">' + fecha + '</td>' +
						'<td class="ac-text-right">' +
							'<div class="ac-row-actions">' +
								'<button type="button" class="ac-btn-continuar" data-id="' + b.id + '">Continuar editando</button>' +
								'<button type="button" class="ac-icon-btn ac-icon-btn-danger ac-btn-eliminar-borrador" data-id="' + b.id + '" data-doc="' + escapeHtml(b.documento_no) + '" title="Eliminar borrador">' +
									'<span class="material-symbols-outlined">delete</span>' +
								'</button>' +
							'</div>' +
						'</td>' +
						'</tr>';
				}).join('');
				Array.prototype.forEach.call(borraBody.querySelectorAll('.ac-btn-continuar'), function (btn) {
					btn.addEventListener('click', function () {
						var id = parseInt(btn.dataset.id, 10);
						cerrarModalBorradores();
						irARegistrar();
						if (window.acRegistrarCargarBorrador) window.acRegistrarCargarBorrador(id);
					});
				});
				Array.prototype.forEach.call(borraBody.querySelectorAll('.ac-btn-eliminar-borrador'), function (btn) {
					btn.addEventListener('click', function () {
						var fila = btn.closest('tr');
						confirmarYEliminarAcuerdo(btn.dataset.id, btn.dataset.doc, function () {
							animarYQuitarFila(fila, 5, 'No tenés borradores guardados.');
						});
					});
				});
			})
			.catch(function () {
				borraBody.innerHTML = '<tr><td colspan="5" class="ac-table-empty">Error al cargar los borradores.</td></tr>';
			});
	}

	function cerrarModalBorradores() {
		borraModalOverlay.classList.remove('ac-modal-open');
	}

	document.getElementById('hist-abrir-borradores').addEventListener('click', abrirModalBorradores);
	document.getElementById('hist-borradores-modal-close').addEventListener('click', cerrarModalBorradores);
	borraModalOverlay.addEventListener('click', function (e) {
		if (e.target === borraModalOverlay) cerrarModalBorradores();
	});

	// ---------- Detalle / Acta (Ver Detalles y Descargar PDF) ---------- Mismo PDF real que Registrar: "Ver Detalles" y "Descargar PDF" abren el mismo iframe, sin una segunda maqueta HTML que mantener.
	function mostrarEstadoDetalleCanvas(mensaje) {
		detalleCanvasEstado.textContent = mensaje;
		detalleCanvasEstado.classList.remove('hidden');
		detalleCanvas.classList.add('hidden');
	}
	function abrirDetalle(id) {
		// &t= evita que el navegador reuse un PDF viejo cacheado con la misma URL ?id=X.
		var url = 'getters/generar_acta_pdf.php?id=' + encodeURIComponent(id) + '&t=' + Date.now();
		// Móvil real: mismo arreglo que "Subir Acta Firmada", ver pdf-preview.js.
		if (window.matchMedia('(max-width: 760px)').matches) {
			pdfFrame.src = '';
			pdfFrame.classList.add('hidden');
			detalleCanvasWrap.classList.remove('hidden');
			mostrarEstadoDetalleCanvas('Cargando vista previa…');
			window.acRenderizarPdfEnCanvas(url, detalleCanvas, { contenedor: detalleCanvasWrap })
				.then(function () {
					detalleCanvasEstado.classList.add('hidden');
					detalleCanvas.classList.remove('hidden');
				})
				.catch(function () {
					mostrarEstadoDetalleCanvas('No se pudo mostrar la vista previa. Usa "Descargar / Imprimir PDF" para verla.');
				});
		} else {
			detalleCanvasWrap.classList.add('hidden');
			pdfFrame.classList.remove('hidden');
			// #toolbar=0&navpanes=0&zoom=page-width: sin esto el visor nativo arranca en zoom "automático", chiquito en un iframe angosto de mobile. "page-width" fuerza que la página ocupe todo el ancho; el usuario igual puede seguir con pinch-zoom nativo.
			pdfFrame.src = url + '#toolbar=0&navpanes=0&zoom=page-width';
		}
		descargarBtn.href = url;
		vistaLista.classList.add('hidden');
		vistaPreview.classList.remove('hidden');
		window.scrollTo(0, 0);
	}

	function eliminarAcuerdo(id, documentoNo) {
		confirmarYEliminarAcuerdo(id, documentoNo, function () {
			cargarHistorial(parseInt(paginacionEl.dataset.pagina, 10) || 1);
		});
	}

	// ---------- Subir/ver Acta firmada ---------- Modal con 2 paneles: Acta generada (izquierda, referencia) y Acta firmada (derecha). Un solo componente sirve para "ver" y "subir nueva".
	var firmaModalOverlay  = document.getElementById('hist-firma-modal-overlay');
	var firmaModalTitle    = document.getElementById('hist-firma-modal-title');
	var firmaOriginalFrame = document.getElementById('hist-firma-original-frame');
	var firmaOriginalCanvasWrap   = document.getElementById('hist-firma-original-canvas-wrap');
	var firmaOriginalCanvas       = document.getElementById('hist-firma-original-canvas');
	var firmaOriginalCanvasEstado = document.getElementById('hist-firma-original-canvas-estado');
	var firmaPreviewArea   = document.getElementById('hist-firma-preview-area');
	var firmaModalHint     = document.getElementById('hist-firma-modal-hint');
	var firmaElegirBtn     = document.getElementById('hist-firma-elegir-btn');
	var firmaGuardarBtn    = document.getElementById('hist-firma-guardar-btn');
	var firmaFileInput     = document.getElementById('hist-firma-file-input');
	var firmaAmpliarOriginalBtn = document.getElementById('hist-firma-ampliar-original');
	var firmaAmpliarFirmadaBtn  = document.getElementById('hist-firma-ampliar-firmada');
	var firmaZoomControles = document.getElementById('hist-firma-zoom-controls');
	var firmaZoomOutBtn    = document.getElementById('hist-firma-zoom-out');
	var firmaZoomInBtn     = document.getElementById('hist-firma-zoom-in');
	var firmaZoomLabel     = document.getElementById('hist-firma-zoom-label');

	var firmaAcuerdoIdActual = null;
	var firmaArchivoElegido  = null;
	var firmaObjectUrl       = null;
	var firmaOriginalUrlActual = ''; // el PDF real (botón "Ampliar" siempre abre esto) — el iframe puede cargar otra URL en móvil, ver abrirModalFirma().
	var firmaFirmadaUrlActual  = ''; // igual, para el panel derecho (Ampliar cuando se está mostrando un canvas en vez de un iframe/img).
	var firmaGuardando       = false; // guarda contra doble click/doble submit al guardar.

	var HTML_BOTON_GUARDAR = '<span class="material-symbols-outlined">save</span> Guardar Acta Firmada';

	// Zoom del panel "Acta Firmada" con rueda del mouse o los botones — transform:scale sobre el img/iframe/canvas que haya adentro, funciona igual para los 3. transform-origin se recalcula en cada rueda con la posición del mouse, para que el zoom crezca hacia donde apunta, no siempre desde el centro.
	var zoomFirmada = 1;
	function aplicarZoomFirmada() {
		firmaZoomLabel.textContent = Math.round(zoomFirmada * 100) + '%';
		var el = firmaPreviewArea.querySelector('img, iframe, canvas');
		if (el) el.style.transform = 'scale(' + zoomFirmada + ')';
	}
	function ajustarZoomFirmada(delta) {
		zoomFirmada = Math.min(3, Math.max(0.5, zoomFirmada + delta));
		aplicarZoomFirmada();
	}
	function centrarOrigenZoomFirmada() {
		var el = firmaPreviewArea.querySelector('img, iframe, canvas');
		if (el) el.style.transformOrigin = '50% 50%';
	}
	firmaZoomInBtn.addEventListener('click', function () { centrarOrigenZoomFirmada(); ajustarZoomFirmada(0.2); });
	firmaZoomOutBtn.addEventListener('click', function () { centrarOrigenZoomFirmada(); ajustarZoomFirmada(-0.2); });
	firmaPreviewArea.addEventListener('wheel', function (e) {
		if (firmaZoomControles.classList.contains('hidden')) return;
		e.preventDefault();
		var el = firmaPreviewArea.querySelector('img, iframe, canvas');
		if (el) {
			var r = el.getBoundingClientRect();
			if (r.width && r.height) {
				el.style.transformOrigin = (((e.clientX - r.left) / r.width) * 100) + '% ' + (((e.clientY - r.top) / r.height) * 100) + '%';
			}
		}
		ajustarZoomFirmada(e.deltaY < 0 ? 0.15 : -0.15);
	}, { passive: false });

	function mostrarControlesFirmada() {
		zoomFirmada = 1;
		aplicarZoomFirmada();
		centrarOrigenZoomFirmada();
		firmaAmpliarFirmadaBtn.classList.remove('hidden');
		firmaZoomControles.classList.remove('hidden');
	}
	function ocultarControlesFirmada() {
		firmaAmpliarFirmadaBtn.classList.add('hidden');
		firmaZoomControles.classList.add('hidden');
	}

	function firmaPreviewVacia(mensaje) {
		firmaPreviewArea.innerHTML = '<div class="ac-firma-preview-vacio">' +
			'<span class="material-symbols-outlined">add_a_photo</span>' +
			'<p>' + escapeHtml(mensaje) + '</p></div>';
		ocultarControlesFirmada();
	}

	// Comprime fotos ANTES de subir: nginx rechaza con 413 fotos pesadas, límite de infraestructura no editable desde este repo. Prueba escalones cada vez más chicos hasta entrar bajo un límite conservador; PDF se sube tal cual, si falla sube el original sin comprimir.
	function nombreComoJpg(archivo) { return archivo.name.replace(/\.[^.]+$/, '') + '.jpg'; }
	function comprimirFotoSiHaceFalta(archivo) {
		if (archivo.type.indexOf('image/') !== 0) return Promise.resolve(archivo);
		// Bien por debajo de 1MB (default real de nginx sin configurar): deja margen para el overhead del multipart/FormData.
		var LIMITE_SEGURO = 500 * 1024;
		var escalones = [
			{ lado: 1280, calidad: 0.6 },
			{ lado: 1000, calidad: 0.5 },
			{ lado: 800,  calidad: 0.4 },
			{ lado: 640,  calidad: 0.35 },
			{ lado: 480,  calidad: 0.3 },
			{ lado: 360,  calidad: 0.25 }
		];
		return new Promise(function (resolve) {
			var url = URL.createObjectURL(archivo);
			var img = new Image();
			img.onload = function () {
				URL.revokeObjectURL(url);
				var mejorBlob = null;
				var i = 0;
				function intentar() {
					if (i >= escalones.length) {
						// Ni el escalón más chico entró en el límite: se usa el más liviano logrado, siempre mejor que el archivo original.
						resolve(mejorBlob ? new File([mejorBlob], nombreComoJpg(archivo), { type: 'image/jpeg' }) : archivo);
						return;
					}
					var cfg = escalones[i++];
					var escala = Math.min(1, cfg.lado / Math.max(img.width, img.height));
					var canvas = document.createElement('canvas');
					canvas.width = Math.max(1, Math.round(img.width * escala));
					canvas.height = Math.max(1, Math.round(img.height * escala));
					canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
					canvas.toBlob(function (blob) {
						if (!blob) { intentar(); return; }
						if (!mejorBlob || blob.size < mejorBlob.size) mejorBlob = blob;
						if (blob.size <= LIMITE_SEGURO) {
							resolve(new File([blob], nombreComoJpg(archivo), { type: 'image/jpeg' }));
							return;
						}
						intentar();
					}, 'image/jpeg', cfg.calidad);
				}
				intentar();
			};
			img.onerror = function () { URL.revokeObjectURL(url); resolve(archivo); };
			img.src = url;
		});
	}

	// PDF en el panel derecho: desktop usa <iframe> de siempre; móvil real dibuja con PDF.js en <canvas> (mismo arreglo que el panel izquierdo) — sin esto salía la "sub-ventanita" rota que muestra Chrome de Android cuando no puede embeber un PDF.
	function mostrarPdfEnPanelFirmada(url) {
		if (window.matchMedia('(max-width: 760px)').matches) {
			firmaPreviewArea.innerHTML = '<div class="ac-firma-canvas-wrap"><canvas></canvas><div class="ac-firma-canvas-estado">Cargando vista previa…</div></div>';
			var wrap = firmaPreviewArea.querySelector('.ac-firma-canvas-wrap');
			var canvas = wrap.querySelector('canvas');
			var estado = wrap.querySelector('.ac-firma-canvas-estado');
			window.acRenderizarPdfEnCanvas(url, canvas, { contenedor: wrap })
				.then(function () { estado.classList.add('hidden'); })
				.catch(function () { estado.textContent = 'No se pudo mostrar la vista previa. Usa "Ampliar" para verla en una pestaña nueva.'; });
		} else {
			firmaPreviewArea.innerHTML = '<iframe title="Acta firmada"></iframe>';
			firmaPreviewArea.querySelector('iframe').src = url;
		}
	}

	function mostrarPreviewArchivoElegido(archivo) {
		if (firmaObjectUrl) URL.revokeObjectURL(firmaObjectUrl);
		firmaObjectUrl = URL.createObjectURL(archivo);
		firmaFirmadaUrlActual = firmaObjectUrl;
		if (archivo.type === 'application/pdf') {
			mostrarPdfEnPanelFirmada(firmaObjectUrl);
		} else {
			firmaPreviewArea.innerHTML = '<img alt="Vista previa del archivo elegido">';
			firmaPreviewArea.querySelector('img').src = firmaObjectUrl;
		}
		mostrarControlesFirmada();
	}

	// Foto → <img> (se ajusta/centra con object-fit); PDF → ver mostrarPdfEnPanelFirmada(). Antes siempre usaba <iframe>, una imagen se mostraba a tamaño natural pegada arriba sin centrar.
	function mostrarFirmaYaSubida(id, mime) {
		var url = 'getters/descargar_acta_firmada.php?id=' + encodeURIComponent(id) + '&t=' + Date.now();
		firmaFirmadaUrlActual = url;
		if (mime && mime.indexOf('image/') === 0) {
			firmaPreviewArea.innerHTML = '<img alt="Acta firmada ya subida">';
			firmaPreviewArea.querySelector('img').src = url;
		} else {
			mostrarPdfEnPanelFirmada(url);
		}
		mostrarControlesFirmada();
	}

	// Render vía PDF.js (assets/js/pdf-preview.js) para móvil real, donde un PDF en <iframe> no renderiza.
	function mostrarEstadoCanvasOriginal(mensaje) {
		firmaOriginalCanvasEstado.textContent = mensaje;
		firmaOriginalCanvasEstado.classList.remove('hidden');
		firmaOriginalCanvas.classList.add('hidden');
	}
	function renderizarFirmaOriginalCanvas(url) {
		mostrarEstadoCanvasOriginal('Cargando vista previa…');
		window.acRenderizarPdfEnCanvas(url, firmaOriginalCanvas, { contenedor: firmaOriginalCanvasWrap })
			.then(function () {
				firmaOriginalCanvasEstado.classList.add('hidden');
				firmaOriginalCanvas.classList.remove('hidden');
			})
			.catch(function (e) {
				// DIAGNÓSTICO TEMPORAL: sacar este alert() en cuanto se identifique la causa real en celular.
				alert(
					'[Vista previa PDF] ' + (e && e.name ? e.name : 'Error') + ': ' + (e && e.message ? e.message : e) + '\n' +
					'pdfjsLib cargado: ' + (!!window.pdfjsLib) + '\n' +
					'canvas soportado: ' + (!!(document.createElement('canvas').getContext)) + '\n' +
					'UA: ' + navigator.userAgent
				);
				mostrarEstadoCanvasOriginal('No se pudo mostrar la vista previa. Usa "Ampliar" para verla en una pestaña nueva.');
			});
	}

	// Acta Generada siempre es PDF: "ampliar" abre el PDF real en pestaña nueva. Acta Firmada puede ser foto (lightbox global) o PDF (pestaña nueva).
	firmaAmpliarOriginalBtn.addEventListener('click', function () {
		if (firmaOriginalUrlActual) window.open(firmaOriginalUrlActual, '_blank');
	});
	firmaAmpliarFirmadaBtn.addEventListener('click', function () {
		var img = firmaPreviewArea.querySelector('img');
		if (img && img.src) { window.acAbrirLightbox(img.src); return; }
		if (firmaFirmadaUrlActual) window.open(firmaFirmadaUrlActual, '_blank');
	});

	function abrirModalFirma(id, documentoNo, tieneFirma, mime) {
		firmaAcuerdoIdActual = id;
		firmaArchivoElegido = null;
		firmaGuardando = false;
		firmaModalTitle.textContent = 'Acta Firmada — #' + documentoNo;
		// El botón "Ampliar" siempre abre el PDF real, independiente de si el panel muestra el iframe o el canvas.
		firmaOriginalUrlActual = 'getters/generar_acta_pdf.php?id=' + encodeURIComponent(id) + '&t=' + Date.now();
		// Móvil real: un PDF embebido en <iframe> no renderiza en Chrome de Android, así que se dibuja con PDF.js en un <canvas> en su lugar. Mismo breakpoint que este modal usa para apilar los 2 paneles (@media max-width:760px).
		if (window.matchMedia('(max-width: 760px)').matches) {
			firmaOriginalFrame.src = '';
			firmaOriginalFrame.classList.add('hidden');
			firmaOriginalCanvasWrap.classList.remove('hidden');
			renderizarFirmaOriginalCanvas(firmaOriginalUrlActual);
		} else {
			firmaOriginalCanvasWrap.classList.add('hidden');
			firmaOriginalFrame.classList.remove('hidden');
			firmaOriginalFrame.src = firmaOriginalUrlActual;
		}
		firmaElegirBtn.disabled = false;
		firmaGuardarBtn.disabled = true;
		firmaGuardarBtn.innerHTML = HTML_BOTON_GUARDAR;

		if (tieneFirma) {
			mostrarFirmaYaSubida(id, mime);
			firmaModalHint.textContent = 'Ya hay un archivo subido. Elige uno nuevo para reemplazarlo.';
		} else {
			firmaPreviewVacia('Selecciona una foto o PDF del Acta firmada para compararla acá');
			firmaModalHint.textContent = 'Sin archivo subido todavía.';
		}
		firmaModalOverlay.classList.add('ac-modal-open');
	}

	function cerrarModalFirma() {
		firmaModalOverlay.classList.remove('ac-modal-open');
		firmaOriginalFrame.src = '';
		if (firmaObjectUrl) { URL.revokeObjectURL(firmaObjectUrl); firmaObjectUrl = null; }
		firmaPreviewArea.innerHTML = '';
		firmaArchivoElegido = null;
		firmaAcuerdoIdActual = null;
	}

	firmaElegirBtn.addEventListener('click', function () {
		firmaFileInput.value = '';
		firmaFileInput.click();
	});

	firmaFileInput.addEventListener('change', function () {
		var archivoOriginal = firmaFileInput.files[0];
		if (!archivoOriginal) return;
		firmaModalHint.textContent = 'Preparando el archivo…';
		comprimirFotoSiHaceFalta(archivoOriginal).then(function (archivoFinal) {
			firmaArchivoElegido = archivoFinal;
			mostrarPreviewArchivoElegido(archivoFinal);
			firmaGuardarBtn.disabled = false;
			firmaModalHint.textContent = archivoFinal.name +
				(archivoFinal !== archivoOriginal ? ' (comprimida automáticamente, ' + Math.round(archivoFinal.size / 1024) + ' KB)' : '');
		});
	});

	firmaGuardarBtn.addEventListener('click', function () {
		if (firmaGuardando || !firmaArchivoElegido || !firmaAcuerdoIdActual) return;
		firmaGuardando = true;
		firmaGuardarBtn.disabled = true;
		firmaElegirBtn.disabled = true;
		firmaGuardarBtn.innerHTML = '<span class="material-symbols-outlined">progress_activity</span> Guardando...';

		var formData = new FormData();
		formData.append('id', firmaAcuerdoIdActual);
		formData.append('archivo', firmaArchivoElegido);

		function restaurarBotones() {
			firmaGuardando = false;
			firmaElegirBtn.disabled = false;
			firmaGuardarBtn.disabled = false;
			firmaGuardarBtn.innerHTML = HTML_BOTON_GUARDAR;
		}

		// DIAGNÓSTICO TEMPORAL: lee la respuesta como texto crudo antes de JSON.parse() para mostrar HTTP status y cuerpo real si no es JSON válido. Sacar este alert() (volver a `.then(r => r.json())` simple) en cuanto se identifique la causa real en celular.
		fetch('getters/subir_acta_firmada.php', { method: 'POST', body: formData })
			.then(function (r) {
				return r.text().then(function (texto) { return { status: r.status, ok: r.ok, texto: texto }; });
			})
			.then(function (resp) {
				var data;
				try {
					data = JSON.parse(resp.texto);
				} catch (errParse) {
					alert(
						'[Guardar Acta Firmada] La respuesta no es JSON válido.\n' +
						'Archivo enviado: ' + firmaArchivoElegido.name + ', ' + firmaArchivoElegido.type + ', ' + Math.round(firmaArchivoElegido.size / 1024) + ' KB\n' +
						'HTTP status: ' + resp.status + ' (ok=' + resp.ok + ')\n' +
						'Primeros 800 caracteres de la respuesta:\n' + resp.texto.slice(0, 800)
					);
					mostrarToast('Error de conexión. Intenta nuevamente.', 'error');
					restaurarBotones();
					return;
				}
				mostrarToast(data.message, data.ok ? 'success' : 'error');
				if (data.ok) {
					cerrarModalFirma();
					cargarHistorial(parseInt(paginacionEl.dataset.pagina, 10) || 1);
				} else {
					restaurarBotones();
				}
			})
			.catch(function (e) {
				alert('[Guardar Acta Firmada] Falló el fetch en sí (red/CORS/timeout).\n' + (e && e.name ? e.name : 'Error') + ': ' + (e && e.message ? e.message : e));
				mostrarToast('Error de conexión. Intenta nuevamente.', 'error');
				restaurarBotones();
			});
	});

	document.getElementById('hist-firma-modal-close').addEventListener('click', cerrarModalFirma);
	firmaModalOverlay.addEventListener('click', function (e) {
		if (e.target === firmaModalOverlay) cerrarModalFirma();
	});

	tbody.addEventListener('click', function (e) {
		var verBtn = e.target.closest('.hist-btn-ver');
		var descargarBtn2 = e.target.closest('.hist-btn-descargar');
		var eliminarBtn = e.target.closest('.hist-btn-eliminar');
		var firmaBtn = e.target.closest('.hist-btn-firma');
		if (verBtn) abrirDetalle(verBtn.dataset.id);
		else if (descargarBtn2) abrirDetalle(descargarBtn2.dataset.id);
		else if (eliminarBtn) eliminarAcuerdo(eliminarBtn.dataset.id, eliminarBtn.dataset.doc);
		else if (firmaBtn) abrirModalFirma(firmaBtn.dataset.id, firmaBtn.dataset.doc, firmaBtn.dataset.tieneFirma === '1', firmaBtn.dataset.mime);
	});

	document.getElementById('hist-volver-lista').addEventListener('click', function () {
		vistaPreview.classList.add('hidden');
		vistaLista.classList.remove('hidden');
		pdfFrame.src = '';
	});

	renderPaginacionBtns(parseInt(paginacionEl.dataset.pagina, 10) || 1, parseInt(paginacionEl.dataset.totalPaginas, 10) || 1);
})();

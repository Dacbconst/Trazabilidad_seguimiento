// Mismos paths que ep_icon() en PHP, solo para los íconos que este archivo re-renderiza en JS.
function epIconMarkup(nombre, size) {
	var paths = {
		'chevron': '<path d="M6 9l6 6 6-6"/>',
		'chevron-up': '<path d="M18 15l-6-6-6 6"/>',
		'trash': '<path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-8 0l1 13a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2l1-13"/>',
		'camera': '<rect x="3" y="6" width="18" height="14" rx="2"/><circle cx="12" cy="13" r="3.2"/><path d="M8 6l1.5-2h5L16 6"/>',
		'close': '<path d="M6 6l12 12M18 6L6 18"/>',
	};
	return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' + (paths[nombre] || '') + '</svg>';
}

// Título estilo "Actividad 2": primera letra de cada palabra en mayúscula, sin importar cómo se tipeó.
function epFormatoTitulo(texto) {
	return (texto || '').trim().replace(/\s+/g, ' ').toLowerCase().replace(/(^|\s)([a-záéíóúñ])/g, function (m, sep, letra) {
		return sep + letra.toUpperCase();
	});
}

document.addEventListener('DOMContentLoaded', function () {
	// Selección de actividad (módulo Actividades)
	var listaActividades = document.getElementById('ep-lista-actividades');
	if (listaActividades) {
		var labelSeleccion = document.getElementById('ep-seleccion-label');
		listaActividades.addEventListener('click', function (ev) {
			var btn = ev.target.closest('.ep-activity-item');
			if (!btn) return;
			listaActividades.querySelectorAll('.ep-activity-item').forEach(function (el) {
				el.classList.remove('selected');
			});
			btn.classList.add('selected');
			if (labelSeleccion) labelSeleccion.textContent = btn.dataset.nombre || '';
			mostrarFormularioDeActividad(btn.dataset.renderId || btn.dataset.id);
			// Elegir otra actividad mientras "Nueva actividad" está abierto vuelve al formulario normal.
			mostrarFormulario();
			// Centra suavemente el chip seleccionado en la tira horizontal móvil
			btn.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
		});
	}

	// Cambia cuál de las plantillas (una por actividad, renderizadas todas en el servidor) se ve, formulario y estadísticas juntos.
	function mostrarFormularioDeActividad(id) {
		document.querySelectorAll('.ep-formulario-actividad, .ep-estadisticas-actividad, .ep-evidencia-actividad').forEach(function (el) {
			el.classList.toggle('hidden', el.dataset.actividadId !== id);
		});
		// Cambiar de actividad abandona cualquier sesión de Competencia a medias (lo ya guardado no se pierde, solo la lista en pantalla).
		if (typeof epCompetenciaReiniciarCompletados === 'function') epCompetenciaReiniciarCompletados();
		var estadisticaVisible = document.querySelector('.ep-estadisticas-actividad[data-actividad-id="' + id + '"]');
		var layout = document.getElementById('ep-actividad-layout');
		if (layout && estadisticaVisible) {
			var sinStats = estadisticaVisible.dataset.sinEstadisticas === '1';
			layout.classList.toggle('ep-actividad-layout-sin-stats', sinStats);
			var tabMetricas = document.getElementById('epMobileTabMetricas');
			if (tabMetricas) {
				tabMetricas.classList.toggle('hidden', sinStats);
				if (sinStats && layout.getAttribute('data-mobile-tab') === 'metricas') {
					activarTabMovil('formulario');
				}
			}
		}
		actualizarContadorFotosMovil();
	}

	// Control de pestañas móvil para formulario, evidencia fotográfica y métricas en vivo.
	var epMobileTabs = document.getElementById('epMobileTabs');
	var epActividadLayout = document.getElementById('ep-actividad-layout');
	function activarTabMovil(tab) {
		if (!epMobileTabs || !epActividadLayout) return;
		epMobileTabs.querySelectorAll('.ep-mobile-tab').forEach(function (b) {
			b.classList.toggle('active', b.dataset.tab === tab);
		});
		epActividadLayout.setAttribute('data-mobile-tab', tab);
		window.scrollTo({ top: 0, behavior: 'smooth' });
		// En móvil las fotos se suben solo con el asistente: al entrar a la pestaña se abre solo si aún faltan fotos.
		if (tab === 'fotos' && window.matchMedia('(max-width: 900px)').matches) {
			actualizarContadorFotosMovil();
			var bloqueFotos = document.querySelector('.ep-evidencia-actividad:not(.hidden)');
			if (bloqueFotos && !bloqueFotos.classList.contains('ep-evidencia-completa')) {
				setTimeout(abrirWizardFotos, 150);
			}
		}
	}
	function actualizarContadorFotosMovil() {
		var bloqueEv = document.querySelector('#ep-panel-evidencia .ep-evidencia-actividad:not(.hidden)') || document.querySelector('.ep-evidencia-actividad:not(.hidden)');
		var slots = bloqueEv ? bloqueEv.querySelectorAll('.ep-foto-slot:not([data-opcional])') : [];
		var count = bloqueEv ? bloqueEv.querySelectorAll('.ep-foto-slot-completa:not([data-opcional])').length : 0;
		var total = slots.length;
		if (bloqueEv) {
			var completo = total > 0 && count >= total;
			bloqueEv.classList.toggle('ep-evidencia-completa', completo);
			bloqueEv.querySelectorAll('.ep-evidencia-actividad').forEach(function (el) { el.classList.toggle('ep-evidencia-completa', completo); });
		}

		var badge = document.getElementById('epMobileTabFotosCount');
		if (badge) badge.textContent = count;

		// Sincronizar contador y barra del paso
		var desktopCount = bloqueEv ? (bloqueEv.querySelector('.ep-desktop-flow-meter-lbl') || document.getElementById('epDesktopFotoCount')) : document.getElementById('epDesktopFotoCount');
		var desktopFill = bloqueEv ? (bloqueEv.querySelector('.ep-desktop-flow-meter-bar') || document.getElementById('epDesktopFotoProgressFill')) : document.getElementById('epDesktopFotoProgressFill');
		var desktopBadge = bloqueEv ? (bloqueEv.querySelector('.ep-desktop-flow-status-pill') || document.getElementById('epDesktopFlowBadge')) : document.getElementById('epDesktopFlowBadge');

		if (desktopCount) {
			desktopCount.textContent = count + ' de ' + total + ' listas';
		}
		if (desktopFill && total > 0) {
			var pct = Math.round((count / total) * 100);
			desktopFill.style.width = pct + '%';
			desktopFill.style.background = (pct === 100) ? '#137A3E' : '#3D2768';
		}
		if (desktopBadge) {
			if (total > 0 && count >= total) {
				desktopBadge.textContent = '✓ Completa (' + count + '/' + total + ')';
				desktopBadge.className = 'ep-desktop-flow-status-pill completado';
			} else {
				desktopBadge.textContent = 'Pendiente (' + (total - count) + ' faltantes)';
				desktopBadge.className = 'ep-desktop-flow-status-pill pendiente';
			}
		}
	}
	function enviarRegistroActividad() {
		var btnPrincipal = document.getElementById('epBtnEnviarRegistro') || document.querySelector('.ep-btn-primary');
		if (btnPrincipal) btnPrincipal.click();
	}
	if (epMobileTabs && epActividadLayout) {
		epActividadLayout.setAttribute('data-mobile-tab', 'formulario');
		epMobileTabs.addEventListener('click', function (ev) {
			var btn = ev.target.closest('.ep-mobile-tab');
			if (!btn) return;
			if (btn.dataset.tab === 'fotos' && epActividadLayout.getAttribute('data-mobile-tab') === 'formulario') { avisarDatosFaltantes(function () { activarTabMovil('fotos'); }); return; }
			activarTabMovil(btn.dataset.tab);
		});
		// Delegación para botones de apertura del asistente fotográfico
		document.addEventListener('click', function (ev) {
			var btn = ev.target.closest('.ep-btn-desktop-start-wizard, #epBtnDesktopStartWizard, .ep-btn-reabrir-wizard, .ep-btn-reabrir-wizard-desktop');
			if (!btn) return;
			if (btn.matches('.ep-btn-desktop-start-wizard, #epBtnDesktopStartWizard')) { avisarDatosFaltantes(abrirWizardFotos); return; }
			abrirWizardFotos();
		});
		var btnIrAFotos = document.getElementById('epBtnIrAFotos');
		if (btnIrAFotos) {
			btnIrAFotos.addEventListener('click', function () { avisarDatosFaltantes(function () { activarTabMovil('fotos'); }); });
		}
		var btnVolverAFotos = document.getElementById('epBtnVolverAFotos');
		if (btnVolverAFotos) {
			btnVolverAFotos.addEventListener('click', function () { activarTabMovil('fotos'); });
		}
		var btnEnviarDesdeMetricas = document.getElementById('epBtnEnviarDesdeMetricas');
		if (btnEnviarDesdeMetricas) {
			btnEnviarDesdeMetricas.addEventListener('click', function () { enviarRegistroActividad(); });
		}
	}

	// Asistente interactivo guiado para subir fotos paso a paso en móvil
	var wizardOverlay = document.getElementById('epWizardFotosOverlay');
	var wizardPasoTexto = document.getElementById('epWizardPasoTexto');
	var wizardTrackSegmentos = document.getElementById('epWizardTrackSegmentos');
	var wizardTituloFoto = document.getElementById('epWizardTituloFoto');
	var wizardVisor = document.getElementById('epWizardVisor');
	var wizardVisorVacio = document.getElementById('epWizardVisorVacio');
	var wizardVisorPreview = document.getElementById('epWizardVisorPreview');
	var wizardPreviewImg = document.getElementById('epWizardPreviewImg');
	var wizardBtnCambiar = document.getElementById('epWizardBtnCambiar');
	var wizardReel = document.getElementById('epWizardReel');
	var wizardBtnAnterior = document.getElementById('epWizardBtnAnterior');
	var wizardBtnSiguiente = document.getElementById('epWizardBtnSiguiente');
	var wizardBtnSigTexto = document.getElementById('epWizardBtnSigTexto');
	var wizardBtnCerrar = document.getElementById('epWizardBtnCerrar');
	var wizardSidebarList = document.getElementById('epWizardSidebarList');
	var wizardSidebarCount = document.getElementById('epWizardSidebarCount');
	var wizardDescripcion = document.getElementById('epWizardDescripcion');
	var wizardDescripcionTexto = document.getElementById('epWizardDescripcionTexto');
	var wizardDescripcionCuenta = document.getElementById('epWizardDescripcionCuenta');
	// "Agregar otra foto": uno bajo la tira (celular) y otro en la lista de requerimientos (escritorio).
	var wizardBtnsAgregar = Array.prototype.slice.call(document.querySelectorAll('.ep-wizard-agregar'));
	var wizardBtnQuitarCasilla = document.getElementById('epWizardBtnQuitarCasilla');

	var wizardSlotsActuales = [];
	var wizardPasoActual = 0;

	// Descripción por foto (Competencia): la casilla trae su propio cuadro de texto; las demás actividades no.
	function descripcionDeSlot(slot) {
		return slot ? slot.querySelector('.ep-foto-descripcion') : null;
	}
	function slotTieneFoto(slot) {
		return !!slot && (slot.classList.contains('ep-foto-slot-completa') || !!slot._blob || !!slot.dataset.fotoRuta);
	}
	function slotFaltaDescripcion(slot) {
		var d = descripcionDeSlot(slot);
		return !!d && slotTieneFoto(slot) && d.value.trim() === '';
	}

	function obtenerSlotsActividadVisible() {
		var bloqueVisible = document.querySelector('.ep-formulario-actividad:not(.hidden) .ep-evidencia-actividad') || document.querySelector('.ep-evidencia-actividad:not(.hidden)');
		if (!bloqueVisible) return [];
		return Array.from(bloqueVisible.querySelectorAll('.ep-foto-slot'));
	}

	// Competencia: un solo registro por sesión, con varios puntos de venta adentro (cada uno con sus propias fotos).
	// "Añadir otro punto de venta" NO manda nada al servidor: sube las fotos de este punto (ya quedan en Azure) y lo
	// guarda en memoria; recién "Enviar registro" arma el registro completo con todos los puntos juntos.
	var epCompetenciaPuntos = [];
	function epCompetenciaEscapar(t) {
		return String(t == null ? '' : t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
	}
	function renderizarCompetenciaCompletados() {
		var cont = document.getElementById('epCompetenciaCompletados');
		if (!cont) return;
		cont.innerHTML = epCompetenciaPuntos.map(function (p) {
			var totalFotos = Object.keys(p.fotos).length;
			return '<div class="ep-competencia-completado-fila">'
				+ '<span class="ep-competencia-completado-check"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></span>'
				+ '<span class="ep-competencia-completado-info"><span class="ep-competencia-completado-nombre">' + epCompetenciaEscapar(p.nombre) + '</span><span class="ep-competencia-completado-sub">' + totalFotos + ' foto' + (totalFotos === 1 ? '' : 's') + '</span></span>'
				+ '<span class="ep-competencia-completado-badge">Listo</span>'
				+ '</div>';
		}).join('');
	}
	function epCompetenciaReiniciarCompletados() {
		epCompetenciaPuntos = [];
		renderizarCompetenciaCompletados();
	}
	// Deja el punto de venta y las fotos en blanco, listos para el siguiente punto (las casillas sumadas con "Agregar foto" se quitan).
	function epCompetenciaReiniciarFormulario() {
		if (window.epPdv && window.epPdv.limpiar) window.epPdv.limpiar();
		var bloqueEv = document.querySelector('.ep-evidencia-actividad:not(.hidden)');
		if (!bloqueEv) return;
		Array.prototype.slice.call(bloqueEv.querySelectorAll('.ep-foto-slot')).forEach(function (slot) {
			if (slot.dataset.extra) quitarCasillaExtra(slot); else quitarFotoDeSlot(slot);
		});
	}
	// Fotos subidas + descripciones del punto que está activo ahora mismo en el formulario (lo usan tanto "Añadir otro" como el envío final).
	function epCompetenciaPuntoActivo() {
		var bloqueEv = document.querySelector('.ep-evidencia-actividad:not(.hidden)');
		var slotsFotos = bloqueEv ? Array.prototype.slice.call(bloqueEv.querySelectorAll('.ep-foto-slot')) : [];
		var pdvElegido = window.epPdv.elegido();
		var descripciones = {};
		slotsFotos.forEach(function (s) {
			var d = descripcionDeSlot(s);
			if (d && slotTieneFoto(s)) descripciones[s.dataset.fotoId] = d.value.trim();
		});
		return { slotsFotos: slotsFotos, pdvElegido: pdvElegido, nombre: pdvElegido ? pdvElegido.nombre : '', descripciones: descripciones };
	}
	var epBtnCompetenciaOtroPunto = document.getElementById('epBtnCompetenciaOtroPunto');
	if (epBtnCompetenciaOtroPunto) {
		epBtnCompetenciaOtroPunto.addEventListener('click', function () {
			if (!validarRegistroActivo()) return;
			var activo = epCompetenciaPuntoActivo();
			if (activo.slotsFotos.some(function (s) { return s.dataset.subiendo; })) {
				epAviso('info', 'Preparando fotos', 'Hay fotos preparándose todavía. Espera unos segundos e intenta de nuevo.');
				return;
			}
			epBtnCompetenciaOtroPunto.disabled = true;
			var textoBtn = epBtnCompetenciaOtroPunto.querySelector('span');
			if (textoBtn) textoBtn.textContent = 'Subiendo fotos...';
			subirFotosPendientes(activo.slotsFotos).then(function (fotos) {
				epCompetenciaPuntos.push({ pos_id: activo.pdvElegido ? activo.pdvElegido.pos_id : '', nombre: activo.nombre, fotos: fotos, descripciones: activo.descripciones });
				renderizarCompetenciaCompletados();
				epCompetenciaReiniciarFormulario();
			}).catch(function () {
				epAviso('error', 'No se pudieron subir las fotos', 'Intenta de nuevo.');
			}).then(function () {
				epBtnCompetenciaOtroPunto.disabled = false;
				if (textoBtn) textoBtn.textContent = 'Añadir otro punto de venta';
			});
		});
	}

	function abrirWizardFotos() {
		if (!wizardOverlay) return;
		wizardSlotsActuales = obtenerSlotsActividadVisible();
		if (wizardSlotsActuales.length === 0) {
			return;
		}
		var primerIncompleto = wizardSlotsActuales.findIndex(function (slot) {
			return !slot.classList.contains('ep-foto-slot-completa');
		});
		wizardPasoActual = primerIncompleto !== -1 ? primerIncompleto : 0;
		wizardOverlay.classList.remove('hidden');
		document.body.style.overflow = 'hidden';
		renderizarWizard();
	}

	function cerrarWizardFotos() {
		if (!wizardOverlay) return;
		wizardOverlay.classList.add('hidden');
		document.body.style.overflow = '';
	}

	function renderizarWizard() {
		var total = wizardSlotsActuales.length;
		if (total === 0) return;
		var idx = wizardPasoActual;
		var slot = wizardSlotsActuales[idx];

		if (wizardPasoTexto) wizardPasoTexto.textContent = 'Foto ' + (idx + 1) + ' de ' + total;
		// Al pasar a otra foto el cuerpo vuelve arriba, para que se vea su título.
		var cuerpoWizard = wizardOverlay.querySelector('.ep-wizard-body');
		if (cuerpoWizard && cuerpoWizard.dataset.paso !== String(idx)) cuerpoWizard.scrollTop = 0;
		if (cuerpoWizard) cuerpoWizard.dataset.paso = idx;

		if (wizardTrackSegmentos) {
			wizardTrackSegmentos.innerHTML = '';
			for (var i = 0; i < total; i++) {
				var seg = document.createElement('div');
				var comp = wizardSlotsActuales[i].classList.contains('ep-foto-slot-completa');
				seg.className = 'ep-wizard-seg' + (i === idx ? ' activo' : '') + (comp ? ' completado' : '');
				wizardTrackSegmentos.appendChild(seg);
			}
		}

		var esOpc = function (x) { return x.hasAttribute('data-opcional'); };
		var requeridas = wizardSlotsActuales.filter(function (x) { return !esOpc(x); });
		var requeridasListas = requeridas.every(function (x) { return x.classList.contains('ep-foto-slot-completa'); });
		var ultimaRequerida = requeridas.length - 1;
		var labelEl = slot.querySelector('.ep-foto-slot-label');
		var labelTexto = labelEl ? labelEl.textContent.trim() : ('Foto ' + (idx + 1));
		if (wizardTituloFoto) wizardTituloFoto.textContent = labelTexto;

		var previewSlot = slot.querySelector('.ep-foto-preview');
		var tieneFoto = slot.classList.contains('ep-foto-slot-completa') && previewSlot && previewSlot.src;
		if (tieneFoto) {
			if (wizardPreviewImg) wizardPreviewImg.src = previewSlot.src;
			if (wizardVisorPreview) wizardVisorPreview.classList.remove('hidden');
			if (wizardVisorVacio) wizardVisorVacio.classList.add('hidden');
		} else {
			if (wizardVisorPreview) wizardVisorPreview.classList.add('hidden');
			if (wizardVisorVacio) wizardVisorVacio.classList.remove('hidden');
		}

		// Sincronizar tira de miniaturas inferior
		if (wizardReel) {
			wizardReel.innerHTML = '';
			for (var j = 0; j < total; j++) {
				var btnReel = document.createElement('button');
				btnReel.type = 'button';
				var completado = wizardSlotsActuales[j].classList.contains('ep-foto-slot-completa');
				btnReel.className = 'ep-wizard-reel-item' + (j === idx ? ' activo' : '') + (completado ? ' completado' : '');
				btnReel.innerHTML = completado ? '✓ ' + (j + 1) : (j + 1);
				btnReel.dataset.index = j;
				btnReel.addEventListener('click', function () {
					wizardPasoActual = parseInt(this.dataset.index, 10);
					renderizarWizard();
				});
				wizardReel.appendChild(btnReel);
			}
		}

		// Sincronizar checklist lateral de requerimientos para Desktop
		if (wizardSidebarList) {
			wizardSidebarList.innerHTML = '';
			var totalComp = 0;
			for (var k = 0; k < total; k++) {
				var sK = wizardSlotsActuales[k];
				var lK = sK.querySelector('.ep-foto-slot-label');
				var txtK = lK ? lK.textContent.trim() : ('Foto ' + (k + 1));
				var isDone = sK.classList.contains('ep-foto-slot-completa');
				if (isDone && !esOpc(sK)) totalComp++;

				var itemDiv = document.createElement('div');
				itemDiv.className = 'ep-wizard-sidebar-item' + (k === idx ? ' activo' : '') + (isDone ? ' completado' : '');
				itemDiv.dataset.index = k;

				var numSpan = document.createElement('span');
				numSpan.className = 'ep-wizard-sidebar-num' + (isDone ? ' done' : '');
				numSpan.textContent = isDone ? '✓' : (k + 1);

				var infoDiv = document.createElement('div');
				infoDiv.className = 'ep-wizard-sidebar-item-info';
				var estadoK = isDone ? (slotFaltaDescripcion(sK) ? 'Falta descripción' : '✓ Cargada') : (esOpc(sK) ? 'Opcional' : 'Pendiente');
				infoDiv.innerHTML = '<strong>' + txtK + '</strong><span>' + estadoK + '</span>';

				itemDiv.appendChild(numSpan);
				itemDiv.appendChild(infoDiv);

				itemDiv.addEventListener('click', function () {
					wizardPasoActual = parseInt(this.dataset.index, 10);
					renderizarWizard();
				});

				wizardSidebarList.appendChild(itemDiv);
			}
			if (wizardSidebarCount) {
				wizardSidebarCount.textContent = totalComp + '/' + requeridas.length;
			}
		}

		// Descripción de la foto actual: se escribe aquí y se copia a la casilla; se habilita recién con la foto cargada.
		var descSlot = descripcionDeSlot(slot);
		if (wizardDescripcion) {
			wizardDescripcion.classList.toggle('hidden', !descSlot);
			if (descSlot && wizardDescripcionTexto) {
				wizardDescripcionTexto.value = descSlot.value;
				wizardDescripcionTexto.disabled = !tieneFoto;
				wizardDescripcionTexto.placeholder = tieneFoto ? 'Ej. Canon da un bono de $10 por cada G3110 vendida en Super Paco' : 'Primero sube la foto';
				wizardDescripcionTexto.classList.remove('ep-campo-error');
				if (wizardDescripcionCuenta) wizardDescripcionCuenta.textContent = descSlot.value.length + '/' + wizardDescripcionTexto.maxLength;
			}
		}
		var bloqueWizard = slot.closest('.ep-evidencia-bloque-card');
		var sePuedeAgregar = !!(bloqueWizard && bloqueWizard.querySelector('.ep-btn-agregar-foto'));
		wizardBtnsAgregar.forEach(function (b) { b.classList.toggle('hidden', !sePuedeAgregar); });
		if (wizardBtnQuitarCasilla) wizardBtnQuitarCasilla.classList.toggle('hidden', !slot.dataset.extra);

		if (wizardBtnAnterior) {
			wizardBtnAnterior.disabled = (idx === 0);
		}
		if (wizardBtnSigTexto) {
			if (idx === total - 1 || (requeridasListas && idx >= ultimaRequerida)) {
				wizardBtnSigTexto.textContent = 'Finalizar y revisar';
			} else {
				wizardBtnSigTexto.textContent = 'Siguiente foto';
			}
		}
	}

	function dispararCapturaActual() {
		var slot = wizardSlotsActuales[wizardPasoActual];
		if (!slot) return;
		var input = slot.querySelector('.ep-foto-input');
		if (input) input.click();
	}

	if (wizardVisorVacio) wizardVisorVacio.addEventListener('click', dispararCapturaActual);
	if (wizardBtnCambiar) wizardBtnCambiar.addEventListener('click', dispararCapturaActual);
	if (wizardBtnCerrar) wizardBtnCerrar.addEventListener('click', cerrarWizardFotos);
	if (wizardBtnAnterior) {
		wizardBtnAnterior.addEventListener('click', function () {
			if (wizardPasoActual > 0) {
				wizardPasoActual--;
				renderizarWizard();
			}
		});
	}
	if (wizardDescripcionTexto) {
		wizardDescripcionTexto.addEventListener('input', function () {
			var descSlot = descripcionDeSlot(wizardSlotsActuales[wizardPasoActual]);
			if (!descSlot) return;
			descSlot.value = wizardDescripcionTexto.value;
			descSlot.classList.remove('ep-campo-error');
			wizardDescripcionTexto.classList.remove('ep-campo-error');
			if (wizardDescripcionCuenta) wizardDescripcionCuenta.textContent = wizardDescripcionTexto.value.length + '/' + wizardDescripcionTexto.maxLength;
		});
		// Al salir del cuadro se refresca la lista lateral ("Falta descripción" → "Cargada") sin redibujar mientras se escribe.
		wizardDescripcionTexto.addEventListener('change', renderizarWizard);
	}
	wizardBtnsAgregar.forEach(function (b) {
		b.addEventListener('click', function () {
			var actual = wizardSlotsActuales[wizardPasoActual];
			var bloque = actual ? actual.closest('.ep-evidencia-bloque-card') : null;
			var btnPagina = bloque ? bloque.querySelector('.ep-btn-agregar-foto') : null;
			if (!btnPagina) return;
			btnPagina.click();
			wizardSlotsActuales = obtenerSlotsActividadVisible();
			wizardPasoActual = wizardSlotsActuales.length - 1;
			renderizarWizard();
		});
	});
	if (wizardBtnQuitarCasilla) {
		wizardBtnQuitarCasilla.addEventListener('click', function () {
			quitarCasillaExtra(wizardSlotsActuales[wizardPasoActual]);
		});
	}
	if (wizardBtnSiguiente) {
		wizardBtnSiguiente.addEventListener('click', function () {
			// Con foto cargada y sin descripción no se avanza: es lo que va de pie en la presentación.
			if (slotFaltaDescripcion(wizardSlotsActuales[wizardPasoActual])) {
				if (wizardDescripcionTexto) {
					wizardDescripcionTexto.classList.add('ep-campo-error');
					wizardDescripcionTexto.focus();
				}
				epToast('warning', 'Falta la descripción de la foto');
				return;
			}
			var total = wizardSlotsActuales.length;
			var reqs = wizardSlotsActuales.filter(function (x) { return !x.hasAttribute('data-opcional'); });
			var reqsListas = reqs.every(function (x) { return x.classList.contains('ep-foto-slot-completa'); });
			if (wizardPasoActual < total - 1 && !(reqsListas && wizardPasoActual >= reqs.length - 1)) {
				wizardPasoActual++;
				renderizarWizard();
			} else {
				cerrarWizardFotos();
			}
		});
	}

	// Tipo (plantilla) de la actividad activa, ya viene en el botón — no adivinar por el nombre (rompía con nombres como "Exhibiciones Regulares").
	function tipoActividadActiva() {
		var item = document.querySelector('.ep-activity-item.selected');
		return (item && item.dataset.plantilla) || 'activaciones';
	}

	// Deja cada foto liviana (objetivo ~120 KB, máx. 1000px): son miles de fotos que luego irán a presentaciones PPT, el peso manda.
	var FOTO_PASOS = [[1000, 0.72], [900, 0.65], [800, 0.6], [720, 0.55], [640, 0.5]];
	var FOTO_OBJETIVO_BYTES = 120 * 1024;
	function comprimirFoto(archivo) {
		return new Promise(function (resolve) {
			var img = new Image();
			var url = URL.createObjectURL(archivo);
			img.onerror = function () { URL.revokeObjectURL(url); resolve(archivo); };
			img.onload = function () {
				URL.revokeObjectURL(url);
				var mejor = null;
				function probar(i) {
					var paso = FOTO_PASOS[i];
					var escala = Math.min(1, paso[0] / Math.max(img.width, img.height));
					var canvas = document.createElement('canvas');
					canvas.width = Math.round(img.width * escala);
					canvas.height = Math.round(img.height * escala);
					var ctx = canvas.getContext('2d');
					ctx.fillStyle = '#FFFFFF'; // fondo blanco: un PNG con transparencia saldría negro en JPEG
					ctx.fillRect(0, 0, canvas.width, canvas.height);
					ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
					canvas.toBlob(function (blob) {
						if (blob) mejor = blob;
						if (blob && blob.size <= FOTO_OBJETIVO_BYTES || i === FOTO_PASOS.length - 1) {
							resolve(mejor && (mejor.size < archivo.size || archivo.type !== 'image/jpeg') ? mejor : archivo);
						} else {
							probar(i + 1);
						}
					}, 'image/jpeg', paso[1]);
				}
				probar(0);
			};
			img.src = url;
		});
	}

	// Al elegir la foto solo se comprime y se guarda en memoria; la subida a Azure ocurre al enviar el registro.
	function prepararFotoDeSlot(archivo, slot) {
		var estado = slot.querySelector('.ep-foto-slot-estado');
		function marcar(texto, ok) {
			if (!estado) return;
			estado.textContent = texto;
			estado.classList.toggle('ep-hist-badge-ok', !!ok);
		}
		slot.dataset.fotoRuta = '';
		slot._blob = null;
		slot.dataset.subiendo = '1';
		marcar('Preparando...', false);
		comprimirFoto(archivo).then(function (blob) {
			slot._blob = blob;
			slot.dataset.subiendo = '';
			marcar('Cargada', true);
		}).catch(function () {
			slot.dataset.subiendo = '';
			slot.classList.remove('ep-foto-slot-completa');
			marcar('Error', false);
			epToast('error', 'No se pudo preparar la foto. Elige otra.');
		});
	}

	// Sube UNA foto ya preparada a Azure (carpeta de Epson) y guarda su ruta en el slot.
	function subirFotoSlot(slot) {
		var fd = new FormData();
		fd.append('archivo', slot._blob, 'foto.jpg');
		fd.append('tipo', tipoActividadActiva());
		fd.append('foto_id', slot.dataset.fotoId || 'foto');
		return fetch('getters/subir_foto.php', { method: 'POST', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data && data.success) { slot.dataset.fotoRuta = data.path; return; }
				var e = new Error((data && data.error) || 'No se pudo subir la foto.');
				e.foto = true;
				e.redirect = data && data.redirect;
				throw e;
			});
	}

	// Sube todas las fotos pendientes (una por una) mostrando el avance; devuelve el mapa id de foto -> ruta.
	function subirFotosPendientes(slots) {
		var pendientes = slots.filter(function (s) { return s._blob && !s.dataset.fotoRuta; });
		var hechas = 0;
		if (pendientes.length && window.Swal) {
			Swal.fire({ title: 'Subiendo fotos', html: '<span id="epSubidaProg">0 de ' + pendientes.length + '</span>', allowOutsideClick: false, showConfirmButton: false, didOpen: function () { Swal.showLoading(); } });
		}
		var seguir = Promise.resolve();
		pendientes.forEach(function (s) {
			seguir = seguir.then(function () {
				return subirFotoSlot(s).then(function () {
					hechas++;
					var prog = document.getElementById('epSubidaProg');
					if (prog) prog.textContent = hechas + ' de ' + pendientes.length;
				});
			});
		});
		return seguir.then(function () {
			if (pendientes.length && window.Swal) Swal.close();
			var fotos = {};
			slots.forEach(function (s) { if (s.dataset.fotoRuta) fotos[s.dataset.fotoId] = s.dataset.fotoRuta; });
			return fotos;
		});
	}

	// Función reutilizable para procesar archivo soltado o cargado
	function cargarArchivoEnSlot(archivo, slot) {
		if (!archivo || !archivo.type.startsWith('image/')) return;
		var preview = slot.querySelector('.ep-foto-preview');
		var vacio = slot.querySelector('.ep-foto-dropzone-vacio');
		var estado = slot.querySelector('.ep-foto-slot-estado');
		if (preview) {
			preview.src = URL.createObjectURL(archivo);
			preview.classList.remove('hidden');
		}
		if (vacio) vacio.classList.add('hidden');
		slot.classList.add('ep-foto-slot-completa');
		if (estado) {
			estado.textContent = 'Cargada';
			estado.classList.add('ep-hist-badge-ok');
		}

		prepararFotoDeSlot(archivo, slot);

		var bloque = slot.closest('.ep-evidencia-bloque');
		var contador = bloque ? bloque.querySelector('.ep-evidencia-contador') : null;
		if (contador) contador.textContent = bloque.querySelectorAll('.ep-foto-slot-completa').length;
		actualizarContadorFotosMovil();
		avanzarWizardTrasFoto(slot);
	}

	// Con el asistente abierto: refresca el visor y pasa solo a la siguiente foto; si la casilla pide descripción, se queda para escribirla.
	function avanzarWizardTrasFoto(slot) {
		if (!wizardOverlay || wizardOverlay.classList.contains('hidden')) return;
		renderizarWizard();
		if (descripcionDeSlot(slot)) {
			if (wizardDescripcionTexto) wizardDescripcionTexto.focus();
			return;
		}
		if (wizardPasoActual < wizardSlotsActuales.length - 1) {
			setTimeout(function () {
				wizardPasoActual++;
				renderizarWizard();
			}, 550);
		}
	}

	// Deja el slot como si nunca hubiera tenido foto (lo usa el visor de fotos al quitar).
	function quitarFotoDeSlot(slot) {
		var preview = slot.querySelector('.ep-foto-preview');
		var vacio = slot.querySelector('.ep-foto-dropzone-vacio');
		var estado = slot.querySelector('.ep-foto-slot-estado');
		var input = slot.querySelector('.ep-foto-input');
		if (preview) { preview.removeAttribute('src'); preview.classList.add('hidden'); }
		if (vacio) vacio.classList.remove('hidden');
		if (input) input.value = '';
		slot.classList.remove('ep-foto-slot-completa');
		slot.dataset.fotoRuta = '';
		slot.dataset.subiendo = '';
		slot._blob = null;
		// La descripción hablaba de esa foto: se va con ella (al "Cambiar foto" sí se conserva).
		var desc = descripcionDeSlot(slot);
		if (desc) { desc.value = ''; desc.classList.remove('ep-campo-error'); }
		if (estado) { estado.textContent = 'Pendiente'; estado.classList.remove('ep-hist-badge-ok'); }
		actualizarContadorFotosMovil();
		if (wizardOverlay && !wizardOverlay.classList.contains('hidden')) renderizarWizard();
	}
	window.epFotos = { slots: obtenerSlotsActividadVisible, quitar: quitarFotoDeSlot };

	// Para "Corregir y reenviar": deja en una casilla una foto ya subida (su ruta viaja tal cual al reenviar).
	function ponerFotoSubida(slot, url, ruta) {
		var preview = slot.querySelector('.ep-foto-preview');
		var vacio = slot.querySelector('.ep-foto-dropzone-vacio');
		var estado = slot.querySelector('.ep-foto-slot-estado');
		if (preview) { preview.src = url; preview.classList.remove('hidden'); }
		if (vacio) vacio.classList.add('hidden');
		slot.classList.add('ep-foto-slot-completa');
		slot.dataset.fotoRuta = ruta;
		if (estado) { estado.textContent = 'Cargada'; estado.classList.add('ep-hist-badge-ok'); }
		var bloque = slot.closest('.ep-evidencia-bloque');
		var contador = bloque ? bloque.querySelector('.ep-evidencia-contador') : null;
		if (contador) contador.textContent = bloque.querySelectorAll('.ep-foto-slot-completa').length;
		actualizarContadorFotosMovil();
	}
	window.epRelleno = { foto: ponerFotoSubida, modelos: function (prefijo, lista) { var g = { act: actModelos, eday: edayModelos, evento: eventoModelos }[prefijo]; if (g) g.cargar(lista); } };

	// Quita del todo una casilla sumada con "Agregar foto" (las fijas no se quitan); su foto, si la tenía, no se envía.
	function quitarCasillaExtra(slot) {
		if (!slot || !slot.dataset.extra) return;
		slot.remove();
		actualizarContadorFotosMovil();
		if (!wizardOverlay || wizardOverlay.classList.contains('hidden')) return;
		wizardSlotsActuales = obtenerSlotsActividadVisible();
		wizardPasoActual = Math.min(wizardPasoActual, wizardSlotsActuales.length - 1);
		renderizarWizard();
	}
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.ep-foto-quitar-casilla');
		if (btn) quitarCasillaExtra(btn.closest('.ep-foto-slot'));
	});

	// Drag & Drop nativo en el Visor del Asistente
	if (wizardVisor) {
		wizardVisor.addEventListener('dragover', function (e) {
			e.preventDefault();
			wizardVisor.classList.add('drag-active');
		});
		wizardVisor.addEventListener('dragleave', function (e) {
			e.preventDefault();
			wizardVisor.classList.remove('drag-active');
		});
		wizardVisor.addEventListener('drop', function (e) {
			e.preventDefault();
			wizardVisor.classList.remove('drag-active');
			var files = e.dataTransfer && e.dataTransfer.files;
			if (files && files.length > 0) {
				var slot = wizardSlotsActuales[wizardPasoActual];
				if (slot) cargarArchivoEnSlot(files[0], slot);
			}
		});
	}

	// Drag & Drop en slots directos de la página
	document.addEventListener('dragover', function (e) {
		var dz = e.target.closest('.ep-foto-dropzone');
		if (dz) {
			e.preventDefault();
			dz.classList.add('drag-active');
		}
	});
	document.addEventListener('dragleave', function (e) {
		var dz = e.target.closest('.ep-foto-dropzone');
		if (dz) {
			e.preventDefault();
			dz.classList.remove('drag-active');
		}
	});
	document.addEventListener('drop', function (e) {
		var dz = e.target.closest('.ep-foto-dropzone');
		if (dz) {
			e.preventDefault();
			dz.classList.remove('drag-active');
			var slot = dz.closest('.ep-foto-slot');
			var files = e.dataTransfer && e.dataTransfer.files;
			if (slot && files && files.length > 0) {
				cargarArchivoEnSlot(files[0], slot);
			}
		}
	});

	// "Agregar foto": suma una casilla opcional más, con la misma marca que las fijas (los eventos de arriba son delegados, no hace falta re-wiring).
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.ep-btn-agregar-foto');
		if (!btn) return;
		var grid = btn.closest('.ep-evidencia-bloque-card').querySelector('.ep-evidencia-grid-panoramica');
		var n = parseInt(btn.dataset.siguiente, 10) || 1;
		var fotoId = 'foto-' + n;
		var inputId = 'ep-foto-' + btn.dataset.prefix + '-' + fotoId;
		var etiqueta = escapeHtml(btn.dataset.label || 'Foto adicional');
		grid.insertAdjacentHTML('beforeend', '<div class="ep-foto-slot" data-foto-id="' + fotoId + '" data-opcional="1" data-extra="1">'
			+ '<button type="button" class="ep-foto-quitar-casilla" title="Quitar esta casilla" aria-label="Quitar esta casilla">' + epIconMarkup('close', 14) + '</button>'
			+ '<label class="ep-foto-dropzone" title="Subir foto: ' + etiqueta + '">'
			+ '<input type="file" accept="image/*" class="ep-foto-input" id="' + inputId + '" hidden>'
			+ '<img class="ep-foto-preview hidden" alt="' + etiqueta + '">'
			+ '<span class="ep-foto-dropzone-vacio"><span class="ep-foto-slot-icon">' + epIconMarkup('camera', 22) + '</span><span class="ep-foto-slot-action">Subir foto</span></span>'
			+ '</label>'
			+ '<div class="ep-foto-slot-info"><span class="ep-foto-slot-label">' + etiqueta + '</span><span class="ep-hist-badge ep-foto-slot-estado">Pendiente</span></div>'
			+ '</div>');
		// Si las casillas de esta actividad llevan descripción, la nueva también (copia vacía de la primera).
		var modeloDesc = grid.querySelector('.ep-foto-descripcion');
		if (modeloDesc) {
			var desc = modeloDesc.cloneNode(false);
			desc.value = '';
			desc.classList.remove('ep-campo-error');
			desc.setAttribute('aria-label', 'Descripción de ' + (btn.dataset.label || 'la foto'));
			grid.lastElementChild.appendChild(desc);
		}
		btn.dataset.siguiente = n + 1;
	});

	// Evidencia fotográfica: genérico para cualquier actividad, reacciona a cualquier .ep-foto-input sin wiring por actividad.
	document.addEventListener('change', function (ev) {
		if (!ev.target.classList.contains('ep-foto-input')) return;
		var input = ev.target;
		var archivo = input.files && input.files[0];
		if (!archivo) return;
		cargarArchivoEnSlot(archivo, input.closest('.ep-foto-slot'));
	});

	// Campos numéricos de todos los formularios (incluidos los creados dinámicamente): solo enteros, máximo 3 dígitos y sin ceros a la izquierda (014 pasa a 14).
	document.addEventListener('keydown', function (ev) {
		if (ev.target.type !== 'number') return;
		if (['e', 'E', '+', '-', '.', ','].indexOf(ev.key) !== -1) ev.preventDefault();
	});
	document.addEventListener('input', function (ev) {
		var campo = ev.target;
		if (campo.type !== 'number') return;
		var limpio = String(campo.value).replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, 3);
		if (campo.value !== limpio) campo.value = limpio;
	}, true);

	// Marca en amarillo un instante el campo que se acaba de topar, para que se note que el sistema lo corrigió solo.
	function destacarTope(campo) {
		campo.classList.remove('ep-input-tope-flash');
		void campo.offsetWidth; // fuerza el reflow para poder re-disparar la animación si se topa dos veces seguidas
		campo.classList.add('ep-input-tope-flash');
	}

	// Formulario de Activaciones: ningún paso del embudo puede superar al anterior (ni realizadas a programadas)
	function aplicarTope(base, dependiente) {
		function clamp() {
			var max = parseFloat(base.value) || 0;
			dependiente.max = max;
			if ((parseFloat(dependiente.value) || 0) > max) {
				dependiente.value = max;
				destacarTope(dependiente);
				dependiente.dispatchEvent(new Event('input')); // encadena el tope al siguiente campo, si lo tiene
			}
		}
		base.addEventListener('input', clamp);
		dependiente.addEventListener('input', clamp);
		clamp();
	}
	var actNacional = document.getElementById('ep-act-nacional');
	var actCoberturadas = document.getElementById('ep-act-coberturadas');
	var actVisitaron = document.getElementById('ep-act-visitaron');
	var actInteractuaron = document.getElementById('ep-act-interactuaron');
	var actCompraron = document.getElementById('ep-act-compraron');

	// Coberturadas se tipea a mano, igual que los demás — solo no puede superar a Nacional (pedido explícito 2026-09-17).
	if (actNacional && actCoberturadas) aplicarTope(actNacional, actCoberturadas);
	if (actVisitaron && actInteractuaron) aplicarTope(actVisitaron, actInteractuaron);
	if (actInteractuaron && actCompraron) aplicarTope(actInteractuaron, actCompraron);

	// Cualquier campo de Activaciones cambia algo del panel de estadísticas de al lado.
	[actNacional, actCoberturadas, actVisitaron, actInteractuaron, actCompraron].forEach(function (input) {
		if (input) input.addEventListener('input', actualizarEstadisticasActivaciones);
	});
	var actComentarios = document.getElementById('ep-act-comentarios');
	if (actComentarios) actComentarios.addEventListener('input', actualizarEstadisticasActivaciones);

	// Panel "Así se ve el reporte final": recalcula las cards del Excel en vivo con lo que hay en el formulario.
	function escapeHtml(texto) {
		var div = document.createElement('div');
		div.textContent = texto;
		return div.innerHTML;
	}
	function pctTexto(parte, total) {
		var t = parseFloat(total);
		if (!t) return '0%';
		return Math.round((parseFloat(parte) || 0) / t * 100) + '%';
	}
	function fmtDolares(valor) {
		return '$' + (valor || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}
	// Precio por modelo: hasta 3 enteros y 2 decimales (máx. 999.99); el signo $ es solo visual, aparte del campo.
	function epLimpiarPrecio(valor) {
		valor = String(valor || '').replace(',', '.').replace(/[^0-9.]/g, '');
		var punto = valor.indexOf('.');
		if (punto !== -1) valor = valor.slice(0, punto + 1) + valor.slice(punto + 1).replace(/\./g, '');
		var partes = valor.split('.');
		var entero = partes[0].slice(0, 3);
		var decimales = partes.length > 1 ? '.' + partes[1].slice(0, 2) : '';
		return entero + decimales;
	}
	// Misma barra de "Detalle de Ventas" pero en dólares generados (cantidad × precio); solo donde el modelo trae precio.
	function pintarIngresosPorModelo(contenedorId, modelos) {
		var cont = document.getElementById(contenedorId);
		if (!cont) return;
		var conIngreso = modelos.map(function (m) { return { nombre: m.nombre, ingreso: (m.cantidad || 0) * (m.precio || 0) }; })
			.filter(function (m) { return m.ingreso > 0; })
			.sort(function (a, b) { return b.ingreso - a.ingreso; });
		if (!conIngreso.length) {
			cont.innerHTML = '<span class="ep-stat-comentarios-vacio">Agrega el precio de cada modelo para ver los ingresos.</span>';
			return;
		}
		var maxIngreso = conIngreso[0].ingreso;
		cont.innerHTML = conIngreso.map(function (m) {
			return '<div class="ep-venta-fila ep-venta-fila-dinero">'
				+ '<span class="ep-venta-nombre">' + escapeHtml(m.nombre) + '</span>'
				+ '<div class="ep-venta-barra-track"><div class="ep-venta-barra-fill" style="width:' + Math.round(m.ingreso / maxIngreso * 100) + '%;"></div></div>'
				+ '<span class="ep-venta-valor">' + fmtDolares(m.ingreso) + '</span>'
				+ '</div>';
		}).join('');
	}
	function actualizarEstadisticasActivaciones() {
		var statCoberturaPct = document.getElementById('ep-stat-cobertura-pct');
		if (!statCoberturaPct) return; // esta actividad no tiene panel de estadísticas todavía

		var nacional = actNacional ? actNacional.value : 0;
		var coberturadas = actCoberturadas ? actCoberturadas.value : 0;
		var visitaron = actVisitaron ? actVisitaron.value : 0;
		var interactuaron = actInteractuaron ? actInteractuaron.value : 0;
		var compraron = actCompraron ? actCompraron.value : 0;

		statCoberturaPct.textContent = pctTexto(coberturadas, nacional);
		document.getElementById('ep-stat-nacional').textContent = nacional || 0;
		document.getElementById('ep-stat-coberturadas').textContent = coberturadas || 0;

		document.getElementById('ep-stat-interaccion-pct').textContent = pctTexto(interactuaron, visitaron);
		document.getElementById('ep-stat-visitaron').textContent = visitaron || 0;
		document.getElementById('ep-stat-interactuaron').textContent = interactuaron || 0;

		document.getElementById('ep-stat-ventas-pct').textContent = pctTexto(compraron, interactuaron);
		document.getElementById('ep-stat-ventas-realizadas').textContent = compraron || 0;

		var maxEmbudo = Math.max(parseFloat(visitaron) || 0, 1);
		document.getElementById('ep-stat-bar-visitaron-valor').textContent = visitaron || 0;
		document.getElementById('ep-stat-bar-visitaron').style.height = pctTexto(visitaron, maxEmbudo);
		document.getElementById('ep-stat-bar-interactuaron-valor').textContent = interactuaron || 0;
		document.getElementById('ep-stat-bar-interactuaron').style.height = pctTexto(interactuaron, maxEmbudo);
		document.getElementById('ep-stat-bar-compraron-valor').textContent = compraron || 0;
		document.getElementById('ep-stat-bar-compraron').style.height = pctTexto(compraron, maxEmbudo);

		var detalleVentas = document.getElementById('ep-stat-detalle-ventas');
		var modelos = (actModelos ? actModelos.modelos() : []).sort(function (a, b) { return b.cantidad - a.cantidad; });
		var totalUnidades = modelos.reduce(function (s, m) { return s + m.cantidad; }, 0);
		pintarIngresosPorModelo('ep-stat-detalle-ingresos', modelos);

		if (!modelos.length) {
			detalleVentas.innerHTML = '<span class="ep-stat-comentarios-vacio">Todavía no cargaste modelos.</span>';
		} else {
			var maxCantidad = modelos[0].cantidad;
			detalleVentas.innerHTML = modelos.map(function (m) {
				return '<div class="ep-venta-fila">'
					+ '<span class="ep-venta-nombre">' + escapeHtml(m.nombre) + '</span>'
					+ '<div class="ep-venta-barra-track"><div class="ep-venta-barra-fill" style="width:' + Math.round(m.cantidad / maxCantidad * 100) + '%;"></div></div>'
					+ '<span class="ep-venta-valor">' + m.cantidad + '</span>'
					+ '</div>';
			}).join('');
		}

		var mayorPct = document.getElementById('ep-stat-mayor-pct');
		var mayorNombre = document.getElementById('ep-stat-mayor-nombre');
		var menorPct = document.getElementById('ep-stat-menor-pct');
		var menorNombre = document.getElementById('ep-stat-menor-nombre');
		if (modelos.length) {
			var mayorCant = modelos[0].cantidad;
			var menorCant = modelos[modelos.length - 1].cantidad;
			var mayores = modelos.filter(function (m) { return m.cantidad === mayorCant; }).map(function (m) { return m.nombre; });
			var menores = modelos.filter(function (m) { return m.cantidad === menorCant; }).map(function (m) { return m.nombre; });
			mayorPct.textContent = pctTexto(mayorCant, totalUnidades);
			mayorNombre.textContent = mayores.join(' / ');
			menorPct.textContent = pctTexto(menorCant, totalUnidades);
			menorNombre.textContent = menores.join(' / ');
		} else {
			mayorPct.textContent = '0%';
			mayorNombre.textContent = 'Sin datos';
			menorPct.textContent = '0%';
			menorNombre.textContent = 'Sin datos';
		}

		var comentariosTextarea = document.getElementById('ep-act-comentarios');
		var comentariosBox = document.getElementById('ep-stat-comentarios');
		var lineas = comentariosTextarea ? comentariosTextarea.value.split('\n').map(function (l) { return l.trim(); }).filter(Boolean) : [];
		comentariosBox.innerHTML = lineas.length
			? lineas.map(function (l) { return '<div>' + escapeHtml(l) + '</div>'; }).join('')
			: '<span class="ep-stat-comentarios-vacio">Sin comentarios todavía.</span>';
	}

	var actModelos = crearGestorModelos('ep-modelo-filas', 'ep-modelo-agregar', 'ep-modelo-total-valor', actualizarEstadisticasActivaciones, true);
	actualizarEstadisticasActivaciones(); // primer cálculo, con los valores de ejemplo que ya trae el formulario

	// ---------- Capacitaciones ---------- Interacciones no puede superar el total de asistentes.
	var capAsistJefe = document.getElementById('ep-cap-asist-jefe');
	var capJefeTienda = document.getElementById('ep-cap-jefe-tienda');
	var capVendedores = document.getElementById('ep-cap-vendedores');
	var capInteracciones = document.getElementById('ep-cap-interacciones');
	var capComentarios = document.getElementById('ep-cap-comentarios');

	function actualizarTotalAsistentesCapacitaciones() {
		var total = (parseFloat(capAsistJefe && capAsistJefe.value) || 0)
			+ (parseFloat(capJefeTienda && capJefeTienda.value) || 0)
			+ (parseFloat(capVendedores && capVendedores.value) || 0);
		var total1 = document.getElementById('ep-cap-total-asistentes-1');
		var total2 = document.getElementById('ep-cap-total-asistentes-2');
		if (total1) total1.textContent = total;
		if (total2) total2.textContent = total;

		if (capInteracciones && (parseFloat(capInteracciones.value) || 0) > total) {
			capInteracciones.value = total;
			destacarTope(capInteracciones);
		}
		actualizarEstadisticasCapacitaciones();
	}

	function actualizarEstadisticasCapacitaciones() {
		var statAsistentes = document.getElementById('ep-cap-stat-asistentes');
		if (!statAsistentes) return; // esta actividad no tiene panel de estadísticas todavía

		var asistJefe = capAsistJefe ? (parseFloat(capAsistJefe.value) || 0) : 0;
		var jefeTienda = capJefeTienda ? (parseFloat(capJefeTienda.value) || 0) : 0;
		var vendedores = capVendedores ? (parseFloat(capVendedores.value) || 0) : 0;
		var total = asistJefe + jefeTienda + vendedores;
		var interacciones = capInteracciones ? (parseFloat(capInteracciones.value) || 0) : 0;

		statAsistentes.textContent = total;
		document.getElementById('ep-cap-stat-asist-jefe').textContent = asistJefe;
		document.getElementById('ep-cap-stat-jefe-tienda').textContent = jefeTienda;
		document.getElementById('ep-cap-stat-vendedores').textContent = vendedores;
		document.getElementById('ep-cap-stat-interacciones').textContent = interacciones;
		document.getElementById('ep-cap-stat-interaccion-pct').textContent = pctTexto(interacciones, total);

		var maxBarraCap = Math.max(total, 1);
		document.getElementById('ep-cap-stat-bar-asistentes-valor').textContent = total;
		document.getElementById('ep-cap-stat-bar-asistentes').style.height = pctTexto(total, maxBarraCap);
		document.getElementById('ep-cap-stat-bar-interacciones-valor').textContent = interacciones;
		document.getElementById('ep-cap-stat-bar-interacciones').style.height = pctTexto(interacciones, maxBarraCap);

		var detalleCargos = document.getElementById('ep-cap-stat-detalle-cargos');
		var cargos = [
			{ nombre: 'Vendedores', cantidad: vendedores },
			{ nombre: 'Jefe de Tienda', cantidad: jefeTienda },
			{ nombre: 'Asist. de Jefe Tienda', cantidad: asistJefe },
		].filter(function (c) { return c.cantidad > 0; }).sort(function (a, b) { return b.cantidad - a.cantidad; });

		if (!cargos.length) {
			detalleCargos.innerHTML = '<span class="ep-stat-comentarios-vacio">Todavía no cargaste asistentes.</span>';
		} else {
			var maxCargo = cargos[0].cantidad;
			detalleCargos.innerHTML = cargos.map(function (c) {
				return '<div class="ep-venta-fila">'
					+ '<span class="ep-venta-nombre">' + c.nombre + '</span>'
					+ '<div class="ep-venta-barra-track"><div class="ep-venta-barra-fill" style="width:' + Math.round(c.cantidad / maxCargo * 100) + '%;"></div></div>'
					+ '<span class="ep-venta-valor">' + c.cantidad + '</span>'
					+ '</div>';
			}).join('');
		}

		var comentariosBoxCap = document.getElementById('ep-cap-stat-comentarios');
		var lineasCap = capComentarios ? capComentarios.value.split('\n').map(function (l) { return l.trim(); }).filter(Boolean) : [];
		comentariosBoxCap.innerHTML = lineasCap.length
			? lineasCap.map(function (l) { return '<div>' + escapeHtml(l) + '</div>'; }).join('')
			: '<span class="ep-stat-comentarios-vacio">Sin comentarios todavía.</span>';
	}

	[capAsistJefe, capJefeTienda, capVendedores].forEach(function (input) {
		if (input) input.addEventListener('input', actualizarTotalAsistentesCapacitaciones);
	});
	if (capInteracciones) capInteracciones.addEventListener('input', actualizarEstadisticasCapacitaciones);
	if (capComentarios) capComentarios.addEventListener('input', actualizarEstadisticasCapacitaciones);
	actualizarEstadisticasCapacitaciones(); // primer cálculo

	// ---------- Exhibiciones que Inspiran ---------- solo detalle (barras) + comentarios, sin porcentajes ni topes.
	var exhMuebles = document.getElementById('ep-exh-muebles');
	var exhRumas = document.getElementById('ep-exh-rumas');
	var exhCabeceras = document.getElementById('ep-exh-cabeceras');
	var exhRegular = document.getElementById('ep-exh-regular');
	var exhOtras = document.getElementById('ep-exh-otras');
	var exhComentarios = document.getElementById('ep-exh-comentarios');

	function actualizarEstadisticasExhibiciones() {
		var statDetalle = document.getElementById('ep-exh-stat-detalle');
		if (!statDetalle) return; // esta actividad no tiene panel de estadísticas todavía

		var muebles = exhMuebles ? (parseFloat(exhMuebles.value) || 0) : 0;
		var rumas = exhRumas ? (parseFloat(exhRumas.value) || 0) : 0;
		var cabeceras = exhCabeceras ? (parseFloat(exhCabeceras.value) || 0) : 0;
		var regular = exhRegular ? (parseFloat(exhRegular.value) || 0) : 0;
		var otras = exhOtras ? (parseFloat(exhOtras.value) || 0) : 0;
		var total = muebles + rumas + cabeceras + regular + otras;

		var totalSpan = document.getElementById('ep-exh-total');
		if (totalSpan) totalSpan.textContent = total;

		var items = [
			{ nombre: 'Cabeceras', cantidad: cabeceras },
			{ nombre: 'Rumas', cantidad: rumas },
			{ nombre: 'Muebles', cantidad: muebles },
			{ nombre: 'Exhibición regular', cantidad: regular },
			{ nombre: 'Otras', cantidad: otras },
		].filter(function (i) { return i.cantidad > 0; }).sort(function (a, b) { return b.cantidad - a.cantidad; });

		if (!items.length) {
			statDetalle.innerHTML = '<span class="ep-stat-comentarios-vacio">Todavía no cargaste exhibiciones.</span>';
		} else {
			var maxItem = items[0].cantidad;
			statDetalle.innerHTML = items.map(function (i) {
				return '<div class="ep-venta-fila">'
					+ '<span class="ep-venta-nombre">' + i.nombre + '</span>'
					+ '<div class="ep-venta-barra-track"><div class="ep-venta-barra-fill" style="width:' + Math.round(i.cantidad / maxItem * 100) + '%;"></div></div>'
					+ '<span class="ep-venta-valor">' + i.cantidad + '</span>'
					+ '</div>';
			}).join('');
		}

		var comentariosBoxExh = document.getElementById('ep-exh-stat-comentarios');
		var lineasExh = exhComentarios ? exhComentarios.value.split('\n').map(function (l) { return l.trim(); }).filter(Boolean) : [];
		comentariosBoxExh.innerHTML = lineasExh.length
			? lineasExh.map(function (l) { return '<div>' + escapeHtml(l) + '</div>'; }).join('')
			: '<span class="ep-stat-comentarios-vacio">Sin comentarios todavía.</span>';
	}

	[exhMuebles, exhRumas, exhCabeceras, exhRegular, exhOtras].forEach(function (input) {
		if (input) input.addEventListener('input', actualizarEstadisticasExhibiciones);
	});
	if (exhComentarios) exhComentarios.addEventListener('input', actualizarEstadisticasExhibiciones);
	actualizarEstadisticasExhibiciones(); // primer cálculo

	// Catálogo de modelos (repositorio_productos): se trae una sola vez para todo el formulario y se reutiliza en cada fila; filtrar es cosa del navegador, no del servidor.
	var epCatalogoModelos = null;
	function epObtenerCatalogoModelos() {
		if (!epCatalogoModelos) {
			epCatalogoModelos = fetch('getters/repositorio_productos_buscar.php')
				.then(function (r) { return r.json(); })
				.then(function (data) { return data.ok ? data.productos : []; })
				.catch(function () { epCatalogoModelos = null; return []; }); // si falla, el próximo intento vuelve a pedirlo
		}
		return epCatalogoModelos;
	}

	// Fábrica de combo+cantidad de modelos: usa el catálogo cacheado y filtra en el navegador (repositorio_productos_buscar.php).
	function crearGestorModelos(idFilas, idAgregar, idTotal, onCambio, conPrecio) {
		var filas = document.getElementById(idFilas);
		if (!filas) return null;
		var agregarBtn = document.getElementById(idAgregar);
		var totalValor = document.getElementById(idTotal);
		var buscarReqId = 0;
		var buscarDebounce = null;

		function filaHTML() {
			return '<div class="ep-modelo-fila-nueva' + (conPrecio ? ' con-precio' : '') + '"><div class="ep-combo">'
				+ '<button type="button" class="ep-input ep-combo-trigger" data-valor="">'
				+ '<span class="ep-combo-trigger-texto">Elegir modelo</span>' + epIconMarkup('chevron', 14) + '</button>'
				+ '<div class="ep-combo-panel hidden"><input type="text" class="ep-input ep-combo-buscador" placeholder="Buscar modelo..." autocomplete="off">'
				+ '<div class="ep-combo-opciones"></div></div></div>'
				+ '<input type="number" min="0" inputmode="numeric" class="ep-input ep-modelo-cantidad" placeholder="Cant.">'
				+ (conPrecio ? '<div class="ep-modelo-precio-wrap"><span>$</span><input type="text" inputmode="decimal" maxlength="6" class="ep-input ep-modelo-precio" placeholder="0.00"></div>' : '')
				+ '<button type="button" class="ep-modelo-quitar" aria-label="Quitar modelo">' + epIconMarkup('trash', 14) + '</button></div>';
		}
		function elegidosEnOtrasFilas(comboActual) {
			var out = [];
			filas.querySelectorAll('.ep-combo').forEach(function (c) {
				if (c === comboActual) return;
				var v = c.querySelector('.ep-combo-trigger').dataset.valor;
				if (v) out.push(v);
			});
			return out;
		}
		function buscarModelos(combo, texto) {
			var opciones = combo.querySelector('.ep-combo-opciones');
			opciones.innerHTML = '<div class="ep-combo-vacio">Buscando...</div>';
			var miReqId = ++buscarReqId;
			epObtenerCatalogoModelos().then(function (productos) {
				if (miReqId !== buscarReqId) return; // llegó una búsqueda más nueva antes que esta
				var ya = elegidosEnOtrasFilas(combo);
				var texto2 = (texto || '').trim().toUpperCase();
				var coincidencias = productos.filter(function (m) { return ya.indexOf(m) === -1 && m.toUpperCase().indexOf(texto2) !== -1; });
				opciones.innerHTML = coincidencias.length
					? coincidencias.map(function (m) { return '<button type="button" class="ep-combo-opcion" data-valor="' + m + '">' + m + '</button>'; }).join('')
					: '<div class="ep-combo-vacio">Sin resultados</div>';
			});
		}
		function filtrarCombo(combo, texto) {
			clearTimeout(buscarDebounce);
			buscarDebounce = setTimeout(function () { buscarModelos(combo, texto); }, 80);
		}
		function cerrarPaneles() { filas.querySelectorAll('.ep-combo-panel').forEach(function (p) { p.classList.add('hidden'); }); }
		function abrirPanel(combo) {
			cerrarPaneles();
			var buscador = combo.querySelector('.ep-combo-buscador');
			buscador.value = '';
			buscarModelos(combo, '');
			combo.querySelector('.ep-combo-panel').classList.remove('hidden');
			buscador.focus();
		}
		function actualizarTotal() {
			var total = 0;
			filas.querySelectorAll('.ep-modelo-cantidad').forEach(function (i) { total += parseFloat(i.value) || 0; });
			if (totalValor) totalValor.textContent = total;
			if (onCambio) onCambio();
		}
		function agregarFila() { filas.insertAdjacentHTML('beforeend', filaHTML()); }

		agregarFila(); // arranca con una fila vacía, igual que Activaciones
		filas.addEventListener('input', function (ev) {
			if (ev.target.classList.contains('ep-combo-buscador')) filtrarCombo(ev.target.closest('.ep-combo'), ev.target.value);
			if (ev.target.classList.contains('ep-modelo-cantidad')) actualizarTotal();
			if (ev.target.classList.contains('ep-modelo-precio')) {
				var limpio = epLimpiarPrecio(ev.target.value);
				if (ev.target.value !== limpio) ev.target.value = limpio;
				actualizarTotal(); // dispara onCambio para que las estadísticas de ingresos se vean en vivo
			}
		});
		filas.addEventListener('click', function (ev) {
			var trigger = ev.target.closest('.ep-combo-trigger');
			if (trigger) {
				var combo = trigger.closest('.ep-combo');
				var abierto = !combo.querySelector('.ep-combo-panel').classList.contains('hidden');
				cerrarPaneles();
				if (!abierto) abrirPanel(combo);
				return;
			}
			var opcion = ev.target.closest('.ep-combo-opcion');
			if (opcion) {
				var comboElegido = opcion.closest('.ep-combo');
				var triggerElegido = comboElegido.querySelector('.ep-combo-trigger');
				triggerElegido.dataset.valor = opcion.dataset.valor;
				triggerElegido.querySelector('.ep-combo-trigger-texto').textContent = opcion.dataset.valor;
				comboElegido.querySelector('.ep-combo-panel').classList.add('hidden');
				if (onCambio) onCambio();
				return;
			}
			var quitar = ev.target.closest('.ep-modelo-quitar');
			if (quitar) { quitar.closest('.ep-modelo-fila-nueva').remove(); actualizarTotal(); }
		});
		if (agregarBtn) agregarBtn.addEventListener('click', agregarFila);
		document.addEventListener('click', function (ev) { if (!ev.target.closest('.ep-combo')) cerrarPaneles(); });

		// Reemplaza las filas por una lista [{ modelo, cantidad, precio }] (la usa "Corregir y reenviar").
		function cargar(lista) {
			filas.innerHTML = '';
			(lista.length ? lista : [{}]).forEach(function (m) {
				agregarFila();
				var fila = filas.lastElementChild;
				if (m.modelo) {
					fila.querySelector('.ep-combo-trigger').dataset.valor = m.modelo;
					fila.querySelector('.ep-combo-trigger-texto').textContent = m.modelo;
				}
				if (m.cantidad) fila.querySelector('.ep-modelo-cantidad').value = m.cantidad;
				var precio = fila.querySelector('.ep-modelo-precio');
				if (precio && m.precio) precio.value = String(m.precio);
			});
			actualizarTotal();
		}

		return { cargar: cargar, modelos: function () {
			var out = [];
			filas.querySelectorAll('.ep-modelo-fila-nueva').forEach(function (fila) {
				var nombre = fila.querySelector('.ep-combo-trigger').dataset.valor;
				var cantidad = parseFloat(fila.querySelector('.ep-modelo-cantidad').value) || 0;
				var precioInput = fila.querySelector('.ep-modelo-precio');
				var precio = precioInput ? (parseFloat(precioInput.value) || 0) : 0;
				if (nombre && cantidad > 0) out.push({ nombre: nombre, cantidad: cantidad, precio: precio });
			});
			return out;
		} };
	}

	// ---------- Epson Day ---------- igual que Activaciones pero sin Cumplimiento (no está en su Excel).
	var edayNacional = document.getElementById('ep-eday-nacional');
	var edayCoberturadas = document.getElementById('ep-eday-coberturadas');
	var edayVisitaron = document.getElementById('ep-eday-visitaron');
	var edayInteractuaron = document.getElementById('ep-eday-interactuaron');
	var edayCompraron = document.getElementById('ep-eday-compraron');
	var edayComentarios = document.getElementById('ep-eday-comentarios');
	if (edayNacional && edayCoberturadas) aplicarTope(edayNacional, edayCoberturadas);
	if (edayVisitaron && edayInteractuaron) aplicarTope(edayVisitaron, edayInteractuaron);
	if (edayInteractuaron && edayCompraron) aplicarTope(edayInteractuaron, edayCompraron);

	var edayModelos = crearGestorModelos('ep-eday-modelo-filas', 'ep-eday-modelo-agregar', 'ep-eday-modelo-total-valor', function () { actualizarEstadisticasEpsonDay(); }, true);

	function actualizarEstadisticasEpsonDay() {
		var statPct = document.getElementById('ep-eday-stat-cobertura-pct');
		if (!statPct) return;

		var nacional = edayNacional ? edayNacional.value : 0;
		var coberturadas = edayCoberturadas ? edayCoberturadas.value : 0;
		var visitaron = edayVisitaron ? edayVisitaron.value : 0;
		var interactuaron = edayInteractuaron ? edayInteractuaron.value : 0;
		var compraron = edayCompraron ? edayCompraron.value : 0;

		statPct.textContent = pctTexto(coberturadas, nacional);
		document.getElementById('ep-eday-stat-nacional').textContent = nacional || 0;
		document.getElementById('ep-eday-stat-coberturadas').textContent = coberturadas || 0;
		document.getElementById('ep-eday-stat-interaccion-pct').textContent = pctTexto(interactuaron, visitaron);
		document.getElementById('ep-eday-stat-visitaron').textContent = visitaron || 0;
		document.getElementById('ep-eday-stat-interactuaron').textContent = interactuaron || 0;
		document.getElementById('ep-eday-stat-ventas-pct').textContent = pctTexto(compraron, interactuaron);
		document.getElementById('ep-eday-stat-compraron').textContent = compraron || 0;

		var maxEmbudo = Math.max(parseFloat(visitaron) || 0, 1);
		document.getElementById('ep-eday-stat-bar-visitaron-valor').textContent = visitaron || 0;
		document.getElementById('ep-eday-stat-bar-visitaron').style.height = pctTexto(visitaron, maxEmbudo);
		document.getElementById('ep-eday-stat-bar-interactuaron-valor').textContent = interactuaron || 0;
		document.getElementById('ep-eday-stat-bar-interactuaron').style.height = pctTexto(interactuaron, maxEmbudo);
		document.getElementById('ep-eday-stat-bar-compraron-valor').textContent = compraron || 0;
		document.getElementById('ep-eday-stat-bar-compraron').style.height = pctTexto(compraron, maxEmbudo);

		var detalleVentas = document.getElementById('ep-eday-stat-detalle-ventas');
		var modelos = (edayModelos ? edayModelos.modelos() : []).sort(function (a, b) { return b.cantidad - a.cantidad; });
		var totalUnidades = modelos.reduce(function (s, m) { return s + m.cantidad; }, 0);
		pintarIngresosPorModelo('ep-eday-stat-detalle-ingresos', modelos);

		detalleVentas.innerHTML = !modelos.length
			? '<span class="ep-stat-comentarios-vacio">Todavía no cargaste modelos.</span>'
			: modelos.map(function (m) {
				return '<div class="ep-venta-fila"><span class="ep-venta-nombre">' + escapeHtml(m.nombre) + '</span>'
					+ '<div class="ep-venta-barra-track"><div class="ep-venta-barra-fill" style="width:' + Math.round(m.cantidad / modelos[0].cantidad * 100) + '%;"></div></div>'
					+ '<span class="ep-venta-valor">' + m.cantidad + '</span></div>';
			}).join('');

		var mayorPct = document.getElementById('ep-eday-stat-mayor-pct');
		var mayorNombre = document.getElementById('ep-eday-stat-mayor-nombre');
		var menorPct = document.getElementById('ep-eday-stat-menor-pct');
		var menorNombre = document.getElementById('ep-eday-stat-menor-nombre');
		if (modelos.length) {
			var mayorCant = modelos[0].cantidad;
			var menorCant = modelos[modelos.length - 1].cantidad;
			mayorPct.textContent = pctTexto(mayorCant, totalUnidades);
			mayorNombre.textContent = modelos.filter(function (m) { return m.cantidad === mayorCant; }).map(function (m) { return m.nombre; }).join(' / ');
			menorPct.textContent = pctTexto(menorCant, totalUnidades);
			menorNombre.textContent = modelos.filter(function (m) { return m.cantidad === menorCant; }).map(function (m) { return m.nombre; }).join(' / ');
		} else {
			mayorPct.textContent = '0%'; mayorNombre.textContent = 'Sin datos';
			menorPct.textContent = '0%'; menorNombre.textContent = 'Sin datos';
		}

		var comentariosBoxEday = document.getElementById('ep-eday-stat-comentarios');
		var lineasEday = edayComentarios ? edayComentarios.value.split('\n').map(function (l) { return l.trim(); }).filter(Boolean) : [];
		comentariosBoxEday.innerHTML = lineasEday.length
			? lineasEday.map(function (l) { return '<div>' + escapeHtml(l) + '</div>'; }).join('')
			: '<span class="ep-stat-comentarios-vacio">Sin comentarios todavía.</span>';
	}

	[edayNacional, edayCoberturadas, edayVisitaron, edayInteractuaron, edayCompraron].forEach(function (input) {
		if (input) input.addEventListener('input', actualizarEstadisticasEpsonDay);
	});
	if (edayComentarios) edayComentarios.addEventListener('input', actualizarEstadisticasEpsonDay);
	actualizarEstadisticasEpsonDay(); // primer cálculo

	// ---------- Evento o Ferias ---------- igual que Epson Day pero sin Cobertura (no está en su Excel).
	var eventoVisitaron = document.getElementById('ep-evento-visitaron');
	var eventoInteractuaron = document.getElementById('ep-evento-interactuaron');
	var eventoCompraron = document.getElementById('ep-evento-compraron');
	var eventoComentarios = document.getElementById('ep-evento-comentarios');
	if (eventoVisitaron && eventoInteractuaron) aplicarTope(eventoVisitaron, eventoInteractuaron);
	if (eventoInteractuaron && eventoCompraron) aplicarTope(eventoInteractuaron, eventoCompraron);

	var eventoModelos = crearGestorModelos('ep-evento-modelo-filas', 'ep-evento-modelo-agregar', 'ep-evento-modelo-total-valor', function () { actualizarEstadisticasEvento(); });

	function actualizarEstadisticasEvento() {
		var statPct = document.getElementById('ep-evento-stat-interaccion-pct');
		if (!statPct) return;

		var visitaron = eventoVisitaron ? eventoVisitaron.value : 0;
		var interactuaron = eventoInteractuaron ? eventoInteractuaron.value : 0;
		var compraron = eventoCompraron ? eventoCompraron.value : 0;

		statPct.textContent = pctTexto(interactuaron, visitaron);
		document.getElementById('ep-evento-stat-visitaron').textContent = visitaron || 0;
		document.getElementById('ep-evento-stat-interactuaron').textContent = interactuaron || 0;
		document.getElementById('ep-evento-stat-ventas-pct').textContent = pctTexto(compraron, interactuaron);
		document.getElementById('ep-evento-stat-compraron').textContent = compraron || 0;

		var maxEmbudo = Math.max(parseFloat(visitaron) || 0, 1);
		document.getElementById('ep-evento-stat-bar-visitaron-valor').textContent = visitaron || 0;
		document.getElementById('ep-evento-stat-bar-visitaron').style.height = pctTexto(visitaron, maxEmbudo);
		document.getElementById('ep-evento-stat-bar-interactuaron-valor').textContent = interactuaron || 0;
		document.getElementById('ep-evento-stat-bar-interactuaron').style.height = pctTexto(interactuaron, maxEmbudo);
		document.getElementById('ep-evento-stat-bar-compraron-valor').textContent = compraron || 0;
		document.getElementById('ep-evento-stat-bar-compraron').style.height = pctTexto(compraron, maxEmbudo);

		var detalleVentas = document.getElementById('ep-evento-stat-detalle-ventas');
		var modelos = (eventoModelos ? eventoModelos.modelos() : []).sort(function (a, b) { return b.cantidad - a.cantidad; });
		var totalUnidades = modelos.reduce(function (s, m) { return s + m.cantidad; }, 0);

		detalleVentas.innerHTML = !modelos.length
			? '<span class="ep-stat-comentarios-vacio">Todavía no cargaste modelos.</span>'
			: modelos.map(function (m) {
				return '<div class="ep-venta-fila"><span class="ep-venta-nombre">' + escapeHtml(m.nombre) + '</span>'
					+ '<div class="ep-venta-barra-track"><div class="ep-venta-barra-fill" style="width:' + Math.round(m.cantidad / modelos[0].cantidad * 100) + '%;"></div></div>'
					+ '<span class="ep-venta-valor">' + m.cantidad + '</span></div>';
			}).join('');

		var mayorPct = document.getElementById('ep-evento-stat-mayor-pct');
		var mayorNombre = document.getElementById('ep-evento-stat-mayor-nombre');
		var menorPct = document.getElementById('ep-evento-stat-menor-pct');
		var menorNombre = document.getElementById('ep-evento-stat-menor-nombre');
		if (modelos.length) {
			var mayorCant = modelos[0].cantidad;
			var menorCant = modelos[modelos.length - 1].cantidad;
			mayorPct.textContent = pctTexto(mayorCant, totalUnidades);
			mayorNombre.textContent = modelos.filter(function (m) { return m.cantidad === mayorCant; }).map(function (m) { return m.nombre; }).join(' / ');
			menorPct.textContent = pctTexto(menorCant, totalUnidades);
			menorNombre.textContent = modelos.filter(function (m) { return m.cantidad === menorCant; }).map(function (m) { return m.nombre; }).join(' / ');
		} else {
			mayorPct.textContent = '0%'; mayorNombre.textContent = 'Sin datos';
			menorPct.textContent = '0%'; menorNombre.textContent = 'Sin datos';
		}

		var comentariosBoxEvento = document.getElementById('ep-evento-stat-comentarios');
		var lineasEvento = eventoComentarios ? eventoComentarios.value.split('\n').map(function (l) { return l.trim(); }).filter(Boolean) : [];
		comentariosBoxEvento.innerHTML = lineasEvento.length
			? lineasEvento.map(function (l) { return '<div>' + escapeHtml(l) + '</div>'; }).join('')
			: '<span class="ep-stat-comentarios-vacio">Sin comentarios todavía.</span>';
	}

	[eventoVisitaron, eventoInteractuaron, eventoCompraron].forEach(function (input) {
		if (input) input.addEventListener('input', actualizarEstadisticasEvento);
	});
	if (eventoComentarios) eventoComentarios.addEventListener('input', actualizarEstadisticasEvento);
	actualizarEstadisticasEvento(); // primer cálculo

	// ---------- Colocación de POP ---------- un registro por punto de venta: material del mes abierto + cantidad; sin panel de estadísticas.
	var popEntregas = document.getElementById('ep-pop-entregas');
	var popAgregar = document.getElementById('ep-pop-entregas-agregar');
	var popCatalogo = window.EP_POP_MATERIALES || [];
	if (popEntregas) {
		// La campaña no se escribe: viene con el material que cargó el gestor en el mes de POP.
		function popFilaHTML() {
			return '<div class="ep-pop-entrega-fila"><div class="ep-combo">'
				+ '<button type="button" class="ep-input ep-combo-trigger ep-pop-entrega-material" data-valor="">'
				+ '<span class="ep-combo-trigger-texto">Elegir material</span>' + epIconMarkup('chevron', 14) + '</button>'
				+ '<div class="ep-combo-panel hidden"><input type="text" class="ep-input ep-combo-buscador" placeholder="Buscar material..." autocomplete="off">'
				+ '<div class="ep-combo-opciones"></div></div></div>'
				+ '<input type="number" min="1" inputmode="numeric" class="ep-input ep-pop-entrega-cantidad" placeholder="Cant.">'
				+ '<button type="button" class="ep-modelo-quitar" aria-label="Quitar material">' + epIconMarkup('trash', 14) + '</button></div>';
		}
		function popElegidosEnOtras(comboActual) {
			var out = [];
			popEntregas.querySelectorAll('.ep-combo').forEach(function (c) {
				if (c === comboActual) return;
				var v = c.querySelector('.ep-combo-trigger').dataset.valor;
				if (v) out.push(v);
			});
			return out;
		}
		function popPintarOpciones(combo, texto) {
			var ya = popElegidosEnOtras(combo);
			var busca = (texto || '').trim().toUpperCase();
			var hay = popCatalogo.filter(function (m) { return ya.indexOf(m.material) === -1 && m.material.toUpperCase().indexOf(busca) !== -1; });
			combo.querySelector('.ep-combo-opciones').innerHTML = hay.length
				? hay.map(function (m) { return '<button type="button" class="ep-combo-opcion" data-valor="' + escapeHtml(m.material) + '">' + escapeHtml(m.material) + '<span class="ep-combo-opcion-nota">' + escapeHtml(m.campana) + (m.disponible != null ? ' · quedan ' + m.disponible : '') + '</span></button>'; }).join('')
				: '<div class="ep-combo-vacio">Sin resultados</div>';
		}
		function popCerrarPaneles() { popEntregas.querySelectorAll('.ep-combo-panel').forEach(function (p) { p.classList.add('hidden'); }); }
		function popAgregarFila() {
			popEntregas.insertAdjacentHTML('beforeend', popFilaHTML());
			return popEntregas.lastElementChild;
		}
		popAgregarFila();
		if (popAgregar) popAgregar.addEventListener('click', function () { popAgregarFila().querySelector('.ep-combo-trigger').focus(); });
		popEntregas.addEventListener('input', function (ev) {
			if (ev.target.classList.contains('ep-combo-buscador')) popPintarOpciones(ev.target.closest('.ep-combo'), ev.target.value);
		});
		popEntregas.addEventListener('click', function (ev) {
			var trigger = ev.target.closest('.ep-combo-trigger');
			if (trigger) {
				var combo = trigger.closest('.ep-combo');
				var abierto = !combo.querySelector('.ep-combo-panel').classList.contains('hidden');
				popCerrarPaneles();
				if (!abierto) {
					var buscador = combo.querySelector('.ep-combo-buscador');
					buscador.value = '';
					popPintarOpciones(combo, '');
					combo.querySelector('.ep-combo-panel').classList.remove('hidden');
					buscador.focus();
				}
				return;
			}
			var opcion = ev.target.closest('.ep-combo-opcion');
			if (opcion) {
				var elegido = opcion.closest('.ep-combo').querySelector('.ep-combo-trigger');
				elegido.dataset.valor = opcion.dataset.valor;
				elegido.querySelector('.ep-combo-trigger-texto').textContent = opcion.dataset.valor;
				// El promotor no puede pasar de lo que su supervisor le asignó y aún no reportó.
				var elegidoMat = popCatalogo.filter(function (m) { return m.material === opcion.dataset.valor; })[0];
				var cant = opcion.closest('.ep-pop-entrega-fila').querySelector('.ep-pop-entrega-cantidad');
				if (elegidoMat && elegidoMat.disponible != null) { cant.max = elegidoMat.disponible; cant.placeholder = 'Máx. ' + elegidoMat.disponible; if (parseInt(cant.value, 10) > elegidoMat.disponible) cant.value = elegidoMat.disponible; }
				popCerrarPaneles();
				return;
			}
			// Siempre queda al menos una fila: la última solo se vacía.
			var quitar = ev.target.closest('.ep-modelo-quitar');
			if (!quitar) return;
			var fila = quitar.closest('.ep-pop-entrega-fila');
			if (popEntregas.querySelectorAll('.ep-pop-entrega-fila').length > 1) {
				fila.remove();
			} else {
				popEntregas.innerHTML = '';
				popAgregarFila();
			}
		});
		popEntregas.addEventListener('input', function (ev) {
			var c = ev.target;
			if (c.classList.contains('ep-pop-entrega-cantidad') && c.max && parseInt(c.value, 10) > parseInt(c.max, 10)) c.value = c.max;
		});
		document.addEventListener('click', function (ev) { if (!ev.target.closest('.ep-combo')) popCerrarPaneles(); });
	}
	function leerEntregasPop() {
		if (!popEntregas) return [];
		return Array.prototype.slice.call(popEntregas.querySelectorAll('.ep-pop-entrega-fila')).map(function (f) {
			return { material: f.querySelector('.ep-pop-entrega-material').dataset.valor, cantidad: parseInt(f.querySelector('.ep-pop-entrega-cantidad').value, 10) || 0 };
		}).filter(function (e) { return e.material && e.cantidad > 0; });
	}

	var buscarActividad = document.getElementById('ep-buscar-actividad');
	if (buscarActividad && listaActividades) {
		buscarActividad.addEventListener('input', function () {
			var texto = buscarActividad.value.trim().toLowerCase();
			listaActividades.querySelectorAll('.ep-activity-item').forEach(function (el) {
				var nombre = (el.dataset.nombre || '').toLowerCase();
				el.classList.toggle('hidden', texto !== '' && nombre.indexOf(texto) === -1);
			});
		});
	}

	// ---------- Módulo Registros de Actividades ----------
	// La interactividad completa (filtros, paginación, conmutador de vistas y lightbox)
	// se gestiona en el controlador unificado de alta escala al final del archivo.


	// Toggle entre "Formulario" y "Nueva actividad" (mismo módulo, solo rol admin lo ve)
	var nuevaActividadBtn = document.getElementById('ep-nueva-actividad-btn');
	var cancelarBtn = document.getElementById('ep-cancelar-nueva-actividad');
	var panelFormulario = document.getElementById('ep-panel-formulario');
	var layoutActividad = document.querySelector('.ep-actividad-layout');
	var panelConstructor = document.getElementById('ep-panel-constructor');
	var headerFormulario = document.getElementById('ep-header-formulario');
	var headerConstructor = document.getElementById('ep-header-constructor');

	function mostrarConstructor() {
		if (!panelConstructor) return;
		if (layoutActividad) layoutActividad.classList.add('hidden');
		panelConstructor.classList.remove('hidden');
		if (headerFormulario) headerFormulario.classList.add('hidden');
		if (headerConstructor) headerConstructor.classList.remove('hidden');
		actualizarVistaPrevia();
	}
	function mostrarFormulario() {
		if (!panelConstructor) return;
		panelConstructor.classList.add('hidden');
		if (layoutActividad) layoutActividad.classList.remove('hidden');
		if (headerConstructor) headerConstructor.classList.add('hidden');
		if (headerFormulario) headerFormulario.classList.remove('hidden');
	}

	if (nuevaActividadBtn && panelFormulario && panelConstructor) {
		nuevaActividadBtn.addEventListener('click', mostrarConstructor);
	}
	if (cancelarBtn) {
		cancelarBtn.addEventListener('click', mostrarFormulario);
	}

	// Vista previa en vivo de "Nueva actividad": nombre tipeado + lógica elegida (solo rol admin)
	var nuevaNombre = document.getElementById('ep-nueva-nombre');
	var nuevaLogica = document.getElementById('ep-nueva-logica');
	var previewBotonLabel = document.getElementById('ep-preview-boton-label');
	var previewFormTitulo = document.getElementById('ep-preview-form-titulo');
	var previewFormCampos = document.getElementById('ep-preview-form-campos');
	var previewFormFotos = document.getElementById('ep-preview-form-fotos');
	var nuevaError = document.getElementById('ep-nueva-error');
	var guardarActividadBtn = document.getElementById('ep-guardar-actividad-btn');

	var ultimaPlantillaPreview = null;
	function actualizarVistaPrevia() {
		if (!nuevaLogica || !window.EP_LOGICAS) return;
		var nombre = epFormatoTitulo(nuevaNombre.value) || 'Nombre de la actividad';
		var logica = window.EP_LOGICAS[nuevaLogica.value] || window.EP_LOGICAS[Object.keys(window.EP_LOGICAS)[0]];

		if (previewBotonLabel) previewBotonLabel.textContent = nombre;
		if (previewFormTitulo) previewFormTitulo.textContent = nombre;

		// El formulario real solo cambia si cambió la plantilla elegida — evita re-pedirlo en cada letra tipeada del nombre.
		if (previewFormCampos && logica.plantilla !== ultimaPlantillaPreview) {
			ultimaPlantillaPreview = logica.plantilla;
			previewFormCampos.innerHTML = '<span style="font-size:12px;color:var(--color-text-muted);">Cargando...</span>';
			fetch('getters/vista_previa_formulario.php?plantilla=' + encodeURIComponent(logica.plantilla))
				.then(function (r) { return r.text(); })
				.then(function (html) { previewFormCampos.innerHTML = html; })
				.catch(function () { previewFormCampos.innerHTML = '<span style="font-size:12px;color:var(--color-text-muted);">No se pudo cargar la vista previa.</span>'; });
		}

		if (previewFormFotos) {
			previewFormFotos.innerHTML = '';
			if (!logica.fotos || !logica.fotos.length) {
				previewFormFotos.innerHTML = '<span style="font-size:12px;color:var(--color-text-muted);">Esta lógica no pide fotos todavía.</span>';
			} else {
				var cajaCarrusel = document.createElement('div');
				cajaCarrusel.className = 'ep-car';
				cajaCarrusel.innerHTML = '<button type="button" class="ep-car-btn ep-car-prev" aria-label="Anterior" disabled>&#8249;</button><button type="button" class="ep-car-btn ep-car-next" aria-label="Siguiente">&#8250;</button><div class="ep-car-carril"></div>';
				var filaContenedor = cajaCarrusel.querySelector('.ep-car-carril');
				logica.fotos.forEach(function (foto) {
					var slot = document.createElement('div');
					slot.className = 'ep-foto-slot';
					slot.innerHTML = '<div class="ep-foto-dropzone" style="pointer-events:none;cursor:default;">'
						+ '<span class="ep-foto-dropzone-vacio">'
						+ epIconMarkup('camera', 22)
						+ '<span>Subir foto</span></span></div>'
						+ '<div class="ep-foto-slot-info">'
						+ '<span class="ep-foto-slot-label">' + escapeHtml(foto.label) + '</span>'
						+ '<span class="ep-hist-badge ep-foto-slot-estado">Pendiente</span>'
						+ '</div>';
					filaContenedor.appendChild(slot);
				});
				previewFormFotos.appendChild(cajaCarrusel);
				window.epCarrusel.iniciar(cajaCarrusel);
			}
		}
	}

	if (nuevaNombre) {
		nuevaNombre.addEventListener('input', actualizarVistaPrevia);
		// Corrige el formato del campo recién al salir (no mientras se tipea, para no pelear con el cursor).
		nuevaNombre.addEventListener('blur', function () {
			nuevaNombre.value = epFormatoTitulo(nuevaNombre.value);
		});
	}
	if (nuevaLogica) nuevaLogica.addEventListener('change', actualizarVistaPrevia);

	// "Guardar actividad": crea de verdad (persiste en sesión), recarga para que aparezca en sidebar/gestión/paneles.
	if (guardarActividadBtn) {
		guardarActividadBtn.addEventListener('click', function () {
			var nombre = epFormatoTitulo(nuevaNombre ? nuevaNombre.value : '');
			if (nuevaError) { nuevaError.classList.add('hidden'); nuevaError.textContent = ''; }
			if (!nombre) {
				if (nuevaError) { nuevaError.textContent = 'Ponle un nombre a la actividad.'; nuevaError.classList.remove('hidden'); }
				return;
			}
			guardarActividadBtn.disabled = true;
			var datos = new FormData();
			datos.append('nombre', nombre);
			datos.append('logica', nuevaLogica ? nuevaLogica.value : '');
			fetch('getters/crear_actividad.php', { method: 'POST', body: datos })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (data.ok) { location.reload(); return; }
					guardarActividadBtn.disabled = false;
					if (nuevaError) { nuevaError.textContent = data.message || 'No se pudo crear la actividad.'; nuevaError.classList.remove('hidden'); }
				})
				.catch(function () {
					guardarActividadBtn.disabled = false;
					if (nuevaError) { nuevaError.textContent = 'Error de conexión, intenta de nuevo.'; nuevaError.classList.remove('hidden'); }
				});
		});
	}

	// Gestión de actividades existentes (mockup, solo visual — sin borrado real todavía)
	var gestionLista = document.getElementById('ep-gestion-lista');
	if (gestionLista && listaActividades) {
		gestionLista.addEventListener('click', function (ev) {
			var btnEliminar = ev.target.closest('.ep-gestion-eliminar');
			if (!btnEliminar) return;
			var fila = btnEliminar.closest('.ep-gestion-fila');
			var nombre = fila.querySelector('.ep-gestion-nombre').textContent;
			if (!window.confirm('¿Eliminar "' + nombre + '"? Esta acción no se puede deshacer.')) return;

			var id = btnEliminar.dataset.id;
			var botonSidebar = listaActividades.querySelector('.ep-activity-item[data-id="' + id + '"]');
			if (botonSidebar) botonSidebar.remove();
			fila.remove();
		});

		gestionLista.addEventListener('change', function (ev) {
			var toggle = ev.target.closest('.ep-gestion-switch');
			if (!toggle) return;
			var fila = toggle.closest('.ep-gestion-fila');
			var id = fila.dataset.id;
			var botonSidebar = listaActividades.querySelector('.ep-activity-item[data-id="' + id + '"]');
			var activa = toggle.checked;

			fila.classList.toggle('ep-gestion-inactiva', !activa);
			if (botonSidebar) botonSidebar.classList.toggle('hidden', !activa);

			var fd = new FormData();
			fd.append('id', id);
			fd.append('activa', activa ? '1' : '0');
			fetch('getters/toggle_actividad.php', { method: 'POST', body: fd }).catch(function () {});
		});
	}

	// =========================================================================
	// ENVÍO DE FORMULARIO DE CAMPO EN VIVO (MÓDULO ACTIVIDADES)
	// =========================================================================
	// Ventanas de aviso (SweetAlert2); si no cargó, cae al alert nativo.
	function epAviso(icono, titulo, texto, boton, extra) {
		if (!window.Swal) { alert(titulo); return Promise.resolve(); }
		return Swal.fire(Object.assign({ icon: icono, title: titulo, html: texto || '', confirmButtonText: boton || 'Entendido', confirmButtonColor: '#513487', allowOutsideClick: false }, extra || {}));
	}
	function epToast(icono, titulo) {
		if (!window.Swal) { alert(titulo); return; }
		Swal.mixin({ toast: true, position: 'top', showConfirmButton: false, timer: 3500, timerProgressBar: true }).fire({ icon: icono, title: titulo });
	}

	// Campos obligatorios del formulario visible que siguen vacíos (punto de venta, datos, combos y al menos un modelo); los comentarios no cuentan.
	function camposFormularioVacios() {
		var panel = document.querySelector('.ep-formulario-actividad:not(.hidden)');
		var camposVacios = [];
		var pdvInput = document.getElementById('ep-pdv-trigger');
		// Todas las actividades piden punto de venta (Colocación de POP también: un registro por punto).
		if (pdvInput && !window.epPdv.elegido()) camposVacios.push(pdvInput);
		if (panel) {
			panel.querySelectorAll('input:not([type="hidden"]):not([type="file"]):not([type="checkbox"]):not([type="radio"]), select, textarea').forEach(function (el) {
				// Comentarios (también las líneas que arma comentarios.js, sin id) y descripciones de foto no entran en este chequeo.
				if (el.disabled || el.readOnly || /comentario/i.test(el.id) || el.closest('.ep-coment-item') || el.offsetParent === null || el.classList.contains('ep-foto-descripcion')) return;
				if (String(el.value).trim() === '') camposVacios.push(el);
			});
			panel.querySelectorAll('.ep-combo-trigger').forEach(function (el) {
				if (el.offsetParent !== null && !el.dataset.valor) camposVacios.push(el);
			});
			// Sin ninguna fila de modelos (se quitaron todas) también falta el dato: se marca el botón de agregar.
			panel.querySelectorAll('[id$="modelo-filas"]').forEach(function (filas) {
				var agregar = document.getElementById(filas.id.replace('filas', 'agregar'));
				if (filas.offsetParent !== null && !filas.children.length && agregar) camposVacios.push(agregar);
			});
		}
		return camposVacios;
	}

	// Asterisco rojo en la etiqueta de cada campo obligatorio del formulario (todos menos comentarios).
	function marcarCamposObligatorios() {
		var marcar = function (label) { if (label) label.classList.add('ep-requerido'); };
		document.querySelectorAll('.ep-formulario-actividad input:not([type="hidden"]):not([type="file"]):not([type="checkbox"]):not([type="radio"]), .ep-formulario-actividad select, .ep-formulario-actividad textarea').forEach(function (el) {
			if (!el.id || /comentario/i.test(el.id) || el.readOnly) return;
			marcar(document.querySelector('label[for="' + el.id + '"]'));
		});
		marcar(document.getElementById('ep-pdv-etiqueta'));
	}
	marcarCamposObligatorios();

	// Antes de pasar a las fotos avisa si faltan datos del formulario; permite completar o seguir igual.
	function avisarDatosFaltantes(continuar) {
		var faltan = camposFormularioVacios();
		if (!faltan.length || !window.Swal) { continuar(); return; }
		faltan.forEach(function (el) { el.classList.add('ep-campo-error'); });
		var n = faltan.length;
		epAviso('warning', 'Faltan datos del formulario', '<b>' + n + '</b> campo' + (n === 1 ? '' : 's') + ' obligatorio' + (n === 1 ? '' : 's') + ' (<span style="color:#C5221F;font-weight:700;">*</span>) sin llenar. Son necesarios para enviar el registro.', 'Completar datos', { showCancelButton: true, cancelButtonText: 'Continuar a fotos', reverseButtons: true }).then(function (r) {
			if (r.isConfirmed) {
				activarTabMovil('formulario');
				faltan[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
				if (faltan[0].focus) faltan[0].focus({ preventScroll: true });
			} else if (r.dismiss === Swal.DismissReason.cancel) {
				continuar();
			}
		});
	}

	// Validador del envío: todos los campos visibles del formulario y todas las fotos son obligatorios; solo comentarios es opcional.
	function validarRegistroActivo() {
		var camposVacios = camposFormularioVacios();
		var bloqueFotos = document.querySelector('.ep-evidencia-actividad:not(.hidden)');
		var fotosFaltan = [];
		var descFaltan = [];
		if (bloqueFotos) {
			bloqueFotos.querySelectorAll('.ep-foto-slot').forEach(function (s) {
				if (!s._blob && !s.dataset.fotoRuta && !s.dataset.opcional) fotosFaltan.push(s);
				if (slotFaltaDescripcion(s)) descFaltan.push(descripcionDeSlot(s));
			});
		}
		document.querySelectorAll('.ep-campo-error, .ep-foto-error').forEach(function (el) { el.classList.remove('ep-campo-error', 'ep-foto-error'); });
		camposVacios.concat(descFaltan).forEach(function (el) { el.classList.add('ep-campo-error'); });
		fotosFaltan.forEach(function (s) { s.classList.add('ep-foto-error'); });
		if (camposVacios.length === 0 && fotosFaltan.length === 0 && descFaltan.length === 0) return true;

		var partes = [];
		if (camposVacios.length) partes.push('<b>' + camposVacios.length + '</b> campo' + (camposVacios.length === 1 ? '' : 's') + ' del formulario sin llenar');
		if (fotosFaltan.length) partes.push('<b>' + fotosFaltan.length + '</b> foto' + (fotosFaltan.length === 1 ? '' : 's') + ' sin subir');
		if (descFaltan.length) partes.push('<b>' + descFaltan.length + '</b> foto' + (descFaltan.length === 1 ? '' : 's') + ' sin descripción');
		epAviso('warning', 'Falta información', 'Antes de enviar completa:<br>' + partes.join('<br>') + '<br><small>Solo los comentarios son opcionales.</small>').then(function () {
			// Con solo descripciones pendientes, se abre el asistente en la primera foto sin descripción (en celular es donde se escribe).
			if (!camposVacios.length && !fotosFaltan.length) {
				abrirWizardFotos();
				var i = wizardSlotsActuales.indexOf(descFaltan[0].closest('.ep-foto-slot'));
				if (i !== -1) { wizardPasoActual = i; renderizarWizard(); }
				// SweetAlert devuelve el foco al botón al cerrarse: se enfoca la descripción después.
				setTimeout(function () { if (wizardDescripcionTexto) wizardDescripcionTexto.focus(); }, 150);
				return;
			}
			var primero = camposVacios[0] || fotosFaltan[0];
			if (camposVacios.length) { activarTabMovil('formulario'); } else { activarTabMovil('fotos'); }
			if (primero) {
				primero.scrollIntoView({ behavior: 'smooth', block: 'center' });
				if (camposVacios.length && primero.focus) primero.focus({ preventScroll: true });
			}
		});
		return false;
	}
	document.addEventListener('input', function (ev) { ev.target.classList.remove('ep-campo-error'); });
	// Campos de texto en mayúsculas: solo letras, números, espacios y guion; si es un cuadro, crece con el texto (Enter no crea líneas).
	document.addEventListener('input', function (ev) {
		if (!ev.target.classList.contains('ep-input-mayusculas')) return;
		ev.target.value = ev.target.value.toUpperCase().replace(/[^\p{L}\p{N} -]/gu, '').replace(/ {2,}/g, ' ');
		if (ev.target.tagName === 'TEXTAREA') {
			ev.target.style.height = 'auto';
			ev.target.style.height = ev.target.scrollHeight + 'px';
		}
	});
	document.addEventListener('keydown', function (ev) {
		if (ev.key === 'Enter' && ev.target.classList.contains('ep-input-mayusculas')) ev.preventDefault();
	});
	document.addEventListener('click', function (ev) { var t = ev.target.closest('.ep-combo-opcion'); if (t) { var c = t.closest('.ep-combo'); if (c) { var tr = c.querySelector('.ep-combo-trigger'); if (tr) tr.classList.remove('ep-campo-error'); } } });

	// Valor de un campo por id; vacío si el campo no existe.
	function valorDeCampo(id) {
		var el = document.getElementById(id);
		return el ? el.value : '';
	}

	// Tipo, fecha y horario que escribe el promotor en el paso "Datos de la actividad" (ids ep-<prefijo>-...).
	function leerDatosActividad(prefijo) {
		function valorDe(sufijo) { var el = document.getElementById('ep-' + prefijo + '-' + sufijo); return el ? el.value : ''; }
		return { tipo_actividad: valorDe('tipo'), fecha_actividad: valorDe('fecha'), hora_inicio: valorDe('hora-inicio'), hora_fin: valorDe('hora-fin') };
	}

	// Fecha y horario de la actividad: lo que falla se resalta en rojo y se avisa qué corregir.
	function actividadValida(prefijo) {
		var fechaEl = document.getElementById('ep-' + prefijo + '-fecha');
		var iniEl = document.getElementById('ep-' + prefijo + '-hora-inicio');
		var finEl = document.getElementById('ep-' + prefijo + '-hora-fin');
		if (!fechaEl || !iniEl || !finEl) return true;
		var fallo = null;
		if (!fechaEl.value) {
			fallo = { campos: [fechaEl], texto: 'Elige la fecha de la actividad.' };
		} else if (!iniEl.value || !finEl.value || finEl.value <= iniEl.value) {
			fallo = { campos: [iniEl, finEl], texto: 'La hora de fin debe ser posterior a la hora de inicio.' };
		}
		if (!fallo) return true;
		fallo.campos.forEach(function (c) { c.classList.add('ep-campo-error'); });
		epAviso('warning', 'Revisa los datos de la actividad', fallo.texto).then(function () {
			activarTabMovil('formulario');
			fallo.campos[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
			fallo.campos[0].focus({ preventScroll: true });
		});
		return false;
	}

	function enviarFormularioActivo() {
		if (!validarRegistroActivo()) return;
		var itemSeleccionado = document.querySelector('.ep-activity-item.selected');
		var actNombre = itemSeleccionado ? (itemSeleccionado.dataset.nombre || 'Activaciones') : 'Activaciones';
		var actBadge = itemSeleccionado ? (itemSeleccionado.querySelector('.ep-activity-badge') ? itemSeleccionado.querySelector('.ep-activity-badge').textContent.trim() : '') : '';

		// El tipo sale de la lógica del botón (así un botón nuevo que replica una lógica se guarda con esa lógica); el nombre solo es respaldo.
		var logicaBoton = itemSeleccionado ? (itemSeleccionado.dataset.plantilla || '') : '';
		var tipo = 'activaciones';
		var nomLower = actNombre.toLowerCase();
		if (nomLower.indexOf('capacita') !== -1) tipo = 'capacitaciones';
		else if (nomLower.indexOf('pop') !== -1) tipo = 'colocacion-pop';
		else if (nomLower.indexOf('day') !== -1) tipo = 'epson-day';
		else if (nomLower.indexOf('exhibi') !== -1) tipo = 'exhibiciones';
		else if (nomLower.indexOf('feria') !== -1 || nomLower.indexOf('evento') !== -1) tipo = 'evento-ferias';
		if (logicaBoton) tipo = logicaBoton;

		var valores = {};
		if (tipo === 'activaciones') {
			Object.assign(valores, leerDatosActividad('act'));
			if (!actividadValida('act')) return;
			valores.nacional = document.getElementById('ep-act-nacional') ? document.getElementById('ep-act-nacional').value : '';
			valores.coberturadas = document.getElementById('ep-act-coberturadas') ? document.getElementById('ep-act-coberturadas').value : '';
			valores.visitaron = document.getElementById('ep-act-visitaron') ? document.getElementById('ep-act-visitaron').value : '';
			valores.interactuaron = document.getElementById('ep-act-interactuaron') ? document.getElementById('ep-act-interactuaron').value : '';
			valores.compraron = document.getElementById('ep-act-compraron') ? document.getElementById('ep-act-compraron').value : '';
			valores.comentarios = document.getElementById('ep-act-comentarios') ? document.getElementById('ep-act-comentarios').value : '';
			// Mismos modelos que alimentan las estadísticas del formulario.
			var mods = (actModelos ? actModelos.modelos() : []).map(function (m) { return { modelo: m.nombre, cantidad: parseInt(m.cantidad, 10), precio: parseFloat(m.precio) || 0 }; });
			valores.modelos = mods;
		} else if (tipo === 'capacitaciones') {
			Object.assign(valores, leerDatosActividad('cap'));
			if (!actividadValida('cap')) return;
			valores.asistente_jefe = capAsistJefe ? capAsistJefe.value : '';
			valores.jefe_tienda = capJefeTienda ? capJefeTienda.value : '';
			valores.vendedores = capVendedores ? capVendedores.value : '';
			valores.interacciones = capInteracciones ? capInteracciones.value : '';
			valores.comentarios = document.getElementById('ep-cap-comentarios') ? document.getElementById('ep-cap-comentarios').value : '';
		} else if (tipo === 'colocacion-pop') {
			valores.campana = valorDeCampo('ep-pop-campana').trim().toUpperCase();
			valores.pop_entregas = leerEntregasPop();
			valores.comentarios = document.getElementById('ep-pop-comentarios') ? document.getElementById('ep-pop-comentarios').value : '';
		} else if (tipo === 'epson-day') {
			Object.assign(valores, leerDatosActividad('eday'));
			if (!actividadValida('eday')) return;
			valores.nacional = valorDeCampo('ep-eday-nacional');
			valores.coberturadas = valorDeCampo('ep-eday-coberturadas');
			valores.visitaron = valorDeCampo('ep-eday-visitaron');
			valores.interactuaron = valorDeCampo('ep-eday-interactuaron');
			valores.compraron = valorDeCampo('ep-eday-compraron');
			valores.comentarios = valorDeCampo('ep-eday-comentarios');
			valores.modelos = (edayModelos ? edayModelos.modelos() : []).map(function (m) { return { modelo: m.nombre, cantidad: parseInt(m.cantidad, 10), precio: parseFloat(m.precio) || 0 }; });
		} else if (tipo === 'exhibiciones') {
			Object.assign(valores, leerDatosActividad('exh'));
			if (!actividadValida('exh')) return;
			valores.cabeceras = valorDeCampo('ep-exh-cabeceras');
			valores.rumas = valorDeCampo('ep-exh-rumas');
			valores.muebles = valorDeCampo('ep-exh-muebles');
			valores.exh_regular = valorDeCampo('ep-exh-regular');
			valores.otras = valorDeCampo('ep-exh-otras');
			valores.comentarios = valorDeCampo('ep-exh-comentarios');
		} else if (tipo === 'evento-ferias') {
			Object.assign(valores, leerDatosActividad('evento'));
			if (!actividadValida('evento')) return;
			valores.visitaron = valorDeCampo('ep-evento-visitaron');
			valores.interactuaron = valorDeCampo('ep-evento-interactuaron');
			valores.compraron = valorDeCampo('ep-evento-compraron');
			valores.comentarios = valorDeCampo('ep-evento-comentarios');
			valores.modelos = (eventoModelos ? eventoModelos.modelos() : []).map(function (m) { return { modelo: m.nombre, cantidad: parseInt(m.cantidad, 10) }; });
		}

		// Fotos: se comprimen al elegirlas y se suben a Azure recién ahora, al enviar.
		var bloqueEv = document.querySelector('.ep-evidencia-actividad:not(.hidden)');
		var slotsFotos = bloqueEv ? Array.prototype.slice.call(bloqueEv.querySelectorAll('.ep-foto-slot')) : [];
		if (slotsFotos.some(function (s) { return s.dataset.subiendo; })) {
			epAviso('info', 'Preparando fotos', 'Hay fotos preparándose todavía. Espera unos segundos e intenta de nuevo.');
			return;
		}

		// Descripción de cada foto subida (Competencia); viaja por id de casilla, igual que las rutas.
		var descripciones = {};
		slotsFotos.forEach(function (s) {
			var d = descripcionDeSlot(s);
			if (d && slotTieneFoto(s)) descripciones[s.dataset.fotoId] = d.value.trim();
		});
		// Competencia manda un solo registro con todos los puntos de venta de la sesión adentro (valores.puntos), no un pos_id suelto.
		var pdvActivo = window.epPdv.elegido();
		if (tipo !== 'competencia' && Object.keys(descripciones).length) valores.descripciones = descripciones;

		var payload = {
			tipo: tipo,
			actividad_label: actNombre,
			actividad_badge: actBadge,
			pos_id: tipo === 'competencia' ? '' : (pdvActivo ? pdvActivo.pos_id : ''),
			valores: valores,
			fotos: {}
		};

		var btnEnviar = document.getElementById('epBtnEnviarRegistro');
		if (btnEnviar) {
			btnEnviar.disabled = true;
			btnEnviar.textContent = 'Enviando formulario...';
		}

		// Antes de subir fotos se revisa que no haya ya una Activación de ese punto y día.
		var chequeo = tipo === 'activaciones' && payload.pos_id
			? fetch('getters/registro_duplicado.php?tipo=' + encodeURIComponent(tipo) + '&pos_id=' + encodeURIComponent(payload.pos_id) + '&fecha_actividad=' + encodeURIComponent(valores.fecha_actividad || ''), { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).catch(function () { return { duplicado: false }; })
			: Promise.resolve({ duplicado: false });
		chequeo.then(function (c) {
			if (c && c.duplicado) throw { duplicado: true, mensaje: c.mensaje };
			return subirFotosPendientes(slotsFotos);
		}).then(function (fotos) {
			if (tipo === 'competencia') {
				var puntoActivo = { pos_id: pdvActivo ? pdvActivo.pos_id : '', fotos: fotos, descripciones: descripciones };
				payload.valores.puntos = epCompetenciaPuntos.map(function (p) { return { pos_id: p.pos_id, fotos: p.fotos, descripciones: p.descripciones }; }).concat([puntoActivo]);
			} else {
				payload.fotos = fotos;
			}
			var metaUsuario = document.querySelector('meta[name="ep-usuario"]');
			payload.usuario_ref = metaUsuario ? metaUsuario.content : '';
			return fetch('getters/guardar_registro.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(payload)
			});
		})
		.then(function(res) { return res.json(); })
		.then(function(data) {
			if (data.success) {
				// Competencia con varios puntos de venta en esta sesión: el mensaje final cuenta todos, no solo el último.
				var totalSesionCompetencia = tipo === 'competencia' ? epCompetenciaPuntos.length + 1 : 1;
				var mensajeBase = data.pendiente ? 'Tu registro quedó enviado y espera la aprobación de tu supervisor.' : 'Tu reporte quedó guardado correctamente.';
				if (totalSesionCompetencia > 1) mensajeBase = 'Enviaste ' + totalSesionCompetencia + ' puntos de venta en esta sesión. ' + mensajeBase;
				if (tipo === 'competencia') epCompetenciaReiniciarCompletados();
epAviso('success', 'Registro enviado', mensajeBase + '<br><span style="display:inline-block;margin-top:8px;padding:4px 10px;border-radius:6px;background:#F4F1FA;color:#513487;font-weight:700;font-size:13px;">' + data.id + '</span>', 'Ver mis registros', { showCloseButton: true }).then(function (r) {
					// Con la X (o Esc) se queda en Actividades con el formulario limpio; solo el botón lleva al Historial.
					window.location.href = r && r.isConfirmed === false ? 'index.php?vista=actividades' : (data.redirect || 'index.php?vista=historial');
				});
			} else {
				epAviso('error', 'No se pudo enviar', data.error || 'Ocurrió un inconveniente. Intenta de nuevo.').then(function () {
					if (data.redirect) window.location.href = data.redirect;
				});
				if (data.redirect) return;
				if (btnEnviar) {
					btnEnviar.disabled = false;
					btnEnviar.textContent = 'Enviar registro';
				}
			}
		})
		.catch(function(err) {
			if (window.Swal) Swal.close();
			if (err && err.duplicado) {
				epAviso('warning', 'Ya enviaste este punto', err.mensaje);
			} else if (err && err.foto) {
				epAviso('error', 'No se pudo subir una foto', err.message + ' Intenta de nuevo.').then(function () { if (err.redirect) window.location.href = err.redirect; });
			} else {
				epAviso('error', 'Sin conexión', 'No se pudo enviar el formulario. Revisa tu conexión e intenta de nuevo.');
			}
			if (btnEnviar) {
				btnEnviar.disabled = false;
				btnEnviar.textContent = 'Enviar registro';
			}
		});
	}

	var btnEnv = document.getElementById('epBtnEnviarRegistro');
	if (btnEnv) btnEnv.addEventListener('click', enviarFormularioActivo);
	var btnEnvMov = document.getElementById('epBtnEnviarDesdeMetricas');
	if (btnEnvMov) btnEnvMov.addEventListener('click', enviarFormularioActivo);

	function descargarPptRegistro(btn) {
		var texto = btn.querySelector('span');
		var original = texto ? texto.textContent : '';
		btn.disabled = true;
		if (texto) texto.textContent = 'Generando...';
		fetch('getters/registro_ppt.php?id=' + encodeURIComponent(btn.dataset.id))
			.then(function (res) {
				var tipoRespuesta = res.headers.get('Content-Type') || '';
				if (tipoRespuesta.indexOf('json') !== -1) {
					return res.json().then(function (d) { throw new Error(d.error || 'No se pudo generar la presentación.'); });
				}
				if (!res.ok || tipoRespuesta.indexOf('presentationml') === -1) {
					throw new Error('El servidor no pudo generar la presentación (código ' + res.status + '). Avisa al equipo técnico.');
				}
				return res.blob();
			})
			.then(function (blob) {
				var enlace = document.createElement('a');
				enlace.href = URL.createObjectURL(blob);
				enlace.download = (btn.dataset.tipo || 'registro').toUpperCase() + '_' + (btn.dataset.promotor || 'REGISTRO').toUpperCase().replace(/\s+/g, '_') + '_' + (btn.dataset.fecha || '') + '.pptx';
				document.body.appendChild(enlace);
				enlace.click();
				enlace.remove();
			})
			.catch(function (err) { epAviso('error', 'No se pudo generar', err.message || 'Intenta de nuevo.'); })
			.then(function () {
				btn.disabled = false;
				if (texto) texto.textContent = original;
			});
	}


	// Botón "Slide" de cada registro: arma y descarga el PPTX al instante, sin guardarlo.
	document.addEventListener('click', function(ev) {
		var btnRecPpt = ev.target.closest('.ep-btn-record-ppt');
		if (!btnRecPpt) return;
		ev.stopPropagation(); // No alternar el acordeón de apertura
		if (!btnRecPpt.dataset.id) return;
		// El servidor decide si la actividad tiene formato (ep_ppt_generador); una lista fija aquí dejaba fuera a las lógicas nuevas.
		descargarPptRegistro(btnRecPpt);
	});

	// Inicializar en carga
	if (document.querySelector('.ep-registros-main')) {
		aplicarFiltrosYPaginar(true);
	}
});

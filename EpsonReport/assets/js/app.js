// Mismos paths que ep_icon() en PHP, solo para los íconos que este archivo re-renderiza en JS.
function epIconMarkup(nombre, size) {
	var paths = {
		'chevron': '<path d="M6 9l6 6 6-6"/>',
		'chevron-up': '<path d="M18 15l-6-6-6 6"/>',
		'trash': '<path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-8 0l1 13a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2l1-13"/>',
		'camera': '<rect x="3" y="6" width="18" height="14" rx="2"/><circle cx="12" cy="13" r="3.2"/><path d="M8 6l1.5-2h5L16 6"/>',
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
			var btnIrAMetricasTexto = document.getElementById('epBtnIrAMetricasTexto');
			if (btnIrAMetricasTexto) {
				btnIrAMetricasTexto.textContent = sinStats ? 'Enviar registro' : 'Revisar Métricas';
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
		var slots = bloqueEv ? bloqueEv.querySelectorAll('.ep-foto-slot') : [];
		var count = bloqueEv ? bloqueEv.querySelectorAll('.ep-foto-slot-completa').length : 0;
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
			desktopFill.style.background = (pct === 100) ? '#137A3E' : '#0B1863';
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
			activarTabMovil(btn.dataset.tab);
		});
		// Delegación para botones de apertura del asistente fotográfico
		document.addEventListener('click', function (ev) {
			var btn = ev.target.closest('.ep-btn-desktop-start-wizard, #epBtnDesktopStartWizard, .ep-btn-reabrir-wizard, .ep-btn-reabrir-wizard-desktop');
			if (btn) {
				abrirWizardFotos();
			}
		});
		var btnIrAFotos = document.getElementById('epBtnIrAFotos');
		if (btnIrAFotos) {
			btnIrAFotos.addEventListener('click', function () { activarTabMovil('fotos'); });
		}
		var btnVolverAFormulario = document.getElementById('epBtnVolverAFormulario');
		if (btnVolverAFormulario) {
			btnVolverAFormulario.addEventListener('click', function () { activarTabMovil('formulario'); });
		}
		var btnIrAMetricas = document.getElementById('epBtnIrAMetricas');
		if (btnIrAMetricas) {
			btnIrAMetricas.addEventListener('click', function () {
				var estadisticaVisible = document.querySelector('.ep-estadisticas-actividad:not(.hidden)');
				var sinStats = estadisticaVisible ? estadisticaVisible.dataset.sinEstadisticas === '1' : false;
				if (sinStats) {
					enviarRegistroActividad();
				} else {
					activarTabMovil('metricas');
				}
			});
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

	var wizardSlotsActuales = [];
	var wizardPasoActual = 0;

	function obtenerSlotsActividadVisible() {
		var bloqueVisible = document.querySelector('.ep-formulario-actividad:not(.hidden) .ep-evidencia-actividad') || document.querySelector('.ep-evidencia-actividad:not(.hidden)');
		if (!bloqueVisible) return [];
		return Array.from(bloqueVisible.querySelectorAll('.ep-foto-slot'));
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

		if (wizardTrackSegmentos) {
			wizardTrackSegmentos.innerHTML = '';
			for (var i = 0; i < total; i++) {
				var seg = document.createElement('div');
				var comp = wizardSlotsActuales[i].classList.contains('ep-foto-slot-completa');
				seg.className = 'ep-wizard-seg' + (i === idx ? ' activo' : '') + (comp ? ' completado' : '');
				wizardTrackSegmentos.appendChild(seg);
			}
		}

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
				if (isDone) totalComp++;

				var itemDiv = document.createElement('div');
				itemDiv.className = 'ep-wizard-sidebar-item' + (k === idx ? ' activo' : '') + (isDone ? ' completado' : '');
				itemDiv.dataset.index = k;

				var numSpan = document.createElement('span');
				numSpan.className = 'ep-wizard-sidebar-num' + (isDone ? ' done' : '');
				numSpan.textContent = isDone ? '✓' : (k + 1);

				var infoDiv = document.createElement('div');
				infoDiv.className = 'ep-wizard-sidebar-item-info';
				infoDiv.innerHTML = '<strong>' + txtK + '</strong><span>' + (isDone ? '✓ Cargada' : 'Pendiente') + '</span>';

				itemDiv.appendChild(numSpan);
				itemDiv.appendChild(infoDiv);

				itemDiv.addEventListener('click', function () {
					wizardPasoActual = parseInt(this.dataset.index, 10);
					renderizarWizard();
				});

				wizardSidebarList.appendChild(itemDiv);
			}
			if (wizardSidebarCount) {
				wizardSidebarCount.textContent = totalComp + '/' + total;
			}
		}

		if (wizardBtnAnterior) {
			wizardBtnAnterior.disabled = (idx === 0);
		}
		if (wizardBtnSigTexto) {
			if (idx === total - 1) {
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
	if (wizardBtnSiguiente) {
		wizardBtnSiguiente.addEventListener('click', function () {
			var total = wizardSlotsActuales.length;
			if (wizardPasoActual < total - 1) {
				wizardPasoActual++;
				renderizarWizard();
			} else {
				cerrarWizardFotos();
			}
		});
	}

	// Tipo de actividad activa (mismo criterio que usa el envío del formulario).
	function tipoActividadActiva() {
		var item = document.querySelector('.ep-activity-item.selected');
		var nom = (item ? (item.dataset.nombre || '') : '').toLowerCase();
		if (nom.indexOf('capacita') !== -1) return 'capacitaciones';
		if (nom.indexOf('pop') !== -1) return 'colocacion-pop';
		if (nom.indexOf('day') !== -1) return 'epson-day';
		if (nom.indexOf('exhibi') !== -1) return 'exhibiciones';
		if (nom.indexOf('feria') !== -1 || nom.indexOf('evento') !== -1) return 'evento-ferias';
		return 'activaciones';
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
							resolve(mejor && mejor.size < archivo.size ? mejor : archivo);
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

		if (wizardOverlay && !wizardOverlay.classList.contains('hidden')) {
			renderizarWizard();
			var totalSlots = wizardSlotsActuales.length;
			if (wizardPasoActual < totalSlots - 1) {
				setTimeout(function () {
					wizardPasoActual++;
					renderizarWizard();
				}, 550);
			}
		}
	}

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

	// Evidencia fotográfica: genérico para cualquier actividad, reacciona a cualquier .ep-foto-input sin wiring por actividad.
	document.addEventListener('change', function (ev) {
		if (!ev.target.classList.contains('ep-foto-input')) return;
		var input = ev.target;
		var archivo = input.files && input.files[0];
		if (!archivo) return;
		var slot = input.closest('.ep-foto-slot');
		var preview = slot.querySelector('.ep-foto-preview');
		var vacio = slot.querySelector('.ep-foto-dropzone-vacio');
		var estado = slot.querySelector('.ep-foto-slot-estado');
		preview.src = URL.createObjectURL(archivo);
		preview.classList.remove('hidden');
		vacio.classList.add('hidden');
		slot.classList.add('ep-foto-slot-completa');
		prepararFotoDeSlot(archivo, slot);

		var bloque = slot.closest('.ep-evidencia-bloque');
		var contador = bloque ? bloque.querySelector('.ep-evidencia-contador') : null;
		if (contador) contador.textContent = bloque.querySelectorAll('.ep-foto-slot-completa').length;
		actualizarContadorFotosMovil();

		// Si el asistente guiado está activo, actualiza el visor y avanza automáticamente al siguiente paso
		if (wizardOverlay && !wizardOverlay.classList.contains('hidden')) {
			renderizarWizard();
			var totalSlots = wizardSlotsActuales.length;
			if (wizardPasoActual < totalSlots - 1) {
				setTimeout(function () {
					wizardPasoActual++;
					renderizarWizard();
				}, 550);
			}
		}
	});

	// Campos numéricos de todos los formularios (incluidos los creados dinámicamente): solo enteros, máximo 3 dígitos.
	document.addEventListener('keydown', function (ev) {
		if (ev.target.type !== 'number') return;
		if (['e', 'E', '+', '-', '.', ','].indexOf(ev.key) !== -1) ev.preventDefault();
	});
	document.addEventListener('input', function (ev) {
		var campo = ev.target;
		if (campo.type !== 'number') return;
		var limpio = String(campo.value).replace(/D/g, '').slice(0, 3);
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

	var actModelos = crearGestorModelos('ep-modelo-filas', 'ep-modelo-agregar', 'ep-modelo-total-valor', actualizarEstadisticasActivaciones);
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
	var exhComentarios = document.getElementById('ep-exh-comentarios');

	function actualizarEstadisticasExhibiciones() {
		var statDetalle = document.getElementById('ep-exh-stat-detalle');
		if (!statDetalle) return; // esta actividad no tiene panel de estadísticas todavía

		var muebles = exhMuebles ? (parseFloat(exhMuebles.value) || 0) : 0;
		var rumas = exhRumas ? (parseFloat(exhRumas.value) || 0) : 0;
		var cabeceras = exhCabeceras ? (parseFloat(exhCabeceras.value) || 0) : 0;
		var total = muebles + rumas + cabeceras;

		var totalSpan = document.getElementById('ep-exh-total');
		if (totalSpan) totalSpan.textContent = total;

		var items = [
			{ nombre: 'Cabeceras', cantidad: cabeceras },
			{ nombre: 'Rumas', cantidad: rumas },
			{ nombre: 'Muebles', cantidad: muebles },
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

	[exhMuebles, exhRumas, exhCabeceras].forEach(function (input) {
		if (input) input.addEventListener('input', actualizarEstadisticasExhibiciones);
	});
	if (exhComentarios) exhComentarios.addEventListener('input', actualizarEstadisticasExhibiciones);
	actualizarEstadisticasExhibiciones(); // primer cálculo

	// Fábrica de combo+cantidad de modelos: busca en vivo contra repositorio_productos (getters/repositorio_productos_buscar.php).
	function crearGestorModelos(idFilas, idAgregar, idTotal, onCambio) {
		var filas = document.getElementById(idFilas);
		if (!filas) return null;
		var agregarBtn = document.getElementById(idAgregar);
		var totalValor = document.getElementById(idTotal);
		var buscarReqId = 0;
		var buscarDebounce = null;

		function filaHTML() {
			return '<div class="ep-modelo-fila-nueva"><div class="ep-combo">'
				+ '<button type="button" class="ep-input ep-combo-trigger" data-valor="">'
				+ '<span class="ep-combo-trigger-texto">Elegir modelo</span>' + epIconMarkup('chevron', 14) + '</button>'
				+ '<div class="ep-combo-panel hidden"><input type="text" class="ep-input ep-combo-buscador" placeholder="Buscar modelo..." autocomplete="off">'
				+ '<div class="ep-combo-opciones"></div></div></div>'
				+ '<input type="number" min="0" inputmode="numeric" class="ep-input ep-modelo-cantidad" placeholder="Cant.">'
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
			fetch('getters/repositorio_productos_buscar.php?q=' + encodeURIComponent(texto || ''))
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (miReqId !== buscarReqId) return; // llegó una búsqueda más nueva antes que esta
					var ya = elegidosEnOtrasFilas(combo);
					var coincidencias = (data.ok ? data.productos : []).filter(function (m) { return ya.indexOf(m) === -1; });
					opciones.innerHTML = coincidencias.length
						? coincidencias.map(function (m) { return '<button type="button" class="ep-combo-opcion" data-valor="' + m + '">' + m + '</button>'; }).join('')
						: '<div class="ep-combo-vacio">Sin resultados</div>';
				})
				.catch(function () {
					if (miReqId !== buscarReqId) return;
					opciones.innerHTML = '<div class="ep-combo-vacio">Error al buscar, intenta de nuevo</div>';
				});
		}
		function filtrarCombo(combo, texto) {
			clearTimeout(buscarDebounce);
			buscarDebounce = setTimeout(function () { buscarModelos(combo, texto); }, 250);
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

		return { modelos: function () {
			var out = [];
			filas.querySelectorAll('.ep-modelo-fila-nueva').forEach(function (fila) {
				var nombre = fila.querySelector('.ep-combo-trigger').dataset.valor;
				var cantidad = parseFloat(fila.querySelector('.ep-modelo-cantidad').value) || 0;
				if (nombre && cantidad > 0) out.push({ nombre: nombre, cantidad: cantidad });
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

	var edayModelos = crearGestorModelos('ep-eday-modelo-filas', 'ep-eday-modelo-agregar', 'ep-eday-modelo-total-valor', function () { actualizarEstadisticasEpsonDay(); });

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

	// ---------- Colocación de POP ---------- sin panel de estadísticas, pedido explícito (esta actividad solo tiene formulario).
	var epPopMateriales = ['vibrin', 'hablador', 'rompe-trafico', 'bases', 'displays', 'cenefas'];
	var epPopEntregas = {}; // key -> [{ pdv, ciudad, cantidad }]

	function epPopFilaEntregaHTML() {
		return '<div class="ep-pop-entrega-fila">'
			+ '<input type="text" class="ep-input ep-pop-entrega-pdv" placeholder="PDV">'
			+ '<input type="text" class="ep-input ep-pop-entrega-ciudad" placeholder="Ciudad">'
			+ '<input type="number" min="0" class="ep-input ep-pop-entrega-cantidad" placeholder="Cant.">'
			+ '<button type="button" class="ep-modelo-quitar" aria-label="Quitar entrega">' + epIconMarkup('trash', 14) + '</button></div>';
	}

	function epPopLeerDisponible(key) {
		var bodega = parseFloat((document.getElementById('ep-pop-bodega-' + key) || {}).value) || 0;
		var canales = parseFloat((document.getElementById('ep-pop-canales-' + key) || {}).value) || 0;
		var retail = parseFloat((document.getElementById('ep-pop-retail-' + key) || {}).value) || 0;
		return { bodega: bodega, canales: canales, retail: retail, disponible: bodega - canales - retail };
	}

	function epPopActualizarFilaMaterial(key) {
		var d = epPopLeerDisponible(key);
		var span = document.getElementById('ep-pop-disponible-' + key);
		if (span) span.textContent = d.disponible;
		epPopActualizarTarjetaMaterial(key);
	}

	function epPopActualizarTarjetaMaterial(key) {
		var retail = epPopLeerDisponible(key).retail;
		var filas = epPopEntregas[key] || [];
		var entregado = filas.reduce(function (s, f) { return s + f.cantidad; }, 0);
		var headerRetail = document.getElementById('ep-pop-header-retail-' + key);
		var headerEntregado = document.getElementById('ep-pop-entregado-' + key);
		if (headerRetail) headerRetail.textContent = retail;
		if (headerEntregado) headerEntregado.textContent = entregado;

		var badge = document.getElementById('ep-pop-badge-' + key);
		if (badge) {
			badge.className = 'ep-hist-badge';
			if (retail <= 0 && entregado <= 0) { badge.textContent = 'Sin registrar'; }
			else if (entregado === retail) { badge.className += ' ep-hist-badge-ok'; badge.textContent = 'Completo'; }
			else if (entregado > retail) { badge.className += ' ep-hist-badge-danger'; badge.textContent = 'Excede el Retail'; }
			else { badge.className += ' ep-hist-badge-pendiente'; badge.textContent = 'Pendiente'; }
		}
	}

	function epPopCrearGestorEntregas(key) {
		var filasEl = document.getElementById('ep-pop-entregas-' + key);
		var agregarBtn = document.getElementById('ep-pop-entregas-agregar-' + key);
		if (!filasEl) return;
		epPopEntregas[key] = [];

		function leerFilas() {
			epPopEntregas[key] = [];
			filasEl.querySelectorAll('.ep-pop-entrega-fila').forEach(function (fila) {
				var pdv = fila.querySelector('.ep-pop-entrega-pdv').value.trim();
				var ciudad = fila.querySelector('.ep-pop-entrega-ciudad').value.trim();
				var cantidad = parseFloat(fila.querySelector('.ep-pop-entrega-cantidad').value) || 0;
				if (pdv && cantidad > 0) epPopEntregas[key].push({ pdv: pdv, ciudad: ciudad, cantidad: cantidad });
			});
			epPopActualizarTarjetaMaterial(key);
		}

		filasEl.addEventListener('input', leerFilas);
		filasEl.addEventListener('click', function (ev) {
			var quitar = ev.target.closest('.ep-modelo-quitar');
			if (quitar) { quitar.closest('.ep-pop-entrega-fila').remove(); leerFilas(); }
		});
		if (agregarBtn) agregarBtn.addEventListener('click', function () { filasEl.insertAdjacentHTML('beforeend', epPopFilaEntregaHTML()); });
	}

	epPopMateriales.forEach(function (key) {
		['ep-pop-bodega-', 'ep-pop-canales-', 'ep-pop-retail-'].forEach(function (prefijo) {
			var input = document.getElementById(prefijo + key);
			if (input) input.addEventListener('input', function () { epPopActualizarFilaMaterial(key); });
		});
		epPopCrearGestorEntregas(key);
		epPopActualizarFilaMaterial(key);
	});
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
				var filaContenedor = document.createElement('div');
				filaContenedor.className = 'ep-evidencia-fila';
				filaContenedor.style.marginTop = '4px';
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
				previewFormFotos.appendChild(filaContenedor);
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
			datos.append('logica_id', nuevaLogica ? nuevaLogica.value : '');
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
		});
	}

	// =========================================================================
	// ENVÍO DE FORMULARIO DE CAMPO EN VIVO (MÓDULO ACTIVIDADES)
	// =========================================================================
	// Ventanas de aviso (SweetAlert2); si no cargó, cae al alert nativo.
	function epAviso(icono, titulo, texto, boton) {
		if (!window.Swal) { alert(titulo); return Promise.resolve(); }
		return Swal.fire({ icon: icono, title: titulo, html: texto || '', confirmButtonText: boton || 'Entendido', confirmButtonColor: '#10218B', allowOutsideClick: false });
	}
	function epToast(icono, titulo) {
		if (!window.Swal) { alert(titulo); return; }
		Swal.mixin({ toast: true, position: 'top', showConfirmButton: false, timer: 3500, timerProgressBar: true }).fire({ icon: icono, title: titulo });
	}

	// Validador del envío: todos los campos visibles del formulario y todas las fotos son obligatorios; solo comentarios es opcional.
	function validarRegistroActivo() {
		var panel = document.querySelector('.ep-formulario-actividad:not(.hidden)');
		var camposVacios = [];
		if (panel) {
			panel.querySelectorAll('input:not([type="hidden"]):not([type="file"]):not([type="checkbox"]):not([type="radio"]), select, textarea').forEach(function (el) {
				if (el.disabled || el.readOnly || /comentario/i.test(el.id) || el.offsetParent === null) return;
				if (String(el.value).trim() === '') camposVacios.push(el);
			});
			panel.querySelectorAll('.ep-combo-trigger').forEach(function (el) {
				if (el.offsetParent !== null && !el.dataset.valor) camposVacios.push(el);
			});
		}
		var bloqueFotos = document.querySelector('.ep-evidencia-actividad:not(.hidden)');
		var fotosFaltan = [];
		if (bloqueFotos) {
			bloqueFotos.querySelectorAll('.ep-foto-slot').forEach(function (s) {
				if (!s._blob && !s.dataset.fotoRuta) fotosFaltan.push(s);
			});
		}
		document.querySelectorAll('.ep-campo-error, .ep-foto-error').forEach(function (el) { el.classList.remove('ep-campo-error', 'ep-foto-error'); });
		camposVacios.forEach(function (el) { el.classList.add('ep-campo-error'); });
		fotosFaltan.forEach(function (s) { s.classList.add('ep-foto-error'); });
		if (camposVacios.length === 0 && fotosFaltan.length === 0) return true;

		var partes = [];
		if (camposVacios.length) partes.push('<b>' + camposVacios.length + '</b> campo' + (camposVacios.length === 1 ? '' : 's') + ' del formulario sin llenar');
		if (fotosFaltan.length) partes.push('<b>' + fotosFaltan.length + '</b> foto' + (fotosFaltan.length === 1 ? '' : 's') + ' sin subir');
		epAviso('warning', 'Falta información', 'Antes de enviar completa:<br>' + partes.join('<br>') + '<br><small>Solo los comentarios son opcionales.</small>').then(function () {
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
	document.addEventListener('click', function (ev) { var t = ev.target.closest('.ep-combo-opcion'); if (t) { var c = t.closest('.ep-combo'); if (c) { var tr = c.querySelector('.ep-combo-trigger'); if (tr) tr.classList.remove('ep-campo-error'); } } });

	function enviarFormularioActivo() {
		if (!validarRegistroActivo()) return;
		var itemSeleccionado = document.querySelector('.ep-activity-item.selected');
		var actNombre = itemSeleccionado ? (itemSeleccionado.dataset.nombre || 'Activaciones') : 'Activaciones';
		var actBadge = itemSeleccionado ? (itemSeleccionado.querySelector('.ep-activity-badge') ? itemSeleccionado.querySelector('.ep-activity-badge').textContent.trim() : '') : '';

		var tipo = 'activaciones';
		var nomLower = actNombre.toLowerCase();
		if (nomLower.indexOf('capacita') !== -1) tipo = 'capacitaciones';
		else if (nomLower.indexOf('pop') !== -1) tipo = 'colocacion-pop';
		else if (nomLower.indexOf('day') !== -1) tipo = 'epson-day';
		else if (nomLower.indexOf('exhibi') !== -1) tipo = 'exhibiciones';
		else if (nomLower.indexOf('feria') !== -1 || nomLower.indexOf('evento') !== -1) tipo = 'evento-ferias';

		var valores = {};
		if (tipo === 'activaciones') {
			valores.nacional = document.getElementById('ep-act-nacional') ? document.getElementById('ep-act-nacional').value : '';
			valores.coberturadas = document.getElementById('ep-act-coberturadas') ? document.getElementById('ep-act-coberturadas').value : '';
			valores.visitaron = document.getElementById('ep-act-visitaron') ? document.getElementById('ep-act-visitaron').value : '';
			valores.interactuaron = document.getElementById('ep-act-interactuaron') ? document.getElementById('ep-act-interactuaron').value : '';
			valores.compraron = document.getElementById('ep-act-compraron') ? document.getElementById('ep-act-compraron').value : '';
			valores.comentarios = document.getElementById('ep-act-comentarios') ? document.getElementById('ep-act-comentarios').value : '';
			var mods = [];
			document.querySelectorAll('#ep-modelo-filas .ep-modelo-fila').forEach(function(f) {
				var sel = f.querySelector('.ep-modelo-select');
				var cant = f.querySelector('.ep-modelo-cantidad');
				if (sel && cant && parseInt(cant.value, 10) > 0) {
					mods.push({ modelo: sel.value, cantidad: parseInt(cant.value, 10) });
				}
			});
			valores.modelos = mods;
		} else if (tipo === 'capacitaciones') {
			valores.asistentes = document.getElementById('ep-cap-asistentes') ? document.getElementById('ep-cap-asistentes').value : '';
			valores.aprobados = document.getElementById('ep-cap-aprobados') ? document.getElementById('ep-cap-aprobados').value : '';
			valores.horas = document.getElementById('ep-cap-horas') ? document.getElementById('ep-cap-horas').value : '';
			valores.temas = document.getElementById('ep-cap-temas') ? document.getElementById('ep-cap-temas').value : '';
			valores.comentarios = document.getElementById('ep-cap-comentarios') ? document.getElementById('ep-cap-comentarios').value : '';
		} else if (tipo === 'colocacion-pop') {
			var pops = [];
			document.querySelectorAll('.ep-pop-fila').forEach(function(pf) {
				var mat = pf.querySelector('.ep-pop-nombre');
				var b = pf.querySelector('.ep-pop-bodega');
				var c = pf.querySelector('.ep-pop-canales');
				var r = pf.querySelector('.ep-pop-retail');
				var d = pf.querySelector('.ep-pop-disponible');
				if (mat) {
					pops.push({
						material: mat.textContent.trim(),
						bodega: b ? parseInt(b.value, 10) || 0 : 0,
						canales: c ? parseInt(c.value, 10) || 0 : 0,
						retail: r ? parseInt(r.value, 10) || 0 : 0,
						disponible: d ? parseInt(d.textContent, 10) || 0 : 0
					});
				}
			});
			valores.pop_materiales = pops;
			valores.comentarios = document.getElementById('ep-pop-comentarios') ? document.getElementById('ep-pop-comentarios').value : '';
		} else if (tipo === 'epson-day') {
			valores.nacional = document.getElementById('ep-eps-nacional') ? document.getElementById('ep-eps-nacional').value : '';
			valores.coberturadas = document.getElementById('ep-eps-coberturadas') ? document.getElementById('ep-eps-coberturadas').value : '';
			valores.visitaron = document.getElementById('ep-eps-visitaron') ? document.getElementById('ep-eps-visitaron').value : '';
			valores.interactuaron = document.getElementById('ep-eps-interactuaron') ? document.getElementById('ep-eps-interactuaron').value : '';
			valores.compraron = document.getElementById('ep-eps-compraron') ? document.getElementById('ep-eps-compraron').value : '';
			valores.comentarios = document.getElementById('ep-eps-comentarios') ? document.getElementById('ep-eps-comentarios').value : '';
		} else if (tipo === 'exhibiciones') {
			valores.muebles = document.getElementById('ep-exh-muebles') ? document.getElementById('ep-exh-muebles').value : '';
			valores.rumas = document.getElementById('ep-exh-rumas') ? document.getElementById('ep-exh-rumas').value : '';
			valores.cabeceras = document.getElementById('ep-exh-cabeceras') ? document.getElementById('ep-exh-cabeceras').value : '';
			valores.comentarios = document.getElementById('ep-exh-comentarios') ? document.getElementById('ep-exh-comentarios').value : '';
		} else if (tipo === 'evento-ferias') {
			valores.visitaron = document.getElementById('ep-fer-visitaron') ? document.getElementById('ep-fer-visitaron').value : '';
			valores.interactuaron = document.getElementById('ep-fer-interactuaron') ? document.getElementById('ep-fer-interactuaron').value : '';
			valores.compraron = document.getElementById('ep-fer-compraron') ? document.getElementById('ep-fer-compraron').value : '';
			valores.comentarios = document.getElementById('ep-fer-comentarios') ? document.getElementById('ep-fer-comentarios').value : '';
		}

		// Fotos: se comprimen al elegirlas y se suben a Azure recién ahora, al enviar.
		var bloqueEv = document.querySelector('.ep-evidencia-actividad:not(.hidden)');
		var slotsFotos = bloqueEv ? Array.prototype.slice.call(bloqueEv.querySelectorAll('.ep-foto-slot')) : [];
		if (slotsFotos.some(function (s) { return s.dataset.subiendo; })) {
			epAviso('info', 'Preparando fotos', 'Hay fotos preparándose todavía. Espera unos segundos e intenta de nuevo.');
			return;
		}

		var payload = {
			tipo: tipo,
			actividad_label: actNombre,
			actividad_badge: actBadge,
			punto_venta: 'SUKASA - MALL DEL SOL',
			cadena: 'Sukasa',
			ciudad: 'GUAYAQUIL',
			canal: 'RETAIL',
			valores: valores,
			fotos: {}
		};

		var btnEnviar = document.getElementById('epBtnEnviarRegistro');
		if (btnEnviar) {
			btnEnviar.disabled = true;
			btnEnviar.textContent = 'Enviando formulario...';
		}

		subirFotosPendientes(slotsFotos).then(function (fotos) {
			payload.fotos = fotos;
			return fetch('getters/guardar_registro.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(payload)
			});
		})
		.then(function(res) { return res.json(); })
		.then(function(data) {
			if (data.success) {
epAviso('success', 'Registro enviado', 'Tu reporte quedó guardado correctamente.<br><span style="display:inline-block;margin-top:8px;padding:4px 10px;border-radius:6px;background:#EEF3FD;color:#10218B;font-weight:700;font-size:13px;">' + data.id + '</span>', 'Ver mis registros').then(function () {					window.location.href = data.redirect || 'index.php?vista=historial';				});
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
			if (err && err.foto) {
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
	var btnBorrador = document.getElementById('epBtnGuardarBorrador');
	if (btnBorrador) {
		btnBorrador.addEventListener('click', function() {
			epAviso('info', 'Borradores', 'Guardar borradores todavía no está disponible.');
		});
	}

	// =========================================================================
	// CONTROLADOR DE REGISTROS DE ACTIVIDADES: ALTA DENSIDAD & DUAL VERSION
	// (USUARIOS VS ADMIN, AGRUPACIÓN POR USUARIO, FILTROS, PAGINACIÓN Y LIGHTBOX)
	// =========================================================================
	var epPillsActividades = document.getElementById('epPillsActividades');
	var epBuscarRegistro = document.getElementById('epBuscarRegistro');
	var epFiltroPromotor = document.getElementById('epFiltroPromotor');
	var epPorPagina = document.getElementById('epPorPagina');
	var epRangoBotones = document.getElementById('epRangoBotones');
	var epFechaDesde = document.getElementById('epFechaDesde');
	var epFechaHasta = document.getElementById('epFechaHasta');
	var epLimpiarFiltros = document.getElementById('epLimpiarFiltros');
	var epHistResumen = document.getElementById('ep-hist-resumen');
	var epBtnVistaFichas = document.getElementById('epBtnVistaFichas');
	var epBtnVistaTabla = document.getElementById('epBtnVistaTabla');
	var epBtnAgruparUsuario = document.getElementById('epBtnAgruparUsuario');
	var epBtnAgruparFecha = document.getElementById('epBtnAgruparFecha');
	var epModoUsuarios = document.getElementById('ep-hist-modo-usuarios');
	var epModoFechas = document.getElementById('ep-hist-modo-fechas');
	var epHistTablaContainer = document.getElementById('ep-hist-tabla-container');
	var btnExpandirTodos = document.getElementById('ep-hist-expandir-todos');
	var epPaginacionWrap = document.getElementById('epPaginacionWrap');
	var epPaginaRango = document.getElementById('epPaginaRango');
	var epPaginaTotal = document.getElementById('epPaginaTotal');
	var epPaginacionControles = document.getElementById('epPaginacionControles');

	var vistaActual = 'fichas';
	var agrupacionActual = epModoUsuarios ? 'usuario' : 'fecha';
	var filtroActualTipo = 'all';
	var filtroActualPromotor = 'all';
	var filtroActualTexto = '';
	var filtroActualRango = 'all';
	var filtroFechaDesde = '';
	var filtroFechaHasta = '';
	var porPagina = epPorPagina ? epPorPagina.value : '15';
	var paginaActual = 1;
	var indicesFiltrados = [];

	function scrollHaciaLista() {
		var target = (vistaActual === 'tabla') ? epHistTablaContainer : (agrupacionActual === 'usuario' ? epModoUsuarios : epModoFechas);
		if (target) {
			target.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	}

	function cambiarModoVista(nuevoModo) {
		vistaActual = nuevoModo;
		var esTabla = (vistaActual === 'tabla');

		if (esTabla) {
			if (epModoUsuarios) epModoUsuarios.classList.add('hidden');
			if (epModoFechas) epModoFechas.classList.add('hidden');
			if (epHistTablaContainer) epHistTablaContainer.classList.remove('hidden');
		} else {
			if (epHistTablaContainer) epHistTablaContainer.classList.add('hidden');
			if (agrupacionActual === 'usuario' && epModoUsuarios) {
				epModoUsuarios.classList.remove('hidden');
				if (epModoFechas) epModoFechas.classList.add('hidden');
			} else {
				if (epModoFechas) epModoFechas.classList.remove('hidden');
				if (epModoUsuarios) epModoUsuarios.classList.add('hidden');
			}
		}

		if (epBtnVistaFichas) epBtnVistaFichas.classList.toggle('ep-view-btn-activo', !esTabla);
		if (epBtnVistaTabla) epBtnVistaTabla.classList.toggle('ep-view-btn-activo', esTabla);

		if (btnExpandirTodos) {
			btnExpandirTodos.style.display = esTabla ? 'none' : 'inline-flex';
		}
		if (epBtnAgruparUsuario && epBtnAgruparFecha) {
			epBtnAgruparUsuario.parentElement.style.display = esTabla ? 'none' : 'inline-flex';
		}
	}

	// Conmutador de Agrupación para Administrador: Por Usuario vs Por Fecha
	if (epBtnAgruparUsuario && epBtnAgruparFecha) {
		epBtnAgruparUsuario.addEventListener('click', function() {
			agrupacionActual = 'usuario';
			epBtnAgruparUsuario.classList.add('ep-group-btn-activo');
			epBtnAgruparFecha.classList.remove('ep-group-btn-activo');
			if (vistaActual !== 'tabla') {
				if (epModoUsuarios) epModoUsuarios.classList.remove('hidden');
				if (epModoFechas) epModoFechas.classList.add('hidden');
			}
		});
		epBtnAgruparFecha.addEventListener('click', function() {
			agrupacionActual = 'fecha';
			epBtnAgruparFecha.classList.add('ep-group-btn-activo');
			epBtnAgruparUsuario.classList.remove('ep-group-btn-activo');
			if (vistaActual !== 'tabla') {
				if (epModoFechas) epModoFechas.classList.remove('hidden');
				if (epModoUsuarios) epModoUsuarios.classList.add('hidden');
			}
		});
	}

	function renderizarBotonesPaginacion(totalPaginas) {
		if (!epPaginacionControles) return;
		epPaginacionControles.innerHTML = '';

		if (totalPaginas <= 1) {
			return;
		}

		// Botón Anterior
		var btnAnt = document.createElement('button');
		btnAnt.type = 'button';
		btnAnt.className = 'ep-pag-btn';
		btnAnt.innerHTML = '&lsaquo; Ant';
		btnAnt.disabled = (paginaActual <= 1);
		btnAnt.addEventListener('click', function() {
			if (paginaActual > 1) {
				paginaActual--;
				aplicarFiltrosYPaginar(false);
				scrollHaciaLista();
			}
		});
		epPaginacionControles.appendChild(btnAnt);

		// Lista dinámica de páginas con elipsis
		var paginasAMostrar = [];
		if (totalPaginas <= 7) {
			for (var p = 1; p <= totalPaginas; p++) paginasAMostrar.push(p);
		} else {
			paginasAMostrar.push(1);
			if (paginaActual > 3) paginasAMostrar.push('...');
			var start = Math.max(2, paginaActual - 1);
			var end = Math.min(totalPaginas - 1, paginaActual + 1);
			for (var i = start; i <= end; i++) {
				if (paginasAMostrar.indexOf(i) === -1) paginasAMostrar.push(i);
			}
			if (paginaActual < totalPaginas - 2) paginasAMostrar.push('...');
			if (paginasAMostrar.indexOf(totalPaginas) === -1) paginasAMostrar.push(totalPaginas);
		}

		paginasAMostrar.forEach(function(item) {
			if (item === '...') {
				var span = document.createElement('span');
				span.className = 'ep-pag-puntos';
				span.textContent = '…';
				epPaginacionControles.appendChild(span);
			} else {
				var btnNum = document.createElement('button');
				btnNum.type = 'button';
				btnNum.className = 'ep-pag-btn' + (item === paginaActual ? ' activo' : '');
				btnNum.textContent = item;
				btnNum.addEventListener('click', function() {
					paginaActual = item;
					aplicarFiltrosYPaginar(false);
					scrollHaciaLista();
				});
				epPaginacionControles.appendChild(btnNum);
			}
		});

		// Botón Siguiente
		var btnSig = document.createElement('button');
		btnSig.type = 'button';
		btnSig.className = 'ep-pag-btn';
		btnSig.innerHTML = 'Sig &rsaquo;';
		btnSig.disabled = (paginaActual >= totalPaginas);
		btnSig.addEventListener('click', function() {
			if (paginaActual < totalPaginas) {
				paginaActual++;
				aplicarFiltrosYPaginar(false);
				scrollHaciaLista();
			}
		});
		epPaginacionControles.appendChild(btnSig);
	}

	function aplicarFiltrosYPaginar(resetearPagina) {
		if (resetearPagina !== false) {
			paginaActual = 1;
		}

		var activeContainer = (agrupacionActual === 'usuario' && epModoUsuarios) ? epModoUsuarios : epModoFechas;
		var cards = activeContainer ? activeContainer.querySelectorAll('.ep-expediente-card') : document.querySelectorAll('.ep-expediente-card');
		var rows = document.querySelectorAll('.ep-datagrid-row');
		var totalItems = Math.max(cards.length, rows.length);

		var pillActiva = epPillsActividades ? epPillsActividades.querySelector('.selected') : null;
		filtroActualTipo = pillActiva ? (pillActiva.dataset.tipo || 'all') : 'all';
		filtroActualPromotor = epFiltroPromotor ? epFiltroPromotor.value : 'all';
		filtroActualTexto = epBuscarRegistro ? epBuscarRegistro.value.trim().toLowerCase() : '';
		filtroFechaDesde = epFechaDesde ? epFechaDesde.value : '';
		filtroFechaHasta = epFechaHasta ? epFechaHasta.value : '';
		porPagina = epPorPagina ? epPorPagina.value : '15';

		indicesFiltrados = [];

		for (var i = 0; i < totalItems; i++) {
			var itemEl = cards[i] || rows[i];
			if (!itemEl) continue;

			var tipo = itemEl.dataset.tipo || '';
			var promotor = itemEl.dataset.promotor || '';
			var fecha = itemEl.dataset.fecha || '';
			var busqueda = itemEl.dataset.busqueda || '';

			var coincideTipo = (filtroActualTipo === 'all' || tipo === filtroActualTipo);
			var coincidePromotor = (filtroActualPromotor === 'all' || promotor === filtroActualPromotor);
			var coincideTexto = (filtroActualTexto === '' || busqueda.indexOf(filtroActualTexto) !== -1);

			var coincideFechasManuales = true;
			if (filtroFechaDesde && fecha && fecha < filtroFechaDesde) coincideFechasManuales = false;
			if (filtroFechaHasta && fecha && fecha > filtroFechaHasta) coincideFechasManuales = false;

			if (coincideTipo && coincidePromotor && coincideTexto && coincideFechasManuales) {
				indicesFiltrados.push(i);
			}
		}

		var totalFiltrados = indicesFiltrados.length;
		var numPorPagina = (porPagina === 'all') ? Infinity : parseInt(porPagina, 10);
		var totalPaginas = (numPorPagina === Infinity) ? 1 : (Math.ceil(totalFiltrados / numPorPagina) || 1);

		if (paginaActual > totalPaginas) paginaActual = totalPaginas;
		if (paginaActual < 1) paginaActual = 1;

		var inicio = (numPorPagina === Infinity) ? 0 : (paginaActual - 1) * numPorPagina;
		var fin = (numPorPagina === Infinity) ? totalFiltrados : Math.min(inicio + numPorPagina, totalFiltrados);

		var setVisibles = {};
		for (var k = inicio; k < fin; k++) {
			setVisibles[indicesFiltrados[k]] = true;
		}

		// Visibilidad en Fichas del contenedor activo
		cards.forEach(function(card, idx) {
			card.classList.toggle('hidden', !setVisibles[idx]);
		});

		// Visibilidad en Grupos de Usuario (Admin)
		document.querySelectorAll('.ep-user-group-card').forEach(function(uCard) {
			var hayVisibles = uCard.querySelectorAll('.ep-hist-record:not(.hidden)').length > 0;
			uCard.classList.toggle('hidden', !hayVisibles);
			// Auto-expandir grupo si el admin buscó un usuario o texto específico
			if (hayVisibles && (filtroActualPromotor !== 'all' || filtroActualTexto !== '')) {
				uCard.classList.add('ep-user-group-abierto');
				var head = uCard.querySelector('.ep-user-group-header');
				if (head) head.setAttribute('aria-expanded', 'true');
			}
		});

		// Visibilidad en Secciones de Día
		document.querySelectorAll('.ep-hist-day').forEach(function(sec) {
			var hayVisibles = sec.querySelectorAll('.ep-hist-record:not(.hidden)').length > 0;
			sec.classList.toggle('hidden', !hayVisibles);
		});

		// Visibilidad en Tabla Data Grid
		rows.forEach(function(row, idx) {
			row.classList.toggle('hidden', !setVisibles[idx]);
		});

		// Resumen y Paginación UI
		if (epHistResumen) {
			epHistResumen.textContent = 'Mostrando ' + totalFiltrados + ' formulario' + (totalFiltrados === 1 ? '' : 's');
		}

		if (epPaginaTotal) {
			epPaginaTotal.textContent = totalFiltrados;
		}
		if (epPaginaRango) {
			if (totalFiltrados === 0) {
				epPaginaRango.textContent = '0';
			} else {
				epPaginaRango.textContent = (inicio + 1) + '–' + fin;
			}
		}

		// Botón Limpiar Filtros
		if (epLimpiarFiltros) {
			var hayFiltroActivo = (filtroActualTipo !== 'all' || filtroActualPromotor !== 'all' || filtroActualTexto !== '' || filtroFechaDesde !== '' || filtroFechaHasta !== '');
			epLimpiarFiltros.classList.toggle('hidden', !hayFiltroActivo);
		}

		renderizarBotonesPaginacion(totalPaginas);
	}

	// Conmutador de vistas
	if (epBtnVistaFichas) {
		epBtnVistaFichas.addEventListener('click', function() {
			cambiarModoVista('fichas');
		});
	}
	if (epBtnVistaTabla) {
		epBtnVistaTabla.addEventListener('click', function() {
			cambiarModoVista('tabla');
		});
	}

	// Filtro por píldoras de actividad
	if (epPillsActividades) {
		epPillsActividades.addEventListener('click', function(ev) {
			var pill = ev.target.closest('.ep-reg-pill-compact, .ep-reg-pill');
			if (!pill) return;
			epPillsActividades.querySelectorAll('.ep-reg-pill-compact, .ep-reg-pill').forEach(function(p) { p.classList.remove('selected'); });
			pill.classList.add('selected');
			aplicarFiltrosYPaginar(true);
		});
	}

	// Filtro por usuario / promotor
	if (epFiltroPromotor) {
		epFiltroPromotor.addEventListener('change', function() {
			aplicarFiltrosYPaginar(true);
		});
	}

	// Buscador de texto
	if (epBuscarRegistro) {
		epBuscarRegistro.addEventListener('input', function() {
			aplicarFiltrosYPaginar(true);
		});
	}

	// Selector de registros por página
	if (epPorPagina) {
		epPorPagina.addEventListener('change', function() {
			aplicarFiltrosYPaginar(true);
		});
	}

	// Inputs de fecha manual Desde / Hasta
	if (epFechaDesde) {
		epFechaDesde.addEventListener('change', function() {
			aplicarFiltrosYPaginar(true);
		});
	}
	if (epFechaHasta) {
		epFechaHasta.addEventListener('change', function() {
			aplicarFiltrosYPaginar(true);
		});
	}

	// Limpiar todos los filtros
	if (epLimpiarFiltros) {
		epLimpiarFiltros.addEventListener('click', function() {
			if (epBuscarRegistro) epBuscarRegistro.value = '';
			if (epFiltroPromotor) epFiltroPromotor.value = 'all';
			if (epFechaDesde) epFechaDesde.value = '';
			if (epFechaHasta) epFechaHasta.value = '';
			if (epPillsActividades) {
				epPillsActividades.querySelectorAll('.ep-reg-pill-compact, .ep-reg-pill').forEach(function(p, idx) {
					p.classList.toggle('selected', idx === 0);
				});
			}
			aplicarFiltrosYPaginar(true);
		});
	}

	// Acordeones de Grupo de Usuario (Admin Mode)
	document.addEventListener('click', function(ev) {
		var uHead = ev.target.closest('.ep-user-group-header');
		if (!uHead) return;
		var uCard = uHead.closest('.ep-user-group-card');
		if (!uCard) return;
		var estaAbierto = uCard.classList.toggle('ep-user-group-abierto');
		uHead.setAttribute('aria-expanded', estaAbierto ? 'true' : 'false');
	});

	// Acordeones: click en la cabecera del registro para desplegar/plegar suavemente
	document.addEventListener('click', function(ev) {
		var cabecera = ev.target.closest('.ep-hist-record-cabecera');
		if (!cabecera) return;
		if (ev.target.closest('a') || ev.target.closest('button')) return;

		var record = cabecera.closest('.ep-hist-record');
		if (!record) return;
		var abierto = record.classList.contains('ep-hist-record-abierto');
		record.classList.toggle('ep-hist-record-abierto', !abierto);

		var indicator = record.querySelector('.ep-accordion-indicator-compact, .ep-accordion-indicator');
		if (indicator) {
			indicator.setAttribute('aria-expanded', !abierto ? 'true' : 'false');
		}
	});

	// Plegar / Expandir todos los registros (en vista Fichas)
	if (btnExpandirTodos) {
		btnExpandirTodos.addEventListener('click', function() {
			var estado = btnExpandirTodos.getAttribute('data-estado');
			var abrir = (estado === 'abrir');

			// Abrir o cerrar grupos de usuario si existen
			document.querySelectorAll('.ep-user-group-card:not(.hidden)').forEach(function(uCard) {
				uCard.classList.toggle('ep-user-group-abierto', abrir);
				var uHead = uCard.querySelector('.ep-user-group-header');
				if (uHead) uHead.setAttribute('aria-expanded', abrir ? 'true' : 'false');
			});

			// Abrir o cerrar fichas individuales
			document.querySelectorAll('.ep-hist-record:not(.hidden)').forEach(function(record) {
				record.classList.toggle('ep-hist-record-abierto', abrir);
				var indicator = record.querySelector('.ep-accordion-indicator-compact, .ep-accordion-indicator');
				if (indicator) {
					indicator.setAttribute('aria-expanded', abrir ? 'true' : 'false');
				}
			});
			btnExpandirTodos.setAttribute('data-estado', abrir ? 'cerrar' : 'abrir');
			var txt = document.getElementById('epExpandirTodosTexto');
			if (txt) txt.textContent = abrir ? 'Plegar' : 'Expandir';
		});
	}

	// Desde la tabla: Inspeccionar ficha correspondiente
	document.addEventListener('click', function(ev) {
		var btnVer = ev.target.closest('.ep-datagrid-ver-btn');
		if (!btnVer) return;
		var targetCardId = btnVer.dataset.targetCard;
		if (!targetCardId) return;
		var card = document.getElementById(targetCardId);
		if (!card) return;

		// 1. Cambiar a vista Fichas
		cambiarModoVista('fichas');

		// 2. Si está dentro de un grupo de usuario, asegurar que el grupo esté abierto
		var parentGroup = card.closest('.ep-user-group-card');
		if (parentGroup) {
			parentGroup.classList.add('ep-user-group-abierto');
			var uHead = parentGroup.querySelector('.ep-user-group-header');
			if (uHead) uHead.setAttribute('aria-expanded', 'true');
		}

		// 3. Abrir la ficha
		card.classList.add('ep-hist-record-abierto');
		var indicator = card.querySelector('.ep-accordion-indicator-compact, .ep-accordion-indicator');
		if (indicator) {
			indicator.setAttribute('aria-expanded', 'true');
		}

		// 4. Scroll suave y efecto resalte
		card.scrollIntoView({ behavior: 'smooth', block: 'center' });
		card.classList.remove('ep-card-highlight');
		void card.offsetWidth;
		card.classList.add('ep-card-highlight');
	});

	// Modal para inspección de evidencias fotográficas
	var modalFoto = document.getElementById('epModalFotoEvidencia');
	var modalBackdrop = document.getElementById('epModalFotoBackdrop');
	var modalCerrar = document.getElementById('epModalFotoCerrar');
	var modalFotoDesc = document.getElementById('epModalFotoDesc');
	var modalFotoTienda = document.getElementById('epModalFotoTienda');
	var modalFotoPromotor = document.getElementById('epModalFotoPromotor');

	function cerrarModalFoto() {
		if (modalFoto) modalFoto.classList.add('hidden');
	}
	if (modalBackdrop) modalBackdrop.addEventListener('click', cerrarModalFoto);
	if (modalCerrar) modalCerrar.addEventListener('click', cerrarModalFoto);
	document.addEventListener('keydown', function(ev) {
		if (ev.key === 'Escape' && modalFoto && !modalFoto.classList.contains('hidden')) {
			cerrarModalFoto();
		}
	});

	document.addEventListener('click', function(ev) {
		var fotoCard = ev.target.closest('.ep-reg-foto-card');
		if (!fotoCard) return;
		var label = fotoCard.dataset.fotoLabel || 'Evidencia Fotográfica';
		var tienda = fotoCard.dataset.fotoTienda || 'Punto de Venta';
		var promotor = fotoCard.dataset.fotoPromotor || 'Promotor';

		if (modalFotoDesc) modalFotoDesc.textContent = label;
		if (modalFotoTienda) modalFotoTienda.textContent = tienda;
		if (modalFotoPromotor) modalFotoPromotor.textContent = promotor;
		if (modalFoto) modalFoto.classList.remove('hidden');
	});

	// =========================================================================
	// MECÁNICA DE EXPORTACIÓN Y SELECCIÓN DE PLANTILLAS POWERPOINT (PPTX)
	// =========================================================================
	var modalPPT = document.getElementById('epModalExportarPPT');
	var modalPptBackdrop = document.getElementById('epModalPptBackdrop');
	var modalPptCerrar = document.getElementById('epModalPptCerrar');
	var modalPptCancelar = document.getElementById('epModalPptCancelar');
	var btnAbrirExportadorPPT = document.getElementById('epBtnAbrirExportadorPPT');
	var selectPptUsuario = document.getElementById('epPptSelectUsuario');
	var selectPptFecha = document.getElementById('epPptSelectFecha');
	var templatesList = document.getElementById('epPptTemplatesList');
	var slidePreviewFecha = document.getElementById('epSlidePreviewFecha');
	var slidePreviewTag = document.getElementById('epSlidePreviewTag');
	var slidePreviewTienda = document.getElementById('epSlidePreviewTienda');
	var slidePreviewPromotor = document.getElementById('epSlidePreviewPromotor');
	var slideDynamicContent = document.getElementById('epSlideDynamicContent');
	var btnEjecutarDescargaPPT = document.getElementById('epBtnEjecutarDescargaPPT');
	var pptDescargaStatus = document.getElementById('epPptDescargaStatus');
	var pptStatusTitulo = document.getElementById('epPptStatusTitulo');
	var pptStatusSub = document.getElementById('epPptStatusSub');
	var btnDescargaPptTexto = document.getElementById('epBtnDescargaPptTexto');

	var nombresPlantillas = {
		'activaciones': 'ACTIVACIONES DE CAMPO',
		'capacitaciones': 'CAPACITACIONES A LA FUERZA DE VENTAS',
		'epson-day': 'JORNADA EPSON DAY',
		'colocacion-pop': 'COLOCACIÓN DE MATERIAL POP',
		'exhibiciones': 'EXHIBICIONES QUE INSPIRAN',
		'evento-ferias': 'EVENTO O FERIAS',
		'consolidado': 'REPORTE DIARIO CONSOLIDADO (TODAS)'
	};

	function abrirModalPPT(config) {
		if (!modalPPT) return;
		config = config || {};

		// Pre-seleccionar usuario si viene en config
		if (config.promotor && selectPptUsuario) {
			selectPptUsuario.value = config.promotor;
			if (!selectPptUsuario.value) selectPptUsuario.selectedIndex = 0;
		}

		// Pre-seleccionar fecha si viene en config
		if (config.fecha && selectPptFecha) {
			selectPptFecha.value = String(config.fecha).slice(0, 7);
		}

		// Pre-seleccionar tienda si viene en config
		if (config.tienda && slidePreviewTienda) {
			slidePreviewTienda.textContent = config.tienda;
		}

		// Pre-seleccionar plantilla si viene en config
		if (config.tipo && templatesList) {
			var opt = templatesList.querySelector('.ep-ppt-tpl-option[data-template="' + config.tipo + '"]');
			if (opt) {
				actualizarPlantillaPPT(config.tipo, opt);
			}
		}

		actualizarSlidePreview();
		if (pptDescargaStatus) pptDescargaStatus.classList.add('hidden');
		if (btnEjecutarDescargaPPT) btnEjecutarDescargaPPT.disabled = false;
		if (btnDescargaPptTexto) btnDescargaPptTexto.textContent = 'Descargar Presentación (.pptx)';
		modalPPT.classList.remove('hidden');
	}

	var pptFootMeta = document.getElementById('epPptFootMeta');

	function cerrarModalPPT() {
		if (modalPPT) modalPPT.classList.add('hidden');
	}

	function actualizarPlantillaPPT(tplKey, optionEl) {
		if (!templatesList) return;
		templatesList.querySelectorAll('.ep-ppt-tpl-option').forEach(function(o) {
			o.classList.remove('selected');
			var radio = o.querySelector('input[type="radio"]');
			if (radio) radio.checked = false;
		});

		if (optionEl) {
			optionEl.classList.add('selected');
			var radioSel = optionEl.querySelector('input[type="radio"]');
			if (radioSel) radioSel.checked = true;
		}

		// Cambiar tag de la diapositiva
		if (slidePreviewTag) {
			slidePreviewTag.textContent = nombresPlantillas[tplKey] || 'ACTIVIDAD DE CAMPO';
		}

		// Mostrar la vista interna del slide correspondiente a esa plantilla
		if (slideDynamicContent) {
			slideDynamicContent.querySelectorAll('.ep-slide-tpl-view').forEach(function(v) {
				v.classList.add('hidden');
			});
			var vistaActiva = slideDynamicContent.querySelector('.ep-slide-view-' + tplKey);
			if (vistaActiva) {
				vistaActiva.classList.remove('hidden');
			}
		}

		actualizarSlidePreview();
	}

	function actualizarSlidePreview() {
		var userText = 'Todos los usuarios';
		if (selectPptUsuario && slidePreviewPromotor) {
			var val = selectPptUsuario.value;
			userText = (val === 'all') ? 'Todos los usuarios (Consolidado)' : val;
			slidePreviewPromotor.textContent = 'Promotor: ' + userText;
		}
		var fechaText = '24 Oct 2024';
		if (selectPptFecha && slidePreviewFecha) {
			var fVal = selectPptFecha.value;
			if (fVal) {
				var partesF = fVal.split('-');
				var meses = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
				var mesNom = meses[parseInt(partesF[1], 10) - 1] || 'OCT';
				fechaText = (partesF[2] || '24') + ' ' + mesNom + ' ' + (partesF[0] || '2024');
				slidePreviewFecha.textContent = fechaText;
			}
		}

		// Sincronizar pastilla de metadatos en el footer del modal
		if (pptFootMeta) {
			var radSel = templatesList ? templatesList.querySelector('input[name="epPptTemplate"]:checked') : null;
			var tpl = radSel ? radSel.value : 'activaciones';
			var tplNom = nombresPlantillas[tpl] || 'Activaciones de Campo';
			var slideTipo = (tpl === 'consolidado') ? 'Multi-Slide Pack' : '1 Diapositiva';
			pptFootMeta.textContent = slideTipo + ' · ' + tplNom + ' · ' + (userText.length > 25 ? userText.substring(0, 22) + '...' : userText) + ' · ' + fechaText;
		}
	}

	if (btnAbrirExportadorPPT) {
		btnAbrirExportadorPPT.addEventListener('click', function() {
			abrirModalPPT();
		});
	}

	if (modalPptBackdrop) modalPptBackdrop.addEventListener('click', cerrarModalPPT);
	if (modalPptCerrar) modalPptCerrar.addEventListener('click', cerrarModalPPT);
	if (modalPptCancelar) modalPptCancelar.addEventListener('click', cerrarModalPPT);
	document.addEventListener('keydown', function(ev) {
		if (ev.key === 'Escape' && modalPPT && !modalPPT.classList.contains('hidden')) {
			cerrarModalPPT();
		}
	});

	// Cambio de usuario en el modal
	if (selectPptUsuario) {
		selectPptUsuario.addEventListener('change', actualizarSlidePreview);
	}

	// Cambio de fecha en el modal
	if (selectPptFecha) {
		selectPptFecha.addEventListener('change', function() {
			var val = selectPptFecha.value;
			document.querySelectorAll('.ep-ppt-quick-date').forEach(function(b) {
				if (b.dataset.fecha === val) {
					b.classList.add('active');
				} else {
					b.classList.remove('active');
				}
			});
			actualizarSlidePreview();
		});
	}

	// Botones rápidos de fecha en modal
	document.querySelectorAll('.ep-ppt-quick-date').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var f = btn.dataset.fecha;
			if (f && selectPptFecha) {
				selectPptFecha.value = f;
				document.querySelectorAll('.ep-ppt-quick-date').forEach(function(b) { b.classList.remove('active'); });
				btn.classList.add('active');
				actualizarSlidePreview();
			}
		});
	});

	// Click en las tarjetas de plantillas PPTX
	if (templatesList) {
		templatesList.addEventListener('click', function(ev) {
			var opt = ev.target.closest('.ep-ppt-tpl-option');
			if (!opt) return;
			var tpl = opt.dataset.template;
			actualizarPlantillaPPT(tpl, opt);
		});
		// Navegación con teclado Enter/Espacio
		templatesList.addEventListener('keydown', function(ev) {
			if (ev.key === 'Enter' || ev.key === ' ') {
				var opt = ev.target.closest('.ep-ppt-tpl-option');
				if (opt) {
					ev.preventDefault();
					var tpl = opt.dataset.template;
					actualizarPlantillaPPT(tpl, opt);
				}
			}
		});
	}

	// Click en botón contextual "PPT Diario" desde la cabecera de grupo de usuario
	document.addEventListener('click', function(ev) {
		var btnUserPpt = ev.target.closest('.ep-btn-user-ppt');
		if (!btnUserPpt) return;
		ev.stopPropagation(); // No alternar el acordeón de apertura
		var promotor = btnUserPpt.dataset.promotor;
		abrirModalPPT({ promotor: promotor, fecha: '2024-10-24' });
	});

	// Click en botón contextual "Slide" desde cada registro individual
	document.addEventListener('click', function(ev) {
		var btnRecPpt = ev.target.closest('.ep-btn-record-ppt');
		if (!btnRecPpt) return;
		ev.stopPropagation(); // No alternar el acordeón de apertura
		var tpl = btnRecPpt.dataset.tipo || 'activaciones';
		var promotor = btnRecPpt.dataset.promotor || '';
		var fecha = btnRecPpt.dataset.fecha || '2024-10-24';
		var tienda = btnRecPpt.dataset.tienda || 'Punto de Venta';
		abrirModalPPT({ tipo: tpl, promotor: promotor, fecha: fecha, tienda: tienda });
	});

	// Descarga del archivo PPTX (real para Activaciones)
	if (btnEjecutarDescargaPPT) {
		btnEjecutarDescargaPPT.addEventListener('click', function() {
			var radSel = templatesList ? templatesList.querySelector('input[name="epPptTemplate"]:checked') : null;
			var tpl = radSel ? radSel.value : 'activaciones';
			var user = selectPptUsuario ? selectPptUsuario.value : 'all';
			var fecha = (selectPptFecha && selectPptFecha.value) ? selectPptFecha.value : new Date().toISOString().slice(0, 7);

			var userClean = (user === 'all') ? 'Consolidado' : user.replace(/\s+/g, '_');
			var fileName = 'Reporte_Epson_' + (tpl.toUpperCase()) + '_' + userClean + '_' + fecha.replace(/-/g, '') + '.pptx';

			if (btnEjecutarDescargaPPT) btnEjecutarDescargaPPT.disabled = true;
			if (btnDescargaPptTexto) btnDescargaPptTexto.textContent = 'Compilando diapositivas...';
			if (pptDescargaStatus) {
				pptDescargaStatus.classList.remove('hidden');
				if (pptStatusTitulo) pptStatusTitulo.textContent = 'Generando archivo PowerPoint (.pptx)...';
				if (pptStatusSub) pptStatusSub.textContent = 'Aplicando la plantilla oficial de ' + (nombresPlantillas[tpl] || tpl) + ' con slots fotográficos y métricas.';
			}

			function terminar(ok, titulo, sub) {
				if (btnDescargaPptTexto) btnDescargaPptTexto.textContent = ok ? 'Descargar Nuevamente (.pptx)' : 'Descargar Presentación (.pptx)';
				if (btnEjecutarDescargaPPT) btnEjecutarDescargaPPT.disabled = false;
				if (pptStatusTitulo) pptStatusTitulo.innerHTML = titulo;
				if (pptStatusSub) pptStatusSub.textContent = sub;
			}

			// Por ahora solo Activaciones genera el archivo real; los demás formatos se habilitan uno a uno.
			if (tpl !== 'activaciones') {
				terminar(false, 'Formato aún no disponible', 'Por ahora solo se puede descargar la presentación de Activaciones.');
				return;
			}

			var url = 'getters/exportar_ppt.php?tipo=activaciones&mes=' + encodeURIComponent(fecha.slice(0, 7)) + '&usuario=' + encodeURIComponent(user);
			fetch(url)
				.then(function (res) {
					var tipoRespuesta = res.headers.get('Content-Type') || '';
					if (tipoRespuesta.indexOf('json') !== -1) {
						return res.json().then(function (d) { throw new Error(d.error || 'No se pudo generar la presentación.'); });
					}
					// Solo se guarda si de verdad es un PowerPoint (un 404/500 del servidor llega como HTML).
					if (!res.ok || tipoRespuesta.indexOf('presentationml') === -1) {
						throw new Error('El servidor no pudo generar la presentación (código ' + res.status + '). Avisa al equipo técnico.');
					}
					return res.blob();
				})
				.then(function (blob) {
					var enlace = document.createElement('a');
					enlace.href = URL.createObjectURL(blob);
					enlace.download = fileName;
					document.body.appendChild(enlace);
					enlace.click();
					enlace.remove();
					terminar(true, '&#10003; ¡Presentación lista: <strong>' + fileName + '</strong>!', 'La descarga comenzó. Revisa tu carpeta de descargas.');
				})
				.catch(function (err) {
					terminar(false, 'No se pudo generar', err.message || 'Intenta de nuevo.');
				});
		});
	}

	// Inicializar en carga
	if (document.querySelector('.ep-registros-main')) {
		aplicarFiltrosYPaginar(true);
	}
});

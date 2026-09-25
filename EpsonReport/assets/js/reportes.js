// Reportes mensuales: Asistente con selección de actividades y espacio de trabajo de Activaciones (2 columnas, filtros, tabla dual y previsualización de la 1ra diapositiva PPTX).
(function () {
	var root = document.getElementById('epRp');
	if (!root) return;

	// Modal y estructura principal
	var modal = document.getElementById('epRpModal');
	var dialog = modal ? modal.querySelector('.ep-modal-ppt-dialog') : null;
	var pasoAct = document.getElementById('epRpPasoActividades');
	var workspaceAct = document.getElementById('epActWorkspace');
	var pasoFinal = document.getElementById('epRpFinal');
	var finalRango = document.getElementById('epRpFinalRango');
	var finalTotal = document.getElementById('epRpFinalTotal');
	var inpComentarios = document.getElementById('epActComentarios');
	var sigTxt = document.getElementById('epRpSigTxt');
	var vistaPrevia = 2; // workspace al que vuelve "Atrás" desde el paso final

	// Controles del pie
	var btnAtras = document.getElementById('epRpAtras');
	var btnSig = document.getElementById('epRpSiguiente');
	var btnGuardarAct = document.getElementById('epActBtnGuardar');
	var btnCancelarModal = document.getElementById('epRpCancelarModal');
	var footInfo = document.getElementById('epRpFootInfo');
	var btnCerrar = document.getElementById('epRpCerrar');
	var fondo = document.getElementById('epRpFondo');

	// Vista 1: Selector de actividades
	var selTipo = document.getElementById('epRpTipo');
	var inpBuscar = document.getElementById('epRpBuscarActividad');
	var btnLimpiarBuscar = document.getElementById('epRpBuscarLimpiar');
	var gridAct = document.getElementById('epRpGridActividades');
	var actVacio = document.getElementById('epRpActVacio');
	var actCount = document.getElementById('epRpActCount');
	var inpMes = document.getElementById('epRpMes');
	var inpTitulo = document.getElementById('epRpTituloTxt');

	// Vista 2: Mecánica de Activaciones
	var actTituloLabel = document.getElementById('epActTituloLabel');

	// Dropzone Calendario
	var drop = document.getElementById('epActDrop');
	var inpCal = document.getElementById('epActCalendario');
	var imgCal = document.getElementById('epActCalPrev');
	var dropVacio = document.getElementById('epActDropVacio');
	var btnQuitarFoto = document.getElementById('epActQuitarFoto');

	// KPIs y Seleccionados
	var inpProg = document.getElementById('epActProgramados');
	var valEjec = document.getElementById('epActEjecutadosVal');
	var badgePct = document.getElementById('epActPorcentajeBadge');
	var selBadge = document.getElementById('epActSelBadge');
	var btnLimpiarSel = document.getElementById('epActLimpiarSel');
	var selLista = document.getElementById('epActSelLista');

	// Filtros de la columna derecha
	var pillFechaUnica = document.getElementById('epActPillUnica');
	var pillFechaRango = document.getElementById('epActPillRango');
	var btnLimpiarFecha = document.getElementById('epActLimpiarFechaBtn');
	var inpFechaDesde = document.getElementById('epActFechaDesde');
	var fechaDesdeBox = document.getElementById('epActFechaDesdeBox');
	var inpFechaHasta = document.getElementById('epActFechaHasta');
	var fechaSep = document.getElementById('epActFechaSep');
	var fechaHastaBox = document.getElementById('epActFechaHastaBox');

	var promotorCombo = document.getElementById('epActPromotorCombo');
	var promotorDisplay = document.getElementById('epActPromotorDisplay');
	var promotorTexto = document.getElementById('epActPromotorTexto');
	var promotorMenu = document.getElementById('epActPromotorMenu');
	var promotorSearch = document.getElementById('epActPromotorSearch');
	var promotorList = document.getElementById('epActPromotorList');

	var canalSelect = document.getElementById('epActCanalSelect');

	// Tabla dual
	var tablaRows = document.getElementById('epActRegistrosLista');

	// Modal Lightbox
	var lightbox = document.getElementById('epActLightbox');
	var lbFondo = document.getElementById('epActLightboxFondo');
	var lbCerrar = document.getElementById('epLbCerrar');
	var lbCerrarBtn = document.getElementById('epLbCerrarBtn');
	var lbCodigo = document.getElementById('epLbCodigo');
	var lbEstado = document.getElementById('epLbEstado');
	var lbDesc = document.getElementById('epLbDesc');
	var lbBody = document.getElementById('epLbBody');
	var lbPdv = document.getElementById('epLbPdv');
	var lbFecha = document.getElementById('epLbFecha');

	// Vista 3: Mecánica de Capacitaciones
	var workspaceCap = document.getElementById('epCapWorkspace');
	var capTituloLabel = document.getElementById('epCapTituloLabel');

	// Seleccionados Capacitaciones
	var selCapBadge = document.getElementById('epCapSelBadge');
	var btnLimpiarCapSel = document.getElementById('epCapLimpiarSel');
	var selCapLista = document.getElementById('epCapSelLista');

	// Filtros Capacitaciones
	var pillCapPillUnica = document.getElementById('epCapPillUnica');
	var pillCapPillRango = document.getElementById('epCapPillRango');
	var btnLimpiarCapFecha = document.getElementById('epCapLimpiarFechaBtn');
	var inpCapFechaDesde = document.getElementById('epCapFechaDesde');
	var capFechaDesdeBox = document.getElementById('epCapFechaDesdeBox');
	var inpCapFechaHasta = document.getElementById('epCapFechaHasta');
	var capFechaSep = document.getElementById('epCapFechaSep');
	var capFechaHastaBox = document.getElementById('epCapFechaHastaBox');

	var promotorCapCombo = document.getElementById('epCapPromotorCombo');
	var promotorCapDisplay = document.getElementById('epCapPromotorDisplay');
	var promotorCapTexto = document.getElementById('epCapPromotorTexto');
	var promotorCapMenu = document.getElementById('epCapPromotorMenu');
	var promotorCapSearch = document.getElementById('epCapPromotorSearch');
	var promotorCapList = document.getElementById('epCapPromotorList');

	var canalCapSelect = document.getElementById('epCapCanalSelect');

	// Tabla dual Capacitaciones
	var tablaCapRows = document.getElementById('epCapRegistrosLista');

	// Estado general
	var vistaActual = 1;
	var calBlob = null;
	var registrosCargados = [];
	var seleccionadosMap = {}; // db_id -> registro
	var modoFecha = 'unica'; // 'unica' o 'rango'
	var promotorFiltro = 'todos';
	var canalFiltro = 'todos';
	var registroLightboxActual = null;

	// Estado Capacitaciones
	var registrosCapCargados = [];
	var seleccionadosCapMap = {}; // db_id -> registro
	var modoFechaCap = 'unica';
	var promotorCapFiltro = 'todos';
	var canalCapFiltro = 'todos';

	function formatearFechaTexto(f) {
		if (!f) return '';
		var s = String(f).trim().substring(0, 10);
		var parts = s.split('-');
		if (parts.length === 3) {
			var meses = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
			var mIdx = parseInt(parts[1], 10) - 1;
			var mesNom = (mIdx >= 0 && mIdx < 12) ? meses[mIdx] : parts[1];
			var dia = parts[2].length === 1 ? '0' + parts[2] : parts[2];
			return dia + ' – ' + mesNom + ' – ' + parts[0];
		}
		return f;
	}

	function formatearFechaCorta(f) {
		if (!f) return '';
		var s = String(f).trim().substring(0, 10);
		var parts = s.split('-');
		if (parts.length === 3) {
			var meses = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
			var mIdx = parseInt(parts[1], 10) - 1;
			var mesNom = (mIdx >= 0 && mIdx < 12) ? meses[mIdx] : parts[1];
			return parts[2] + ' ' + mesNom + ' ' + parts[0];
		}
		return f;
	}

	function formatearFechaSlash(iso) {
		if (!iso) return '';
		var partes = String(iso).substring(0, 10).split('-');
		return partes.length === 3 ? partes[2] + '/' + partes[1] + '/' + partes[0] : iso;
	}

	function aviso(icono, titulo, texto) {
		if (window.Swal) return Swal.fire({ icon: icono, title: titulo, html: texto || '', confirmButtonColor: '#513487', allowOutsideClick: false });
		alert(titulo + (texto ? ' ' + texto : ''));
		return Promise.resolve();
	}
	function esc(s) { return String(s || '').replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function normalizar(txt) { return (txt || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim(); }

	// ==================== NAVEGACIÓN DE VISTAS ====================
	function mostrarVista(n) {
		vistaActual = n;
		if (pasoFinal) pasoFinal.classList.toggle('hidden', n !== 4);
		if (sigTxt) sigTxt.textContent = n === 1 ? 'Siguiente' : 'Continuar';
		if (n === 1) {
			if (pasoAct) pasoAct.classList.remove('hidden');
			if (workspaceAct) workspaceAct.classList.add('hidden');
			if (workspaceCap) workspaceCap.classList.add('hidden');
			if (dialog) dialog.classList.remove('ep-rp-dialog-wide');

			if (btnAtras) btnAtras.classList.add('hidden');
			if (footInfo) footInfo.classList.add('hidden');
			if (btnSig) btnSig.classList.remove('hidden');
			if (btnGuardarAct) btnGuardarAct.classList.add('hidden');
		} else if (n === 2) {
			if (pasoAct) pasoAct.classList.add('hidden');
			if (workspaceAct) workspaceAct.classList.remove('hidden');
			if (workspaceCap) workspaceCap.classList.add('hidden');
			if (dialog) dialog.classList.add('ep-rp-dialog-wide');

			if (btnAtras) btnAtras.classList.remove('hidden');
			if (footInfo) footInfo.classList.remove('hidden');
			if (btnSig) btnSig.classList.remove('hidden');
			if (btnGuardarAct) btnGuardarAct.classList.add('hidden');

			var labelAct = (selTipo && selTipo.dataset.label) || 'ACTIVACIONES';
			if (actTituloLabel) actTituloLabel.textContent = labelAct.toUpperCase();

			cargarRegistrosActivaciones();
		} else if (n === 3) {
			if (pasoAct) pasoAct.classList.add('hidden');
			if (workspaceAct) workspaceAct.classList.add('hidden');
			if (workspaceCap) workspaceCap.classList.remove('hidden');
			if (dialog) dialog.classList.add('ep-rp-dialog-wide');

			if (btnAtras) btnAtras.classList.remove('hidden');
			if (footInfo) footInfo.classList.remove('hidden');
			if (btnSig) btnSig.classList.remove('hidden');
			if (btnGuardarAct) btnGuardarAct.classList.add('hidden');

			var tipoActual = (selTipo && selTipo.value) || 'capacitaciones';
			var labelCap = (selTipo && selTipo.dataset.label) || 'Capacitaciones';
			if (capTituloLabel) capTituloLabel.textContent = labelCap.toUpperCase();
			var subEl = document.getElementById('epCapTituloSub');
			if (subEl) subEl.textContent = 'Selecciona los registros de ' + labelCap.toLowerCase() + ' que formarán parte del reporte mensual.';
			var dotEl = document.getElementById('epCapDot');
			var coloresTipo = {
				'capacitaciones': '#164194',
				'epson-day': '#006699',
				'evento-ferias': '#0D9488',
				'exhibiciones': '#D97706',
				'colocacion-pop': '#7C3AED'
			};
			if (dotEl) dotEl.style.background = coloresTipo[tipoActual] || '#164194';

			cargarRegistrosCapacitaciones();
		} else if (n === 4) {
			if (pasoAct) pasoAct.classList.add('hidden');
			if (workspaceAct) workspaceAct.classList.add('hidden');
			if (workspaceCap) workspaceCap.classList.add('hidden');
			if (dialog) dialog.classList.remove('ep-rp-dialog-wide');

			if (btnAtras) btnAtras.classList.remove('hidden');
			if (footInfo) footInfo.classList.add('hidden');
			if (btnSig) btnSig.classList.add('hidden');
			if (btnGuardarAct) btnGuardarAct.classList.remove('hidden');
		}
	}

	function abrir() {
		quitarCalendario();
		seleccionadosMap = {};
		seleccionadosCapMap = {};
		if (inpProg) inpProg.value = '';
		if (inpTitulo) inpTitulo.value = '';
		if (inpMes) inpMes.value = '';
		if (inpComentarios) inpComentarios.value = '';
		if (inpBuscar) inpBuscar.value = '';
		if (inpFechaDesde) inpFechaDesde.value = '';
		if (inpFechaHasta) inpFechaHasta.value = '';
		if (inpCapFechaDesde) inpCapFechaDesde.value = '';
		if (inpCapFechaHasta) inpCapFechaHasta.value = '';
		actualizarVisibilidadLimpiarFecha();
		actualizarVisibilidadLimpiarFechaCap();
		filtrarActividades('');
		actualizarKpisYSeleccionados();
		actualizarSeleccionadosCap();
		mostrarVista(1);
		if (modal) modal.classList.remove('hidden');
	}

	function cerrar() {
		if (modal) modal.classList.add('hidden');
	}

	if (document.getElementById('epRpNuevo')) document.getElementById('epRpNuevo').addEventListener('click', abrir);
	if (btnCerrar) btnCerrar.addEventListener('click', cerrar);
	if (btnCancelarModal) btnCancelarModal.addEventListener('click', cerrar);

	if (btnAtras) {
		btnAtras.addEventListener('click', function () {
			mostrarVista(vistaActual === 4 ? vistaPrevia : 1);
		});
	}

	if (btnSig) {
		btnSig.addEventListener('click', function () {
			if (vistaActual === 2 || vistaActual === 3) {
				irAlPasoFinal();
				return;
			}
			if (!selTipo || !selTipo.value) {
				aviso('warning', 'Falta actividad', 'Elige una actividad para el reporte.');
				return;
			}
			var tipoLogica = selTipo.value;
			if (tipoLogica === 'activaciones') {
				mostrarVista(2);
			} else {
				mostrarVista(3);
			}
		});
	}

	// ==================== BUSCADOR Y SELECCIÓN DE ACTIVIDAD (VISTA 1) ====================
	function filtrarActividades(q) {
		if (!gridAct) return;
		var term = normalizar(q);
		var cards = Array.prototype.slice.call(gridAct.querySelectorAll('.ep-rp-act-card'));
		var visibles = 0;
		cards.forEach(function (c) {
			var label = normalizar(c.dataset.label);
			var sub = normalizar(c.dataset.sub);
			var match = term === '' || label.indexOf(term) !== -1 || sub.indexOf(term) !== -1;
			c.classList.toggle('hidden', !match);
			if (match) visibles++;
		});
		if (actVacio) actVacio.classList.toggle('hidden', visibles > 0);
		if (btnLimpiarBuscar) btnLimpiarBuscar.classList.toggle('hidden', term === '');
		if (actCount) actCount.textContent = term === '' ? (cards.length + ' activas') : (visibles + ' de ' + cards.length);
	}

	if (gridAct) {
		gridAct.addEventListener('click', function (ev) {
			var card = ev.target.closest('.ep-rp-act-card');
			if (!card || card.disabled) return;
			gridAct.querySelectorAll('.ep-rp-act-card').forEach(function (c) {
				c.classList.remove('selected');
				c.setAttribute('aria-checked', 'false');
			});
			card.classList.add('selected');
			card.setAttribute('aria-checked', 'true');
			if (selTipo) {
				selTipo.value = card.dataset.tipo;
				selTipo.dataset.label = card.dataset.label;
			}
		});
	}

	if (inpBuscar) {
		inpBuscar.addEventListener('input', function () { filtrarActividades(this.value); });
	}
	if (btnLimpiarBuscar) {
		btnLimpiarBuscar.addEventListener('click', function () {
			if (inpBuscar) { inpBuscar.value = ''; inpBuscar.focus(); }
			filtrarActividades('');
		});
	}

	// ==================== DROPZONE DE CALENDARIO ====================
	function comprimir(archivo) {
		return new Promise(function (resolve) {
			var img = new Image();
			var url = URL.createObjectURL(archivo);
			img.onerror = function () { URL.revokeObjectURL(url); resolve(archivo); };
			img.onload = function () {
				URL.revokeObjectURL(url);
				var pasosC = [[1800, 0.85], [1500, 0.75], [1200, 0.65]];
				function probar(i) {
					var escala = Math.min(1, pasosC[i][0] / Math.max(img.width, img.height));
					var c = document.createElement('canvas');
					c.width = Math.round(img.width * escala);
					c.height = Math.round(img.height * escala);
					var ctx = c.getContext('2d');
					ctx.fillStyle = '#FFFFFF';
					ctx.fillRect(0, 0, c.width, c.height);
					ctx.drawImage(img, 0, 0, c.width, c.height);
					c.toBlob(function (b) {
						if (b && (b.size <= 700 * 1024 || i === pasosC.length - 1)) resolve(b && b.size < archivo.size ? b : archivo);
						else probar(i + 1);
					}, 'image/jpeg', pasosC[i][1]);
				}
				probar(0);
			};
			img.src = url;
		});
	}

	function mostrarCalendario(f) {
		if (!f || !/^image\/(jpeg|png)$/.test(f.type)) {
			aviso('warning', 'Imagen no válida', 'Sube una imagen en formato JPG o PNG.');
			return;
		}
		comprimir(f).then(function (b) {
			calBlob = b;
			if (imgCal) {
				imgCal.src = URL.createObjectURL(b);
				imgCal.classList.remove('hidden');
			}
			if (dropVacio) dropVacio.classList.add('hidden');
			if (btnQuitarFoto) btnQuitarFoto.classList.remove('hidden');
			if (drop) drop.classList.remove('ep-act-error');
		});
	}

	// Foto del calendario ampliada; un clic (o Esc) la cierra.
	var zoom = document.getElementById('epActZoom');
	function abrirZoomCalendario() {
		var img = document.getElementById('epActZoomImg');
		if (!zoom || !img || !imgCal || !imgCal.src) return;
		img.src = imgCal.src;
		zoom.classList.remove('hidden');
	}
	function cerrarZoomCalendario() { if (zoom) zoom.classList.add('hidden'); }
	if (zoom) zoom.addEventListener('click', cerrarZoomCalendario);
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && zoom && !zoom.classList.contains('hidden')) cerrarZoomCalendario();
	});

	function quitarCalendario() {
		calBlob = null;
		if (inpCal) inpCal.value = '';
		if (imgCal) {
			imgCal.classList.add('hidden');
			imgCal.removeAttribute('src');
		}
		if (dropVacio) dropVacio.classList.remove('hidden');
		if (btnQuitarFoto) btnQuitarFoto.classList.add('hidden');
	}

	if (inpCal) {
		inpCal.addEventListener('change', function () {
			if (inpCal.files && inpCal.files[0]) mostrarCalendario(inpCal.files[0]);
		});
	}
	if (btnQuitarFoto) btnQuitarFoto.addEventListener('click', function (e) {
		e.stopPropagation();
		quitarCalendario();
	});

	if (drop) {
		drop.addEventListener('click', function (e) {
			if (e.target === btnQuitarFoto) return;
			if (calBlob && e.target === imgCal) {
				abrirZoomCalendario();
				return;
			}
			if (inpCal) inpCal.click();
		});
		['dragenter', 'dragover'].forEach(function (ev) {
			drop.addEventListener(ev, function (e) { e.preventDefault(); e.stopPropagation(); drop.classList.add('drag-active'); });
		});
		['dragleave', 'drop'].forEach(function (ev) {
			drop.addEventListener(ev, function (e) { e.preventDefault(); e.stopPropagation(); drop.classList.remove('drag-active'); });
		});
		drop.addEventListener('drop', function (e) {
			var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
			if (f) mostrarCalendario(f);
		});
	}

	// ==================== KPIS, PROGRAMADOS Y SELECCIONADOS ====================
	function actualizarKpisYSeleccionados() {
		var ids = Object.keys(seleccionadosMap);
		var total = ids.length;

		// 1. Contador ejecutado
		if (valEjec) valEjec.textContent = total;
		if (selBadge) selBadge.textContent = total + (total === 1 ? ' ítem' : ' ítems');

		// 2. Porcentaje vs programados
		var prog = parseInt(inpProg ? inpProg.value : '0', 10);
		var pct = (!isNaN(prog) && prog > 0) ? Math.round((total / prog) * 100) : 0;
		if (badgePct) {
			badgePct.textContent = pct + '%';
			if (pct > 100) {
				badgePct.style.background = '#FEF2F2';
				badgePct.style.color = '#DC2626';
			} else if (pct === 100) {
				badgePct.style.background = '#DCFCE7';
				badgePct.style.color = '#15803D';
			} else {
				badgePct.style.background = '#EDE8F6';
				badgePct.style.color = '#513487';
			}
		}

		// 3. Renderizar chips de seleccionados en la columna izquierda
		if (selLista) {
			if (total === 0) {
				selLista.innerHTML = '<div class="ep-act-sel-vacio"><span>Marca los registros del lado derecho para sumarlos a este reporte.</span></div>';
			} else {
				var html = '';
				ids.forEach(function (idStr) {
					var item = seleccionadosMap[idStr];
					if (!item) return;
					var horaTxt = item.hora ? item.hora.substring(0, 5) : '';
					html += '<div class="ep-act-sel-item" data-id="' + item.id + '">'
						+ '<div class="ep-act-sel-item-left">'
						+ '<span class="ep-act-sel-item-dot"></span>'
						+ '<span class="ep-act-sel-item-title" title="' + esc(item.codigo) + ' • ' + esc(item.punto_venta) + '">'
						+ esc(item.codigo) + ' • ' + esc(item.punto_venta)
						+ '</span>'
						+ (horaTxt ? '<span class="ep-act-sel-item-time">' + esc(horaTxt) + '</span>' : '')
						+ '</div>'
						+ '<button type="button" class="ep-act-sel-item-del" data-id="' + item.id + '" title="Quitar de seleccionados">'
						+ '<svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>'
						+ '</button>'
						+ '</div>';
				});
				selLista.innerHTML = html;
			}
		}

		// 4. Sincronizar checkboxes visibles en la tabla dual
		if (tablaRows) {
			tablaRows.querySelectorAll('.ep-act-dual-row').forEach(function (fila) {
				var chk = fila.querySelector('input[type="checkbox"]');
				if (chk) {
					var id = Number(chk.value);
					var checked = !!seleccionadosMap[id];
					chk.checked = checked;
					fila.classList.toggle('checked', checked);
				}
			});
		}
	}

	// Cambio en el input de programados: recalcula el porcentaje y valida
	if (inpProg) {
		inpProg.addEventListener('input', function () {
			actualizarKpisYSeleccionados();
		});
	}

	// Botón limpiar todos los seleccionados
	if (btnLimpiarSel) {
		btnLimpiarSel.addEventListener('click', function () {
			seleccionadosMap = {};
			actualizarKpisYSeleccionados();
		});
	}

	// Quitar chip individual desde la lista de seleccionados
	if (selLista) {
		selLista.addEventListener('click', function (e) {
			var delBtn = e.target.closest('.ep-act-sel-item-del');
			if (!delBtn) return;
			var id = Number(delBtn.dataset.id);
			if (seleccionadosMap[id]) {
				delete seleccionadosMap[id];
				actualizarKpisYSeleccionados();
			}
		});
	}

	// ==================== FILTROS (FECHA, PROMOTOR, CANAL) ====================
	function actualizarVisibilidadLimpiarFecha() {
		var hasVal = (inpFechaDesde && inpFechaDesde.value) || (inpFechaHasta && inpFechaHasta.value);
		if (btnLimpiarFecha) {
			btnLimpiarFecha.classList.toggle('hidden', !hasVal);
		}
	}

	function setModoFecha(nuevoModo) {
		modoFecha = nuevoModo;
		if (modoFecha === 'rango') {
			if (pillFechaRango) pillFechaRango.classList.add('active');
			if (pillFechaUnica) pillFechaUnica.classList.remove('active');
			if (fechaHastaBox) fechaHastaBox.classList.remove('hidden');
			if (fechaSep) fechaSep.classList.remove('hidden');
		} else {
			if (pillFechaUnica) pillFechaUnica.classList.add('active');
			if (pillFechaRango) pillFechaRango.classList.remove('active');
			if (fechaHastaBox) fechaHastaBox.classList.add('hidden');
			if (fechaSep) fechaSep.classList.add('hidden');
			if (inpFechaHasta) inpFechaHasta.value = '';
		}
		actualizarVisibilidadLimpiarFecha();
		aplicarFiltrosYRenderizar();
	}

	if (pillFechaUnica) {
		pillFechaUnica.addEventListener('click', function () { setModoFecha('unica'); });
	}
	if (pillFechaRango) {
		pillFechaRango.addEventListener('click', function () { setModoFecha('rango'); });
	}

	if (btnLimpiarFecha) {
		btnLimpiarFecha.addEventListener('click', function () {
			if (inpFechaDesde) inpFechaDesde.value = '';
			if (inpFechaHasta) inpFechaHasta.value = '';
			actualizarVisibilidadLimpiarFecha();
			aplicarFiltrosYRenderizar();
		});
	}

	// Apertura automática del calendario al hacer clic en cualquier parte del campo de fecha
	function abrirPickerFecha(inp) {
		if (!inp) return;
		try {
			if (typeof inp.showPicker === 'function') {
				inp.showPicker();
			} else {
				inp.focus();
			}
		} catch (e) {
			inp.focus();
		}
	}

	if (inpFechaDesde) {
		inpFechaDesde.addEventListener('click', function () {
			abrirPickerFecha(this);
		});
	}
	if (fechaDesdeBox) {
		fechaDesdeBox.addEventListener('click', function (e) {
			if (e.target !== inpFechaDesde) {
				abrirPickerFecha(inpFechaDesde);
			}
		});
	}

	if (inpFechaHasta) {
		inpFechaHasta.addEventListener('click', function () {
			abrirPickerFecha(this);
		});
	}
	if (fechaHastaBox) {
		fechaHastaBox.addEventListener('click', function (e) {
			if (e.target !== inpFechaHasta) {
				abrirPickerFecha(inpFechaHasta);
			}
		});
	}

	['input', 'change'].forEach(function (evType) {
		if (inpFechaDesde) {
			inpFechaDesde.addEventListener(evType, function () {
				actualizarVisibilidadLimpiarFecha();
				aplicarFiltrosYRenderizar();
			});
		}
		if (inpFechaHasta) {
			inpFechaHasta.addEventListener(evType, function () {
				actualizarVisibilidadLimpiarFecha();
				aplicarFiltrosYRenderizar();
			});
		}
	});

	// Combobox Promotor
	if (promotorDisplay) {
		promotorDisplay.addEventListener('click', function (e) {
			e.stopPropagation();
			if (promotorMenu) promotorMenu.classList.toggle('hidden');
			if (promotorSearch && !promotorMenu.classList.contains('hidden')) {
				promotorSearch.value = '';
				if (promotorList) {
					promotorList.querySelectorAll('.ep-act-combo-item').forEach(function (it) { it.classList.remove('hidden'); });
				}
				promotorSearch.focus();
			}
		});
	}

	if (promotorSearch) {
		promotorSearch.addEventListener('input', function () {
			var q = normalizar(this.value);
			if (!promotorList) return;
			promotorList.querySelectorAll('.ep-act-combo-item').forEach(function (it) {
				var txt = normalizar(it.textContent);
				it.classList.toggle('hidden', q !== '' && txt.indexOf(q) === -1);
			});
		});
	}

	if (promotorList) {
		promotorList.addEventListener('click', function (e) {
			var item = e.target.closest('.ep-act-combo-item');
			if (!item) return;
			promotorFiltro = item.dataset.value;
			if (promotorTexto) promotorTexto.textContent = item.textContent;
			promotorList.querySelectorAll('.ep-act-combo-item').forEach(function (it) { it.classList.remove('selected'); });
			item.classList.add('selected');
			if (promotorMenu) promotorMenu.classList.add('hidden');
			aplicarFiltrosYRenderizar();
		});
	}

	// Cerrar menú del combobox al hacer clic fuera
	document.addEventListener('click', function (e) {
		if (promotorCombo && !promotorCombo.contains(e.target)) {
			if (promotorMenu) promotorMenu.classList.add('hidden');
		}
	});

	if (canalSelect) {
		canalSelect.addEventListener('change', function () {
			canalFiltro = this.value;
			aplicarFiltrosYRenderizar();
		});
	}

	// ==================== CARGA Y FILTRADO DE REGISTROS ====================
	function cargarRegistrosActivaciones() {
		if (tablaRows) {
			tablaRows.innerHTML = '<div class="ep-act-loading-state">'
				+ '<svg style="width:24px;height:24px;animation:spin 1s linear infinite;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="3" stroke-dasharray="32" stroke-linecap="round"></circle></svg>'
				+ '<span>Cargando registros de activaciones...</span>'
				+ '</div>';
		}

		var tipo = selTipo ? selTipo.value : 'activaciones';
		return fetch('getters/reportes_registros.php?tipo=' + encodeURIComponent(tipo))
			.then(function (r) { return r.json(); })
			.then(function (d) {
				if (!d.success) {
					if (tablaRows) tablaRows.innerHTML = '<div class="ep-act-loading-state"><span>No se pudieron cargar los registros.</span></div>';
					aviso('error', 'Error', esc(d.error || 'Intenta de nuevo.'));
					return;
				}
				registrosCargados = d.registros || [];

				// Poblar lista de promotores
				if (promotorList && d.promotores) {
					var htmlProm = '<button type="button" class="ep-act-combo-item' + (promotorFiltro === 'todos' ? ' selected' : '') + '" data-value="todos">Todos los promotores</button>';
					d.promotores.forEach(function (p) {
						if (!p) return;
						htmlProm += '<button type="button" class="ep-act-combo-item' + (promotorFiltro === p ? ' selected' : '') + '" data-value="' + esc(p) + '">' + esc(p) + '</button>';
					});
					promotorList.innerHTML = htmlProm;
				}

				// Poblar selector de canales
				if (canalSelect && d.canales) {
					var htmlCan = '<option value="todos">Todos</option>';
					d.canales.forEach(function (c) {
						if (!c) return;
						htmlCan += '<option value="' + esc(c) + '">' + esc(c) + '</option>';
					});
					canalSelect.innerHTML = htmlCan;
				}

				aplicarFiltrosYRenderizar();
			})
			.catch(function () {
				if (tablaRows) tablaRows.innerHTML = '<div class="ep-act-loading-state"><span>Error de conexión al cargar registros.</span></div>';
			});
	}

	function aplicarFiltrosYRenderizar() {
		if (!tablaRows) return;

		var desdeVal = (inpFechaDesde && inpFechaDesde.value) ? inpFechaDesde.value.trim() : '';
		var hastaVal = (inpFechaHasta && inpFechaHasta.value) ? inpFechaHasta.value.trim() : '';

		var filtrados = registrosCargados.filter(function (r) {
			var fIso = (r.fecha || '').substring(0, 10);

			// Filtro de fecha
			if (modoFecha === 'rango') {
				if (desdeVal && hastaVal) {
					var minD = desdeVal <= hastaVal ? desdeVal : hastaVal;
					var maxD = desdeVal <= hastaVal ? hastaVal : desdeVal;
					if (fIso < minD || fIso > maxD) return false;
				} else if (desdeVal) {
					if (fIso < desdeVal) return false;
				} else if (hastaVal) {
					if (fIso > hastaVal) return false;
				}
			} else {
				if (desdeVal && fIso !== desdeVal) return false;
			}

			// Filtro de promotor
			if (promotorFiltro !== 'todos') {
				if (normalizar(r.promotor) !== normalizar(promotorFiltro)) return false;
			}

			// Filtro de canal
			if (canalFiltro !== 'todos') {
				if ((r.canal || '').toLowerCase() !== canalFiltro.toLowerCase()) return false;
			}

			return true;
		});

		if (filtrados.length === 0) {
			tablaRows.innerHTML = '<div class="ep-act-loading-state"><span>No se encontraron registros con los filtros seleccionados.</span></div>';
			return;
		}

		var html = '';
		filtrados.forEach(function (r) {
			var isChecked = !!seleccionadosMap[r.id];
			var statusClass = (r.estado || 'Activo').toLowerCase() === 'activo' ? 'activo' : 'pendiente';

			var cob = r.cobertura || { pct: 0 };
			var emb = r.embudo || { visitaron: 0, interactuaron: 0, compraron: 0, tasa_interaccion_pct: 0, tasa_conversion_pct: 0 };
			var topModel = (r.modelos && r.modelos.length) ? r.modelos[0].modelo : (r.tipo_actividad || 'Activación');
			var fechaCorta = formatearFechaCorta(r.fecha);

			var vis = Math.max(1, parseInt(emb.visitaron || 0, 10));
			var v1 = parseInt(emb.visitaron || 0, 10);
			var v2 = parseInt(emb.interactuaron || 0, 10);
			var v3 = parseInt(emb.compraron || 0, 10);
			var h1 = 20;
			var h2 = Math.max(2, Math.round(20 * (v2 / vis)));
			var h3 = Math.max(2, Math.round(20 * (v3 / vis)));

			html += '<div class="ep-act-dual-row' + (isChecked ? ' checked' : '') + '" data-id="' + r.id + '">'
				// Columna 1: Registro
				+ '<div class="ep-act-reg-card">'
				+ '<input type="checkbox" value="' + r.id + '"' + (isChecked ? ' checked' : '') + ' aria-label="Seleccionar registro ' + esc(r.codigo) + '">'
				+ '<div class="ep-act-reg-info">'
				+ '<div class="ep-act-reg-top">'
				+ '<strong class="ep-act-reg-code">' + esc(r.codigo) + '</strong>'
				+ '<span class="ep-act-badge-status ' + statusClass + '">' + esc(r.estado || 'Activo') + '</span>'
				+ '</div>'
				+ '<p class="ep-act-reg-desc">' + esc(r.tipo_actividad || 'Activación') + '</p>'
				+ '<p class="ep-act-reg-pdv">PDV: ' + esc(r.punto_venta) + (r.ciudad ? ' • ' + esc(r.ciudad) : '') + '</p>'
				+ '</div>'
				+ '</div>'

				// Columna 2: Previsualización de la PRIMERA DIAPOSITIVA (Miniatura del PPTX)
				+ '<div class="ep-act-prev-card preview-trigger" data-id="' + r.id + '" role="button" tabindex="0" title="Ver primera diapositiva (Estadísticas y Métricas)">'
				+ '<div class="ep-act-mini-slide-canvas">'
				+ '<div class="ep-act-mini-pro-col">'
				+ '<span class="ep-act-mini-pro-name" title="' + esc(r.promotor) + '">' + esc(r.promotor || 'PROMOTOR') + '</span>'
				+ '<span class="ep-act-mini-pro-pdv" title="' + esc(r.punto_venta) + '">' + esc(r.punto_venta) + '</span>'
				+ '<span class="ep-act-mini-pro-date">' + esc(fechaCorta) + '</span>'
				+ '<span class="ep-act-mini-pro-city">' + esc(r.ciudad || 'ECUADOR') + '</span>'
				+ '</div>'
				+ '<div class="ep-act-mini-body-col">'
				+ '<div class="ep-act-mini-title-row">'
				+ '<span class="ep-act-mini-title">ESTADISTICAS ACTIVACIONES</span>'
				+ '<span class="ep-act-mini-badge">Slide 1</span>'
				+ '</div>'
				+ '<div class="ep-act-mini-kpis-row">'
				+ '<div class="ep-act-mini-kpi"><div class="ep-act-mini-kpi-tag">COB</div><div class="ep-act-mini-kpi-val">' + (cob.pct || 0) + '%</div></div>'
				+ '<div class="ep-act-mini-kpi"><div class="ep-act-mini-kpi-tag">INT</div><div class="ep-act-mini-kpi-val">' + (emb.tasa_interaccion_pct || 0) + '%</div></div>'
				+ '<div class="ep-act-mini-kpi"><div class="ep-act-mini-kpi-tag">VEN</div><div class="ep-act-mini-kpi-val">' + (emb.tasa_conversion_pct || 0) + '%</div></div>'
				+ '</div>'
				+ '<div class="ep-act-mini-bottom-row">'
				+ '<div class="ep-act-mini-bars">'
				+ '<div class="ep-act-mini-bar-item"><span class="ep-act-mini-bar-lbl" title="' + esc(topModel) + '">' + esc(topModel) + '</span><div class="ep-act-mini-bar-track"><div class="ep-act-mini-bar-fill" style="width:85%;"></div></div></div>'
				+ '</div>'
				+ '<div class="ep-act-mini-funnel">'
				+ '<div class="ep-act-mini-fun-bar" style="height:' + h1 + 'px;background:#164194;"></div>'
				+ '<div class="ep-act-mini-fun-bar" style="height:' + h2 + 'px;background:#3062A9;"></div>'
				+ '<div class="ep-act-mini-fun-bar" style="height:' + h3 + 'px;background:#0284C7;"></div>'
				+ '</div>'
				+ '</div>'
				+ '</div>'
				+ '</div>'
				+ '<div class="ep-act-prev-overlay">'
				+ '<span class="ep-act-prev-pill">'
				+ '<svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>'
				+ 'Ampliar diapositiva'
				+ '</span>'
				+ '</div>'
				+ '</div>'

				+ '</div>';
		});

		tablaRows.innerHTML = html;
	}

	// Evento de selección de checkboxes y clic en previsualización de diapositiva
	if (tablaRows) {
		tablaRows.addEventListener('change', function (e) {
			var chk = e.target.closest('input[type="checkbox"]');
			if (!chk) return;
			var id = Number(chk.value);
			var reg = registrosCargados.find(function (it) { return it.id === id; });
			if (!reg) return;

			if (chk.checked) {
				// Regla del usuario: Si en programado puse 5, max podré poner 5
				var maxProg = parseInt(inpProg ? inpProg.value : '0', 10);
				var cantActual = Object.keys(seleccionadosMap).length;
				if (!isNaN(maxProg) && maxProg > 0 && cantActual >= maxProg) {
					chk.checked = false;
					aviso('info', 'Límite alcanzado', 'Has alcanzado el número máximo de registros programados (' + maxProg + '). Si deseas seleccionar más, amplía el valor en "Programados".');
					return;
				}
				seleccionadosMap[id] = reg;
			} else {
				delete seleccionadosMap[id];
			}
			actualizarKpisYSeleccionados();
		});

		tablaRows.addEventListener('click', function (e) {
			var prevCard = e.target.closest('.ep-act-prev-card');
			if (!prevCard) return;
			var id = Number(prevCard.dataset.id);
			var reg = registrosCargados.find(function (it) { return it.id === id; });
			if (reg) {
				abrirLightbox(reg);
			}
		});

		tablaRows.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ') {
				var prevCard = e.target.closest('.ep-act-prev-card');
				if (prevCard) {
					e.preventDefault();
					prevCard.click();
				}
			}
		});
	}

	// ==================== CAPACITACIONES: SELECCIONADOS ====================
	function actualizarSeleccionadosCap() {
		var ids = Object.keys(seleccionadosCapMap);
		var total = ids.length;

		if (selCapBadge) selCapBadge.textContent = total + (total === 1 ? ' ítem' : ' ítems');

		if (selCapLista) {
			if (total === 0) {
				selCapLista.innerHTML = '<div class="ep-act-sel-vacio" id="epCapSelVacio"><span>Marca los registros del lado derecho para sumarlos a este reporte.</span></div>';
			} else {
				var html = '';
				ids.forEach(function (idStr) {
					var item = seleccionadosCapMap[idStr];
					if (!item) return;
					var horaTxt = item.hora ? item.hora.substring(0, 5) : '';
					html += '<div class="ep-act-sel-item" data-id="' + item.id + '">'
						+ '<div class="ep-act-sel-item-left">'
						+ '<span class="ep-act-sel-item-dot" style="background:#164194;"></span>'
						+ '<span class="ep-act-sel-item-title" title="' + esc(item.codigo) + ' • ' + esc(item.punto_venta) + '">'
						+ esc(item.codigo) + ' • ' + esc(item.punto_venta)
						+ '</span>'
						+ (horaTxt ? '<span class="ep-act-sel-item-time">' + esc(horaTxt) + '</span>' : '')
						+ '</div>'
						+ '<button type="button" class="ep-act-sel-item-del" data-id="' + item.id + '" title="Quitar de seleccionados">'
						+ '<svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>'
						+ '</button>'
						+ '</div>';
				});
				selCapLista.innerHTML = html;
			}
		}

		if (tablaCapRows) {
			tablaCapRows.querySelectorAll('.ep-act-dual-row').forEach(function (fila) {
				var chk = fila.querySelector('input[type="checkbox"]');
				if (chk) {
					var id = Number(chk.value);
					var checked = !!seleccionadosCapMap[id];
					chk.checked = checked;
					fila.classList.toggle('checked', checked);
				}
			});
		}
	}

	if (btnLimpiarCapSel) {
		btnLimpiarCapSel.addEventListener('click', function () {
			seleccionadosCapMap = {};
			actualizarSeleccionadosCap();
		});
	}

	if (selCapLista) {
		selCapLista.addEventListener('click', function (e) {
			var delBtn = e.target.closest('.ep-act-sel-item-del');
			if (!delBtn) return;
			var id = Number(delBtn.dataset.id);
			if (seleccionadosCapMap[id]) {
				delete seleccionadosCapMap[id];
				actualizarSeleccionadosCap();
			}
		});
	}

	// ==================== CAPACITACIONES: FILTROS ====================
	function actualizarVisibilidadLimpiarFechaCap() {
		var hasVal = (inpCapFechaDesde && inpCapFechaDesde.value) || (inpCapFechaHasta && inpCapFechaHasta.value);
		if (btnLimpiarCapFecha) {
			btnLimpiarCapFecha.classList.toggle('hidden', !hasVal);
		}
	}

	function setModoFechaCap(nuevoModo) {
		modoFechaCap = nuevoModo;
		if (modoFechaCap === 'rango') {
			if (pillCapPillRango) pillCapPillRango.classList.add('active');
			if (pillCapPillUnica) pillCapPillUnica.classList.remove('active');
			if (capFechaHastaBox) capFechaHastaBox.classList.remove('hidden');
			if (capFechaSep) capFechaSep.classList.remove('hidden');
		} else {
			if (pillCapPillUnica) pillCapPillUnica.classList.add('active');
			if (pillCapPillRango) pillCapPillRango.classList.remove('active');
			if (capFechaHastaBox) capFechaHastaBox.classList.add('hidden');
			if (capFechaSep) capFechaSep.classList.add('hidden');
			if (inpCapFechaHasta) inpCapFechaHasta.value = '';
		}
		actualizarVisibilidadLimpiarFechaCap();
		aplicarFiltrosYRenderizarCap();
	}

	if (pillCapPillUnica) pillCapPillUnica.addEventListener('click', function () { setModoFechaCap('unica'); });
	if (pillCapPillRango) pillCapPillRango.addEventListener('click', function () { setModoFechaCap('rango'); });

	if (btnLimpiarCapFecha) {
		btnLimpiarCapFecha.addEventListener('click', function () {
			if (inpCapFechaDesde) inpCapFechaDesde.value = '';
			if (inpCapFechaHasta) inpCapFechaHasta.value = '';
			actualizarVisibilidadLimpiarFechaCap();
			aplicarFiltrosYRenderizarCap();
		});
	}

	if (inpCapFechaDesde) inpCapFechaDesde.addEventListener('click', function () { abrirPickerFecha(this); });
	if (capFechaDesdeBox) capFechaDesdeBox.addEventListener('click', function (e) { if (e.target !== inpCapFechaDesde) abrirPickerFecha(inpCapFechaDesde); });
	if (inpCapFechaHasta) inpCapFechaHasta.addEventListener('click', function () { abrirPickerFecha(this); });
	if (capFechaHastaBox) capFechaHastaBox.addEventListener('click', function (e) { if (e.target !== inpCapFechaHasta) abrirPickerFecha(inpCapFechaHasta); });

	['input', 'change'].forEach(function (evType) {
		if (inpCapFechaDesde) inpCapFechaDesde.addEventListener(evType, function () { actualizarVisibilidadLimpiarFechaCap(); aplicarFiltrosYRenderizarCap(); });
		if (inpCapFechaHasta) inpCapFechaHasta.addEventListener(evType, function () { actualizarVisibilidadLimpiarFechaCap(); aplicarFiltrosYRenderizarCap(); });
	});

	if (promotorCapDisplay) {
		promotorCapDisplay.addEventListener('click', function (e) {
			e.stopPropagation();
			if (promotorCapMenu) promotorCapMenu.classList.toggle('hidden');
			if (promotorCapSearch && !promotorCapMenu.classList.contains('hidden')) {
				promotorCapSearch.value = '';
				if (promotorCapList) promotorCapList.querySelectorAll('.ep-act-combo-item').forEach(function (it) { it.classList.remove('hidden'); });
				promotorCapSearch.focus();
			}
		});
	}

	if (promotorCapSearch) {
		promotorCapSearch.addEventListener('input', function () {
			var q = normalizar(this.value);
			if (!promotorCapList) return;
			promotorCapList.querySelectorAll('.ep-act-combo-item').forEach(function (it) {
				var txt = normalizar(it.textContent);
				it.classList.toggle('hidden', q !== '' && txt.indexOf(q) === -1);
			});
		});
	}

	if (promotorCapList) {
		promotorCapList.addEventListener('click', function (e) {
			var item = e.target.closest('.ep-act-combo-item');
			if (!item) return;
			promotorCapFiltro = item.dataset.value;
			if (promotorCapTexto) promotorCapTexto.textContent = item.textContent;
			promotorCapList.querySelectorAll('.ep-act-combo-item').forEach(function (it) { it.classList.remove('selected'); });
			item.classList.add('selected');
			if (promotorCapMenu) promotorCapMenu.classList.add('hidden');
			aplicarFiltrosYRenderizarCap();
		});
	}

	document.addEventListener('click', function (e) {
		if (promotorCapCombo && !promotorCapCombo.contains(e.target)) {
			if (promotorCapMenu) promotorCapMenu.classList.add('hidden');
		}
	});

	if (canalCapSelect) {
		canalCapSelect.addEventListener('change', function () {
			canalCapFiltro = this.value;
			aplicarFiltrosYRenderizarCap();
		});
	}

	// ==================== CARGAR Y RENDERIZAR CAPACITACIONES ====================
	function cargarRegistrosCapacitaciones() {
		var tipoActual = (selTipo && selTipo.value) || 'capacitaciones';
		if (tablaCapRows) {
			tablaCapRows.innerHTML = '<div class="ep-act-loading-state">'
				+ '<svg style="width:24px;height:24px;animation:spin 1s linear infinite;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="3" stroke-dasharray="32" stroke-linecap="round"></circle></svg>'
				+ '<span>Cargando registros...</span>'
				+ '</div>';
		}

		return fetch('getters/reportes_registros.php?tipo=' + encodeURIComponent(tipoActual))
			.then(function (r) { return r.json(); })
			.then(function (d) {
				if (!d.success) {
					if (tablaCapRows) tablaCapRows.innerHTML = '<div class="ep-act-loading-state"><span>No se pudieron cargar los registros.</span></div>';
					aviso('error', 'Error', esc(d.error || 'Intenta de nuevo.'));
					return;
				}
				registrosCapCargados = d.registros || [];

				if (promotorCapList && d.promotores) {
					var htmlProm = '<button type="button" class="ep-act-combo-item' + (promotorCapFiltro === 'todos' ? ' selected' : '') + '" data-value="todos">Todos los promotores</button>';
					d.promotores.forEach(function (p) {
						if (!p) return;
						htmlProm += '<button type="button" class="ep-act-combo-item' + (promotorCapFiltro === p ? ' selected' : '') + '" data-value="' + esc(p) + '">' + esc(p) + '</button>';
					});
					promotorCapList.innerHTML = htmlProm;
				}

				if (canalCapSelect && d.canales) {
					var htmlCan = '<option value="todos">Todos</option>';
					d.canales.forEach(function (c) {
						if (!c) return;
						htmlCan += '<option value="' + esc(c) + '">' + esc(c) + '</option>';
					});
					canalCapSelect.innerHTML = htmlCan;
				}

				aplicarFiltrosYRenderizarCap();
			})
			.catch(function () {
				if (tablaCapRows) tablaCapRows.innerHTML = '<div class="ep-act-loading-state"><span>Error de conexión al cargar registros.</span></div>';
			});
	}

	function aplicarFiltrosYRenderizarCap() {
		if (!tablaCapRows) return;

		var desdeVal = (inpCapFechaDesde && inpCapFechaDesde.value) ? inpCapFechaDesde.value.trim() : '';
		var hastaVal = (inpCapFechaHasta && inpCapFechaHasta.value) ? inpCapFechaHasta.value.trim() : '';

		var filtrados = registrosCapCargados.filter(function (r) {
			var fIso = (r.fecha || '').substring(0, 10);

			if (modoFechaCap === 'rango') {
				if (desdeVal && hastaVal) {
					var minD = desdeVal <= hastaVal ? desdeVal : hastaVal;
					var maxD = desdeVal <= hastaVal ? hastaVal : desdeVal;
					if (fIso < minD || fIso > maxD) return false;
				} else if (desdeVal) {
					if (fIso < desdeVal) return false;
				} else if (hastaVal) {
					if (fIso > hastaVal) return false;
				}
			} else {
				if (desdeVal && fIso !== desdeVal) return false;
			}

			if (promotorCapFiltro !== 'todos') {
				if (normalizar(r.promotor) !== normalizar(promotorCapFiltro)) return false;
			}

			if (canalCapFiltro !== 'todos') {
				if ((r.canal || '').toLowerCase() !== canalCapFiltro.toLowerCase()) return false;
			}

			return true;
		});

		if (filtrados.length === 0) {
			tablaCapRows.innerHTML = '<div class="ep-act-loading-state"><span>No se encontraron registros con los filtros seleccionados.</span></div>';
			return;
		}

		var html = '';
		filtrados.forEach(function (r) {
			var isChecked = !!seleccionadosCapMap[r.id];
			var statusClass = (r.estado || 'Activo').toLowerCase() === 'aprobado' || (r.estado || '').toLowerCase() === 'activo' ? 'activo' : 'pendiente';
			var fechaCorta = formatearFechaCorta(r.fecha);

			var tipoReg = r.tipo || (selTipo ? selTipo.value : 'capacitaciones');
			var miniBodyHtml = '';

			if (tipoReg === 'capacitaciones') {
				var cap = r.capacitacion || {};
				var vend = parseInt(cap.vendedores || 0, 10);
				var jefe = parseInt(cap.jefe_tienda || 0, 10);
				var asist = parseInt(cap.asistente_jefe || 0, 10);
				var totalAsistentes = vend + jefe + asist;
				var interacciones = parseInt(cap.interacciones || 0, 10);

				var maxCargo = Math.max(1, totalAsistentes);
				var pctVend = Math.round((vend / maxCargo) * 100);
				var pctJefe = Math.round((jefe / maxCargo) * 100);
				var pctAsist = Math.round((asist / maxCargo) * 100);

				var hAsist = 20;
				var hInter = totalAsistentes > 0 ? Math.max(3, Math.round(20 * (interacciones / totalAsistentes))) : (interacciones > 0 ? 8 : 0);

				miniBodyHtml = '<div class="ep-act-mini-title-row">'
					+ '<span class="ep-act-mini-title">ESTADISTICAS CAPACITACIONES</span>'
					+ '<span class="ep-act-mini-badge" style="background:#EDE8F6;color:#164194;">Slide 1</span>'
					+ '</div>'
					+ '<div class="ep-cap-mini-cargos-wrap">'
					+ '<div class="ep-cap-mini-cargo-item"><span class="ep-cap-mini-cargo-label">VEND</span><div class="ep-cap-mini-cargo-track"><div class="ep-cap-mini-cargo-bar" style="width:' + pctVend + '%;"></div></div><span class="ep-cap-mini-cargo-val">' + vend + '</span></div>'
					+ '<div class="ep-cap-mini-cargo-item"><span class="ep-cap-mini-cargo-label">JEFE</span><div class="ep-cap-mini-cargo-track"><div class="ep-cap-mini-cargo-bar" style="width:' + pctJefe + '%;"></div></div><span class="ep-cap-mini-cargo-val">' + jefe + '</span></div>'
					+ '<div class="ep-cap-mini-cargo-item"><span class="ep-cap-mini-cargo-label">ASIS</span><div class="ep-cap-mini-cargo-track"><div class="ep-cap-mini-cargo-bar" style="width:' + pctAsist + '%;"></div></div><span class="ep-cap-mini-cargo-val">' + asist + '</span></div>'
					+ '</div>'
					+ '<div class="ep-cap-mini-chart-row">'
					+ '<div class="ep-cap-mini-bar-col"><span class="ep-cap-mini-bar-num">' + totalAsistentes + '</span><div class="ep-cap-mini-bar-v" style="height:' + hAsist + 'px;background:#164194;"></div><span class="ep-cap-mini-bar-tag">ASIST</span></div>'
					+ '<div class="ep-cap-mini-bar-col"><span class="ep-cap-mini-bar-num">' + interacciones + '</span><div class="ep-cap-mini-bar-v" style="height:' + hInter + 'px;background:#3062A9;"></div><span class="ep-cap-mini-bar-tag">INTER</span></div>'
					+ '</div>';
			} else if (tipoReg === 'epson-day') {
				var cob = r.cobertura || { pct: 0 };
				var emb = r.embudo || { visitaron: 0, interactuaron: 0, compraron: 0, tasa_interaccion_pct: 0, tasa_conversion_pct: 0 };
				var topModel = (r.modelos && r.modelos.length) ? r.modelos[0].modelo : (r.tipo_actividad || 'Epson Day');
				var vis = Math.max(1, parseInt(emb.visitaron || 0, 10));
				var v1 = parseInt(emb.visitaron || 0, 10);
				var v2 = parseInt(emb.interactuaron || 0, 10);
				var v3 = parseInt(emb.compraron || 0, 10);
				var h1 = 20;
				var h2 = Math.max(2, Math.round(20 * (v2 / vis)));
				var h3 = Math.max(2, Math.round(20 * (v3 / vis)));

				miniBodyHtml = '<div class="ep-act-mini-title-row">'
					+ '<span class="ep-act-mini-title">EPSON DAY</span>'
					+ '<span class="ep-act-mini-badge" style="background:#E0F2FE;color:#006699;">Slide 1</span>'
					+ '</div>'
					+ '<div class="ep-act-mini-kpis-row">'
					+ '<div class="ep-act-mini-kpi"><div class="ep-act-mini-kpi-tag">COB</div><div class="ep-act-mini-kpi-val">' + (cob.pct || 0) + '%</div></div>'
					+ '<div class="ep-act-mini-kpi"><div class="ep-act-mini-kpi-tag">INT</div><div class="ep-act-mini-kpi-val">' + (emb.tasa_interaccion_pct || 0) + '%</div></div>'
					+ '<div class="ep-act-mini-kpi"><div class="ep-act-mini-kpi-tag">VEN</div><div class="ep-act-mini-kpi-val">' + (emb.tasa_conversion_pct || 0) + '%</div></div>'
					+ '</div>'
					+ '<div class="ep-act-mini-bottom-row">'
					+ '<div class="ep-act-mini-bars"><div class="ep-act-mini-bar-item"><span class="ep-act-mini-bar-lbl" title="' + esc(topModel) + '">' + esc(topModel) + '</span><div class="ep-act-mini-bar-track"><div class="ep-act-mini-bar-fill" style="width:85%;background:#006699;"></div></div></div></div>'
					+ '<div class="ep-act-mini-funnel">'
					+ '<div class="ep-act-mini-fun-bar" style="height:' + h1 + 'px;background:#006699;"></div>'
					+ '<div class="ep-act-mini-fun-bar" style="height:' + h2 + 'px;background:#0284C7;"></div>'
					+ '<div class="ep-act-mini-fun-bar" style="height:' + h3 + 'px;background:#38BDF8;"></div>'
					+ '</div>'
					+ '</div>';
			} else if (tipoReg === 'evento-ferias') {
				var embF = r.embudo || { visitaron: 0, interactuaron: 0, compraron: 0, tasa_interaccion_pct: 0, tasa_conversion_pct: 0 };
				var topModelF = (r.modelos && r.modelos.length) ? r.modelos[0].modelo : (r.tipo_actividad || 'Evento/Feria');
				var visF = Math.max(1, parseInt(embF.visitaron || 0, 10));
				var vf1 = parseInt(embF.visitaron || 0, 10);
				var vf2 = parseInt(embF.interactuaron || 0, 10);
				var vf3 = parseInt(embF.compraron || 0, 10);
				var hf1 = 20;
				var hf2 = Math.max(2, Math.round(20 * (vf2 / visF)));
				var hf3 = Math.max(2, Math.round(20 * (vf3 / visF)));

				miniBodyHtml = '<div class="ep-act-mini-title-row">'
					+ '<span class="ep-act-mini-title">EVENTOS O FERIAS</span>'
					+ '<span class="ep-act-mini-badge" style="background:#CCFBF1;color:#0D9488;">Slide 1</span>'
					+ '</div>'
					+ '<div class="ep-act-mini-kpis-row" style="grid-template-columns:1fr 1fr;">'
					+ '<div class="ep-act-mini-kpi"><div class="ep-act-mini-kpi-tag">INT</div><div class="ep-act-mini-kpi-val">' + (embF.tasa_interaccion_pct || 0) + '%</div></div>'
					+ '<div class="ep-act-mini-kpi"><div class="ep-act-mini-kpi-tag">VEN</div><div class="ep-act-mini-kpi-val">' + (embF.tasa_conversion_pct || 0) + '%</div></div>'
					+ '</div>'
					+ '<div class="ep-act-mini-bottom-row">'
					+ '<div class="ep-act-mini-bars"><div class="ep-act-mini-bar-item"><span class="ep-act-mini-bar-lbl" title="' + esc(topModelF) + '">' + esc(topModelF) + '</span><div class="ep-act-mini-bar-track"><div class="ep-act-mini-bar-fill" style="width:85%;background:#0D9488;"></div></div></div></div>'
					+ '<div class="ep-act-mini-funnel">'
					+ '<div class="ep-act-mini-fun-bar" style="height:' + hf1 + 'px;background:#0D9488;"></div>'
					+ '<div class="ep-act-mini-fun-bar" style="height:' + hf2 + 'px;background:#14B8A6;"></div>'
					+ '<div class="ep-act-mini-fun-bar" style="height:' + hf3 + 'px;background:#2DD4BF;"></div>'
					+ '</div>'
					+ '</div>';
			} else if (tipoReg === 'exhibiciones') {
				var exh = r.exhibiciones || {};
				var cab = parseInt(exh.cabeceras || 0, 10);
				var rum = parseInt(exh.rumas || 0, 10);
				var mue = parseInt(exh.muebles || 0, 10);
				var maxE = Math.max(1, cab, rum, mue);
				miniBodyHtml = '<div class="ep-act-mini-title-row">'
					+ '<span class="ep-act-mini-title">EXHIBICIONES</span>'
					+ '<span class="ep-act-mini-badge" style="background:#FEF3C7;color:#D97706;">Slide 1</span>'
					+ '</div>'
					+ '<div class="ep-cap-mini-cargos-wrap">'
					+ '<div class="ep-cap-mini-cargo-item"><span class="ep-cap-mini-cargo-label">CAB</span><div class="ep-cap-mini-cargo-track"><div class="ep-cap-mini-cargo-bar" style="width:' + Math.round((cab/maxE)*100) + '%;background:#D97706;"></div></div><span class="ep-cap-mini-cargo-val" style="color:#D97706;">' + cab + '</span></div>'
					+ '<div class="ep-cap-mini-cargo-item"><span class="ep-cap-mini-cargo-label">RUM</span><div class="ep-cap-mini-cargo-track"><div class="ep-cap-mini-cargo-bar" style="width:' + Math.round((rum/maxE)*100) + '%;background:#D97706;"></div></div><span class="ep-cap-mini-cargo-val" style="color:#D97706;">' + rum + '</span></div>'
					+ '<div class="ep-cap-mini-cargo-item"><span class="ep-cap-mini-cargo-label">MUE</span><div class="ep-cap-mini-cargo-track"><div class="ep-cap-mini-cargo-bar" style="width:' + Math.round((mue/maxE)*100) + '%;background:#D97706;"></div></div><span class="ep-cap-mini-cargo-val" style="color:#D97706;">' + mue + '</span></div>'
					+ '</div>';
			} else {
				var mats = r.pop_materiales || [];
				miniBodyHtml = '<div class="ep-act-mini-title-row">'
					+ '<span class="ep-act-mini-title">MATERIAL POP</span>'
					+ '<span class="ep-act-mini-badge" style="background:#EDE9FE;color:#7C3AED;">Slide 1</span>'
					+ '</div>'
					+ '<div style="padding:6px 0;font-size:8px;color:#475569;"><strong>' + mats.length + '</strong> materiales registrados</div>';
			}

			html += '<div class="ep-act-dual-row' + (isChecked ? ' checked' : '') + '" data-id="' + r.id + '">'
				// Columna 1: Registro (código, estado, actividad, PDV)
				+ '<div class="ep-act-reg-card">'
				+ '<input type="checkbox" value="' + r.id + '"' + (isChecked ? ' checked' : '') + ' aria-label="Seleccionar registro ' + esc(r.codigo) + '">'
				+ '<div class="ep-act-reg-info">'
				+ '<div class="ep-act-reg-top">'
				+ '<strong class="ep-act-reg-code">' + esc(r.codigo) + '</strong>'
				+ '<span class="ep-act-badge-status ' + statusClass + '">' + esc(r.estado || 'Aprobado') + '</span>'
				+ '</div>'
				+ '<p class="ep-act-reg-desc">' + esc(r.tipo_actividad || 'Capacitación') + '</p>'
				+ '<p class="ep-act-reg-pdv">PDV: ' + esc(r.punto_venta) + (r.ciudad ? ' • ' + esc(r.ciudad) : '') + '</p>'
				+ '</div>'
				+ '</div>'

				// Columna 2: Previsualización de la PRIMERA DIAPOSITIVA (Miniatura oficial)
				+ '<div class="ep-act-prev-card preview-trigger" data-id="' + r.id + '" role="button" tabindex="0" title="Ver primera diapositiva">'
				+ '<div class="ep-act-mini-slide-canvas">'
				+ '<div class="ep-act-mini-pro-col">'
				+ '<span class="ep-act-mini-pro-name" title="' + esc(r.promotor) + '">' + esc(r.promotor || 'PROMOTOR') + '</span>'
				+ '<span class="ep-act-mini-pro-pdv" title="' + esc(r.punto_venta) + '">' + esc(r.punto_venta) + '</span>'
				+ '<span class="ep-act-mini-pro-date">' + esc(fechaCorta) + '</span>'
				+ '<span class="ep-act-mini-pro-city">' + esc(r.ciudad || 'ECUADOR') + '</span>'
				+ '</div>'
				+ '<div class="ep-act-mini-body-col">'
				+ miniBodyHtml
				+ '</div>'
				+ '</div>'
				+ '<div class="ep-act-prev-overlay">'
				+ '<span class="ep-act-prev-pill">'
				+ '<svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>'
				+ 'Ampliar diapositiva'
				+ '</span>'
				+ '</div>'
				+ '</div>'

				+ '</div>';
		});

		tablaCapRows.innerHTML = html;
	}

	if (tablaCapRows) {
		tablaCapRows.addEventListener('change', function (e) {
			var chk = e.target.closest('input[type="checkbox"]');
			if (!chk) return;
			var id = Number(chk.value);
			var reg = registrosCapCargados.find(function (it) { return it.id === id; });
			if (!reg) return;

			if (chk.checked) {
				seleccionadosCapMap[id] = reg;
			} else {
				delete seleccionadosCapMap[id];
			}
			actualizarSeleccionadosCap();
		});

		tablaCapRows.addEventListener('click', function (e) {
			var prevCard = e.target.closest('.ep-act-prev-card');
			if (!prevCard) return;
			var id = Number(prevCard.dataset.id);
			var reg = registrosCapCargados.find(function (it) { return it.id === id; });
			if (reg) {
				abrirLightbox(reg);
			}
		});

		tablaCapRows.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ') {
				var prevCard = e.target.closest('.ep-act-prev-card');
				if (prevCard) {
					e.preventDefault();
					prevCard.click();
				}
			}
		});
	}

	// ==================== LIGHTBOX: PRIMERA HOJA DEL PPTX OFICIAL ====================
	function abrirLightbox(reg) {
		if (!lightbox) return;
		registroLightboxActual = reg;

		if (lbCodigo) lbCodigo.textContent = reg.codigo;
		if (lbEstado) {
			lbEstado.textContent = reg.estado || 'Activo';
			lbEstado.className = 'ep-act-lightbox-status ' + ((reg.estado || '').toLowerCase() === 'activo' || (reg.estado || '').toLowerCase() === 'aprobado' ? 'activo' : 'pendiente');
		}
		var tipoActual = reg.tipo || (selTipo ? selTipo.value : 'activaciones');
		var actNombreDisplay = reg.tipo_actividad || (selTipo && selTipo.dataset.label ? selTipo.dataset.label : 'Actividad');
		if (lbDesc) lbDesc.textContent = actNombreDisplay + ' · Diapositiva 1 oficial de PowerPoint';
		if (lbPdv) lbPdv.textContent = (reg.punto_venta || 'Punto de venta') + (reg.ciudad ? ' • ' + reg.ciudad : '');
		if (lbFecha) lbFecha.textContent = formatearFechaSlash(reg.fecha) + (reg.hora ? ' • ' + reg.hora.substring(0, 5) : '');

		if (lbBody) {
			var horarioTxt = (reg.hora_inicio && reg.hora_fin) ? (reg.hora_inicio + ' – ' + reg.hora_fin) : (reg.hora ? reg.hora.substring(0, 5) : '10:00 – 16:00');
			var fechaSlide = formatearFechaTexto(reg.fecha);
			var ciudadTxt = ((reg.ciudad || 'ECUADOR').toUpperCase()) + ' - ECUADOR';
			var promotorNom = (reg.promotor || 'PROMOTOR').toUpperCase();
			var promotorMail = (reg.promotor_correo || (promotorNom.toLowerCase().replace(/\s+/g, '.') + '@xplora.net')).toLowerCase();
			var pdvNom = (reg.punto_venta || 'PUNTO DE VENTA').toUpperCase();

			var coms = (reg.comentarios && reg.comentarios.length) ? reg.comentarios.slice(0, 5) : [];
			var htmlComs = '';
			if (coms.length > 0) {
				coms.forEach(function (c) {
					if (c && String(c).trim()) {
						htmlComs += '<div class="ep-slide-com-item">• ' + esc(String(c).trim()) + '</div>';
					}
				});
			}
			if (!htmlComs) {
				htmlComs = '<div class="ep-slide-com-item" style="color:#94A3B8;">• Sin comentarios registrados</div>';
			}

			function armarColumnaPromotor(actividadTitulo) {
				return '<div class="ep-slide-real-promotor">'
					+ '<div class="ep-slide-pro-avatar">'
					+ '<svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>'
					+ '</div>'
					+ '<div style="margin-bottom:auto;">'
					+ '<div class="ep-slide-pro-nombre">' + esc(promotorNom) + '</div>'
					+ '<div class="ep-slide-pro-email">' + esc(promotorMail) + '</div>'
					+ '</div>'
					+ '<div class="ep-slide-pro-divider"></div>'
					+ '<div style="margin-bottom:auto;">'
					+ '<div class="ep-slide-pro-punto">' + esc(pdvNom) + '</div>'
					+ '<div class="ep-slide-pro-actividad">' + esc(actividadTitulo) + '</div>'
					+ '</div>'
					+ '<div class="ep-slide-pro-divider"></div>'
					+ '<div>'
					+ '<div class="ep-slide-pro-fecha">' + esc(fechaSlide) + '<br>' + esc(horarioTxt) + '</div>'
					+ '<div class="ep-slide-pro-ciudad">' + esc(ciudadTxt) + '</div>'
					+ '</div>'
					+ '</div>';
			}

			if (tipoActual === 'capacitaciones') {
				// ================= CAPACITACIONES (slide3.xml) =================
				var actNomCap = (reg.tipo_actividad || 'CAPACITACION').toUpperCase();
				if (actNomCap.indexOf('CAPACITACION') === -1) actNomCap = 'CAPACITACION ' + actNomCap;

				var cap = reg.capacitacion || {};
				var vend = parseInt(cap.vendedores || 0, 10);
				var jefe = parseInt(cap.jefe_tienda || 0, 10);
				var asist = parseInt(cap.asistente_jefe || 0, 10);
				var totalAsistentes = vend + jefe + asist;
				var interacciones = parseInt(cap.interacciones || 0, 10);

				var maxTotal = Math.max(1, totalAsistentes);
				var pctVend = Math.round((vend / maxTotal) * 100);
				var pctJefe = Math.round((jefe / maxTotal) * 100);
				var pctAsist = Math.round((asist / maxTotal) * 100);

				var maxH = 65;
				var hAsist = maxH;
				var hInter = totalAsistentes > 0 ? Math.max(6, Math.round(maxH * (interacciones / totalAsistentes))) : (interacciones > 0 ? 12 : 0);

				var htmlSlideCap = '<div class="ep-slide-real-canvas">'
					+ '<h2 class="ep-slide-real-title">ESTADISTICAS CAPACITACIONES</h2>'
					+ '<div class="ep-slide-real-grid">'
					+ armarColumnaPromotor(actNomCap)
					+ '<div class="ep-slide-cap-main">'
					+ '<div class="ep-slide-cap-sec">'
					+ '<div class="ep-slide-cap-sec-title">DETALLE ASISTENTES</div>'
					+ '<div class="ep-slide-cap-cargos-list">'
					+ '<div class="ep-slide-cap-cargo-row"><span class="ep-slide-cap-cargo-nombre">VENDEDORES</span><div class="ep-slide-cap-cargo-track"><div class="ep-slide-cap-cargo-fill" style="width:' + pctVend + '%;"></div></div><span class="ep-slide-cap-cargo-num">' + vend + '</span></div>'
					+ '<div class="ep-slide-cap-cargo-row"><span class="ep-slide-cap-cargo-nombre">JEFE DE TIENDA</span><div class="ep-slide-cap-cargo-track"><div class="ep-slide-cap-cargo-fill" style="width:' + pctJefe + '%;"></div></div><span class="ep-slide-cap-cargo-num">' + jefe + '</span></div>'
					+ '<div class="ep-slide-cap-cargo-row"><span class="ep-slide-cap-cargo-nombre">ASIST. DE TIENDA</span><div class="ep-slide-cap-cargo-track"><div class="ep-slide-cap-cargo-fill" style="width:' + pctAsist + '%;"></div></div><span class="ep-slide-cap-cargo-num">' + asist + '</span></div>'
					+ '</div>'
					+ '</div>'
					+ '<div class="ep-slide-cap-sec">'
					+ '<div class="ep-slide-cap-sec-title">DETALLE INTERACCIONES</div>'
					+ '<div class="ep-slide-cap-inter-chart">'
					+ '<div class="ep-slide-cap-inter-col"><span class="ep-slide-cap-inter-num">' + totalAsistentes + '</span><div class="ep-slide-cap-inter-bar bar-asistentes" style="height:' + hAsist + 'px;"></div></div>'
					+ '<div class="ep-slide-cap-inter-col"><span class="ep-slide-cap-inter-num">' + interacciones + '</span><div class="ep-slide-cap-inter-bar bar-interacciones" style="height:' + hInter + 'px;"></div></div>'
					+ '</div>'
					+ '<div class="ep-slide-cap-inter-axis"></div>'
					+ '<div class="ep-slide-cap-inter-labels"><span>ASISTENTES</span><span>INTERACCIONES</span></div>'
					+ '</div>'
					+ '<div class="ep-slide-cap-sec">'
					+ '<div class="ep-slide-cap-sec-title">COMENTARIOS</div>'
					+ '<div class="ep-slide-cap-com-list">' + htmlComs + '</div>'
					+ '</div>'
					+ '</div>'
					+ '</div>'
					+ '</div>';
				lbBody.innerHTML = htmlSlideCap;

			} else if (tipoActual === 'exhibiciones') {
				// ================= EXHIBICIONES (slide3.xml) =================
				var exh = reg.exhibiciones || {};
				var filasExh = [
					{ label: 'CABECERAS', val: parseInt(exh.cabeceras || 0, 10) },
					{ label: 'RUMAS', val: parseInt(exh.rumas || 0, 10) },
					{ label: 'MUEBLES', val: parseInt(exh.muebles || 0, 10) },
					{ label: 'EXHIBICION REGULAR', val: parseInt(exh.exh_regular || 0, 10) },
					{ label: 'OTRAS', val: parseInt(exh.otras || 0, 10) }
				];
				var maxExh = Math.max(1, filasExh[0].val, filasExh[1].val, filasExh[2].val, filasExh[3].val, filasExh[4].val);

				var htmlFilasExh = '';
				filasExh.forEach(function (f) {
					var pct = Math.round((f.val / maxExh) * 100);
					htmlFilasExh += '<div class="ep-slide-cap-cargo-row">'
						+ '<span class="ep-slide-cap-cargo-nombre" style="font-size:10px;">' + f.label + '</span>'
						+ '<div class="ep-slide-cap-cargo-track" style="height:12px;"><div class="ep-slide-cap-cargo-fill" style="width:' + pct + '%;background:#D97706;"></div></div>'
						+ '<span class="ep-slide-cap-cargo-num" style="color:#D97706;font-size:12px;">' + f.val + '</span>'
						+ '</div>';
				});

				var htmlSlideExh = '<div class="ep-slide-real-canvas">'
					+ '<h2 class="ep-slide-real-title">EXHIBICIONES QUE INSPIRAN</h2>'
					+ '<div class="ep-slide-real-grid">'
					+ armarColumnaPromotor('EXHIBICION ' + (reg.tipo_actividad || 'REGULAR').toUpperCase())
					+ '<div class="ep-slide-cap-main">'
					+ '<div class="ep-slide-cap-sec">'
					+ '<div class="ep-slide-cap-sec-title">AUDITORIA DE ESPACIOS DE EXHIBICION</div>'
					+ '<div class="ep-slide-cap-cargos-list" style="gap:10px;padding:8px 0;">'
					+ htmlFilasExh
					+ '</div>'
					+ '</div>'
					+ '<div class="ep-slide-cap-sec">'
					+ '<div class="ep-slide-cap-sec-title">COMENTARIOS Y OBSERVACIONES</div>'
					+ '<div class="ep-slide-cap-com-list">' + htmlComs + '</div>'
					+ '</div>'
					+ '</div>'
					+ '</div>'
					+ '</div>';
				lbBody.innerHTML = htmlSlideExh;

			} else if (tipoActual === 'colocacion-pop') {
				// ================= COLOCACION POP =================
				var mats = reg.pop_materiales || [];
				var filasMats = '';
				if (mats.length > 0) {
					mats.forEach(function (m) {
						filasMats += '<tr><td style="padding:6px;font-weight:600;">' + esc(m.material || '') + '</td>'
							+ '<td style="padding:6px;text-align:center;">' + (m.bodega || 0) + '</td>'
							+ '<td style="padding:6px;text-align:center;">' + (m.canales || 0) + '</td>'
							+ '<td style="padding:6px;text-align:center;">' + (m.retail || 0) + '</td>'
							+ '<td style="padding:6px;text-align:center;font-weight:700;color:#7C3AED;">' + (m.disponible || 0) + '</td></tr>';
					});
				} else {
					filasMats = '<tr><td colspan="5" style="padding:10px;text-align:center;color:#94A3B8;">Sin materiales registrados</td></tr>';
				}

				var htmlSlidePop = '<div class="ep-slide-real-canvas">'
					+ '<h2 class="ep-slide-real-title">COLOCACION DE MATERIAL POP</h2>'
					+ '<div class="ep-slide-real-grid">'
					+ armarColumnaPromotor('ENTREGA DE MATERIAL POP')
					+ '<div class="ep-slide-cap-main">'
					+ '<div class="ep-slide-cap-sec">'
					+ '<div class="ep-slide-cap-sec-title">INVENTARIO Y ENTREGA DE MATERIAL</div>'
					+ '<table style="width:100%;font-size:10px;border-collapse:collapse;margin-top:6px;">'
					+ '<thead><tr style="background:#F1F5F9;color:#475569;text-align:left;"><th style="padding:6px;">Material</th><th style="padding:6px;text-align:center;">Bodega</th><th style="padding:6px;text-align:center;">Canales</th><th style="padding:6px;text-align:center;">Retail</th><th style="padding:6px;text-align:center;">Disp.</th></tr></thead>'
					+ '<tbody>' + filasMats + '</tbody>'
					+ '</table>'
					+ '</div>'
					+ '<div class="ep-slide-cap-sec">'
					+ '<div class="ep-slide-cap-sec-title">COMENTARIOS</div>'
					+ '<div class="ep-slide-cap-com-list">' + htmlComs + '</div>'
					+ '</div>'
					+ '</div>'
					+ '</div>'
					+ '</div>';
				lbBody.innerHTML = htmlSlidePop;

			} else {
				// ================= EMBUDO DE VENTAS (ACTIVACIONES, EPSON DAY, EVENTOS O FERIAS) =================
				var esEvento = (tipoActual === 'evento-ferias');
				var esEpsonDay = (tipoActual === 'epson-day');
				var slideTitle = esEvento ? 'EVENTOS O FERIAS' : (esEpsonDay ? 'EPSON DAY' : 'ESTADISTICAS ACTIVACIONES');
				var prefijoAct = esEvento ? 'EVENTO ' : (esEpsonDay ? 'ACTIVIDAD ' : 'ACTIVIDAD ');
				var actNomEmb = prefijoAct + (reg.tipo_actividad || (esEvento ? 'EVENTO' : (esEpsonDay ? 'EPSON DAY' : 'ACTIVACIONES'))).toUpperCase();

				var cob = reg.cobertura || { nacional: 0, coberturadas: 0, pct: 0 };
				var emb = reg.embudo || { visitaron: 0, interactuaron: 0, compraron: 0, tasa_interaccion_pct: 0, tasa_conversion_pct: 0 };

				var modelos = (reg.modelos && reg.modelos.length) ? reg.modelos.slice() : [];
				modelos.sort(function (a, b) {
					return (parseInt(b.cantidad || 0, 10) || 0) - (parseInt(a.cantidad || 0, 10) || 0);
				});

				var modelosTop5 = modelos.slice(0, 5);
				var maxCant = 1;
				modelosTop5.forEach(function (m) {
					var c = parseInt(m.cantidad || 0, 10);
					if (c > maxCant) maxCant = c;
				});

				var mayor = modelos.length ? modelos[0] : null;
				var menor = modelos.length > 1 ? modelos[modelos.length - 1] : mayor;

				var mayorNom = mayor ? mayor.modelo.toUpperCase() : 'SIN DATOS';
				var mayorPct = mayor ? (mayor.pct || '0%') : '0%';
				var menorNom = menor ? menor.modelo.toUpperCase() : 'SIN DATOS';
				var menorPct = menor ? (menor.pct || '0%') : '0%';

				var htmlModelos = '';
				if (modelosTop5.length > 0) {
					modelosTop5.forEach(function (m) {
						var cant = parseInt(m.cantidad || 0, 10);
						var pctBar = Math.min(100, Math.round((cant / maxCant) * 100));
						htmlModelos += '<div class="ep-slide-mod-item">'
							+ '<span class="ep-slide-mod-nom" title="' + esc(m.modelo) + '">' + esc(m.modelo) + '</span>'
							+ '<div class="ep-slide-mod-track">'
							+ '<div class="ep-slide-mod-bar" style="width:' + pctBar + '%;"></div>'
							+ '</div>'
							+ '<span class="ep-slide-mod-qty">' + cant + '</span>'
							+ '</div>';
					});
				} else {
					htmlModelos = '<div style="font-size:8px;color:#94A3B8;padding:6px 0;">Sin modelos registrados</div>';
				}

				var vis = Math.max(1, parseInt(emb.visitaron || 0, 10));
				var v1 = parseInt(emb.visitaron || 0, 10);
				var v2 = parseInt(emb.interactuaron || 0, 10);
				var v3 = parseInt(emb.compraron || 0, 10);
				var maxH = 68;
				var h1 = maxH;
				var h2 = Math.max(6, Math.round(maxH * (v2 / vis)));
				var h3 = Math.max(6, Math.round(maxH * (v3 / vis)));

				var kpiInterLabel = esEvento ? 'CLIENTES FUERON AL EVENTO' : 'CLIENTES VISITARON LA TIENDA';
				var funnelLabel1 = esEvento ? 'FUERON AL EVENTO' : 'CLIENTES EN TIENDA';

				var htmlKpis = '';
				if (!esEvento) {
					htmlKpis += '<div class="ep-slide-kpi-card">'
						+ '<div class="ep-slide-kpi-tag">COBERTURA</div>'
						+ '<div class="ep-slide-kpi-big">' + (cob.pct || 0) + ' %</div>'
						+ '<div class="ep-slide-kpi-lines">'
						+ '<div>' + (cob.nacional || 0) + ' TIENDAS A NIVEL NACIONAL</div>'
						+ '<div>' + (cob.coberturadas || 0) + ' TIENDAS COBERTURADAS</div>'
						+ '</div>'
						+ '</div>';
				}
				htmlKpis += '<div class="ep-slide-kpi-card"' + (esEvento ? ' style="flex:1;"' : '') + '>'
					+ '<div class="ep-slide-kpi-tag">INTERACCIONES</div>'
					+ '<div class="ep-slide-kpi-big">' + (emb.tasa_interaccion_pct || 0) + ' %</div>'
					+ '<div class="ep-slide-kpi-lines">'
					+ '<div>' + (emb.visitaron || 0) + ' ' + kpiInterLabel + '</div>'
					+ '<div>' + (emb.interactuaron || 0) + ' CLIENTES ATENDIDOS</div>'
					+ '</div>'
					+ '</div>'
					+ '<div class="ep-slide-kpi-card"' + (esEvento ? ' style="flex:1;"' : '') + '>'
					+ '<div class="ep-slide-kpi-tag">VENTAS</div>'
					+ '<div class="ep-slide-kpi-big">' + (emb.tasa_conversion_pct || 0) + ' %</div>'
					+ '<div class="ep-slide-kpi-lines">'
					+ '<div>' + (emb.compraron || 0) + ' VENTAS EFECTIVAS</div>'
					+ '</div>'
					+ '</div>';

				var htmlSlide = '<div class="ep-slide-real-canvas">'
					+ '<h2 class="ep-slide-real-title">' + esc(slideTitle) + '</h2>'
					+ '<div class="ep-slide-real-grid">'
					+ armarColumnaPromotor(actNomEmb)
					+ '<div class="ep-slide-real-main">'
					+ '<div class="ep-slide-real-kpis">'
					+ htmlKpis
					+ '</div>'

					+ '<div class="ep-slide-real-bottom">'
					+ '<div class="ep-slide-subcol-funnel">'
					+ '<div class="ep-slide-sku-grid">'
					+ '<div class="ep-slide-sku-card">'
					+ '<span class="ep-slide-sku-pct">' + esc(mayorPct) + '</span>'
					+ '<strong class="ep-slide-sku-name">' + esc(mayorNom) + '</strong>'
					+ '<span class="ep-slide-sku-lbl">SKU CON MAYOR VENTA</span>'
					+ '</div>'
					+ '<div class="ep-slide-sku-card">'
					+ '<span class="ep-slide-sku-pct">' + esc(menorPct) + '</span>'
					+ '<strong class="ep-slide-sku-name">' + esc(menorNom) + '</strong>'
					+ '<span class="ep-slide-sku-lbl">SKU CON MENOR VENTA</span>'
					+ '</div>'
					+ '</div>'

					+ '<div class="ep-slide-embudo-card">'
					+ '<div class="ep-slide-embudo-chart">'
					+ '<div class="ep-slide-emb-col">'
					+ '<span class="ep-slide-emb-val">' + v1 + '</span>'
					+ '<div class="ep-slide-emb-bar bar-visitaron" style="height:' + h1 + 'px;"></div>'
					+ '</div>'
					+ '<div class="ep-slide-emb-col">'
					+ '<span class="ep-slide-emb-val">' + v2 + '</span>'
					+ '<div class="ep-slide-emb-bar bar-interactuaron" style="height:' + h2 + 'px;"></div>'
					+ '</div>'
					+ '<div class="ep-slide-emb-col">'
					+ '<span class="ep-slide-emb-val">' + v3 + '</span>'
					+ '<div class="ep-slide-emb-bar bar-compraron" style="height:' + h3 + 'px;"></div>'
					+ '</div>'
					+ '</div>'
					+ '<div class="ep-slide-embudo-axis"></div>'
					+ '<div class="ep-slide-embudo-labels">'
					+ '<span>' + esc(funnelLabel1) + '</span>'
					+ '<span>INTERACCIONES</span>'
					+ '<span>VENTAS</span>'
					+ '</div>'
					+ '</div>'
					+ '</div>'

					+ '<div class="ep-slide-subcol-detalle">'
					+ '<div class="ep-slide-detalle-card">'
					+ '<div class="ep-slide-card-header">DETALLE VENTAS</div>'
					+ '<div class="ep-slide-modelos-list">'
					+ htmlModelos
					+ '</div>'
					+ '</div>'

					+ '<div class="ep-slide-comentarios-card">'
					+ '<div class="ep-slide-card-header">COMENTARIOS</div>'
					+ '<div class="ep-slide-comentarios-list">'
					+ htmlComs
					+ '</div>'
					+ '</div>'
					+ '</div>'

					+ '</div>'
					+ '</div>'
					+ '</div>'
					+ '</div>';

				lbBody.innerHTML = htmlSlide;
			}
		}

		lightbox.classList.remove('hidden');
	}

	function cerrarLightbox() {
		if (lightbox) lightbox.classList.add('hidden');
		registroLightboxActual = null;
	}

	if (lbCerrar) lbCerrar.addEventListener('click', cerrarLightbox);
	if (lbCerrarBtn) lbCerrarBtn.addEventListener('click', cerrarLightbox);
	if (lbFondo) lbFondo.addEventListener('click', cerrarLightbox);
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && lightbox && !lightbox.classList.contains('hidden')) {
			cerrarLightbox();
		}
	});

	// ==================== PASO FINAL: NOMBRE, MES Y RANGO ====================
	var NOMBRES_MES = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
	function seleccionActual() { return Object.keys(vistaPrevia === 2 ? seleccionadosMap : seleccionadosCapMap).map(function (k) { return (vistaPrevia === 2 ? seleccionadosMap : seleccionadosCapMap)[k]; }); }

	function irAlPasoFinal() {
		if (vistaActual === 2 && !calBlob) {
			if (drop) drop.classList.add('ep-act-error');
			aviso('warning', 'Falta el calendario', 'Sube la foto del calendario de activaciones para continuar.');
			return;
		}
		vistaPrevia = vistaActual;
		var regs = seleccionActual();
		if (!regs.length) {
			aviso('warning', 'Falta seleccionar', 'Marca al menos un registro para el reporte.');
			return;
		}
		var fechas = regs.map(function (r) { return (r.fecha || '').substring(0, 10); }).filter(Boolean).sort();
		if (finalRango) finalRango.textContent = fechas.length ? (fechas[0] === fechas[fechas.length - 1] ? formatearFechaSlash(fechas[0]) : formatearFechaSlash(fechas[0]) + ' al ' + formatearFechaSlash(fechas[fechas.length - 1])) : '-';
		if (finalTotal) finalTotal.textContent = regs.length + (regs.length === 1 ? ' registro' : ' registros');
		if (inpMes && !inpMes.value && fechas.length) inpMes.value = fechas[fechas.length - 1].substring(0, 7);
		if (inpTitulo && !inpTitulo.value.trim()) {
			var label = ((selTipo && selTipo.dataset.label) || 'Reporte').toUpperCase();
			var m = inpMes && inpMes.value ? NOMBRES_MES[parseInt(inpMes.value.substring(5, 7), 10) - 1] + ' ' + inpMes.value.substring(0, 4) : '';
			inpTitulo.value = (label + ' ' + m).trim();
		}
		mostrarVista(4);
		if (inpTitulo) inpTitulo.focus();
	}

	// Nombre y mes son obligatorios para guardar.
	function datosFinalValidos() {
		if (!inpTitulo || !inpTitulo.value.trim()) {
			aviso('warning', 'Falta el nombre', 'Escribe el nombre con el que se descargará el reporte.');
			return false;
		}
		if (!inpMes || !/^\d{4}-\d{2}$/.test(inpMes.value)) {
			aviso('warning', 'Falta el mes', 'Elige el mes del reporte.');
			return false;
		}
		return true;
	}

	// ==================== GUARDAR Y DESCARGAR REPORTE ====================
	function descargarReporte(id) {
		if (window.Swal) Swal.fire({ title: 'Generando presentación', html: 'Esto puede tardar un momento según la cantidad de fotos...', allowOutsideClick: false, showConfirmButton: false, didOpen: function () { Swal.showLoading(); } });
		return fetch('getters/reporte_descargar.php?id=' + id)
			.then(function (res) {
				var tipo = res.headers.get('Content-Type') || '';
				if (tipo.indexOf('json') !== -1) return res.json().then(function (d) { throw new Error(d.error || 'No se pudo generar la presentación.'); });
				if (!res.ok || tipo.indexOf('presentationml') === -1) throw new Error('El servidor no pudo generar la presentación (código ' + res.status + ').');
				var cd = res.headers.get('Content-Disposition') || '';
				var m = cd.match(/filename="([^"]+)"/);
				return res.blob().then(function (b) { return { blob: b, nombre: m ? m[1] : 'Reporte.pptx' }; });
			})
			.then(function (r) {
				var a = document.createElement('a');
				a.href = URL.createObjectURL(r.blob);
				a.download = r.nombre;
				document.body.appendChild(a);
				a.click();
				a.remove();
				if (window.Swal) Swal.close();
			})
			.catch(function (e) {
				if (window.Swal) Swal.close();
				aviso('error', 'No se pudo descargar', esc(e.message));
			});
	}

	function guardarReporteActivaciones() {
		var ids = Object.keys(seleccionadosMap).map(Number);
		if (!ids.length) {
			aviso('warning', 'Falta seleccionar', 'Marca al menos un registro para el reporte.');
			return;
		}

		var fd = new FormData();
		fd.append('tipo', selTipo ? selTipo.value : 'activaciones');
		fd.append('mes', inpMes ? inpMes.value : '');
		fd.append('nombre_actividad', (selTipo && selTipo.dataset.label) || '');
		fd.append('titulo', inpTitulo ? inpTitulo.value.trim() : '');
		fd.append('programadas', inpProg ? inpProg.value : '');
		fd.append('comentarios', inpComentarios ? inpComentarios.value : '');
		fd.append('registros', JSON.stringify(ids));
		if (calBlob) {
			fd.append('calendario', calBlob, 'calendario.jpg');
		}

		if (btnGuardarAct) btnGuardarAct.disabled = true;
		if (window.Swal) Swal.fire({ title: 'Guardando reporte', html: 'Consolidando registros y evidencias...', allowOutsideClick: false, showConfirmButton: false, didOpen: function () { Swal.showLoading(); } });

		fetch('getters/reporte_guardar.php', { method: 'POST', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				if (!d.success) {
					if (window.Swal) Swal.close();
					if (btnGuardarAct) btnGuardarAct.disabled = false;
					return aviso('error', 'No se pudo guardar', esc(d.error || 'Intenta de nuevo.')).then(function () {
						if (d.redirect) window.location.href = d.redirect;
					});
				}
				cerrar();
				return descargarReporte(d.id).then(function () {
					window.location.reload();
				});
			})
			.catch(function () {
				if (window.Swal) Swal.close();
				if (btnGuardarAct) btnGuardarAct.disabled = false;
				aviso('error', 'Sin conexión', 'No se pudo guardar el reporte. Intenta de nuevo.');
			});
	}

	function guardarReporteCapacitaciones() {
		var ids = Object.keys(seleccionadosCapMap).map(Number);
		if (!ids.length) {
			aviso('warning', 'Falta seleccionar', 'Marca al menos un registro para el reporte.');
			return;
		}

		var tipoEnvio = (selTipo && selTipo.value) || 'capacitaciones';
		var labelEnvio = (selTipo && selTipo.dataset.label) || 'Actividad';
		var fd = new FormData();
		fd.append('tipo', tipoEnvio);
		fd.append('mes', inpMes ? inpMes.value : '');
		fd.append('nombre_actividad', (selTipo && selTipo.dataset.label) || '');
		fd.append('titulo', inpTitulo ? inpTitulo.value.trim() : '');
		fd.append('registros', JSON.stringify(ids));

		if (btnGuardarAct) btnGuardarAct.disabled = true;
		if (window.Swal) Swal.fire({ title: 'Guardando reporte', html: 'Consolidando ' + esc(labelEnvio) + ' seleccionadas...', allowOutsideClick: false, showConfirmButton: false, didOpen: function () { Swal.showLoading(); } });

		fetch('getters/reporte_guardar.php', { method: 'POST', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				if (!d.success) {
					if (window.Swal) Swal.close();
					if (btnGuardarAct) btnGuardarAct.disabled = false;
					return aviso('error', 'No se pudo guardar', esc(d.error || 'Intenta de nuevo.')).then(function () {
						if (d.redirect) window.location.href = d.redirect;
					});
				}
				cerrar();
				return descargarReporte(d.id).then(function () {
					window.location.reload();
				});
			})
			.catch(function () {
				if (window.Swal) Swal.close();
				if (btnGuardarAct) btnGuardarAct.disabled = false;
				aviso('error', 'Sin conexión', 'No se pudo guardar el reporte. Intenta de nuevo.');
			});
	}

	if (btnGuardarAct) {
		btnGuardarAct.addEventListener('click', function () {
			if (vistaActual !== 4 || !datosFinalValidos()) return;
			if (vistaPrevia === 2) {
				guardarReporteActivaciones();
			} else {
				guardarReporteCapacitaciones();
			}
		});
	}

	// ==================== LISTADO HISTÓRICO: DESCARGAR Y QUITAR ====================
	root.addEventListener('click', function (ev) {
		var d = ev.target.closest('.ep-rp-descargar');
		if (d) {
			descargarReporte(d.dataset.id);
			return;
		}
		var q = ev.target.closest('.ep-rp-quitar');
		if (!q) return;
		var confirmar = window.Swal
			? Swal.fire({ icon: 'question', title: 'Quitar reporte', text: 'Se quita del histórico y sus registros quedan libres para armar otro reporte.', showCancelButton: true, confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar', confirmButtonColor: '#513487' }).then(function (r) { return r.isConfirmed; })
			: Promise.resolve(confirm('¿Quitar este reporte del histórico?'));
		confirmar.then(function (ok) {
			if (!ok) return;
			var fd = new FormData();
			fd.append('id', q.dataset.id);
			fetch('getters/reporte_eliminar.php', { method: 'POST', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (d2) {
					if (d2.success) window.location.reload();
					else aviso('error', 'No se pudo quitar', esc(d2.error || ''));
				});
		});
	});

})();

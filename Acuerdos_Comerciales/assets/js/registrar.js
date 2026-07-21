(function () {
	var allMonthsShort = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

	// Catálogo (Segmento -> Categoría -> [Marcas]) y Distribuidores se cargan
	// en vivo desde la base (ver getters/acuerdo_catalogo.php y
	// getters/acuerdo_distribuidores.php) — nunca hardcodeados aquí.
	var catalogo = { segmentos: {}, marcasPercha: [] };
	var distribuidores = [];

	var selectedStart = 0;
	var selectedEnd = 2;
	var activeMonthsIndices = [0, 1, 2];
	var acuerdoId = null;
	var documentoNo = null;

	var formatCurr = function (val) {
		return (isNaN(val) ? 0 : val).toLocaleString('en-US', { style: 'currency', currency: 'USD' });
	};

	// ---------- Selectores ----------
	var distribuidorSelect = document.getElementById('ac-distribuidor');
	var distribuidorSearch = document.getElementById('ac-distribuidor-search');
	var distribuidorPanel  = document.getElementById('ac-distribuidor-panel');
	var distribuidorCombo  = document.getElementById('ac-distribuidor-combo');
	var localidadEl        = document.getElementById('ac-localidad');
	var anioSelect          = document.getElementById('ac-anio');
	var monthsDisplay       = document.getElementById('ac-months-display');
	var rangeText           = document.getElementById('ac-selected-range-text');
	var pickerTrigger       = document.getElementById('ac-month-picker-trigger');
	var pickerPopover       = document.getElementById('ac-month-picker-popover');
	var monthGrid           = document.getElementById('ac-month-grid');
	var formMsg             = document.getElementById('ac-form-msg');

	var purchaseHead = document.getElementById('ac-purchase-head');
	var purchaseBody = document.getElementById('ac-purchase-body');
	var purchaseFoot = document.getElementById('ac-purchase-foot');
	var cabecerasHead = document.getElementById('ac-cabeceras-head');
	var cabecerasBody = document.getElementById('ac-cabeceras-body');
	var rumasHead = document.getElementById('ac-rumas-head');
	var rumasBody = document.getElementById('ac-rumas-body');
	var rumasLegendBody = document.getElementById('ac-rumas-legend-body');
	var perchasHead = document.getElementById('ac-perchas-head');
	var perchasBody = document.getElementById('ac-perchas-body');

	function mostrarMensaje(texto, ok) {
		formMsg.textContent = texto;
		formMsg.className = 'ac-form-msg ' + (ok ? 'ac-form-msg-success' : 'ac-form-msg-error');
	}

	// ---------- Carga inicial ----------
	function cargarDatosIniciales() {
		Promise.all([
			fetch('getters/acuerdo_catalogo.php').then(function (r) { return r.json(); }),
			fetch('getters/acuerdo_distribuidores.php').then(function (r) { return r.json(); })
		]).then(function (resultados) {
			var catRes = resultados[0];
			var distRes = resultados[1];

			if (catRes.ok) {
				catalogo.segmentos = catRes.segmentos;
				catalogo.marcasPercha = catRes.marcas_percha;
			}
			if (distRes.ok) {
				distribuidores = distRes.distribuidores;
			}

			buildMonthGrid();
			updatePickerUI();
			syncTables();
		}).catch(function () {
			distribuidorSearch.placeholder = 'Error al cargar';
			mostrarMensaje('No se pudo cargar el catálogo de productos ni distribuidores. Recarga la página.', false);
		});
	}

	// Localidad nunca se guarda (regla de negocio): siempre se deriva de
	// province + " - " + city del distribuidor elegido, en el momento de
	// mostrarla — nunca de un valor tipeado por el usuario.
	function formatLocalidad(d) {
		if (!d) return '—';
		var partes = [d.province, d.city].filter(function (p) { return p; });
		return partes.length ? partes.join(' - ') : '—';
	}

	function distribuidorSeleccionado() {
		return distribuidores.filter(function (x) { return x.pos_id === distribuidorSelect.value; })[0];
	}

	// ---------- Combobox de Distribuidor (buscador sobre repositorio_locales_dtt2.pos_name) ----------
	function seleccionarDistribuidor(d) {
		distribuidorSelect.value = d.pos_id;
		distribuidorSearch.value = d.pos_name + ' (' + d.pos_id + ')';
		localidadEl.textContent = formatLocalidad(d);
		cerrarPanelDistribuidor();
	}

	function renderPanelDistribuidor(filtro) {
		var q = (filtro || '').toLowerCase().trim();
		var coincidencias = distribuidores.filter(function (d) {
			return !q || d.pos_name.toLowerCase().indexOf(q) !== -1 || d.pos_id.toLowerCase().indexOf(q) !== -1;
		}).slice(0, 60);

		if (!coincidencias.length) {
			distribuidorPanel.innerHTML = '<div class="ac-combo-empty">Sin coincidencias</div>';
		} else {
			distribuidorPanel.innerHTML = coincidencias.map(function (d) {
				return '<div class="ac-combo-option" data-pos-id="' + d.pos_id + '">' + d.pos_name + ' <span class="ac-combo-option-hint">(' + d.pos_id + ')</span></div>';
			}).join('');
		}
		distribuidorPanel.classList.remove('hidden');

		Array.prototype.forEach.call(distribuidorPanel.querySelectorAll('.ac-combo-option'), function (opt) {
			opt.addEventListener('mousedown', function (e) {
				e.preventDefault();
				var d = distribuidores.filter(function (x) { return x.pos_id === opt.dataset.posId; })[0];
				if (d) seleccionarDistribuidor(d);
			});
		});
	}

	function cerrarPanelDistribuidor() {
		distribuidorPanel.classList.add('hidden');
	}

	distribuidorSearch.addEventListener('input', function () {
		if (distribuidorSelect.value) {
			distribuidorSelect.value = '';
			localidadEl.textContent = '—';
		}
		renderPanelDistribuidor(distribuidorSearch.value);
	});
	distribuidorSearch.addEventListener('focus', function () { renderPanelDistribuidor(distribuidorSearch.value); });
	document.addEventListener('click', function (e) {
		if (!distribuidorCombo.contains(e.target)) cerrarPanelDistribuidor();
	});

	// ---------- Picker de meses ----------
	function buildMonthGrid() {
		var html = '';
		allMonthsShort.forEach(function (m, idx) {
			html += '<button type="button" class="ac-month-btn" data-month="' + idx + '">' + m + '</button>';
		});
		monthGrid.innerHTML = html;

		Array.prototype.forEach.call(monthGrid.querySelectorAll('.ac-month-btn'), function (btn) {
			btn.addEventListener('click', function () {
				var index = parseInt(btn.dataset.month, 10);
				if (selectedStart === null || (selectedStart !== null && selectedEnd !== null)) {
					selectedStart = index;
					selectedEnd = null;
				} else if (index < selectedStart) {
					selectedEnd = selectedStart;
					selectedStart = index;
				} else {
					selectedEnd = index;
				}
				updatePickerUI();
				if (selectedStart !== null && selectedEnd !== null) {
					activeMonthsIndices = [];
					for (var i = selectedStart; i <= selectedEnd; i++) activeMonthsIndices.push(i);
					syncTables();
				}
			});
		});
	}

	pickerTrigger.addEventListener('click', function (e) {
		e.stopPropagation();
		pickerPopover.classList.toggle('hidden');
	});
	document.addEventListener('click', function (e) {
		if (!pickerPopover.contains(e.target) && e.target !== pickerTrigger) {
			pickerPopover.classList.add('hidden');
		}
	});
	document.getElementById('ac-clear-range').addEventListener('click', function () {
		selectedStart = null;
		selectedEnd = null;
		activeMonthsIndices = [];
		updatePickerUI();
	});

	function updatePickerUI() {
		Array.prototype.forEach.call(monthGrid.querySelectorAll('.ac-month-btn'), function (btn) {
			var idx = parseInt(btn.dataset.month, 10);
			btn.classList.remove('selected', 'in-range');
			if (idx === selectedStart || idx === selectedEnd) btn.classList.add('selected');
			if (selectedStart !== null && selectedEnd !== null && idx > selectedStart && idx < selectedEnd) btn.classList.add('in-range');
		});

		if (selectedStart !== null && selectedEnd !== null) {
			rangeText.textContent = allMonthsShort[selectedStart] + ' - ' + allMonthsShort[selectedEnd];
			monthsDisplay.textContent = activeMonthsIndices.map(function (i) { return allMonthsShort[i]; }).join(', ');
		} else if (selectedStart !== null) {
			rangeText.textContent = 'Desde ' + allMonthsShort[selectedStart] + '...';
			monthsDisplay.textContent = '...';
		} else {
			rangeText.textContent = 'Seleccionar período';
			monthsDisplay.textContent = 'Sin selección';
		}
	}

	// ---------- Construcción de tablas ----------
	function syncTables() {
		var months = activeMonthsIndices.map(function (i) { return allMonthsShort[i]; });
		var count = months.length;

		purchaseHead.innerHTML =
			'<tr><th class="ac-sticky-col">Segmento</th><th class="ac-sticky-col ac-sticky-col-2">Categoría</th><th class="ac-sticky-col ac-sticky-col-3">Marca</th>' +
			months.map(function (m) { return '<th class="ac-text-right">' + m + ' ($)</th>'; }).join('') +
			'<th class="ac-text-right ac-col-highlight">Total Período</th><th class="ac-text-right ac-col-highlight">Rebate %</th><th class="ac-text-right ac-col-highlight">Valor Estimado</th><th></th></tr>';

		cabecerasHead.innerHTML =
			'<tr><th rowspan="2" class="ac-sticky-col">Segmento</th><th rowspan="2" class="ac-sticky-col ac-sticky-col-2">Categoría</th><th rowspan="2" class="ac-sticky-col ac-sticky-col-3">Marca</th>' +
			'<th colspan="' + count + '">Cabecera Pago x Mes</th><th rowspan="2">Pago Total</th><th rowspan="2"></th></tr>' +
			'<tr>' + months.map(function (m) { return '<th>' + m + '</th>'; }).join('') + '</tr>';

		// Rumas visualmente tiene una columna por mes (igual que Cabeceras/Perchas),
		// pero las 'N' celdas están espejadas al mismo valor: el negocio exige un
		// único "valor_mensual_unico" que se repite en todo el periodo, no un
		// valor distinto por mes — ver CLAUDE.md.
		rumasHead.innerHTML =
			'<tr><th rowspan="2" class="ac-sticky-col">Segmento</th><th rowspan="2" class="ac-sticky-col ac-sticky-col-2">Categoría</th><th rowspan="2" class="ac-sticky-col ac-sticky-col-3">Marca</th>' +
			'<th colspan="' + count + '">Valor Ruma x Mes (mismo valor todo el periodo)</th><th rowspan="2">Pago Total</th><th rowspan="2"></th></tr>' +
			'<tr>' + months.map(function (m) { return '<th>' + m + '</th>'; }).join('') + '</tr>';

		perchasHead.innerHTML =
			'<tr><th rowspan="3" class="ac-sticky-col">Marca Perchas</th><th rowspan="1">Participación</th><th rowspan="1">Cantidad</th>' +
			'<th colspan="' + (count + 1) + '">Pago Mensual</th><th rowspan="3"></th></tr>' +
			'<tr><th colspan="' + (count + 2) + '">Pago x Mes x Percha ($)</th></tr>' +
			'<tr><th>% de Peso</th><th>Max Percha</th>' + months.map(function (m) { return '<th>' + m + '</th>'; }).join('') + '<th>Pago Total</th></tr>';

		purchaseBody.innerHTML = '';
		cabecerasBody.innerHTML = '';
		rumasBody.innerHTML = '';
		perchasBody.innerHTML = '';

		addPurchaseRow();
		addCabeceraRow();
		addRumaRow();
		addPerchaRow();
		updateGrandTotals();
	}

	function segmentoOptions() {
		return '<option value="">Segmento...</option>' + Object.keys(catalogo.segmentos).map(function (s) {
			return '<option value="' + s + '">' + s + '</option>';
		}).join('');
	}

	function bindCascada(segSelect, catSelect, marcaSelect) {
		segSelect.addEventListener('change', function () {
			catSelect.innerHTML = '<option value="">Categoría...</option>';
			marcaSelect.innerHTML = '<option value="">Marca...</option>';
			marcaSelect.disabled = true;
			if (segSelect.value && catalogo.segmentos[segSelect.value]) {
				catSelect.disabled = false;
				Object.keys(catalogo.segmentos[segSelect.value]).forEach(function (c) {
					var opt = document.createElement('option');
					opt.value = c; opt.textContent = c;
					catSelect.appendChild(opt);
				});
			} else {
				catSelect.disabled = true;
			}
		});
		catSelect.addEventListener('change', function () {
			marcaSelect.innerHTML = '<option value="">Marca...</option>';
			var marcas = (catalogo.segmentos[segSelect.value] || {})[catSelect.value] || [];
			if (catSelect.value && marcas.length) {
				marcaSelect.disabled = false;
				marcas.forEach(function (m) {
					var opt = document.createElement('option');
					opt.value = m; opt.textContent = m;
					marcaSelect.appendChild(opt);
				});
			} else {
				marcaSelect.disabled = true;
			}
		});
	}

	// ---------- Meta de Compras ----------
	function addPurchaseRow() {
		var tr = document.createElement('tr');
		var html =
			'<td class="ac-sticky-col"><select class="ac-select ac-mini-select seg-select">' + segmentoOptions() + '</select></td>' +
			'<td class="ac-sticky-col ac-sticky-col-2"><select class="ac-select ac-mini-select cat-select" disabled><option value="">Categoría...</option></select></td>' +
			'<td class="ac-sticky-col ac-sticky-col-3"><select class="ac-select ac-mini-select marca-select" disabled><option value="">Marca...</option></select></td>';
		activeMonthsIndices.forEach(function () {
			html += '<td class="ac-text-right"><input type="number" step="0.01" class="ac-input ac-mini-input month-input" value="0"></td>';
		});
		html +=
			'<td class="ac-text-right ac-col-highlight ac-tabular total-cell">$0.00</td>' +
			'<td class="ac-text-right ac-col-highlight"><input type="number" step="0.01" min="0" class="ac-input ac-mini-input ac-rebate-input" value="0"></td>' +
			'<td class="ac-text-right ac-col-highlight ac-tabular est-cell">$0.00</td>' +
			'<td class="ac-text-center"><button type="button" class="ac-icon-btn ac-remove-row"><span class="material-symbols-outlined">delete</span></button></td>';
		tr.innerHTML = html;
		purchaseBody.appendChild(tr);

		bindCascada(tr.querySelector('.seg-select'), tr.querySelector('.cat-select'), tr.querySelector('.marca-select'));

		var recalc = function () { updatePurchaseRow(tr); };
		tr.querySelectorAll('.month-input, .ac-rebate-input').forEach(function (i) { i.addEventListener('input', recalc); });
		tr.querySelector('.ac-remove-row').addEventListener('click', function () { tr.remove(); updateGrandTotals(); });
	}

	function updatePurchaseRow(row) {
		var inputs = Array.prototype.map.call(row.querySelectorAll('.month-input'), function (i) { return parseFloat(i.value) || 0; });
		var total = inputs.reduce(function (a, b) { return a + b; }, 0);
		var rebatePct = (parseFloat(row.querySelector('.ac-rebate-input').value) || 0) / 100;
		row.querySelector('.total-cell').textContent = formatCurr(total);
		row.querySelector('.est-cell').textContent = formatCurr(total * (1 + rebatePct));
		updateGrandTotals();
	}

	function updateGrandTotals() {
		var rows = Array.prototype.slice.call(purchaseBody.querySelectorAll('tr'));
		var monthSums = new Array(activeMonthsIndices.length).fill(0);
		var grandTotal = 0, grandEst = 0;

		rows.forEach(function (r) {
			var inputs = r.querySelectorAll('.month-input');
			var total = parseFloat(r.querySelector('.total-cell').textContent.replace(/[$,]/g, '')) || 0;
			var est = parseFloat(r.querySelector('.est-cell').textContent.replace(/[$,]/g, '')) || 0;
			Array.prototype.forEach.call(inputs, function (input, idx) { monthSums[idx] += parseFloat(input.value) || 0; });
			grandTotal += total; grandEst += est;
		});

		purchaseFoot.innerHTML =
			'<tr class="ac-totales-row"><td class="ac-sticky-col" colspan="3">Totales</td>' +
			monthSums.map(function (s) { return '<td class="ac-text-right ac-tabular">' + formatCurr(s) + '</td>'; }).join('') +
			'<td class="ac-text-right ac-tabular">' + formatCurr(grandTotal) + '</td>' +
			'<td class="ac-text-right">—</td>' +
			'<td class="ac-text-right ac-tabular">' + formatCurr(grandEst) + '</td><td></td></tr>';
	}

	// ---------- Cabeceras ----------
	function addCabeceraRow() {
		var tr = document.createElement('tr');
		var html =
			'<td class="ac-sticky-col"><select class="ac-select ac-mini-select seg-select">' + segmentoOptions() + '</select></td>' +
			'<td class="ac-sticky-col ac-sticky-col-2"><select class="ac-select ac-mini-select cat-select" disabled><option value="">Categoría...</option></select></td>' +
			'<td class="ac-sticky-col ac-sticky-col-3"><select class="ac-select ac-mini-select marca-select" disabled><option value="">Marca...</option></select></td>';
		activeMonthsIndices.forEach(function () {
			html += '<td><input type="number" step="0.01" class="ac-input ac-mini-input v-val" value="0"></td>';
		});
		html += '<td class="ac-tabular v-tot">$0.00</td><td class="ac-text-center"><button type="button" class="ac-icon-btn ac-remove-row"><span class="material-symbols-outlined">delete</span></button></td>';
		tr.innerHTML = html;
		cabecerasBody.appendChild(tr);
		bindCascada(tr.querySelector('.seg-select'), tr.querySelector('.cat-select'), tr.querySelector('.marca-select'));
		attachVisListeners(tr);
	}

	// ---------- Rumas ----------
	// Muestra una celda por mes (igual look que Cabeceras/Perchas), pero las
	// 'N' celdas quedan espejadas al mismo número — el negocio exige un único
	// "valor_mensual_unico" repetido en todo el periodo, no un valor por mes.
	function addRumaRow() {
		var tr = document.createElement('tr');
		var html =
			'<td class="ac-sticky-col"><select class="ac-select ac-mini-select seg-select">' + segmentoOptions() + '</select></td>' +
			'<td class="ac-sticky-col ac-sticky-col-2"><select class="ac-select ac-mini-select cat-select" disabled><option value="">Categoría...</option></select></td>' +
			'<td class="ac-sticky-col ac-sticky-col-3"><select class="ac-select ac-mini-select marca-select" disabled><option value="">Marca...</option></select></td>';
		activeMonthsIndices.forEach(function () {
			html += '<td><input type="number" step="0.01" class="ac-input ac-mini-input v-val-repetido" value="0"></td>';
		});
		html += '<td class="ac-tabular v-tot">$0.00</td><td class="ac-text-center"><button type="button" class="ac-icon-btn ac-remove-row"><span class="material-symbols-outlined">delete</span></button></td>';
		tr.innerHTML = html;
		rumasBody.appendChild(tr);
		bindCascada(tr.querySelector('.seg-select'), tr.querySelector('.cat-select'), tr.querySelector('.marca-select'));

		var repetidos = tr.querySelectorAll('.v-val-repetido');
		var recalc = function (origen) {
			var v = parseFloat(origen ? origen.value : repetidos[0].value) || 0;
			Array.prototype.forEach.call(repetidos, function (input) { input.value = v; });
			tr.querySelector('.v-tot').textContent = formatCurr(v * activeMonthsIndices.length);
			updateRumaLegend();
		};
		repetidos.forEach(function (input) { input.addEventListener('input', function () { recalc(input); }); });
		tr.querySelectorAll('.seg-select, .cat-select, .marca-select').forEach(function (i) { i.addEventListener('change', function () { recalc(); }); });
		tr.querySelector('.ac-remove-row').addEventListener('click', function () { tr.remove(); updateRumaLegend(); });
	}

	function updateRumaLegend() {
		var rows = Array.prototype.slice.call(rumasBody.querySelectorAll('tr'));
		var brands = {};
		rows.forEach(function (r) {
			var m = r.querySelector('.marca-select').value;
			if (!m) return;
			var v = parseFloat(r.querySelector('.v-val-repetido').value) || 0;
			brands[m] = Math.max(brands[m] || 0, v);
		});
		var entries = Object.keys(brands);
		rumasLegendBody.innerHTML = entries.length
			? entries.map(function (b) { return '<tr><td>' + b + '</td><td class="ac-text-right ac-tabular">' + formatCurr(brands[b]) + '</td></tr>'; }).join('')
			: '<tr><td colspan="2" class="ac-table-empty">Sin datos</td></tr>';
	}

	// ---------- Perchas ----------
	function addPerchaRow() {
		var tr = document.createElement('tr');
		var html =
			'<td class="ac-sticky-col"><select class="ac-select ac-mini-select marca-select"><option value="">Marca...</option>' +
			catalogo.marcasPercha.map(function (m) { return '<option value="' + m + '">' + m + '</option>'; }).join('') + '</select></td>' +
			'<td><input type="text" class="ac-input ac-mini-input v-participacion" value="50%"></td>' +
			'<td><input type="number" min="0" max="5" class="ac-input ac-mini-input v-cantidad" value="1"></td>';
		activeMonthsIndices.forEach(function () {
			html += '<td><input type="number" step="0.01" class="ac-input ac-mini-input v-val" value="0"></td>';
		});
		html += '<td class="ac-tabular v-tot">$0.00</td><td class="ac-text-center"><button type="button" class="ac-icon-btn ac-remove-row"><span class="material-symbols-outlined">delete</span></button></td>';
		tr.innerHTML = html;
		perchasBody.appendChild(tr);
		attachVisListeners(tr);

		tr.querySelector('.v-cantidad').addEventListener('change', function () {
			var val = parseInt(tr.querySelector('.v-cantidad').value, 10) || 0;
			if (val > 5) { tr.querySelector('.v-cantidad').value = 5; mostrarMensaje('El máximo de perchas por marca es 5.', false); }
		});
	}

	function attachVisListeners(row) {
		var recalc = function () {
			var vals = Array.prototype.map.call(row.querySelectorAll('.v-val'), function (v) { return parseFloat(v.value) || 0; });
			row.querySelector('.v-tot').textContent = formatCurr(vals.reduce(function (a, b) { return a + b; }, 0));
		};
		row.querySelectorAll('input, select').forEach(function (i) { i.addEventListener('input', recalc); });
		row.querySelector('.ac-remove-row').addEventListener('click', function () { row.remove(); });
	}

	// ---------- Recolección de datos para guardar ----------
	function recolectarLineas() {
		var metaCompra = Array.prototype.map.call(purchaseBody.querySelectorAll('tr'), function (r) {
			return {
				segmento: r.querySelector('.seg-select').value,
				categoria: r.querySelector('.cat-select').value,
				marca: r.querySelector('.marca-select').value,
				rebate_pct: (parseFloat(r.querySelector('.ac-rebate-input').value) || 0) / 100,
				valores: Array.prototype.map.call(r.querySelectorAll('.month-input'), function (i) { return parseFloat(i.value) || 0; })
			};
		});

		var cabecera = Array.prototype.map.call(cabecerasBody.querySelectorAll('tr'), function (r) {
			return {
				segmento: r.querySelector('.seg-select').value,
				categoria: r.querySelector('.cat-select').value,
				marca: r.querySelector('.marca-select').value,
				valores: Array.prototype.map.call(r.querySelectorAll('.v-val'), function (i) { return parseFloat(i.value) || 0; })
			};
		});

		var ruma = Array.prototype.map.call(rumasBody.querySelectorAll('tr'), function (r) {
			// Las celdas .v-val-repetido están todas espejadas al mismo valor
			// (ver addRumaRow) — cualquiera de ellas sirve como fuente única.
			var repetidos = r.querySelectorAll('.v-val-repetido');
			return {
				segmento: r.querySelector('.seg-select').value,
				categoria: r.querySelector('.cat-select').value,
				marca: r.querySelector('.marca-select').value,
				valor_mensual_unico: repetidos.length ? (parseFloat(repetidos[0].value) || 0) : 0
			};
		});

		var percha = Array.prototype.map.call(perchasBody.querySelectorAll('tr'), function (r) {
			return {
				marca: r.querySelector('.marca-select').value,
				cantidad_max_percha: parseInt(r.querySelector('.v-cantidad').value, 10) || 0,
				precio_percha: 40,
				valores: Array.prototype.map.call(r.querySelectorAll('.v-val'), function (i) { return parseFloat(i.value) || 0; })
			};
		});

		return { meta_compra: metaCompra, cabecera: cabecera, ruma: ruma, percha: percha };
	}

	function validarCabecera() {
		if (!distribuidorSelect.value) { mostrarMensaje('Selecciona un Distribuidor.', false); return false; }
		if (selectedStart === null || selectedEnd === null) { mostrarMensaje('Selecciona el Periodo del Acuerdo.', false); return false; }
		return true;
	}

	function guardarAcuerdo(estado, onOk) {
		if (!validarCabecera()) return;

		var payload = {
			acuerdo_id: acuerdoId,
			pos_id: distribuidorSelect.value,
			anio: parseInt(anioSelect.value, 10),
			mes_inicio: selectedStart,
			mes_fin: selectedEnd,
			estado: estado,
			lineas: recolectarLineas()
		};

		fetch('getters/guardar_acuerdo.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload)
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				mostrarMensaje(data.message, data.ok);
				if (data.ok) {
					acuerdoId = data.acuerdo_id;
					documentoNo = data.documento_no;
					if (onOk) onOk();
				}
			})
			.catch(function () { mostrarMensaje('Error de conexión. Intenta nuevamente.', false); });
	}

	document.getElementById('ac-guardar-borrador').addEventListener('click', function () {
		guardarAcuerdo('borrador');
	});

	document.getElementById('ac-finalizar-enviar').addEventListener('click', function () {
		guardarAcuerdo('enviado', mostrarPreview);
	});

	document.getElementById('ac-generar-acta').addEventListener('click', function () {
		guardarAcuerdo('generado', mostrarPreview);
	});

	// ---------- Preview / Acta ----------
	function mostrarPreview() {
		var d = distribuidorSeleccionado();

		document.getElementById('ac-preview-documento-no').textContent = documentoNo || '—';
		document.getElementById('ac-preview-distribuidor').textContent = d ? d.pos_name : '—';
		document.getElementById('ac-preview-dist-text').textContent = d ? d.pos_name : '—';
		document.getElementById('ac-preview-localidad').textContent = formatLocalidad(d);
		document.getElementById('ac-preview-fecha').textContent = new Date().toLocaleDateString();
		document.getElementById('ac-preview-periodo').textContent = rangeText.textContent + ' ' + anioSelect.value;

		var metasHtml = '<h2 class="ac-acuerdo-canvas-subtitle">1. Meta de Compras en Dólares</h2>' +
			'<table class="ac-table ac-table-bordered ac-table-print"><thead><tr><th>Segmento</th><th>Categoría</th><th>Marca</th>' +
			activeMonthsIndices.map(function (i) { return '<th class="ac-text-right">' + allMonthsShort[i] + '</th>'; }).join('') +
			'<th class="ac-text-right">Total</th><th class="ac-text-right">Valor Est.</th></tr></thead><tbody>' +
			Array.prototype.map.call(purchaseBody.querySelectorAll('tr'), function (r) {
				var s = r.querySelector('.seg-select').value, c = r.querySelector('.cat-select').value, m = r.querySelector('.marca-select').value;
				if (!s || !c || !m) return '';
				return '<tr><td>' + s + '</td><td>' + c + '</td><td>' + m + '</td>' +
					Array.prototype.map.call(r.querySelectorAll('.month-input'), function (i) { return '<td class="ac-text-right">' + formatCurr(parseFloat(i.value) || 0) + '</td>'; }).join('') +
					'<td class="ac-text-right">' + r.querySelector('.total-cell').textContent + '</td>' +
					'<td class="ac-text-right">' + r.querySelector('.est-cell').textContent + '</td></tr>';
			}).join('') + '</tbody></table>';
		document.getElementById('ac-preview-metas-section').innerHTML = metasHtml;

		function tablaVisibilidad(titulo, body, cols) {
			var filas = Array.prototype.map.call(body.querySelectorAll('tr'), function (r) {
				var celdas = cols.map(function (c) { return '<td>' + r.querySelector(c.sel).value + '</td>'; }).join('');
				var tot = r.querySelector('.v-tot') ? r.querySelector('.v-tot').textContent : '';
				return (r.querySelector('.marca-select') && r.querySelector('.marca-select').value) ? '<tr>' + celdas + '<td class="ac-text-right">' + tot + '</td></tr>' : '';
			}).join('');
			return '<h2 class="ac-acuerdo-canvas-subtitle">' + titulo + '</h2><table class="ac-table ac-table-bordered ac-table-print"><thead><tr>' +
				cols.map(function (c) { return '<th>' + c.label + '</th>'; }).join('') + '<th class="ac-text-right">Pago Total</th></tr></thead><tbody>' + filas + '</tbody></table>';
		}

		var visHtml = '';
		visHtml += tablaVisibilidad('3.a. Extravisibilidad: Cabeceras', cabecerasBody, [
			{ sel: '.seg-select', label: 'Segmento' }, { sel: '.cat-select', label: 'Categoría' }, { sel: '.marca-select', label: 'Marca' }
		]);
		visHtml += tablaVisibilidad('3.b. Espacio: Rumas', rumasBody, [
			{ sel: '.seg-select', label: 'Segmento' }, { sel: '.cat-select', label: 'Categoría' }, { sel: '.marca-select', label: 'Marca' }
		]);
		visHtml += tablaVisibilidad('3.c. Espacio: Perchas', perchasBody, [
			{ sel: '.marca-select', label: 'Marca' }
		]);
		document.getElementById('ac-preview-visibility-sections').innerHTML = visHtml;

		document.getElementById('ac-preview-page').classList.remove('hidden');
		document.querySelector('.ac-acuerdo').classList.add('hidden');
		window.scrollTo(0, 0);
	}

	document.getElementById('ac-back-to-form').addEventListener('click', function () {
		document.getElementById('ac-preview-page').classList.add('hidden');
		document.querySelector('.ac-acuerdo').classList.remove('hidden');
	});

	document.getElementById('ac-add-purchase-row').addEventListener('click', addPurchaseRow);
	document.getElementById('ac-add-cabecera-row').addEventListener('click', addCabeceraRow);
	document.getElementById('ac-add-ruma-row').addEventListener('click', addRumaRow);
	document.getElementById('ac-add-percha-row').addEventListener('click', addPerchaRow);

	cargarDatosIniciales();
})();

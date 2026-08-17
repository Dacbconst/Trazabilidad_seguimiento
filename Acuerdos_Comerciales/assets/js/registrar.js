(function () {
	var allMonthsShort = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

	// Catálogo (Segmento -> Categoría -> [Marcas]) y Distribuidores se cargan
	// en vivo desde la base (ver getters/acuerdo_catalogo.php y
	// getters/acuerdo_distribuidores.php) — nunca hardcodeados aquí.
	var catalogo = { segmentos: {}, marcasPercha: [], sectores: {} };
	// canal/empresas/clientes vienen de getters/acuerdo_distribuidores.php,
	// filtrados por el `supervisor` del usuario logueado — ver CANAL_USUARIO
	// (impreso por registrar.php desde la sesión) y canalDeSupervisor() en
	// functions.php. `empresas` solo tiene datos si canal==='distribuidor'
	// (agrupado por tipo_distribuidor); `clientes` es la lista plana para
	// Directo/Mayorista.
	var catalogoDistribuidor = { canal: 'directo', empresas: {}, clientes: [] };

	var selectedStart = 0;
	var selectedEnd = 2;
	var activeMonthsIndices = [0, 1, 2];
	var acuerdoId = null;
	var documentoNo = null;

	// ---------- Cambios sin guardar ----------
	// Cambiar de módulo en el sidebar NUNCA destruye este formulario (solo se
	// oculta con CSS, ver index.php) — el único riesgo real de perder trabajo
	// es que el usuario cierre la pestaña del navegador. formSucio se marca
	// true en cualquier edición real (combos, meses, filas, montos tipeados) y
	// se limpia al guardar con éxito (borrador o generado) o al cargar un
	// borrador recién traído del servidor (eso no es un cambio "sin guardar").
	var formSucio = false;
	function marcarSucio() { formSucio = true; }
	window.addEventListener('beforeunload', function (e) {
		if (!formSucio) return;
		e.preventDefault();
		e.returnValue = '';
	});

	var formatCurr = function (val) {
		return (isNaN(val) ? 0 : val).toLocaleString('en-US', { style: 'currency', currency: 'USD' });
	};

	// ---------- Selectores ----------
	var empresaSelect       = document.getElementById('ac-empresa');
	var empresaSearch       = document.getElementById('ac-empresa-search');
	var distribuidorSelect = document.getElementById('ac-distribuidor');
	var distribuidorSearch = document.getElementById('ac-distribuidor-search');
	var localidadEl        = document.getElementById('ac-localidad');
	var anioSelect          = document.getElementById('ac-anio');
	var monthsDisplay       = document.getElementById('ac-months-display');
	var rangeText           = document.getElementById('ac-selected-range-text');
	var pickerTrigger       = document.getElementById('ac-month-picker-trigger');
	var pickerPopover       = document.getElementById('ac-month-picker-popover');
	var monthGrid           = document.getElementById('ac-month-grid');

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
		mostrarToast(texto, ok ? 'success' : 'error');
	}

	// Un solo listener delegado cubre todos los campos tipeados (montos, rebate,
	// participación, cantidad) sin importar que las filas se creen/destruyan
	// dinámicamente — los combos (Distribuidor/Segmento/Categoría/Marca/Sector)
	// no disparan 'input' nativo (comboSeleccionar asigna .value directo), así
	// que esos marcan sucio aparte, en su propio flujo de selección.
	var acuerdoContainer = document.querySelector('.ac-acuerdo');
	acuerdoContainer.addEventListener('input', marcarSucio);

	// ---------- Carga inicial ----------
	// El badge de Canal (#ac-canal-badge) ya lo arma registrar.php del lado del
	// servidor, a partir de CANAL_USUARIO (sesión -> canalDeSupervisor()) — acá
	// solo se usa CANAL_USUARIO para la cascada Empresa->Cliente más abajo.
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
				catalogo.sectores = catRes.sectores || {};
			}
			if (distRes.ok) {
				catalogoDistribuidor.canal = distRes.canal;
				catalogoDistribuidor.empresas = distRes.empresas || {};
				catalogoDistribuidor.clientes = distRes.clientes || [];
			}

			buildMonthGrid();
			updatePickerUI();
			syncTables();
		}).catch(function () {
			distribuidorSearch.placeholder = 'Error al cargar';
			mostrarMensaje('No se pudo cargar el catálogo de productos ni distribuidores. Recarga la página.', false);
		});
	}

	// Localidad nunca se guarda (regla de negocio): siempre se deriva del
	// `cedi` del cliente elegido, en el momento de mostrarla — nunca de un
	// valor tipeado por el usuario. repositorio_locales_supervisores_cliente
	// no tiene province/city como el maestro viejo, solo `cedi`.
	function formatLocalidad(d) {
		return (d && d.cedi) ? d.cedi : '—';
	}

	// Junta los clientes disponibles sin importar si vienen agrupados por
	// empresa (canal Distribuidor) o en lista plana (Directo/Mayorista) — para
	// poder buscar por pos_id sin duplicar la lógica de "¿qué fuente uso?".
	function todosLosClientesDisponibles() {
		if (catalogoDistribuidor.canal === 'distribuidor') {
			var todos = [];
			Object.keys(catalogoDistribuidor.empresas).forEach(function (emp) {
				todos = todos.concat(catalogoDistribuidor.empresas[emp]);
			});
			return todos;
		}
		return catalogoDistribuidor.clientes;
	}

	function distribuidorSeleccionado() {
		return todosLosClientesDisponibles().filter(function (x) { return x.pos_id === distribuidorSelect.value; })[0];
	}

	// Sector (ej: CREMA/BARRA/LIQUIDO) depende de la combinación exacta
	// Segmento+Categoría+Marca — solo se usa en Meta de Compras (ver
	// getters/acuerdo_catalogo.php).
	function sectoresDisponibles(segmento, categoria, marca) {
		return catalogo.sectores[segmento + '|' + categoria + '|' + marca] || [];
	}

	// ---------- Sistema genérico de combobox (buscador + panel flotante) ----------
	// Un solo panel compartido para TODOS los campos (Distribuidor, y Segmento/
	// Categoría/Marca de las 4 tablas) en vez de un panel por celda — más liviano
	// y evita duplicar lógica. El panel usa position:fixed calculado con
	// getBoundingClientRect(), así nunca lo recorta un ancestro con overflow
	// distinto de "visible" (tablas con scroll horizontal, cards, etc. — el
	// mismo tipo de bug que ya arreglamos antes para el picker de meses).
	//
	// Quita espacios/acentos para que "super alianza" encuentre "SUPERALIANZA"
	// (el pos_name real de los maestros externos no siempre trae espacios
	// entre palabras) — sin esto la búsqueda exigía coincidir carácter a
	// carácter incluyendo espacios.
	function normalizarBusqueda(str) {
		return (str || '')
			.toString()
			.normalize('NFD').replace(/[̀-ͯ]/g, '')
			.toLowerCase()
			.replace(/\s+/g, '');
	}

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.textContent = str == null ? '' : String(str);
		return div.innerHTML;
	}

	var comboPanel = document.createElement('div');
	comboPanel.className = 'ac-combo-panel hidden';
	document.body.appendChild(comboPanel);
	var comboActivo = null; // { input, hidden, getOpciones, onSeleccionar }

	function posicionarPanelCombo(input) {
		var r = input.getBoundingClientRect();
		comboPanel.style.position = 'fixed';
		comboPanel.style.left = r.left + 'px';
		comboPanel.style.top = (r.bottom + 4) + 'px';
		comboPanel.style.width = Math.max(r.width, 220) + 'px';
	}

	function comboRender(filtro) {
		if (!comboActivo) return;
		var q = normalizarBusqueda(filtro);
		var opciones = comboActivo.getOpciones();
		var coincidencias = opciones.filter(function (op) {
			return !q || normalizarBusqueda(op.label).indexOf(q) !== -1;
		}).sort(function (a, b) {
			return a.label.localeCompare(b.label, 'es', { sensitivity: 'base' });
		}).slice(0, 60);

		comboPanel.innerHTML = coincidencias.length
			? coincidencias.map(function (op, i) { return '<div class="ac-combo-option" data-i="' + i + '">' + escapeHtml(op.label) + '</div>'; }).join('')
			: '<div class="ac-combo-empty">Sin coincidencias</div>';
		comboPanel.classList.remove('hidden');

		Array.prototype.forEach.call(comboPanel.querySelectorAll('.ac-combo-option'), function (opt) {
			opt.addEventListener('mousedown', function (e) {
				e.preventDefault();
				comboSeleccionar(coincidencias[parseInt(opt.dataset.i, 10)]);
			});
		});
	}

	function comboSeleccionar(op) {
		if (!comboActivo) return;
		var onSel = comboActivo.onSeleccionar;
		comboActivo.hidden.value = op.value;
		comboActivo.input.value = op.label;
		comboCerrar();
		marcarSucio();
		if (onSel) onSel(op.value, op.label);
	}

	function comboCerrar() {
		comboPanel.classList.add('hidden');
		comboActivo = null;
	}

	// getOpciones: función que devuelve [{value, label}] — función (no array
	// fijo) porque en los combos encadenados (Categoría/Marca) las opciones
	// válidas cambian según lo que se eligió antes en la fila.
	function inicializarCombo(input, hidden, getOpciones, onSeleccionar) {
		function abrir() {
			comboActivo = { input: input, hidden: hidden, getOpciones: getOpciones, onSeleccionar: onSeleccionar };
			posicionarPanelCombo(input);
			// Al reabrir un campo que ya tiene algo elegido, seleccionar todo el
			// texto (para que tipear lo reemplace de una) y mostrar la lista
			// completa (no filtrada por el valor actual) — si no, había que
			// borrar el campo a mano antes de poder buscar de nuevo.
			input.select();
			comboRender('');
		}
		input.addEventListener('focus', abrir);
		// El evento 'focus' NO se dispara de nuevo si el campo ya estaba
		// enfocado (ej: elegís una opción, el campo se queda con el foco a
		// propósito, y volvés a hacer click ahí mismo para buscar otra cosa)
		// — sin este listener de 'click' aparte, el panel no se reabría y se
		// sentía "trabado" hasta hacer click en otro lado y volver.
		input.addEventListener('click', function () {
			if (!comboActivo || comboActivo.input !== input) abrir();
		});
		input.addEventListener('input', function () {
			hidden.value = '';
			if (comboActivo && comboActivo.input === input) comboRender(input.value);
		});
		// Sin esto, salir del campo con Tab (en vez de elegir una opción con
		// el mouse) dejaba el panel abierto apuntando al campo anterior. El
		// mousedown+preventDefault() de las opciones evita el blur mientras
		// se hace click en una, así que esto no interfiere con esa selección.
		input.addEventListener('blur', function () {
			if (comboActivo && comboActivo.input === input) comboCerrar();
		});
	}

	document.addEventListener('click', function (e) {
		if (comboActivo && comboActivo.input !== e.target && !comboPanel.contains(e.target)) comboCerrar();
	});
	// capture:true para detectar scroll dentro de la tabla/página (el panel es
	// position:fixed y no la sigue) — pero excluyendo el scroll DENTRO del
	// propio panel (comboPanel tiene overflow-y:auto para ver más opciones),
	// si no se cerraba solo apenas el usuario intentaba scrollear la lista.
	document.addEventListener('scroll', function (e) {
		if (comboActivo && !comboPanel.contains(e.target)) comboCerrar();
	}, true);

	// Seleccionar todo el texto al enfocar cualquier campo de monto en
	// dólares (Meta de Compras, Cabeceras, Perchas, leyenda de Rumas), para
	// que tipear un valor nuevo lo reemplace de una — no hace falta borrar
	// el "0" o el valor anterior a mano. Un solo listener delegado (en vez
	// de uno por celda) porque las filas se crean y destruyen dinámicamente.
	document.addEventListener('focusin', function (e) {
		if (e.target.matches && e.target.matches('.month-input, .v-val, .ac-ruma-legend-input')) {
			e.target.select();
		}
	});

	// ---------- Empresa Distribuidora (solo canal Distribuidor) ----------
	// Un supervisor de canal Distribuidor puede manejar varias empresas
	// distribuidoras (tipo_distribuidor) — hay que elegir la empresa antes de
	// poder ver sus clientes, igual que Categoría depende de haber elegido
	// Segmento en las tablas del Acta. El campo #ac-empresa-field ya viene
	// oculto por PHP (registrar.php) cuando el canal no es Distribuidor.
	if (CANAL_USUARIO === 'distribuidor') distribuidorSearch.disabled = true;

	function limpiarClienteElegido() {
		distribuidorSelect.value = '';
		distribuidorSearch.value = '';
		localidadEl.textContent = '—';
		actualizarBloqueoPorDistribuidor();
	}

	inicializarCombo(empresaSearch, empresaSelect, function () {
		return Object.keys(catalogoDistribuidor.empresas).map(function (e) { return { value: e, label: e }; });
	}, function () {
		distribuidorSearch.disabled = false;
		limpiarClienteElegido();
	});
	empresaSearch.addEventListener('input', function () {
		distribuidorSearch.disabled = true;
		limpiarClienteElegido();
	});

	// El primer campo de cada tabla (Segmento en Meta/Cabeceras/Rumas, Marca en
	// Perchas) queda deshabilitado hasta elegir Distribuidor — no tiene sentido
	// armar líneas de producto antes de saber para quién es el acuerdo. Los
	// siguientes campos de cada cascada (Categoría/Marca/Sector) ya se rigen
	// solos una vez que Segmento tiene valor, así que no hace falta tocarlos.
	function actualizarBloqueoPorDistribuidor() {
		var habilitado = !!distribuidorSelect.value;
		Array.prototype.forEach.call(document.querySelectorAll('#ac-purchase-body .seg-input, #ac-cabeceras-body .seg-input, #ac-rumas-body .seg-input'), function (input) {
			input.disabled = !habilitado;
			input.placeholder = habilitado ? 'Segmento...' : 'Elige un Distribuidor primero';
		});
		Array.prototype.forEach.call(document.querySelectorAll('#ac-perchas-body .marca-input'), function (input) {
			input.disabled = !habilitado;
			input.placeholder = habilitado ? 'Marca...' : 'Elige un Distribuidor primero';
		});
	}

	// ---------- Distribuidor / Cliente (repositorio_locales_supervisores_cliente.pos_name) ----------
	inicializarCombo(distribuidorSearch, distribuidorSelect, function () {
		if (catalogoDistribuidor.canal === 'distribuidor') {
			return (catalogoDistribuidor.empresas[empresaSelect.value] || []).map(function (d) { return { value: d.pos_id, label: d.pos_name }; });
		}
		return catalogoDistribuidor.clientes.map(function (d) { return { value: d.pos_id, label: d.pos_name }; });
	}, function (posId) {
		var d = todosLosClientesDisponibles().filter(function (x) { return x.pos_id === posId; })[0];
		localidadEl.textContent = formatLocalidad(d);
		actualizarBloqueoPorDistribuidor();
	});
	distribuidorSearch.addEventListener('input', function () {
		localidadEl.textContent = '—';
		actualizarBloqueoPorDistribuidor();
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
				marcarSucio();
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
		marcarSucio();
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
	// Separado de syncTables() para que poblarTablasConLineas() (carga de un
	// borrador) pueda reconstruir los encabezados según el período guardado
	// sin pasar por el reset a una sola fila vacía por tabla.
	function renderTableHeaders() {
		var months = activeMonthsIndices.map(function (i) { return allMonthsShort[i]; });
		var count = months.length;

		purchaseHead.innerHTML =
			'<tr><th class="ac-sticky-col">Segmento</th><th class="ac-sticky-col ac-sticky-col-2">Categoría</th><th class="ac-sticky-col ac-sticky-col-3">Marca</th><th class="ac-sticky-col ac-sticky-col-4">Sector</th>' +
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
			'<th colspan="' + count + '">Valor Ruma x Mes (se edita en la mini tabla de la derecha)</th><th rowspan="2">Pago Total</th><th rowspan="2"></th></tr>' +
			'<tr>' + months.map(function (m) { return '<th>' + m + '</th>'; }).join('') + '</tr>';

		perchasHead.innerHTML =
			'<tr><th rowspan="3" class="ac-sticky-col">Marca Perchas</th><th rowspan="1">Participación</th><th rowspan="1">Cantidad</th>' +
			'<th colspan="' + (count + 1) + '">Pago Mensual</th><th rowspan="3"></th></tr>' +
			'<tr><th colspan="' + (count + 2) + '">Pago x Mes x Percha ($)</th></tr>' +
			'<tr><th>% de Peso</th><th>Max Percha</th>' + months.map(function (m) { return '<th>' + m + '</th>'; }).join('') + '<th>Pago Total</th></tr>';
	}

	function syncTables() {
		renderTableHeaders();
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

	// Celda de tabla con buscador (input visible) + valor real (input oculto,
	// mismo nombre de clase que antes usaba el <select>, para no tocar el
	// resto del código que lee `.seg-select`/`.cat-select`/`.marca-select`).
	function comboCellHtml(tipo, placeholder, disabled) {
		return '<div class="ac-combo ac-combo-cell">' +
			'<input type="text" class="ac-input ac-mini-input ac-combo-input ' + tipo + '-input" placeholder="' + placeholder + '" autocomplete="off"' + (disabled ? ' disabled' : '') + '>' +
			'<input type="hidden" class="' + tipo + '-select" value="">' +
			'</div>';
	}

	// Encadena Segmento -> Categoría -> Marca en una fila usando el sistema
	// genérico de combobox. onCambio (opcional) se llama después de cualquier
	// selección — lo usa Rumas para refrescar la leyenda lateral. onMarcaElegida
	// (opcional) se llama SOLO cuando la Marca queda con un valor real — lo usa
	// Meta de Compras para sugerir la misma combinación en las otras 3 tablas.
	// Devuelve un controlador con .sugerir(seg, cat, marca) para que otras
	// filas puedan aplicarle una sugerencia sin pisar una elección ya hecha.
	function bindCascadaCombo(tr, onCambio, onMarcaElegida) {
		var segInput = tr.querySelector('.seg-input'), segHidden = tr.querySelector('.seg-select');
		var catInput = tr.querySelector('.cat-input'), catHidden = tr.querySelector('.cat-select');
		var marcaInput = tr.querySelector('.marca-input'), marcaHidden = tr.querySelector('.marca-select');
		// Sector solo existe en la fila de Meta de Compras (ver comboCellHtml
		// en addPurchaseRow) — en Cabeceras/Rumas no hay estos elementos y
		// todo lo de acá abajo queda inerte (sectorInput === null).
		var sectorInput = tr.querySelector('.sector-input'), sectorHidden = tr.querySelector('.sector-select');

		function limpiarSector() {
			if (!sectorInput) return;
			sectorHidden.value = ''; sectorInput.value = ''; sectorInput.disabled = true;
		}

		function aplicarSeg(value) {
			segHidden.value = value; segInput.value = value;
			catHidden.value = ''; catInput.value = ''; catInput.disabled = !value;
			marcaHidden.value = ''; marcaInput.value = ''; marcaInput.disabled = true;
			limpiarSector();
			if (onCambio) onCambio();
		}
		function aplicarCat(value) {
			catHidden.value = value; catInput.value = value;
			marcaHidden.value = ''; marcaInput.value = ''; marcaInput.disabled = !value;
			limpiarSector();
			if (onCambio) onCambio();
		}
		function aplicarMarca(value) {
			marcaHidden.value = value; marcaInput.value = value;
			if (sectorInput) {
				var sectores = value ? sectoresDisponibles(segHidden.value, catHidden.value, value) : [];
				sectorHidden.value = ''; sectorInput.value = '';
				sectorInput.disabled = !sectores.length;
				// Si la combinación solo tiene un sector posible, se autocompleta
				// — no tiene sentido obligar a elegir entre una sola opción.
				if (sectores.length === 1) { sectorHidden.value = sectores[0]; sectorInput.value = sectores[0]; }
			}
			if (onCambio) onCambio();
			if (value && onMarcaElegida) onMarcaElegida(segHidden.value, catHidden.value, value);
		}

		inicializarCombo(segInput, segHidden, function () {
			return Object.keys(catalogo.segmentos).map(function (s) { return { value: s, label: s }; });
		}, aplicarSeg);

		inicializarCombo(catInput, catHidden, function () {
			return Object.keys(catalogo.segmentos[segHidden.value] || {}).map(function (c) { return { value: c, label: c }; });
		}, aplicarCat);

		inicializarCombo(marcaInput, marcaHidden, function () {
			return ((catalogo.segmentos[segHidden.value] || {})[catHidden.value] || []).map(function (m) { return { value: m, label: m }; });
		}, aplicarMarca);

		if (sectorInput) {
			inicializarCombo(sectorInput, sectorHidden, function () {
				return sectoresDisponibles(segHidden.value, catHidden.value, marcaHidden.value).map(function (s) { return { value: s, label: s }; });
			}, function (value) {
				sectorHidden.value = value; sectorInput.value = value;
				if (onCambio) onCambio();
			});
		}

		return {
			// Solo rellena si la fila sigue vacía — nunca pisa una selección
			// que el usuario ya hizo a mano en esa tabla.
			sugerir: function (segmento, categoria, marca) {
				if (segHidden.value) return;
				aplicarSeg(segmento);
				aplicarCat(categoria);
				aplicarMarca(marca);
			}
		};
	}

	// Marca de Perchas: lista plana, sin cascada de Segmento/Categoría.
	function bindMarcaPerchaCombo(tr) {
		var marcaInput = tr.querySelector('.marca-input'), marcaHidden = tr.querySelector('.marca-select');
		function aplicarMarca(value) {
			marcaHidden.value = value; marcaInput.value = value;
		}
		inicializarCombo(marcaInput, marcaHidden, function () {
			return catalogo.marcasPercha.map(function (m) { return { value: m, label: m }; });
		}, aplicarMarca);

		return {
			sugerir: function (marca) {
				if (marcaHidden.value) return;
				aplicarMarca(marca);
			}
		};
	}

	// Al completar Segmento+Categoría+Marca en Meta de Compras, se sugiere la
	// misma combinación en la primera fila vacía de Cabeceras/Rumas/Perchas —
	// solo la identidad del producto, nunca los valores en dólares (eso lo
	// sigue tipeando el usuario a mano en cada tabla).
	function sugerirEnOtrasTablas(segmento, categoria, marca) {
		var filaCab = Array.prototype.filter.call(cabecerasBody.querySelectorAll('tr'), function (r) {
			return !r.querySelector('.seg-select').value;
		})[0];
		if (filaCab && filaCab._combo) filaCab._combo.sugerir(segmento, categoria, marca);

		var filaRuma = Array.prototype.filter.call(rumasBody.querySelectorAll('tr'), function (r) {
			return !r.querySelector('.seg-select').value;
		})[0];
		if (filaRuma && filaRuma._combo) filaRuma._combo.sugerir(segmento, categoria, marca);

		var filaPercha = Array.prototype.filter.call(perchasBody.querySelectorAll('tr'), function (r) {
			return !r.querySelector('.marca-select').value;
		})[0];
		if (filaPercha && filaPercha._comboMarca) filaPercha._comboMarca.sugerir(marca);
	}

	// ---------- Meta de Compras ----------
	function addPurchaseRow() {
		var tr = document.createElement('tr');
		var html =
			'<td class="ac-sticky-col">' + comboCellHtml('seg', 'Segmento...', false) + '</td>' +
			'<td class="ac-sticky-col ac-sticky-col-2">' + comboCellHtml('cat', 'Categoría...', true) + '</td>' +
			'<td class="ac-sticky-col ac-sticky-col-3">' + comboCellHtml('marca', 'Marca...', true) + '</td>' +
			'<td class="ac-sticky-col ac-sticky-col-4">' + comboCellHtml('sector', 'Sector...', true) + '</td>';
		activeMonthsIndices.forEach(function () {
			html += '<td class="ac-text-right"><div class="ac-money-field"><input type="number" step="0.01" class="ac-input ac-mini-input month-input" value="0"></div></td>';
		});
		html +=
			'<td class="ac-text-right ac-col-highlight ac-tabular total-cell">$0.00</td>' +
			'<td class="ac-text-right ac-col-highlight"><input type="number" step="0.01" min="0" class="ac-input ac-mini-input ac-rebate-input" value="0"></td>' +
			'<td class="ac-text-right ac-col-highlight ac-tabular est-cell">$0.00</td>' +
			'<td class="ac-text-center"><button type="button" class="ac-icon-btn ac-remove-row"><span class="material-symbols-outlined">delete</span></button></td>';
		tr.innerHTML = html;
		purchaseBody.appendChild(tr);

		tr._combo = bindCascadaCombo(tr, null, function (seg, cat, marca) { sugerirEnOtrasTablas(seg, cat, marca); });

		var recalc = function () { updatePurchaseRow(tr); };
		tr.querySelectorAll('.month-input, .ac-rebate-input').forEach(function (i) { i.addEventListener('input', recalc); });
		tr.querySelector('.ac-remove-row').addEventListener('click', function () { marcarSucio(); tr.remove(); updateGrandTotals(); });
		actualizarBloqueoPorDistribuidor();
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
			'<tr class="ac-totales-row"><td class="ac-sticky-col" colspan="4">Totales</td>' +
			monthSums.map(function (s) { return '<td class="ac-text-right ac-tabular">' + formatCurr(s) + '</td>'; }).join('') +
			'<td class="ac-text-right ac-tabular">' + formatCurr(grandTotal) + '</td>' +
			'<td class="ac-text-right">—</td>' +
			'<td class="ac-text-right ac-tabular">' + formatCurr(grandEst) + '</td><td></td></tr>';
	}

	// ---------- Cabeceras ----------
	function addCabeceraRow() {
		var tr = document.createElement('tr');
		var html =
			'<td class="ac-sticky-col">' + comboCellHtml('seg', 'Segmento...', false) + '</td>' +
			'<td class="ac-sticky-col ac-sticky-col-2">' + comboCellHtml('cat', 'Categoría...', true) + '</td>' +
			'<td class="ac-sticky-col ac-sticky-col-3">' + comboCellHtml('marca', 'Marca...', true) + '</td>';
		activeMonthsIndices.forEach(function () {
			html += '<td><div class="ac-money-field"><input type="number" step="0.01" class="ac-input ac-mini-input v-val" value="0"></div></td>';
		});
		html += '<td class="ac-tabular v-tot">$0.00</td><td class="ac-text-center"><button type="button" class="ac-icon-btn ac-remove-row"><span class="material-symbols-outlined">delete</span></button></td>';
		tr.innerHTML = html;
		cabecerasBody.appendChild(tr);
		tr._combo = bindCascadaCombo(tr);
		attachVisListeners(tr);
		actualizarBloqueoPorDistribuidor();
	}

	// ---------- Rumas ----------
	// Muestra una celda por mes (igual look que Cabeceras/Perchas) pero de
	// SOLO LECTURA — el valor se tipea UNA vez en la mini tabla "Valor Ruma x
	// Marca x Mes" de al lado (updateRumaLegend) y desde ahí se replica a
	// todos los meses de las filas que compartan esa misma Marca. Así el
	// usuario nunca tipea directo en los meses, y una marca nunca contamina
	// el valor de otra fila con una Marca distinta.
	function addRumaRow() {
		var tr = document.createElement('tr');
		var html =
			'<td class="ac-sticky-col">' + comboCellHtml('seg', 'Segmento...', false) + '</td>' +
			'<td class="ac-sticky-col ac-sticky-col-2">' + comboCellHtml('cat', 'Categoría...', true) + '</td>' +
			'<td class="ac-sticky-col ac-sticky-col-3">' + comboCellHtml('marca', 'Marca...', true) + '</td>';
		activeMonthsIndices.forEach(function () {
			html += '<td><div class="ac-money-field"><input type="number" step="0.01" class="ac-input ac-mini-input v-val-repetido" value="0" readonly tabindex="-1"></div></td>';
		});
		html += '<td class="ac-tabular v-tot">$0.00</td><td class="ac-text-center"><button type="button" class="ac-icon-btn ac-remove-row"><span class="material-symbols-outlined">delete</span></button></td>';
		tr.innerHTML = html;
		rumasBody.appendChild(tr);
		tr._combo = bindCascadaCombo(tr, function () { updateRumaLegend(); });

		tr.querySelector('.ac-remove-row').addEventListener('click', function () { marcarSucio(); tr.remove(); updateRumaLegend(); });
		actualizarBloqueoPorDistribuidor();
	}

	// La leyenda es ahora la ÚNICA fuente editable: un input por Marca
	// distinta presente en la tabla. Al tipear ahí, se replica el valor a
	// todas (y SOLO a) las filas de esa misma Marca — nunca a otras marcas.
	// También se auto-sincroniza al agregar una fila nueva con una Marca que
	// ya tenía valor en otra fila (para que no se vea "0" mientras las demás
	// muestran el valor real).
	function updateRumaLegend() {
		var rows = Array.prototype.slice.call(rumasBody.querySelectorAll('tr'));
		var marcas = [];
		rows.forEach(function (r) {
			var m = r.querySelector('.marca-select').value;
			if (m && marcas.indexOf(m) === -1) marcas.push(m);
		});

		if (!marcas.length) {
			rumasLegendBody.innerHTML = '<tr><td colspan="2" class="ac-table-empty">Sin datos</td></tr>';
			return;
		}

		var valores = {};
		marcas.forEach(function (m) {
			var filasMarca = rows.filter(function (r) { return r.querySelector('.marca-select').value === m; });
			var valorActual = 0;
			filasMarca.forEach(function (r) {
				var reps = r.querySelectorAll('.v-val-repetido');
				var v = reps.length ? (parseFloat(reps[0].value) || 0) : 0;
				if (v > 0) valorActual = v;
			});
			filasMarca.forEach(function (r) {
				var reps = r.querySelectorAll('.v-val-repetido');
				Array.prototype.forEach.call(reps, function (rep) { rep.value = valorActual; });
				r.querySelector('.v-tot').textContent = formatCurr(valorActual * activeMonthsIndices.length);
			});
			valores[m] = valorActual;
		});

		rumasLegendBody.innerHTML = marcas.map(function (m) {
			return '<tr><td>' + escapeHtml(m) + '</td><td class="ac-text-right"><div class="ac-money-field"><input type="number" step="0.01" min="0" class="ac-input ac-mini-input ac-ruma-legend-input" data-marca="' + escapeHtml(m) + '" value="' + valores[m] + '"></div></td></tr>';
		}).join('');

		Array.prototype.forEach.call(rumasLegendBody.querySelectorAll('.ac-ruma-legend-input'), function (input) {
			input.addEventListener('input', function () {
				var marca = input.dataset.marca;
				var v = parseFloat(input.value) || 0;
				rows.forEach(function (r) {
					if (r.querySelector('.marca-select').value !== marca) return; // nunca tocar filas de otra Marca
					var reps = r.querySelectorAll('.v-val-repetido');
					Array.prototype.forEach.call(reps, function (rep) { rep.value = v; });
					r.querySelector('.v-tot').textContent = formatCurr(v * activeMonthsIndices.length);
				});
			});
		});
	}

	// ---------- Perchas ----------
	function addPerchaRow() {
		var tr = document.createElement('tr');
		var html =
			'<td class="ac-sticky-col">' + comboCellHtml('marca', 'Marca...', false) + '</td>' +
			'<td><input type="text" class="ac-input ac-mini-input v-participacion" value="50%"></td>' +
			'<td><input type="number" min="0" max="5" class="ac-input ac-mini-input v-cantidad" value="1"></td>';
		activeMonthsIndices.forEach(function () {
			html += '<td><div class="ac-money-field"><input type="number" step="0.01" class="ac-input ac-mini-input v-val" value="0"></div></td>';
		});
		html += '<td class="ac-tabular v-tot">$0.00</td><td class="ac-text-center"><button type="button" class="ac-icon-btn ac-remove-row"><span class="material-symbols-outlined">delete</span></button></td>';
		tr.innerHTML = html;
		perchasBody.appendChild(tr);
		tr._comboMarca = bindMarcaPerchaCombo(tr);
		attachVisListeners(tr);

		tr.querySelector('.v-cantidad').addEventListener('change', function () {
			var val = parseInt(tr.querySelector('.v-cantidad').value, 10) || 0;
			if (val > 5) { tr.querySelector('.v-cantidad').value = 5; mostrarMensaje('El máximo de perchas por marca es 5.', false); }
		});
		actualizarBloqueoPorDistribuidor();
	}

	function attachVisListeners(row) {
		var recalc = function () {
			var vals = Array.prototype.map.call(row.querySelectorAll('.v-val'), function (v) { return parseFloat(v.value) || 0; });
			row.querySelector('.v-tot').textContent = formatCurr(vals.reduce(function (a, b) { return a + b; }, 0));
		};
		row.querySelectorAll('input, select').forEach(function (i) { i.addEventListener('input', recalc); });
		row.querySelector('.ac-remove-row').addEventListener('click', function () { marcarSucio(); row.remove(); });
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
				participacion: r.querySelector('.v-participacion').value,
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
					formSucio = false;
					if (onOk) onOk();
				}
			})
			.catch(function () { mostrarMensaje('Error de conexión. Intenta nuevamente.', false); });
	}

	// "Ver y Generar Acta" ya NO guarda el acuerdo como 'generado' — guarda
	// como 'borrador' (mismo estado silencioso de "Guardar Borrador"/"Mis
	// Borradores", no aparece en Historial) solo para tener un id real con el
	// que armar la previsualización del PDF. Recién se promueve a 'generado'
	// (con el snapshot definitivo) cuando el usuario da click en "Descargar /
	// Imprimir PDF" dentro del modal — ver actaDescargarBtn más abajo.
	document.getElementById('ac-generar-acta').addEventListener('click', function () {
		guardarAcuerdo('borrador', mostrarPreview);
	});
	document.getElementById('ac-guardar-borrador').addEventListener('click', function () {
		guardarAcuerdo('borrador');
	});

	// ---------- Preview / Acta ----------
	// El iframe carga el PDF real (Dompdf, mismo endpoint que "Descargar") —
	// el preview y el PDF descargado son literalmente el mismo archivo, no
	// dos maquetas que mantener sincronizadas.
	var actaModalOverlay = document.getElementById('ac-acta-modal-overlay');
	var actaPdfFrame = document.getElementById('ac-acta-pdf-frame');
	var actaDescargarBtn = document.getElementById('ac-acta-descargar-pdf');

	// pdfDescargado: si el usuario cierra la ventanita de previsualización sin
	// haber dado click en "Descargar / Imprimir PDF", se le avisa con un toast
	// — cerrar sin descargar es fácil de hacer sin querer y perder de vista que
	// el Acta todavía no quedó descargada/impresa.
	var pdfDescargado = false;

	function mostrarPreview() {
		// &t= evita que el navegador reuse un PDF viejo cacheado con la misma URL ?id=X.
		var url = 'getters/generar_acta_pdf.php?id=' + acuerdoId + '&t=' + Date.now();
		// #toolbar=0&navpanes=0 oculta la barra/miniaturas del visor nativo del
		// navegador (ya tenemos nuestros propios botones Descargar/Cerrar) y
		// view=FitH ajusta la hoja al ancho del modal en vez de dejarla chica
		// con espacio gris alrededor.
		actaPdfFrame.src = url + '#toolbar=0&navpanes=0&view=FitH';
		actaDescargarBtn.href = url;
		pdfDescargado = false;
		actaModalOverlay.classList.add('ac-modal-open');
	}

	// Acá es donde realmente se "genera" el Acta: promueve el borrador silencioso
	// a estado 'generado' (dispara el snapshot definitivo del PDF en
	// guardar_acuerdo.php) y recién entonces abre el PDF ya guardado. Si el
	// usuario solo mira el preview y cierra sin llegar a este click, el acuerdo
	// queda como borrador nomás (visible en "Mis Borradores", no en Historial).
	actaDescargarBtn.addEventListener('click', function (e) {
		e.preventDefault();
		guardarAcuerdo('generado', function () {
			pdfDescargado = true;
			var url = 'getters/generar_acta_pdf.php?id=' + acuerdoId + '&t=' + Date.now();
			window.open(url, '_blank');
		});
	});

	function cerrarModalActa() {
		actaModalOverlay.classList.remove('ac-modal-open');
		actaPdfFrame.src = '';
		if (!pdfDescargado) {
			mostrarToast('Cerraste la vista previa sin descargar/imprimir el PDF. El Acta no quedó descargada.', 'warning');
		}
	}
	document.getElementById('ac-acta-modal-close').addEventListener('click', cerrarModalActa);
	actaModalOverlay.addEventListener('click', function (e) {
		if (e.target === actaModalOverlay) cerrarModalActa();
	});

	// "Agregar Fila" en Meta de Compras agrega también una fila nueva en
	// Cabeceras/Rumas/Perchas (vacía), lista para recibir la sugerencia de
	// Segmento/Categoría/Marca en cuanto se elija la Marca en la fila nueva
	// de Meta de Compras (ver sugerirEnOtrasTablas). Los botones "Agregar
	// Fila" de las otras 3 tablas siguen agregando solo ahí — para cuando el
	// usuario necesita una fila extra en una sola tabla (ej. dos cabeceras
	// para el mismo producto).
	document.getElementById('ac-add-purchase-row').addEventListener('click', function () {
		marcarSucio();
		addPurchaseRow();
		addCabeceraRow();
		addRumaRow();
		addPerchaRow();
	});
	document.getElementById('ac-add-cabecera-row').addEventListener('click', function () { marcarSucio(); addCabeceraRow(); });
	document.getElementById('ac-add-ruma-row').addEventListener('click', function () { marcarSucio(); addRumaRow(); });
	document.getElementById('ac-add-percha-row').addEventListener('click', function () { marcarSucio(); addPerchaRow(); });

	// ---------- Mis Borradores ----------
	// input event sintético: las filas se llenan seteando .value directo por
	// código (no tipeando), lo que no dispara el listener 'input' que ya
	// recalcula totales por fila (attachVisListeners/updatePurchaseRow) — así
	// se reusa ese mismo recálculo en vez de duplicar la lógica de suma.
	function dispararInput(el) {
		if (el) el.dispatchEvent(new Event('input', { bubbles: true }));
	}

	function llenarValoresMensuales(inputs, valoresMensuales) {
		Array.prototype.forEach.call(inputs, function (input, i) {
			var mes = activeMonthsIndices[i];
			input.value = (valoresMensuales && valoresMensuales[String(mes)]) || 0;
		});
	}

	// Reconstruye las 4 tablas a partir de las líneas guardadas de un
	// borrador, en vez de la fila vacía única de syncTables(). El Sector de
	// Meta de Compras no se guarda en la base (ver CLAUDE.md) — si la
	// combinación Segmento+Categoría+Marca solo admite un Sector, igual se
	// autocompleta solo (misma lógica de aplicarMarca() que ya corre para
	// carga manual).
	function poblarTablasConLineas(lineas) {
		renderTableHeaders();
		purchaseBody.innerHTML = '';
		cabecerasBody.innerHTML = '';
		rumasBody.innerHTML = '';
		perchasBody.innerHTML = '';

		if (lineas.meta_compra && lineas.meta_compra.length) {
			lineas.meta_compra.forEach(function (fila) {
				addPurchaseRow();
				var tr = purchaseBody.lastElementChild;
				tr._combo.sugerir(fila.segmento, fila.categoria, fila.marca);
				llenarValoresMensuales(tr.querySelectorAll('.month-input'), fila.valores_mensuales);
				tr.querySelector('.ac-rebate-input').value = (fila.rebate_pct || 0) * 100;
				updatePurchaseRow(tr);
			});
		} else {
			addPurchaseRow();
		}

		if (lineas.cabecera && lineas.cabecera.length) {
			lineas.cabecera.forEach(function (fila) {
				addCabeceraRow();
				var tr = cabecerasBody.lastElementChild;
				tr._combo.sugerir(fila.segmento, fila.categoria, fila.marca);
				llenarValoresMensuales(tr.querySelectorAll('.v-val'), fila.valores_mensuales);
				dispararInput(tr.querySelector('.v-val'));
			});
		} else {
			addCabeceraRow();
		}

		if (lineas.ruma && lineas.ruma.length) {
			lineas.ruma.forEach(function (fila) {
				addRumaRow();
				var tr = rumasBody.lastElementChild;
				tr._combo.sugerir(fila.segmento, fila.categoria, fila.marca);
				Array.prototype.forEach.call(tr.querySelectorAll('.v-val-repetido'), function (rep) {
					rep.value = fila.valor_mensual_unico || 0;
				});
			});
			// La leyenda (única fuente editable de Rumas) se reconstruye UNA vez
			// al final, después de que todas las filas ya tienen su valor real
			// seteado — si se llamara fila por fila, updateRumaLegend() leería
			// valores todavía en 0 de las filas que faltan procesar.
			updateRumaLegend();
		} else {
			addRumaRow();
		}

		if (lineas.percha && lineas.percha.length) {
			lineas.percha.forEach(function (fila) {
				addPerchaRow();
				var tr = perchasBody.lastElementChild;
				tr._comboMarca.sugerir(fila.marca);
				tr.querySelector('.v-participacion').value = fila.participacion || '';
				tr.querySelector('.v-cantidad').value = fila.cantidad_max_percha || 0;
				llenarValoresMensuales(tr.querySelectorAll('.v-val'), fila.valores_mensuales);
				dispararInput(tr.querySelector('.v-val'));
			});
		} else {
			addPerchaRow();
		}

		updateGrandTotals();
	}

	function aplicarBorrador(a) {
		acuerdoId = a.id;
		documentoNo = a.documento_no;

		anioSelect.value = a.anio;
		selectedStart = a.mes_inicio;
		selectedEnd = a.mes_fin;
		activeMonthsIndices = [];
		for (var i = selectedStart; i <= selectedEnd; i++) activeMonthsIndices.push(i);
		updatePickerUI();

		// Canal Distribuidor: hay que fijar la Empresa del cliente guardado
		// antes de poder setear el Distribuidor, porque el combo de
		// Distribuidor arma sus opciones a partir de la Empresa elegida (ver
		// catalogoDistribuidor.empresas).
		if (catalogoDistribuidor.canal === 'distribuidor') {
			var empresaDeCliente = null;
			Object.keys(catalogoDistribuidor.empresas).some(function (emp) {
				var match = catalogoDistribuidor.empresas[emp].some(function (c) { return c.pos_id === a.pos_id; });
				if (match) { empresaDeCliente = emp; }
				return match;
			});
			if (empresaDeCliente) {
				empresaSelect.value = empresaDeCliente;
				empresaSearch.value = empresaDeCliente;
				distribuidorSearch.disabled = false;
			}
		}
		distribuidorSelect.value = a.pos_id;
		distribuidorSearch.value = a.distribuidor;
		localidadEl.textContent = a.localidad || '—';
		actualizarBloqueoPorDistribuidor();

		poblarTablasConLineas(a.lineas);
		// Cargar un borrador no es un cambio "sin guardar" propio — recién se
		// vuelve sucio si el usuario lo edita a partir de acá.
		formSucio = false;
		mostrarMensaje('Borrador #' + a.documento_no + ' cargado. Podés seguir editándolo.', true);
	}

	function cargarBorrador(id) {
		fetch('getters/obtener_borrador.php?id=' + id)
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) { mostrarMensaje(data.message || 'No se pudo cargar el borrador.', false); return; }
				aplicarBorrador(data.acuerdo);
			})
			.catch(function () { mostrarMensaje('Error de conexión al cargar el borrador.', false); });
	}

	// El modal "Mis Borradores" vive en Historial (components/historial.js),
	// pero cargar un borrador en el formulario solo lo puede hacer este
	// módulo — todo el estado de las 4 tablas y los combos vive acá adentro.
	// historial.js cambia a la pestaña Registrar y llama a esta función
	// expuesta (mismo patrón que "Nuevo Acuerdo" ya usa para cambiar de tab).
	window.acRegistrarCargarBorrador = cargarBorrador;

	cargarDatosIniciales();
})();

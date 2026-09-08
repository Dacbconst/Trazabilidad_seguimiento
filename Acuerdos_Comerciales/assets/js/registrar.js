(function () {
	var allMonthsShort = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
	var allMonthsLong = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
	// El periodo se maneja en trimestres fijos (Q1-Q4), no rango libre: cada entrada es [mesInicio, mesFin] (0=Ene) = value de #ac-periodo-select.
	var TRIMESTRES = [[0, 2], [3, 5], [6, 8], [9, 11]];

	// Catálogo y Distribuidores se cargan en vivo desde la base, nunca hardcodeados. segmentos: árbol Segmento->Categoría->Marca (Cabeceras/Rumas/Perchas).
	// segmentosSector: árbol Segmento->Sector->Categoría->Marca, solo para Meta de Compras (ver bindCascadaComboConSector).
	var catalogo = { segmentos: {}, marcasPercha: [], segmentosSector: {} };
	// canal/empresas/clientes filtrados por el `supervisor` del usuario (ver CANAL_USUARIO y canalDeSupervisor()).
	// `empresas` solo tiene datos si canal==='distribuidor' (agrupado por tipo_distribuidor); `clientes` es la lista plana para Directo/Mayorista.
	var catalogoDistribuidor = { canal: 'directo', empresas: {}, clientes: [] };

	// Etiqueta dinámica del campo pos_id: Directo dice "Distribuidor", Distribuidor dice "Local" para no pisar el campo de empresa ("Distribuidor").
	function etiquetaCampoLocal() { return CANAL_USUARIO === 'distribuidor' ? 'Local' : 'Distribuidor'; }

	var selectedStart = 0;
	var selectedEnd = 2;
	var activeMonthsIndices = [0, 1, 2];
	var acuerdoId = null;
	var documentoNo = null;
	// Evita el doble click en "Generar PDF"/"Guardar Borrador": 2 clicks seguidos disparaban 2 requests en paralelo y chocaban los mensajes.
	// Mientras hay un guardado en vuelo, cualquier click nuevo se ignora en silencio.
	var guardandoAcuerdo = false;
	// Si el formulario vino de una Acta precargada (cargarPrecarga()), se manda junto con el guardado para marcar esas filas como 'usada'.
	// null en cualquier otro caso (Nuevo Acuerdo, Borrador).
	var origenPrecarga = null;

	// ---------- Switch "Visibilidad y Espacios" ----------
	// Activado por defecto. Al desactivarlo, el Acta sale "sin visibilidad" (sin Cabeceras ni Rumas&Perchas, ver includes/acta_pdf.php $sinVisibilidad).
	var visibilidadActiva = true;

	// ---------- Cambios sin guardar ----------
	// Cambiar de módulo nunca destruye este formulario (solo se oculta con CSS). formSucio se marca true en cualquier edición y se limpia al guardar.
	var formSucio = false;
	function marcarSucio() { formSucio = true; }
	window.addEventListener('beforeunload', function (e) {
		if (!formSucio) return;
		e.preventDefault();
		e.returnValue = '';
	});

	// Canal Distribuidor mide en Cajas, no en Dólares: sin signo "$" ni formato de moneda. CANAL_USUARIO es fijo por usuario logueado,
	// no cambia según qué cliente puntual se elija en el formulario.
	var formatCurr = function (val) {
		var num = isNaN(val) ? 0 : val;
		if (CANAL_USUARIO === 'distribuidor') {
			return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
		}
		return num.toLocaleString('en-US', { style: 'currency', currency: 'USD' });
	};

	// ---------- Selectores ----------
	var empresaSelect       = document.getElementById('ac-empresa');
	var empresaSearch       = document.getElementById('ac-empresa-search');
	var distribuidorSelect = document.getElementById('ac-distribuidor');
	var distribuidorSearch = document.getElementById('ac-distribuidor-search');
	var localidadEl        = document.getElementById('ac-localidad');
	var anioSelect          = document.getElementById('ac-anio');
	var monthsDisplay       = document.getElementById('ac-months-display');
	var periodoSelect       = document.getElementById('ac-periodo-select');

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
	var visibilidadToggle = document.getElementById('ac-visibilidad-toggle');
	var visibilidadZona = document.getElementById('ac-visibilidad-zona');
	var visibilidadIcon = document.getElementById('ac-visibilidad-icon');

	function mostrarMensaje(texto, ok) {
		mostrarToast(texto, ok ? 'success' : 'error');
	}

	// Un solo listener delegado cubre todos los campos tipeados sin importar que las filas se creen/destruyan dinámicamente.
	// Los combos no disparan 'input' nativo (comboSeleccionar asigna .value directo), así que marcan sucio aparte en su propio flujo.
	var acuerdoContainer = document.querySelector('.ac-acuerdo');
	acuerdoContainer.addEventListener('input', marcarSucio);

	// Montos en dólares: nunca negativos. El rebate NO se toca acá, va a salir de un repositorio nuevo, no tiene sentido validarle un rango a mano.
	acuerdoContainer.addEventListener('input', function (e) {
		if (e.target.matches && e.target.matches('.month-input, .v-val, .ac-ruma-legend-input') && parseFloat(e.target.value) < 0) {
			e.target.value = 0;
		}
		// Participación de Perchas: texto libre (ej. "50%") pero nunca negativo.
		if (e.target.matches && e.target.matches('.v-participacion') && e.target.value.indexOf('-') !== -1) {
			e.target.value = e.target.value.replace(/-/g, '');
		}
	});

	// ---------- Carga inicial ----------
	// El badge de Canal ya lo arma registrar.php server-side desde CANAL_USUARIO; acá solo se usa para la cascada Empresa->Cliente.
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
				catalogo.segmentosSector = catRes.segmentos_sector || {};
			}
			if (distRes.ok) {
				catalogoDistribuidor.canal = distRes.canal;
				catalogoDistribuidor.empresas = distRes.empresas || {};
				catalogoDistribuidor.clientes = distRes.clientes || [];
			}

			// Distribuidor: el switch arranca desactivado por defecto (esas 2 tablas siempre se ocultaban en el Acta de Distribuidor). Directo sigue
			// activado. Solo aplica a un Acuerdo nuevo: un borrador ya guardado restaura su propio valor en aplicarBorrador().
			if (catalogoDistribuidor.canal === 'distribuidor') {
				visibilidadActiva = false;
				visibilidadToggle.checked = false;
			}
			aplicarBloqueoVisibilidad();

			updatePickerUI();
			syncTables();
		}).catch(function () {
			distribuidorSearch.placeholder = 'Error al cargar';
			mostrarMensaje('No se pudo cargar el catálogo de productos ni distribuidores. Recarga la página.', false);
		});
	}

	// Localidad nunca se guarda: siempre se deriva del `cedi` del cliente elegido al mostrarla, nunca de un valor tipeado. El maestro solo tiene `cedi`.
	function formatLocalidad(d) {
		return (d && d.cedi) ? d.cedi : '—';
	}

	// Junta clientes sin importar si vienen agrupados por empresa (Distribuidor) o en lista plana (Directo/Mayorista), para buscar por pos_id.
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

	// Solo Meta de Compras persiste Sector; al restaurar un borrador se infiere buscando en qué Sector aparece esa combinación Categoría+Marca.
	// Si la marca vende en más de un Sector con la misma Categoría, se toma el primero: limitación conocida, Sector nunca se guardó en la tabla.
	function inferirSectorDesde(segmento, categoria, marca) {
		var porSector = catalogo.segmentosSector[segmento] || {};
		return Object.keys(porSector).filter(function (sec) {
			return (porSector[sec][categoria] || []).indexOf(marca) !== -1;
		})[0] || '';
	}

	// ---------- Sistema genérico de combobox (buscador + panel flotante) ----------
	// Un solo panel compartido para todos los campos: más liviano, position:fixed con getBoundingClientRect() para que nunca lo recorte un ancestro.
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

	// Clamp de left: sin esto, un combo cerca del borde derecho en pantalla angosta hacía que left + width se saliera del viewport.
	function posicionarPanelCombo(input) {
		var r = input.getBoundingClientRect();
		var ancho = Math.max(r.width, 220);
		var margen = 8;
		var left = Math.min(r.left, window.innerWidth - ancho - margen);
		left = Math.max(left, margen);
		comboPanel.style.position = 'fixed';
		comboPanel.style.left = left + 'px';
		comboPanel.style.top = (r.bottom + 4) + 'px';
		comboPanel.style.width = ancho + 'px';
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

	// getOpciones: función (no array fijo) porque en combos encadenados las opciones cambian según lo elegido antes en la fila.
	// Se puede tipear para filtrar; al perder el foco, si el texto no coincide exacto con la opción elegida, el campo se limpia solo.
	function inicializarCombo(input, hidden, getOpciones, onSeleccionar) {
		function abrir() {
			comboActivo = { input: input, hidden: hidden, getOpciones: getOpciones, onSeleccionar: onSeleccionar };
			posicionarPanelCombo(input);
			comboRender(input.value);
		}
		function labelDeSeleccionActual() {
			var op = getOpciones().filter(function (o) { return o.value === hidden.value; })[0];
			return op ? op.label : '';
		}
		input.addEventListener('focus', function () {
			abrir();
			input.select();
		});
		// 'focus' no se dispara de nuevo si el campo ya estaba enfocado; sin este listener de 'click' aparte el panel se sentía "trabado".
		input.addEventListener('click', function () {
			if (!comboActivo || comboActivo.input !== input) abrir();
		});
		// Filtra la lista en vivo mientras se tipea, no toca `hidden.value` acá; si el usuario tipea y se va sin elegir, el blur de abajo limpia todo.
		input.addEventListener('input', function () {
			if (!comboActivo || comboActivo.input !== input) abrir();
			else comboRender(input.value);
		});
		// Sin esto, salir con Tab dejaba el panel abierto apuntando al campo anterior. mousedown+preventDefault() de las opciones evita el blur al elegir.
		input.addEventListener('blur', function () {
			if (comboActivo && comboActivo.input === input) comboCerrar();
			// Nunca dejar un valor tipeado que no coincide con una opción real seleccionada.
			if (input.value !== labelDeSeleccionActual()) {
				hidden.value = '';
				input.value = '';
			}
		});
	}

	document.addEventListener('click', function (e) {
		if (comboActivo && comboActivo.input !== e.target && !comboPanel.contains(e.target)) comboCerrar();
	});
	// capture:true para detectar scroll en la tabla/página (el panel es fixed y no la sigue), excluyendo el scroll dentro del propio panel.
	document.addEventListener('scroll', function (e) {
		if (comboActivo && !comboPanel.contains(e.target)) comboCerrar();
	}, true);

	// Seleccionar todo el texto al enfocar un campo de monto, para que tipear un valor nuevo lo reemplace de una. Delegado porque las filas se crean/destruyen.
	document.addEventListener('focusin', function (e) {
		if (e.target.matches && e.target.matches('.month-input, .v-val, .ac-ruma-legend-input')) {
			e.target.select();
		}
	});

	// ---------- Empresa Distribuidora (solo canal Distribuidor) ----------
	// Un supervisor puede manejar varias empresas: hay que elegirla antes de ver sus clientes, igual que Categoría depende de Segmento.
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

	// El primer campo de cada tabla queda deshabilitado hasta elegir Distribuidor: no tiene sentido armar líneas antes de saber para quién es.
	function actualizarBloqueoPorDistribuidor() {
		var habilitado = !!distribuidorSelect.value;
		Array.prototype.forEach.call(document.querySelectorAll('#ac-purchase-body .seg-input, #ac-cabeceras-body .seg-input, #ac-rumas-body .seg-input'), function (input) {
			input.disabled = !habilitado;
			input.placeholder = habilitado ? 'Segmento...' : 'Elige un ' + etiquetaCampoLocal() + ' primero';
		});
		Array.prototype.forEach.call(document.querySelectorAll('#ac-perchas-body .marca-input'), function (input) {
			input.disabled = !habilitado;
			input.placeholder = habilitado ? 'Marca...' : 'Elige un ' + etiquetaCampoLocal() + ' primero';
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

	// ---------- Periodo del Acuerdo (trimestres fijos Q1-Q4) ----------
	periodoSelect.addEventListener('change', function () {
		marcarSucio();
		aplicarTrimestre(parseInt(periodoSelect.value, 10));
	});

	// value: índice en TRIMESTRES (0=Q1...3=Q4). Separado del listener de arriba para reusarlo desde limpiarFormularioParaNuevoAcuerdo()/aplicarBorrador().
	function aplicarTrimestre(value) {
		var t = TRIMESTRES[value];
		selectedStart = t[0];
		selectedEnd = t[1];
		activeMonthsIndices = [];
		for (var i = selectedStart; i <= selectedEnd; i++) activeMonthsIndices.push(i);
		periodoSelect.value = String(value);
		updatePickerUI();
		syncTables();
	}

	function updatePickerUI() {
		monthsDisplay.textContent = (selectedStart !== null && selectedEnd !== null)
			? activeMonthsIndices.map(function (i) { return allMonthsLong[i]; }).join('-')
			: 'Sin selección';
	}

	// ---------- Construcción de tablas ----------
	// Separado de syncTables() para que poblarTablasConLineas() reconstruya los encabezados según el período guardado sin resetear las filas.
	function renderTableHeaders() {
		var months = activeMonthsIndices.map(function (i) { return allMonthsShort[i]; });
		var count = months.length;

		purchaseHead.innerHTML =
			// Etiquetas "Categoría"/"Subcategoría" (no "Sector"/"Categoría") por pedido de JW: solo texto visible, la columna interna sigue siendo 'sector'.
			'<tr><th class="ac-sticky-col">Segmento</th><th class="ac-sticky-col ac-sticky-col-2">Categoría</th><th class="ac-sticky-col ac-sticky-col-3">Subcategoría</th><th class="ac-sticky-col ac-sticky-col-4">Marca</th>' +
			// "($)" solo en canal Directo, Distribuidor mide en Cajas (mismo fix que formatCurr()).
			months.map(function (m) { return '<th class="ac-text-right">' + m + (CANAL_USUARIO === 'distribuidor' ? '' : ' ($)') + '</th>'; }).join('') +
			'<th class="ac-text-right ac-col-highlight">Total Período</th><th class="ac-text-right ac-col-highlight">Rebate %</th><th class="ac-text-right ac-col-highlight ac-th-2l">Valor Estimado<br>a Ganar</th><th></th></tr>';

		cabecerasHead.innerHTML =
			'<tr><th rowspan="2" class="ac-sticky-col">Segmento</th><th rowspan="2" class="ac-sticky-col ac-sticky-col-2">Categoría</th><th rowspan="2" class="ac-sticky-col ac-sticky-col-3">Marca</th>' +
			'<th colspan="' + count + '">Cabecera Pago x Mes</th><th rowspan="2" class="ac-th-2l">Pago Total<br>Cajas</th><th rowspan="2"></th></tr>' +
			'<tr>' + months.map(function (m) { return '<th>' + m + '</th>'; }).join('') + '</tr>';

		// Rumas visualmente tiene una columna por mes, pero las celdas están espejadas al mismo valor: el negocio exige "valor_mensual_unico" único.
		rumasHead.innerHTML =
			'<tr><th rowspan="2" class="ac-sticky-col">Segmento</th><th rowspan="2" class="ac-sticky-col ac-sticky-col-2">Categoría</th><th rowspan="2" class="ac-sticky-col ac-sticky-col-3">Marca</th>' +
			'<th colspan="' + count + '">Valor Ruma x Mes (se edita en la mini tabla de la derecha)</th><th rowspan="2" class="ac-th-2l">Pago Total<br>Cajas</th><th rowspan="2"></th></tr>' +
			'<tr>' + months.map(function (m) { return '<th>' + m + '</th>'; }).join('') + '</tr>';

		perchasHead.innerHTML =
			'<tr><th rowspan="3" class="ac-sticky-col">Marca Perchas</th><th rowspan="1">Participación</th><th rowspan="1">Cantidad</th>' +
			'<th colspan="' + (count + 1) + '">Pago Mensual</th><th rowspan="3"></th></tr>' +
			'<tr><th colspan="' + (count + 2) + '">Pago x Mes x Percha' + (CANAL_USUARIO === 'distribuidor' ? '' : ' ($)') + '</th></tr>' +
			'<tr><th>% de Peso</th><th>Max Percha</th>' + months.map(function (m) { return '<th>' + m + '</th>'; }).join('') + '<th class="ac-th-2l">Pago Total<br>Cajas</th></tr>';
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
		// La leyenda "Valor Ruma x Marca x Mes" no se limpia sola, solo se actualiza al cambiar un combo o eliminar una fila (ver addRumaRow()).
		// Sin este llamado quedaba mostrando las filas de la Acta anterior.
		updateRumaLegend();
		updateGrandTotals();
	}

	// Celda con buscador (input visible) + valor real (input oculto, mismo nombre de clase que antes usaba el <select>, sin tocar el resto del código).
	// Ya no es readonly: `inicializarCombo()` sigue sin dejar un valor tipeado sin elegir de verdad, mismo resultado sin el efecto colateral de antes.
	function comboCellHtml(tipo, placeholder, disabled) {
		return '<div class="ac-combo ac-combo-cell">' +
			'<input type="text" class="ac-input ac-mini-input ac-combo-input ' + tipo + '-input" placeholder="' + placeholder + '" autocomplete="off"' + (disabled ? ' disabled' : '') + '>' +
			'<input type="hidden" class="' + tipo + '-select" value="">' +
			'</div>';
	}

	// Encadena Segmento -> Categoría -> Marca. Usado por Cabeceras y Rumas (Meta de Compras usa bindCascadaComboConSector, orden distinto).
	// onCambio (opcional) se llama tras cualquier selección; devuelve .sugerir(seg, cat, marca) para aplicar sugerencia sin pisar lo ya elegido.
	function bindCascadaCombo(tr, onCambio) {
		var segInput = tr.querySelector('.seg-input'), segHidden = tr.querySelector('.seg-select');
		var catInput = tr.querySelector('.cat-input'), catHidden = tr.querySelector('.cat-select');
		var marcaInput = tr.querySelector('.marca-input'), marcaHidden = tr.querySelector('.marca-select');

		function aplicarSeg(value) {
			segHidden.value = value; segInput.value = value;
			catHidden.value = ''; catInput.value = ''; catInput.disabled = !value;
			marcaHidden.value = ''; marcaInput.value = ''; marcaInput.disabled = true;
			if (onCambio) onCambio();
		}
		function aplicarCat(value) {
			catHidden.value = value; catInput.value = value;
			marcaHidden.value = ''; marcaInput.value = ''; marcaInput.disabled = !value;
			if (onCambio) onCambio();
		}
		function aplicarMarca(value) {
			marcaHidden.value = value; marcaInput.value = value;
			if (onCambio) onCambio();
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

		return {
			// Solo rellena si la fila sigue vacía, nunca pisa una selección que el usuario ya hizo a mano.
			sugerir: function (segmento, categoria, marca) {
				if (segHidden.value) return;
				aplicarSeg(segmento);
				aplicarCat(categoria);
				aplicarMarca(marca);
			}
		};
	}

	// Encadena Segmento -> Sector -> Categoría -> Marca, solo Meta de Compras: el nombre impreso de cada categoría es "Sector + Categoría + Marca".
	// onMarcaElegida se llama solo cuando la Marca queda con valor real; lo usa Meta de Compras para sugerir en Cabeceras/Rumas/Perchas (sin Sector).
	function bindCascadaComboConSector(tr, onMarcaElegida) {
		var segInput = tr.querySelector('.seg-input'), segHidden = tr.querySelector('.seg-select');
		var sectorInput = tr.querySelector('.sector-input'), sectorHidden = tr.querySelector('.sector-select');
		var catInput = tr.querySelector('.cat-input'), catHidden = tr.querySelector('.cat-select');
		var marcaInput = tr.querySelector('.marca-input'), marcaHidden = tr.querySelector('.marca-select');
		var rebateInput = tr.querySelector('.ac-rebate-input');

		// Rebate % conectado al repositorio (ver buscarYAplicarRebate): cualquier cambio en la cascada por encima de Marca invalida el % mostrado.
		// `silencioso=true` (usado por sugerir()) lo salta a propósito: restaurar un borrador no debe tocar el rebate_pct ya guardado.
		function resetearRebate() {
			if (!rebateInput) return;
			rebateInput.value = 0;
			// Bloqueado siempre: este campo nunca se tipea a mano, ni mientras se espera la fila ni cuando el repositorio no tiene el dato (ver buscarYAplicarRebate).
			rebateInput.readOnly = true;
			rebateInput.title = '';
			updatePurchaseRow(tr);
		}

		function aplicarSeg(value, label, silencioso) {
			segHidden.value = value; segInput.value = value;
			sectorHidden.value = ''; sectorInput.value = ''; sectorInput.disabled = !value;
			catHidden.value = ''; catInput.value = ''; catInput.disabled = true;
			marcaHidden.value = ''; marcaInput.value = ''; marcaInput.disabled = true;
			if (!silencioso) resetearRebate();
		}
		function aplicarSector(value, label, silencioso) {
			sectorHidden.value = value; sectorInput.value = value;
			catHidden.value = ''; catInput.value = ''; catInput.disabled = !value;
			marcaHidden.value = ''; marcaInput.value = ''; marcaInput.disabled = true;
			if (!silencioso) resetearRebate();
		}
		function aplicarCat(value, label, silencioso) {
			catHidden.value = value; catInput.value = value;
			marcaHidden.value = ''; marcaInput.value = ''; marcaInput.disabled = !value;
			if (!silencioso) resetearRebate();
		}
		function aplicarMarca(value, label, silencioso) {
			marcaHidden.value = value; marcaInput.value = value;
			if (value && onMarcaElegida && !silencioso) onMarcaElegida(segHidden.value, sectorHidden.value, catHidden.value, value);
		}

		inicializarCombo(segInput, segHidden, function () {
			return Object.keys(catalogo.segmentosSector).map(function (s) { return { value: s, label: s }; });
		}, aplicarSeg);

		inicializarCombo(sectorInput, sectorHidden, function () {
			return Object.keys(catalogo.segmentosSector[segHidden.value] || {}).map(function (s) { return { value: s, label: s }; });
		}, aplicarSector);

		inicializarCombo(catInput, catHidden, function () {
			return Object.keys((catalogo.segmentosSector[segHidden.value] || {})[sectorHidden.value] || {}).map(function (c) { return { value: c, label: c }; });
		}, aplicarCat);

		inicializarCombo(marcaInput, marcaHidden, function () {
			return (((catalogo.segmentosSector[segHidden.value] || {})[sectorHidden.value] || {})[catHidden.value] || []).map(function (m) { return { value: m, label: m }; });
		}, aplicarMarca);

		return {
			// sector es opcional (null al restaurar un borrador); si no viene, se infiere antes de aplicar para seguir la cascada normal.
			sugerir: function (segmento, sector, categoria, marca) {
				if (segHidden.value) return;
				if (!sector) sector = inferirSectorDesde(segmento, categoria, marca);
				aplicarSeg(segmento, null, true);
				if (sector) aplicarSector(sector, null, true);
				aplicarCat(categoria, null, true);
				aplicarMarca(marca, null, true);
			}
		};
	}

	// Marca de Perchas: lista plana, sin cascada. Participación % conectada al repositorio (ver buscarYAplicarParticipacion): al elegir Marca se
	// busca el % real y se bloquea el campo si hay match. `silencioso=true` lo salta, mismo criterio que Rebate.
	function bindMarcaPerchaCombo(tr) {
		var marcaInput = tr.querySelector('.marca-input'), marcaHidden = tr.querySelector('.marca-select');
		function aplicarMarca(value, label, silencioso) {
			marcaHidden.value = value; marcaInput.value = value;
			if (silencioso) return;
			if (value) buscarYAplicarParticipacion(tr, value);
			else resetearParticipacion(tr);
		}
		inicializarCombo(marcaInput, marcaHidden, function () {
			return catalogo.marcasPercha.map(function (m) { return { value: m, label: m }; });
		}, aplicarMarca);

		return {
			sugerir: function (marca) {
				if (marcaHidden.value) return;
				aplicarMarca(marca, null, true);
			}
		};
	}

	// buscarYAplicarParticipacion() busca el % real en repositorio_participacion_percha, clave Ciudad+Marca (Ciudad = Localidad del cliente, o "TODAS" en Distribuidor).
	// Si no hay match, el campo queda editable: nunca bloquea por falta de datos en un repositorio que se sigue poblando.
	function resetearParticipacion(tr) {
		var input = tr.querySelector('.v-participacion');
		if (!input) return;
		input.value = '0%';
		// Bloqueado siempre, mismo criterio que resetearRebate().
		input.readOnly = true;
		input.title = '';
	}
	function buscarYAplicarParticipacion(tr, marca) {
		var input = tr.querySelector('.v-participacion');
		if (!input) return;
		var canal = catalogoDistribuidor.canal === 'distribuidor' ? 'DISTRIBUIDOR' : 'DIRECTA';
		var ciudad = canal === 'DISTRIBUIDOR' ? 'TODAS' : localidadEl.textContent;
		var params = new URLSearchParams({ ciudad: ciudad || '', marca: marca });
		fetch('getters/acuerdo_buscar_participacion.php?' + params.toString())
			.then(function (r) { return r.json(); })
			.then(function (data) {
				// La fila puede haber cambiado de Marca mientras esta consulta estaba en vuelo; solo aplica si el combo sigue mostrando la misma Marca.
				if (tr.querySelector('.marca-select').value !== marca) return;
				if (data && data.ok && data.encontrado) {
					input.value = (Math.round(parseFloat(data.participacion_pct) * 100) / 100) + '%';
					input.readOnly = true;
					input.title = 'Bloqueado — viene del repositorio de Participación.';
				} else {
					// Bloqueado igual sin match, mismo criterio que Rebate: nunca editable a mano, ni mientras el repositorio no tiene el dato.
					input.readOnly = true;
					input.title = 'Bloqueado — todavía no hay Participación % cargada en el repositorio para esta Ciudad/Marca.';
				}
			})
			.catch(function () { /* silencioso: el campo se queda bloqueado en 0 (resetearParticipacion), nunca editable a mano */ });
	}

	// Al completar Segmento+Categoría+Marca en Meta de Compras, sugiere la misma combinación en la 1ra fila vacía de Cabeceras/Rumas/Perchas.
	// Solo la identidad del producto, nunca los valores en dólares (eso lo sigue tipeando el usuario en cada tabla).
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

	// Conecta el Rebate % de Meta de Compras al repositorio self-service, clave Ciudad+Canal+Sector+Categoría+Marca (ver CLAUDE.md "Rebate: rediseño").
	// Ciudad = Localidad del cliente, excepto en Distribuidor donde siempre usa "TODAS". Si no hay match, deja el campo editable.
	function buscarYAplicarRebate(tr, sector, categoria, marca) {
		var rebateInput = tr.querySelector('.ac-rebate-input');
		if (!rebateInput) return;
		var canal = catalogoDistribuidor.canal === 'distribuidor' ? 'DISTRIBUIDOR' : 'DIRECTA';
		var ciudad = canal === 'DISTRIBUIDOR' ? 'TODAS' : localidadEl.textContent;
		var params = new URLSearchParams({ ciudad: ciudad || '', canal: canal, sector: sector || '', categoria: categoria, marca: marca });
		fetch('getters/acuerdo_buscar_rebate.php?' + params.toString())
			.then(function (r) { return r.json(); })
			.then(function (data) {
				// La fila puede haber cambiado de Marca mientras esta consulta estaba en vuelo; solo aplica si el combo sigue mostrando la misma Marca.
				if (tr.querySelector('.marca-select').value !== marca) return;
				if (data && data.ok && data.encontrado) {
					rebateInput.value = (parseFloat(data.rebate_pct) * 100).toFixed(2);
					rebateInput.readOnly = true;
					rebateInput.title = 'Bloqueado — viene del repositorio de Rebate.';
				} else {
					// Bloqueado igual sin match: el campo debe quedar siempre bloqueado, sin excepción, aunque falte el dato. Se queda en 0.
					rebateInput.readOnly = true;
					rebateInput.title = 'Bloqueado — todavía no hay Rebate % cargado en el repositorio para esta combinación.';
				}
				updatePurchaseRow(tr);
			})
			.catch(function () { /* silencioso: el campo se queda bloqueado en 0 (resetearRebate), nunca editable a mano */ });
	}

	// ---------- Meta de Compras ----------
	function addPurchaseRow() {
		var tr = document.createElement('tr');
		// data-key/data-label: solo para la tarjeta mobile (ver style.css), no tocan la lógica.
		var html =
			'<td class="ac-sticky-col" data-key="segmento" data-label="Segmento">' + comboCellHtml('seg', 'Segmento...', false) + '</td>' +
			'<td class="ac-sticky-col ac-sticky-col-2" data-key="sector" data-label="Categoría">' + comboCellHtml('sector', 'Categoría...', true) + '</td>' +
			'<td class="ac-sticky-col ac-sticky-col-3" data-key="categoria" data-label="Subcategoría">' + comboCellHtml('cat', 'Subcategoría...', true) + '</td>' +
			'<td class="ac-sticky-col ac-sticky-col-4" data-key="marca" data-label="Marca">' + comboCellHtml('marca', 'Marca...', true) + '</td>';
		activeMonthsIndices.forEach(function (mIdx) {
			var etiquetaMes = allMonthsShort[mIdx] + (CANAL_USUARIO === 'distribuidor' ? '' : ' ($)');
			html += '<td class="ac-text-right" data-key="mes" data-label="' + etiquetaMes + '"><div class="ac-money-field"><input type="number" step="0.01" class="ac-input ac-mini-input month-input" value="0"></div></td>';
		});
		html +=
			'<td class="ac-text-right ac-col-highlight ac-tabular total-cell" data-key="total" data-label="Total Período">$0.00</td>' +
			// Rebate % conectado al repositorio (ver buscarYAplicarRebate): arranca readonly/0 porque la fila todavía no tiene la cascada completa.
			'<td class="ac-text-right ac-col-highlight" data-key="rebate" data-label="Rebate %"><input type="number" step="0.01" min="0" class="ac-input ac-mini-input ac-rebate-input" value="0" readonly></td>' +
			'<td class="ac-text-right ac-col-highlight ac-tabular est-cell" data-key="estimado" data-label="Valor Estimado a Ganar">$0.00</td>' +
			'<td class="ac-text-center" data-key="acciones"><button type="button" class="ac-icon-btn ac-remove-row"><span class="material-symbols-outlined">delete</span><span class="ac-btn-text">Eliminar Fila</span></button></td>';
		tr.innerHTML = html;
		purchaseBody.appendChild(tr);

		tr._combo = bindCascadaComboConSector(tr, function (seg, sector, cat, marca) {
			sugerirEnOtrasTablas(seg, cat, marca);
			buscarYAplicarRebate(tr, sector, cat, marca);
		});

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
			'<td class="ac-sticky-col" data-key="segmento" data-label="Segmento">' + comboCellHtml('seg', 'Segmento...', false) + '</td>' +
			'<td class="ac-sticky-col ac-sticky-col-2" data-key="categoria" data-label="Categoría">' + comboCellHtml('cat', 'Categoría...', true) + '</td>' +
			'<td class="ac-sticky-col ac-sticky-col-3" data-key="marca" data-label="Marca">' + comboCellHtml('marca', 'Marca...', true) + '</td>';
		activeMonthsIndices.forEach(function (mIdx) {
			html += '<td data-key="mes" data-label="' + allMonthsShort[mIdx] + '"><div class="ac-money-field"><input type="number" step="0.01" class="ac-input ac-mini-input v-val" value="0"></div></td>';
		});
		html += '<td class="ac-tabular v-tot" data-key="total" data-label="Pago Total">$0.00</td><td class="ac-text-center" data-key="acciones"><button type="button" class="ac-icon-btn ac-remove-row"><span class="material-symbols-outlined">delete</span><span class="ac-btn-text">Eliminar Fila</span></button></td>';
		tr.innerHTML = html;
		cabecerasBody.appendChild(tr);
		tr._combo = bindCascadaCombo(tr);
		attachVisListeners(tr);
		actualizarBloqueoPorDistribuidor();
	}

	// ---------- Rumas ----------
	// Celda por mes de solo lectura: el valor se tipea una vez en la leyenda "Valor Ruma x Marca x Mes" (updateRumaLegend) y se replica a esa fila.
	function addRumaRow() {
		var tr = document.createElement('tr');
		var html =
			'<td class="ac-sticky-col" data-key="segmento" data-label="Segmento">' + comboCellHtml('seg', 'Segmento...', false) + '</td>' +
			'<td class="ac-sticky-col ac-sticky-col-2" data-key="categoria" data-label="Categoría">' + comboCellHtml('cat', 'Categoría...', true) + '</td>' +
			'<td class="ac-sticky-col ac-sticky-col-3" data-key="marca" data-label="Marca">' + comboCellHtml('marca', 'Marca...', true) + '</td>';
		activeMonthsIndices.forEach(function (mIdx) {
			html += '<td data-key="mes" data-label="' + allMonthsShort[mIdx] + '"><div class="ac-money-field"><input type="number" step="0.01" class="ac-input ac-mini-input v-val-repetido" value="0" readonly tabindex="-1"></div></td>';
		});
		html += '<td class="ac-tabular v-tot" data-key="total" data-label="Pago Total">$0.00</td><td class="ac-text-center" data-key="acciones"><button type="button" class="ac-icon-btn ac-remove-row"><span class="material-symbols-outlined">delete</span><span class="ac-btn-text">Eliminar Fila</span></button></td>';
		tr.innerHTML = html;
		rumasBody.appendChild(tr);
		tr._combo = bindCascadaCombo(tr, function () { updateRumaLegend(); });

		tr.querySelector('.ac-remove-row').addEventListener('click', function () { marcarSucio(); tr.remove(); updateRumaLegend(); });
		actualizarBloqueoPorDistribuidor();
	}

	// La leyenda es la única fuente editable, un input por CADA fila de la tabla que ya tenga Marca elegida (ver comentario de addRumaRow).
	// El orden es el mismo que la tabla grande, cada input atado por closure a su fila exacta (no por nombre de Marca), sin desambiguar repetidas.
	function updateRumaLegend() {
		var filasConMarca = Array.prototype.filter.call(rumasBody.querySelectorAll('tr'), function (r) {
			return !!r.querySelector('.marca-select').value;
		});

		if (!filasConMarca.length) {
			rumasLegendBody.innerHTML = '<tr><td colspan="2" class="ac-table-empty">Sin datos</td></tr>';
			return;
		}

		rumasLegendBody.innerHTML = filasConMarca.map(function (fila) {
			var marca = fila.querySelector('.marca-select').value;
			var reps = fila.querySelectorAll('.v-val-repetido');
			var valorActual = reps.length ? (parseFloat(reps[0].value) || 0) : 0;
			return '<tr><td>' + escapeHtml(marca) + '</td><td class="ac-text-right"><div class="ac-money-field"><input type="number" step="0.01" min="0" class="ac-input ac-mini-input ac-ruma-legend-input" value="' + valorActual + '"></div></td></tr>';
		}).join('');

		Array.prototype.forEach.call(rumasLegendBody.querySelectorAll('.ac-ruma-legend-input'), function (input, i) {
			var fila = filasConMarca[i];
			input.addEventListener('input', function () {
				var v = parseFloat(input.value) || 0;
				var reps = fila.querySelectorAll('.v-val-repetido');
				Array.prototype.forEach.call(reps, function (rep) { rep.value = v; });
				fila.querySelector('.v-tot').textContent = formatCurr(v * activeMonthsIndices.length);
			});
		});
	}

	// ---------- Perchas ----------
	function addPerchaRow() {
		var tr = document.createElement('tr');
		var html =
			'<td class="ac-sticky-col" data-key="marca" data-label="Marca Perchas">' + comboCellHtml('marca', 'Marca...', false) + '</td>' +
			// Participación conectada al repositorio (ver buscarYAplicarParticipacion): arranca readonly/0%, mismo patrón que el Rebate % de Meta de Compras.
			'<td data-key="participacion" data-label="Participación"><input type="text" class="ac-input ac-mini-input v-participacion" value="0%" readonly></td>' +
			'<td data-key="cantidad" data-label="Max Percha"><input type="number" min="0" max="5" class="ac-input ac-mini-input v-cantidad" value="1"></td>';
		activeMonthsIndices.forEach(function (mIdx) {
			var etiquetaMes = allMonthsShort[mIdx] + (CANAL_USUARIO === 'distribuidor' ? '' : ' ($)');
			html += '<td data-key="mes" data-label="' + etiquetaMes + '"><div class="ac-money-field"><input type="number" step="0.01" class="ac-input ac-mini-input v-val" value="0"></div></td>';
		});
		html += '<td class="ac-tabular v-tot" data-key="total" data-label="Pago Total">$0.00</td><td class="ac-text-center" data-key="acciones"><button type="button" class="ac-icon-btn ac-remove-row"><span class="material-symbols-outlined">delete</span><span class="ac-btn-text">Eliminar Fila</span></button></td>';
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

	// Vuelve las 3 tablas de "Visibilidad y Espacios" a una fila vacía cada una: se llama al desactivar el switch, para no dejar datos "atrapados"
	// detrás del bloqueo visual (ya no se mandan, ver guardar_acuerdo.php $sinVisibilidad, pero es más honesto no dejarlos ahí).
	function resetearZonaVisibilidad() {
		cabecerasBody.innerHTML = '';
		rumasBody.innerHTML = '';
		perchasBody.innerHTML = '';
		addCabeceraRow();
		addRumaRow();
		addPerchaRow();
		updateRumaLegend();
	}

	// El ícono del título refuerza el estado del switch a simple vista (heurística "reconocimiento en vez de recuerdo": un switch solo no dice qué activa).
	function aplicarBloqueoVisibilidad() {
		visibilidadZona.classList.toggle('ac-zona-bloqueada', !visibilidadActiva);
		visibilidadIcon.textContent = visibilidadActiva ? 'visibility' : 'visibility_off';
	}

	visibilidadToggle.addEventListener('change', function () {
		marcarSucio();
		visibilidadActiva = visibilidadToggle.checked;
		if (!visibilidadActiva) resetearZonaVisibilidad();
		aplicarBloqueoVisibilidad();
	});

	// ---------- Recolección de datos para guardar ----------
	function recolectarLineas() {
		var metaCompra = Array.prototype.map.call(purchaseBody.querySelectorAll('tr'), function (r) {
			return {
				segmento: r.querySelector('.seg-select').value,
				sector: r.querySelector('.sector-select').value,
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
			// Las celdas .v-val-repetido están todas espejadas al mismo valor (ver addRumaRow), cualquiera sirve como fuente única.
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

	// Detecta combos donde el usuario tipeó texto pero nunca eligió una opción real: el input visible muestra texto pero el hidden queda vacío,
	// y guardar_acuerdo.php descartaría la fila en silencio. Todos comparten la estructura ".ac-combo > .ac-combo-input + input[hidden]".
	function encontrarSpinnersSinConfirmar() {
		return Array.prototype.filter.call(acuerdoContainer.querySelectorAll('.ac-combo'), function (combo) {
			var input = combo.querySelector('.ac-combo-input');
			var hidden = combo.querySelector('input[type="hidden"]');
			return input && hidden && !input.disabled && input.value.trim() !== '' && !hidden.value;
		}).map(function (combo) { return combo.querySelector('.ac-combo-input'); });
	}

	// Etiqueta legible para el toast de "campo sin confirmar": segunda capa de seguridad además del blur de inicializarCombo(), por si algún
	// flujo raro llega a guardar sin pasar por ese blur, para ubicar el campo exacto en vez de un mensaje genérico.
	function describirCampoCombo(input) {
		if (input === distribuidorSearch) return etiquetaCampoLocal();
		if (input === empresaSearch) return 'Distribuidor';

		var tablaPorId = {
			'ac-purchase-body': 'Meta de Compras',
			'ac-cabeceras-body': 'Cabeceras',
			'ac-rumas-body': 'Rumas',
			'ac-perchas-body': 'Perchas'
		};
		var tbody = input.closest('tbody');
		var etiquetaTabla = tbody && tablaPorId[tbody.id];

		// Meta de Compras usa "Categoría"/"Subcategoría" (nomenclatura de JW) para sector-input/cat-input; las demás tablas siguen con "Categoría".
		var tipoPorClase = etiquetaTabla === 'Meta de Compras'
			? { 'seg-input': 'Segmento', 'sector-input': 'Categoría', 'cat-input': 'Subcategoría', 'marca-input': 'Marca' }
			: { 'seg-input': 'Segmento', 'sector-input': 'Sector', 'cat-input': 'Categoría', 'marca-input': 'Marca' };
		var tipo = Object.keys(tipoPorClase).filter(function (c) { return input.classList.contains(c); })[0];
		var etiquetaTipo = tipo ? tipoPorClase[tipo] : 'Campo';

		return etiquetaTabla ? (etiquetaTipo + ' en ' + etiquetaTabla) : etiquetaTipo;
	}

	function participacionesInvalidas() {
		return Array.prototype.filter.call(perchasBody.querySelectorAll('.v-participacion'), function (input) {
			var num = parseFloat(input.value);
			return input.value.trim() === '' || isNaN(num) || num < 0;
		});
	}

	// Al menos una fila real en alguna de las 4 tablas: solo se exige para Generar Acta, no para Guardar Borrador (puede arrancar vacío).
	function hayAlgunaLineaReal() {
		function algunaFilaConValor(tbody, selector) {
			return Array.prototype.some.call(tbody.querySelectorAll('tr'), function (r) {
				var campo = r.querySelector(selector);
				return campo && !!campo.value;
			});
		}
		return algunaFilaConValor(purchaseBody, '.seg-select')
			|| algunaFilaConValor(cabecerasBody, '.seg-select')
			|| algunaFilaConValor(rumasBody, '.seg-select')
			|| algunaFilaConValor(perchasBody, '.marca-select');
	}

	function validarCabecera(estado) {
		if (!distribuidorSelect.value) { mostrarMensaje('Selecciona un ' + etiquetaCampoLocal() + '.', false); return false; }
		if (selectedStart === null || selectedEnd === null) { mostrarMensaje('Selecciona el Periodo del Acuerdo.', false); return false; }

		var sinConfirmar = encontrarSpinnersSinConfirmar();
		if (sinConfirmar.length) {
			var campo = sinConfirmar[0];
			mostrarMensaje('"' + describirCampoCombo(campo) + '" quedó con un valor que no se eligió de la lista ("' + campo.value + '") — haz click ahí y elige una opción antes de guardar.', false);
			campo.focus();
			campo.classList.add('ac-campo-resaltado');
			setTimeout(function () { campo.classList.remove('ac-campo-resaltado'); }, 1800);
			return false;
		}

		var participacionesMal = participacionesInvalidas();
		if (participacionesMal.length) {
			mostrarMensaje('La Participación de Perchas debe ser un número y no puede quedar vacía ni ser negativa.', false);
			participacionesMal[0].focus();
			return false;
		}

		if (estado !== 'borrador' && !hayAlgunaLineaReal()) {
			mostrarMensaje('Agrega al menos un producto en alguna tabla antes de Generar el Acta (o guardalo como borrador si todavía no está listo).', false);
			return false;
		}

		return true;
	}

	function guardarAcuerdo(estado, onOk, btn) {
		if (guardandoAcuerdo) return;
		if (!validarCabecera(estado)) return;

		guardandoAcuerdo = true;
		if (btn) acBotonCargando(btn, true);
		// Mismo feedback que "Previsualización": aplica solo al guardado final ('generado'), que puede tardar (arma el PDF con Dompdf).
		if (estado === 'generado') acMostrarCargandoPantalla('Generando el Acta');

		var payload = {
			acuerdo_id: acuerdoId,
			pos_id: distribuidorSelect.value,
			anio: parseInt(anioSelect.value, 10),
			mes_inicio: selectedStart,
			mes_fin: selectedEnd,
			estado: estado,
			sin_visibilidad: !visibilidadActiva,
			lineas: recolectarLineas(),
			origen_precarga: origenPrecarga
		};

		fetch('getters/guardar_acuerdo.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload)
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				// "Ya tiene un Acta generada" (regla de 1 Acta por Local+Período) usa SweetAlert2, no el toast genérico, solo informativo.
				if (data.duplicado) {
					Swal.fire({
						icon: 'warning',
						title: 'Acta ya generada',
						text: data.message,
						confirmButtonText: 'Entendido'
					});
					return;
				}
				if (!data.ok) { mostrarMensaje(data.message, false); return; }
				acuerdoId = data.acuerdo_id;
				documentoNo = data.documento_no;
				formSucio = false;
				// Solo tiene sentido limpiar `origenPrecarga` cuando el guardado que se consolida es el final ('generado'), nunca en un
				// "Guardar Borrador" intermedio: si no, guardar_acuerdo.php nunca marcaba esas filas como 'usada' (bug real confirmado).
				if (estado === 'generado') origenPrecarga = null;
				// Con onOk, el llamador muestra su propio mensaje de éxito (más específico, ej. "PDF generado") — mostrar acá también el genérico
				// del backend duplicaba la alerta (bug real reportado). Sin onOk (Guardar Borrador), el genérico sigue siendo la única confirmación.
				if (onOk) onOk(); else mostrarMensaje(data.message, true);
			})
			.catch(function () { mostrarMensaje('Error de conexión. Intenta nuevamente.', false); })
			.finally(function () {
				guardandoAcuerdo = false;
				if (btn) acBotonCargando(btn, false);
				if (estado === 'generado') acOcultarCargandoPantalla();
			});
	}

	document.getElementById('ac-generar-acta').addEventListener('click', mostrarPreview);
	var guardarBorradorBtn = document.getElementById('ac-guardar-borrador');
	guardarBorradorBtn.addEventListener('click', function () {
		guardarAcuerdo('borrador', null, guardarBorradorBtn);
	});

	// ---------- Preview / Acta ----------
	var actaModalOverlay = document.getElementById('ac-acta-modal-overlay');
	var actaPdfFrame = document.getElementById('ac-acta-pdf-frame');
	var actaGenerarBtn = document.getElementById('ac-acta-generar-pdf');
	var actaDescargarBtn = document.getElementById('ac-acta-descargar-pdf');
	var actaZoomInBtn = document.getElementById('ac-acta-zoom-in');
	var actaZoomOutBtn = document.getElementById('ac-acta-zoom-out');
	var actaZoomLabel = document.getElementById('ac-acta-zoom-label');
	var actaCanvasWrap   = document.getElementById('ac-acta-canvas-wrap');
	var actaCanvas       = document.getElementById('ac-acta-canvas');
	var actaCanvasEstado = document.getElementById('ac-acta-canvas-estado');

	// pdfGenerado: true recién después de "Generar PDF" (el único click que guarda en la base); "Previsualización" no guarda nada.
	var pdfGenerado = false;
	var previewBlobUrl = null; // URL.createObjectURL() del PDF de preview — hay que revocarla para no filtrar memoria.
	var pdfUrlActual = '';     // lo que cargan el iframe y el zoom ahora mismo (blob de preview, o el PDF real ya generado).
	var zoomActual = 100;

	function actualizarZoomLabel() { actaZoomLabel.textContent = zoomActual + '%'; }

	// Móvil real: mismo arreglo de PDF.js que Historial, ver pdf-preview.js — zoom +/- pasa directo como zoomPct.
	function esMovilAngosto() { return window.matchMedia('(max-width: 760px)').matches; }
	function aplicarZoom() {
		if (!pdfUrlActual) return;
		actualizarZoomLabel();
		if (esMovilAngosto()) {
			actaPdfFrame.classList.add('hidden');
			actaCanvasWrap.classList.remove('hidden');
			actaCanvasEstado.textContent = 'Cargando vista previa…';
			actaCanvasEstado.classList.remove('hidden');
			actaCanvas.classList.add('hidden');
			window.acRenderizarPdfEnCanvas(pdfUrlActual, actaCanvas, { zoomPct: zoomActual })
				.then(function () {
					actaCanvasEstado.classList.add('hidden');
					actaCanvas.classList.remove('hidden');
				})
				.catch(function () {
					actaCanvasEstado.textContent = 'No se pudo mostrar la vista previa.';
				});
			return;
		}
		actaCanvasWrap.classList.add('hidden');
		actaPdfFrame.classList.remove('hidden');
		// #toolbar=0&navpanes=0 oculta la barra del visor nativo (ya tenemos nuestros botones), funciona igual con blob: URL que con URL normal.
		// Reasignar iframe.src solo cambiando el #fragment no dispara recarga real (el navegador lo trata como salto de ancla), por eso pasa por about:blank.
		actaPdfFrame.src = 'about:blank';
		window.setTimeout(function () {
			actaPdfFrame.src = pdfUrlActual + '#toolbar=0&navpanes=0&zoom=' + zoomActual;
		}, 30);
	}
	actaZoomInBtn.addEventListener('click', function () { zoomActual = Math.min(300, zoomActual + 25); aplicarZoom(); });
	actaZoomOutBtn.addEventListener('click', function () { zoomActual = Math.max(25, zoomActual - 25); aplicarZoom(); });

	function deshabilitarDescarga() {
		actaDescargarBtn.classList.add('ac-btn-disabled');
		actaDescargarBtn.setAttribute('aria-disabled', 'true');
		actaDescargarBtn.removeAttribute('href');
		actaDescargarBtn.removeAttribute('download');
	}
	// download (sin target=_blank): "Descargar PDF" debe bajar el archivo directo, no abrir otra pestaña con el visor del navegador.
	function habilitarDescarga(url, nombreArchivo) {
		actaDescargarBtn.href = url;
		actaDescargarBtn.setAttribute('download', nombreArchivo);
		actaDescargarBtn.classList.remove('ac-btn-disabled');
		actaDescargarBtn.removeAttribute('aria-disabled');
	}

	// "Previsualización" no guarda nada en la base: arma el PDF al vuelo desde lo que hay en pantalla y lo muestra como blob: URL. Corre las
	// mismas validaciones que guardarAcuerdo, pero sin exigir ninguna línea real (previsualizar algo incompleto es válido).
	var generarActaBtn = document.getElementById('ac-generar-acta');

	function mostrarPreview() {
		if (!validarCabecera('borrador')) return;

		// Feedback de carga: armar el PDF con Dompdf tarda un momento, sin esto el usuario clickeaba varias veces pensando que estaba colgado.
		// acMostrarCargandoPantalla() usa un overlay fijo centrado en la pantalla (acMostrarCargando(acuerdoContainer) quedaba fuera de vista, el form es muy alto).
		acBotonCargando(generarActaBtn, true);
		acMostrarCargandoPantalla('Generando la vista previa del Acta');

		var payload = {
			pos_id: distribuidorSelect.value,
			distribuidor_nombre: distribuidorSearch.value,
			localidad: localidadEl.textContent,
			anio: parseInt(anioSelect.value, 10),
			mes_inicio: selectedStart,
			mes_fin: selectedEnd,
			// Este endpoint nunca abre conexión a la base; el canal ya lo sabe el cliente, se manda para que la vista previa use el formato correcto.
			es_distribuidor: catalogoDistribuidor.canal === 'distribuidor',
			// "Empresa Distribuidora" (se muestra como "Distribuidor" en la UI) va en "Estimado(a)" del Acta, separado del "Local". Vacío en Directo.
			empresa_distribuidora: empresaSearch.value,
			// Switch "Visibilidad y Espacios", independiente del canal (ver includes/acta_pdf.php $sinVisibilidad).
			sin_visibilidad: !visibilidadActiva,
			lineas: recolectarLineas()
		};

		fetch('getters/previsualizar_acta_pdf.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload)
		})
			.then(function (r) {
				if (!r.ok) throw new Error('preview');
				return r.blob();
			})
			.then(function (blob) {
				if (previewBlobUrl) URL.revokeObjectURL(previewBlobUrl);
				previewBlobUrl = URL.createObjectURL(blob);
				pdfUrlActual = previewBlobUrl;
				zoomActual = 100;
				pdfGenerado = false;
				deshabilitarDescarga();
				aplicarZoom();
				actaModalOverlay.classList.add('ac-modal-open');
			})
			.catch(function () { mostrarMensaje('No se pudo armar la vista previa. Intenta nuevamente.', false); })
			.finally(function () {
				acBotonCargando(generarActaBtn, false);
				acOcultarCargandoPantalla();
			});
	}

	// Deja el formulario listo para el siguiente Acuerdo: el usuario puede estar registrando muchos PDV seguidos, sin arrastrar datos del anterior.
	function limpiarFormularioParaNuevoAcuerdo() {
		acuerdoId = null;
		documentoNo = null;
		origenPrecarga = null;
		distribuidorSelect.value = '';
		distribuidorSearch.value = '';
		if (CANAL_USUARIO === 'distribuidor') {
			empresaSelect.value = '';
			empresaSearch.value = '';
			distribuidorSearch.disabled = true;
		}
		localidadEl.textContent = '—';
		aplicarTrimestre(0); // vuelve a Q1 por defecto (ya llama a syncTables())
		actualizarBloqueoPorDistribuidor();
		visibilidadActiva = true;
		visibilidadToggle.checked = true;
		aplicarBloqueoVisibilidad();
		// "Agregar Fila" pudo quedar bloqueado por una Acta precargada anterior (ver bloquearFilasPrecargadas()); el siguiente Acuerdo empieza limpio.
		var btnAgregarPurchase = document.getElementById('ac-add-purchase-row');
		if (btnAgregarPurchase) { btnAgregarPurchase.disabled = false; btnAgregarPurchase.title = ''; }
		desbloquearAgregarOtrasTablas();
		formSucio = false;
	}

	// Acá es donde de verdad se "genera": crea el acuerdo como 'generado' (snapshot del PDF, ver guardar_acuerdo.php). Previsualización no toca la base.
	actaGenerarBtn.addEventListener('click', function () {
		guardarAcuerdo('generado', function () {
			pdfGenerado = true;
			var url = 'getters/generar_acta_pdf.php?id=' + acuerdoId + '&t=' + Date.now();
			if (previewBlobUrl) { URL.revokeObjectURL(previewBlobUrl); previewBlobUrl = null; }
			pdfUrlActual = url;
			habilitarDescarga(url, 'Acta_' + documentoNo + '.pdf');
			aplicarZoom();
			mostrarMensaje('PDF generado. Ya puedes descargarlo.', true);
			limpiarFormularioParaNuevoAcuerdo();
			// El backend marca la precarga como 'usada', pero la campanita no se refrescaba sola tras generar (mismo patrón que index.php al cambiar de módulo).
			if (window.acAlertasFirmaRefrescar) window.acAlertasFirmaRefrescar();
		}, actaGenerarBtn);
	});

	function cerrarModalActa() {
		actaModalOverlay.classList.remove('ac-modal-open');
		actaPdfFrame.src = '';
		if (previewBlobUrl) { URL.revokeObjectURL(previewBlobUrl); previewBlobUrl = null; }
		pdfUrlActual = '';
		if (!pdfGenerado) {
			mostrarToast('Cerraste la vista previa sin generar el PDF. El Acuerdo no quedó guardado.', 'warning');
		}
	}
	document.getElementById('ac-acta-modal-close').addEventListener('click', cerrarModalActa);
	actaModalOverlay.addEventListener('click', function (e) {
		if (e.target === actaModalOverlay) cerrarModalActa();
	});

	// "Agregar Fila" en Meta de Compras agrega también una fila vacía en Cabeceras/Rumas/Perchas, lista para la sugerencia (ver sugerirEnOtrasTablas).
	// Los botones de las otras 3 tablas siguen agregando solo ahí, para cuando el usuario necesita una fila extra en una sola tabla.
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
	// input event sintético: las filas se llenan seteando .value por código (no tipeando), lo que no dispara 'input'; reusa el recálculo existente.
	function dispararInput(el) {
		if (el) el.dispatchEvent(new Event('input', { bubbles: true }));
	}

	function llenarValoresMensuales(inputs, valoresMensuales) {
		Array.prototype.forEach.call(inputs, function (input, i) {
			var mes = activeMonthsIndices[i];
			input.value = (valoresMensuales && valoresMensuales[String(mes)]) || 0;
		});
	}

	// Reconstruye las 4 tablas desde las líneas guardadas, en vez de la fila vacía única de syncTables(). Para Actas viejas sin Sector persistido,
	// `fila.sector` viene null y sugerir() lo sigue infiriendo solo (fallback de compatibilidad).
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
				tr._combo.sugerir(fila.segmento, fila.sector || null, fila.categoria, fila.marca);
				llenarValoresMensuales(tr.querySelectorAll('.month-input'), fila.valores_mensuales);
				tr.querySelector('.ac-rebate-input').value = ((fila.rebate_pct || 0) * 100).toFixed(2);
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
			// La leyenda se reconstruye una vez al final, con todas las filas ya seteadas; llamada fila por fila leería valores todavía en 0.
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
		origenPrecarga = null; // un Borrador nunca viene de una precarga
		var btnAgregarPurchase = document.getElementById('ac-add-purchase-row');
		if (btnAgregarPurchase) { btnAgregarPurchase.disabled = false; btnAgregarPurchase.title = ''; } // por si quedó bloqueado de una precarga anterior en esta sesión
		desbloquearAgregarOtrasTablas();

		anioSelect.value = a.anio;
		selectedStart = a.mes_inicio;
		selectedEnd = a.mes_fin;
		activeMonthsIndices = [];
		for (var i = selectedStart; i <= selectedEnd; i++) activeMonthsIndices.push(i);
		// Borradores viejos podrían tener un rango que no calza con ningún Q1-Q4: se deja el select como esté, pero se respetan los meses guardados.
		for (var q = 0; q < TRIMESTRES.length; q++) {
			if (TRIMESTRES[q][0] === selectedStart && TRIMESTRES[q][1] === selectedEnd) {
				periodoSelect.value = String(q);
				break;
			}
		}
		updatePickerUI();

		// Canal Distribuidor: hay que fijar la Empresa del cliente antes de setear el Distribuidor, porque su combo arma opciones desde la Empresa.
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

		// No se llama a resetearZonaVisibilidad() aunque esté desactivado: poblarTablasConLineas() ya reconstruye desde a.lineas (vacías si el switch estaba apagado).
		visibilidadActiva = !a.sin_visibilidad;
		visibilidadToggle.checked = visibilidadActiva;
		aplicarBloqueoVisibilidad();

		poblarTablasConLineas(a.lineas);
		// Cargar un borrador no es un cambio "sin guardar" propio: recién se vuelve sucio si el usuario lo edita a partir de acá.
		formSucio = false;
		mostrarMensaje('Borrador #' + a.documento_no + ' cargado. Puedes seguir editándolo.', true);
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

	// Deja readonly/disabled las celdas de cada fila de Meta de Compras poblada por una precarga: Segmento/Categoría(sector) y los 3 montos
	// siempre bloqueados; Subcategoría/Marca solo si vinieron resueltas del historial. `lineasMeta` y `purchaseBody` están en el mismo orden.
	function bloquearFilasPrecargadas(lineasMeta) {
		var filas = purchaseBody.querySelectorAll('tr');
		Array.prototype.forEach.call(filas, function (tr, i) {
			var fila = lineasMeta[i];
			if (!fila || !fila.bloqueado) return;
			Array.prototype.forEach.call(tr.querySelectorAll('.month-input'), function (inp) { inp.readOnly = true; });
			// Segmento/Sector solo se bloquean si vinieron resueltos: si quedó ambiguo (ver obtener_precarga_detalle()), la fila sigue el cascade
			// normal, si no bloquearla la dejaría trabada para siempre sin forma de completarla.
			if (fila.segmento) {
				tr.querySelector('.seg-input').disabled = true;
				tr.querySelector('.seg-input').classList.add('ac-combo-input-precargado');
				tr.querySelector('.sector-input').disabled = true;
				tr.querySelector('.sector-input').classList.add('ac-combo-input-precargado');
			}
			if (fila.categoria) {
				tr.querySelector('.cat-input').disabled = true;
				tr.querySelector('.cat-input').classList.add('ac-combo-input-precargado');
			}
			if (fila.marca) {
				tr.querySelector('.marca-input').disabled = true;
				tr.querySelector('.marca-input').classList.add('ac-combo-input-precargado');
			}
			// La fila no debe poder eliminarse: la Acta precargada es una estructura fija que el asesor solo completa. Se deshabilita el botón
			// en vez de sacarlo del DOM para no tocar el resto del layout.
			var btnEliminar = tr.querySelector('.ac-remove-row');
			if (btnEliminar) { btnEliminar.disabled = true; btnEliminar.title = 'Esta fila viene de una Acta precargada — no se puede quitar'; }
		});
		// "Agregar Fila" de Meta de Compras también se bloquea: la tabla es fija mientras la Acta vino de una precarga, el asesor solo completa.
		var btnAgregar = document.getElementById('ac-add-purchase-row');
		if (btnAgregar) { btnAgregar.disabled = true; btnAgregar.title = 'Esta Acta viene de una precarga — la tabla de Meta de Compras es fija'; }
	}

	// Cabeceras/Rumas/Perchas no vienen en el Excel de Cuotas, no hay nada que autocompletar. Se dejan tantas filas vacías como Meta de Compras
	// trajo (para no clickear "Agregar Fila" una por una); a diferencia de Meta de Compras, acá "Eliminar Fila" sigue habilitado.
	function generarFilasVaciasOtrasTablas(cantidadLineasMeta) {
		var cantidad = cantidadLineasMeta > 0 ? cantidadLineasMeta : 1;
		for (var i = cabecerasBody.querySelectorAll('tr').length; i < cantidad; i++) addCabeceraRow();
		for (var j = rumasBody.querySelectorAll('tr').length; j < cantidad; j++) addRumaRow();
		for (var k = perchasBody.querySelectorAll('tr').length; k < cantidad; k++) addPerchaRow();
	}

	// Mismo criterio que bloquearFilasPrecargadas() pero para las filas espejo de Cabeceras/Rumas/Perchas: la fila i corresponde a la línea i de
	// Meta de Compras (mismo orden/conteo). Si esa línea trajo Segmento+Subcategoría+Marca resueltos, se copia y bloquea; si no, cascade normal.
	function espejarIdentidadOtrasTablas(lineasMeta) {
		var filasCab = cabecerasBody.querySelectorAll('tr');
		var filasRuma = rumasBody.querySelectorAll('tr');
		var filasPercha = perchasBody.querySelectorAll('tr');
		lineasMeta.forEach(function (fila, i) {
			if (fila.segmento && fila.categoria && fila.marca) {
				[filasCab[i], filasRuma[i]].forEach(function (tr) {
					if (!tr || !tr._combo) return;
					tr._combo.sugerir(fila.segmento, fila.categoria, fila.marca);
					['.seg-input', '.cat-input', '.marca-input'].forEach(function (sel) {
						var input = tr.querySelector(sel);
						if (input) { input.disabled = true; input.classList.add('ac-combo-input-precargado'); }
					});
				});
			}
			var trPercha = filasPercha[i];
			if (fila.marca && trPercha && trPercha._comboMarca) {
				trPercha._comboMarca.sugerir(fila.marca);
				var marcaInput = trPercha.querySelector('.marca-input');
				if (marcaInput) { marcaInput.disabled = true; marcaInput.classList.add('ac-combo-input-precargado'); }
				// A diferencia de restaurar un borrador (donde `sugerir()` no pisa una Participación ya guardada), acá la fila es nueva: se busca
				// en vivo el % real del repositorio, igual que si el asesor hubiera elegido la Marca a mano.
				buscarYAplicarParticipacion(trPercha, fila.marca);
			}
		});
	}

	function bloquearAgregarOtrasTablas() {
		['ac-add-cabecera-row', 'ac-add-ruma-row', 'ac-add-percha-row'].forEach(function (id) {
			var btn = document.getElementById(id);
			if (btn) { btn.disabled = true; btn.title = 'Esta Acta viene de una precarga. Completa las filas ya generadas, no se agregan más.'; }
		});
	}

	function desbloquearAgregarOtrasTablas() {
		['ac-add-cabecera-row', 'ac-add-ruma-row', 'ac-add-percha-row'].forEach(function (id) {
			var btn = document.getElementById(id);
			if (btn) { btn.disabled = false; btn.title = ''; }
		});
	}

	function aplicarPrecarga(p, trimestre, anio) {
		acuerdoId = null;
		documentoNo = null;
		origenPrecarga = { pos_id: p.pos_id, trimestre: trimestre, anio: anio };

		anioSelect.value = p.anio;
		selectedStart = p.mes_inicio;
		selectedEnd = p.mes_fin;
		activeMonthsIndices = [];
		for (var i = selectedStart; i <= selectedEnd; i++) activeMonthsIndices.push(i);
		for (var q = 0; q < TRIMESTRES.length; q++) {
			if (TRIMESTRES[q][0] === selectedStart && TRIMESTRES[q][1] === selectedEnd) {
				periodoSelect.value = String(q);
				break;
			}
		}
		updatePickerUI();

		// Mismo criterio que aplicarBorrador(): en canal Distribuidor hay que fijar la Empresa antes que el Distribuidor.
		if (catalogoDistribuidor.canal === 'distribuidor') {
			var empresaDeCliente = null;
			Object.keys(catalogoDistribuidor.empresas).some(function (emp) {
				var match = catalogoDistribuidor.empresas[emp].some(function (c) { return c.pos_id === p.pos_id; });
				if (match) { empresaDeCliente = emp; }
				return match;
			});
			if (empresaDeCliente) {
				empresaSelect.value = empresaDeCliente;
				empresaSearch.value = empresaDeCliente;
				distribuidorSearch.disabled = false;
			}
		}
		distribuidorSelect.value = p.pos_id;
		distribuidorSearch.value = p.distribuidor;
		localidadEl.textContent = p.localidad || '—';
		actualizarBloqueoPorDistribuidor();

		// Acta nueva de verdad (no un borrador restaurado): Visibilidad arranca en su estado por defecto, igual que "Nuevo Acuerdo".
		visibilidadActiva = true;
		visibilidadToggle.checked = true;
		aplicarBloqueoVisibilidad();

		poblarTablasConLineas(p.lineas);
		// Orden importa: generarFilasVaciasOtrasTablas() termina en actualizarBloqueoPorDistribuidor() (rehabilita todos los .seg-input),
		// así que bloquearFilasPrecargadas() debe ir DESPUÉS de generar filas o desharía el bloqueo de Segmento en Meta de Compras.
		generarFilasVaciasOtrasTablas(p.lineas.meta_compra.length);
		bloquearFilasPrecargadas(p.lineas.meta_compra);
		espejarIdentidadOtrasTablas(p.lineas.meta_compra);
		bloquearAgregarOtrasTablas();

		// Cargar la precarga no es en sí un cambio "sin guardar": recién se vuelve sucio si el asesor edita a partir de acá (mismo criterio que un Borrador).
		formSucio = false;
		mostrarMensaje('Acta precargada cargada. Completa lo que falte y genera el Acta.', true);
	}

	function cargarPrecarga(posId, trimestre, anio) {
		var params = new URLSearchParams({ pos_id: posId, trimestre: trimestre, anio: anio });
		fetch('getters/obtener_acta_precargada.php?' + params.toString())
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) { mostrarMensaje(data.message || 'No se pudo cargar la Acta precargada.', false); return; }
				aplicarPrecarga(data.precarga, trimestre, anio);
			})
			.catch(function () { mostrarMensaje('Error de conexión al cargar la Acta precargada.', false); });
	}

	// La campanita vive en assets/js/alertas-firma.js, pero cargar la precarga en el formulario solo lo puede hacer este módulo.
	window.acRegistrarCargarPrecarga = cargarPrecarga;

	// El modal "Mis Borradores" vive en Historial, pero cargar un borrador solo lo puede hacer este módulo (el estado de las 4 tablas vive acá).
	window.acRegistrarCargarBorrador = cargarBorrador;

	// ---------- Switch de canal para superdesarrollador sin cartera propia (ver esModoAdminSinCartera() en registrar.php) ----------
	// La mayoría del layout (título, badge, labels, "1. Meta de Compras en Cajas/Dólares", etc.) se arma server-side a partir de
	// CANAL_USUARIO — recalcular todo eso en JS duplicaría demasiado texto/HTML ya existente en PHP. Más simple y sin riesgo: guardar
	// la elección en sesión y recargar la página completa, mismo criterio que ya usa esta app ("todo se renderiza una vez al entrar").
	var canalAdminGroup = document.getElementById('ac-canal-admin-group');
	if (canalAdminGroup) {
		function cambiarCanalAdmin(canal) {
			fetch('getters/admin_set_canal.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ canal: canal })
			})
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (!data.ok) { mostrarMensaje(data.message || 'No se pudo cambiar de canal.', false); return; }
					location.reload();
				})
				.catch(function () { mostrarMensaje('Error de conexión al cambiar de canal.', false); });
		}
		Array.prototype.forEach.call(canalAdminGroup.querySelectorAll('.ac-seg-pill'), function (btn) {
			btn.addEventListener('click', function () {
				if (btn.classList.contains('ac-seg-pill-activo')) return;
				// El bloqueo duro de antes ("Guarda o descarta los cambios...") no daba ninguna pista de QUÉ había que guardar/descartar —
				// confuso cuando el usuario no recuerda haber tocado nada. Reemplazado por una confirmación real (mismo componente que ya
				// usa el resto de la app para "esto puede perder datos"): explica la consecuencia real (recarga la página, se pierde lo no
				// guardado) y deja seguir con un solo click, sin tener que ir a buscar qué campo "ensució" el formulario.
				if (!formSucio) { cambiarCanalAdmin(btn.dataset.canal); return; }
				Swal.fire({
					icon: 'warning',
					title: 'Cambiar de canal',
					text: 'Cambiar de canal recarga la página y se pierde cualquier dato del Acuerdo que no hayas guardado. ¿Continuar?',
					showCancelButton: true,
					confirmButtonText: 'Sí, cambiar de canal',
					cancelButtonText: 'Cancelar'
				}).then(function (resultado) {
					if (resultado.isConfirmed) cambiarCanalAdmin(btn.dataset.canal);
				});
			});
		});
	}

	cargarDatosIniciales();
})();

(function () {
	var allMonthsShort = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
	var allMonthsLong = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
	// El periodo del acuerdo se maneja en trimestres fijos (Q1-Q4), no en rango
	// libre — cada entrada es [mesInicio, mesFin] (0=Ene). Índice del array =
	// value de <option> en #ac-periodo-select.
	var TRIMESTRES = [[0, 2], [3, 5], [6, 8], [9, 11]];

	// Catálogo (Segmento -> Categoría -> [Marcas]) y Distribuidores se cargan
	// en vivo desde la base (ver getters/acuerdo_catalogo.php y
	// getters/acuerdo_distribuidores.php) — nunca hardcodeados aquí.
	// segmentos: árbol Segmento->Categoría->Marca, usado por Cabeceras/Rumas/
	// Perchas. segmentosSector: árbol Segmento->Sector->Categoría->Marca,
	// SOLO para Meta de Compras (ver bindCascadaComboConSector más abajo).
	var catalogo = { segmentos: {}, marcasPercha: [], segmentosSector: {} };
	// canal/empresas/clientes vienen de getters/acuerdo_distribuidores.php,
	// filtrados por el `supervisor` del usuario logueado — ver CANAL_USUARIO
	// (impreso por registrar.php desde la sesión) y canalDeSupervisor() en
	// functions.php. `empresas` solo tiene datos si canal==='distribuidor'
	// (agrupado por tipo_distribuidor); `clientes` es la lista plana para
	// Directo/Mayorista.
	var catalogoDistribuidor = { canal: 'directo', empresas: {}, clientes: [] };

	// Etiqueta dinámica del campo pos_id (2026-08-24, pedido explícito): en
	// Directo vuelve a decir "Distribuidor" (como antes del rename de
	// 2026-08-20); Distribuidor sigue diciendo "Local" para no pisar el otro
	// campo de esa pantalla (la empresa, que se ve "Distribuidor"). Mismo
	// criterio en todos los textos generados por JS que nombran este campo.
	function etiquetaCampoLocal() { return CANAL_USUARIO === 'distribuidor' ? 'Local' : 'Distribuidor'; }

	var selectedStart = 0;
	var selectedEnd = 2;
	var activeMonthsIndices = [0, 1, 2];
	var acuerdoId = null;
	var documentoNo = null;
	// Fase 2 del Repositorio de Cuotas (2026-08-25): si el formulario actual
	// vino de una Acta precargada (cargarPrecarga()), se manda junto con el
	// guardado para que guardar_acuerdo.php marque esas filas como 'usada'.
	// null en cualquier otro caso (Nuevo Acuerdo, Borrador).
	var origenPrecarga = null;

	// ---------- Switch "Visibilidad y Espacios" (2026-08-24) ----------
	// Activado por defecto (mismo comportamiento de siempre, sin cambios). Al
	// desactivarlo, el Acta sale en el formato "sin visibilidad" (sin
	// Cabeceras ni Rumas&Perchas, ver includes/acta_pdf.php $sinVisibilidad) —
	// independiente del canal del usuario, ver ese archivo para el porqué.
	var visibilidadActiva = true;

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

	// Canal Distribuidor mide en Cajas, no en Dólares (2026-08-30, bug real
	// reportado con captura — el PDF/Excel ya distinguía esto, la pantalla
	// interactiva nunca se ajustó): sin signo "$" ni formato de moneda para
	// Distribuidor, solo el número. CANAL_USUARIO es fijo por usuario
	// logueado (ver componentes/registrar/registrar.php), no cambia según
	// qué cliente puntual se elija en el formulario.
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

	// Un solo listener delegado cubre todos los campos tipeados (montos, rebate,
	// participación, cantidad) sin importar que las filas se creen/destruyan
	// dinámicamente — los combos (Distribuidor/Segmento/Categoría/Marca/Sector)
	// no disparan 'input' nativo (comboSeleccionar asigna .value directo), así
	// que esos marcan sucio aparte, en su propio flujo de selección.
	var acuerdoContainer = document.querySelector('.ac-acuerdo');
	acuerdoContainer.addEventListener('input', marcarSucio);

	// Montos en dólares: nunca negativos. El rebate NO se toca acá — el
	// usuario aclaró que va a salir de un repositorio nuevo (todavía sin
	// datos), no tiene sentido validarle un rango a mano ahora.
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
				catalogo.segmentosSector = catRes.segmentos_sector || {};
			}
			if (distRes.ok) {
				catalogoDistribuidor.canal = distRes.canal;
				catalogoDistribuidor.empresas = distRes.empresas || {};
				catalogoDistribuidor.clientes = distRes.clientes || [];
			}

			// Distribuidor: el switch arranca DESACTIVADO por defecto (preserva
			// el comportamiento histórico — hasta 2026-08-24 esas 2 tablas
			// SIEMPRE se ocultaban en el Acta de Distribuidor, sin excepción,
			// ver includes/acta_pdf.php). Directo sigue arrancando activado,
			// sin cambios. Solo aplica a un Acuerdo NUEVO — un borrador ya
			// guardado restaura su propio valor real en aplicarBorrador().
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

	// Solo Meta de Compras persiste Sector en la base — al restaurar un
	// borrador no viene el Sector guardado (ver poblarTablasConLineas), así
	// que se infiere buscando en qué Sector(es) aparece exactamente esa
	// combinación Categoría+Marca dentro del Segmento. Si la marca vende en
	// más de un Sector con la misma Categoría (ej. LAVA en Lavavajillas existe
	// en Crema/Barra/Líquido), se toma el primero — limitación conocida,
	// mismo hueco que ya existía antes (Sector nunca se pudo restaurar de un
	// borrador porque no se guarda en repositorio_acuerdo_lineas).
	function inferirSectorDesde(segmento, categoria, marca) {
		var porSector = catalogo.segmentosSector[segmento] || {};
		return Object.keys(porSector).filter(function (sec) {
			return (porSector[sec][categoria] || []).indexOf(marca) !== -1;
		})[0] || '';
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

	// Clamp de left (2026-08-25, pase de responsividad): sin esto, un combo
	// cerca del borde derecho de una pantalla angosta hacía que
	// left + width se saliera del viewport (el ancho mínimo de 220px no
	// entra completo en varios celulares si el input está a la derecha).
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

	// getOpciones: función que devuelve [{value, label}] — función (no array
	// fijo) porque en los combos encadenados (Categoría/Marca) las opciones
	// válidas cambian según lo que se eligió antes en la fila.
	// Búsqueda por texto restaurada (2026-08-24) — el bloqueo total (readonly,
	// sin poder tipear nada) que se puso el 2026-08-20 tenía un efecto
	// colateral serio no previsto entonces: el panel corta en 60 opciones
	// (ver comboRender más abajo) y sin poder tipear para filtrar, cualquier
	// opción más allá del puesto 60 alfabético quedaba INALCANZABLE — un
	// supervisor con más de 60 locales/clientes (ej. 368 en un caso real)
	// no podía elegir la mayoría de su cartera. Se restaura poder tipear
	// para filtrar, pero se mantiene la regla original de fondo (nunca un
	// valor tipeado sin elegir de verdad queda como "fantasma"): al perder
	// el foco, si lo que quedó escrito no coincide EXACTO con la opción
	// realmente seleccionada, el campo se limpia solo.
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
		// El evento 'focus' NO se dispara de nuevo si el campo ya estaba
		// enfocado (ej: elegís una opción, el campo se queda con el foco a
		// propósito, y volvés a hacer click ahí mismo para elegir otra cosa)
		// — sin este listener de 'click' aparte, el panel no se reabría y se
		// sentía "trabado" hasta hacer click en otro lado y volver.
		input.addEventListener('click', function () {
			if (!comboActivo || comboActivo.input !== input) abrir();
		});
		// Filtra la lista en vivo mientras se tipea — no toca `hidden.value`
		// acá (solo comboSeleccionar() lo hace); si el usuario tipea y se va
		// sin elegir, el blur de abajo detecta el desajuste y limpia todo.
		input.addEventListener('input', function () {
			if (!comboActivo || comboActivo.input !== input) abrir();
			else comboRender(input.value);
		});
		// Sin esto, salir del campo con Tab (en vez de elegir una opción con
		// el mouse) dejaba el panel abierto apuntando al campo anterior. El
		// mousedown+preventDefault() de las opciones evita el blur mientras
		// se hace click en una, así que esto no interfiere con esa selección.
		input.addEventListener('blur', function () {
			if (comboActivo && comboActivo.input === input) comboCerrar();
			// Nunca dejar un valor tipeado que no coincide con una opción
			// real seleccionada — mismo espíritu que el bloqueo total de
			// antes, pero ahora sí se puede tipear para buscar.
			if (input.value !== labelDeSeleccionActual()) {
				hidden.value = '';
				input.value = '';
			}
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

	// El primer campo de cada tabla (Segmento en Meta/Cabeceras/Rumas, Marca en
	// Perchas) queda deshabilitado hasta elegir Distribuidor — no tiene sentido
	// armar líneas de producto antes de saber para quién es el acuerdo. Los
	// siguientes campos de cada cascada (Categoría/Marca/Sector) ya se rigen
	// solos una vez que Segmento tiene valor, así que no hace falta tocarlos.
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

	// value: índice en TRIMESTRES (0=Q1...3=Q4). Separado del listener de
	// arriba para poder reusarlo desde limpiarFormularioParaNuevoAcuerdo() y
	// aplicarBorrador() sin duplicar la lógica de armar activeMonthsIndices.
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
	// Separado de syncTables() para que poblarTablasConLineas() (carga de un
	// borrador) pueda reconstruir los encabezados según el período guardado
	// sin pasar por el reset a una sola fila vacía por tabla.
	function renderTableHeaders() {
		var months = activeMonthsIndices.map(function (i) { return allMonthsShort[i]; });
		var count = months.length;

		purchaseHead.innerHTML =
			// Etiquetas "Categoría"/"Subcategoría" (no "Sector"/"Categoría") a
			// pedido explícito de JW (reunión 2026-08-24): así llaman ellos a
			// estos mismos dos niveles. Solo texto visible — la columna interna
			// sigue siendo 'sector' (clase sector-input, catalogo.segmentosSector),
			// sin tocar acuerdo_catalogo.php ni el mapeo de datos. Cabeceras/Rumas
			// no tienen este nivel intermedio, así que ahí "Categoría" queda igual.
			'<tr><th class="ac-sticky-col">Segmento</th><th class="ac-sticky-col ac-sticky-col-2">Categoría</th><th class="ac-sticky-col ac-sticky-col-3">Subcategoría</th><th class="ac-sticky-col ac-sticky-col-4">Marca</th>' +
			// "($)" solo en canal Directo — Distribuidor mide en Cajas, no en
			// dólares (2026-08-30, mismo fix que formatCurr()/el símbolo "$" de
			// los inputs — ver .ac-acuerdo-distribuidor en style.css).
			months.map(function (m) { return '<th class="ac-text-right">' + m + (CANAL_USUARIO === 'distribuidor' ? '' : ' ($)') + '</th>'; }).join('') +
			'<th class="ac-text-right ac-col-highlight">Total Período</th><th class="ac-text-right ac-col-highlight">Rebate %</th><th class="ac-text-right ac-col-highlight ac-th-2l">Valor Estimado<br>a Ganar</th><th></th></tr>';

		cabecerasHead.innerHTML =
			'<tr><th rowspan="2" class="ac-sticky-col">Segmento</th><th rowspan="2" class="ac-sticky-col ac-sticky-col-2">Categoría</th><th rowspan="2" class="ac-sticky-col ac-sticky-col-3">Marca</th>' +
			'<th colspan="' + count + '">Cabecera Pago x Mes</th><th rowspan="2" class="ac-th-2l">Pago Total<br>Cajas</th><th rowspan="2"></th></tr>' +
			'<tr>' + months.map(function (m) { return '<th>' + m + '</th>'; }).join('') + '</tr>';

		// Rumas visualmente tiene una columna por mes (igual que Cabeceras/Perchas),
		// pero las 'N' celdas están espejadas al mismo valor: el negocio exige un
		// único "valor_mensual_unico" que se repite en todo el periodo, no un
		// valor distinto por mes — ver CLAUDE.md.
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
		updateGrandTotals();
	}

	// Celda de tabla con buscador (input visible) + valor real (input oculto,
	// mismo nombre de clase que antes usaba el <select>, para no tocar el
	// resto del código que lee `.seg-select`/`.cat-select`/`.marca-select`).
	// Ya NO es readonly (era así desde 2026-08-20, se sacó el 2026-08-24):
	// con listas de más de 60 opciones (ej. 368 clientes de un supervisor
	// real) y sin poder tipear para filtrar, cualquier opción más allá del
	// puesto 60 alfabético quedaba inalcanzable — bug real encontrado en
	// producción. Ahora se puede tipear para buscar, pero `inicializarCombo()`
	// sigue sin dejar un valor tipeado sin elegir de verdad (se limpia solo
	// al perder el foco si no coincide con una opción real) — mismo
	// resultado que se buscaba con el readonly, sin el efecto colateral.
	function comboCellHtml(tipo, placeholder, disabled) {
		return '<div class="ac-combo ac-combo-cell">' +
			'<input type="text" class="ac-input ac-mini-input ac-combo-input ' + tipo + '-input" placeholder="' + placeholder + '" autocomplete="off"' + (disabled ? ' disabled' : '') + '>' +
			'<input type="hidden" class="' + tipo + '-select" value="">' +
			'</div>';
	}

	// Encadena Segmento -> Categoría -> Marca en una fila usando el sistema
	// genérico de combobox. Usado por Cabeceras y Rumas (Meta de Compras usa
	// bindCascadaComboConSector, con un orden distinto — ver más abajo).
	// onCambio (opcional) se llama después de cualquier selección — lo usa
	// Rumas para refrescar la leyenda lateral. Devuelve un controlador con
	// .sugerir(seg, cat, marca) para que otras filas puedan aplicarle una
	// sugerencia sin pisar una elección ya hecha.
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

	// Encadena Segmento -> Sector -> Categoría -> Marca — SOLO Meta de
	// Compras. Este orden (a diferencia de Cabeceras/Rumas) fue pedido
	// explícitamente por el usuario tras revisar un Acta real: el nombre
	// impreso de cada categoría es "Sector + Categoría + Marca" (ej. "Crema
	// Lavavajillas LAVA"), así que elegir en ese mismo orden es más intuitivo.
	// onMarcaElegida se llama SOLO cuando la Marca queda con un valor real —
	// lo usa Meta de Compras para sugerir Segmento/Categoría/Marca (sin
	// Sector, esa tabla no lo tiene) en Cabeceras/Rumas/Perchas.
	function bindCascadaComboConSector(tr, onMarcaElegida) {
		var segInput = tr.querySelector('.seg-input'), segHidden = tr.querySelector('.seg-select');
		var sectorInput = tr.querySelector('.sector-input'), sectorHidden = tr.querySelector('.sector-select');
		var catInput = tr.querySelector('.cat-input'), catHidden = tr.querySelector('.cat-select');
		var marcaInput = tr.querySelector('.marca-input'), marcaHidden = tr.querySelector('.marca-select');
		var rebateInput = tr.querySelector('.ac-rebate-input');

		// Rebate % conectado al repositorio (2026-08-27, ver
		// buscarYAplicarRebate más abajo) — cualquier cambio en la cascada
		// por encima de Marca invalida el % que estaba mostrado (venía de OTRA
		// combinación), así que se resetea a editable/0 hasta que se vuelva a
		// completar la fila. `silencioso=true` (usado por sugerir(), abajo) lo
		// salta a propósito: restaurar un borrador/precarga no debe tocar el
		// rebate_pct histórico ya guardado en esa línea.
		function resetearRebate() {
			if (!rebateInput) return;
			rebateInput.value = 0;
			// Bloqueado siempre (2026-08-31, pedido explícito del usuario: "no
			// dejes campos editables, eso rompe lo que me pidieron que esos
			// campos deben estar bloqueados") — este campo nunca se tipea a
			// mano, ni mientras se espera a que se complete la fila ni cuando
			// el repositorio no tiene el dato todavía (ver buscarYAplicarRebate
			// más abajo, mismo criterio en su rama "sin match").
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
			// sector es opcional (null al restaurar un borrador, ver
			// inferirSectorDesde) — si no viene, se infiere antes de aplicar
			// para que Categoría/Marca puedan seguir la cascada normal.
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

	// Marca de Perchas: lista plana, sin cascada de Segmento/Categoría.
	// Participación % conectada al repositorio (2026-08-30, ver
	// buscarYAplicarParticipacion más abajo) — al elegir Marca de verdad se
	// busca el % real y se bloquea el campo si hay match. `silencioso=true`
	// (usado por sugerir(), abajo) lo salta a propósito, mismo criterio que
	// Rebate: restaurar un borrador/precarga no debe tocar la participación
	// ya guardada en esa línea.
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

	// Bloquea/desbloquea el campo de Participación de una fila de Perchas —
	// resetearParticipacion() vuelve a un estado neutral editable (usado al
	// limpiar/cambiar Marca), buscarYAplicarParticipacion() busca el % real en
	// repositorio_participacion_percha (2026-08-30, objetivo final ya
	// aplicado a Rebate el 2026-08-27: "que se autocomplete y bloquee, no se
	// tipee a mano"). La clave del repositorio es Ciudad+Marca — SIN
	// Categoría/Subcategoría (la tabla de Perchas del Acta nunca las guarda,
	// a diferencia de Meta de Compras) y SIN Canal (el Excel real de JW no lo
	// trae, aplica igual para Directo y Distribuidor). Ciudad se resuelve
	// igual que Rebate: la Localidad (CEDI) del cliente elegido para canal
	// Directo, o "TODAS" para Distribuidor (buscarParticipacionPercha(),
	// includes/functions.php, además prueba "RESTO CIUDADES" como catch-all
	// si la ciudad real no tiene fila propia — ver ese comentario para el
	// detalle completo). Si no hay match, el campo queda editable — nunca
	// bloquea al usuario por falta de datos en un repositorio que se sigue
	// poblando de a poco.
	function resetearParticipacion(tr) {
		var input = tr.querySelector('.v-participacion');
		if (!input) return;
		input.value = '0%';
		// Bloqueado siempre, mismo criterio que resetearRebate() (2026-08-31).
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
				// La fila puede haber cambiado de Marca mientras esta consulta
				// estaba en vuelo (el usuario re-eligió rápido) — solo aplica si
				// el combo sigue mostrando la misma Marca que se consultó.
				if (tr.querySelector('.marca-select').value !== marca) return;
				if (data && data.ok && data.encontrado) {
					input.value = (Math.round(parseFloat(data.participacion_pct) * 100) / 100) + '%';
					input.readOnly = true;
					input.title = 'Bloqueado — viene del repositorio de Participación.';
				} else {
					// Bloqueado igual sin match (2026-08-31, mismo pedido explícito
					// que Rebate — nunca editable a mano, ni siquiera mientras el
					// repositorio no tiene el dato todavía).
					input.readOnly = true;
					input.title = 'Bloqueado — todavía no hay Participación % cargada en el repositorio para esta Ciudad/Marca.';
				}
			})
			.catch(function () { /* silencioso: el campo se queda bloqueado en 0 (resetearParticipacion), nunca editable a mano */ });
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

	// Conecta el Rebate % de Meta de Compras al repositorio self-service
	// (repositorio_rebate_producto, 2026-08-27 — objetivo final documentado
	// desde la reunión JW 2026-08-18: "que Rebate % se autocomplete y
	// bloquee, no se tipee a mano"). Se llama al completar Sector+Categoría+
	// Marca en una fila (ver el callback de bindCascadaComboConSector en
	// addPurchaseRow). El repositorio guarda por Ciudad+Canal además de
	// Sector+Categoría+Marca (el mismo producto tiene % distinto según esos
	// 2 — ver CLAUDE.md "Rebate: rediseño — Ciudad+Canal reemplazan a
	// Segmento") — Canal se resuelve del canal real del usuario (mismo
	// criterio que `es_distribuidor` en el resto del proyecto); Ciudad se
	// resuelve de la Localidad (CEDI) del cliente ya elegido, EXCEPTO en
	// Distribuidor, donde el repositorio siempre usa "TODAS" sin importar la
	// ciudad real (confirmado con datos reales: las filas de canal
	// Distribuidor nunca varían por ciudad). Si hay match exacto, bloquea el
	// campo con el valor real; si NO hay match (combinación no cargada, o
	// todavía no se eligió Distribuidor/Local — sin Ciudad no hay cómo
	// buscar), deja el campo editable — nunca bloquea al usuario por falta
	// de datos.
	function buscarYAplicarRebate(tr, sector, categoria, marca) {
		var rebateInput = tr.querySelector('.ac-rebate-input');
		if (!rebateInput) return;
		var canal = catalogoDistribuidor.canal === 'distribuidor' ? 'DISTRIBUIDOR' : 'DIRECTA';
		var ciudad = canal === 'DISTRIBUIDOR' ? 'TODAS' : localidadEl.textContent;
		var params = new URLSearchParams({ ciudad: ciudad || '', canal: canal, sector: sector || '', categoria: categoria, marca: marca });
		fetch('getters/acuerdo_buscar_rebate.php?' + params.toString())
			.then(function (r) { return r.json(); })
			.then(function (data) {
				// La fila puede haber cambiado de Marca mientras esta consulta
				// estaba en vuelo (el usuario re-eligió rápido) — solo aplica si
				// el combo sigue mostrando la misma Marca que se consultó.
				if (tr.querySelector('.marca-select').value !== marca) return;
				if (data && data.ok && data.encontrado) {
					rebateInput.value = (parseFloat(data.rebate_pct) * 100).toFixed(2);
					rebateInput.readOnly = true;
					rebateInput.title = 'Bloqueado — viene del repositorio de Rebate.';
				} else {
					// Bloqueado igual sin match (2026-08-31, pedido explícito —
					// antes se dejaba editable para no trabar el flujo mientras el
					// repositorio se sigue poblando, pero el usuario corrigió: el
					// campo debe quedar SIEMPRE bloqueado, sin excepción, aunque
					// falte el dato). Se queda en 0, sin poder tipearse a mano.
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
		var html =
			'<td class="ac-sticky-col">' + comboCellHtml('seg', 'Segmento...', false) + '</td>' +
			'<td class="ac-sticky-col ac-sticky-col-2">' + comboCellHtml('sector', 'Categoría...', true) + '</td>' +
			'<td class="ac-sticky-col ac-sticky-col-3">' + comboCellHtml('cat', 'Subcategoría...', true) + '</td>' +
			'<td class="ac-sticky-col ac-sticky-col-4">' + comboCellHtml('marca', 'Marca...', true) + '</td>';
		activeMonthsIndices.forEach(function () {
			html += '<td class="ac-text-right"><div class="ac-money-field"><input type="number" step="0.01" class="ac-input ac-mini-input month-input" value="0"></div></td>';
		});
		html +=
			'<td class="ac-text-right ac-col-highlight ac-tabular total-cell">$0.00</td>' +
			// Rebate % conectado al repositorio (2026-08-27, ver
			// buscarYAplicarRebate) — arranca readonly/0 porque la fila todavía
			// no tiene Segmento/Sector/Categoría/Marca completos; se bloquea con
			// el valor real si hay match en repositorio_rebate_producto, o se
			// desbloquea para tipear a mano si no lo hay (ver resetearRebate()).
			'<td class="ac-text-right ac-col-highlight"><input type="number" step="0.01" min="0" class="ac-input ac-mini-input ac-rebate-input" value="0" readonly></td>' +
			'<td class="ac-text-right ac-col-highlight ac-tabular est-cell">$0.00</td>' +
			'<td class="ac-text-center"><button type="button" class="ac-icon-btn ac-remove-row"><span class="material-symbols-outlined">delete</span></button></td>';
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
	// todos los meses de ESA fila (nunca a otra, aunque comparta la misma
	// Marca — cambiado 2026-08-20: antes se agrupaba y compartía valor por
	// Marca, calcando el Acta real, pero el usuario pidió que cada fila de la
	// tabla tenga su propio valor independiente, sin importar si la Marca se
	// repite). Así el usuario nunca tipea directo en los meses.
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

	// La leyenda es la ÚNICA fuente editable, un input por CADA fila de la
	// tabla grande que ya tenga Marca elegida (2026-08-20: antes agrupaba por
	// Marca distinta y compartía un solo valor entre filas repetidas — el
	// usuario pidió que cada fila tenga su propio valor independiente, aunque
	// dos filas compartan la misma Marca, ver comentario de addRumaRow). El
	// orden de las filas de la leyenda es el mismo que el de la tabla grande,
	// y cada input queda atado por closure a SU fila exacta (no por nombre de
	// Marca), así que no hace falta desambiguar Marcas repetidas.
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
			'<td class="ac-sticky-col">' + comboCellHtml('marca', 'Marca...', false) + '</td>' +
			// Participación conectada al repositorio (2026-08-30, ver
			// buscarYAplicarParticipacion) — arranca readonly/0% porque la fila
			// todavía no tiene Marca elegida (mismo patrón que el Rebate % de
			// Meta de Compras); se bloquea con el valor real si hay match en
			// repositorio_participacion_percha, o se desbloquea para tipear a
			// mano si no lo hay (ver resetearParticipacion()).
			'<td><input type="text" class="ac-input ac-mini-input v-participacion" value="0%" readonly></td>' +
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

	// Vuelve las 3 tablas de "Visibilidad y Espacios" a una sola fila vacía
	// cada una (mismo estado inicial que syncTables()) — se llama al
	// desactivar el switch, para no dejar datos cargados "atrapados" detrás
	// del bloqueo visual (que ya de por sí no se van a mandar, ver
	// guardar_acuerdo.php $sinVisibilidad, pero es más honesto no dejarlos ahí).
	function resetearZonaVisibilidad() {
		cabecerasBody.innerHTML = '';
		rumasBody.innerHTML = '';
		perchasBody.innerHTML = '';
		addCabeceraRow();
		addRumaRow();
		addPerchaRow();
		updateRumaLegend();
	}

	// El ícono del título ("visibility"/"visibility_off", mismo glifo con y sin
	// tachar de Material Symbols — la fuente que ya usa toda la app, no hace
	// falta traer un ícono aparte) refuerza el estado del switch a simple
	// vista (heurística de Nielsen "reconocimiento en vez de recuerdo": un
	// switch solo no dice qué activa sin leerlo).
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

	// Detecta campos de spinner (Distribuidor/Empresa/Segmento/Sector/
	// Categoría/Marca, en cualquiera de las 4 tablas) donde el usuario tipeó
	// texto pero nunca hizo click en una opción real de la lista — el input
	// visible muestra ese texto pero el valor real (hidden) queda vacío, y
	// guardar_acuerdo.php descartaría esa fila en silencio sin avisar. Todos
	// los combos comparten la estructura ".ac-combo > .ac-combo-input +
	// input[hidden]" (ver inicializarCombo/comboCellHtml), así que un solo
	// selector cubre Distribuidor/Empresa y las 4 tablas a la vez.
	function encontrarSpinnersSinConfirmar() {
		return Array.prototype.filter.call(acuerdoContainer.querySelectorAll('.ac-combo'), function (combo) {
			var input = combo.querySelector('.ac-combo-input');
			var hidden = combo.querySelector('input[type="hidden"]');
			return input && hidden && !input.disabled && input.value.trim() !== '' && !hidden.value;
		}).map(function (combo) { return combo.querySelector('.ac-combo-input'); });
	}

	// Arma una etiqueta legible ("Marca en Meta de Compras", "Distribuidor")
	// para el toast de "campo sin confirmar" — segunda capa de seguridad
	// además del blur de inicializarCombo() (que ya debería limpiar solo
	// cualquier texto tipeado sin elegir de verdad, ver 2026-08-24) — por si
	// algún flujo raro llega a guardar sin pasar por ese blur, así se puede
	// ubicar el campo exacto en vez de un mensaje genérico que obliga a
	// revisar las 4 tablas a mano.
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

		// Meta de Compras usa "Categoría"/"Subcategoría" (nomenclatura de JW)
		// para sector-input/cat-input; las demás tablas no tienen ese nivel
		// intermedio y siguen llamando "Categoría" al cat-input, sin más.
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

	// Al menos una fila real (con Segmento o Marca elegidos) en alguna de las
	// 4 tablas — solo se exige para Generar Acta, no para Guardar Borrador
	// (un borrador puede arrancar vacío, es lo esperable de un work-in-progress).
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

	function guardarAcuerdo(estado, onOk) {
		if (!validarCabecera(estado)) return;

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
				// "Ya tiene un Acta generada para este trimestre" (regla de un
				// Acta por Local+Período) usa SweetAlert2, no el toast genérico
				// de error — mismo estilo que la confirmación de Eliminar, pero
				// solo informativo (un botón, sin acción que confirmar).
				if (data.duplicado) {
					Swal.fire({
						icon: 'warning',
						title: 'Acta ya generada',
						text: data.message,
						confirmButtonText: 'Entendido'
					});
					return;
				}
				mostrarMensaje(data.message, data.ok);
				if (data.ok) {
					acuerdoId = data.acuerdo_id;
					documentoNo = data.documento_no;
					formSucio = false;
					// Bug real reportado 2026-08-31 (caso real: Carlos Proaño / ROBERT
					// - PONCE COMPANY, Acta ADN-2026-0058 — confirmado contra la base:
					// el Acuerdo se generó bien, pero repositorio_cuota_cliente se
					// quedó en 'pendiente_uso' para siempre, así que nunca desapareció
					// de "Actas Asignadas"). Causa: acá se limpiaba `origenPrecarga`
					// apenas CUALQUIER guardado exitoso, incluido un "Guardar
					// Borrador" intermedio — si el asesor guarda como borrador antes
					// de terminar de completar Subcategoría/Marca y recién más tarde
					// (misma sesión) le da "Generar PDF", ese guardado final ya
					// mandaba `origen_precarga: null`, así que
					// guardar_acuerdo.php nunca marcaba las filas como 'usada' (ver
					// el bloque "Consumir la Acta precargada de origen" en ese
					// archivo). Solo tiene sentido limpiarlo acá cuando el guardado
					// que SÍ se consolidó es el final ('generado') — y ni siquiera
					// hace falta: limpiarFormularioParaNuevoAcuerdo() (llamada desde
					// el onOk de "Generar PDF") ya lo deja en null como parte del
					// reset completo para el próximo Acuerdo. Un "Guardar Borrador"
					// intermedio ya NO lo toca — origenPrecarga sobrevive intacto
					// hasta el guardado final que de verdad lo consume.
					if (estado === 'generado') origenPrecarga = null;
					if (onOk) onOk();
				}
			})
			.catch(function () { mostrarMensaje('Error de conexión. Intenta nuevamente.', false); });
	}

	document.getElementById('ac-generar-acta').addEventListener('click', mostrarPreview);
	document.getElementById('ac-guardar-borrador').addEventListener('click', function () {
		guardarAcuerdo('borrador');
	});

	// ---------- Preview / Acta ----------
	var actaModalOverlay = document.getElementById('ac-acta-modal-overlay');
	var actaPdfFrame = document.getElementById('ac-acta-pdf-frame');
	var actaGenerarBtn = document.getElementById('ac-acta-generar-pdf');
	var actaDescargarBtn = document.getElementById('ac-acta-descargar-pdf');
	var actaZoomInBtn = document.getElementById('ac-acta-zoom-in');
	var actaZoomOutBtn = document.getElementById('ac-acta-zoom-out');
	var actaZoomLabel = document.getElementById('ac-acta-zoom-label');

	// pdfGenerado: true recién después de "Generar PDF" (el único click que de
	// verdad guarda algo en la base) — "Previsualización" ya NO guarda nada,
	// así que cerrar el modal sin haber generado no pierde ningún dato real,
	// solo se avisa por si se le olvidó generar.
	var pdfGenerado = false;
	var previewBlobUrl = null; // URL.createObjectURL() del PDF de preview — hay que revocarla para no filtrar memoria.
	var pdfUrlActual = '';     // lo que cargan el iframe y el zoom ahora mismo (blob de preview, o el PDF real ya generado).
	var zoomActual = 100;

	function actualizarZoomLabel() { actaZoomLabel.textContent = zoomActual + '%'; }

	function aplicarZoom() {
		if (!pdfUrlActual) return;
		actualizarZoomLabel();
		// #toolbar=0&navpanes=0 oculta la barra/miniaturas del visor nativo del
		// navegador (ya tenemos nuestros propios botones) — funciona igual
		// pegado a una blob: URL que a una URL normal del servidor.
		//
		// Reasignar iframe.src a la MISMA url solo cambiando el #fragment no
		// dispara una recarga real — el navegador lo trata como un salto de
		// ancla (como <a href="#x">), no como un documento nuevo, así que el
		// visor de PDF nunca llega a leer el nuevo #zoom=. Pasar por
		// about:blank en el medio fuerza que sí sea una carga nueva cada vez.
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
	// download (sin target=_blank): "Descargar PDF" debe bajar el archivo
	// directo, no abrir otra pestaña con el visor del navegador.
	function habilitarDescarga(url, nombreArchivo) {
		actaDescargarBtn.href = url;
		actaDescargarBtn.setAttribute('download', nombreArchivo);
		actaDescargarBtn.classList.remove('ac-btn-disabled');
		actaDescargarBtn.removeAttribute('aria-disabled');
	}

	// "Previsualización" NO guarda nada en la base (2026-08-18: antes guardaba
	// un borrador en silencio, se sacó a pedido explícito) — arma el PDF al
	// vuelo desde lo que hay en pantalla ahora mismo
	// (getters/previsualizar_acta_pdf.php) y lo muestra como blob: URL. Corre
	// las mismas validaciones que guardarAcuerdo (spinners sin confirmar,
	// participación, Distribuidor/Periodo) pero sin exigir ninguna línea real
	// — previsualizar algo todavía incompleto es válido.
	var generarActaBtn = document.getElementById('ac-generar-acta');

	function mostrarPreview() {
		if (!validarCabecera('borrador')) return;

		// Feedback de carga (2026-08-24, pedido explícito): armar el PDF con
		// Dompdf tarda un momento — sin esto no había ninguna señal visible y
		// el usuario terminaba clickeando "Previsualización" varias veces
		// pensando que el sistema se había quedado colgado.
		// Corregido 2026-08-31: acMostrarCargando(acuerdoContainer) centraba
		// el spinner DENTRO del formulario — que es mucho más alto que la
		// pantalla (4 tablas), así que el spinner quedaba fuera de vista y
		// solo se veía el fondo blanquecino del overlay, sin ningún mensaje.
		// acMostrarCargandoPantalla() (assets/js/cargando.js) reemplaza eso:
		// overlay fijo centrado en la PANTALLA, con un mensaje real.
		acBotonCargando(generarActaBtn, true);
		acMostrarCargandoPantalla('Generando la vista previa del Acta');

		var payload = {
			pos_id: distribuidorSelect.value,
			distribuidor_nombre: distribuidorSearch.value,
			localidad: localidadEl.textContent,
			anio: parseInt(anioSelect.value, 10),
			mes_inicio: selectedStart,
			mes_fin: selectedEnd,
			// Este endpoint nunca abre conexión a la base (a propósito, ver su
			// comentario de cabecera) — el canal ya lo sabe el cliente
			// (catalogoDistribuidor.canal, cargado al inicio), así que se manda
			// para que la vista previa use el formato de Acta correcto.
			es_distribuidor: catalogoDistribuidor.canal === 'distribuidor',
			// "Empresa Distribuidora" (campo que en la UI se muestra como
			// "Distribuidor", ver ac-empresa-field en registrar.php) — en el
			// Acta de canal Distribuidor va en "Estimado(a)", separado del
			// nombre del "Local" (distribuidorSearch, arriba). Vacío en Directo.
			empresa_distribuidora: empresaSearch.value,
			// Switch "Visibilidad y Espacios" — independiente del canal, ver
			// includes/acta_pdf.php $sinVisibilidad.
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

	// Deja el formulario listo para el siguiente Acuerdo — el usuario puede
	// estar registrando muchos PDV seguidos, no tiene sentido que arrastre los
	// datos del anterior después de generar uno.
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
		// "Agregar Fila" de Meta de Compras pudo quedar bloqueado por una Acta
		// precargada anterior en esta misma sesión (ver bloquearFilasPrecargadas())
		// — el siguiente Acuerdo empieza limpio, sin ese bloqueo. Las filas en
		// sí ya se reconstruyen frescas (sin disabled) porque syncTables()
		// arriba las arma de cero.
		var btnAgregarPurchase = document.getElementById('ac-add-purchase-row');
		if (btnAgregarPurchase) { btnAgregarPurchase.disabled = false; btnAgregarPurchase.title = ''; }
		desbloquearAgregarOtrasTablas();
		formSucio = false;
	}

	// Acá es donde de verdad se "genera": crea el acuerdo directo como
	// 'generado' (guarda el snapshot definitivo del PDF, ver
	// guardar_acuerdo.php) — hasta este click, Previsualización no había
	// tocado la base para nada.
	actaGenerarBtn.addEventListener('click', function () {
		guardarAcuerdo('generado', function () {
			pdfGenerado = true;
			var url = 'getters/generar_acta_pdf.php?id=' + acuerdoId + '&t=' + Date.now();
			if (previewBlobUrl) { URL.revokeObjectURL(previewBlobUrl); previewBlobUrl = null; }
			pdfUrlActual = url;
			habilitarDescarga(url, 'Acta_' + documentoNo + '.pdf');
			aplicarZoom();
			mostrarMensaje('PDF generado. Ya podés descargarlo.', true);
			limpiarFormularioParaNuevoAcuerdo();
		});
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
	// Meta de Compras se persiste desde 2026-08-18 (antes no, ver CLAUDE.md) —
	// para Actas viejas guardadas antes de ese cambio, `fila.sector` viene
	// null y sugerir() lo sigue infiriendo solo (fallback de compatibilidad,
	// mismo comportamiento que ya existía).
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
		origenPrecarga = null; // un Borrador nunca viene de una precarga
		var btnAgregarPurchase = document.getElementById('ac-add-purchase-row');
		if (btnAgregarPurchase) { btnAgregarPurchase.disabled = false; btnAgregarPurchase.title = ''; } // por si quedó bloqueado de una precarga anterior en esta sesión
		desbloquearAgregarOtrasTablas();

		anioSelect.value = a.anio;
		selectedStart = a.mes_inicio;
		selectedEnd = a.mes_fin;
		activeMonthsIndices = [];
		for (var i = selectedStart; i <= selectedEnd; i++) activeMonthsIndices.push(i);
		// Borradores guardados antes de pasar a trimestres fijos podrían tener
		// un rango que no calza con ningún Q1-Q4 — en ese caso se deja el
		// select como esté (no hay opción que marcarle) pero igual se respetan
		// los meses reales guardados.
		for (var q = 0; q < TRIMESTRES.length; q++) {
			if (TRIMESTRES[q][0] === selectedStart && TRIMESTRES[q][1] === selectedEnd) {
				periodoSelect.value = String(q);
				break;
			}
		}
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

		// Switch "Visibilidad y Espacios": no se llama a resetearZonaVisibilidad()
		// acá aunque esté desactivado — poblarTablasConLineas() de abajo ya
		// reconstruye esas 3 tablas desde a.lineas (que van a venir vacías si
		// se guardó con el switch apagado, ver guardar_acuerdo.php), así que
		// solo hace falta aplicar la clase visual de bloqueo.
		visibilidadActiva = !a.sin_visibilidad;
		visibilidadToggle.checked = visibilidadActiva;
		aplicarBloqueoVisibilidad();

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

	// Fase 2 del Repositorio de Cuotas (2026-08-25) — deja readonly/disabled
	// (según corresponda) las celdas de cada fila de Meta de Compras recién
	// poblada por una precarga: Segmento/Categoría(DB sector) y los 3 montos
	// SIEMPRE bloqueados (eso es justo lo que pidió JW, "que no lo puedan
	// tipear"); Subcategoría(DB categoria)/Marca SOLO si vinieron resueltas
	// desde el historial del cliente — si no hay historial, quedan abiertas
	// para que el asesor las complete con el combo normal (sin esto la fila
	// se guardaría incompleta y guardar_acuerdo.php la descartaría en
	// silencio, ver ese archivo línea ~127). `lineasMeta` y las filas de
	// `purchaseBody` están en el mismo orden porque
	// poblarTablasConLineas() agrega una fila por cada elemento del array,
	// en orden, sin saltarse ninguno.
	function bloquearFilasPrecargadas(lineasMeta) {
		var filas = purchaseBody.querySelectorAll('tr');
		Array.prototype.forEach.call(filas, function (tr, i) {
			var fila = lineasMeta[i];
			if (!fila || !fila.bloqueado) return;
			Array.prototype.forEach.call(tr.querySelectorAll('.month-input'), function (inp) { inp.readOnly = true; });
			// Segmento/Sector SOLO se bloquean si vinieron resueltos — si el
			// Segmento quedó ambiguo (2+ Segmentos reales posibles para ese
			// Sector, ver obtener_precarga_detalle()), la fila queda con el
			// cascade normal (Sector deshabilitado hasta elegir Segmento, como
			// en cualquier fila nueva) — bloquearla igual la habría dejado
			// trabada para siempre, sin ninguna forma de completarla (bug real
			// encontrado probando con datos reales, 2026-08-25).
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
			// Corregido 2026-08-25 (pedido explícito, probando en navegador
			// real): la fila NO debe poder eliminarse — la Acta precargada es
			// una estructura fija que el asesor solo completa (Subcategoría/
			// Marca si faltan), nunca reorganiza. Se deshabilita el botón en
			// vez de sacarlo del DOM para no tener que tocar el resto del
			// layout de la fila.
			var btnEliminar = tr.querySelector('.ac-remove-row');
			if (btnEliminar) { btnEliminar.disabled = true; btnEliminar.title = 'Esta fila viene de una Acta precargada — no se puede quitar'; }
		});
		// "Agregar Fila" de Meta de Compras también se bloquea del todo — la
		// tabla es una estructura fija mientras esta Acta vino de una
		// precarga, el asesor solo llena lo que falta, no agrega productos
		// nuevos acá (si hace falta, es un caso para hablarlo aparte, no
		// para resolverlo agregando una fila suelta).
		var btnAgregar = document.getElementById('ac-add-purchase-row');
		if (btnAgregar) { btnAgregar.disabled = true; btnAgregar.title = 'Esta Acta viene de una precarga — la tabla de Meta de Compras es fija'; }
	}

	// Cabeceras/Rumas/Perchas no vienen en el Excel de Cuotas (esa hoja solo
	// trae CEDI/CLIENTE/PLAN/CATEGORIAS/meses — nada de Subcategoría/Marca
	// para estas 3 tablas) — así que no hay nada que autocompletar ahí. Lo
	// que SÍ se puede hacer sin ese dato: dejar tantas filas vacías como
	// líneas trajo Meta de Compras (para que el asesor no tenga que ir
	// clickeando "Agregar Fila" una por una) y bloquear "Agregar Fila" del
	// todo — a diferencia de Meta de Compras, acá "Eliminar Fila" SÍ sigue
	// habilitado (si una categoría no lleva Cabecera/Ruma/Percha, el asesor
	// puede sacar esa fila de más).
	function generarFilasVaciasOtrasTablas(cantidadLineasMeta) {
		var cantidad = cantidadLineasMeta > 0 ? cantidadLineasMeta : 1;
		for (var i = cabecerasBody.querySelectorAll('tr').length; i < cantidad; i++) addCabeceraRow();
		for (var j = rumasBody.querySelectorAll('tr').length; j < cantidad; j++) addRumaRow();
		for (var k = perchasBody.querySelectorAll('tr').length; k < cantidad; k++) addPerchaRow();
	}

	// Mismo criterio que bloquearFilasPrecargadas() pero para las filas
	// espejo de Cabeceras/Rumas/Perchas (2026-08-27, pedido explícito "así
	// mismo como la tabla 1, estos no podrán modificar los campos, solo los
	// precios"): la fila i de cada tabla corresponde a la línea i de Meta de
	// Compras (mismo orden, mismo conteo, ver generarFilasVaciasOtrasTablas)
	// — si esa línea de Meta de Compras trajo Segmento+Subcategoría+Marca ya
	// resueltos (fila.segmento/categoria/marca truthy), se copia esa misma
	// identidad acá y se bloquean esos 3 campos; si no (producto ambiguo,
	// sin historial), la fila queda con el cascade normal para que el
	// asesor la complete a mano — nunca se llama a `.sugerir()` con datos a
	// medias (dejaría literalmente el texto "null" en el campo).
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
				// A diferencia de restaurar un borrador (donde `sugerir()` se
				// queda silencioso a propósito para no pisar una Participación ya
				// tipeada/guardada), acá la fila es NUEVA — nunca tuvo un valor
				// real, se queda en "0%" para siempre si no se busca. Se busca en
				// vivo el % real del repositorio, igual que si el asesor hubiera
				// elegido la Marca a mano (2026-08-31, bug real reportado:
				// Perchas de una Acta Precargada siempre quedaban en 0%).
				buscarYAplicarParticipacion(trPercha, fila.marca);
			}
		});
	}

	function bloquearAgregarOtrasTablas() {
		['ac-add-cabecera-row', 'ac-add-ruma-row', 'ac-add-percha-row'].forEach(function (id) {
			var btn = document.getElementById(id);
			if (btn) { btn.disabled = true; btn.title = 'Esta Acta viene de una precarga — completá las filas ya generadas, no se agregan más'; }
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

		// Mismo criterio que aplicarBorrador(): en canal Distribuidor hay que
		// fijar la Empresa antes que el Distribuidor, porque el combo de
		// Distribuidor arma sus opciones a partir de la Empresa elegida.
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

		// Acta nueva de verdad (no un borrador restaurado) — Visibilidad
		// arranca en su estado por defecto, igual que "Nuevo Acuerdo".
		visibilidadActiva = true;
		visibilidadToggle.checked = true;
		aplicarBloqueoVisibilidad();

		poblarTablasConLineas(p.lineas);
		// Orden importa: generarFilasVaciasOtrasTablas() llama a
		// addCabeceraRow()/addRumaRow()/addPerchaRow(), y cada una de esas
		// termina en actualizarBloqueoPorDistribuidor() (rehabilita TODOS los
		// .seg-input de las 3 tablas + Meta de Compras, según haya
		// Distribuidor elegido) — si bloquearFilasPrecargadas() corriera
		// antes, esas llamadas post-lock desharían el bloqueo de Segmento en
		// Meta de Compras sin querer (bug real encontrado probando esta
		// misma vuelta). Por eso bloquearFilasPrecargadas() va DESPUÉS de
		// terminar de generar filas — es la última palabra sobre Meta de
		// Compras, nada corre después que vuelva a tocar sus inputs.
		generarFilasVaciasOtrasTablas(p.lineas.meta_compra.length);
		bloquearFilasPrecargadas(p.lineas.meta_compra);
		espejarIdentidadOtrasTablas(p.lineas.meta_compra);
		bloquearAgregarOtrasTablas();

		// Cargar la precarga no es en sí un cambio "sin guardar" — recién se
		// vuelve sucio si el asesor completa Subcategoría/Marca o edita
		// Cabeceras/Rumas/Perchas a partir de acá (mismo criterio que un
		// Borrador restaurado).
		formSucio = false;
		mostrarMensaje('Acta precargada cargada — completá lo que falte y generá el Acta.', true);
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

	// La campanita de alertas vive en assets/js/alertas-firma.js (widget
	// global del header), pero cargar la precarga en el formulario solo lo
	// puede hacer este módulo — mismo patrón que
	// window.acRegistrarCargarBorrador de abajo.
	window.acRegistrarCargarPrecarga = cargarPrecarga;

	// El modal "Mis Borradores" vive en Historial (components/historial.js),
	// pero cargar un borrador en el formulario solo lo puede hacer este
	// módulo — todo el estado de las 4 tablas y los combos vive acá adentro.
	// historial.js cambia a la pestaña Registrar y llama a esta función
	// expuesta (mismo patrón que "Nuevo Acuerdo" ya usa para cambiar de tab).
	window.acRegistrarCargarBorrador = cargarBorrador;

	cargarDatosIniciales();
})();

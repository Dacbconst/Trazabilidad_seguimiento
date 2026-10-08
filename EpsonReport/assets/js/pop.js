// Colocación de POP: carga del mes (Fabricio), equipo del supervisor, seguimiento en vivo y acciones de cada mes.
(function () {
	function post(url, datos) {
		var form = new FormData();
		Object.keys(datos).forEach(function (k) { form.append(k, datos[k]); });
		return fetch(url, { method: 'POST', body: form, credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}
	function avisar(icono, titulo, texto) {
		if (window.Swal) Swal.fire({ icon: icono, title: titulo, text: texto || '' });
	}
	function escapar(t) {
		return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
	}
	function leerJson(id, defecto) {
		var el = document.getElementById(id);
		return el ? JSON.parse(el.textContent || 'null') : defecto;
	}

	// Avisos que sobreviven a la recarga: guardar recarga la página y sin esto el éxito no se ve.
	function recargarConAviso(texto) {
		try { sessionStorage.setItem('epPopToast', texto); } catch (e) { /* sin almacenamiento: se recarga igual */ }
		location.reload();
	}
	function toast(icono, texto) {
		if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: icono, title: texto, showConfirmButton: false, timer: 2600 });
	}
	window.addEventListener('load', function () {
		var pendiente = null;
		try { pendiente = sessionStorage.getItem('epPopToast'); sessionStorage.removeItem('epPopToast'); } catch (e) { /* sin almacenamiento */ }
		if (pendiente) toast('success', pendiente);
	});

	// Foto liviana: carga diferida y, si falla o no hay, quedan las iniciales.
	document.addEventListener('error', function (ev) {
		var img = ev.target;
		if (!img || img.tagName !== 'IMG' || !img.closest('.ep-eq-av, .ep-sg-av')) return;
		var cont = img.parentNode;
		cont.textContent = img.getAttribute('data-ini') || '';
	}, true);

	// ---------- Pestañas: la elegida queda en el hash para que recargar no cambie de pestaña ----------
	var tabs = Array.prototype.slice.call(document.querySelectorAll('.ep-popm-tab'));
	var paneles = Array.prototype.slice.call(document.querySelectorAll('.ep-popm-panel'));
	var indicador = document.getElementById('epPopmIndicador');
	function elegirTab(nombre) {
		if (!tabs.some(function (t) { return t.dataset.tab === nombre; })) nombre = tabs[0].dataset.tab;
		tabs.forEach(function (t) {
			var on = t.dataset.tab === nombre;
			t.classList.toggle('activo', on);
			t.setAttribute('aria-selected', on ? 'true' : 'false');
			if (on) indicador.style.transform = 'translateX(' + t.offsetLeft + 'px) scaleX(' + t.offsetWidth + ')';
		});
		paneles.forEach(function (p) { p.classList.toggle('hidden', p.dataset.tab !== nombre); });
		if (location.hash !== '#' + nombre) history.replaceState(null, '', '#' + nombre);
	}
	tabs.forEach(function (t) { t.addEventListener('click', function () { elegirTab(t.dataset.tab); }); });
	elegirTab(location.hash.replace('#', ''));
	window.addEventListener('resize', function () { elegirTab(location.hash.replace('#', '')); });
	if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { elegirTab(location.hash.replace('#', '')); });

	// ---------- Modal de carga (Fabricio): material, bodega y reparto a cada supervisor ----------
	var modal = document.getElementById('epPopModal');
	var abiertos = leerJson('epPopDatos', []) || [];
	var supervisores = leerJson('epPopSupervisores', []) || [];
	var elegidos = [];
	var editandoId = 0;

	if (modal) {
		var filas = document.getElementById('epPopFilas');
		var filasHead = document.getElementById('epPopFilasHead');
		var inputMes = document.getElementById('epPopMes');
		var inputComentarios = document.getElementById('epPopComentarios');
		var btnGuardar = document.getElementById('epPopGuardar');
		var titulo = document.getElementById('epPopModalTitulo');

		var activos = function () { return supervisores.filter(function (sup) { return elegidos.indexOf(sup.id) !== -1; }); };
		// Columnas de la tabla según los supervisores elegidos (un repeat(0, ...) en CSS invalida toda la regla).
		var columnas = function () { return 'minmax(150px, 2fr) minmax(110px, 1.4fr) 92px ' + elegidos.map(function () { return '104px '; }).join('') + '96px 34px'; };
		var supBtn = document.getElementById('epPopSupBtn');
		var supLista = document.getElementById('epPopSupLista');
		var catalogo = leerJson('epPopCatalogo', { materiales: [], campanas: [] }) || { materiales: [], campanas: [] };
		// Lista propia (mismo .ep-combo del resto de la app): buscador, opciones y el panel fijo para que no lo recorte la tabla.
		var comboHTML = function (campo, vacio) {
			return '<div class="ep-combo ep-pop-combo" data-campo="' + campo + '"><button type="button" class="ep-input ep-combo-trigger" data-valor="" aria-label="' + vacio + '">'
				+ '<span class="ep-combo-trigger-texto">' + vacio + '</span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></button>'
				+ '<div class="ep-combo-panel hidden"><input type="text" class="ep-input ep-combo-buscador" placeholder="Buscar..." autocomplete="off"><div class="ep-combo-opciones"></div></div></div>';
		};
		var valorCombo = function (fila, campo) { return fila.querySelector('.ep-pop-combo[data-campo="' + campo + '"] .ep-combo-trigger').dataset.valor; };
		var fijarCombo = function (combo, valor) {
			var trigger = combo.querySelector('.ep-combo-trigger');
			trigger.dataset.valor = valor;
			trigger.querySelector('.ep-combo-trigger-texto').textContent = valor || trigger.getAttribute('aria-label');
		};
		var cerrarCombos = function () { filas.querySelectorAll('.ep-combo-panel').forEach(function (p) { p.classList.add('hidden'); }); };
		var pintarOpciones = function (combo, texto) {
			var lista = campoLista(combo.dataset.campo);
			var q = texto.trim().toUpperCase();
			var actual = combo.querySelector('.ep-combo-trigger').dataset.valor;
			var usados = campoLista(combo.dataset.campo) === catalogo.materiales ? materialesElegidos(combo) : [];
			var disponibles = lista.filter(function (n) { return usados.indexOf(n) === -1; });
			var coincidencias = disponibles.filter(function (n) { return !q || n.indexOf(q) !== -1; });
			combo.querySelector('.ep-combo-opciones').innerHTML = coincidencias.length
				? coincidencias.map(function (n) { return '<button type="button" class="ep-combo-opcion' + (n === actual ? ' activo' : '') + '" data-valor="' + escapar(n) + '">' + escapar(n) + '</button>'; }).join('')
				: '<div class="ep-combo-vacio">' + (!lista.length ? 'Aún no hay nada. Agrégalo en Repositorios.' : (!disponibles.length ? 'Ya elegiste todos los materiales.' : 'Sin resultados')) + '</div>';
		};
		// Materiales ya puestos en las otras filas del mes (el material es único por mes).
		var materialesElegidos = function (combo) {
			var vistos = [];
			filas.querySelectorAll('.ep-pop-combo[data-campo="material"]').forEach(function (c) {
				var v = c.querySelector('.ep-combo-trigger').dataset.valor;
				if (c !== combo && v) vistos.push(v);
			});
			return vistos;
		};
		var campoLista = function (campo) { return campo === 'material' ? catalogo.materiales : catalogo.campanas; };
		var abrirCombo = function (combo) {
			cerrarCombos();
			var trigger = combo.querySelector('.ep-combo-trigger');
			var panel = combo.querySelector('.ep-combo-panel');
			var caja = trigger.getBoundingClientRect();
			var buscador = panel.querySelector('.ep-combo-buscador');
			buscador.value = '';
			pintarOpciones(combo, '');
			panel.style.width = Math.max(caja.width, 220) + 'px';
			panel.style.left = Math.min(caja.left, window.innerWidth - Math.max(caja.width, 220) - 12) + 'px';
			panel.style.top = (caja.bottom + 4) + 'px';
			panel.classList.remove('hidden');
			buscador.focus();
		};
		var filaHTML = function () {
			return '<div class="ep-pop-fila" data-id="0">'
				+ comboHTML('material', 'Elige el material')
				+ comboHTML('campana', 'Elige la campaña')
				+ '<input type="number" min="0" inputmode="numeric" class="ep-input ep-pop-fila-bodega" placeholder="Bodega">'
				+ activos().map(function (sup) { return '<input type="number" min="0" inputmode="numeric" class="ep-input ep-pop-fila-sup" data-sup="' + sup.id + '" placeholder="0">'; }).join('')
				+ '<span class="ep-pop-fila-disponible">0</span>'
				+ '<button type="button" class="ep-modelo-quitar" aria-label="Quitar material"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button>'
				+ '</div>';
		};
		// Una columna por supervisor activo; "Disponible" va siempre al final.
		var pintarEncabezado = function () {
			filasHead.innerHTML = '<span>Material</span><span>Campaña</span><span>Bodega</span>'
				+ activos().map(function (sup) { return '<span title="' + escapar(sup.nombre) + '">' + escapar(sup.nombre) + '<button type="button" class="ep-pop-quitar-sup" data-sup="' + sup.id + '" aria-label="Quitar a ' + escapar(sup.nombre) + '" title="Quitar supervisor">×</button></span>'; }).join('')
				+ '<span>Disponible</span><span></span>';
			filasHead.style.setProperty('--ep-pop-cols', columnas());
			filas.querySelectorAll('.ep-pop-fila').forEach(function (f) { f.style.setProperty('--ep-pop-cols', columnas()); });
		};
		var recalcular = function (fila) {
			var bodega = parseInt(fila.querySelector('.ep-pop-fila-bodega').value, 10) || 0;
			var repartido = 0;
			fila.querySelectorAll('.ep-pop-fila-sup').forEach(function (i) { repartido += parseInt(i.value, 10) || 0; });
			var disp = fila.querySelector('.ep-pop-fila-disponible');
			disp.textContent = bodega - repartido;
			disp.classList.toggle('ep-pop-negativo', bodega - repartido < 0);
			fila.classList.toggle('error', bodega - repartido < 0);
			fila.title = bodega - repartido < 0 ? 'Repartes ' + repartido + ' y en bodega hay ' + bodega : '';
		};
		// Lo que se escribe a un supervisor nunca pasa de lo que queda en bodega ni baja de 0.
		var topeSupervisor = function (input) {
			var fila = input.closest('.ep-pop-fila');
			var bodega = parseInt(fila.querySelector('.ep-pop-fila-bodega').value, 10) || 0;
			var otros = 0;
			fila.querySelectorAll('.ep-pop-fila-sup').forEach(function (i) { if (i !== input) otros += parseInt(i.value, 10) || 0; });
			var valor = parseInt(input.value, 10);
			if (isNaN(valor)) return;
			var tope = Math.max(0, bodega - otros);
			if (valor < 0) input.value = 0;
			else if (valor > tope) { input.value = tope; toast('info', 'Máximo ' + tope + ': es lo que queda en bodega'); }
		};
		var agregarFila = function () {
			filas.insertAdjacentHTML('beforeend', filaHTML());
			var fila = filas.lastElementChild;
			fila.style.setProperty('--ep-pop-cols', columnas());
			return fila;
		};
		// Al sumar un supervisor se agrega su columna a todas las filas; al quitarlo se pierde lo escrito en ella.
		var agregarSup = function (id) {
			if (elegidos.indexOf(id) !== -1) return;
			elegidos.push(id);
			filas.querySelectorAll('.ep-pop-fila').forEach(function (f) {
				var input = document.createElement('input');
				input.type = 'number'; input.min = '0'; input.inputMode = 'numeric'; input.placeholder = '0';
				input.className = 'ep-input ep-pop-fila-sup'; input.dataset.sup = id;
				f.insertBefore(input, f.querySelector('.ep-pop-fila-disponible'));
			});
			pintarEncabezado();
		};
		var quitarSup = function (id) {
			elegidos = elegidos.filter(function (x) { return x !== id; });
			filas.querySelectorAll('.ep-pop-fila-sup').forEach(function (i) {
				if (parseInt(i.dataset.sup, 10) === id) { var f = i.closest('.ep-pop-fila'); i.remove(); recalcular(f); }
			});
			pintarEncabezado();
		};
		var pintarMenuSup = function () {
			var libres = supervisores.filter(function (sup) { return elegidos.indexOf(sup.id) === -1; });
			supLista.innerHTML = libres.length
				? libres.map(function (sup) { return '<button type="button" role="menuitem" data-sup="' + sup.id + '">' + escapar(sup.nombre) + '</button>'; }).join('')
				: '<div class="ep-pop-sup-vacio">Ya agregaste a todos los supervisores</div>';
		};
		var leerFilas = function () {
			return Array.prototype.slice.call(filas.querySelectorAll('.ep-pop-fila')).map(function (f) {
				var reparto = {};
				f.querySelectorAll('.ep-pop-fila-sup').forEach(function (i) { reparto[i.dataset.sup] = parseInt(i.value, 10) || 0; });
				return {
					id: parseInt(f.dataset.id, 10) || 0,
					material: valorCombo(f, 'material'),
					campana: valorCombo(f, 'campana'),
					bodega: parseInt(f.querySelector('.ep-pop-fila-bodega').value, 10) || 0,
					reparto: reparto
				};
			}).filter(function (f) { return f.material && f.campana; });
		};
		var abrirModal = function (pop) {
			editandoId = pop ? pop.id : 0;
			titulo.textContent = pop ? 'Corregir mes de POP' : 'Cargar mes de POP';
			btnGuardar.textContent = pop ? 'Guardar cambios' : 'Cargar mes';
			inputMes.value = pop ? pop.mes : new Date().toISOString().slice(0, 7);
			inputMes.disabled = !!pop;
			inputComentarios.value = pop ? pop.comentarios : '';
			filas.innerHTML = '';
			elegidos = [];
			supLista.classList.add('hidden');
			if (pop) pop.filas.forEach(function (f) { Object.keys(f.reparto || {}).forEach(function (id) { if ((f.reparto[id] || 0) > 0 && elegidos.indexOf(parseInt(id, 10)) === -1) elegidos.push(parseInt(id, 10)); }); });
			pintarEncabezado();
			if (pop && pop.filas.length) {
				pop.filas.forEach(function (f) {
					var fila = agregarFila();
					fila.dataset.id = f.id;
					fijarCombo(fila.querySelector('.ep-pop-combo[data-campo="material"]'), f.material);
					fijarCombo(fila.querySelector('.ep-pop-combo[data-campo="campana"]'), f.campana);
					fila.querySelector('.ep-pop-fila-bodega').value = f.bodega;
					fila.querySelectorAll('.ep-pop-fila-sup').forEach(function (i) { var v = (f.reparto || {})[i.dataset.sup]; if (v) i.value = v; });
					recalcular(fila);
				});
			} else {
				agregarFila();
			}
			modal.classList.remove('hidden');
		};
		var cerrarModal = function () { modal.classList.add('hidden'); supLista.classList.add('hidden'); };

		supBtn.addEventListener('click', function () {
			pintarMenuSup();
			supBtn.setAttribute('aria-expanded', supLista.classList.toggle('hidden') ? 'false' : 'true');
		});
		supLista.addEventListener('click', function (ev) {
			var b = ev.target.closest('button[data-sup]');
			if (!b) return;
			agregarSup(parseInt(b.dataset.sup, 10));
			supLista.classList.add('hidden');
		});
		filasHead.addEventListener('click', function (ev) {
			var q = ev.target.closest('.ep-pop-quitar-sup');
			if (!q) return;
			var supId = parseInt(q.dataset.sup, 10);
			var conDatos = Array.prototype.some.call(filas.querySelectorAll('.ep-pop-fila-sup'), function (i) { return parseInt(i.dataset.sup, 10) === supId && (parseInt(i.value, 10) || 0) > 0; });
			if (!conDatos || !window.Swal) { quitarSup(supId); return; }
			Swal.fire({ icon: 'warning', title: '¿Quitar a este supervisor?', text: 'Se pierden las cantidades que escribiste en su columna.', showCancelButton: true, cancelButtonText: 'Cancelar', confirmButtonText: 'Sí, quitarlo', focusCancel: true })
				.then(function (res) { if (res.isConfirmed) quitarSup(supId); });
		});
		filas.addEventListener('input', function (ev) {
			if (ev.target.classList.contains('ep-combo-buscador')) pintarOpciones(ev.target.closest('.ep-pop-combo'), ev.target.value);
			if (ev.target.classList.contains('ep-pop-fila-sup')) topeSupervisor(ev.target);
			if (ev.target.classList.contains('ep-pop-fila-bodega') || ev.target.classList.contains('ep-pop-fila-sup')) recalcular(ev.target.closest('.ep-pop-fila'));
		});
		filas.addEventListener('click', function (ev) {
			var trigger = ev.target.closest('.ep-combo-trigger');
			if (trigger) {
				var combo = trigger.closest('.ep-pop-combo');
				var abierto = !combo.querySelector('.ep-combo-panel').classList.contains('hidden');
				cerrarCombos();
				if (!abierto) abrirCombo(combo);
				return;
			}
			var opcion = ev.target.closest('.ep-combo-opcion');
			if (opcion) { fijarCombo(opcion.closest('.ep-pop-combo'), opcion.dataset.valor); cerrarCombos(); }
		});
		document.addEventListener('click', function (ev) { if (!ev.target.closest('.ep-pop-combo')) cerrarCombos(); });
		modal.addEventListener('scroll', function (ev) { if (!ev.target.closest || !ev.target.closest('.ep-combo-panel')) cerrarCombos(); }, true);
		var btnNuevo = document.getElementById('epPopNuevo');
		if (btnNuevo) btnNuevo.addEventListener('click', function () {
			if (btnNuevo.dataset.sinCatalogo) { avisar('info', 'Primero llena los repositorios', 'Para cargar un mes hacen falta materiales y campañas. Súbelos en Repositorios.'); return; }
			abrirModal(null);
		});
		document.getElementById('epPopAgregar').addEventListener('click', function () { agregarFila().querySelector('.ep-combo-trigger').focus(); });
		document.addEventListener('keydown', function (ev) {
			if (ev.key !== 'Escape' || modal.classList.contains('hidden')) return;
			if (filas.querySelector('.ep-combo-panel:not(.hidden)')) { cerrarCombos(); return; }
			cerrarModal();
		});
		document.getElementById('epPopCancelar').addEventListener('click', cerrarModal);
		document.getElementById('epPopModalCerrar').addEventListener('click', cerrarModal);
		document.getElementById('epPopModalFondo').addEventListener('click', cerrarModal);
		// Siempre queda al menos una fila: la última solo se vacía.
		filas.addEventListener('click', function (ev) {
			if (!ev.target.closest('.ep-modelo-quitar')) return;
			if (filas.querySelectorAll('.ep-pop-fila').length > 1) ev.target.closest('.ep-pop-fila').remove();
			else { filas.querySelectorAll('input').forEach(function (i) { i.value = ''; }); filas.querySelectorAll('.ep-pop-combo').forEach(function (c) { fijarCombo(c, ''); }); recalcular(filas.querySelector('.ep-pop-fila')); }
		});

		btnGuardar.addEventListener('click', function () {
			var lista = leerFilas();
			if (!lista.length) {
				avisar('warning', 'Falta material', 'Agrega al menos un material con su campaña.');
				return;
			}
			var excedida = lista.filter(function (f) { var t = 0; Object.keys(f.reparto).forEach(function (c) { t += f.reparto[c]; }); return t > f.bodega; })[0];
			if (excedida) {
				avisar('warning', 'Revisa las cantidades', 'En ' + excedida.material + ' repartes más de lo que hay en bodega.');
				return;
			}
			btnGuardar.disabled = true;
			var url = editandoId ? 'getters/pop_editar.php' : 'getters/pop_crear.php';
			var datos = { filas: JSON.stringify(lista), comentarios: inputComentarios.value };
			if (editandoId) datos.id = editandoId; else datos.mes = inputMes.value;
			post(url, datos).then(function (r) {
				btnGuardar.disabled = false;
				if (r && r.ok) recargarConAviso(editandoId ? 'Cambios del mes guardados' : 'Mes cargado');
				else avisar('error', 'No se pudo guardar', (r && r.message) || 'Intenta de nuevo.');
			}).catch(function () {
				btnGuardar.disabled = false;
				avisar('error', 'No se pudo guardar', 'El servidor no respondió correctamente.');
			});
		});
	}

	// ---------- Colocación asignada: matriz para marcar qué promotor reporta qué material ----------
	var eqRaiz = document.getElementById('epEq');
	var eqDatos = leerJson('epEqDatos', null);
	if (eqRaiz && eqDatos) {
		var eqMatriz = document.getElementById('epEqMatriz');
		var eqPop = document.getElementById('epEqPop');
		var eqAgregar = document.getElementById('epEqAgregar');
		var eqBuscar = document.getElementById('epEqBuscar');
		var eqLista = document.getElementById('epEqLista');
		var eqGuardar = document.getElementById('epEqGuardar');
		var eqBarra = document.getElementById('epEqSucio');
		var eqCuenta = document.getElementById('epEqCuenta');
		var eqEditar = document.getElementById('epEqEditar');
		var eqDescartar = document.getElementById('epEqDescartar');
		// Con un equipo ya guardado la matriz queda de solo lectura hasta pulsar "Editar equipo"; sin equipo se arma directo.
		var eqModo = false;
		var hayGuardado = function () { return Object.keys(eqDatos.asignados).length > 0; };
		var eqBloqueo = eqDatos.reportado || {};
		var eqUsuarios = [];
		var eqMarcas = {};
		var eqInicial = '';
		var eqGuardado = false;

		var iniciales = function (n) { return n.trim().split(/\s+/).slice(0, 2).map(function (p) { return p.charAt(0); }).join('').toUpperCase(); };
		var fotoEq = function (id) { return (eqDatos.equipo.filter(function (p) { return p.id === id; })[0] || {}).foto || ''; };
		var avatarEq = function (nombre, foto) {
			var ini = escapar(iniciales(nombre));
			return '<span class="ep-eq-av">' + (foto ? '<img src="' + escapar(foto) + '" alt="" width="26" height="26" loading="lazy" decoding="async" data-ini="' + ini + '">' : ini) + '</span>';
		};
		var nombreEq = function (id) { return (eqDatos.equipo.filter(function (p) { return p.id === id; })[0] || { nombre: 'Promotor' }).nombre; };
		var bloqueado = function (u, fila) { return !!(eqBloqueo[u] && eqBloqueo[u][fila]); };
		// Firma del estado actual para saber si hay cambios sin guardar.
		var firmaEq = function () {
			return JSON.stringify(eqUsuarios.slice().sort().map(function (u) { return [u, Object.keys(eqMarcas[u] || {}).filter(function (k) { return eqMarcas[u][k]; }).sort()]; }));
		};
		var hayCambios = function () { return firmaEq() !== eqInicial; };
		var cargarEq = function () {
			eqUsuarios = Object.keys(eqDatos.asignados).map(Number);
			eqMarcas = {};
			eqUsuarios.forEach(function (u) { eqMarcas[u] = {}; eqDatos.asignados[u].forEach(function (fila) { eqMarcas[u][fila] = true; }); });
			eqInicial = firmaEq();
			eqModo = !hayGuardado();
		};
		var enfocarEq = function (selector) { var n = selector ? eqMatriz.querySelector(selector) : null; if (n) n.focus(); };
		var casilla = function (attrs, marcada, desactivada, etiqueta, extra) {
			return '<label class="ep-eq-ck"><input type="checkbox" ' + attrs + (marcada ? ' checked' : '') + (desactivada ? ' disabled' : '') + ' aria-label="' + escapar(etiqueta) + '"><span class="ep-eq-bx"></span>' + (extra || '') + '</label>';
		};
		var iconoPapelera = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-8 0l1 13a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2l1-13"/></svg>';
		var iconoMas = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>';
		var iconoCandado = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';

		var pintarEq = function () {
			var mats = eqDatos.materiales;
			var cols = 'minmax(210px, 1.2fr) repeat(' + mats.length + ', minmax(190px, 1fr)) 100px';
			var html = '<div class="ep-eq-r ep-eq-h" style="grid-template-columns:' + cols + '"><span class="ep-eq-lbl">' + (eqUsuarios.length ? 'Promotor' : 'Material recibido') + '</span>'
				+ mats.map(function (m) {
					var pct = m.recibido > 0 ? Math.min(100, Math.round(m.reportado / m.recibido * 100)) : 0;
					return '<div class="ep-eq-mh"><span class="ep-eq-nm"><strong>' + escapar(m.material) + '</strong><em>' + escapar(m.campana) + '</em></span>'
						+ '<span class="ep-eq-pbar' + (m.disponible <= 0 ? ' ag' : '') + '"><i style="width:' + pct + '%"></i></span>'
						+ '<span class="ep-eq-sd"><b>' + m.disponible + '</b> disponibles · ' + m.reportado + ' reportadas de ' + m.recibido + '</span></div>';
				}).join('') + '<span></span></div>';
			if (!eqUsuarios.length) {
				var hayEquipo = eqDatos.equipo.length > 0;
				html += '<div class="ep-eq-vacio"><p><strong>Nadie puede reportar este POP todavía.</strong>'
					+ (hayEquipo ? 'Agrega a los promotores de tu equipo y marca qué material reporta cada uno.' : 'No tienes promotores a tu cargo. Pídele al administrador que te los asigne en Usuarios.') + '</p>'
					+ (hayEquipo ? '<div class="ep-eq-acc"><button type="button" class="ep-eq-btn ep-eq-btn-p" data-accion="agregar">' + iconoMas + ' Agregar promotor</button><button type="button" class="ep-eq-btn" data-accion="todo">Agregar todo mi equipo (' + eqDatos.equipo.length + ')</button></div>' : '') + '</div>';
			} else if (eqModo && eqUsuarios.length >= 3) {
				html += '<div class="ep-eq-r ep-eq-todos" style="grid-template-columns:' + cols + '"><span>Todos los promotores</span>'
					+ mats.map(function (m) {
						var marcados = eqUsuarios.filter(function (u) { return eqMarcas[u][m.id]; }).length;
						return '<span class="ep-eq-c">' + casilla('data-todos="' + m.id + '"', marcados === eqUsuarios.length, false, 'Marcar ' + m.material + ' para todos') + '</span>';
					}).join('') + '<span></span><span></span></div>';
			}
			eqUsuarios.forEach(function (u) {
				var conBloqueo = mats.some(function (m) { return bloqueado(u, m.id); });
				html += '<div class="ep-eq-r ep-eq-fila" style="grid-template-columns:' + cols + '"><span class="ep-eq-who">' + avatarEq(nombreEq(u), fotoEq(u)) + '<b>' + escapar(nombreEq(u)) + '</b></span>'
					+ mats.map(function (m) {
						var cand = bloqueado(u, m.id);
						var extra = cand ? '<span class="ep-eq-lock">' + iconoCandado + 'Reportó ' + eqBloqueo[u][m.id] + '</span>' : '';
						return '<span class="ep-eq-c">' + casilla('data-u="' + u + '" data-f="' + m.id + '"', !!eqMarcas[u][m.id], cand || !eqModo, nombreEq(u) + ', ' + m.material + (cand ? ', ya reportó' : ''), extra) + '</span>';
					}).join('')
					+ '<span class="ep-eq-fin">' + (eqModo ? '<button type="button" class="ep-eq-txt" data-fila-todos="' + u + '" aria-label="Marcar todo el material para ' + escapar(nombreEq(u)) + '">Todos</button>' : '')
					+ (conBloqueo || !eqModo ? '' : '<button type="button" class="ep-eq-rm" data-quitar="' + u + '" aria-label="Quitar a ' + escapar(nombreEq(u)) + '" title="Quitar">' + iconoPapelera + '</button>') + '</span></div>';
			});
			eqMatriz.innerHTML = html;
			var asign = 0;
			eqUsuarios.forEach(function (u) { asign += Object.keys(eqMarcas[u]).filter(function (k) { return eqMarcas[u][k]; }).length; });
			var cambios = hayCambios();
			eqCuenta.textContent = cambios
				? 'Cambios sin guardar · ' + eqUsuarios.length + (eqUsuarios.length === 1 ? ' promotor' : ' promotores') + ' · ' + asign + (asign === 1 ? ' asignación' : ' asignaciones')
				: 'Editando tu equipo: agrega, quita o cambia lo que marcaste';
			eqBarra.hidden = !(cambios || (eqModo && hayGuardado()));
			eqBarra.classList.toggle('sin-cambios', !cambios);
			eqDescartar.textContent = cambios ? 'Descartar' : 'Cancelar';
			eqAgregar.disabled = !eqModo;
			eqAgregar.title = eqModo ? '' : 'Pulsa «Editar equipo» para agregar o cambiar promotores';
			eqEditar.hidden = eqModo;
			if (!eqModo) cerrarMenu();
		};
		var pintarMenu = function () {
			var q = eqBuscar.value.trim().toLowerCase();
			var libres = eqDatos.equipo.filter(function (p) { return eqUsuarios.indexOf(p.id) === -1 && (!q || p.nombre.toLowerCase().indexOf(q) !== -1); });
			eqLista.innerHTML = libres.length
				? libres.map(function (p) { return '<button type="button" class="ep-eq-it" data-add="' + p.id + '">' + avatarEq(p.nombre, p.foto) + '<b>' + escapar(p.nombre) + '</b>' + iconoMas + '</button>'; }).join('')
				: '<div class="ep-eq-sinmas">' + (eqDatos.equipo.length ? (q ? 'Nadie coincide.' : 'Ya agregaste a todo tu equipo.') : 'No tienes promotores a tu cargo. Pídele al administrador que te los asigne en Usuarios.') + '</div>';
		};
		var cerrarMenu = function () { eqPop.classList.add('hidden'); eqAgregar.setAttribute('aria-expanded', 'false'); };
		var abrirMenu = function () {
			if (!eqModo) return;
			eqBuscar.value = '';
			pintarMenu();
			eqPop.classList.remove('hidden');
			eqAgregar.setAttribute('aria-expanded', 'true');
			eqBuscar.focus();
		};
		var agregarTodos = function () {
			eqDatos.equipo.forEach(function (p) { if (eqUsuarios.indexOf(p.id) === -1) { eqUsuarios.push(p.id); eqMarcas[p.id] = {}; } });
			pintarEq();
			if (!eqPop.classList.contains('hidden')) pintarMenu();
		};

		eqEditar.addEventListener('click', function () {
			eqModo = true;
			pintarEq();
			eqAgregar.focus();
		});
		eqAgregar.addEventListener('click', function () { if (eqPop.classList.contains('hidden')) abrirMenu(); else cerrarMenu(); });
		eqBuscar.addEventListener('input', pintarMenu);
		// El menú queda abierto para sumar varios seguidos; se cierra con Esc o al hacer clic fuera.
		eqLista.addEventListener('click', function (ev) {
			var it = ev.target.closest('[data-add]');
			if (!it) return;
			var id = parseInt(it.dataset.add, 10);
			eqUsuarios.push(id);
			eqMarcas[id] = {};
			pintarEq();
			pintarMenu();
			eqBuscar.focus();
		});
		document.getElementById('epEqTodo').addEventListener('click', agregarTodos);
		// Un clic sobre algo que la propia lista ya repintó (isConnected falso) cuenta como clic dentro del menú.
		document.addEventListener('click', function (ev) { if (ev.target.isConnected && !ev.target.closest('.ep-eq-menu') && !ev.target.closest('[data-accion="agregar"]')) cerrarMenu(); });
		document.addEventListener('keydown', function (ev) {
			if (ev.key === 'Escape' && !eqPop.classList.contains('hidden')) { cerrarMenu(); eqAgregar.focus(); }
		});
		eqMatriz.addEventListener('change', function (ev) {
			var c = ev.target;
			if (c.dataset.todos) {
				eqUsuarios.forEach(function (u) { eqMarcas[u][c.dataset.todos] = c.checked || bloqueado(u, parseInt(c.dataset.todos, 10)); });
				pintarEq();
				enfocarEq('[data-todos="' + c.dataset.todos + '"]');
			} else if (c.dataset.u) {
				eqMarcas[c.dataset.u][c.dataset.f] = c.checked;
				pintarEq();
				enfocarEq('[data-u="' + c.dataset.u + '"][data-f="' + c.dataset.f + '"]');
			}
		});
		eqMatriz.addEventListener('click', function (ev) {
			var acc = ev.target.closest('[data-accion]');
			if (acc) { if (acc.dataset.accion === 'todo') agregarTodos(); else abrirMenu(); return; }
			var todos = ev.target.closest('[data-fila-todos]');
			if (todos) {
				var uid = parseInt(todos.dataset.filaTodos, 10);
				eqDatos.materiales.forEach(function (m) { eqMarcas[uid][m.id] = true; });
				pintarEq();
				enfocarEq('[data-fila-todos="' + uid + '"]');
				return;
			}
			var q = ev.target.closest('[data-quitar]');
			if (!q) return;
			var id = parseInt(q.dataset.quitar, 10);
			eqUsuarios = eqUsuarios.filter(function (u) { return u !== id; });
			delete eqMarcas[id];
			pintarEq();
			eqAgregar.focus();
		});
		eqDescartar.addEventListener('click', function () {
			if (!hayCambios()) { cargarEq(); pintarEq(); eqEditar.focus(); return; }
			if (!window.Swal) { cargarEq(); pintarEq(); return; }
			Swal.fire({ icon: 'warning', title: '¿Descartar los cambios?', text: 'Se pierde lo que marcaste desde la última vez que guardaste.', showCancelButton: true, cancelButtonText: 'Seguir editando', confirmButtonText: 'Sí, descartar', focusCancel: true })
				.then(function (res) { if (res.isConfirmed) { cargarEq(); pintarEq(); } });
		});
		window.addEventListener('beforeunload', function (ev) {
			if (!eqGuardado && hayCambios()) { ev.preventDefault(); ev.returnValue = ''; }
		});
		var enviarEq = function (envio) {
			eqGuardar.disabled = true;
			post('getters/pop_equipo.php', { id: eqDatos.id, asignaciones: JSON.stringify(envio) }).then(function (r) {
				eqGuardar.disabled = false;
				if (r && r.ok) { eqGuardado = true; recargarConAviso('Equipo guardado'); }
				else avisar('error', 'No se pudo guardar', (r && r.message) || 'Intenta de nuevo.');
			}).catch(function () {
				eqGuardar.disabled = false;
				avisar('error', 'No se pudo guardar', 'El servidor no respondió correctamente.');
			});
		};
		eqGuardar.addEventListener('click', function () {
			var envio = {};
			eqUsuarios.forEach(function (u) {
				var filas = eqDatos.materiales.filter(function (m) { return eqMarcas[u][m.id]; }).map(function (m) { return m.id; });
				if (filas.length) envio[u] = filas;
			});
			var habia = Object.keys(eqDatos.asignados).length > 0;
			if (!Object.keys(envio).length && !habia) { avisar('warning', 'Marca al menos una casilla', 'Agrega promotores y marca qué material reporta cada uno.'); return; }
			if (!Object.keys(envio).length && habia && window.Swal) {
				Swal.fire({ icon: 'warning', title: '¿Quitar a todo tu equipo?', text: 'Ningún promotor podrá reportar este POP hasta que los marques de nuevo.', showCancelButton: true, cancelButtonText: 'Cancelar', confirmButtonText: 'Sí, quitarlos', focusCancel: true })
					.then(function (res) { if (res.isConfirmed) enviarEq(envio); });
				return;
			}
			enviarEq(envio);
		});
		cargarEq();
		pintarEq();
	}

	// ---------- Seguimiento: período, filtro por material, grupos plegables y refresco en vivo ----------
	var sgCaja = document.getElementById('epPopSeg');
	if (sgCaja) {
		var sgFiltro = 0;
		var sgAbiertos = {};
		var sgCerrados = {};
		var sgVivo = { url: 'getters/pop_seguimiento_vivo.php?pop=' + sgCaja.dataset.pop, indicador: 'epPopVivo', cada: 10000 };

		var aplicarSeg = function () {
			var total = 0;
			sgCaja.querySelectorAll('.ep-sv-mat').forEach(function (b) {
				var on = parseInt(b.dataset.fila, 10) === sgFiltro;
				b.classList.toggle('on', on);
				b.setAttribute('aria-pressed', on ? 'true' : 'false');
			});
			sgCaja.querySelectorAll('.ep-sv-grupo').forEach(function (g) {
				var visibles = 0;
				g.querySelectorAll('.ep-sv-fila').forEach(function (fila) {
					var reportaron = (fila.dataset.filas || '').split(' ');
					var mostrar = !sgFiltro || reportaron.indexOf(String(sgFiltro)) !== -1;
					fila.hidden = !mostrar;
					visibles += mostrar ? 1 : 0;
					var abierto = sgFiltro ? mostrar : !!sgAbiertos[fila.dataset.id];
					fila.classList.toggle('abierto', abierto && !!fila.querySelector('.ep-sg-dt'));
					fila.querySelector('.ep-sv-sm').setAttribute('aria-expanded', abierto ? 'true' : 'false');
					fila.querySelectorAll('.ep-sg-dr[data-fila]:not(.ep-sg-tt)').forEach(function (dr) { dr.hidden = !!sgFiltro && parseInt(dr.dataset.fila, 10) !== sgFiltro; });
					fila.querySelectorAll('.ep-sg-tt[data-todos]').forEach(function (t) { t.hidden = !!sgFiltro; });
					fila.querySelectorAll('.ep-sg-tf').forEach(function (t) { t.hidden = !sgFiltro || parseInt(t.dataset.fila, 10) !== sgFiltro; });
					fila.querySelectorAll('.ep-sv-chip').forEach(function (c) { c.classList.toggle('apagado', !!sgFiltro && parseInt(c.dataset.fila, 10) !== sgFiltro); });
				});
				g.hidden = visibles === 0;
				g.querySelector('.ep-sv-cnt').textContent = visibles;
				g.open = !sgCerrados[g.dataset.grupo];
				total += visibles;
			});
			var vacio = sgCaja.querySelector('.ep-sv-vacio');
			if (vacio) vacio.hidden = !(sgFiltro && !total);
		};
		var pintarSeg = function (html) {
			var foco = document.activeElement;
			var recuerdo = foco && sgCaja.contains(foco) ? { mat: foco.dataset.fila || null, id: foco.closest('.ep-sv-fila') ? foco.closest('.ep-sv-fila').dataset.id : null } : null;
			sgCaja.innerHTML = html;
			if (sgFiltro && !sgCaja.querySelector('.ep-sv-mat[data-fila="' + sgFiltro + '"]')) sgFiltro = 0;
			aplicarSeg();
			if (recuerdo) {
				var igual = recuerdo.mat !== null && sgCaja.querySelector('.ep-sv-mat[data-fila="' + recuerdo.mat + '"]') || (recuerdo.id ? sgCaja.querySelector('.ep-sv-fila[data-id="' + recuerdo.id + '"] .ep-sv-sm') : null);
				if (igual) igual.focus();
			}
		};
		sgCaja.addEventListener('click', function (ev) {
			var mat = ev.target.closest('.ep-sv-mat');
			if (mat) {
				var id = parseInt(mat.dataset.fila, 10);
				sgFiltro = sgFiltro === id ? 0 : id;
				aplicarSeg();
				return;
			}
			var sm = ev.target.closest('.ep-sv-sm');
			if (sm && !sm.disabled && !sgFiltro) {
				var fila = sm.closest('.ep-sv-fila');
				sgAbiertos[fila.dataset.id] = !sgAbiertos[fila.dataset.id];
				aplicarSeg();
			}
		});
		// Recordar qué grupo plegó el usuario (el evento toggle no burbujea).
		sgCaja.addEventListener('toggle', function (ev) {
			var g = ev.target;
			if (g.classList && g.classList.contains('ep-sv-grupo') && !g.hidden) sgCerrados[g.dataset.grupo] = !g.open;
		}, true);

		// Período: año y mes; al elegir un mes se pide su vista y el refresco en vivo sigue ese mes.
		var periodo = document.querySelector('.ep-sv-periodo');
		var elegirMes = function (popId) {
			var url = 'getters/pop_seguimiento_vivo.php?pop=' + popId;
			fetch(url, { cache: 'no-store', credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
				if (!d || !d.html) { avisar('error', 'No se pudo abrir ese mes', 'Intenta de nuevo.'); return; }
				periodo.querySelectorAll('.ep-sv-mes').forEach(function (b) { var on = b.dataset.pop === String(popId); b.classList.toggle('on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
				sgVivo.url = url;
				sgCaja.dataset.pop = String(popId);
				sgFiltro = 0; sgAbiertos = {}; sgCerrados = {};
				pintarSeg(d.html);
			}).catch(function () { avisar('error', 'No se pudo abrir ese mes', 'El servidor no respondió correctamente.'); });
		};
		if (periodo) {
			periodo.addEventListener('click', function (ev) {
				var anio = ev.target.closest('.ep-sv-anio');
				if (anio) {
					periodo.querySelectorAll('.ep-sv-anio').forEach(function (b) { var on = b === anio; b.classList.toggle('on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
					var delAnio = [];
					periodo.querySelectorAll('.ep-sv-mes').forEach(function (b) { var ver = b.dataset.anio === anio.dataset.anio; b.hidden = !ver; if (ver) delAnio.push(b); });
					if (delAnio.length && !delAnio.some(function (b) { return b.classList.contains('on'); })) elegirMes(delAnio[0].dataset.pop);
					return;
				}
				var mes = ev.target.closest('.ep-sv-mes');
				if (mes && !mes.classList.contains('on')) elegirMes(mes.dataset.pop);
			});
		}
		aplicarSeg();
		if (window.epVivo) {
			sgVivo.alCambiar = function (d) {
				if (!d.html) return false;
				pintarSeg(d.html);
			};
			window.epVivo(sgVivo);
		}
	}

	// ---------- Acciones de cada mes ----------
	function idDe(ev) {
		var mes = ev.target.closest('.ep-pop-mes');
		return mes ? parseInt(mes.dataset.popId, 10) : 0;
	}
	document.addEventListener('click', function (ev) {
		if (ev.target.closest('.ep-pop-editar')) {
			var id = idDe(ev);
			var pop = abiertos.filter(function (p) { return p.id === id; })[0];
			if (pop && modal) abrirModal(pop);
			return;
		}
		if (ev.target.closest('.ep-pop-cerrar')) {
			confirmarCierre(idDe(ev), ev.target.closest('.ep-pop-mes'));
			return;
		}
		if (ev.target.closest('.ep-pop-eliminar')) confirmarEliminar(idDe(ev));
	});

	function confirmarCierre(id, seccion) {
		if (!window.Swal || !id) return;
		var avance = seccion ? (seccion.querySelector('.ep-pop-avance') || {}).textContent : '';
		Swal.fire({
			icon: 'warning',
			title: '¿Cerrar el mes?',
			html: '<p style="margin:0">' + (avance || '').trim() + '. Se genera el reporte con lo que los promotores ya reportaron y el mes deja de recibir.</p>',
			showCancelButton: true,
			cancelButtonText: 'Seguir abierto',
			confirmButtonText: 'Sí, cerrar el mes',
			confirmButtonColor: '#B25E00',
			focusCancel: true
		}).then(function (res) {
			if (!res.isConfirmed) return;
			post('getters/pop_cerrar.php', { id: id }).then(function (r) {
				if (r && r.ok && r.aviso) Swal.fire({ icon: 'warning', title: 'Cerrado sin reporte', text: r.aviso }).then(function () { location.reload(); });
				else if (r && r.ok) recargarConAviso('Mes cerrado y reporte generado');
				else avisar('error', 'No se pudo cerrar', (r && r.message) || 'Intenta de nuevo.');
			}).catch(function () { avisar('error', 'No se pudo cerrar', 'El servidor no respondió correctamente.'); });
		});
	}

	function confirmarEliminar(id) {
		if (!window.Swal || !id) return;
		Swal.fire({
			icon: 'warning',
			title: '¿Eliminar este mes?',
			text: 'Los promotores dejarán de ver su material. Los registros que ya enviaron no se borran.',
			showCancelButton: true,
			cancelButtonText: 'Cancelar',
			confirmButtonText: 'Sí, eliminar',
			confirmButtonColor: '#C5221F',
			focusCancel: true
		}).then(function (res) {
			if (!res.isConfirmed) return;
			post('getters/pop_eliminar.php', { id: id }).then(function (r) {
				if (r && r.ok) recargarConAviso('Mes eliminado');
				else avisar('error', 'No se pudo eliminar', (r && r.message) || 'Intenta de nuevo.');
			}).catch(function () { avisar('error', 'No se pudo eliminar', 'El servidor no respondió correctamente.'); });
		});
	}
})();

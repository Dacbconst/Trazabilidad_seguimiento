// Tab de Colocación de POP: pestañas, carga del mes (Fabricio), reparto del supervisor a su equipo y acciones de cada mes.
(function () {
	var panelAct = document.getElementById('epCalPanelAct');
	var panelPop = document.getElementById('epCalPanelPop');
	var btnCalNuevo = document.getElementById('epCalNuevo');
	if (!panelAct || !panelPop) return;

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

	// ---------- Pestañas ----------
	document.querySelectorAll('.ep-cl-tab').forEach(function (tab) {
		tab.addEventListener('click', function () {
			var esPop = tab.dataset.tab === 'pop';
			document.querySelectorAll('.ep-cl-tab').forEach(function (t) {
				t.classList.toggle('activo', t === tab);
				t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
			});
			panelAct.classList.toggle('hidden', esPop);
			panelPop.classList.toggle('hidden', !esPop);
			// Cada pestaña tiene su propio botón de crear; el de Activaciones no aplica en POP.
			if (btnCalNuevo) btnCalNuevo.classList.toggle('hidden', esPop);
		});
	});

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
		var opciones = function (lista, vacio) {
			return '<option value="">' + vacio + '</option>' + lista.map(function (n) { return '<option value="' + escapar(n) + '">' + escapar(n) + '</option>'; }).join('');
		};
		// Un valor que ya no está en el repositorio (quitado después de cargar el mes) se conserva para poder corregir el mes.
		var fijarSelect = function (sel, valor) {
			if (valor && !Array.prototype.some.call(sel.options, function (o) { return o.value === valor; })) sel.insertAdjacentHTML('beforeend', '<option value="' + escapar(valor) + '">' + escapar(valor) + '</option>');
			sel.value = valor;
		};
		var filaHTML = function () {
			return '<div class="ep-pop-fila" data-id="0">'
				+ '<select class="ep-input ep-pop-fila-material" aria-label="Material">' + opciones(catalogo.materiales, 'Elige el material') + '</select>'
				+ '<select class="ep-input ep-pop-fila-campana" aria-label="Campaña">' + opciones(catalogo.campanas, 'Elige la campaña') + '</select>'
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
					material: f.querySelector('.ep-pop-fila-material').value.trim().toUpperCase(),
					campana: f.querySelector('.ep-pop-fila-campana').value.trim().toUpperCase(),
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
					fijarSelect(fila.querySelector('.ep-pop-fila-material'), f.material);
					fijarSelect(fila.querySelector('.ep-pop-fila-campana'), f.campana);
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
			if (q) quitarSup(parseInt(q.dataset.sup, 10));
		});
		filas.addEventListener('input', function (ev) {
			if (ev.target.classList.contains('ep-pop-fila-bodega') || ev.target.classList.contains('ep-pop-fila-sup')) recalcular(ev.target.closest('.ep-pop-fila'));
		});
		var btnNuevo = document.getElementById('epPopNuevo');
		if (btnNuevo) btnNuevo.addEventListener('click', function () {
			if (btnNuevo.dataset.sinCatalogo) { avisar('info', 'Primero llena los repositorios', 'Para cargar un mes hacen falta materiales y campañas. Súbelos en Repositorios.'); return; }
			abrirModal(null);
		});
		document.getElementById('epPopAgregar').addEventListener('click', function () { agregarFila().querySelector('select').focus(); });
		document.getElementById('epPopCancelar').addEventListener('click', cerrarModal);
		document.getElementById('epPopModalCerrar').addEventListener('click', cerrarModal);
		document.getElementById('epPopModalFondo').addEventListener('click', cerrarModal);
		// Siempre queda al menos una fila: la última solo se vacía.
		filas.addEventListener('click', function (ev) {
			if (!ev.target.closest('.ep-modelo-quitar')) return;
			if (filas.querySelectorAll('.ep-pop-fila').length > 1) ev.target.closest('.ep-pop-fila').remove();
			else { filas.querySelectorAll('input, select').forEach(function (i) { i.value = ''; }); recalcular(filas.querySelector('.ep-pop-fila')); }
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
				if (r && r.ok) location.reload();
				else avisar('error', 'No se pudo guardar', (r && r.message) || 'Intenta de nuevo.');
			}).catch(function () {
				btnGuardar.disabled = false;
				avisar('error', 'No se pudo guardar', 'El servidor no respondió correctamente.');
			});
		});
	}

	// ---------- Reparto del supervisor a sus promotores ----------
	var repModal = document.getElementById('epPopRepModal');
	var miReparto = leerJson('epPopMiReparto', null);
	if (repModal && miReparto) {
		var repTabla = document.getElementById('epPopRepTabla');
		var repGuardar = document.getElementById('epPopRepGuardar');

		var recalcularSaldos = function () {
			miReparto.materiales.forEach(function (m, i) {
				var total = 0;
				repTabla.querySelectorAll('.ep-pop-rep-in[data-col="' + i + '"]').forEach(function (inp) { total += parseInt(inp.value, 10) || 0; });
				var saldo = m.recibido - total;
				var el = repTabla.querySelector('.ep-pop-rep-saldo[data-col="' + i + '"]');
				el.textContent = saldo + ' de ' + m.recibido;
				el.classList.toggle('ep-pop-negativo', saldo < 0);
			});
		};
		// Filas = promotores del equipo, columnas = materiales; abajo, lo que queda por repartir de lo recibido.
		var pintarReparto = function () {
			var cols = miReparto.materiales;
			var rejilla = 'grid-template-columns: minmax(170px, 1.6fr) repeat(' + cols.length + ', minmax(96px, 1fr));';
			var html = '<div class="ep-pop-rep-fila ep-pop-rep-th" style="' + rejilla + '"><span>Promotor</span>'
				+ cols.map(function (m) { return '<span>' + escapar(m.material) + '<small>' + escapar(m.campana) + '</small></span>'; }).join('') + '</div>';
			miReparto.equipo.forEach(function (p) {
				html += '<div class="ep-pop-rep-fila" style="' + rejilla + '"><span class="ep-pop-rep-nombre">' + escapar(p.nombre) + '</span>'
					+ cols.map(function (m, i) {
						var v = (m.reparto || {})[p.id] || '';
						var min = (p.reportado || {})[m.fila_id] || 0;
						return '<input type="number" min="' + min + '" inputmode="numeric" class="ep-input ep-pop-rep-in" data-promotor="' + p.id + '" data-col="' + i + '" placeholder="0" value="' + v + '"' + (min ? ' title="Ya reportó ' + min + '"' : '') + '>';
					}).join('') + '</div>';
			});
			html += '<div class="ep-pop-rep-fila ep-pop-rep-pie" style="' + rejilla + '"><span>Por repartir</span>'
				+ cols.map(function (m, i) { return '<span class="ep-pop-rep-saldo" data-col="' + i + '"></span>'; }).join('') + '</div>';
			repTabla.innerHTML = html;
			recalcularSaldos();
		};
		var cerrarRep = function () { repModal.classList.add('hidden'); };

		repTabla.addEventListener('input', function (ev) { if (ev.target.classList.contains('ep-pop-rep-in')) recalcularSaldos(); });
		['epPopRepCerrar', 'epPopRepCancelar', 'epPopRepFondo'].forEach(function (id) { document.getElementById(id).addEventListener('click', cerrarRep); });
		document.addEventListener('click', function (ev) {
			if (!ev.target.closest('.ep-pop-repartir')) return;
			pintarReparto();
			repModal.classList.remove('hidden');
		});
		repGuardar.addEventListener('click', function () {
			var envio = miReparto.materiales.map(function (m, i) {
				var reparto = {};
				repTabla.querySelectorAll('.ep-pop-rep-in[data-col="' + i + '"]').forEach(function (inp) {
					var v = parseInt(inp.value, 10) || 0;
					if (v > 0) reparto[inp.dataset.promotor] = v;
				});
				return { fila_id: m.fila_id, reparto: reparto };
			});
			var pasado = miReparto.materiales.filter(function (m, i) {
				var t = 0;
				Object.keys(envio[i].reparto).forEach(function (k) { t += envio[i].reparto[k]; });
				return t > m.recibido;
			})[0];
			if (pasado) {
				avisar('warning', 'Revisa las cantidades', 'En ' + pasado.material + ' repartes más de lo que recibiste.');
				return;
			}
			repGuardar.disabled = true;
			post('getters/pop_repartir.php', { id: miReparto.id, filas: JSON.stringify(envio) }).then(function (r) {
				repGuardar.disabled = false;
				if (r && r.ok) location.reload();
				else avisar('error', 'No se pudo guardar', (r && r.message) || 'Intenta de nuevo.');
			}).catch(function () {
				repGuardar.disabled = false;
				avisar('error', 'No se pudo guardar', 'El servidor no respondió correctamente.');
			});
		});
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
				else if (r && r.ok) location.reload();
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
				if (r && r.ok) location.reload();
				else avisar('error', 'No se pudo eliminar', (r && r.message) || 'Intenta de nuevo.');
			}).catch(function () { avisar('error', 'No se pudo eliminar', 'El servidor no respondió correctamente.'); });
		});
	}
})();

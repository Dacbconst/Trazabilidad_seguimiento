(function () {
	var vistaLista = document.getElementById('ac-liquidacion-lista');
	var vistaPendientes = document.getElementById('ac-liquidacion-pendientes');
	var tablaBody = document.getElementById('liq-tabla-body');
	var pendientesBody = document.getElementById('liq-pendientes-body');

	function escapeHtml(texto) {
		var div = document.createElement('div');
		div.textContent = texto == null ? '' : String(texto);
		return div.innerHTML;
	}

	var mesesCorto = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
	var etiquetaEstado = { procesando: 'Procesando', completado: 'Completado', con_errores: 'Con errores' };
	var etiquetaCanal = { directa: 'Directa', distribuidor: 'Distribuidor' };

	// Mismo período detectado del archivo (no elegido a mano) — un solo mes
	// se muestra una vez, un rango se muestra "Abr - Jun".
	function periodoTexto(mesInicio, mesFin) {
		mesInicio = parseInt(mesInicio, 10); mesFin = parseInt(mesFin, 10);
		if (mesInicio === mesFin) return mesesCorto[mesInicio];
		return mesesCorto[mesInicio] + ' - ' + mesesCorto[mesFin];
	}

	// ---------- Listado de importaciones ----------
	function cargarImportaciones() {
		tablaBody.innerHTML = '<tr><td colspan="7" class="ac-table-empty">Cargando...</td></tr>';
		fetch('getters/listar_liquidacion_importaciones.php')
			.then(function (r) { return r.json(); })
			.then(function (data) {
				var filas = (data.ok && data.importaciones) ? data.importaciones : [];
				if (!filas.length) {
					tablaBody.innerHTML = '<tr><td colspan="7" class="ac-table-empty">Todavía no se subió ningún Excel.</td></tr>';
					return;
				}
				tablaBody.innerHTML = filas.map(function (f) {
					var pendientes = parseInt(f.filas_pendientes, 10) || 0;
					var accion = pendientes > 0
						? '<button type="button" class="ac-link-id liq-btn-pendientes" data-id="' + f.id + '">Resolver (' + pendientes + ')</button>'
						: '<span class="ac-field-hint">—</span>';
					return '<tr>' +
						'<td>' + (etiquetaCanal[f.canal] || escapeHtml(f.canal)) + '</td>' +
						'<td>' + periodoTexto(f.mes_inicio, f.mes_fin) + ' ' + f.anio + '</td>' +
						'<td>' + escapeHtml(f.nombre_archivo) + '</td>' +
						'<td class="ac-text-center"><span class="ac-badge ac-badge-desarrollador">' + (etiquetaEstado[f.estado] || escapeHtml(f.estado)) + '</span></td>' +
						'<td class="ac-text-right ac-tabular">' + f.total_filas + '</td>' +
						'<td class="ac-text-right ac-tabular">' + pendientes + '</td>' +
						'<td class="ac-text-right">' + accion + '</td>' +
						'</tr>';
				}).join('');
				Array.prototype.forEach.call(tablaBody.querySelectorAll('.liq-btn-pendientes'), function (btn) {
					btn.addEventListener('click', function () { abrirPendientes(parseInt(btn.dataset.id, 10)); });
				});
			})
			.catch(function () {
				tablaBody.innerHTML = '<tr><td colspan="7" class="ac-table-empty">Error al cargar las importaciones.</td></tr>';
			});
	}

	document.getElementById('liq-actualizar').addEventListener('click', cargarImportaciones);

	// ---------- Modal: Subir Excel ----------
	var subirModalOverlay = document.getElementById('liq-subir-modal-overlay');
	var formSubir = document.getElementById('liq-form-subir');
	var submitBtn = document.getElementById('liq-subir-submit');

	document.getElementById('liq-abrir-subir').addEventListener('click', function () {
		formSubir.reset();
		subirModalOverlay.classList.add('ac-modal-open');
	});
	document.getElementById('liq-subir-modal-close').addEventListener('click', function () {
		subirModalOverlay.classList.remove('ac-modal-open');
	});
	subirModalOverlay.addEventListener('click', function (e) {
		if (e.target === subirModalOverlay) subirModalOverlay.classList.remove('ac-modal-open');
	});

	formSubir.addEventListener('submit', function (e) {
		e.preventDefault();
		submitBtn.disabled = true;
		submitBtn.textContent = 'Procesando...';

		fetch('getters/importar_liquidacion.php', {
			method: 'POST',
			body: new FormData(formSubir),
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				submitBtn.disabled = false;
				submitBtn.textContent = 'Procesar Excel';
				mostrarToast(data.message, data.ok ? 'success' : 'error');
				if (data.ok) {
					subirModalOverlay.classList.remove('ac-modal-open');
					if (data.filas_pendientes > 0) {
						mostrarToast(data.filas_pendientes + ' filas quedaron pendientes de asignar a mano.', 'warning');
					}
					cargarImportaciones();
				}
			})
			.catch(function () {
				submitBtn.disabled = false;
				submitBtn.textContent = 'Procesar Excel';
				mostrarToast('Error de conexión al subir el archivo.', 'error');
			});
	});

	// ---------- Pendientes de Asignar ----------
	function abrirPendientes(importacionId) {
		vistaLista.classList.add('hidden');
		vistaPendientes.classList.remove('hidden');
		pendientesBody.innerHTML = '<tr><td colspan="5" class="ac-table-empty">Cargando...</td></tr>';
		window.scrollTo(0, 0);

		fetch('getters/liquidacion_pendientes.php?importacion_id=' + importacionId)
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.ok) { mostrarToast(data.message || 'Error al cargar pendientes.', 'error'); return; }
				renderPendientes(data.pendientes);
			})
			.catch(function () {
				pendientesBody.innerHTML = '<tr><td colspan="5" class="ac-table-empty">Error al cargar pendientes.</td></tr>';
			});
	}

	document.getElementById('liq-volver-lista').addEventListener('click', function () {
		vistaPendientes.classList.add('hidden');
		vistaLista.classList.remove('hidden');
		cargarImportaciones();
	});

	function renderPendientes(filas) {
		if (!filas.length) {
			pendientesBody.innerHTML = '<tr><td colspan="5" class="ac-table-empty">No quedan filas pendientes — todo se resolvió.</td></tr>';
			return;
		}
		pendientesBody.innerHTML = filas.map(function (f) {
			var candidatosHtml = '';
			if (f.candidatos && f.candidatos.length) {
				candidatosHtml = f.candidatos.map(function (c) {
					return '<button type="button" class="ac-btn-outline ac-btn-inline liq-btn-candidato" style="margin:2px;" ' +
						'data-tabla="' + f.tabla + '" data-id="' + f.id + '" data-pos-id="' + escapeHtml(c.pos_id) + '">' +
						escapeHtml(c.pos_name) + ' <span class="ac-field-hint">(' + escapeHtml(c.pos_id) + ')</span></button>';
				}).join('');
			}
			// El estado se deriva de los candidatos recalculados AHORA (no del
			// estado_match guardado en el momento de importar) — si el maestro
			// de clientes cambió desde entonces, puede haber más o menos
			// candidatos que cuando se subió el Excel originalmente.
			var cantidadCandidatos = f.candidatos ? f.candidatos.length : 0;
			var etiquetaEstadoFila = cantidadCandidatos === 0
				? 'Sin candidatos'
				: 'Ambiguo (' + cantidadCandidatos + ' posibles)';
			return '<tr data-fila-tabla="' + f.tabla + '" data-fila-id="' + f.id + '">' +
				'<td>' + (f.tabla === 'cuota_categoria' ? 'Cuota/Venta' : 'Visibilidad') + '</td>' +
				'<td>' + escapeHtml(f.cedi_o_distribuidor) + '</td>' +
				'<td>' + escapeHtml(f.cliente_o_nombre) + '</td>' +
				'<td>' + etiquetaEstadoFila + '</td>' +
				'<td>' +
					'<div class="liq-candidatos">' + candidatosHtml + '</div>' +
					'<div class="ac-combo" style="margin-top:6px;">' +
						'<input type="text" class="ac-input ac-mini-input liq-busqueda-input" placeholder="Buscar cliente por nombre..." autocomplete="off">' +
					'</div>' +
					'<div class="liq-busqueda-resultados"></div>' +
					'<button type="button" class="ac-btn-outline ac-btn-inline liq-btn-sin-acta" style="margin-top:6px;" ' +
						'data-tabla="' + f.tabla + '" data-id="' + f.id + '">No tiene Acta (dato histórico)</button>' +
				'</td>' +
				'</tr>';
		}).join('');

		Array.prototype.forEach.call(pendientesBody.querySelectorAll('.liq-btn-candidato'), function (btn) {
			btn.addEventListener('click', function () {
				resolverFila(btn.dataset.tabla, btn.dataset.id, btn.dataset.posId, btn.closest('tr'), 'matchear');
			});
		});

		Array.prototype.forEach.call(pendientesBody.querySelectorAll('.liq-btn-sin-acta'), function (btn) {
			btn.addEventListener('click', function () {
				if (!confirm('¿Confirmás que esta fila es un dato histórico y no tiene ninguna Acta en el sistema? Esto no se puede deshacer desde acá.')) return;
				resolverFila(btn.dataset.tabla, btn.dataset.id, '', btn.closest('tr'), 'sin_acta');
			});
		});

		var buscarTimeout = null;
		Array.prototype.forEach.call(pendientesBody.querySelectorAll('.liq-busqueda-input'), function (input) {
			input.addEventListener('input', function () {
				clearTimeout(buscarTimeout);
				var tr = input.closest('tr');
				var contenedor = tr.querySelector('.liq-busqueda-resultados');
				var q = input.value.trim();
				if (q.length < 3) { contenedor.innerHTML = ''; return; }
				buscarTimeout = setTimeout(function () {
					fetch('getters/liquidacion_buscar_pos.php?q=' + encodeURIComponent(q))
						.then(function (r) { return r.json(); })
						.then(function (data) {
							var resultados = (data.ok && data.resultados) ? data.resultados : [];
							if (!resultados.length) {
								contenedor.innerHTML = '<p class="ac-field-hint">Sin resultados.</p>';
								return;
							}
							contenedor.innerHTML = resultados.map(function (r) {
								return '<button type="button" class="ac-btn-outline ac-btn-inline liq-btn-resultado-busqueda" style="margin:2px; display:block;" data-pos-id="' + escapeHtml(r.pos_id) + '">' +
									escapeHtml(r.pos_name) + ' <span class="ac-field-hint">(' + escapeHtml(r.pos_id) + (r.supervisor ? ' · ' + escapeHtml(r.supervisor) : '') + ')</span></button>';
							}).join('');
							Array.prototype.forEach.call(contenedor.querySelectorAll('.liq-btn-resultado-busqueda'), function (btn) {
								btn.addEventListener('click', function () {
									resolverFila(tr.dataset.filaTabla, tr.dataset.filaId, btn.dataset.posId, tr, 'matchear');
								});
							});
						});
				}, 350);
			});
		});
	}

	function resolverFila(tabla, id, posId, tr, accion) {
		fetch('getters/liquidacion_resolver_match.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({ tabla: tabla, id: id, pos_id: posId, accion: accion }),
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				mostrarToast(data.message, data.ok);
				if (data.ok && tr) tr.remove();
			})
			.catch(function () { mostrarToast('Error de conexión al resolver.', 'error'); });
	}

	cargarImportaciones();
})();

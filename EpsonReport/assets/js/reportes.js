// Reportes mensuales: asistente (tipo y mes -> calendario -> registros), guardado y descarga del PPT.
(function () {
	var root = document.getElementById('epRp');
	if (!root) return;

	var modal = document.getElementById('epRpModal');
	var pasos = Array.prototype.slice.call(root.querySelectorAll('.ep-rp-paso'));
	var pasosBarra = Array.prototype.slice.call(root.querySelectorAll('.ep-rp-step'));
	var resumenPie = document.getElementById('epRpResumenPie');
	var txtSig = document.getElementById('epRpSigTxt');
	var btnAtras = document.getElementById('epRpAtras');
	var btnSig = document.getElementById('epRpSiguiente');
	var selTipo = document.getElementById('epRpTipo');
	var inpMes = document.getElementById('epRpMes');
	var inpTitulo = document.getElementById('epRpTituloTxt');
	var inpCal = document.getElementById('epRpCalendario');
	var imgCal = document.getElementById('epRpCalPrev');
	var drop = document.getElementById('epRpDrop');
	var dropVacio = document.getElementById('epRpDropVacio');
	var btnQuitarFoto = document.getElementById('epRpQuitarFoto');
	var inpProg = document.getElementById('epRpProgramadas');
	var chkTodos = document.getElementById('epRpTodos');
	var contador = document.getElementById('epRpContador');
	var contReg = document.getElementById('epRpRegistros');
	var paso = 1;
	var calBlob = null;

	function aviso(icono, titulo, texto) {
		if (window.Swal) return Swal.fire({ icon: icono, title: titulo, html: texto || '', confirmButtonColor: '#10218B', allowOutsideClick: false });
		alert(titulo + (texto ? ' ' + texto : ''));
		return Promise.resolve();
	}
	function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

	function mostrarPaso(n) {
		paso = n;
		pasos.forEach(function (p) { p.classList.toggle('hidden', Number(p.dataset.paso) !== n); });
		pasosBarra.forEach(function (s) {
			var num = Number(s.dataset.paso);
			s.classList.toggle('activo', num === n);
			s.classList.toggle('hecho', num < n);
		});
		btnAtras.style.visibility = n === 1 ? 'hidden' : 'visible';
		txtSig.textContent = n === 3 ? 'Generar y descargar' : 'Siguiente';
		resumenPie.textContent = selTipo.options[selTipo.selectedIndex].text.replace(' (próximamente)', '') + ' · ' + (inpMes.value || 'sin mes');
	}
	function abrir() {
		quitarCalendario();
		inpProg.value = ''; inpTitulo.value = ''; contReg.innerHTML = '';
		mostrarPaso(1);
		modal.classList.remove('hidden');
	}
	function cerrar() { modal.classList.add('hidden'); }
	document.getElementById('epRpNuevo').addEventListener('click', abrir);
	document.getElementById('epRpCerrar').addEventListener('click', cerrar);
	document.getElementById('epRpFondo').addEventListener('click', cerrar);

	// La imagen del calendario se reduce antes de subir (evita el límite de tamaño del servidor).
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
					c.width = Math.round(img.width * escala); c.height = Math.round(img.height * escala);
					var ctx = c.getContext('2d');
					ctx.fillStyle = '#FFFFFF'; ctx.fillRect(0, 0, c.width, c.height);
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
	// Dropzone (mismo diseño que las casillas de fotos del formulario): clic o arrastrar.
	function mostrarCalendario(f) {
		if (!f || !/^image\/(jpeg|png)$/.test(f.type)) { aviso('warning', 'Imagen no válida', 'Sube una imagen JPG o PNG.'); return; }
		comprimir(f).then(function (b) {
			calBlob = b;
			imgCal.src = URL.createObjectURL(b);
			imgCal.classList.remove('hidden');
			dropVacio.classList.add('hidden');
			btnQuitarFoto.classList.remove('hidden');
		});
	}
	function quitarCalendario() {
		calBlob = null; inpCal.value = '';
		imgCal.classList.add('hidden'); imgCal.removeAttribute('src');
		dropVacio.classList.remove('hidden');
		btnQuitarFoto.classList.add('hidden');
	}
	inpCal.addEventListener('change', function () { if (inpCal.files && inpCal.files[0]) mostrarCalendario(inpCal.files[0]); });
	btnQuitarFoto.addEventListener('click', quitarCalendario);
	['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); e.stopPropagation(); drop.classList.add('drag-active'); }); });
	['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); e.stopPropagation(); drop.classList.remove('drag-active'); }); });
	drop.addEventListener('drop', function (e) { var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]; if (f) mostrarCalendario(f); });

	function actualizarContador() {
		var marcados = contReg.querySelectorAll('input[type="checkbox"]:checked').length;
		var total = contReg.querySelectorAll('input[type="checkbox"]').length;
		contador.textContent = marcados + ' de ' + total + ' seleccionados';
		chkTodos.checked = total > 0 && marcados === total;
	}
	contReg.addEventListener('change', actualizarContador);
	chkTodos.addEventListener('change', function () {
		contReg.querySelectorAll('input[type="checkbox"]').forEach(function (c) { c.checked = chkTodos.checked; });
		actualizarContador();
	});

	function cargarRegistros() {
		contReg.innerHTML = '<div class="ep-rp-nota">Cargando registros...</div>';
		return fetch('getters/reportes_registros.php?tipo=' + encodeURIComponent(selTipo.value) + '&mes=' + encodeURIComponent(inpMes.value))
			.then(function (r) { return r.json(); })
			.then(function (d) {
				if (!d.success) { contReg.innerHTML = ''; aviso('error', 'No se pudo cargar', esc(d.error || 'Intenta de nuevo.')); return false; }
				if (!d.registros.length) {
					contReg.innerHTML = '<div class="ep-rp-nota">No hay registros de ese tipo en ese mes.</div>';
					actualizarContador();
					return true;
				}
				contReg.innerHTML = d.registros.map(function (r) {
					return '<label class="ep-rp-reg"><input type="checkbox" value="' + r.id + '" checked>'
						+ '<span class="ep-rp-reg-f">' + esc(r.fecha.split('-').reverse().join('/')) + ' <small>' + esc(r.hora) + '</small></span>'
						+ '<span class="ep-rp-reg-p">' + esc(r.promotor) + '</span>'
						+ '<span class="ep-rp-reg-n">' + r.fotos + ' fotos</span></label>';
				}).join('');
				actualizarContador();
				return true;
			})
			.catch(function () { contReg.innerHTML = ''; aviso('error', 'Sin conexión', 'No se pudieron cargar los registros.'); return false; });
	}

	function descargar(id) {
		if (window.Swal) Swal.fire({ title: 'Generando presentación', html: 'Esto puede tardar un momento según la cantidad de fotos.', allowOutsideClick: false, showConfirmButton: false, didOpen: function () { Swal.showLoading(); } });
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
				document.body.appendChild(a); a.click(); a.remove();
				if (window.Swal) Swal.close();
			})
			.catch(function (e) { if (window.Swal) Swal.close(); aviso('error', 'No se pudo descargar', esc(e.message)); });
	}

	function generar() {
		var ids = Array.prototype.slice.call(contReg.querySelectorAll('input[type="checkbox"]:checked')).map(function (c) { return Number(c.value); });
		if (!ids.length) { aviso('warning', 'Falta seleccionar', 'Marca al menos un registro para el reporte.'); return; }
		var fd = new FormData();
		fd.append('tipo', selTipo.value);
		fd.append('mes', inpMes.value);
		fd.append('titulo', inpTitulo.value.trim());
		fd.append('programadas', inpProg.value);
		fd.append('registros', JSON.stringify(ids));
		if (calBlob) fd.append('calendario', calBlob, 'calendario.jpg');
		btnSig.disabled = true;
		if (window.Swal) Swal.fire({ title: 'Guardando reporte', allowOutsideClick: false, showConfirmButton: false, didOpen: function () { Swal.showLoading(); } });
		fetch('getters/reporte_guardar.php', { method: 'POST', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				if (!d.success) {
					if (window.Swal) Swal.close();
					btnSig.disabled = false;
					return aviso('error', 'No se pudo guardar', esc(d.error || 'Intenta de nuevo.')).then(function () { if (d.redirect) window.location.href = d.redirect; });
				}
				cerrar();
				return descargar(d.id).then(function () { window.location.reload(); });
			})
			.catch(function () { if (window.Swal) Swal.close(); btnSig.disabled = false; aviso('error', 'Sin conexión', 'No se pudo guardar el reporte. Intenta de nuevo.'); });
	}

	btnAtras.addEventListener('click', function () { if (paso > 1) mostrarPaso(paso - 1); });
	btnSig.addEventListener('click', function () {
		if (paso === 1) {
			if (!inpMes.value) { aviso('warning', 'Falta el mes', 'Elige el mes del reporte.'); return; }
			mostrarPaso(2);
		} else if (paso === 2) {
			btnSig.disabled = true;
			cargarRegistros().then(function (ok) { btnSig.disabled = false; if (ok) mostrarPaso(3); });
		} else {
			generar();
		}
	});

	// Lista: descargar de nuevo y quitar del histórico.
	root.addEventListener('click', function (ev) {
		var d = ev.target.closest('.ep-rp-descargar');
		if (d) { descargar(d.dataset.id); return; }
		var q = ev.target.closest('.ep-rp-quitar');
		if (!q) return;
		var confirmar = window.Swal
			? Swal.fire({ icon: 'question', title: 'Quitar reporte', text: 'Se quita del histórico. Los registros no se borran.', showCancelButton: true, confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar', confirmButtonColor: '#10218B' }).then(function (r) { return r.isConfirmed; })
			: Promise.resolve(confirm('¿Quitar este reporte del histórico?'));
		confirmar.then(function (ok) {
			if (!ok) return;
			var fd = new FormData(); fd.append('id', q.dataset.id);
			fetch('getters/reporte_eliminar.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (d2) {
				if (d2.success) window.location.reload(); else aviso('error', 'No se pudo quitar', esc(d2.error || ''));
			});
		});
	});
})();

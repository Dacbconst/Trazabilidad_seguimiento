// Usuarios: lista con filtros y panel lateral para crear, editar, cambiar clave, foto y estado.
(function () {
	var root = document.getElementById('epUs');
	var datosEl = document.getElementById('epUsDatos');
	if (!root || !datosEl) return;

	var usuarios = JSON.parse(datosEl.textContent);
	var claveMinima = parseInt(root.dataset.claveMinima, 10) || 6;
	var tonos = ['#513487', '#6A4DAA', '#3D2768', '#7C5FC0'];
	var filtro = { q: '', rol: '', estado: '' };
	var actual = null;
	var fotoPendiente = null;
	var nuevo = false;

	function $(id) { return document.getElementById(id); }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function iniciales(n) { return n.split(' ').filter(Boolean).slice(0, 2).map(function (w) { return w[0]; }).join('').toUpperCase(); }
	function estiloAv(u) { return u.foto ? 'background-image:url(\'' + esc(u.foto) + '\')' : 'background-color:' + tonos[u.id % 4]; }

	function toast(icono, titulo) {
		if (!window.Swal) { alert(titulo); return; }
		Swal.mixin({ toast: true, position: 'top', showConfirmButton: false, timer: 3200, timerProgressBar: true }).fire({ icon: icono, title: titulo });
	}

	// POST con FormData; devuelve el JSON o un error legible si el servidor respondió otra cosa.
	function enviar(url, datos) {
		var fd = new FormData();
		Object.keys(datos).forEach(function (k) { fd.append(k, datos[k]); });
		return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.catch(function () { return { ok: false, message: 'No se pudo conectar con el servidor.' }; });
	}

	function visibles() {
		var q = filtro.q;
		return usuarios.filter(function (u) {
			if (filtro.rol && u.rol !== filtro.rol) return false;
			if (filtro.estado !== '' && String(u.activo ? 1 : 0) !== filtro.estado) return false;
			return !q || (u.nombre + ' ' + u.correo + ' ' + u.usuario).toLowerCase().indexOf(q) !== -1;
		});
	}

	function pintarLista() {
		var lista = visibles();
		var h = '<div class="ep-us-fila ep-us-hd"><span>Usuario</span><span class="ep-us-c-ciudad">Ciudad</span><span class="ep-us-c-rol">Rol</span><span class="ep-us-c-est">Estado</span><span></span></div>';
		lista.forEach(function (u) {
			h += '<div class="ep-us-fila' + (actual && actual.id === u.id ? ' on' : '') + '" tabindex="0" role="button" data-id="' + u.id + '">' +
				'<div class="ep-us-quien"><div class="ep-us-av" style="' + estiloAv(u) + '">' + (u.foto ? '' : esc(iniciales(u.nombre))) + '</div><div><b>' + esc(u.nombre) + '</b><span>' + esc(u.correo || u.usuario) + '</span></div></div>' +
				'<span class="ep-us-celda ep-us-c-ciudad">' + (u.ciudad ? esc(u.ciudad) : '—') + '</span>' +
				'<span class="ep-us-c-rol"><span class="ep-us-tag ep-us-tag-' + esc(u.rol) + '">' + esc(u.rol_label) + '</span></span>' +
				'<span class="ep-us-c-est"><span class="ep-us-tag ' + (u.activo ? 'ep-us-tag-on' : 'ep-us-tag-off') + '"><i></i>' + (u.activo ? 'Activo' : 'Inactivo') + '</span></span>' +
				'<svg class="ep-us-chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></div>';
		});
		if (!lista.length) h += '<div class="ep-us-vacio">Ningún usuario coincide con la búsqueda.</div>';
		$('epUsLista').innerHTML = h;
		$('epUsCuenta').textContent = lista.length + ' de ' + usuarios.length + ' usuarios';
	}

	// ---- Clave (formulario de cambio y clave inicial) ----
	function claveValida(a, b) { return a.length >= claveMinima && a === b; }
	function revisarClave() {
		var a = $('epUsP1').value, b = $('epUsP2').value, ok = claveValida(a, b);
		$('epUsPErr').classList.toggle('on', !!b && a !== b);
		$('epUsPOk').classList.toggle('on', ok);
		$('epUsClaveGuardar').disabled = !ok;
	}
	function cerrarFormClave() {
		$('epUsClaveForm').classList.remove('on');
		$('epUsClaveAbrir').style.display = '';
		['epUsP1', 'epUsP2', 'epUsC1', 'epUsC2'].forEach(function (id) { $(id).value = ''; $(id).type = 'password'; });
		root.querySelectorAll('.ep-us-eye').forEach(function (b) { b.setAttribute('aria-label', 'Mostrar clave'); });
		revisarClave();
	}
	function generarClave() {
		var c = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789', p = '';
		for (var i = 0; i < 10; i++) p += c[Math.floor(Math.random() * c.length)];
		return p;
	}

	// ---- Ruta del promotor: categorías y supervisores (solo promotores y solo si ya existen las columnas) ----
	function opcionesSup(sel, valor) {
		var h = '<option value="0">Sin asignar</option>';
		usuarios.filter(function (u) { return u.rol === 'supervisor' && u.activo; }).forEach(function (u) { h += '<option value="' + u.id + '">' + esc(u.nombre) + '</option>'; });
		sel.innerHTML = h;
		sel.value = String(valor || 0);
	}
	function pintarRuta() {
		var conRuta = usuarios.length && usuarios[0].con_ruta;
		var esProm = $('epUsRolCampo').value === 'promotor';
		var esSup = $('epUsRolCampo').value === 'supervisor';
		$('epUsWRuta').style.display = conRuta && (esProm || esSup) ? '' : 'none';
		var cat = $('epUsCategorias').value;
		// Un promotor solo de retail no necesita supervisor de canales, y al revés; con "todas" o por su ruta hacen falta los dos.
		$('epUsWSupCanales').style.display = esProm && cat !== 'retail' ? '' : 'none';
		$('epUsWSupRetail').style.display = esProm && cat !== 'canales' ? '' : 'none';
	}

	// ---- Panel ----
	function pintarEstado() {
		var on = actual.activo;
		$('epUsEstadoTitulo').textContent = on ? 'Desactivar usuario' : 'Usuario desactivado';
		$('epUsEstadoTexto').textContent = on ? 'No podrá iniciar sesión. Sus registros se conservan.' : 'No puede iniciar sesión. Al reactivarlo vuelve a entrar con su clave actual.';
		var b = $('epUsEstadoBtn');
		b.textContent = on ? 'Desactivar' : 'Reactivar';
		b.className = 'ep-us-btn ' + (on ? 'ep-us-btn-d' : 'ep-us-btn-p');
		b.disabled = actual.propio;
		b.title = actual.propio ? 'No puedes desactivar tu propia cuenta' : '';
	}
	// Foto que se ve en el panel: la elegida (aún sin guardar) o la ya guardada.
	function fotoVisible() { return fotoPendiente ? fotoPendiente.url : actual.foto; }
	function soltarFotoPendiente() {
		if (fotoPendiente) URL.revokeObjectURL(fotoPendiente.url);
		fotoPendiente = null;
	}
	function pintarAvatar() {
		var av = $('epUsAv');
		var foto = fotoVisible();
		av.style.cssText = foto ? 'background-image:url(\'' + esc(foto) + '\')' : estiloAv(actual);
		av.textContent = foto ? '' : (nuevo ? '+' : iniciales(actual.nombre));
		av.classList.toggle('ep-us-av-zoom', !!foto);
		av.tabIndex = foto ? 0 : -1;
		av.setAttribute('role', foto ? 'button' : 'img');
		av.setAttribute('aria-label', foto ? 'Ver foto ampliada' : 'Sin foto');
		$('epUsFotoNota').textContent = fotoPendiente ? 'Foto lista: se guarda al pulsar Guardar cambios.' : 'JPG, PNG o WEBP';
	}
	function abrirZoom() {
		var foto = fotoVisible();
		if (foto && window.epZoomFoto) window.epZoomFoto(foto, actual.nombre);
	}
	function abrir(u) {
		nuevo = !u;
		soltarFotoPendiente();
		actual = u || { id: 0, usuario: '', nombre: '', correo: '', rol: 'admin', rol_label: '', activo: true, foto: '', ciudad: '', canal: '', propio: false };
		$('epUsTitulo').textContent = nuevo ? 'Nuevo usuario' : 'Editar usuario';
		$('epUsNombre').value = actual.nombre;
		$('epUsUsuario').value = actual.usuario;
		$('epUsCorreo').value = actual.correo;
		$('epUsRolCampo').value = actual.rol;
		$('epUsRolCampo').disabled = actual.propio;
		$('epUsCategorias').value = actual.categorias || '';
		opcionesSup($('epUsSupCanales'), actual.sup_canales);
		opcionesSup($('epUsSupRetail'), actual.sup_retail);
		pintarRuta();
		$('epUsCiudad').value = actual.ciudad || '—';
		$('epUsCanal').value = actual.canal;
		$('epUsNombre').disabled = !nuevo;
		$('epUsNombreNota').style.display = nuevo ? 'none' : '';
		$('epUsWUsuario').style.display = nuevo ? '' : 'none';
		$('epUsWCiudad').style.display = nuevo ? 'none' : '';
		$('epUsWCanal').style.display = nuevo ? 'none' : '';
		$('epUsWClaveNueva').style.display = nuevo ? '' : 'none';
		$('epUsAcceso').style.display = nuevo ? 'none' : '';
		$('epUsFotoBloque').style.display = '';
		$('epUsGuardar').textContent = nuevo ? 'Crear usuario' : 'Guardar cambios';
		cerrarFormClave();
		if (!nuevo) pintarEstado();
		pintarAvatar();
		$('epUsDrawer').classList.add('on');
		$('epUsScrim').classList.add('on');
		$('epUsDrawer').setAttribute('aria-hidden', 'false');
		pintarLista();
		setTimeout(function () { $(nuevo ? 'epUsUsuario' : 'epUsCorreo').focus(); }, 280);
	}
	function cerrar() {
		soltarFotoPendiente();
		actual = null;
		$('epUsDrawer').classList.remove('on');
		$('epUsScrim').classList.remove('on');
		$('epUsDrawer').setAttribute('aria-hidden', 'true');
		pintarLista();
	}

	function guardar() {
		var btn = $('epUsGuardar');
		var datos = { id: actual.id, correo: $('epUsCorreo').value, rol: $('epUsRolCampo').value, categorias: $('epUsCategorias').value, sup_canales: $('epUsSupCanales').value, sup_retail: $('epUsSupRetail').value };
		if (nuevo) {
			datos.usuario = $('epUsUsuario').value;
			datos.nombre = $('epUsNombre').value;
			datos.clave = $('epUsC1').value;
			datos.clave2 = $('epUsC2').value;
		}
		btn.disabled = true;
		enviar('getters/usuario_guardar.php', datos).then(function (r) {
			if (!r.ok) { btn.disabled = false; toast('error', r.message || 'No se pudo guardar.'); return; }
			// Un usuario nuevo recién tiene id ahora; la foto elegida se sube después de guardarlo.
			var conFoto = !!fotoPendiente;
			return subirFotoPendiente(nuevo ? r.id : actual.id).then(function (fotoOk) {
				btn.disabled = false;
				if (!fotoOk) return;
				// Si lo único que cambió fue la foto, el aviso de datos dice "sin cambios": aquí se corrige.
				toast('success', conFoto && r.cambio === false ? 'Foto actualizada.' : r.message);
				// Se recarga para que la lista salga con los datos reales (nombre de Xplora, ciudad, canal, foto).
				setTimeout(function () { window.location.reload(); }, 700);
			});
		});
	}

	// Sube la foto elegida (si hay); resuelve true si no había o se subió bien.
	function subirFotoPendiente(id) {
		if (!fotoPendiente) return Promise.resolve(true);
		var fd = new FormData();
		fd.append('id', id);
		fd.append('archivo', fotoPendiente.archivo);
		return fetch('getters/usuario_foto.php', { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.catch(function () { return { ok: false, message: 'No se pudo conectar con el servidor.' }; })
			.then(function (r) {
				if (!r.ok) toast('error', 'Se guardaron los datos, pero la foto no: ' + (r.message || 'error al subirla.'));
				return !!r.ok;
			});
	}

	// ---- Eventos ----
	$('epUsLista').addEventListener('click', function (e) {
		var f = e.target.closest('.ep-us-fila[data-id]');
		if (!f) return;
		var id = parseInt(f.dataset.id, 10);
		abrir(usuarios.filter(function (u) { return u.id === id; })[0]);
	});
	$('epUsLista').addEventListener('keydown', function (e) {
		if (e.key !== 'Enter' && e.key !== ' ') return;
		var f = e.target.closest('.ep-us-fila[data-id]');
		if (f) { e.preventDefault(); f.click(); }
	});
	$('epUsNuevo').addEventListener('click', function () { abrir(null); });
	$('epUsCerrar').addEventListener('click', cerrar);
	$('epUsCancelar').addEventListener('click', cerrar);
	$('epUsScrim').addEventListener('click', cerrar);
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && actual) cerrar();
	});
	$('epUsGuardar').addEventListener('click', guardar);
	$('epUsRolCampo').addEventListener('change', pintarRuta);
	$('epUsCategorias').addEventListener('change', pintarRuta);

	$('epUsBuscar').addEventListener('input', function (e) { filtro.q = e.target.value.trim().toLowerCase(); pintarLista(); });
	$('epUsRol').addEventListener('change', function (e) { filtro.rol = e.target.value; pintarLista(); });
	$('epUsEstado').addEventListener('change', function (e) { filtro.estado = e.target.value; pintarLista(); });

	root.querySelectorAll('.ep-us-eye').forEach(function (b) {
		b.addEventListener('click', function () {
			var i = $(b.dataset.eye), ver = i.type === 'password';
			i.type = ver ? 'text' : 'password';
			b.setAttribute('aria-label', ver ? 'Ocultar clave' : 'Mostrar clave');
		});
	});

	$('epUsClaveAbrir').addEventListener('click', function () {
		$('epUsClaveForm').classList.add('on');
		$('epUsClaveAbrir').style.display = 'none';
		$('epUsP1').focus();
	});
	$('epUsClaveCancelar').addEventListener('click', cerrarFormClave);
	['epUsP1', 'epUsP2'].forEach(function (id) { $(id).addEventListener('input', revisarClave); });
	$('epUsClaveGenerar').addEventListener('click', function () {
		var p = generarClave();
		$('epUsP1').value = $('epUsP2').value = p;
		$('epUsP1').type = $('epUsP2').type = 'text';
		revisarClave();
	});
	$('epUsClaveGuardar').addEventListener('click', function () {
		var btn = $('epUsClaveGuardar');
		btn.disabled = true;
		enviar('getters/usuario_clave.php', { id: actual.id, clave: $('epUsP1').value, clave2: $('epUsP2').value }).then(function (r) {
			if (r.ok) { toast('success', r.message); cerrarFormClave(); } else { toast('error', r.message || 'No se pudo cambiar la clave.'); btn.disabled = false; }
		});
	});

	$('epUsEstadoBtn').addEventListener('click', function () {
		var activar = !actual.activo;
		var btn = $('epUsEstadoBtn');
		btn.disabled = true;
		enviar('getters/usuario_estado.php', { id: actual.id, activo: activar ? 1 : 0 }).then(function (r) {
			if (!r.ok) { toast('error', r.message || 'No se pudo cambiar el estado.'); btn.disabled = actual.propio; return; }
			actual.activo = activar;
			pintarEstado();
			pintarLista();
			toast('success', r.message);
		});
	});

	$('epUsFotoBtn').addEventListener('click', function () { $('epUsFotoArchivo').click(); });
	// Reduce la foto a un JPEG liviano (máx. 800 px, bajo 700 KB): el servidor rechaza peticiones de más de ~1 MB.
	function reducirFoto(archivo) {
		return new Promise(function (resolve) {
			var img = new Image();
			var url = URL.createObjectURL(archivo);
			img.onerror = function () { URL.revokeObjectURL(url); resolve(archivo); };
			img.onload = function () {
				URL.revokeObjectURL(url);
				var escala = Math.min(1, 800 / Math.max(img.width, img.height));
				var lienzo = document.createElement('canvas');
				lienzo.width = Math.round(img.width * escala);
				lienzo.height = Math.round(img.height * escala);
				lienzo.getContext('2d').drawImage(img, 0, 0, lienzo.width, lienzo.height);
				var calidades = [0.85, 0.75, 0.65, 0.55, 0.45];
				(function probar(i) {
					lienzo.toBlob(function (blob) {
						if (!blob) { resolve(archivo); return; }
						if (blob.size <= 700 * 1024 || i === calidades.length - 1) {
							resolve(new File([blob], 'foto.jpg', { type: 'image/jpeg' }));
							return;
						}
						probar(i + 1);
					}, 'image/jpeg', calidades[i]);
				})(0);
			};
			img.src = url;
		});
	}

	$('epUsFotoArchivo').addEventListener('change', function () {
		var archivo = this.files[0];
		this.value = '';
		if (!archivo) return;
		if (['image/jpeg', 'image/png', 'image/webp'].indexOf(archivo.type) === -1) { toast('error', 'Solo se permiten fotos JPG, PNG o WEBP.'); return; }
		if (archivo.size > 25 * 1024 * 1024) { toast('error', 'La foto es demasiado grande.'); return; }
		// Solo se muestra; se sube al pulsar Guardar cambios, ya reducida.
		reducirFoto(archivo).then(function (liviana) {
			soltarFotoPendiente();
			fotoPendiente = { archivo: liviana, url: URL.createObjectURL(liviana) };
			pintarAvatar();
		});
	});

	$('epUsAv').addEventListener('click', abrirZoom);
	$('epUsAv').addEventListener('keydown', function (e) {
		if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); abrirZoom(); }
	});

	pintarLista();

	// Ciudad y canal vienen del rutero (consulta lenta): si la caché del servidor venció, se piden aparte y se completa la lista.
	if (root.dataset.ruteroPendiente) {
		fetch('getters/usuarios_rutero.php', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (r) {
			if (!r || !r.ok) return;
			usuarios.forEach(function (u) {
				var d = r.rutero[u.usuario];
				if (u.canal === 'Cargando…') u.canal = d ? d.canal : 'Sin rutero';
				if (d && !u.ciudad) u.ciudad = d.ciudad;
			});
			pintarLista();
		}).catch(function () {
			usuarios.forEach(function (u) { if (u.canal === 'Cargando…') u.canal = 'Sin rutero'; });
		});
	}
})();

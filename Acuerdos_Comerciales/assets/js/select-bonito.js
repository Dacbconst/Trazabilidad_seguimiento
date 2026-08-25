// "Select bonito" (2026-08-25) — reemplaza la interacción de un <select>
// nativo por un trigger + panel propio, mismo look que el combobox de
// Registrar (.ac-combo-panel/.ac-combo-option, ver style.css). El motivo:
// el dropdown ABIERTO de un <select> es UI del sistema operativo en mobile
// (Android/iOS) — no hay forma de restylearlo con CSS, y con pocas
// opciones cortas terminaba viéndose enorme/desproporcionado (reportado
// con captura real). El <select> original queda oculto pero SIGUE siendo
// la única fuente de verdad: su .value cambia y dispara un 'change' real,
// así que cualquier código existente que ya escuche 'change' sobre el
// select (historial.js, liquidacion.js, registrar.js, etc.) sigue
// funcionando sin que haga falta tocarlo. Reusable en cualquier módulo:
// agregar la clase "ac-select-bonito-auto" al <select> alcanza.
(function () {
	function mejorarSelect(select) {
		if (select.dataset.bonito) return;
		select.dataset.bonito = '1';

		// El wrapper hereda las MISMAS clases que ya tenía el <select> (ej.
		// ".ac-hist-periodo", que en el CSS de la grilla de tarjetas mobile
		// usa "grid-area: periodo") — así cualquier layout que ya apuntaba
		// a esa clase (grid-area, flex-basis, width, lo que sea) sigue
		// aplicando sobre el elemento que ahora SÍ es el item real del
		// contenedor (el wrapper), no sobre el <select> que quedó oculto
		// adentro.
		var wrap = document.createElement('div');
		wrap.className = select.className + ' ac-select-bonito';
		select.parentNode.insertBefore(wrap, select);
		wrap.appendChild(select);

		var trigger = document.createElement('button');
		trigger.type = 'button';
		trigger.className = select.className + ' ac-select-bonito-trigger';
		trigger.setAttribute('aria-haspopup', 'listbox');
		var label = document.createElement('span');
		label.className = 'ac-select-bonito-label';
		var chevron = document.createElement('span');
		chevron.className = 'material-symbols-outlined ac-select-bonito-chevron';
		chevron.textContent = 'expand_more';
		trigger.appendChild(label);
		trigger.appendChild(chevron);
		wrap.appendChild(trigger);

		var panel = document.createElement('div');
		panel.className = 'ac-combo-panel hidden';
		panel.setAttribute('role', 'listbox');
		document.body.appendChild(panel);

		function sincronizarLabel() {
			var opt = select.options[select.selectedIndex];
			label.textContent = opt ? opt.textContent : '';
		}

		function renderPanel() {
			panel.innerHTML = '';
			Array.prototype.forEach.call(select.options, function (opt, i) {
				var item = document.createElement('div');
				item.className = 'ac-combo-option' + (i === select.selectedIndex ? ' ac-combo-option-activa' : '');
				item.setAttribute('role', 'option');
				item.textContent = opt.textContent;
				item.addEventListener('click', function () {
					if (select.selectedIndex !== i) {
						select.selectedIndex = i;
						select.dispatchEvent(new Event('change', { bubbles: true }));
					}
					sincronizarLabel();
					cerrarPanel();
				});
				panel.appendChild(item);
			});
		}

		// Mismo clamp de viewport ya usado en el combobox de Registrar
		// (posicionarPanelCombo, registrar.js) — sin esto el panel se sale
		// del borde derecho en pantallas angostas.
		function posicionarPanel() {
			var r = trigger.getBoundingClientRect();
			var ancho = r.width;
			var margen = 8;
			var left = Math.min(r.left, window.innerWidth - ancho - margen);
			left = Math.max(left, margen);
			panel.style.position = 'fixed';
			panel.style.left = left + 'px';
			panel.style.top = (r.bottom + 4) + 'px';
			panel.style.width = ancho + 'px';
		}

		function abrirPanel() {
			renderPanel();
			posicionarPanel();
			panel.classList.remove('hidden');
			trigger.classList.add('ac-select-bonito-trigger-abierto');
		}
		function cerrarPanel() {
			panel.classList.add('hidden');
			trigger.classList.remove('ac-select-bonito-trigger-abierto');
		}

		trigger.addEventListener('click', function (e) {
			e.stopPropagation();
			if (panel.classList.contains('hidden')) abrirPanel(); else cerrarPanel();
		});
		document.addEventListener('click', function (e) {
			if (!panel.contains(e.target) && e.target !== trigger) cerrarPanel();
		});
		window.addEventListener('resize', function () {
			if (!panel.classList.contains('hidden')) posicionarPanel();
		});
		// Si algo del propio módulo cambia el <select> por código (ej.
		// resumenFiltroTrimestre.value = '2'), el label tiene que reflejarlo
		// igual, no solo cuando el usuario clickea el panel. 'change' cubre
		// los casos normales, pero HAY módulos (ej. Liquidación,
		// popularFiltroCedi() en liquidacion.js) que reasignan
		// "select.value = ..." por código DIRECTO — eso nunca dispara
		// 'change' (ni debería, sería un evento falso). Para cubrir ese
		// caso sin tener que tocar cada módulo que use esto, se intercepta
		// el setter de .value de ESTE select puntual (no el prototype
		// global, no afecta a ningún otro <select> de la página) — así
		// cualquier "select.value = x" futuro, sea del módulo que sea,
		// re-sincroniza el label solo.
		var valueDescriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
		Object.defineProperty(select, 'value', {
			get: function () { return valueDescriptor.get.call(select); },
			set: function (v) { valueDescriptor.set.call(select, v); sincronizarLabel(); },
			configurable: true,
		});
		select.addEventListener('change', sincronizarLabel);
		sincronizarLabel();
	}

	window.acMejorarSelect = mejorarSelect;

	function mejorarTodos() {
		document.querySelectorAll('select.ac-select-bonito-auto').forEach(mejorarSelect);
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', mejorarTodos);
	} else {
		mejorarTodos();
	}
	// Los paneles de Liquidación/Historial se agregan al DOM recién al
	// entrar a esa sección por primera vez en algunos flujos — exponerlo
	// para que cada módulo pueda volver a llamarlo si hace falta.
	window.acMejorarSelectsNuevos = mejorarTodos;
})();

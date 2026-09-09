// Reemplaza un <select> nativo por un trigger + panel propio: el dropdown abierto es UI del SO en mobile, sin forma de restylearlo con CSS. El <select> original queda oculto pero sigue siendo la fuente de verdad (su .value dispara 'change' real). Agregar "ac-select-bonito-auto" alcanza.
(function () {
	function mejorarSelect(select) {
		if (select.dataset.bonito) return;
		select.dataset.bonito = '1';

		// El wrapper hereda las mismas clases que ya tenía el <select>, así cualquier layout que apuntaba a esa clase sigue aplicando sobre el wrapper.
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

		// Mismo clamp de viewport que el combobox de Registrar (posicionarPanelCombo): sin esto el panel se sale del borde derecho en pantallas angostas.
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
		// Algunos módulos reasignan "select.value = ..." por código directo, lo que nunca dispara 'change'. Se intercepta el setter de .value de ESTE select puntual (no el prototype global) para que cualquier asignación futura re-sincronice el label solo.
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
	// Los paneles de Liquidación/Historial se agregan al DOM recién al entrar a esa sección; se expone para que cada módulo lo re-llame si hace falta.
	window.acMejorarSelectsNuevos = mejorarTodos;
})();

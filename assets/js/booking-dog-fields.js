(function () {
	'use strict';
	// The Basic booking form translates every label and renders custom fields as text.
	if (window.ssa_translations && window.ssa_translations.appointmentTypes) {
		var labels = window.ssa_translations.appointmentTypes.customer_information;
		if (labels) {
			labels['Name des Vierbeiners'] = 'Name des Vierbeiners';
			labels['Geschlecht des Hundes'] = 'Geschlecht des Hundes';
		}
	}
	function enhance() {
		document.querySelectorAll('input[name="name-des-vierbeiners"]').forEach(function (input) {
			var container = input.closest('.form-field');
			if (!container || container.querySelector('.anubis-dog-name-label')) { return; }
			input.setAttribute('aria-label', 'Name des Vierbeiners');
			var label = document.createElement('label');
			label.className = 'anubis-dog-name-label';
			label.style.cssText = 'display:block;margin:0 0 8px';
			if (!input.id) { input.id = 'anubis-dog-name'; }
			label.htmlFor = input.id;
			container.prepend(label);
		});
		var containers = Array.from(document.querySelectorAll('[class*="field-geschlecht-des-hundes-"]'));
		document.querySelectorAll('input[name="geschlecht-des-hundes"]').forEach(function (input) {
			var container = input.closest('.form-field');
			if (container && !containers.includes(container)) { containers.push(container); }
		});
		containers.forEach(function (container) {
			var input = container.querySelector('input[type="text"], input:not([type])');
			if (!input || container.querySelector('.anubis-dog-sex')) { return; }
			var fieldset = document.createElement('fieldset');
			fieldset.className = 'anubis-dog-sex';
			var legend = document.createElement('legend');
			legend.textContent = 'Geschlecht des Hundes';
			fieldset.appendChild(legend);
			var options = document.createElement('div');
			options.className = 'anubis-dog-sex-options';
			fieldset.appendChild(options);
			['Männlich', 'Weiblich'].forEach(function (value, index) {
				var label = document.createElement('label');
				label.style.cssText = 'display:inline-flex;align-items:center;gap:8px;margin:8px 24px 0 0;cursor:pointer';
				var radio = document.createElement('input');
				radio.type = 'radio';
				radio.name = 'anubis-dog-sex-' + input.id;
				radio.value = value;
				radio.checked = input.value === value;
				radio.addEventListener('change', function () {
					input.value = value;
					input.dispatchEvent(new Event('input', { bubbles: true }));
					input.dispatchEvent(new Event('change', { bubbles: true }));
				});
				label.appendChild(radio);
				label.appendChild(document.createTextNode(value));
				options.appendChild(label);
			});
			// Retain the original input so Vue saves the value with the appointment.
			Array.from(container.children).forEach(function (child) { child.hidden = true; });
			container.appendChild(fieldset);
		});
	}
	var observer = new MutationObserver(enhance);
	observer.observe(document.body, { childList: true, subtree: true });
	enhance();
}());

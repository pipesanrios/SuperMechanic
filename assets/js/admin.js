/* super-mechanic/assets/js/admin.js */

document.addEventListener('click', async function (event) {
	const button = event.target.closest('.sm-copy-shortcode');

	if (!button) {
		return;
	}

	const targetId = button.getAttribute('data-copy-target');
	const feedbackId = button.getAttribute('data-feedback-target');
	const successLabel = button.getAttribute('data-success-label') || 'Copied';
	const defaultLabel = button.getAttribute('data-default-label') || button.textContent;
	const input = targetId ? document.getElementById(targetId) : null;
	const feedback = feedbackId ? document.getElementById(feedbackId) : null;

	if (!input) {
		return;
	}

	event.preventDefault();

	const value = input.value || input.textContent || '';

	try {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			await navigator.clipboard.writeText(value);
		} else {
			const helper = document.createElement('textarea');
			helper.value = value;
			helper.setAttribute('readonly', 'readonly');
			helper.style.position = 'absolute';
			helper.style.left = '-9999px';
			document.body.appendChild(helper);
			helper.select();
			helper.setSelectionRange(0, helper.value.length);

			if (!document.execCommand('copy')) {
				document.body.removeChild(helper);
				throw new Error('copy_failed');
			}

			document.body.removeChild(helper);
		}

		button.textContent = successLabel;
		button.classList.add('is-copied');

		if (feedback) {
			feedback.textContent = successLabel;
		}

		window.setTimeout(function () {
			button.textContent = defaultLabel;
			button.classList.remove('is-copied');

			if (feedback) {
				feedback.textContent = '';
			}
		}, 1800);
	} catch (error) {
		if (feedback) {
			feedback.textContent = 'No se pudo copiar.';
		}
	}
});

document.addEventListener('DOMContentLoaded', function () {
	const forms = document.querySelectorAll('.sm-process-form[data-sm-process-relations]');

	forms.forEach(function (form) {
		const vehicleSelect = form.querySelector('#vehicle_id');
		const clientSelect = form.querySelector('#client_id');
		const hint = form.querySelector('#sm-process-relation-hint');
		const quickAddClientLink = form.querySelector('#sm-quick-add-client');
		const quickAddVehicleLink = form.querySelector('#sm-quick-add-vehicle');

		if (!vehicleSelect || !clientSelect) {
			return;
		}

		let relations = {};

		try {
			relations = JSON.parse(form.getAttribute('data-sm-process-relations') || '{}');
		} catch (error) {
			relations = {};
		}

		const vehicleToClient = relations.vehicle_to_client || {};
		const clientToVehicles = relations.client_to_vehicles || {};
		const vehicleLabels = relations.vehicle_labels || {};
		const clientLabels = relations.client_labels || {};
		const originalVehicleOptions = Array.from(vehicleSelect.options).map(function (option) {
			return {
				value: option.value,
				text: option.textContent,
				selected: option.selected
			};
		});

		function updateHint() {
			if (!hint) {
				return;
			}

			const vehicleId = vehicleSelect.value || '0';
			const clientId = clientSelect.value || '0';
			const messages = [];

			if (vehicleId !== '0' && vehicleLabels[vehicleId]) {
				messages.push('Vehiculo seleccionado: ' + vehicleLabels[vehicleId] + '.');
			}

			if (clientId !== '0' && clientLabels[clientId]) {
				const count = Array.isArray(clientToVehicles[clientId]) ? clientToVehicles[clientId].length : 0;
				messages.push('Cliente vinculado: ' + clientLabels[clientId] + ' (' + count + ' vehiculos asociados).');
			}

			if (!messages.length) {
				messages.push('Selecciona cliente o vehiculo para sincronizar la relacion y limitar las opciones disponibles sin salir del flujo.');
			}

			hint.textContent = messages.join(' ');
		}

		function updateQuickAddLinks() {
			if (quickAddClientLink) {
				try {
					const nextClientUrl = new URL(quickAddClientLink.href);
					nextClientUrl.searchParams.set('vehicle_id', vehicleSelect.value || '0');
					quickAddClientLink.href = nextClientUrl.toString();
				} catch (error) {}
			}

			if (quickAddVehicleLink) {
				try {
					const nextVehicleUrl = new URL(quickAddVehicleLink.href);
					nextVehicleUrl.searchParams.set('client_id', clientSelect.value || '0');
					quickAddVehicleLink.href = nextVehicleUrl.toString();
				} catch (error) {}
			}
		}

		function rebuildVehicleOptions(clientId) {
			const allowedVehicles = clientId !== '0' && Array.isArray(clientToVehicles[clientId])
				? clientToVehicles[clientId].map(String)
				: null;
			const previousValue = vehicleSelect.value;

			vehicleSelect.innerHTML = '';

			originalVehicleOptions.forEach(function (option) {
				if (option.value === '0') {
					const placeholder = document.createElement('option');
					placeholder.value = option.value;
					placeholder.textContent = option.text;
					vehicleSelect.appendChild(placeholder);
					return;
				}

				if (allowedVehicles && allowedVehicles.indexOf(String(option.value)) === -1) {
					return;
				}

				const nextOption = document.createElement('option');
				nextOption.value = option.value;
				nextOption.textContent = option.text;
				vehicleSelect.appendChild(nextOption);
			});

			if (allowedVehicles && allowedVehicles.indexOf(String(previousValue)) === -1) {
				if (allowedVehicles.length === 1) {
					vehicleSelect.value = allowedVehicles[0];
				} else {
					vehicleSelect.value = '0';
				}
			} else {
				vehicleSelect.value = previousValue;
			}
		}

		clientSelect.addEventListener('change', function () {
			rebuildVehicleOptions(clientSelect.value || '0');
			updateHint();
			updateQuickAddLinks();
		});

		vehicleSelect.addEventListener('change', function () {
			const vehicleId = vehicleSelect.value || '0';
			const mappedClientId = vehicleToClient[vehicleId] ? String(vehicleToClient[vehicleId]) : '0';

			if (mappedClientId !== '0') {
				clientSelect.value = mappedClientId;
				rebuildVehicleOptions(mappedClientId);
				vehicleSelect.value = vehicleId;
			}

			updateHint();
			updateQuickAddLinks();
		});

		if ((clientSelect.value || '0') !== '0') {
			rebuildVehicleOptions(clientSelect.value || '0');
		}

		if ((vehicleSelect.value || '0') !== '0' && vehicleToClient[vehicleSelect.value]) {
			clientSelect.value = String(vehicleToClient[vehicleSelect.value]);
		}

		updateHint();
		updateQuickAddLinks();
	});
});

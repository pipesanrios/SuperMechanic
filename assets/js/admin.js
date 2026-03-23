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

	const value = input.value || input.textContent || '';

	try {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			await navigator.clipboard.writeText(value);
		} else {
			input.removeAttribute('readonly');
			input.select();
			document.execCommand('copy');
			input.setAttribute('readonly', 'readonly');
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

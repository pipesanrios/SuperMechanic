(function () {
	'use strict';

	document.addEventListener('click', function (event) {
		var link = event.target.closest('a[href*="process_id="]');
		if (!link) {
			return;
		}

		if (window.location.hash !== '#sm-portal-documents') {
			// Keep behavior lightweight: after selecting process, focus documents section.
			setTimeout(function () {
				var section = document.getElementById('sm-portal-documents');
				if (section) {
					section.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}
			}, 150);
		}
	});
}());

(function () {
	'use strict';

	function bootLazySections() {
		if (typeof window.smDashboardLazy !== 'object' || !window.smDashboardLazy) {
			return;
		}

		var placeholder = document.getElementById('sm-dashboard-lazy-placeholder');
		var content = document.getElementById('sm-dashboard-lazy-content');
		var shell = document.getElementById('sm-dashboard-lazy-shell');
		if (!placeholder || !content) {
			return;
		}

		var params = new URLSearchParams();
		params.append('action', String(window.smDashboardLazy.action || 'sm_dashboard_lazy_sections'));
		params.append('nonce', String(window.smDashboardLazy.nonce || ''));
		params.append('workload_user_id', String(window.smDashboardLazy.workloadUserId || 0));
		params.append('sm_profile', String(window.smDashboardLazy.profile || 0));

		fetch(String(window.smDashboardLazy.ajaxUrl || ''), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			credentials: 'same-origin',
			body: params.toString()
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('http_' + response.status);
				}
				return response.json();
			})
			.then(function (payload) {
				if (!payload || payload.success !== true || !payload.data || typeof payload.data.html !== 'string') {
					throw new Error('invalid_payload');
				}
				content.innerHTML = payload.data.html;
				placeholder.setAttribute('hidden', 'hidden');
				if (shell) {
					shell.classList.add('is-loaded');
					shell.classList.remove('is-error');
				}
				appendLazyProfile(payload.data && payload.data.profile ? payload.data.profile : null);
			})
			.catch(function () {
				placeholder.textContent = String(window.smDashboardLazy.errorText || 'Could not load this section right now.');
				placeholder.classList.add('is-error');
				if (shell) {
					shell.classList.add('is-error');
				}
			});
	}

	function appendLazyProfile(profilePayload) {
		if (!profilePayload || profilePayload.enabled !== true || !Array.isArray(profilePayload.marks)) {
			return;
		}

		var list = document.getElementById('sm-profile-marks');
		if (!list) {
			return;
		}

		profilePayload.marks.forEach(function (mark) {
			if (!mark || typeof mark.label !== 'string') {
				return;
			}
			var li = document.createElement('li');
			var left = document.createElement('span');
			left.textContent = '[lazy] ' + mark.label;
			var right = document.createElement('strong');
			var ms = typeof mark.elapsed_ms === 'number' ? mark.elapsed_ms : 0;
			right.textContent = ms.toFixed(2) + 'ms';
			li.appendChild(left);
			li.appendChild(right);
			list.appendChild(li);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bootLazySections);
	} else {
		bootLazySections();
	}
})();

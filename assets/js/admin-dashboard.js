(function () {
	'use strict';

	var dashboardConfig = typeof window.smDashboardLazy === 'object' && window.smDashboardLazy ? window.smDashboardLazy : {};
	var preferenceState = {
		collapsedBlocks: new Set(),
		hiddenSecondaryBlocks: new Set(),
		compactMode: false
	};
	var preferenceSaveTimer = null;
	var secondaryBlockIds = [];

	function bootLazySections() {
		if (!dashboardConfig || typeof dashboardConfig !== 'object') {
			return;
		}

		var placeholder = document.getElementById('sm-dashboard-lazy-placeholder');
		var content = document.getElementById('sm-dashboard-lazy-content');
		var shell = document.getElementById('sm-dashboard-lazy-shell');
		if (!placeholder || !content) {
			return;
		}
		if (shell && (shell.hidden || shell.closest('[hidden]'))) {
			return;
		}

		var params = new URLSearchParams();
		params.append('action', String(dashboardConfig.action || 'sm_dashboard_lazy_sections'));
		params.append('nonce', String(dashboardConfig.nonce || ''));
		params.append('workload_user_id', String(dashboardConfig.workloadUserId || 0));
		params.append('sm_profile', String(dashboardConfig.profile || 0));

		fetch(String(dashboardConfig.ajaxUrl || ''), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			credentials: 'same-origin',
			body: params.toString()
		})
			.then(function (response) {
				return response
					.json()
					.catch(function () {
						return null;
					})
					.then(function (payload) {
						if (!response.ok) {
							var message = payload && payload.data && typeof payload.data.message === 'string'
								? payload.data.message
								: ('http_' + response.status);
							throw new Error(message);
						}
						return payload;
					});
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
				applyPreferenceStateToDOM();
			})
			.catch(function (error) {
				renderLazyErrorState(placeholder, error && error.message ? String(error.message) : '');
				if (shell) {
					shell.classList.add('is-error');
				}
			});
	}

	function renderLazyErrorState(placeholder, detailMessage) {
		if (!placeholder) {
			return;
		}
		var fallbackText = String(dashboardConfig.errorText || 'Could not load this section right now.');
		var detail = detailMessage && detailMessage.indexOf('http_') !== 0 ? detailMessage : '';
		var detailHtml = detail ? ('<span class="sm-lazy-error-detail">' + escapeHtml(detail) + '</span>') : '';
		placeholder.innerHTML =
			'<strong>' + escapeHtml(fallbackText) + '</strong>' +
			detailHtml +
			'<button type="button" class="button button-secondary sm-lazy-retry">Retry loading section</button>';
		placeholder.classList.add('is-error');
		var retryButton = placeholder.querySelector('.sm-lazy-retry');
		if (retryButton) {
			retryButton.addEventListener('click', function () {
				placeholder.classList.remove('is-error');
				placeholder.textContent = String(dashboardConfig.loadingText || 'Loading section...');
				bootLazySections();
			});
		}
	}

	function escapeHtml(value) {
		return String(value || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
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

	function readArray(value) {
		if (!Array.isArray(value)) {
			return [];
		}
		return value.map(function (entry) {
			return String(entry || '').trim();
		}).filter(Boolean);
	}

	function initPreferenceState() {
		var prefs = dashboardConfig.preferences && typeof dashboardConfig.preferences === 'object'
			? dashboardConfig.preferences
			: {};
		var secondaryMap = dashboardConfig.secondaryBlocks && typeof dashboardConfig.secondaryBlocks === 'object'
			? dashboardConfig.secondaryBlocks
			: {};

		secondaryBlockIds = Object.keys(secondaryMap);
		preferenceState.collapsedBlocks = new Set(readArray(prefs.collapsed_blocks));
		preferenceState.hiddenSecondaryBlocks = new Set(readArray(prefs.hidden_secondary_blocks));
		preferenceState.compactMode = prefs.compact_mode === 1 || prefs.compact_mode === true || String(prefs.compact_mode || '') === '1';

		secondaryBlockIds.forEach(function (blockId) {
			if (preferenceState.hiddenSecondaryBlocks.has(blockId)) {
				preferenceState.collapsedBlocks.delete(blockId);
			}
		});
	}

	function isAllowedSecondaryBlock(blockId) {
		if (!blockId) {
			return false;
		}
		return secondaryBlockIds.indexOf(String(blockId)) >= 0;
	}

	function applyPreferenceStateToDOM() {
		var shell = document.querySelector('.sm-admin-shell');
		if (shell) {
			shell.classList.toggle('is-compact-mode', !!preferenceState.compactMode);
		}

		secondaryBlockIds.forEach(function (blockId) {
			document.querySelectorAll('.sm-pref-block[data-sm-pref-block-id="' + blockId + '"]').forEach(function (block) {
				var isHidden = preferenceState.hiddenSecondaryBlocks.has(blockId);
				var isCollapsed = !isHidden && preferenceState.collapsedBlocks.has(blockId);
				block.hidden = isHidden;
				block.classList.toggle('is-collapsed', isCollapsed);
				block.setAttribute('data-sm-pref-collapsed', isCollapsed ? '1' : '0');
				var collapseButton = block.querySelector('.sm-pref-toggle-collapse');
				if (collapseButton) {
					collapseButton.textContent = isCollapsed
						? String(dashboardConfig.expandLabel || 'Expand block')
						: String(dashboardConfig.collapseLabel || 'Collapse block');
				}
			});
		});

		document.querySelectorAll('[data-sm-pref-visibility="1"]').forEach(function (input) {
			var blockId = String(input.getAttribute('data-sm-pref-block-id') || '');
			if (!isAllowedSecondaryBlock(blockId)) {
				return;
			}
			input.checked = !preferenceState.hiddenSecondaryBlocks.has(blockId);
		});

		var compactToggle = document.getElementById('sm-pref-compact-mode');
		if (compactToggle) {
			compactToggle.checked = !!preferenceState.compactMode;
		}
	}

	function queuePreferenceSave() {
		if (preferenceSaveTimer) {
			window.clearTimeout(preferenceSaveTimer);
		}
		preferenceSaveTimer = window.setTimeout(savePreferences, 180);
	}

	function savePreferences() {
		preferenceSaveTimer = null;
		if (!dashboardConfig.ajaxUrl || !dashboardConfig.preferencesAction || !dashboardConfig.preferencesNonce) {
			return;
		}

		var params = new URLSearchParams();
		params.append('action', String(dashboardConfig.preferencesAction));
		params.append('nonce', String(dashboardConfig.preferencesNonce));
		params.append('collapsed_blocks', JSON.stringify(Array.from(preferenceState.collapsedBlocks)));
		params.append('hidden_secondary_blocks', JSON.stringify(Array.from(preferenceState.hiddenSecondaryBlocks)));
		params.append('compact_mode', preferenceState.compactMode ? '1' : '0');

		fetch(String(dashboardConfig.ajaxUrl), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			credentials: 'same-origin',
			body: params.toString()
		})
			.then(function (response) {
				return response
					.json()
					.catch(function () {
						return null;
					})
					.then(function (payload) {
						if (!response.ok || !payload || payload.success !== true) {
							throw new Error('save_failed');
						}
						return payload;
					});
			})
			.then(function (payload) {
				if (payload && payload.data && payload.data.preferences) {
					var prefs = payload.data.preferences;
					preferenceState.collapsedBlocks = new Set(readArray(prefs.collapsed_blocks));
					preferenceState.hiddenSecondaryBlocks = new Set(readArray(prefs.hidden_secondary_blocks));
					preferenceState.compactMode = prefs.compact_mode === 1 || String(prefs.compact_mode || '') === '1';
				}
				applyPreferenceStateToDOM();
			})
			.catch(function () {
				// Keep UX stable; local state remains active for current view.
			});
	}

	function bootPreferenceInteractions() {
		initPreferenceState();
		applyPreferenceStateToDOM();

		document.addEventListener('click', function (event) {
			var collapseButton = event.target.closest('.sm-pref-toggle-collapse');
			if (collapseButton) {
				var collapseBlockId = String(collapseButton.getAttribute('data-sm-pref-block-id') || '');
				if (!isAllowedSecondaryBlock(collapseBlockId) || preferenceState.hiddenSecondaryBlocks.has(collapseBlockId)) {
					return;
				}
				if (preferenceState.collapsedBlocks.has(collapseBlockId)) {
					preferenceState.collapsedBlocks.delete(collapseBlockId);
				} else {
					preferenceState.collapsedBlocks.add(collapseBlockId);
				}
				applyPreferenceStateToDOM();
				queuePreferenceSave();
				return;
			}

			var hideButton = event.target.closest('.sm-pref-toggle-visibility');
			if (hideButton) {
				var hideBlockId = String(hideButton.getAttribute('data-sm-pref-block-id') || '');
				if (!isAllowedSecondaryBlock(hideBlockId)) {
					return;
				}
				preferenceState.hiddenSecondaryBlocks.add(hideBlockId);
				preferenceState.collapsedBlocks.delete(hideBlockId);
				applyPreferenceStateToDOM();
				queuePreferenceSave();
				return;
			}

			var resetButton = event.target.closest('.sm-pref-reset-layout');
			if (resetButton) {
				preferenceState.collapsedBlocks = new Set();
				preferenceState.hiddenSecondaryBlocks = new Set();
				preferenceState.compactMode = false;
				applyPreferenceStateToDOM();
				queuePreferenceSave();
			}
		});

		document.addEventListener('change', function (event) {
			var visibilityToggle = event.target.closest('[data-sm-pref-visibility="1"]');
			if (visibilityToggle) {
				var visibilityBlockId = String(visibilityToggle.getAttribute('data-sm-pref-block-id') || '');
				if (!isAllowedSecondaryBlock(visibilityBlockId)) {
					return;
				}
				if (visibilityToggle.checked) {
					preferenceState.hiddenSecondaryBlocks.delete(visibilityBlockId);
				} else {
					preferenceState.hiddenSecondaryBlocks.add(visibilityBlockId);
					preferenceState.collapsedBlocks.delete(visibilityBlockId);
				}
				applyPreferenceStateToDOM();
				queuePreferenceSave();
				return;
			}

			if (event.target && event.target.id === 'sm-pref-compact-mode') {
				preferenceState.compactMode = !!event.target.checked;
				applyPreferenceStateToDOM();
				queuePreferenceSave();
			}
		});
	}

	function bootDashboard() {
		bootPreferenceInteractions();
		bootLazySections();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bootDashboard);
	} else {
		bootDashboard();
	}
})();

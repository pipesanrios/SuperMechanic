(function ($) {
	'use strict';

	var primaryVisibilityKey = 'sm_roles_access_visible_columns';
	var legacyVisibilityKeys = ['smRolesAccessVisibleColumns', 'smRolesVisibleColumns'];
	var defaultVisibilityState = {
		id: false,
		name: true,
		email: false,
		wp_roles: false,
		operational_role: true,
		business: true,
		memberships: true,
		dashboard_access: false,
		automation_access: false,
		status: false,
		actions: true
	};

	function getSuccessMessage(action, fallback) {
		if ('create_membership' === action) {
			return 'Membership added successfully.';
		}
		if ('transfer' === action || 'transfer_user_to_business' === action) {
			return 'User transferred successfully.';
		}
		return fallback || 'OK';
	}

	function getErrorMessage(action, fallback) {
		if ('transfer' === action || 'transfer_user_to_business' === action) {
			return fallback || 'Could not transfer user.';
		}
		return fallback || smRolesAccess.messages.unexpected;
	}

	function sendMembershipAction(payload, $feedback) {
		$feedback.text('Working...');

		$.post(
			smRolesAccess.ajax_url,
			$.extend(
				{
					action: 'sm_roles_membership_action',
					nonce: smRolesAccess.nonce
				},
				payload
			)
		)
			.done(function (response) {
				if (response && response.success) {
					$feedback.text(
						getSuccessMessage(
							String(payload.membership_action || ''),
							response.data && response.data.message ? response.data.message : 'OK'
						)
					);
					window.location.reload();
					return;
				}

				$feedback.text(getErrorMessage(
					String(payload.membership_action || ''),
					response && response.data && response.data.message ? response.data.message : ''
				));
			})
			.fail(function (xhr) {
				var message = '';
				if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					message = xhr.responseJSON.data.message;
				}
				$feedback.text(getErrorMessage(String(payload.membership_action || ''), message));
			});
	}

	function applyRolesColumnVisibility(columnKey, visible) {
		var selector = '.sm-roles-access-table [data-col="' + columnKey + '"]';
		$(selector).toggle(!!visible);
	}

	function readPersistedVisibilityState() {
		var keys = [primaryVisibilityKey].concat(legacyVisibilityKeys);
		var i;
		var raw = '';
		var sourceKey = '';

		try {
			for (i = 0; i < keys.length; i += 1) {
				raw = window.localStorage.getItem(keys[i]);
				if (raw) {
					sourceKey = keys[i];
					break;
				}
			}
		} catch (e) {
			return { hasState: false, state: {}, source: '' };
		}

		if (!raw) {
			return { hasState: false, state: {}, source: '' };
		}

		try {
			var parsed = JSON.parse(raw);
			if (Array.isArray(parsed)) {
				var fromArray = {};
				parsed.forEach(function (columnKey) {
					var key = String(columnKey || '');
					if (key) {
						fromArray[key] = true;
					}
				});
				return { hasState: true, state: fromArray, source: sourceKey };
			}

			if (parsed && typeof parsed === 'object') {
				return { hasState: true, state: parsed, source: sourceKey };
			}
		} catch (e2) {
			return { hasState: false, state: {}, source: '' };
		}

		return { hasState: false, state: {}, source: '' };
	}

	function persistVisibilityState(state) {
		try {
			window.localStorage.setItem(primaryVisibilityKey, JSON.stringify(state || {}));
		} catch (e) {
			return;
		}
	}

	function initRolesColumnFilters() {
		var $toggles = $('.sm-roles-column-toggle');
		if (!$toggles.length) {
			return;
		}

		var persistedPayload = readPersistedVisibilityState();
		var persistedState = persistedPayload.state || {};
		var currentState = {};
		var hadPersistedState = !!persistedPayload.hasState;
		var needsMigration = hadPersistedState && persistedPayload.source && persistedPayload.source !== primaryVisibilityKey;

		$toggles.each(function () {
			var $toggle = $(this);
			var key = String($toggle.val() || '');
			var visible = $toggle.is(':checked');

			if (hadPersistedState) {
				if (Object.prototype.hasOwnProperty.call(persistedState, key)) {
					visible = !!persistedState[key];
				} else if (Object.prototype.hasOwnProperty.call(defaultVisibilityState, key)) {
					visible = !!defaultVisibilityState[key];
				}
			} else if (Object.prototype.hasOwnProperty.call(defaultVisibilityState, key)) {
				visible = !!defaultVisibilityState[key];
			}

			$toggle.prop('checked', visible);
			currentState[key] = visible;
			applyRolesColumnVisibility(key, visible);
		});

		if (needsMigration) {
			persistVisibilityState(currentState);
		}

		$(document).on('change', '.sm-roles-column-toggle', function () {
			var $toggle = $(this);
			var key = String($toggle.val() || '');
			var visible = $toggle.is(':checked');
			applyRolesColumnVisibility(key, visible);
			currentState[key] = visible;
			persistVisibilityState(currentState);
		});
	}

	$(document).on('click', '.sm-membership-action', function () {
		var $btn = $(this);
		var action = String($btn.data('action') || '');
		var $membershipItem = $btn.closest('.sm-membership-item');
		var $addWrapper = $btn.closest('.sm-membership-add');
		var $panel = $btn.closest('.sm-membership-panel');
		var $feedback = $addWrapper.length ? $addWrapper.find('.sm-membership-feedback') : $panel.find('.sm-membership-feedback').first();
		var payload = { membership_action: action };

		if ('create_membership' === action) {
			var $businessField = $addWrapper.find('.sm-membership-business');
			if (!$businessField.length) {
				$businessField = $addWrapper.find('[name="business_id"]');
			}
			if (!$businessField.length) {
				$businessField = $addWrapper.find('[data-field="business_id"]');
			}

			var businessId = parseInt($businessField.val(), 10) || 0;
			if (businessId <= 0) {
				$feedback.text('Please select a business.');
				return;
			}

			payload.user_id = parseInt($addWrapper.data('user-id'), 10) || 0;
			payload.business_id = businessId;
			payload.role = String($addWrapper.find('.sm-membership-role').val() || '');
		} else if ('transfer' === action || 'transfer_user_to_business' === action) {
			var $transferWrapper = $btn.closest('.sm-membership-transfer');
			var $targetBusinessField = $transferWrapper.find('.sm-transfer-business');
			if (!$targetBusinessField.length) {
				$targetBusinessField = $transferWrapper.find('[name="target_business_id"]');
			}
			if (!$targetBusinessField.length) {
				$targetBusinessField = $transferWrapper.find('[data-field="target_business_id"]');
			}
			if (!$targetBusinessField.length) {
				$targetBusinessField = $transferWrapper.find('.sm-membership-business');
			}

			var targetBusinessId = parseInt($targetBusinessField.val(), 10) || 0;
			if (targetBusinessId <= 0) {
				$feedback = $transferWrapper.find('.sm-membership-feedback');
				$feedback.text('Please select a target business.');
				return;
			}

			payload.user_id = parseInt($transferWrapper.data('user-id'), 10) || 0;
			payload.target_business_id = targetBusinessId;
			payload.business_id = payload.target_business_id;
			payload.role = String($transferWrapper.find('.sm-transfer-role').val() || '');
			payload.mode = String($transferWrapper.find('.sm-transfer-mode').val() || '');
			payload.membership_action = 'transfer';
			$feedback = $transferWrapper.find('.sm-membership-feedback');
		} else {
			payload.membership_id = parseInt($membershipItem.data('membership-id'), 10) || 0;

			if ('update_membership_role' === action) {
				payload.role = String($membershipItem.find('.sm-membership-role').val() || '');
			}

			if ('set_membership_status' === action) {
				payload.status = String($btn.data('status') || '');
			}
		}

		sendMembershipAction(payload, $feedback);
	});

	$(function () {
		initRolesColumnFilters();
	});
})(jQuery);

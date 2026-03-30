/* super-mechanic/assets/js/admin-calendar.js */

document.addEventListener('DOMContentLoaded', function () {
	if (!window.smAdminCalendar) {
		return;
	}

	var calendarEl = document.getElementById('sm-appointments-calendar');
	if (!calendarEl || !window.FullCalendar || !window.FullCalendar.Calendar) {
		return;
	}

	var config = window.smAdminCalendar;
	var selectedEventId = null;
	var selectedEventType = '';
	var isBusy = false;
	var selectedTitleEl = document.getElementById('sm-calendar-selected-title');
	var feedbackEl = document.getElementById('sm-calendar-feedback');
	var statusSelectEl = document.getElementById('sm-calendar-status-select');
	var updateButtonEl = document.getElementById('sm-calendar-status-update');

	function setFeedback(message, type) {
		if (!feedbackEl) {
			return;
		}

		feedbackEl.textContent = message || '';
		feedbackEl.classList.toggle('is-error', type === 'error');
		feedbackEl.classList.toggle('is-success', type === 'success');
		feedbackEl.classList.toggle('is-loading', type === 'loading');
	}

	function setBusyState(busy) {
		isBusy = !!busy;
		calendarEl.classList.toggle('is-loading', isBusy);
		var canUpdateStatus = !!selectedEventId && selectedEventType !== 'crm_task';
		if (statusSelectEl) {
			statusSelectEl.disabled = isBusy || !canUpdateStatus;
		}
		if (updateButtonEl) {
			updateButtonEl.disabled = isBusy || !canUpdateStatus;
		}
	}

	function setSelectedEvent(event) {
		selectedEventId = event ? event.id : null;
		selectedEventType = event && event.extendedProps && event.extendedProps.event_type ? event.extendedProps.event_type : '';

		if (selectedTitleEl) {
			if (!event) {
				selectedTitleEl.textContent = 'Selecciona una cita en el calendario para actualizar su estado.';
			} else if (selectedEventType === 'crm_task') {
				selectedTitleEl.textContent = (event.title || ('CRM task #' + event.id)) + ' (solo lectura en calendario)';
			} else {
				selectedTitleEl.textContent = event.title || ('Cita #' + event.id);
			}
		}

		if (statusSelectEl && event && event.extendedProps && selectedEventType !== 'crm_task') {
			statusSelectEl.value = event.extendedProps.appointment_status || 'scheduled';
		}

		if (statusSelectEl && !event) {
			statusSelectEl.value = 'scheduled';
		}

		setBusyState(isBusy);
	}

	function resolveEventDetailsUrl(event) {
		if (!event) {
			return '';
		}

		if (event.url) {
			return event.url;
		}

		var eventType = event.extendedProps && event.extendedProps.event_type ? event.extendedProps.event_type : '';
		if (eventType === 'crm_task') {
			if (event.extendedProps && event.extendedProps.crm_pipeline_id && config.crmTaskDetailsBaseUrl) {
				return config.crmTaskDetailsBaseUrl + encodeURIComponent(String(event.extendedProps.crm_pipeline_id));
			}
			return '';
		}

		if (!event.id || !config.detailsBaseUrl) {
			return '';
		}

		return config.detailsBaseUrl + encodeURIComponent(String(event.id));
	}

	function normalizeDateTimeForPrefill(dateObj) {
		var year = dateObj.getFullYear();
		var month = String(dateObj.getMonth() + 1).padStart(2, '0');
		var day = String(dateObj.getDate()).padStart(2, '0');
		var hours = String(dateObj.getHours()).padStart(2, '0');
		var minutes = String(dateObj.getMinutes()).padStart(2, '0');

		return {
			date: year + '-' + month + '-' + day,
			datetime: year + '-' + month + '-' + day + 'T' + hours + ':' + minutes
		};
	}

	function requestCalendarUpdate(path, payload) {
		return fetch(config.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce
			},
			body: JSON.stringify(payload || {})
		}).then(function (response) {
			if (!response.ok) {
				throw new Error('calendar_update_failed');
			}
			return response.json();
		});
	}

	function applyEventPayload(event, payload) {
		if (!event || !payload) {
			return;
		}

		if (payload.start || payload.end) {
			event.setDates(payload.start || event.start, payload.end || event.end, { allDay: false });
		}
		if (typeof payload.title === 'string') {
			event.setProp('title', payload.title);
		}
		if (typeof payload.url === 'string') {
			event.setProp('url', payload.url);
		}
		if (typeof payload.backgroundColor === 'string') {
			event.setProp('backgroundColor', payload.backgroundColor);
		}
		if (typeof payload.borderColor === 'string') {
			event.setProp('borderColor', payload.borderColor);
		}
		if (typeof payload.textColor === 'string') {
			event.setProp('textColor', payload.textColor);
		}
		if (payload.extendedProps && typeof payload.extendedProps === 'object') {
			Object.keys(payload.extendedProps).forEach(function (key) {
				event.setExtendedProp(key, payload.extendedProps[key]);
			});
		}
	}

	function updateStatus(eventId, status) {
		return requestCalendarUpdate(encodeURIComponent(eventId) + '/status', { status: status });
	}

	function updateSchedule(eventId, startAtIso) {
		return requestCalendarUpdate(encodeURIComponent(eventId) + '/reschedule', { start_at: startAtIso });
	}

	var calendar = new window.FullCalendar.Calendar(calendarEl, {
		initialView: 'timeGridWeek',
		height: 'auto',
		editable: true,
		eventStartEditable: true,
		eventDurationEditable: false,
		nowIndicator: true,
		firstDay: 1,
		headerToolbar: {
			left: 'prev,next today',
			center: 'title',
			right: 'timeGridDay,timeGridWeek,dayGridMonth'
		},
		buttonText: {
			today: 'Hoy',
			month: 'Mes',
			week: 'Semana',
			day: 'Dia'
		},
		events: function (fetchInfo, successCallback, failureCallback) {
			var url = config.restUrl + 'calendar?start=' + encodeURIComponent(fetchInfo.startStr) + '&end=' + encodeURIComponent(fetchInfo.endStr);

			setFeedback('', '');
			fetch(url, {
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': config.nonce
				}
			})
				.then(function (response) {
					if (!response.ok) {
						throw new Error('calendar_fetch_failed');
					}
					return response.json();
				})
				.then(function (events) {
					successCallback(Array.isArray(events) ? events : []);
				})
				.catch(function () {
					setFeedback(config.calendarLoadError || 'No fue posible cargar el calendario.', 'error');
					failureCallback();
				});
		},
		eventClick: function (info) {
			info.jsEvent.preventDefault();
			setSelectedEvent(info.event);
			setFeedback('', '');
			var eventType = info.event && info.event.extendedProps ? info.event.extendedProps.event_type : '';
			var detailsUrl = resolveEventDetailsUrl(info.event);
			if (detailsUrl) {
				if (info.jsEvent.ctrlKey || info.jsEvent.metaKey) {
					window.open(detailsUrl, '_blank', 'noopener');
					return;
				}

				if (eventType === 'appointment' || eventType === 'crm_task' || eventType === '') {
					window.location.href = detailsUrl;
					return;
				}

				window.location.href = detailsUrl;
			}
		},
		eventDrop: function (info) {
			var eventId = info.event && info.event.id ? info.event.id : null;
			var eventType = info.event && info.event.extendedProps ? info.event.extendedProps.event_type : '';

			if (eventType === 'crm_task') {
				info.revert();
				setFeedback(config.crmTaskMoveBlockedLabel || 'CRM tasks cannot be moved from calendar in this phase.', 'error');
				return;
			}

			if (!eventId || !info.event.start) {
				info.revert();
				return;
			}

			setSelectedEvent(info.event);
			setBusyState(true);
			setFeedback('Guardando cambios...', 'loading');

			updateSchedule(eventId, info.event.start.toISOString())
				.then(function (payload) {
					applyEventPayload(info.event, payload);
					setFeedback(config.moveUpdateLabel || 'Cita reprogramada.', 'success');
				})
				.catch(function () {
					info.revert();
					setFeedback(config.moveUpdateError || 'No fue posible reprogramar la cita.', 'error');
				})
				.finally(function () {
					setBusyState(false);
				});
		},
		dateClick: function (info) {
			var normalized = normalizeDateTimeForPrefill(info.date);
			window.location.href = config.createBaseUrl + '&appointment_date=' + encodeURIComponent(normalized.date) + '&start_at=' + encodeURIComponent(normalized.datetime);
		}
	});

	calendar.render();

	if (updateButtonEl) {
		updateButtonEl.addEventListener('click', function () {
			if (!selectedEventId || !statusSelectEl || isBusy) {
				return;
			}

			var event = calendar.getEventById(String(selectedEventId));
			var currentStatus = event && event.extendedProps ? event.extendedProps.appointment_status : '';
			var nextStatus = statusSelectEl.value;

			if (currentStatus === nextStatus) {
				setFeedback(config.statusUpdateLabel || 'Estado actualizado.', 'success');
				return;
			}

			setBusyState(true);
			setFeedback('Actualizando estado...', 'loading');

			updateStatus(selectedEventId, nextStatus)
				.then(function (payload) {
					if (event) {
						applyEventPayload(event, payload);
						setSelectedEvent(event);
					}
					setFeedback(config.statusUpdateLabel || 'Estado actualizado.', 'success');
				})
				.catch(function () {
					if (typeof currentStatus === 'string' && currentStatus !== '' && statusSelectEl) {
						statusSelectEl.value = currentStatus;
					}
					setFeedback(config.statusUpdateError || 'No fue posible actualizar el estado.', 'error');
				})
				.finally(function () {
					setBusyState(false);
				});
		});
	}

	if (statusSelectEl) {
		statusSelectEl.addEventListener('change', function () {
			if (!selectedEventId || isBusy) {
				return;
			}
			if (updateButtonEl) {
				updateButtonEl.click();
			}
		});
	}
});

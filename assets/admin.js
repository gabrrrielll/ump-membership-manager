/**
 * Admin JavaScript
 */
(function ($) {
	'use strict';

	// Bug #6 Fix: Add error logging helper
	function logError(context, error, data) {
		console.error('[UMP MM Error]', context, {
			error: error,
			data: data,
			timestamp: new Date().toISOString()
		});
	}

	// Bug #9 Fix: Safe HTML creation helper
	function escapeHtml(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text.replace(/[&<>"']/g, function (m) { return map[m]; });
	}

	// Bug #4 Fix: Handle nonce expiration
	function handleAjaxError(response, context) {
		if (response.data && response.data.code === 'nonce_expired') {
			alert(umpMM.strings.sessionExpired);
			location.reload();
			return true;
		}
		if (response.data && response.data.code === 'rate_limit_exceeded') {
			alert(response.data.message);
			return true;
		}
		return false;
	}

	$(document).ready(function () {

		// Search users
		$('#ump-mm-search-btn').on('click', function () {
			var membershipId = $('#ump-mm-search-membership').val();

			if (!membershipId) {
				alert(umpMM.strings.selectMembership);
				return;
			}

			var $btn = $(this);
			// Bug #5 Fix: Use localized strings
			$btn.prop('disabled', true).text(umpMM.strings.loading);

			$.ajax({
				url: umpMM.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ump_mm_search_users',
					nonce: umpMM.nonce,
					membership_id: membershipId
				},
				success: function (response) {
					$btn.prop('disabled', false).text(umpMM.strings.searchUsers);

					if (response.success) {
						displayUsers(response.data.users, response.data.total);
						$('#ump-mm-users-results').show();
					} else {
						// Bug #4 Fix: Handle special error codes
						if (!handleAjaxError(response, 'search_users')) {
							alert(response.data.message || umpMM.strings.error);
						}
					}
				},
				error: function (xhr, status, error) {
					// Bug #6 Fix: Log error details
					logError('AJAX search_users failed', error, {
						status: status,
						xhr: xhr,
						membershipId: membershipId
					});
					$btn.prop('disabled', false).text(umpMM.strings.searchUsers);
					alert(umpMM.strings.error);
				}
			});
		});

		// Display users
		function displayUsers(users, total) {
			var $list = $('#ump-mm-users-list');
			$list.empty();

			if (!users || users.length === 0) {
				$list.html('<tr><td colspan="5">' + umpMM.strings.noUsers + '</td></tr>');
				return;
			}

			// Show pagination info if there are more users
			if (total && total > users.length) {
				var infoMsg = 'Afișate primele ' + users.length + ' din ' + total + ' utilizatori.';
				$list.before('<p class="ump-mm-pagination-info">' + infoMsg + '</p>');
			}

			$.each(users, function (index, user) {
				var $row = $('<tr></tr>');

				// Bug #9 Fix: Use safe DOM manipulation instead of string concatenation
				var $checkbox = $('<td></td>').append(
					$('<input type="checkbox" class="ump-mm-user-checkbox">').val(user.id)
				);
				var $id = $('<td></td>').text(user.id);
				var $username = $('<td></td>').text(user.username);
				var $email = $('<td></td>').text(user.email);
				var $name = $('<td></td>').text(user.name || '-');

				$row.append($checkbox).append($id).append($username).append($email).append($name);
				$list.append($row);
			});

			// Reset select all
			$('#ump-mm-select-all').prop('checked', false);
		}

		// Select all checkbox
		$('#ump-mm-select-all').on('change', function () {
			$('.ump-mm-user-checkbox').prop('checked', $(this).prop('checked'));
		});

		// Add membership in bulk
		$('#ump-mm-add-membership-btn').on('click', function () {
			var selectedUsers = [];
			$('.ump-mm-user-checkbox:checked').each(function () {
				selectedUsers.push($(this).val());
			});

			if (selectedUsers.length === 0) {
				alert(umpMM.strings.selectUsers);
				return;
			}

			var membershipId = $('#ump-mm-add-membership').val();
			if (!membershipId) {
				alert(umpMM.strings.selectMembership);
				return;
			}

			// Bug #5 Fix: Use localized string with placeholder
			var confirmMsg = umpMM.strings.confirmBulk.replace('%d', selectedUsers.length);
			if (!confirm(confirmMsg)) {
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true).text(umpMM.strings.loading);

			$.ajax({
				url: umpMM.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ump_mm_add_membership_bulk',
					nonce: umpMM.nonce,
					user_ids: selectedUsers,
					membership_id: membershipId
				},
				success: function (response) {
					$btn.prop('disabled', false).text(umpMM.strings.addToSelected);

					if (response.success) {
						var msg = 'Succes: ' + response.data.success + ' utilizatori';
						if (response.data.errors > 0) {
							msg += '\nErori: ' + response.data.errors;
						}

						// Show error messages if any
						if (response.data.messages && response.data.messages.length > 0) {
							msg += '\n\nDetalii erori:\n' + response.data.messages.join('\n');
							// Bug #6 Fix: Log to console
							logError('Bulk operation partial failures', null, response.data.messages);
						}

						alert(msg);
					} else {
						if (!handleAjaxError(response, 'add_membership_bulk')) {
							alert(response.data.message || umpMM.strings.error);
						}
					}
				},
				error: function (xhr, status, error) {
					// Bug #6 Fix: Log error details
					logError('AJAX add_membership_bulk failed', error, {
						status: status,
						xhr: xhr,
						selectedUsers: selectedUsers,
						membershipId: membershipId
					});
					$btn.prop('disabled', false).text(umpMM.strings.addToSelected);
					alert(umpMM.strings.error);
				}
			});
		});

		// Save auto rule
		$('#ump-mm-save-rule-btn').on('click', function () {
			var trigger = $('#ump-mm-rule-trigger').val();
			var target = $('#ump-mm-rule-target').val();

			if (!trigger || !target) {
				alert(umpMM.strings.selectBoth);
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true).text(umpMM.strings.loading);

			$.ajax({
				url: umpMM.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ump_mm_save_auto_rule',
					nonce: umpMM.nonce,
					trigger: trigger,
					target: target
				},
				success: function (response) {
					$btn.prop('disabled', false).text(umpMM.strings.saveRule);

					if (response.success) {
						alert(response.data.message || umpMM.strings.success);
						location.reload();
					} else {
						if (!handleAjaxError(response, 'save_auto_rule')) {
							alert(response.data.message || umpMM.strings.error);
						}
					}
				},
				error: function (xhr, status, error) {
					// Bug #6 Fix: Log error details
					logError('AJAX save_auto_rule failed', error, {
						status: status,
						xhr: xhr,
						trigger: trigger,
						target: target
					});
					$btn.prop('disabled', false).text(umpMM.strings.saveRule);
					alert(umpMM.strings.error);
				}
			});
		});

		// Bug #10 Fix: Use more specific selector to prevent memory leaks
		// Delete auto rule
		$('.ump-mm-existing-rules').on('click', '.ump-mm-delete-rule', function () {
			if (!confirm(umpMM.strings.confirmDelete)) {
				return;
			}

			var $btn = $(this);
			var ruleId = $btn.data('rule-id');

			$btn.prop('disabled', true);

			$.ajax({
				url: umpMM.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ump_mm_delete_auto_rule',
					nonce: umpMM.nonce,
					rule_id: ruleId
				},
				success: function (response) {
					if (response.success) {
						$btn.closest('tr').fadeOut(function () {
							$(this).remove();
							if ($('.ump-mm-delete-rule').length === 0) {
								location.reload();
							}
						});
					} else {
						$btn.prop('disabled', false);
						if (!handleAjaxError(response, 'delete_auto_rule')) {
							alert(response.data.message || umpMM.strings.error);
						}
					}
				},
				error: function (xhr, status, error) {
					// Bug #6 Fix: Log error details
					logError('AJAX delete_auto_rule failed', error, {
						status: status,
						xhr: xhr,
						ruleId: ruleId
					});
					$btn.prop('disabled', false);
					alert(umpMM.strings.error);
				}
			});
		});

		// Save WooCommerce status mapping
		$('#ump-mm-save-wc-mapping-btn').on('click', function () {
			var source = $('#ump-mm-wc-source-status').val();
			var target = $('#ump-mm-wc-target-status').val();

			if (!source || !target) {
				alert(umpMM.strings.selectStatuses);
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true).text(umpMM.strings.loading);

			$.ajax({
				url: umpMM.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ump_mm_save_wc_status_mapping',
					nonce: umpMM.nonce,
					source: source,
					target: target
				},
				success: function (response) {
					$btn.prop('disabled', false).text('Salvează Maparea');

					if (response.success) {
						alert(response.data.message || umpMM.strings.mappingSaved);
					} else {
						if (!handleAjaxError(response, 'save_wc_status_mapping')) {
							alert(response.data.message || umpMM.strings.error);
						}
					}
				},
				error: function (xhr, status, error) {
					logError('AJAX save_wc_status_mapping failed', error, {
						status: status,
						xhr: xhr,
						source: source,
						target: target
					});
					$btn.prop('disabled', false).text('Salvează Maparea');
					alert(umpMM.strings.error);
				}
			});
		});

	});

})(jQuery);


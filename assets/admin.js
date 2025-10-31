/**
 * Admin JavaScript
 */
(function($) {
	'use strict';
	
	$(document).ready(function() {
		
		// Search users
		$('#ump-mm-search-btn').on('click', function() {
			var membershipId = $('#ump-mm-search-membership').val();
			
			if (!membershipId) {
				alert(umpMM.strings.selectMembership);
				return;
			}
			
			var $btn = $(this);
			$btn.prop('disabled', true).text(umpMM.strings.loading);
			
			$.ajax({
				url: umpMM.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ump_mm_search_users',
					nonce: umpMM.nonce,
					membership_id: membershipId
				},
				success: function(response) {
					$btn.prop('disabled', false).text('Caută Utilizatori');
					
					if (response.success) {
						displayUsers(response.data.users);
						$('#ump-mm-users-results').show();
					} else {
						alert(response.data.message || umpMM.strings.error);
					}
				},
				error: function() {
					$btn.prop('disabled', false).text('Caută Utilizatori');
					alert(umpMM.strings.error);
				}
			});
		});
		
		// Display users
		function displayUsers(users) {
			var $list = $('#ump-mm-users-list');
			$list.empty();
			
			if (!users || users.length === 0) {
				$list.html('<tr><td colspan="5">' + umpMM.strings.noUsers + '</td></tr>');
				return;
			}
			
			$.each(users, function(index, user) {
				var $row = $('<tr></tr>');
				$row.append('<td><input type="checkbox" class="ump-mm-user-checkbox" value="' + user.id + '"></td>');
				$row.append('<td>' + user.id + '</td>');
				$row.append('<td>' + user.username + '</td>');
				$row.append('<td>' + user.email + '</td>');
				$row.append('<td>' + (user.name || '-') + '</td>');
				$list.append($row);
			});
			
			// Reset select all
			$('#ump-mm-select-all').prop('checked', false);
		}
		
		// Select all checkbox
		$('#ump-mm-select-all').on('change', function() {
			$('.ump-mm-user-checkbox').prop('checked', $(this).prop('checked'));
		});
		
		// Add membership in bulk
		$('#ump-mm-add-membership-btn').on('click', function() {
			var selectedUsers = [];
			$('.ump-mm-user-checkbox:checked').each(function() {
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
			
			if (!confirm('Ești sigur că vrei să adaugi acest membership la ' + selectedUsers.length + ' utilizatori selectați?')) {
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
				success: function(response) {
					$btn.prop('disabled', false).text('Adaugă la Utilizatorii Selectați');
					
					if (response.success) {
						var msg = 'Succes: ' + response.data.success + ' utilizatori';
						if (response.data.errors > 0) {
							msg += '\nErori: ' + response.data.errors;
						}
						
						// Show error messages if any
						if (response.data.messages && response.data.messages.length > 0) {
							msg += '\n\nDetalii erori:\n' + response.data.messages.join('\n');
							console.log('Error messages:', response.data.messages);
						}
						
						alert(msg);
					} else {
						alert(response.data.message || umpMM.strings.error);
					}
				},
				error: function() {
					$btn.prop('disabled', false).text('Adaugă la Utilizatorii Selectați');
					alert(umpMM.strings.error);
				}
			});
		});
		
		// Save auto rule
		$('#ump-mm-save-rule-btn').on('click', function() {
			var trigger = $('#ump-mm-rule-trigger').val();
			var target = $('#ump-mm-rule-target').val();
			
			if (!trigger || !target) {
				alert('Te rugăm să selectezi ambele memberships.');
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
				success: function(response) {
					$btn.prop('disabled', false).text('Salvează Regula');
					
					if (response.success) {
						alert(response.data.message || umpMM.strings.success);
						location.reload();
					} else {
						alert(response.data.message || umpMM.strings.error);
					}
				},
				error: function() {
					$btn.prop('disabled', false).text('Salvează Regula');
					alert(umpMM.strings.error);
				}
			});
		});
		
		// Delete auto rule
		$(document).on('click', '.ump-mm-delete-rule', function() {
			if (!confirm('Ești sigur că vrei să ștergi această regulă?')) {
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
				success: function(response) {
					if (response.success) {
						$btn.closest('tr').fadeOut(function() {
							$(this).remove();
							if ($('.ump-mm-delete-rule').length === 0) {
								location.reload();
							}
						});
					} else {
						$btn.prop('disabled', false);
						alert(response.data.message || umpMM.strings.error);
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					alert(umpMM.strings.error);
				}
			});
		});
		
	});
	
})(jQuery);


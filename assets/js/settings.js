jQuery(function($) {
	'use strict';

	// Get translations
	var i18n = (typeof LukStackSettings !== 'undefined' && LukStackSettings.i18n) ? LukStackSettings.i18n : {};

	/**
	 * Escape HTML to prevent XSS
	 *
	 * @param {string} text Text to escape
	 * @return {string} Escaped text
	 */
	function escapeHtml(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
	}

	$('#test-webhook').on('click', function() {
		var $btn = $(this);
		var $result = $('#webhook-test-result');

		$btn.prop('disabled', true).text(i18n.sending || 'Sending...');
		$result.html('<p style="color: #666;">' + escapeHtml(i18n.connecting || 'Connecting to webhook...') + '</p>');

		$.ajax({
			url: LukStack.ajax_url,
			type: 'POST',
			data: {
				action: 'lukstack_test_webhook',
				nonce: LukStack.nonce
			},
			timeout: 15000,
			success: function(response) {
				if (response.success) {
					var msg = (response.data && response.data.message) ? response.data.message : response.data;
					$result.html('<div class="notice notice-success inline"><p><strong>' + escapeHtml(i18n.success || 'Success!') + '</strong> ' + escapeHtml(msg) + '</p></div>');
				} else {
					var errorMsg = (response.data && response.data.message) ? response.data.message : (response.data || (i18n.unknownError || 'Unknown error occurred'));
					$result.html('<div class="notice notice-error inline"><p><strong>' + escapeHtml(i18n.error || 'Error:') + '</strong> ' + escapeHtml(errorMsg) + '</p></div>');
				}
			},
			error: function(xhr, status) {
				var errorMsg = i18n.networkError || 'Network error. Please try again.';
				if (status === 'timeout') {
					errorMsg = i18n.timeoutError || 'Request timeout. The webhook may be slow or unreachable.';
				}
				$result.html('<div class="notice notice-error inline"><p><strong>' + escapeHtml(i18n.error || 'Error:') + '</strong> ' + escapeHtml(errorMsg) + '</p></div>');
			},
			complete: function() {
				$btn.prop('disabled', false).text(i18n.sendTest || 'Send Test Notification');
			}
		});
	});

	$('#webhook_url').on('input', function() {
		var hasUrl = $(this).val().trim().length > 0;
		$('#test-webhook').prop('disabled', !hasUrl);
	});
});

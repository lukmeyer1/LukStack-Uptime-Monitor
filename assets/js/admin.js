jQuery(function($) {
	'use strict';

	// Constants
	const AJAX_TIMEOUT = 30000; // 30 seconds
	const TOAST_DURATION = 3000; // 3 seconds

	// Get translations
	const i18n = LukStack.i18n || {};

	/**
	 * Escape HTML to prevent XSS
	 *
	 * @param {string} text Text to escape
	 * @return {string} Escaped text
	 */
	function escapeHtml(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, m => map[m]);
	}

	/**
	 * Show toast notification
	 *
	 * @param {string} message Message to display
	 * @param {string} type Type: success, error, info
	 */
	function showToast(message, type = 'info') {
		const $toast = $('<div class="lukstack-toast lukstack-toast-' + type + '">' + escapeHtml(message) + '</div>');
		$('body').append($toast);

		setTimeout(() => $toast.addClass('show'), 10);

		setTimeout(() => {
			$toast.removeClass('show');
			setTimeout(() => $toast.remove(), 300);
		}, TOAST_DURATION);
	}

	/**
	 * Handle AJAX errors uniformly
	 *
	 * @param {object} xhr XHR object
	 * @param {string} status Status string
	 * @param {string} error Error string
	 * @return {string} Error message
	 */
	function handleAjaxError(xhr, status, error) {
		if (status === 'timeout') {
			return i18n.timeout || 'Request timeout - website may be slow or down';
		}

		if (xhr.status === 0) {
			return i18n.noConnection || 'No connection - check your internet';
		}

		if (xhr.status >= 500) {
			return i18n.serverError || 'Server error - please try again';
		}

		return i18n.networkError || 'Network error - please try again';
	}

	/**
	 * Make AJAX request with standard error handling
	 *
	 * @param {object} options AJAX options
	 * @return {object} jQuery AJAX promise
	 */
	function makeAjaxRequest(options) {
		const defaults = {
			url: LukStack.ajax_url,
			type: 'POST',
			timeout: AJAX_TIMEOUT,
			data: {
				nonce: LukStack.nonce
			}
		};

		return $.ajax($.extend(true, {}, defaults, options));
	}

	/**
	 * Reset button to original state
	 *
	 * @param {jQuery} $btn Button element
	 * @param {string} originalText Original button text
	 */
	function resetButton($btn, originalText) {
		$btn.text(originalText)
			.removeClass('loading')
			.prop('disabled', false);
	}

	/**
	 * Update table row with new data
	 *
	 * @param {jQuery} $row Table row
	 * @param {object} data Response data
	 */
	function updateTableRow($row, data) {
		if (data.status_html) {
			$row.find('.status').html(data.status_html);
		}
		if (data.response_time_html) {
			$row.find('.response-time').html(data.response_time_html);
		}
		if (data.ssl_html) {
			$row.find('.ssl-expiry').html(data.ssl_html);
		}
		if (data.checked) {
			$row.find('.last-checked').text(data.checked);
		}
	}

	/**
	 * Form validation
	 */
	$('#lukstack-add-form').on('submit', function(e) {
		const $urlInput = $('#lukstack_url');
		const $emailInput = $('#lukstack_email');
		const url = $urlInput.val().trim();
		const email = $emailInput.val().trim();

		// Validate URL format
		if (!url) {
			e.preventDefault();
			showToast(i18n.enterUrl || 'Please enter a URL', 'error');
			$urlInput.addClass('error').focus();
			return false;
		}

		if (!url.match(/^https?:\/\//i)) {
			e.preventDefault();
			showToast(i18n.invalidUrl || 'URL must start with http:// or https://', 'error');
			$urlInput.addClass('error').focus();
			return false;
		}

		// Validate email if provided
		if (email && !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
			e.preventDefault();
			showToast(i18n.invalidEmail || 'Please enter a valid email address', 'error');
			$emailInput.addClass('error').focus();
			return false;
		}

		return true;
	});

	/**
	 * Auto-add https:// if missing
	 */
	$('#lukstack_url').on('blur', function() {
		const $input = $(this);
		const val = $input.val().trim();

		if (val && !val.match(/^https?:\/\//i)) {
			$input.val('https://' + val);
		}
	});

	/**
	 * Remove error class on input
	 */
	$('input[type="url"], input[type="email"]').on('focus input', function() {
		$(this).removeClass('error');
	});

	/**
	 * Check Now button handler
	 */
	$(document).on('click', '.lukstack-check', function(e) {
		e.preventDefault();

		const $btn = $(this);
		const $row = $btn.closest('tr');
		const id = $btn.data('id');
		const originalText = $btn.text();

		if ($btn.prop('disabled')) {
			return;
		}

		$btn.prop('disabled', true)
			.text(i18n.checking || 'Checking...')
			.addClass('loading');

		makeAjaxRequest({
			data: {
				action: 'lukstack_check_now',
				id: id,
				nonce: LukStack.nonce
			}
		})
			.done(function(response) {
				if (response.success && response.data) {
					updateTableRow($row, response.data);

					$btn.text('✓ ' + (i18n.checked || 'Checked'))
						.removeClass('loading')
						.addClass('button-primary');

					showToast(response.data.message || (i18n.success || 'Success!'), 'success');

					setTimeout(() => {
						$btn.text(originalText)
							.removeClass('button-primary')
							.prop('disabled', false);
					}, 2000);
				} else {
					showToast((i18n.error || 'Error') + ': ' + (response.data?.message || i18n.networkError || 'Unknown error'), 'error');
					resetButton($btn, originalText);
				}
			})
			.fail(function(xhr, status, error) {
				showToast(handleAjaxError(xhr, status, error), 'error');
				resetButton($btn, originalText);
			});
	});

	/**
	 * Delete button handler
	 */
	$(document).on('click', '.lukstack-delete', function(e) {
		e.preventDefault();

		if (!confirm(i18n.confirmDelete || 'Are you sure you want to delete this website?')) {
			return;
		}

		const $btn = $(this);
		const $row = $btn.closest('tr');
		const id = $btn.data('id');
		const originalText = $btn.text();

		if ($btn.prop('disabled')) {
			return;
		}

		$row.find('button').prop('disabled', true);
		$btn.text(i18n.deleting || 'Deleting...').addClass('loading');

		makeAjaxRequest({
			data: {
				action: 'lukstack_delete',
				id: id,
				nonce: LukStack.nonce
			},
			timeout: 10000
		})
			.done(function(response) {
				if (response.success) {
					$row.fadeOut(400, function() {
						$(this).remove();

						const $tbody = $('table.widefat tbody');
						if ($tbody.find('tr').length === 0) {
							$tbody.html('<tr><td colspan="8" class="no-sites">' +
								escapeHtml(i18n.noSites || 'No websites added yet') + '</td></tr>');
						}
					});

					showToast(response.data?.message || (i18n.success || 'Success!'), 'success');
				} else {
					showToast((i18n.error || 'Error') + ': ' + (response.data?.message || i18n.networkError || 'Unknown error'), 'error');
					$row.find('button').prop('disabled', false);
					$btn.text(originalText).removeClass('loading');
				}
			})
			.fail(function(xhr, status, error) {
				showToast(handleAjaxError(xhr, status, error), 'error');
				$row.find('button').prop('disabled', false);
				$btn.text(originalText).removeClass('loading');
			});
	});

	/**
	 * Bulk check all sites
	 */
	$('.lukstack-bulk-check').on('click', function(e) {
		e.preventDefault();

		const $btn = $(this);
		const originalText = $btn.text();

		if ($btn.prop('disabled')) {
			return;
		}

		if (!confirm(i18n.confirmBulkCheck || 'Check all websites now? This may take a few minutes.')) {
			return;
		}

		$btn.prop('disabled', true).text(i18n.checking || 'Checking...');

		makeAjaxRequest({
			data: {
				action: 'lukstack_bulk_check',
				nonce: LukStack.nonce
			},
			timeout: 120000 // 2 minutes
		})
			.done(function(response) {
				if (response.success) {
					showToast(response.data.message, 'success');
					setTimeout(() => location.reload(), 1500);
				} else {
					showToast((i18n.error || 'Error') + ': ' + (response.data?.message || i18n.networkError || 'Unknown error'), 'error');
				}
			})
			.fail(function(xhr, status, error) {
				showToast(handleAjaxError(xhr, status, error), 'error');
			})
			.always(function() {
				resetButton($btn, originalText);
			});
	});

	/**
	 * Auto-dismiss WordPress notices
	 */
	setTimeout(() => $('.notice.is-dismissible').fadeOut(), 5000);

	/**
	 * Prevent form resubmission on page reload
	 */
	if (window.history.replaceState) {
		window.history.replaceState(null, null, window.location.href);
	}

	/**
	 * Keyboard shortcuts
	 */
	$(document).on('keydown', function(e) {
		if (e.key === 'Escape') {
			$('button:disabled').blur();
			$('.lukstack-toast').remove();
		}
	});

	/**
	 * Copy URL to clipboard on Ctrl+Click
	 */
	$(document).on('click', '.widefat td.site-url a', function(e) {
		if (e.ctrlKey || e.metaKey) {
			e.preventDefault();
			const url = $(this).attr('href');

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url)
					.then(() => showToast(i18n.urlCopied || 'URL copied to clipboard!', 'success'))
					.catch(() => showToast(i18n.copyFailed || 'Could not copy URL', 'error'));
			}
		}
	});

	/**
	 * Loading indicator for form submission
	 */
	$('#lukstack-add-form').on('submit', function() {
		const $submitBtn = $(this).find('button[type="submit"]');
		$submitBtn.prop('disabled', true).addClass('loading');
	});
});

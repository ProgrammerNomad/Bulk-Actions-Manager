(function () {
	'use strict';

	if ( typeof wp === 'undefined' || ! wp.apiFetch ) {
		return;
	}

	wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( bamAdmin.nonce ) );
	wp.apiFetch.use( wp.apiFetch.createRootURLMiddleware( bamAdmin.restRoot ) );

	function apiPath(path) {
		return bamAdmin.restNs + '/' + path.replace(/^\//, '');
	}

	window.bamApi = {
		get: function (path) {
			return wp.apiFetch({ path: apiPath(path), method: 'GET' });
		},
		post: function (path, data) {
			return wp.apiFetch({ path: apiPath(path), method: 'POST', data: data || {} });
		},
		del: function (path) {
			return wp.apiFetch({ path: apiPath(path), method: 'DELETE' });
		},
		put: function (path, data) {
			return wp.apiFetch({ path: apiPath(path), method: 'PUT', data: data || {} });
		}
	};

	function bamDialogFallback(message) {
		return window.confirm(message);
	}

	function bamOpenDialog($body, options, resolve) {
		var i18n = bamAdmin.i18n || {};
		var resolved = false;

		function finish(value) {
			if (resolved) {
				return;
			}
			resolved = true;
			resolve(value);
		}

		if (typeof jQuery === 'undefined' || !jQuery.fn.dialog) {
			finish(bamDialogFallback(options.message || i18n.confirm));
			return;
		}

		var buttons = options.buttons || [];
		if (!buttons.length) {
			buttons = [
				{
					text: options.cancelText || i18n.confirmCancel || 'Cancel',
					class: 'button',
					click: function () {
						jQuery(this).dialog('close');
						finish(false);
					}
				},
				{
					text: options.okText || i18n.confirmOk || 'Continue',
					class: options.destructive ? 'button button-link-delete' : 'button button-primary',
					click: function () {
						jQuery(this).dialog('close');
						finish(true);
					}
				}
			];
		}

		$body.dialog({
			title: options.title || i18n.confirmTitle || 'Confirm',
			modal: true,
			width: options.width || 480,
			dialogClass: 'wp-dialog bam-confirm-dialog' + (options.destructive ? ' bam-confirm-dialog--destructive' : ''),
			closeOnEscape: true,
			buttons: buttons,
			close: function () {
				finish(false);
				jQuery(this).dialog('destroy');
				$body.remove();
			}
		});
	}

	window.bamConfirm = function (options) {
		options = options || {};
		var i18n = bamAdmin.i18n || {};
		var message = options.message || i18n.confirm || 'Are you sure?';

		return new Promise(function (resolve) {
			if (typeof jQuery === 'undefined' || !jQuery.fn.dialog) {
				resolve(bamDialogFallback(message));
				return;
			}

			var $body = jQuery('<div class="bam-confirm-dialog__body"></div>');
			$body.append(jQuery('<p class="bam-confirm-dialog__message"></p>').text(message));

			if (options.detail) {
				$body.append(jQuery('<p class="bam-confirm-dialog__detail description"></p>').text(options.detail));
			}

			bamOpenDialog($body, options, resolve);
		});
	};

	window.bamAlert = function (options) {
		if (typeof options === 'string') {
			options = { message: options };
		}
		options = options || {};
		var i18n = bamAdmin.i18n || {};
		var message = options.message || '';

		return new Promise(function (resolve) {
			if (typeof jQuery === 'undefined' || !jQuery.fn.dialog) {
				window.alert(message);
				resolve();
				return;
			}

			var $body = jQuery('<div class="bam-confirm-dialog__body"></div>');
			$body.append(jQuery('<p class="bam-confirm-dialog__message"></p>').text(message));

			bamOpenDialog($body, {
				title: options.title || i18n.noticeTitle || 'Notice',
				width: options.width || 420,
				buttons: [
					{
						text: options.okText || i18n.confirmOk || 'OK',
						class: 'button button-primary',
						click: function () {
							jQuery(this).dialog('close');
							resolve();
						}
					}
				]
			}, function () {
				resolve();
			});
		});
	};
})();

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

	var supportsDialog = typeof HTMLDialogElement !== 'undefined';
	var dialogEl = null;
	var dismissValue = false;

	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	function ensureDialog() {
		if (dialogEl) {
			return dialogEl;
		}

		dialogEl = document.createElement('dialog');
		dialogEl.id = 'bam-dialog';
		dialogEl.className = 'bam-dialog';
		dialogEl.setAttribute('aria-labelledby', 'bam-dialog-title');
		dialogEl.innerHTML =
			'<div class="bam-dialog__panel">' +
				'<div class="bam-dialog__header">' +
					'<h2 id="bam-dialog-title" class="bam-dialog__title"></h2>' +
					'<button type="button" class="bam-dialog__close" aria-label="Close">' +
						'<span class="screen-reader-text">Close</span>' +
						'<span aria-hidden="true">&times;</span>' +
					'</button>' +
				'</div>' +
				'<div class="bam-dialog__body"></div>' +
				'<div class="bam-dialog__footer"></div>' +
			'</div>';

		document.body.appendChild(dialogEl);

		dialogEl.querySelector('.bam-dialog__close').addEventListener('click', function () {
			closeDialog(dismissValue);
		});

		dialogEl.addEventListener('cancel', function (event) {
			event.preventDefault();
			closeDialog(dismissValue);
		});

		dialogEl.addEventListener('click', function (event) {
			if (event.target === dialogEl) {
				closeDialog(dismissValue);
			}
		});

		return dialogEl;
	}

	function closeDialog(value) {
		if (!dialogEl || !dialogEl.open) {
			return;
		}
		dialogEl.close();
		if (typeof dialogEl._bamResolve === 'function') {
			var resolve = dialogEl._bamResolve;
			dialogEl._bamResolve = null;
			resolve(value);
		}
	}

	window.bamDialog = {
		open: function (options) {
			options = options || {};
			var i18n = (window.bamAdmin && bamAdmin.i18n) || {};

			if (!supportsDialog) {
				var fallbackMessage = options.message || '';
				if (options.detail) {
					fallbackMessage += (fallbackMessage ? '\n\n' : '') + options.detail;
				}
				var buttons = options.buttons || [];
				if (buttons.length <= 1) {
					window.alert(fallbackMessage || options.title || i18n.noticeTitle || 'Notice');
					return Promise.resolve(buttons.length ? buttons[0].value : undefined);
				}
				return Promise.resolve(window.confirm(fallbackMessage));
			}

			return new Promise(function (resolve) {
				var el = ensureDialog();
				dismissValue = options.dismissValue !== undefined ? options.dismissValue : false;

				var titleEl = el.querySelector('.bam-dialog__title');
				var bodyEl = el.querySelector('.bam-dialog__body');
				var footerEl = el.querySelector('.bam-dialog__footer');

				titleEl.textContent = options.title || i18n.confirmTitle || 'Confirm';

				var bodyHtml = '';
				if (options.htmlMessage) {
					bodyHtml += '<div class="bam-dialog__message">' + options.htmlMessage + '</div>';
				} else if (options.message) {
					bodyHtml += '<p class="bam-dialog__message">' + escapeHtml(options.message) + '</p>';
				}
				if (options.detail) {
					bodyHtml += '<p class="bam-dialog__detail description">' + escapeHtml(options.detail) + '</p>';
				}
				bodyEl.innerHTML = bodyHtml;

				if (options.destructive) {
					el.classList.add('bam-dialog--destructive');
				} else {
					el.classList.remove('bam-dialog--destructive');
				}

				footerEl.innerHTML = '';
				var buttons = options.buttons || [];
				var focusBtn = null;

				buttons.forEach(function (btn) {
					var button = document.createElement('button');
					button.type = 'button';
					button.textContent = btn.text;
					var classes = ['button'];
					if (btn.destructive) {
						classes.push('button-link-delete');
					} else if (btn.primary) {
						classes.push('button-primary');
					}
					button.className = classes.join(' ');
					button.addEventListener('click', function () {
						el._bamResolve = null;
						el.close();
						resolve(btn.value);
					});
					footerEl.appendChild(button);
					if (btn.primary || btn.destructive) {
						focusBtn = button;
					}
				});

				el._bamResolve = resolve;
				el.showModal();

				if (focusBtn) {
					focusBtn.focus();
				} else if (footerEl.firstChild) {
					footerEl.firstChild.focus();
				}
			});
		}
	};

	window.bamConfirm = function (options) {
		options = options || {};
		var i18n = (window.bamAdmin && bamAdmin.i18n) || {};

		return bamDialog.open({
			title: options.title || i18n.confirmTitle,
			message: options.message || i18n.confirm,
			detail: options.detail,
			destructive: !!options.destructive,
			dismissValue: false,
			buttons: [
				{ text: options.cancelText || i18n.confirmCancel || 'Cancel', value: false },
				{
					text: options.okText || i18n.confirmOk || 'Continue',
					value: true,
					primary: !options.destructive,
					destructive: !!options.destructive
				}
			]
		});
	};

	window.bamAlert = function (options) {
		if (typeof options === 'string') {
			options = { message: options };
		}
		options = options || {};
		var i18n = (window.bamAdmin && bamAdmin.i18n) || {};
		var message = options.message || '';
		var hasHtml = !!options.html || /<[a-z][\s\S]*>/i.test(message);

		return bamDialog.open({
			title: options.title || i18n.noticeTitle || 'Notice',
			message: hasHtml ? undefined : message,
			htmlMessage: hasHtml ? (options.html || message) : undefined,
			dismissValue: undefined,
			buttons: [
				{ text: options.okText || i18n.confirmOk || 'OK', value: true, primary: true }
			]
		}).then(function () {});
	};

	window.bamRestErrorMessage = function (err) {
		if (err && err.message && typeof err.message === 'string') {
			return err.message;
		}
		if (err && err.data && err.data.message) {
			return err.data.message;
		}
		var i18n = (window.bamAdmin && bamAdmin.i18n) || {};
		return i18n.error || 'An error occurred.';
	};
})();

(function ($) {
	'use strict';

	function deleteJobConfirmOptions() {
		var i18n = (window.bamAdmin && bamAdmin.i18n) || {};

		return {
			title: i18n.confirmDeleteJobTitle || 'Delete selected jobs?',
			message:
				i18n.confirmDeleteJobMessage ||
				'Remove the selected job records from the jobs list.',
			detail:
				i18n.confirmDeleteJobDetail ||
				'Audit log entries are kept. This cannot be undone.',
			destructive: true,
			okText: i18n.confirmDeleteOk || 'Delete'
		};
	}

	function confirmDeleteJob() {
		var opts = deleteJobConfirmOptions();

		if (typeof window.bamConfirm === 'function') {
			return window.bamConfirm(opts);
		}

		return Promise.resolve(window.confirm(opts.message));
	}

	function getBulkActionValue($form) {
		var action = $form.find('#bulk-action-selector-top').val();

		if (!action || '-1' === action) {
			action = $form.find('#bulk-action-selector-bottom').val();
		}

		return action;
	}

	$(function () {
		var $form = $('#bam-jobs-list-form');

		if (!$form.length) {
			return;
		}

		$form.on('submit', function (event) {
			if ('delete' !== getBulkActionValue($form)) {
				return;
			}

			event.preventDefault();
			var form = this;

			confirmDeleteJob().then(function (confirmed) {
				if (confirmed) {
					form.submit();
				}
			});
		});

		$form.on('click', 'a.bam-delete-job-link', function (event) {
			event.preventDefault();
			var href = $(this).attr('href');

			confirmDeleteJob().then(function (confirmed) {
				if (confirmed && href) {
					window.location.href = href;
				}
			});
		});
	});
})(jQuery);

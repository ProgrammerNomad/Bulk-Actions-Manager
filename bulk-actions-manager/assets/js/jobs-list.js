(function ($) {
	'use strict';

	var i18n = function () {
		return (window.bamAdmin && bamAdmin.i18n) || {};
	};

	function deleteJobConfirmOptions() {
		var strings = i18n();

		return {
			title: strings.confirmDeleteJobTitle || 'Delete selected jobs?',
			message:
				strings.confirmDeleteJobMessage ||
				'Remove the selected job records from the jobs list.',
			detail:
				strings.confirmDeleteJobDetail ||
				'Audit log entries are kept. This cannot be undone.',
			destructive: true,
			okText: strings.confirmDeleteOk || 'Delete'
		};
	}

	function cancelJobConfirmOptions() {
		var strings = i18n();

		return {
			title: strings.confirmCancelJob || 'Cancel this job?',
			message: strings.confirmCancelJobMessage || 'Processing will stop and the job will be marked as cancelled.',
			destructive: true,
			okText: strings.confirmCancelJobOk || 'Yes, cancel job'
		};
	}

	function scheduleDeleteConfirmOptions() {
		var strings = i18n();

		return {
			title: strings.confirmScheduleDeleteTitle || 'Delete this schedule?',
			message: strings.confirmScheduleDeleteMessage || 'This recurring schedule will be removed.',
			destructive: true,
			okText: strings.confirmDeleteOk || 'Delete'
		};
	}

	function scheduleRunConfirmOptions() {
		var strings = i18n();

		return {
			title: strings.confirmScheduleRunTitle || 'Run this schedule now?',
			message: strings.confirmScheduleRunMessage || 'A new background job will be created using this schedule configuration.',
			okText: strings.confirmOk || 'Continue'
		};
	}

	function confirmWith(options) {
		if (typeof window.bamConfirm === 'function') {
			return window.bamConfirm(options);
		}

		return Promise.resolve(window.confirm(options.message));
	}

	function confirmDeleteJob() {
		return confirmWith(deleteJobConfirmOptions());
	}

	function confirmCancelJob() {
		return confirmWith(cancelJobConfirmOptions());
	}

	function confirmLink(event, options) {
		event.preventDefault();
		var href = event.currentTarget.href;

		confirmWith(options).then(function (confirmed) {
			if (confirmed && href) {
				window.location.href = href;
			}
		});
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

		if ($form.length) {
			$form.on('submit', function (event) {
				var action = getBulkActionValue($form);

				if ('delete' === action) {
					event.preventDefault();
					var form = this;

					confirmDeleteJob().then(function (confirmed) {
						if (confirmed) {
							form.submit();
						}
					});
					return;
				}

				if ('cancel' === action) {
					event.preventDefault();
					var cancelForm = this;

					confirmCancelJob().then(function (confirmed) {
						if (confirmed) {
							cancelForm.submit();
						}
					});
				}
			});

			$form.on('click', 'a.bam-delete-job-link', function (event) {
				confirmLink(event, deleteJobConfirmOptions());
			});

			$form.on('click', 'a.bam-cancel-job-link', function (event) {
				confirmLink(event, cancelJobConfirmOptions());
			});

			$form.on('click', 'a.bam-schedule-delete-link', function (event) {
				confirmLink(event, scheduleDeleteConfirmOptions());
			});

			$form.on('click', 'a.bam-schedule-run-link', function (event) {
				confirmLink(event, scheduleRunConfirmOptions());
			});
		}

		$(document).on('click', 'a.bam-cancel-job-link', function (event) {
			if ($form.length && $form[0].contains(event.currentTarget)) {
				return;
			}
			confirmLink(event, cancelJobConfirmOptions());
		});
	});
})(jQuery);

(function () {
	'use strict';

	function undoLogConfirmOptions() {
		var i18n = (window.bamAdmin && bamAdmin.i18n) || {};

		return {
			title: i18n.confirmUndoLogTitle || 'Undo this job?',
			message: i18n.confirmUndoLogMessage || 'A new undo job will be created to reverse the changes from this log entry.',
			okText: i18n.confirmUndoLogOk || 'Undo'
		};
	}

	function confirmUndoLink(link) {
		var href = link.href;

		if (typeof window.bamConfirm !== 'function') {
			if (window.confirm(undoLogConfirmOptions().message)) {
				window.location.href = href;
			}
			return;
		}

		window.bamConfirm(undoLogConfirmOptions()).then(function (confirmed) {
			if (confirmed && href) {
				window.location.href = href;
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.addEventListener('click', function (event) {
			var link = event.target.closest('.bam-undo-log-link');
			if (!link) {
				return;
			}
			event.preventDefault();
			confirmUndoLink(link);
		});
	});
})();

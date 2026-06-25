(function () {
	'use strict';

	var currentJobId = null;
	var running = false;

	function statusLabel(status) {
		var labels = (bamAdmin.i18n && bamAdmin.i18n.statusLabels) || {};
		return labels[status] || status;
	}

	function isActiveStatus(status) {
		return status === 'running' || status === 'queued' || status === 'paused';
	}

	function cancelJobConfirmOptions() {
		var i18n = bamAdmin.i18n || {};
		return {
			title: i18n.confirmCancelJob || 'Cancel this job?',
			message: i18n.confirmCancelJobMessage || 'Processing will stop and the job will be marked as cancelled.',
			destructive: true,
			okText: i18n.confirmCancelJobOk || 'Yes, cancel job'
		};
	}

	function confirmCancelJob() {
		if (typeof window.bamConfirm === 'function') {
			return window.bamConfirm(cancelJobConfirmOptions());
		}
		return Promise.resolve(window.confirm(cancelJobConfirmOptions().message));
	}

	window.bamJobRunner = {
		syncStatusUI: function (data) {
			var status = data.status || '';
			var label = statusLabel(status);

			var badge = document.getElementById('bam-job-status-badge');
			if (badge) {
				badge.innerHTML = '<span class="bam-status-badge bam-status-badge--' + status + '">' + label + '</span>';
			}

			var controls = document.getElementById('bam-job-controls');
			if (controls) {
				controls.style.display = isActiveStatus(status) ? '' : 'none';
			}

			var pauseBtn = document.getElementById('bam-control-pause');
			var resumeBtn = document.getElementById('bam-control-resume');
			var cancelBtn = document.getElementById('bam-control-cancel');
			if (pauseBtn) pauseBtn.style.display = status === 'running' ? '' : 'none';
			if (resumeBtn) resumeBtn.style.display = status === 'paused' ? '' : 'none';
			if (cancelBtn) cancelBtn.style.display = isActiveStatus(status) ? '' : 'none';

			var errorRow = document.getElementById('bam-job-last-error-row');
			var errorText = document.getElementById('bam-job-last-error-text');
			var errorMsg = data.error_message || '';
			if (errorRow) errorRow.style.display = errorMsg ? '' : 'none';
			if (errorText) errorText.textContent = errorMsg;
		},

		start: function (jobId) {
			currentJobId = jobId;
			running = true;
			this.runBatch();
		},

		runBatch: function () {
			if (!running || !currentJobId) return;

			var self = this;
			bamApi.post('jobs/' + currentJobId + '/batch', {}).then(function (data) {
				self.updateUI(data);
				if (data.status === 'running' || data.status === 'queued') {
					setTimeout(function () { self.runBatch(); }, 500);
				} else {
					running = false;
				}
			}).catch(function (err) {
				running = false;
				bamAlert({
					title: bamAdmin.i18n.errorTitle,
					message: typeof bamRestErrorMessage === 'function' ? bamRestErrorMessage(err) : bamAdmin.i18n.error
				});
			});
		},

		updateUI: function (data) {
			this.syncStatusUI(data);

			var bar = document.getElementById('bam-progress-bar');
			var text = document.getElementById('bam-progress-text');
			var stats = document.getElementById('bam-progress-stats');
			var processed = data.processed != null ? data.processed : (data.processed_items || 0);
			var total = data.total != null ? data.total : (data.total_items || 0);
			var status = data.status || '';

			if (bar) {
				if (bar.tagName === 'PROGRESS') {
					bar.value = data.percent || 0;
				} else {
					bar.style.width = (data.percent || 0) + '%';
				}
			}
			if (text) text.textContent = (data.percent || 0) + '% - ' + statusLabel(status);
			if (stats) {
				var handledLabel = (bamAdmin.i18n && bamAdmin.i18n.handled) || 'Processed';
				stats.textContent = handledLabel + ': ' + processed + ' / ' + total +
					(data.eta_seconds ? ' - ETA: ~' + data.eta_seconds + 's' : '');
			}

			this.renderItemLists(data);

			if (data.export_download_url) {
				var exportEl = document.getElementById('bam-export-download');
				if (!exportEl) {
					exportEl = document.createElement('p');
					exportEl.id = 'bam-export-download';
					var statsEl = document.getElementById('bam-progress-stats');
					if (statsEl && statsEl.parentNode) {
						statsEl.parentNode.insertBefore(exportEl, statsEl.nextSibling);
					}
				}
				exportEl.innerHTML = '<a class="button button-secondary" href="' + data.export_download_url + '">' +
					(bamAdmin.i18n.downloadExport || 'Download Export') + '</a>';
			}
		},

		renderItemLists: function (data) {
			var errorsTitle = (bamAdmin.i18n && bamAdmin.i18n.errorsHeading) || 'Errors';
			var skippedTitle = (bamAdmin.i18n && bamAdmin.i18n.skippedHeading) || 'Skipped';
			var errEl = document.getElementById('bam-job-errors');
			var skipEl = document.getElementById('bam-job-skipped');

			if (errEl) {
				if (data.errors && data.errors.length) {
					errEl.innerHTML = '<p class="bam-item-list-heading"><strong>' + errorsTitle + '</strong></p><ul>' +
						data.errors.map(function (e) {
							return '<li>#' + e.object_id + ': ' + e.message + '</li>';
						}).join('') + '</ul>';
					errEl.style.display = '';
				} else {
					errEl.innerHTML = '';
					errEl.style.display = 'none';
				}
			}

			if (skipEl) {
				if (data.skipped && data.skipped.length) {
					skipEl.innerHTML = '<p class="bam-item-list-heading"><strong>' + skippedTitle + '</strong></p><ul>' +
						data.skipped.map(function (e) {
							return '<li>#' + e.object_id + ': ' + e.message + '</li>';
						}).join('') + '</ul>';
					skipEl.style.display = '';
				} else {
					skipEl.innerHTML = '';
					skipEl.style.display = 'none';
				}
			}
		},

		cancelCurrentJob: function () {
			if (!currentJobId) {
				return Promise.resolve();
			}

			return bamApi.post('jobs/' + currentJobId + '/cancel', {}).then(function () {
				running = false;
			});
		}
	};

	function wireCancelControls() {
		var cancelButton = document.getElementById('bam-cancel-job');
		if (cancelButton) {
			cancelButton.addEventListener('click', function () {
				confirmCancelJob().then(function (confirmed) {
					if (!confirmed) {
						return;
					}
					bamJobRunner.cancelCurrentJob().then(function () {
						running = false;
						if (currentJobId) {
							window.location.href = (bamAdmin.jobsUrl || '') + '&job_id=' + currentJobId + '&cancelled=1';
						}
					}).catch(function (err) {
						bamAlert({
							title: bamAdmin.i18n.errorTitle,
							message: typeof bamRestErrorMessage === 'function' ? bamRestErrorMessage(err) : bamAdmin.i18n.error
						});
					});
				});
			});
		}

		var cancelLink = document.getElementById('bam-control-cancel');
		if (cancelLink && cancelLink.classList.contains('bam-cancel-job-link')) {
			cancelLink.addEventListener('click', function (event) {
				if (typeof window.bamConfirm !== 'function') {
					return;
				}
				event.preventDefault();
				var href = cancelLink.href;
				confirmCancelJob().then(function (confirmed) {
					if (confirmed && href) {
						window.location.href = href;
					}
				});
			});
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		wireCancelControls();

		var detailEl = document.getElementById('bam-job-detail');
		if (detailEl && detailEl.dataset.jobId) {
			var jobId = parseInt(detailEl.dataset.jobId, 10);
			bamApi.get('jobs/' + jobId).then(function (job) {
				bamJobRunner.updateUI(job);
				if (job.status === 'running' || job.status === 'queued') {
					currentJobId = jobId;
					running = true;
					bamJobRunner.start(jobId);
				}
			});
		}
	});
})();

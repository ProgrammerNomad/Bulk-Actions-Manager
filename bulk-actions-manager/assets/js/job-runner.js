(function () {
	'use strict';

	var currentJobId = null;
	var running = false;

	window.bamJobRunner = {
		start: function (jobId) {
			currentJobId = jobId;
			running = true;
			this.runBatch();
		},

		runBatch: function () {
			if ( !running || !currentJobId ) return;

			var self = this;
			bamApi.post('jobs/' + currentJobId + '/batch', {}).then(function (data) {
				self.updateUI(data);
				if ( data.status === 'running' || data.status === 'queued' ) {
					setTimeout(function () { self.runBatch(); }, 500);
				} else {
					running = false;
				}
			}).catch(function () {
				running = false;
				alert(bamAdmin.i18n.error);
			});
		},

		updateUI: function (data) {
			var bar = document.getElementById('bam-progress-bar');
			var text = document.getElementById('bam-progress-text');
			var stats = document.getElementById('bam-progress-stats');

			if ( bar ) bar.style.width = (data.percent || 0) + '%';
			if ( text ) text.textContent = (data.percent || 0) + '% — ' + (data.status || '');
			if ( stats ) {
				stats.textContent = 'Processed: ' + (data.processed || 0) + ' / ' + (data.total || 0) +
					(data.eta_seconds ? ' — ETA: ~' + data.eta_seconds + 's' : '');
			}

			if ( data.errors && data.errors.length ) {
				var errEl = document.getElementById('bam-job-errors');
				if ( errEl ) {
					errEl.innerHTML = '<p><strong>Errors:</strong></p><ul>' +
						data.errors.map(function (e) { return '<li>#' + e.object_id + ': ' + e.message + '</li>'; }).join('') +
						'</ul>';
				}
			}

			if ( data.export_download_url ) {
				var exportEl = document.getElementById('bam-export-download');
				if ( !exportEl ) {
					exportEl = document.createElement('p');
					exportEl.id = 'bam-export-download';
					var statsEl = document.getElementById('bam-progress-stats');
					if ( statsEl && statsEl.parentNode ) {
						statsEl.parentNode.insertBefore(exportEl, statsEl.nextSibling);
					}
				}
				exportEl.innerHTML = '<a class="button button-secondary" href="' + data.export_download_url + '">' +
					(bamAdmin.i18n.downloadExport || 'Download Export') + '</a>';
			}
		}
	};

	document.addEventListener('DOMContentLoaded', function () {
		var pauseBtn = document.getElementById('bam-pause-job');
		var resumeBtn = document.getElementById('bam-resume-job');
		var cancelBtn = document.getElementById('bam-cancel-job');

		if ( pauseBtn ) pauseBtn.addEventListener('click', function () {
			if ( !currentJobId ) return;
			running = false;
			bamApi.post('jobs/' + currentJobId + '/pause', {});
		});

		if ( resumeBtn ) resumeBtn.addEventListener('click', function () {
			if ( !currentJobId ) return;
			bamApi.post('jobs/' + currentJobId + '/resume', {}).then(function () {
				running = true;
				bamJobRunner.runBatch();
			});
		});

		if ( cancelBtn ) cancelBtn.addEventListener('click', function () {
			if ( !currentJobId || !confirm(bamAdmin.i18n.confirm) ) return;
			running = false;
			bamApi.post('jobs/' + currentJobId + '/cancel', {});
		});

		var detailEl = document.getElementById('bam-job-detail');
		if ( detailEl && detailEl.dataset.jobId ) {
			var jobId = parseInt(detailEl.dataset.jobId, 10);
			bamApi.get('jobs/' + jobId).then(function (job) {
				bamJobRunner.updateUI(job);
				if ( job.status === 'running' || job.status === 'queued' ) {
					currentJobId = jobId;
					running = true;
					bamJobRunner.start(jobId);
				}
			});
		}
	});
})();

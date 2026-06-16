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

		// Job detail page
		var detailEl = document.getElementById('bam-job-detail');
		if ( detailEl && detailEl.dataset.jobId ) {
			var jobId = detailEl.dataset.jobId;
			bamApi.get('jobs/' + jobId).then(function (job) {
				detailEl.innerHTML =
					'<table class="form-table"><tbody>' +
					'<tr><th>Status</th><td>' + job.status + '</td></tr>' +
					'<tr><th>Action</th><td>' + job.action_type + '</td></tr>' +
					'<tr><th>Progress</th><td>' + job.processed_items + ' / ' + job.total_items + ' (' + job.percent + '%)</td></tr>' +
					'<tr><th>Undo Available</th><td>' + (job.undo_available ? 'Yes' : 'No') + '</td></tr>' +
					'</tbody></table>';
				if ( job.status === 'running' ) {
					currentJobId = jobId;
					document.getElementById('bam-job-progress') || detailEl.insertAdjacentHTML('beforeend',
						'<div class="bam-progress"><div class="bam-progress__bar" id="bam-progress-bar" style="width:' + job.percent + '%"></div></div>');
					running = true;
					bamJobRunner.start(jobId);
				}
			});
		}
	});
})();

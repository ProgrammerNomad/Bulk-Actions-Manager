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

	window.bamDashboard = {
		render: function (data) {
			var statsEl = document.querySelector('#bam-widget-stats .bam-widget__body');
			if ( statsEl && data.statistics ) {
				statsEl.innerHTML =
					'<dl class="bam-stat-grid">' +
					'<dt>Total Jobs</dt><dd>' + data.statistics.total + '</dd>' +
					'<dt>Completed</dt><dd>' + data.statistics.completed + '</dd>' +
					'<dt>Running</dt><dd>' + data.statistics.running + '</dd>' +
					'<dt>Failed</dt><dd>' + data.statistics.failed + '</dd>' +
					'<dt>Scheduled</dt><dd>' + data.statistics.scheduled + '</dd>' +
					'</dl>';
			}

			var recentEl = document.querySelector('#bam-widget-recent-jobs .bam-widget__body');
			if ( recentEl && data.recent_jobs ) {
				if ( !data.recent_jobs.length ) {
					recentEl.textContent = 'No jobs yet.';
					return;
				}
				recentEl.innerHTML = '<table class="widefat"><thead><tr><th>Name</th><th>Action</th><th>Status</th><th>Date</th></tr></thead><tbody>' +
					data.recent_jobs.map(function (j) {
						return '<tr><td><a href="admin.php?page=bam-jobs&job_id=' + j.id + '">' + j.name + '</a></td><td>' + j.action_type + '</td><td>' + j.status + '</td><td>' + j.created_at + '</td></tr>';
					}).join('') + '</tbody></table>';
			}

			var healthEl = document.querySelector('#bam-widget-health .bam-widget__body');
			if ( healthEl && data.system_health ) {
				var h = data.system_health;
				healthEl.innerHTML =
					'<dl class="bam-stat-grid">' +
					'<dt>PHP</dt><dd>' + h.php_version + '</dd>' +
					'<dt>WordPress</dt><dd>' + h.wordpress_version + '</dd>' +
					'<dt>Memory</dt><dd>' + h.memory_limit + '</dd>' +
					'<dt>Max Execution</dt><dd>' + h.max_execution_time + 's</dd>' +
					'<dt>Cron</dt><dd>' + h.cron_status + '</dd>' +
					'<dt>Queue</dt><dd>' + h.queue_status + '</dd>' +
					'</dl>';
			}

			var undoEl = document.querySelector('#bam-widget-undo .bam-widget__body');
			if ( undoEl && data.undo_summary ) {
				var u = data.undo_summary;
				undoEl.innerHTML =
					'<dl class="bam-stat-grid">' +
					'<dt>Undo Available</dt><dd>' + u.undo_available_jobs + '</dd>' +
					'<dt>Snapshots</dt><dd>' + u.snapshot_count + '</dd>' +
					'<dt>Retention</dt><dd>' + (u.snapshot_retention === 0 ? 'Forever' : u.snapshot_retention + ' days') + '</dd>' +
					'</dl>';
			}
		}
	};
})();

(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var tbody = document.querySelector('#bam-jobs-table tbody');
		if ( !tbody ) return;

		var currentStatus = '';

		function loadJobs() {
			var path = 'jobs' + (currentStatus ? '?status=' + currentStatus : '');
			bamApi.get(path).then(function (data) {
				if ( !data.items || !data.items.length ) {
					tbody.innerHTML = '<tr><td colspan="7">No jobs found.</td></tr>';
					return;
				}
				tbody.innerHTML = data.items.map(function (job) {
					return '<tr>' +
						'<td><a href="admin.php?page=bam-jobs&job_id=' + job.id + '">' + job.id + '</a></td>' +
						'<td>' + job.name + '</td>' +
						'<td>' + job.action_type + '</td>' +
						'<td>' + job.status + '</td>' +
						'<td>' + job.processed_items + '/' + job.total_items + '</td>' +
						'<td>' + job.created_at + '</td>' +
						'<td>' + (job.finished_at || '-') + '</td>' +
						'</tr>';
				}).join('');
			});
		}

		document.querySelectorAll('#bam-jobs-filter a').forEach(function (link) {
			link.addEventListener('click', function (e) {
				e.preventDefault();
				currentStatus = link.dataset.status || '';
				loadJobs();
			});
		});

		loadJobs();
	});
})();

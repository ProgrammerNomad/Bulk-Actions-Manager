(function () {
	'use strict';

	function renderPreview(data) {
		var countEl = document.getElementById('bam-preview-count');
		var tbody = document.querySelector('#bam-preview-table tbody');
		if ( !countEl || !tbody ) return;

		countEl.textContent = data.total + ' matching record(s)';
		if ( !data.items || !data.items.length ) {
			tbody.innerHTML = '<tr><td colspan="6">No results.</td></tr>';
			return;
		}
		tbody.innerHTML = data.items.map(function (item) {
			return '<tr><td>' + item.id + '</td><td>' + item.title + '</td><td>' + item.type + '</td><td>' + item.status + '</td><td>' + item.author + '</td><td>' + item.date + '</td></tr>';
		}).join('');
	}

	function refreshPreview() {
		if ( typeof bamFilterBuilder === 'undefined' ) return;
		var filter = bamFilterBuilder.getFilter();
		bamApi.post('preview', { filter: filter }).then(renderPreview);
	}

	function exportPreview() {
		if ( typeof bamFilterBuilder === 'undefined' ) return;
		var filter = bamFilterBuilder.getFilter();
		bamApi.post('preview/export', { filter: filter }).then(function (data) {
			var blob = new Blob([ data.csv ], { type: 'text/csv' });
			var url = URL.createObjectURL(blob);
			var a = document.createElement('a');
			a.href = url;
			a.download = 'bam-preview.csv';
			a.click();
			URL.revokeObjectURL(url);
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var refreshBtn = document.getElementById('bam-refresh-preview');
		var exportBtn = document.getElementById('bam-export-preview');
		if ( refreshBtn ) refreshBtn.addEventListener('click', refreshPreview);
		if ( exportBtn ) exportBtn.addEventListener('click', exportPreview);

		// Load actions
		var actionSelect = document.getElementById('bam-action-type');
		var safetyBadge = document.getElementById('bam-action-safety');
		var actionFields = document.getElementById('bam-action-fields');

		if ( actionSelect ) {
			bamApi.get('actions').then(function (data) {
				var html = '';
				Object.keys(data.groups).forEach(function (group) {
					html += '<optgroup label="' + group + '">';
					data.groups[group].forEach(function (action) {
						html += '<option value="' + action.id + '" data-safety="' + action.safety_level + '" data-undo="' + action.supports_undo + '">' + action.label + '</option>';
					});
					html += '</optgroup>';
				});
				actionSelect.innerHTML = html;
				updateActionUI();
			});

			actionSelect.addEventListener('change', updateActionUI);
		}

		function updateActionUI() {
			var opt = actionSelect.selectedOptions[0];
			if ( !opt ) return;
			var safety = opt.dataset.safety;
			var labels = { safe: '✓ Undo Supported', recoverable: '↺ Recoverable', destructive: '⚠ Cannot Be Undone' };
			safetyBadge.textContent = labels[safety] || '';
			safetyBadge.className = 'bam-badge bam-badge--' + safety;

			actionFields.innerHTML = '';
			var id = actionSelect.value;

			if ( id === 'author.change' ) {
				actionFields.innerHTML = '<div class="bam-field"><label>Author ID</label><input type="number" id="bam-payload-author" class="small-text" /></div>';
			} else if ( id.indexOf('category.') === 0 || id.indexOf('tag.') === 0 ) {
				actionFields.innerHTML = '<div class="bam-field"><label>Term IDs (comma-separated)</label><input type="text" id="bam-payload-terms" class="regular-text" /></div>';
			} else if ( id.indexOf('meta.') === 0 ) {
				actionFields.innerHTML = '<div class="bam-field"><label>Meta Key</label><input type="text" id="bam-payload-meta-key" class="regular-text" /></div>' +
					(id !== 'meta.remove' ? '<div class="bam-field"><label>Meta Value</label><input type="text" id="bam-payload-meta-value" class="regular-text" /></div>' : '');
			} else if ( id === 'content.find_replace' ) {
				actionFields.innerHTML = '<div class="bam-field"><label>Field</label><select id="bam-payload-field"><option value="content">Content</option><option value="title">Title</option><option value="excerpt">Excerpt</option></select></div>' +
					'<div class="bam-field"><label>Find</label><input type="text" id="bam-payload-find" class="regular-text" /></div>' +
					'<div class="bam-field"><label>Replace</label><input type="text" id="bam-payload-replace" class="regular-text" /></div>';
			} else if ( id === 'content.append' || id === 'content.prepend' ) {
				actionFields.innerHTML = '<div class="bam-field"><label>Field</label><select id="bam-payload-field"><option value="content">Content</option><option value="title">Title</option></select></div>' +
					'<div class="bam-field"><label>Text</label><textarea id="bam-payload-text" class="large-text" rows="3"></textarea></div>';
			}
		}

		var startBtn = document.getElementById('bam-start-job');
		if ( startBtn ) {
			startBtn.addEventListener('click', function () {
				var actionId = actionSelect.value;
				if ( actionId.indexOf('delete.permanent') === 0 && !confirm('This action is destructive and cannot be undone. Continue?') ) {
					return;
				}

				var payload = buildPayload(actionId);
				var data = {
					name: document.getElementById('bam-job-name').value,
					filter: bamFilterBuilder.getFilter(),
					action_type: actionId,
					action_payload: payload,
					is_dry_run: document.getElementById('bam-dry-run').checked,
					batch_size: parseInt(document.getElementById('bam-batch-size').value, 10),
					processing_mode: document.getElementById('bam-processing-mode').value
				};

				startBtn.disabled = true;
				bamApi.post('jobs', data).then(function (result) {
					if ( result.dry_run ) {
						alert(result.message || 'Dry run complete.');
						startBtn.disabled = false;
						return;
					}
					if ( typeof bamJobRunner !== 'undefined' ) {
						document.getElementById('bam-job-progress').classList.remove('bam-panel--hidden');
						bamJobRunner.start(result.job_id);
					}
					startBtn.disabled = false;
				}).catch(function () {
					alert(bamAdmin.i18n.error);
					startBtn.disabled = false;
				});
			});
		}

		function buildPayload(actionId) {
			if ( actionId === 'author.change' ) {
				return { author_id: parseInt(document.getElementById('bam-payload-author').value, 10) };
			}
			if ( actionId.indexOf('category.') === 0 || actionId.indexOf('tag.') === 0 ) {
				var terms = document.getElementById('bam-payload-terms').value;
				return { term_ids: terms.split(',').map(function (s) { return parseInt(s.trim(), 10); }).filter(Boolean) };
			}
			if ( actionId.indexOf('meta.') === 0 ) {
				var p = { meta_key: document.getElementById('bam-payload-meta-key').value };
				var valEl = document.getElementById('bam-payload-meta-value');
				if ( valEl ) p.meta_value = valEl.value;
				return p;
			}
			if ( actionId === 'content.find_replace' ) {
				return {
					field: document.getElementById('bam-payload-field').value,
					find: document.getElementById('bam-payload-find').value,
					replace: document.getElementById('bam-payload-replace').value
				};
			}
			if ( actionId === 'content.append' || actionId === 'content.prepend' ) {
				return {
					field: document.getElementById('bam-payload-field').value,
					text: document.getElementById('bam-payload-text').value
				};
			}
			return {};
		}
	});
})();

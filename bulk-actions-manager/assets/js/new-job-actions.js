(function () {
	'use strict';

	function getFilterPayload() {
		var el = document.getElementById('bam-filter-payload');
		if (!el) return { post_type: ['post'], logic: 'AND', conditions: [] };
		try {
			return JSON.parse(el.textContent);
		} catch (e) {
			return { post_type: ['post'], logic: 'AND', conditions: [] };
		}
	}

	function safetyHint(level) {
		var map = {
			safe: { icon: 'dashicons-yes-alt', text: 'Undo supported' },
			recoverable: { icon: 'dashicons-backup', text: 'Recoverable' },
			destructive: { icon: 'dashicons-warning', text: 'Cannot be undone' }
		};
		var item = map[level];
		if (!item) return '';
		return '<p class="description"><span class="dashicons ' + item.icon + '" aria-hidden="true"></span> ' + item.text + '</p>';
	}

	function fieldRow(label, html) {
		return '<tr><th scope="row">' + label + '</th><td>' + html + '</td></tr>';
	}

	document.addEventListener('DOMContentLoaded', function () {
		var actionSelect = document.getElementById('bam-action-type');
		var safetyWrap = document.getElementById('bam-action-safety-wrap');
		var actionFields = document.getElementById('bam-action-fields');

		if (actionSelect) {
			actionSelect.addEventListener('change', updateActionUI);
			updateActionUI();
		}

		function updateActionUI() {
			if (!actionSelect) return;
			var opt = actionSelect.selectedOptions[0];
			if (!opt) return;
			if (safetyWrap) {
				safetyWrap.innerHTML = safetyHint(opt.dataset.safety);
			}

			if (!actionFields) return;
			actionFields.innerHTML = '';
			var id = actionSelect.value;

			if (id === 'author.change') {
				actionFields.innerHTML = fieldRow('Author ID', '<input type="number" id="bam-payload-author" class="small-text" />');
			} else if (id.indexOf('category.') === 0 || id.indexOf('tag.') === 0) {
				actionFields.innerHTML = fieldRow('Term IDs', '<input type="text" id="bam-payload-terms" class="regular-text" placeholder="1, 2, 3" />');
			} else if (id.indexOf('meta.') === 0) {
				var html = fieldRow('Meta Key', '<input type="text" id="bam-payload-meta-key" class="regular-text" />');
				if (id !== 'meta.remove') {
					html += fieldRow('Meta Value', '<input type="text" id="bam-payload-meta-value" class="regular-text" />');
				}
				actionFields.innerHTML = html;
			} else if (id === 'content.find_replace') {
				actionFields.innerHTML =
					fieldRow('Field', '<select id="bam-payload-field"><option value="content">Content</option><option value="title">Title</option><option value="excerpt">Excerpt</option></select>') +
					fieldRow('Find', '<input type="text" id="bam-payload-find" class="regular-text" />') +
					fieldRow('Replace', '<input type="text" id="bam-payload-replace" class="regular-text" />');
			} else if (id === 'content.append' || id === 'content.prepend') {
				actionFields.innerHTML =
					fieldRow('Field', '<select id="bam-payload-field"><option value="content">Content</option><option value="title">Title</option></select>') +
					fieldRow('Text', '<textarea id="bam-payload-text" class="large-text" rows="3"></textarea>');
			}
		}

		var startBtn = document.getElementById('bam-start-job');
		if (startBtn && actionSelect) {
			startBtn.addEventListener('click', function () {
				var actionId = actionSelect.value;
				if (actionId.indexOf('delete.permanent') === 0 && !confirm('This action is destructive and cannot be undone. Continue?')) {
					return;
				}

				var payload = buildPayload(actionId);
				var data = {
					name: document.getElementById('bam-job-name').value,
					filter: getFilterPayload(),
					action_type: actionId,
					action_payload: payload,
					is_dry_run: document.getElementById('bam-dry-run').checked,
					batch_size: parseInt(document.getElementById('bam-batch-size').value, 10),
					processing_mode: document.getElementById('bam-processing-mode').value
				};

				startBtn.disabled = true;
				bamApi.post('jobs', data).then(function (result) {
					if (result.dry_run) {
						alert(result.message || 'Dry run complete.');
						startBtn.disabled = false;
						return;
					}
					if (typeof bamJobRunner !== 'undefined') {
						var progressBox = document.getElementById('bam-job-progress');
						if (progressBox) progressBox.classList.remove('bam-hidden');
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
			if (actionId === 'author.change') {
				return { author_id: parseInt(document.getElementById('bam-payload-author').value, 10) };
			}
			if (actionId.indexOf('category.') === 0 || actionId.indexOf('tag.') === 0) {
				var terms = document.getElementById('bam-payload-terms').value;
				return { term_ids: terms.split(',').map(function (s) { return parseInt(s.trim(), 10); }).filter(Boolean) };
			}
			if (actionId.indexOf('meta.') === 0) {
				var p = { meta_key: document.getElementById('bam-payload-meta-key').value };
				var valEl = document.getElementById('bam-payload-meta-value');
				if (valEl) p.meta_value = valEl.value;
				return p;
			}
			if (actionId === 'content.find_replace') {
				return {
					field: document.getElementById('bam-payload-field').value,
					find: document.getElementById('bam-payload-find').value,
					replace: document.getElementById('bam-payload-replace').value
				};
			}
			if (actionId === 'content.append' || actionId === 'content.prepend') {
				return {
					field: document.getElementById('bam-payload-field').value,
					text: document.getElementById('bam-payload-text').value
				};
			}
			return {};
		}
	});
})();

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

	function fieldRow(label, html) {
		return '<tr><th scope="row">' + label + '</th><td>' + html + '</td></tr>';
	}

	function safetyLabel(level, supportsUndo) {
		if (level === 'destructive') {
			return {
				icon: 'dashicons-warning',
				className: 'bam-action-description--destructive',
				text: bamAdmin.i18n.cannotUndo || 'Cannot be undone'
			};
		}
		if (supportsUndo) {
			return {
				icon: 'dashicons-yes-alt',
				className: 'bam-action-description--safe',
				text: bamAdmin.i18n.undoSupported || 'Undo supported'
			};
		}
		if (level === 'recoverable') {
			return {
				icon: 'dashicons-backup',
				className: 'bam-action-description--recoverable',
				text: bamAdmin.i18n.recoverable || 'Recoverable'
			};
		}
		return {
			icon: 'dashicons-info',
			className: '',
			text: bamAdmin.i18n.noUndo || 'Undo not available'
		};
	}

	document.addEventListener('DOMContentLoaded', function () {
		var actionSelect = document.getElementById('bam-action-type');
		var descriptionEl = document.getElementById('bam-action-description');
		var actionFields = document.getElementById('bam-action-fields');
		var startBtn = document.getElementById('bam-start-job');
		var previewBtn = document.getElementById('bam-preview-job');
		var dryRunNotice = document.getElementById('bam-dry-run-notice');

		if (actionSelect) {
			actionSelect.addEventListener('change', updateActionUI);
			updateActionUI();
		}

		var scheduleToggle = document.getElementById('bam-save-as-schedule');
		var schedulePanel = document.getElementById('bam-schedule-panel');
		if (scheduleToggle && schedulePanel) {
			scheduleToggle.addEventListener('change', function () {
				var expanded = scheduleToggle.checked;
				schedulePanel.classList.toggle('bam-hidden', !expanded);
				scheduleToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
			});
		}

		function updateActionUI() {
			if (!actionSelect) return;
			var opt = actionSelect.selectedOptions[0];
			var hasAction = opt && opt.value;

			if (startBtn) startBtn.disabled = !hasAction;
			if (previewBtn) previewBtn.disabled = !hasAction;

			if (!opt || !hasAction) {
				if (descriptionEl) descriptionEl.innerHTML = '';
				if (actionFields) actionFields.innerHTML = '';
				return;
			}

			var safety = safetyLabel(opt.dataset.safety, opt.dataset.undo === '1');
			var description = opt.dataset.description || '';

			if (descriptionEl) {
				var html = '<div class="bam-action-description__panel ' + safety.className + '">';
				html += '<p class="bam-action-description__safety"><span class="dashicons ' + safety.icon + '" aria-hidden="true"></span> ' + safety.text + '</p>';
				if (description) {
					html += '<p class="bam-action-description__text">' + description + '</p>';
				}
				html += '</div>';
				descriptionEl.innerHTML = html;
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

		function runSubmitJob(actionId, isDryRun, button) {
			var data = {
				name: document.getElementById('bam-job-name').value,
				filter: getFilterPayload(),
				action_type: actionId,
				action_payload: buildPayload(actionId),
				is_dry_run: isDryRun,
				batch_size: parseInt(document.getElementById('bam-batch-size').value, 10),
				processing_mode: document.getElementById('bam-processing-mode').value
			};

			button.disabled = true;
			bamApi.post('jobs', data).then(function (result) {
				if (result.dry_run) {
					if (dryRunNotice) {
						dryRunNotice.classList.remove('bam-hidden');
						var p = dryRunNotice.querySelector('p');
						if (p) p.textContent = result.message || bamAdmin.i18n.completed;
					} else {
						bamAlert({ message: result.message || bamAdmin.i18n.completed });
					}
					button.disabled = false;
					return;
				}

				var isBackground = data.processing_mode === 'background';
				if (!isBackground && typeof bamJobRunner !== 'undefined') {
					var progressBox = document.getElementById('bam-job-progress');
					if (progressBox) progressBox.classList.remove('bam-hidden');
					bamJobRunner.start(result.job_id);
				} else if (isBackground) {
					// Show notice with a direct link to the job - do not start the live runner.
					var jobUrl = (bamAdmin.jobsUrl || '') + '&job_id=' + result.job_id;
					var backgroundNotice = document.getElementById('bam-background-notice');
					if (backgroundNotice) {
						var linkEl = backgroundNotice.querySelector('.bam-background-notice__link');
						if (linkEl) linkEl.href = jobUrl;
						backgroundNotice.classList.remove('bam-hidden');
					} else {
						bamAlert({
							message: bamAdmin.i18n.backgroundQueued + ' <a href="' + jobUrl + '">' + (bamAdmin.i18n.backgroundJobsLink || 'View job') + '</a>'
						});
					}
				}
				button.disabled = false;
			}).catch(function () {
				bamAlert({ title: bamAdmin.i18n.errorTitle, message: bamAdmin.i18n.error });
				button.disabled = false;
			});
		}

		function submitJob(isDryRun, button) {
			if (!actionSelect || !actionSelect.value) return;

			var actionId = actionSelect.value;
			var opt = actionSelect.selectedOptions[0];

			if (!isDryRun && actionId.indexOf('delete.permanent') === 0) {
				bamConfirm({
					title: bamAdmin.i18n.confirmDeleteTitle,
					message: bamAdmin.i18n.confirmDeleteMessage,
					detail: opt && opt.dataset.description ? opt.dataset.description : '',
					okText: bamAdmin.i18n.confirmDeleteOk,
					destructive: true
				}).then(function (confirmed) {
					if (confirmed) {
						runSubmitJob(actionId, isDryRun, button);
					}
				});
				return;
			}

			runSubmitJob(actionId, isDryRun, button);
		}

		if (startBtn && actionSelect) {
			startBtn.addEventListener('click', function () {
				submitJob(document.getElementById('bam-dry-run').checked, startBtn);
			});
		}

		if (previewBtn && actionSelect) {
			previewBtn.addEventListener('click', function () {
				submitJob(true, previewBtn);
			});
		}
	});
})();

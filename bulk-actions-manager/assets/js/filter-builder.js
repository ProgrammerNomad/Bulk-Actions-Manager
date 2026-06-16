(function () {
	'use strict';

	var filterData = { filters: [], post_types: {}, statuses: {} };
	var conditionsEl, postTypeEl;

	function init() {
		conditionsEl = document.getElementById('bam-conditions');
		postTypeEl = document.getElementById('bam-post-type');
		if ( !conditionsEl || !postTypeEl ) return;

		bamApi.get('filters').then(function (data) {
			filterData = data;
			populatePostTypes();
			addCondition();
		});

		document.getElementById('bam-add-condition').addEventListener('click', addCondition);
	}

	function populatePostTypes() {
		postTypeEl.innerHTML = Object.keys(filterData.post_types).map(function (key) {
			return '<option value="' + key + '">' + filterData.post_types[key] + '</option>';
		}).join('');
	}

	function addCondition() {
		var row = document.createElement('div');
		row.className = 'bam-condition';

		var typeSelect = document.createElement('select');
		typeSelect.className = 'bam-condition-type';
		filterData.filters.forEach(function (f) {
			var opt = document.createElement('option');
			opt.value = f.type + '|' + (f.taxonomy || f.field || f.metric || '');
			opt.textContent = f.label;
			opt.dataset.filter = JSON.stringify(f);
			typeSelect.appendChild(opt);
		});

		var opSelect = document.createElement('select');
		opSelect.className = 'bam-condition-operator';

		var valueInput = document.createElement('input');
		valueInput.type = 'text';
		valueInput.className = 'bam-condition-value regular-text';
		valueInput.placeholder = 'Value';

		var removeBtn = document.createElement('button');
		removeBtn.type = 'button';
		removeBtn.className = 'button-link-delete';
		removeBtn.textContent = 'Remove';
		removeBtn.addEventListener('click', function () { row.remove(); });

		function updateOperators() {
			var selected = typeSelect.selectedOptions[0];
			var filter = JSON.parse(selected.dataset.filter);
			opSelect.innerHTML = (filter.operators || []).map(function (op) {
				return '<option value="' + op + '">' + op + '</option>';
			}).join('');
		}

		typeSelect.addEventListener('change', updateOperators);
		updateOperators();

		row.appendChild(typeSelect);
		row.appendChild(opSelect);
		row.appendChild(valueInput);
		row.appendChild(removeBtn);
		conditionsEl.appendChild(row);
	}

	window.bamFilterBuilder = {
		getFilter: function () {
			var conditions = [];
			document.querySelectorAll('.bam-condition').forEach(function (row) {
				var typeSelect = row.querySelector('.bam-condition-type');
				var filter = JSON.parse(typeSelect.selectedOptions[0].dataset.filter);
				var operator = row.querySelector('.bam-condition-operator').value;
				var value = row.querySelector('.bam-condition-value').value;

				var condition = { type: filter.type, operator: operator };

				if ( filter.taxonomy ) condition.taxonomy = filter.taxonomy;
				if ( filter.field ) condition.field = filter.field;
				if ( filter.metric ) condition.metric = filter.metric;

				if ( filter.type === 'status' ) {
					condition.value = value ? value.split(',').map(function (s) { return s.trim(); }) : ['publish'];
				} else if ( filter.type === 'author' || filter.type === 'taxonomy' ) {
					condition.value = value ? value.split(',').map(function (s) { return parseInt(s, 10); }) : [];
				} else if ( filter.type === 'meta_value' ) {
					var parts = value.split('=');
					condition.key = parts[0] || '';
					condition.value = parts[1] || '';
				} else if ( filter.type === 'meta' ) {
					condition.key = value;
				} else if ( filter.type === 'date' && operator === 'between' ) {
					var dates = value.split(',');
					condition.value = [ dates[0] || '', dates[1] || '' ];
				} else {
					condition.value = value;
				}

				conditions.push(condition);
			});

			return {
				post_type: [ postTypeEl.value ],
				logic: 'AND',
				conditions: conditions
			};
		}
	};

	document.addEventListener('DOMContentLoaded', init);
})();

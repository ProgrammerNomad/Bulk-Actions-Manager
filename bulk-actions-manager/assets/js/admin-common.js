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
})();

/**
 * Copyright (C) 2013 Eoko
 *
 * This file is part of Opence.
 *
 * Opence is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Opence is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Opence. If not, see <http://www.gnu.org/licenses/gpl.txt>.
 *
 * @copyright Copyright (C) 2013 Eoko
 * @licence http://www.gnu.org/licenses/gpl.txt GPLv3
 * @author Éric Ortega <eric@eoko.fr>
 */

(function(Ext) {
/**
 *
 * @since 2013-06-24 20:02
 */
Ext.define('Eoze.data.proxy.mixin.AjaxHttpCache', {

	httpCacheEnabled: true

	,hasCacheExpireEvent: true

	,initHttpCache: function(config) {
		var createInterceptor = Ext.Function.createInterceptor,
			createSequence = Ext.Function.createSequence;

		this.noCache = this.nocache = false;

		this.verifiedCaches = {};

		Ext.apply(this, {
			doRequest: function(operation, callback, scope) {
				var writer  = this.getWriter(),
					request = this.buildRequest(operation),
					headers = Ext.apply({}, this.headers),
					key;

				if (operation.allowWrite()) {
					request = writer.write(request);
				}

				if (operation.action === 'read' && this.httpCacheEnabled) {

					key = this.hashOperation(operation);

					headers['Cache-Control'] = (!operation.invalidateHttpCache && this.verifiedCaches[key])
						? 'max-age'
						: 'max-age=0';
				}

				Ext.apply(request, {
					binary        : this.binary,
					headers       : headers,
					timeout       : this.timeout,
					scope         : this,
					callback      : this.createRequestCallback(request, operation, callback, scope),
					method        : this.getMethod(request),
					disableCaching: false // explicitly set it to false, ServerProxy handles caching
				});

				Ext.Ajax.request(request);

				return request;
			}

			,processResponse: createSequence(
				this.processResponse,
				function(success, operation, request, response) {
					var key = this.hashOperation(operation);
					if (operation.action === 'read') {
						this.verifiedCaches[key] = true;
					}
				}
			)
		});

		// TODO kepler specific should be moved out
		if (this.keplerReloadEvent) {
			eo.Kepler.on(this.keplerReloadEvent, function() {
				this.verifiedCaches = {};
				this.fireEvent('cacheexpire', this);
			}, this);
		}
	}

	,hashOperation: function(operation) {
		if (operation._proxyUid) {
			return operation._proxyUid;
		}

		var request = operation.request,
			params = operation.request.params,
			normalizedKeys = Ext.Object.getKeys(params).sort(),
			normalizedParams = Ext.copyTo({}, params, normalizedKeys),
			proxyUid = request.url + '::' + Ext.encode(normalizedParams);

		return operation._proxyUid = proxyUid;
	}

});
})(Ext4);

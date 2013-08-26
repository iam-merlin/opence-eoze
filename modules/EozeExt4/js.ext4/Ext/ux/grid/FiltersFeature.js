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

/**
 * Loader for overrides of Filters feature.
 *
 * Also:
 *
 * - Adds support for {@link Eoze.Ext.data.Types#DAYDATE} filter.
 *
 * @since 2013-05-17 16:15
 */
Ext4.define('Eoze.Ext.ux.grid.FiltersFeature', {
	override: 'Ext.ux.grid.FiltersFeature'

	,requires: [
		'Eoze.Ext.ux.grid.filter.DateFilter',
		'Eoze.Ext.ux.grid.filter.Filter.EmptyValues'
	]

	/**
	 * Adds support for {@link Eoze.Ext.data.Types#DAYDATE}.
	 */
	,getFilterClass: function(type) {
		switch (type) {
			case 'daydate':
				type = 'date';
				break;
		}
		return this.callParent([type]);
	}
});
<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2015 webtrees development team
 * Copyright (C) 2015 JustCarmen
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace JustCarmen\WebtreesAddOns\FancyTreeView\Schema;

use Fisharebest\Webtrees\Database;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Schema\MigrationInterface;
use Fisharebest\Webtrees\Tree;

/**
 * Upgrade the database schema from version 0 to version 1.
 */
class Migration0 implements MigrationInterface {

	/** {@inheritDoc} */
	public function upgrade() {
		// add key 'LINK' to FTV_SETTINGS
		// change options to multidimensional array with array key = tree id.
		$module_settings = 'FTV_SETTINGS';
		$ftv_settings = Database::prepare(
				"SELECT setting_value FROM `##module_setting` WHERE setting_name=?"
			)->execute(array($module_settings))->fetchOne();

		$settings = unserialize($ftv_settings);
		if (!empty($settings)) {
			foreach ($settings as $setting) {
				if (!array_key_exists('LINK', $setting)) {
					$setting['LINK'] = /* I18N: %s is the surname of the root individual */ I18N::translate('Descendants of the %s family', $setting['SURNAME']);
					$new_settings[] = $setting;
				}
			}
			if (isset($new_settings)) {
				Database::prepare(
					"UPDATE `##module_setting` SET setting_value=? WHERE setting_name=?"
				)->execute(array(serialize($new_settings), $module_settings));
			}
			unset($new_settings);
		}

		$module_options = 'FTV_OPTIONS';
		$ftv_options = Database::prepare(
				"SELECT setting_value FROM `##module_setting` WHERE setting_name=?"
			)->execute(array($module_options))->fetchOne();

		$options = unserialize($ftv_options);
		if (!empty($options)) {
			$show_places = array_key_exists('SHOW_PLACES', $options) ? $options['SHOW_PLACES'] : '1';
			$country = array_key_exists('COUNTRY', $options) ? $options['COUNTRY'] : '';
			$show_occu = array_key_exists('SHOW_OCCU', $options) ? $options['SHOW_OCCU'] : '1';

			foreach (Tree::getAll() as $tree) {
				$new_options[$tree->getTreeId()] = array(
					'SHOW_PLACES'	 => $show_places,
					'COUNTRY'		 => $country,
					'SHOW_OCCU'		 => $show_occu
				);
			}
			if (isset($new_options)) {
				Database::prepare(
					"UPDATE `##module_setting` SET setting_value=? WHERE setting_name=?"
				)->execute(array(serialize($new_options), $module_options));
			}
			unset($new_options);
		}
	}

}

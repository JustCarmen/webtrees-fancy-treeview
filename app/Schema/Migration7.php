<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2017 webtrees development team
 * Copyright (C) 2017 JustCarmen
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
namespace JustCarmen\WebtreesAddOns\FancyTreeview\Schema;

use Fisharebest\Webtrees\Database;
use Fisharebest\Webtrees\Schema\MigrationInterface;

/**
 * Upgrade the database schema from version 7 to version 8.
 */
class Migration7 implements MigrationInterface {

	/** {@inheritDoc} */
	public function upgrade() {

		$module_options = 'FTV_OPTIONS';
		$ftv_options = Database::prepare(
				"SELECT setting_value FROM `##module_setting` WHERE setting_name=?"
			)->execute(array($module_options))->fetchOne();

		$options = unserialize($ftv_options);
		if (!empty($options)) {
			foreach ($options as $option) {
				foreach ($option as $key => $value) {
					if ($key == 'USE_FTV_THUMBS') {
						$option['RESIZE_THUMBS'] = $value;
						unset($option[$key]);
					}
					if ($key == 'COUNTRY') {
						unset($option[$key]);
					}
				}
				$option['USE_GEDCOM_PLACES'] = '1';
				$option['THUMB_RESIZE_FORMAT'] = '2';
				$new_options[] = $option;
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

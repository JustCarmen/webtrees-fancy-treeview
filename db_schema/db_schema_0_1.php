<?php
// Update the Fancy Tree View module database schema from version 0 to 1
// - add key 'LINK' to FTV_SETTINGS
// - Change options to multidimeninal array with array key = tree id.
//
// The script should assume that it can be interrupted at
// any point, and be able to continue by re-running the script.
// Fatal errors, however, should be allowed to throw exceptions,
// which will be caught by the framework.
// It shouldn't do anything that might take more than a few
// seconds, for systems with low timeout values.
//
// webtrees: Web based Family History software
// Copyright (C) 2014 webtrees development team.
// Copyright (C) 2014 JustCarmen.
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA

$module_settings = 'FTV_SETTINGS';
$ftv_settings=WT_DB::prepare(
	"SELECT setting_value FROM `##module_setting` WHERE setting_name=?"
)->execute(array($module_settings))->fetchOne();

$settings = unserialize($ftv_settings);
if(!empty($settings)) {
	foreach ($settings as $setting) {
		if(!array_key_exists('LINK', $setting)) {
			$setting['LINK'] = /* I18N: %s is the surname of the root individual */ WT_I18N::translate('Descendants of the %s family', $setting['SURNAME']);
			$new_settings[] = $setting;
		}
	}
	if (isset($new_settings)) {
		WT_DB::prepare(
			"UPDATE `##module_setting` SET setting_value=? WHERE setting_name=?"
		)->execute(array(serialize($new_settings), $module_settings));
	}
	unset($new_settings);
}

$module_options = 'FTV_OPTIONS';
$ftv_options=WT_DB::prepare(
	"SELECT setting_value FROM `##module_setting` WHERE setting_name=?"
)->execute(array($module_options))->fetchOne();

$options = unserialize($ftv_options);
if(!empty($options)) {
	$show_places = array_key_exists('SHOW_PLACES', $options) ? $options['SHOW_PLACES'] : '1';
	$country = array_key_exists('COUNTRY', $options) ? $options['COUNTRY'] : '';
	$show_occu = array_key_exists('SHOW_OCCU', $options) ? $options['SHOW_OCCU'] : '1';
	
	foreach (WT_Tree::getAll() as $tree) {
		$new_options[$tree->tree_id] = array(
			'SHOW_PLACES' 	=> $show_places,
			'COUNTRY' 		=> $country,
			'SHOW_OCCU'		=> $show_occu
		);
	}
	if(isset($new_options)) {
		WT_DB::prepare(
			"UPDATE `##module_setting` SET setting_value=? WHERE setting_name=?"
		)->execute(array(serialize($new_options), $module_options));
	}
	unset($new_options);
}

// Update the version to indicate success
WT_Site::setPreference($schema_name, $next_version);

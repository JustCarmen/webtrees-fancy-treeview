<?php
// Update the Fancy Tree View module database schema from version 1 to 2
// - remove key 'LINK' from FTV_SETTINGS
//
// The script should assume that it can be interrupted at
// any point, and be able to continue by re-running the script.
// Fatal errors, however, should be allowed to throw exceptions,
// which will be caught by the framework.
// It shouldn't do anything that might take more than a few
// seconds, for systems with low timeout values.
//
// webtrees: Web based Family History software
// Copyright (C) 2013 webtrees development team.
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
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

if (!defined('WT_WEBTREES')) {
	header('HTTP/1.0 403 Forbidden');
	exit;
}

$settings = unserialize(get_module_setting('fancy_treeview', 'FTV_SETTINGS'));
if(!empty($settings)) {
	foreach ($settings as $setting) {
		if(array_key_exists('LINK', $setting)) {
			unset($setting['LINK']);
			$new_settings[] = $setting;
		}
	}
	if(isset($new_settings)) set_module_setting('fancy_treeview', 'FTV_SETTINGS',  serialize($new_settings));
	unset($new_settings);
}

$settings = unserialize(get_module_setting('fancy_treeview', 'FTV_SETTINGS'));
if(!empty($settings)) {
	foreach ($settings as $setting) {		
		if(!array_key_exists('DISPLAY_NAME', $setting)) {
			$setting['DISPLAY_NAME'] = $setting['SURNAME'];	
			$new_settings[] = $setting;
		}
	}
	if(isset($new_settings)) set_module_setting('fancy_treeview', 'FTV_SETTINGS',  serialize($new_settings));
	unset($new_settings);
}


$options = unserialize(get_module_setting('fancy_treeview', 'FTV_OPTIONS'));
if(!empty($options)) {
	foreach($options as $option) {		
		$option['USE_FULLNAME'] = '0';
		$new_options[] = $option;
	}
	set_module_setting('fancy_treeview', 'FTV_OPTIONS',  serialize($new_options));
	unset($new_options);
}
// Update the version to indicate success
WT_Site::preference($schema_name, $next_version);

<?php
// Update the Fancy Tree View module database schema from version 7 to 8
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

if (!defined('WT_WEBTREES')) {
	header('HTTP/1.0 403 Forbidden');
	exit;
}

$options = unserialize(get_module_setting('fancy_treeview', 'FTV_OPTIONS'));
if(!empty($options)) {
	foreach($options as $tree => $option) {
		foreach($option as $key => $value){
			$new_option = array();
			if($key == 'USE_FTV_THUMBS'){
				$new_option['RESIZE_THUMBS'] = $value;
			}else{
				if($key !== 'COUNTRY') {
					$new_option[$key] = $value;
				}
			}
			$new_option['USE_GEDCOM_PLACES'] = '1';
			$new_option['THUMB_RESIZE_FORMAT'] = '2';
		}		
		$new_options[$tree] = $new_option;
	}
	set_module_setting('fancy_treeview', 'FTV_OPTIONS',  serialize($new_options));
	unset($new_options);
}
// Update the version to indicate success
WT_Site::preference($schema_name, $next_version);

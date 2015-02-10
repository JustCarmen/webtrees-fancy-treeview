<?php
namespace Fisharebest\Webtrees;

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

use Zend_Session;

Zend_Session::writeClose();

$filename = WT_DATA_DIR . '/fancy_treeview_tmp.txt';
$content = Filter::post('pdfContent');

// make our datafile if it does not exist.
if (!file_exists($filename)) {
	$handle = fopen($filename, 'w');
	@fclose($handle);
	@chmod($filename, 0644);
}

// Let's make sure the file exists and is writable first.
if (is_writable($filename)) {

	if (!$handle = @fopen($filename, 'w')) {
		exit;
	}

	// Write the pdfContent to our data.txt file.
	if (@fwrite($handle, $content) === FALSE) {
		exit;
	}

	@fclose($handle);
}
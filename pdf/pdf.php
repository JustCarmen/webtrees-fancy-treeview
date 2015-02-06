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

use DOMPDF;
use Font_Metrics;
use Zend_Loader_Autoloader;

require_once(WT_MODULES_DIR . $this->getName() . '/pdf/dompdf/dompdf_config.inc.php');
Zend_Loader_Autoloader::getInstance()->pushAutoloader('DOMPDF_autoload', '');

$filename = WT_DATA_DIR . '/fancy_treeview_tmp.txt';

$html = '<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<link type="text/css" href="style.css" rel="stylesheet" />
	</head>
	<body>' . @file_get_contents($filename) . '</body>
  </html>';

$dompdf = new DOMPDF();
$dompdf->set_base_path(WT_MODULES_DIR . $this->getName() . '/pdf/'); // works only for the template file (set absolute links for images and all other links)
$dompdf->set_paper('a3', 'portrait');
$dompdf->load_html($html);
$dompdf->render();

// create the page
$canvas = $dompdf->get_canvas();
$font = Font_Metrics::get_font("DejaVu Sans", "normal");
$headertext_left = WT_BASE_URL;
$headertext_right = I18N::translate('Page') . " {PAGE_NUM} " . I18N::translate('of') . " {PAGE_COUNT} ";
$headerpos_right = $canvas->get_width() - $canvas->get_text_width($headertext_right, $font, 9, 0) + 100;

$canvas->page_text(20, 10, $headertext_left, $font, 9, array(0, 0, 0));
$canvas->page_text($headerpos_right, 10, $headertext_right, $font, 9, array(0, 0, 0));

// pdf output
$dompdf->stream(Filter::get('title') . '.pdf');

// remove the temporary text file
@unlink(WT_DATA_DIR . '/fancy_treeview_tmp.txt');

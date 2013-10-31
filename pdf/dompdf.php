<?php

if (!defined('WT_WEBTREES')) {
	header('HTTP/1.0 403 Forbidden');
	exit;
}

require_once(WT_MODULES_DIR.$this->getName().'/pdf/dompdf_0.5.2/dompdf_config.inc.php');
Zend_Loader_Autoloader::getInstance()->pushAutoloader('DOMPDF_autoload','');

$filename = WT_MODULES_DIR.$this->getName().'/pdf/tmp/data.txt';

$html =
  '<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<link type="text/css" href="themes/base/print.css" rel="stylesheet" />
	</head>
	<body>'.file_get_contents($filename).'</body>
  </html>';
  
  

$dompdf = new DOMPDF();
$dompdf->set_base_path(WT_MODULES_DIR.$this->getName().'/'); // works only on the template file (set absolute links for images and all other links)
$dompdf->set_paper('a3', 'portrait');
$dompdf->load_html($html);
$dompdf->render();

$canvas				= $dompdf->get_canvas();
$font 				= Font_Metrics::get_font("helvetica", "normal");
$headertext_left 	= WT_SERVER_NAME.substr(WT_SCRIPT_PATH, 0, -1);
$headertext_right 	= WT_I18N::translate('Page')." {PAGE_NUM} ".WT_I18N::translate('of')." {PAGE_COUNT} ";
$headerpos_right	= $canvas->get_width() - $canvas->get_text_width($headertext_right, $font, 9, 0) + 100;

$canvas->page_text(20, 10, $headertext_left, $font, 9, array(0,0,0));
$canvas->page_text($headerpos_right, 10, $headertext_right, $font, 9, array(0,0,0));
$dompdf->stream(WT_Filter::get('title'),array('Attachment'=>0));	
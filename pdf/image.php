<?php

if (!defined('WT_WEBTREES')) {
	header('HTTP/1.0 403 Forbidden');
	exit;
}

Zend_Session::writeClose();

$data = $_POST['image'];
$filename = WT_MODULES_DIR.$this->getName().'/pdf/tmp/'.$_POST['filename'];

if (!file_exists($filename)) {
	// check if the pdf/tmp directory exists on the server, otherwise make it.
	if (!is_dir(dirname($filename))) {
		mkdir(dirname($filename), WT_PERM_EXE);
	}

	// strip of the unneccessary parts of the datastring.
	list($type, $data) = explode(';', $data);
	list(, $data)      = explode(',', $data);
	$data = base64_decode($data);

	// upload the images to the tmp direcotry
	file_put_contents($filename, $data);
	@chmod($filename, WT_PERM_FILE);
}

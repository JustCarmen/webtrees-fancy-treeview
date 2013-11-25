<?php

if (!defined('WT_WEBTREES')) {
	header('HTTP/1.0 403 Forbidden');
	exit;
}

Zend_Session::writeClose();

$filename = WT_MODULES_DIR.$this->getName().'/pdf/tmp/data.txt';
$content = $_POST['pdfContent'];

// check if the pdf/tmp directory exists on the server, otherwise make it.
if (!is_dir(dirname($filename))) {
	mkdir(dirname($filename), WT_PERM_EXE);
}

// make our datafile if it does not exist.
if(!file_exists($filename)) {
	$handle = fopen($filename, 'w');
	fclose($handle);
	@chmod($filename, WT_PERM_FILE);
}

// Let's make sure the file exists and is writable first.
if (is_writable($filename)) {

    if (!$handle = fopen($filename, 'w')) {
         exit;
    }

    // Write the pdfContent to our data.txt file.
    if (fwrite($handle, $content) === FALSE) {
        exit;
    }

    fclose($handle);
}
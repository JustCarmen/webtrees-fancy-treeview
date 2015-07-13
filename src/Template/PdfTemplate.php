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
namespace JustCarmen\WebtreesAddOns\FancyTreeview\Template;

use Fisharebest\Webtrees\File;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\I18N;
use JustCarmen\WebtreesAddOns\FancyTreeview\FancyTreeviewClass;
use mPDF;

class PdfTemplate extends FancyTreeviewClass {
	
	public function pageBody() {
		global $WT_TREE;
		
		require_once(WT_MODULES_DIR . $this->getName() . '/packages/mpdf60/mpdf.php');
		
		$tmpfile = WT_DATA_DIR . '/fancy_treeview_tmp.txt';
		if (is_dir(WT_DATA_DIR) && is_readable($tmpfile)) {
			$stylesheet = file_get_contents($this->directory . '/css/pdf/style.css');
			$stylesheet_rtl = file_get_contents($this->directory . '/css/pdf/style-rtl.css');
			$html = file_get_contents($tmpfile);

			$header = '<header>=== ' . $WT_TREE->getTitleHtml() . ' ===</header>';
			$footer = '<footer>' .
				'<div class="left">' . WT_BASE_URL . '</div>' .
				'<div class="right">{PAGENO}</div>' .
				'</footer>';

			$mpdf = new mPDF();

			$mpdf->simpleTables = true;
			$mpdf->shrink_tables_to_fit = 1;

			$mpdf->autoScriptToLang = true;
			if (I18N::direction() === 'rtl') {
				$mpdf->SetDirectionality('rtl');
			}

			if (I18N::direction() === 'rtl') {
				$mpdf->WriteHTML($stylesheet_rtl, 1);
			} else {
				$mpdf->WriteHTML($stylesheet, 1);
			}

			$mpdf->setAutoTopMargin = 'stretch';
			$mpdf->setAutoBottomMargin = 'stretch';
			$mpdf->autoMarginPadding = 5;

			$mpdf->SetHTMLHeader($header);
			$mpdf->setHTMLFooter($footer);

			$html_chunks = explode("\n", $html);
			$chunks = count($html_chunks);
			$i = 1;
			foreach ($html_chunks as $html_chunk) {
				if ($i === 1) {
					$mpdf->WriteHTML($html_chunk, 2, true, false);
				} elseif ($i === $chunks) {
					$mpdf->WriteHTML($html_chunk, 2, false, false);
				} else {
					$mpdf->WriteHTML($html_chunk, 2, false, true);
				}
				$i++;
			}

			$mpdf->Output(Filter::get('title') . '.pdf', 'D');

			// remove the temporary files
			File::delete($tmpfile);
			foreach (glob(WT_DATA_DIR . 'ftv*.*') as $image) {
				File::delete($image);
			}
		} else {
			echo $this->addMessage('alert', 'danger', false, I18N::translate('Error: the pdf file could not be generated.'));
		}
	}
	
	public function pageData() {
		$filename = WT_DATA_DIR . '/fancy_treeview_tmp.txt';
		$content = Filter::post('pdfContent');

		// make our datafile if it does not exist.
		if (!file_exists($filename)) {
			$handle = fopen($filename, 'w');
			fclose($handle);
			chmod($filename, 0644);
		}

		// Let's make sure the file exists and is writable first.
		if (is_writable($filename)) {
			if (!$handle = @fopen($filename, 'w')) {
				exit;
			}

			// Write the pdfContent to our data.txt file.
			if (fwrite($handle, $content) === FALSE) {
				exit;
			}

			fclose($handle);
		}
	}
}
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
use Fisharebest\Webtrees\User;
use JustCarmen\WebtreesAddOns\FancyTreeview\FancyTreeviewClass;
use mPDF;

class PdfTemplate extends FancyTreeviewClass {

	public function pageBody() {
		$cache_dir = WT_DATA_DIR . 'ftv_cache/';

		define("_JPGRAPH_PATH", $cache_dir);
		define("_MPDF_TEMP_PATH", $cache_dir);
		define('_MPDF_TTFONTDATAPATH', $cache_dir);

		require_once(WT_MODULES_DIR . $this->getName() . '/packages/mpdf60/mpdf.php');

		$tmpfile = $cache_dir . 'fancy-treeview-tmp.txt';
		if (file_exists($cache_dir) && is_readable($tmpfile)) {
			$stylesheet = file_get_contents($this->directory . '/css/pdf/style.css');
			$stylesheet_rtl = file_get_contents($this->directory . '/css/pdf/style-rtl.css');
			$html = file_get_contents($tmpfile);

			$header = '<header>=== ' . $this->tree->getTitleHtml() . ' ===</header>';
			$footer = '<footer>' .
				'<table><tr>' .
				'<td class="left">' . WT_BASE_URL . '</td>' .
				'<td class="center">{DATE d-m-Y}</td>' .
				'<td class="right">{PAGENO}</td>' .
				'</tr></table>' .
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

			$admin = User::find($this->tree->getPreference('WEBMASTER_USER_ID'))->getRealName();

			$mpdf->setCreator($this->getTitle() . ' - a webtrees module by justcarmen.nl');
			$mpdf->SetTitle(Filter::get('title'));
			$mpdf->setAuthor($admin);

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
			
			$index = '
				<pagebreak type="next-odd" />
				<h2>' . I18N::translate('Index') . '</h2>
				<columns column-count="2" column-gap="5" />
				<indexinsert usedivletters="on" links="on" collation="' . WT_LOCALE . '.utf8" collationgroup="' . I18N::collation() . '" />';
			$mpdf->writeHTML($index);
			$mpdf->Output(Filter::get('title') . '.pdf', 'D');
		} else {
			echo $this->addMessage('alert', 'danger', false, I18N::translate('Error: the pdf file could not be generated.'));
		}
	}

	public function pageData() {
		$path = WT_DATA_DIR . '/ftv_cache/';
		if (!file_exists($path)) {
			File::mkdir($path);
		}
		$filename = $path . 'fancy-treeview-tmp.txt';
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

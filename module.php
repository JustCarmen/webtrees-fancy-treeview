<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2018 JustCarmen (http://justcarmen.nl)
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
namespace JustCarmen\WebtreesAddOns\FancyTreeview;

use Composer\Autoload\ClassLoader;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Module;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Theme;
use Fisharebest\Webtrees\Tree;
use JustCarmen\WebtreesAddOns\FancyTreeview\Template\AdminTemplate;
use JustCarmen\WebtreesAddOns\FancyTreeview\Template\PageTemplate;
use JustCarmen\WebtreesAddOns\FancyTreeviewPdf\FancyTreeviewPdfClass;

class FancyTreeviewModule extends AbstractModule implements ModuleConfigInterface, ModuleTabInterface, ModuleMenuInterface {

	const CUSTOM_VERSION			 = '1.7.11';
	const CUSTOM_WEBSITE			 = 'http://www.justcarmen.nl/fancy-modules/fancy-treeview/';

	/** @var string location of the fancy treeview module files */
	var $directory;

	/** @var string module action */
	var $action;

	/** {@inheritdoc} */
	public function __construct() {
		parent::__construct('fancy_treeview');

		$this->directory = WT_MODULES_DIR . $this->getName();
		$this->action	 = Filter::get('mod_action');

		// register the namespaces
		$loader = new ClassLoader();
		$loader->addPsr4('JustCarmen\\WebtreesAddOns\\FancyTreeview\\', $this->directory . '/app');
		$loader->register();
	}

	/**
	 * Get the module class.
	 *
	 * Class functions are called with $this inside the source directory.
	 */
	protected function module() {
		return new FancyTreeviewClass;
	}

	public function getName() {
		return 'fancy_treeview';
	}

	/** {@inheritdoc} */
	public function getTitle() {
		return /* I18N: Name of the module */ I18N::translate('Fancy Treeview');
	}

	public function getTabTitle() {
		return /* I18N: Title used in the tab panel */ I18N::translate('Descendants');
	}

	/** {@inheritdoc} */
	public function getDescription() {
		return /* I18N: Description of the module */ I18N::translate('A Fancy overview of the descendants of one family(branch) in a narrative way.');
	}

	/** {@inheritdoc} */
	public function getConfigLink() {
		return 'module.php?mod=' . $this->getName() . '&amp;mod_action=admin_config';
	}

	/** {@inheritdoc} */
	public function defaultMenuOrder() {
		return 10;
	}

	/** {@inheritdoc} */
	public function defaultTabOrder() {
		return 25;
	}

	/** {@inheritdoc} */
	public function hasTabContent() {
		if ($this->module()->options('ftv_tab')) {
			return true;
		} else {
			return false;
		}
	}

	/** {@inheritdoc} */
	public function isGrayedOut() {
		return false;
	}

	/** {@inheritdoc} */
	public function canLoadAjax() {
		return !Auth::isSearchEngine(); // Search engines cannot use AJAX
	}

	/** {@inheritdoc} */
	public function modAction($mod_action) {
		switch ($mod_action) {
			case 'admin_config':
				$template = new AdminTemplate;
				return $template->pageContent();

			case 'admin_search':
				// new settings
				$surname = Filter::post('SURNAME');
				$pid	 = Filter::post('PID');
				if ($surname) {
					$soundex_std = Filter::postBool('soundex_std');
					$soundex_dm	 = Filter::postBool('soundex_dm');

					$indis = $this->module()->indisArray($surname, $soundex_std, $soundex_dm);
					usort($indis, 'Fisharebest\Webtrees\Individual::compareBirthDate');

					if (isset($indis) && count($indis) > 0) {
						$pid = $indis[0]->getXref();
					} else {
						$result['error'] = I18N::translate('Error: The surname you entered doesn’t exist in this tree.');
					}
				}

				if (isset($pid)) {
					$FTV_SETTINGS = unserialize($this->getSetting('FTV_SETTINGS'));
					if ($this->module()->searchArray($this->module()->searchArray($FTV_SETTINGS, 'TREE', Filter::getInteger('tree')), 'PID', $pid)) {
						if ($surname) {
							$result['error'] = I18N::translate('Error: The root person belonging to this surname already exists');
						} else {
							$result['error'] = I18N::translate('Error: A root person with ID %s already exists', $pid);
						}
					} else {
						$record = Individual::getInstance($pid, $this->tree());
						if ($record) {
							$root	 = $record->getFullName() . ' (' . $record->getLifeSpan() . ')';
							$title	 = $this->module()->getPageLink($pid);

							$result = [
								'access_level'	 => '2', // default access level = show to visitors
								'pid'			 => $pid,
								'root'			 => $root,
								'sort'			 => count($this->module()->searchArray($FTV_SETTINGS, 'TREE', Filter::getInteger('tree'))) + 1,
								'surname'		 => $this->module()->getSurname($pid),
								'title'			 => $title,
								'tree'			 => Filter::getInteger('tree')
							];
						} else {
							if (empty($result['error'])) {
								$result['error'] = I18N::translate('Error: A person with ID %s does not exist in this tree', $pid);
							}
						}
					}
				}
				echo json_encode($result);
				break;

			case 'admin_add':
				$FTV_SETTINGS		 = unserialize($this->getSetting('FTV_SETTINGS'));
				$NEW_FTV_SETTINGS	 = $FTV_SETTINGS;
				$NEW_FTV_SETTINGS[]	 = [
					'TREE'			 => Filter::getInteger('tree'),
					'SURNAME'		 => Filter::post('surname'),
					'PID'			 => Filter::post('pid'),
					'ACCESS_LEVEL'	 => Filter::postInteger('access_level'),
					'SORT'			 => Filter::postInteger('sort'),
				];
				$this->setSetting('FTV_SETTINGS', serialize(array_values($NEW_FTV_SETTINGS)));
				Log::addConfigurationLog($this->getTitle() . ' config updated');
				break;

			case 'admin_update':
				$FTV_SETTINGS = unserialize($this->getSetting('FTV_SETTINGS'));

				$new_surname		 = Filter::postArray('surname');
				$new_access_level	 = Filter::postArray('access_level');
				$new_sort			 = Filter::postArray('sort');

				foreach ($new_surname as $key => $new_surname) {
					$FTV_SETTINGS[$key]['SURNAME'] = $new_surname;
				}

				foreach ($new_access_level as $key => $new_access_level) {
					$FTV_SETTINGS[$key]['ACCESS_LEVEL'] = $new_access_level;
				}

				foreach ($new_sort as $key => $new_sort) {
					$FTV_SETTINGS[$key]['SORT'] = $new_sort;
				}

				$NEW_FTV_SETTINGS = $this->module()->sortArray($FTV_SETTINGS, 'SORT');
				$this->setSetting('FTV_SETTINGS', serialize($NEW_FTV_SETTINGS));
				break;

			case 'admin_save':
				$FTV_OPTIONS							 = unserialize($this->getSetting('FTV_OPTIONS'));
				$FTV_OPTIONS[Filter::getInteger('tree')] = Filter::postArray('NEW_FTV_OPTIONS');
				$this->setSetting('FTV_OPTIONS', serialize($FTV_OPTIONS));
				Log::addConfigurationLog($this->getTitle() . ' config updated');

				// the cache has to be recreated because the image options could have been changed
				$this->module()->emptyCache();
				break;

			case 'admin_copy':
				$FTV_OPTIONS = unserialize($this->getSetting('FTV_OPTIONS'));
				foreach (Tree::getAll() as $tree) {
					$FTV_OPTIONS[$tree->getTreeId()] = Filter::postArray('NEW_FTV_OPTIONS');
				}
				$this->setSetting('FTV_OPTIONS', serialize($FTV_OPTIONS));
				Log::addConfigurationLog($this->getTitle() . ' config saved and copied to all trees');

				// the cache has to be recreated because the image options could have been changed
				$this->module()->emptyCache();
				break;

			case 'admin_reset':
				$FTV_OPTIONS = unserialize($this->getSetting('FTV_OPTIONS'));
				unset($FTV_OPTIONS[Filter::getInteger('tree')]);
				$this->setSetting('FTV_OPTIONS', serialize($FTV_OPTIONS));
				Log::addConfigurationLog($this->getTitle() . ' options set to default');
				break;

			case 'admin_delete':
				$FTV_SETTINGS = unserialize($this->getSetting('FTV_SETTINGS'));
				unset($FTV_SETTINGS[Filter::getInteger('key')]);
				$this->setSetting('FTV_SETTINGS', serialize($FTV_SETTINGS));
				Log::addConfigurationLog($this->getTitle() . ' item deleted');
				break;

			case 'page':
				$template = new PageTemplate;
				return $template->pageContent();

			// See mediafirewall.php
			case 'thumbnail':
				$mid			 = Filter::get('mid', WT_REGEX_XREF);
				$media			 = Media::getInstance($mid, $this->tree());
				$mimetype		 = $media->mimeType();
				$cache_filename	 = $this->module()->cacheFileName($media);
				$filetime		 = filemtime($cache_filename);
				$filetimeHeader	 = gmdate('D, d M Y H:i:s', $filetime) . ' GMT';
				$expireOffset	 = 3600 * 24 * 7; // tell browser to cache this image for 7 days
				$expireHeader	 = gmdate('D, d M Y H:i:s', WT_TIMESTAMP + $expireOffset) . ' GMT';
				$etag			 = $media->getEtag();
				$filesize		 = filesize($cache_filename);

				// parse IF_MODIFIED_SINCE header from client
				$if_modified_since = 'x';
				if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
					$if_modified_since = preg_replace('/;.*$/', '', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
				}

				// parse IF_NONE_MATCH header from client
				$if_none_match = 'x';
				if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
					$if_none_match = str_replace('"', '', $_SERVER['HTTP_IF_NONE_MATCH']);
				}

				// add caching headers.  allow browser to cache file, but not proxy
				header('Last-Modified: ' . $filetimeHeader);
				header('ETag: "' . $etag . '"');
				header('Expires: ' . $expireHeader);
				header('Cache-Control: max-age=' . $expireOffset . ', s-maxage=0, proxy-revalidate');

				// if this file is already in the user’s cache, don’t resend it
				// first check if the if_modified_since param matches
				if ($if_modified_since === $filetimeHeader) {
					// then check if the etag matches
					if ($if_none_match === $etag) {
						http_response_code(304);
						return;
					}
				}

				// send headers for the image
				header('Content-Type: ' . $mimetype);
				header('Content-Disposition: filename="' . basename($cache_filename) . '"');
				header('Content-Length: ' . $filesize);

				// Some servers disable fpassthru() and readfile()
				if (function_exists('readfile')) {
					readfile($cache_filename);
				} else {
					$fp = fopen($cache_filename, 'rb');
					if (function_exists('fpassthru')) {
						fpassthru($fp);
					} else {
						while (!feof($fp)) {
							echo fread($fp, 65536);
						}
					}
					fclose($fp);
				}
				break;

			default:
				http_response_code(404);
				break;
		}
	}

	/** {@inheritdoc} */
	public function getTabContent() {
		global $controller;

		$html = '<script src="' . WT_STATIC_URL . $this->directory . '/js/tab.js" defer="defer"></script>';
		if ($this->pdf()) {
			$html .= $this->pdf()->includeJs($controller, true);
		}
		$html .= '<div id="fancy_treeview-page" class="fancy_treeview-tab">';
		if ($this->pdf()) {
			$html .= $this->pdf()->getPdfWaitingMessage();
			if ($this->pdf()->tab()) {
				$html .= $this->pdf()->getPdfIcon();
			}
		}
		$html .= '<ol id="fancy_treeview">' . $this->module()->printTabContent($controller->record->getXref()) . '</ol>';
		$html .= '</div>';
		return $html;
	}

	/** {@inheritdoc} */
	public function getPreLoadContent() {
		return false;
	}

	/** {@inheritdoc} */
	public function getMenu() {
		$this->getPreLoadModuleContent();
		return $this->menuFancyTreeview();
	}

	public function getPreloadModuleContent() {
		global $controller;

		if (Theme::theme()->themeId() === '_administration') {
			return null;
		}

		// load the module stylesheets
		echo $this->module()->getStylesheet();

		// add javascript files and scripts
		$this->module()->includeJs($controller, 'menu');

		if (WT_SCRIPT_NAME === 'individual.php') {
			$this->module()->includeJs($controller, 'tab');
		}
	}

	public function menuFancyTreeview() {

		// Function has already run. We need this to re-use the menu in extensions
		static $menu;
		if ($menu !== null && count($menu->getSubmenus()) > 0) {
			return $menu;
		}

		$FTV_SETTINGS = unserialize($this->getSetting('FTV_SETTINGS'));
		if (!empty($FTV_SETTINGS)) {
			foreach ($FTV_SETTINGS as $FTV_ITEM) {
				if ($FTV_ITEM['TREE'] == $this->tree()->getTreeId() && !empty($FTV_ITEM['PID']) && $FTV_ITEM['ACCESS_LEVEL'] >= Auth::accessLevel($this->tree())) {
					$FTV_GED_SETTINGS[] = $FTV_ITEM;
				}
			}

			if (!empty($FTV_GED_SETTINGS)) {
				$menu = new Menu(I18N::translate('Family tree overview'), '#', 'menu-fancy_treeview');

				foreach ($FTV_GED_SETTINGS as $FTV_ITEM) {
					$xref		 = $FTV_ITEM['PID'];
					$surname	 = $FTV_ITEM['SURNAME'];
					$record		 = Individual::getInstance($xref, $this->tree());
					$tree_name	 = Filter::escapeUrl($this->tree()->getName());

					if ($record && $record->canShowName()) {
						if ($this->module()->options('use_fullname') == true) {
							$submenu = new Menu(I18N::translate('Descendants of %s', $record->getFullName()), 'module.php?mod=' . $this->getName() . '&amp;mod_action=page&amp;rootid=' . $xref . '&amp;ged=' . $tree_name, 'menu-fancy_treeview-' . $xref);
						} else {
							$submenu = new Menu(I18N::translate('Descendants of the %s family', $surname), 'module.php?mod=' . $this->getName() . '&amp;mod_action=page&amp;rootid=' . $xref . '&amp;ged=' . $tree_name, 'menu-fancy_treeview-' . $xref);
						}
						$menu->addSubmenu($submenu);
					}
				}

				if (count($menu->getSubmenus()) > 0) {
					return $menu;
				}
			}
		}
	}

	protected function tree() {
		global $WT_TREE;

		$tree = Tree::findByName(Filter::get('ged'));
		if ($tree) {
			return $tree;
		} else {
			return $WT_TREE;
		}
	}

	protected function pdf() {
		if (Module::getModuleByName('fancy_treeview_pdf')) {
			return new FancyTreeviewPdfClass;
		}
	}
	
	public static function intVersion() {
		$version = (int) str_replace(".", "", str_replace("-dev", "", self::CUSTOM_VERSION));
		if ($version > 999) {
			return $version / 10;
		} else {
			return $version;
		}
	}

}

return new FancyTreeviewModule;

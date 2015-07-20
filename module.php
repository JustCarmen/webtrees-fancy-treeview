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
namespace JustCarmen\WebtreesAddOns\FancyTreeview;

use Composer\Autoload\ClassLoader;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Database;
use Fisharebest\Webtrees\File;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Theme;
use JustCarmen\WebtreesAddOns\FancyTreeview\Template\AdminTemplate;
use JustCarmen\WebtreesAddOns\FancyTreeview\Template\PageTemplate;
use JustCarmen\WebtreesAddOns\FancyTreeview\Template\PdfTemplate;
use Rhumsaa\Uuid\Uuid;

class FancyTreeviewModule extends AbstractModule implements ModuleConfigInterface, ModuleTabInterface, ModuleMenuInterface {

	// How to update the database schema for this module
	const SCHEMA_TARGET_VERSION = 8;
	const SCHEMA_SETTING_NAME = 'FTV_SCHEMA_VERSION';
	const SCHEMA_MIGRATION_PREFIX = '\JustCarmen\WebtreesAddOns\FancyTreeview\Schema';

	/** @var integer The tree's ID number */
	var $tree_id;

	/** @var string location of the fancy treeview module files */
	var $directory;

	/** @var string module action */
	var $action;

	/** {@inheritdoc} */
	public function __construct() {
		parent::__construct('fancy_treeview');

		$this->tree_id = $this->getTreeId();
		$this->directory = WT_MODULES_DIR . $this->getName();
		$this->action = Filter::get('mod_action');

		// register the namespaces
		$loader = new ClassLoader();
		$loader->addPsr4('JustCarmen\\WebtreesAddOns\\FancyTreeview\\', $this->directory . '/src');
		$loader->register();
	}

	/**
	 * Get the module class.
	 * 
	 * Class functions are called with $this inside the source directory.
	 */
	private function module() {
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
		return /* I18N: Title used in the tab panel */ I18N::translate('Generations');
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
		return 99;
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
		return true;
	}

	/** {@inheritdoc} */
	public function modAction($mod_action) {
		global $WT_TREE;

		Database::updateSchema(self::SCHEMA_MIGRATION_PREFIX, self::SCHEMA_SETTING_NAME, self::SCHEMA_TARGET_VERSION);

		switch ($mod_action) {
			case 'admin_config':
				$template = new AdminTemplate;
				return $template->pageContent();

			case 'admin_search':
				// new settings
				$surname = Filter::post('SURNAME');
				$pid = Filter::post('PID');
				if ($surname) {
					$soundex_std = Filter::postBool('soundex_std');
					$soundex_dm = Filter::postBool('soundex_dm');

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
						$record = Individual::getInstance($pid, $WT_TREE);
						if ($record) {
							$root = $record->getFullName() . ' (' . $record->getLifeSpan() . ')';
							$title = $this->module()->getPageLink($pid);

							$result = array(
								'access_level'	 => '2', // default access level = show to visitors
								'pid'			 => $pid,
								'root'			 => $root,
								'sort'			 => count($this->module()->searchArray($FTV_SETTINGS, 'TREE', Filter::getInteger('tree'))) + 1,
								'surname'		 => $this->module()->getSurname($pid),
								'title'			 => $title,
								'tree'			 => Filter::getInteger('tree')
							);
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
				$FTV_SETTINGS = unserialize($this->getSetting('FTV_SETTINGS'));
				$NEW_FTV_SETTINGS = $FTV_SETTINGS;
				$NEW_FTV_SETTINGS[] = array(
					'TREE'			 => Filter::getInteger('tree'),
					'SURNAME'		 => Filter::post('surname'),
					'PID'			 => Filter::post('pid'),
					'ACCESS_LEVEL'	 => Filter::postInteger('access_level'),
					'SORT'			 => Filter::postInteger('sort'),
				);
				$this->setSetting('FTV_SETTINGS', serialize(array_values($NEW_FTV_SETTINGS)));
				Log::addConfigurationLog($this->getTitle() . ' config updated');
				break;

			case 'admin_update':
				$FTV_SETTINGS = unserialize($this->getSetting('FTV_SETTINGS'));

				$new_surname = Filter::postArray('surname');
				$new_access_level = Filter::postArray('access_level');
				$new_sort = Filter::postArray('sort');

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
				$FTV_OPTIONS = unserialize($this->getSetting('FTV_OPTIONS'));
				$FTV_OPTIONS[Filter::getInteger('tree')] = Filter::postArray('NEW_FTV_OPTIONS');
				$this->setSetting('FTV_OPTIONS', serialize($FTV_OPTIONS));
				Log::addConfigurationLog($this->getTitle() . ' config updated');
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

			case 'image_data':
				header('Content-type: text/html; charset=UTF-8');
				$xref = Filter::get('mid');
				if (Filter::get('ftv_thumb')) {
					$path = WT_DATA_DIR . '/ftv_cache/';
					if (!file_exists($path)) {
						File::mkdir($path);
					}
					$cache_file = $path . 'ftv-' . $xref . '-cache.jpg';
					if (file_exists($cache_file)) {
						$filemtime = filemtime($cache_file);
					} else {
						$filemtime = 0;
					}
					if (time() > $filemtime + 86400) {
						$data = Filter::post('base64');
						list($type, $data) = explode(';', $data);
						list(, $data) = explode(',', $data);
						$image = base64_decode($data);
						if ($image) {
							file_put_contents($cache_file, $image);
						}
					}
					echo $cache_file;
				} else {
					$mediaobject = Media::getInstance($xref, $WT_TREE);
					if ($mediaobject) {
						echo $mediaobject->getServerFilename('thumb');
					}
				}
				break;

			case 'pdf_data':
				$template = new PdfTemplate;
				return $template->pageData();
				break;

			case 'show_pdf':
				$template = new PdfTemplate();
				return $template->pageBody();
				break;

			default:
				http_response_code(404);
				break;
		}
	}

	/** {@inheritdoc} */
	public function getTabContent() {
		global $controller;

		Database::updateSchema(self::SCHEMA_MIGRATION_PREFIX, self::SCHEMA_SETTING_NAME, self::SCHEMA_TARGET_VERSION);

		return
			'<script src="' . WT_STATIC_URL . $this->directory . '/js/tab.js" defer="defer"></script>' .
			'<div id="fancy_treeview-page" class="fancy_treeview-tab">' .
			'<ol id="fancy_treeview">' . $this->module()->printTabContent($controller->record->getXref()) . '</ol>' .
			'</div>';
	}

	/** {@inheritdoc} */
	public function getPreLoadContent() {
		return false;
	}

	/** {@inheritdoc} */
	public function getMenu() {
		global $WT_TREE, $controller;

		if (!Auth::isSearchEngine()) {

			Database::updateSchema(self::SCHEMA_MIGRATION_PREFIX, self::SCHEMA_SETTING_NAME, self::SCHEMA_TARGET_VERSION);

			static $menu;

			// Function has already run
			if ($menu !== null) {
				return $menu;
			}

			$FTV_SETTINGS = unserialize($this->getSetting('FTV_SETTINGS'));

			if (!empty($FTV_SETTINGS)) {

				foreach ($FTV_SETTINGS as $FTV_ITEM) {
					if ($FTV_ITEM['TREE'] == $WT_TREE->getTreeId() && !empty($FTV_ITEM['PID']) && $FTV_ITEM['ACCESS_LEVEL'] >= Auth::accessLevel($WT_TREE)) {
						$FTV_GED_SETTINGS[] = $FTV_ITEM;
					}
				}

				if (!empty($FTV_GED_SETTINGS)) {

					if (Theme::theme()->themeId() !== '_administration') {
						// load the module stylesheets
						echo $this->module()->getStylesheet();

						// add javascript files and scripts
						$this->module()->includeJs($controller, 'menu');

						if (WT_SCRIPT_NAME === 'individual.php') {
							$this->module()->includeJs($controller, 'tab');
						}
					}

					$menu = new Menu(I18N::translate('Family tree overview'), 'module.php?mod=' . $this->getName() . '&amp;mod_action=page&amp;rootid=' . $FTV_GED_SETTINGS[0]['PID'], 'menu-fancy_treeview');

					foreach ($FTV_GED_SETTINGS as $FTV_ITEM) {
						$record = Individual::getInstance($FTV_ITEM['PID'], $WT_TREE);
						if ($record) {
							if ($this->module()->options('use_fullname') == true) {
								$submenu = new Menu(I18N::translate('Descendants of %s', $record->getFullName()), 'module.php?mod=' . $this->getName() . '&amp;mod_action=page&amp;rootid=' . $FTV_ITEM['PID'], 'menu-fancy_treeview-' . $FTV_ITEM['PID']);
							} else {
								$submenu = new Menu(I18N::translate('Descendants of the %s family', $FTV_ITEM['SURNAME']), 'module.php?mod=' . $this->getName() . '&amp;mod_action=page&amp;rootid=' . $FTV_ITEM['PID'], 'menu-fancy_treeview-' . $FTV_ITEM['PID']);
							}
							$menu->addSubmenu($submenu);
						}
					}

					return $menu;
				}
			}
		}
	}

	private function getTreeId() {
		global $WT_TREE;

		if ($WT_TREE) {
			$tree = $WT_TREE->findByName(Filter::get('ged'));
			if ($tree) {
				return $tree->getTreeId();
			} else {
				return $WT_TREE->getTreeId();
			}
		}
	}

}

return new FancyTreeviewModule;

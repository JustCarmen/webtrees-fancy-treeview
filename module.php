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

use PDOException;
use Zend_Session;
use Zend_Translate;

class fancy_treeview_WT_Module extends Module implements ModuleConfigInterface, ModuleTabInterface, ModuleMenuInterface {

	/** @var integer The tree's ID number */
	var $tree_id;

	/** @var string location of the fancy treeview module files */
	var $module;
	
	var $action;

	/** {@inheritdoc} */
	public function __construct() {
		parent::__construct();

		$this->tree_id = $this->getTreeId();
		$this->module = WT_MODULES_DIR . $this->getName();
		$this->action = Filter::get('mod_action');

		// update the database if neccessary
		self::updateSchema();

		// Load the module class
		require_once $this->module . '/fancytreeview.php';

		// Load any local user translations
		if (is_dir($this->module . '/language')) {
			if (file_exists($this->module . '/language/' . WT_LOCALE . '.mo')) {
				I18N::addTranslation(
					new Zend_Translate('gettext', $this->module . '/language/' . WT_LOCALE . '.mo', WT_LOCALE)
				);
			}
		}
	}

	public function getName() {
		return 'fancy_treeview';
	}

	/** {@inheritdoc} */
	public function getTitle() {
		return /* I18N: Name of the module */ I18N::translate('Fancy Tree View');
	}

	/** {@inheritdoc} */
	public function getDescription() {
		return /* I18N: Description of the module */ I18N::translate('A Fancy overview of the descendants of one family(branch) in a narrative way');
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
		$ftv = new FancyTreeView;
		if($ftv->options('ftv_tab')) {
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
		return false;
	}

	/** {@inheritdoc} */
	public function modAction($mod_action) {
		$ftv = new FancyTreeView;
		switch ($mod_action) {
		case 'admin_config':
			$controller = new PageController;
			$controller
				->restrictAccess(Auth::isAdmin())
				->setPageTitle('Fancy Tree View')
				->pageHeader();

			// add javascript files and scripts
			$ftv->includeJs($controller, 'admin');

			// add stylesheet
			echo $ftv->getStylesheet();

			// get the settings for this tree
			$FTV_SETTINGS = unserialize($this->getSetting('FTV_SETTINGS'));

			// get the admin page content
			include($this->module . '/templates/admin.php');
			break;

		case 'admin_search':
			Zend_Session::writeClose();
			// new settings
			$surname = Filter::post('SURNAME');
			$pid = Filter::post('PID');
			if ($surname) {
				$soundex_std = Filter::postBool('soundex_std');
				$soundex_dm = Filter::postBool('soundex_dm');

				$indis = $ftv->indisArray($surname, $soundex_std, $soundex_dm);
				usort($indis, __NAMESPACE__ . '\\Individual::compareBirthDate');

				if (isset($indis) && count($indis) > 0) {
					$pid = $indis[0]->getXref();
				} else {
					$result['error'] = I18N::translate('Error: The surname you entered doesn’t exist in this tree.');
				}
			}

			if (isset($pid)) {
				$FTV_SETTINGS = unserialize($this->getSetting('FTV_SETTINGS'));
				if ($ftv->searchArray($ftv->searchArray($FTV_SETTINGS, 'TREE', Filter::getInteger('tree')), 'PID', $pid)) {
					if ($surname) {
						$result['error'] = I18N::translate('Error: The root person belonging to this surname already exists');
					}
					else {
						$result['error'] = I18N::translate('Error: A root person with ID %s already exists', $pid);
					}
				} else {
					$root = Individual::getInstance($pid)->getFullName() . ' (' . Individual::getInstance($pid)->getLifeSpan() . ')';
					$title = $ftv->getPageLink($pid);

					$result = array(
						'access_level'	 => '2', // default access level = show to visitors
						'pid'			 => $pid,
						'root'			 => $root,
						'sort'			 => count($ftv->searchArray($FTV_SETTINGS, 'TREE', Filter::getInteger('tree'))) + 1,
						'surname'		 => $ftv->getSurname($pid),
						'title'			 => $title,
						'tree'			 => Filter::getInteger('tree')
					);
				}
			}
			echo json_encode($result);
			break;

		case 'admin_add':
			Zend_Session::writeClose();
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
			Zend_Session::writeClose();
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

			$NEW_FTV_SETTINGS = $ftv->sortArray($FTV_SETTINGS, 'SORT');
			$this->setSetting('FTV_SETTINGS', serialize($NEW_FTV_SETTINGS));
			break;

		case 'admin_save':
			Zend_Session::writeClose();
			$FTV_OPTIONS = unserialize($this->getSetting('FTV_OPTIONS'));
			$FTV_OPTIONS[Filter::getInteger('tree')] = Filter::postArray('NEW_FTV_OPTIONS');
			$this->setSetting('FTV_OPTIONS', serialize($FTV_OPTIONS));
			Log::addConfigurationLog($this->getTitle() . ' config updated');
			break;

		case 'admin_reset':
			Zend_Session::writeClose();
			$FTV_OPTIONS = unserialize($this->getSetting('FTV_OPTIONS'));
			unset($FTV_OPTIONS[Filter::getInteger('tree')]);
			$this->setSetting('FTV_OPTIONS', serialize($FTV_OPTIONS));
			Log::addConfigurationLog($this->getTitle() . ' options set to default');
			break;

		case 'admin_delete':
			Zend_Session::writeClose();
			$FTV_SETTINGS = unserialize($this->getSetting('FTV_SETTINGS'));
			unset($FTV_SETTINGS[Filter::getInteger('key')]);
			$this->setSetting('FTV_SETTINGS', serialize($FTV_SETTINGS));
			Log::addConfigurationLog($this->getTitle() . ' item deleted');
			break;

		case 'page':
			global $controller;

			$controller = new PageController;

			$root_person = $ftv->getIndividual($ftv->rootId());
			if ($root_person && $root_person->canShowName()) {
				$controller
					->setPageTitle(/* I18N: %s is the surname of the root individual */ I18N::translate('Descendants of %s', $root_person->getFullName()))
					->pageHeader();

				// add javascript files and scripts
				$ftv->includeJs($controller, 'page');

				// get the Fancy Tree View page content
				include($this->module . '/templates/page.php');
			} else {
				http_response_code(404);
				$controller->pageHeader();
				echo $ftv->addMessage('alert', 'warning', false, I18N::translate('This individual does not exist or you do not have permission to view it.'));

			}
			break;

		case 'image_data':
			Zend_Session::writeClose();
			header('Content-type: text/html; charset=UTF-8');
			$xref = Filter::get('mid');
			$mediaobject = Media::getInstance($xref);
			if ($mediaobject) {
				echo $mediaobject->getServerFilename();
			}
			break;

		case 'pdf_data':
			include('pdf/data.php');
			break;

		case 'show_pdf':
			include('pdf/pdf.php');
			break;

		default:
			http_response_code(404);
			break;
		}
	}

	/** {@inheritdoc} */
	public function getTabContent() {
		global $controller;
		$ftv = new FancyTreeView;
		return
			'<script src="' . WT_STATIC_URL . WT_MODULES_DIR . $this->getName() . '/js/tab.js" defer="defer"></script>' .
			'<div id="fancy_treeview-page" class="fancy_treeview-tab">' .
				'<ol id="fancy_treeview">' . $ftv->printTabContent($controller->record->getXref()) . '</ol>' .
			'</div>';
	}

	/** {@inheritdoc} */
	public function getPreLoadContent() {
		return false;
	}

	/** {@inheritdoc} */
	public function getMenu() {
		global $controller;
		
		if (!Auth::isSearchEngine() && Theme::theme()->themeId() !== '_administration') {

			$ftv = new FancyTreeView;
			static $menu;

			// Function has already run
			if ($menu !== null) {
				return $menu;
			}

			$FTV_SETTINGS = unserialize($this->getSetting('FTV_SETTINGS'));

			if (!empty($FTV_SETTINGS)) {

				foreach ($FTV_SETTINGS as $FTV_ITEM) {
					if ($FTV_ITEM['TREE'] == WT_GED_ID && !empty($FTV_ITEM['PID']) && $FTV_ITEM['ACCESS_LEVEL'] >= WT_USER_ACCESS_LEVEL) {
						$FTV_GED_SETTINGS[] = $FTV_ITEM;
					}
				}

				if (!empty($FTV_GED_SETTINGS)) {
					// load the module stylesheets
					echo $ftv->getStylesheet();

					// add javascript files and scripts
					$ftv->includeJs($controller, 'menu');

					$menu = new Menu(I18N::translate('Tree view'), 'module.php?mod=' . $this->getName() . '&amp;mod_action=page&amp;rootid=' . $FTV_GED_SETTINGS[0]['PID'], 'menu-fancy_treeview');

					foreach ($FTV_GED_SETTINGS as $FTV_ITEM) {
						if (Individual::getInstance($FTV_ITEM['PID'])) {
							if ($ftv->options('use_fullname') == true) {
								$submenu = new Menu(I18N::translate('Descendants of %s', Individual::getInstance($FTV_ITEM['PID'])->getFullName()), 'module.php?mod=' . $this->getName() . '&amp;mod_action=page&amp;rootid=' . $FTV_ITEM['PID'], 'menu-fancy_treeview-' . $FTV_ITEM['PID']);
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

		$tree_id = $WT_TREE->getIdFromName(Filter::get('ged'));
		if (!$tree_id) {
			$tree_id = $WT_TREE->getTreeId();
		}

		return $tree_id;
	}

	/**
	 * Make sure the database structure is up-to-date.
	 */
	protected static function updateSchema() {
		try {
			Database::updateSchema(WT_ROOT . WT_MODULES_DIR . 'fancy_treeview/db_schema/', 'FTV_SCHEMA_VERSION', 8);
		} catch (PDOException $ex) {
			// The schema update scripts should never fail.  If they do, there is no clean recovery.
			FlashMessages::addMessage($ex->getMessage(), 'danger');
			header('Location: ' . WT_BASE_URL . 'site-unavailable.php');
			throw $ex;
		}
	}

}

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

use PDO;
use PDOException;
use Rhumsaa\Uuid\Uuid;
use Zend_Session;
use Zend_Translate;

// Update database when upgrading from a previous version
try {
	Database::updateSchema(WT_ROOT . WT_MODULES_DIR . 'fancy_treeview/db_schema/', 'FTV_SCHEMA_VERSION', 8);
} catch (PDOException $ex) {
	// The schema update scripts should never fail.  If they do, there is no clean recovery.
	FlashMessages::addMessage($ex->getMessage(), 'danger');
	header('Location: ' . WT_BASE_URL . 'site-unavailable.php');
	throw $ex;
}

class fancy_treeview_WT_Module extends Module implements ModuleConfigInterface, ModuleMenuInterface {

	public function __construct() {
		parent::__construct();
		// Load any local user translations
		if (is_dir(WT_MODULES_DIR . $this->getName() . '/language')) {
			if (file_exists(WT_MODULES_DIR . $this->getName() . '/language/' . WT_LOCALE . '.mo')) {
				I18N::addTranslation(
					new Zend_Translate('gettext', WT_MODULES_DIR . $this->getName() . '/language/' . WT_LOCALE . '.mo', WT_LOCALE)
				);
			}
		}
	}

	// Extend Module
	public function getTitle() {
		return /* I18N: Name of the module */ I18N::translate('Fancy Tree View');
	}

	// Extend Module
	public function getDescription() {
		return /* I18N: Description of the module */ I18N::translate('A Fancy overview of the descendants of one family(branch) in a narrative way');
	}

	// Get module options
	private function options($value = '') {
		global $WT_TREE;
		$FTV_OPTIONS = unserialize($this->getSetting('FTV_OPTIONS'));

		$key = $WT_TREE->getIdFromName(Filter::get('ged'));
		if (empty($key)) {
			$key = WT_GED_ID;
		}

		if (empty($FTV_OPTIONS) || (is_array($FTV_OPTIONS) && !array_key_exists($key, $FTV_OPTIONS))) {
			$FTV_OPTIONS[0] = array(
				'USE_FULLNAME'			 => '0',
				'NUMBLOCKS'				 => '0',
				'CHECK_RELATIONSHIP'	 => '0',
				'SHOW_SINGLES'			 => '0',
				'SHOW_PLACES'			 => '1',
				'USE_GEDCOM_PLACES'		 => '0',
				'COUNTRY'				 => '',
				'SHOW_OCCU'				 => '1',
				'RESIZE_THUMBS'			 => '1',
				'THUMB_SIZE'			 => '60',
				'THUMB_RESIZE_FORMAT'	 => '2',
				'USE_SQUARE_THUMBS'		 => '1',
				'SHOW_USERFORM'			 => '2',
				'SHOW_PDF_ICON'			 => '2'
			);
			$key = 0;
		}

		// country could be disabled and thus not set
		if ($value == 'country' && !array_key_exists(strtoupper($value), $FTV_OPTIONS[$key])) {
			return '';
		} elseif ($value) {
			return($FTV_OPTIONS[$key][strtoupper($value)]);
		} else {
			return $FTV_OPTIONS[$key];
		}
	}

	// Get Indis from surname input - see: WT\Controller\Branches.php - loadIndividuals
	private function indis_array($surname, $russell, $daitchMokotoff) {
		$sql = "SELECT DISTINCT i_id AS xref, i_file AS gedcom_id, i_gedcom AS gedcom" .
			" FROM `##individuals`" .
			" JOIN `##name` ON (i_id = n_id AND i_file = n_file)" .
			" WHERE n_file = :ged_id" .
			" AND n_type != '_MARNM'" .
			" AND (n_surn = :surname1 OR n_surname = :surname2";
		$args = array(
			'ged_id' => WT_GED_ID,			
			'surname1' => $surname,
			'surname2' => $surname
		);
		if ($russell) { // works only with latin letters. For other letters it outputs the code '0000'.
			foreach (explode(':', Soundex::russell($surname)) as $value) {
				if ($value != '0000') {
					$sql .= " OR n_soundex_surn_std LIKE CONCAT('%', '" . $value . "', '%')";
				}
			}
		}
		if ($daitchMokotoff) { // works only with predefined letters and lettercombinations. Fot other letters it outputs the code '000000'.
			foreach (explode(':', Soundex::daitchMokotoff($surname)) as $value) {
				if ($value != '000000') {
					$sql .= " OR n_soundex_surn_dm LIKE CONCAT('%', '" . $value . "', '%')";
				}
			}
		}
		$sql .= ')';
		$rows = Database::prepare($sql)
			->execute($args)
			->fetchAll();
		$data = array();
		foreach ($rows as $row) {
			$data[] = Individual::getInstance($row->xref, $row->gedcom_id, $row->gedcom);
		}
		return $data;
	}

	// Get surname from pid
	private function getSurname($pid) {
		$sql = "SELECT n_surname AS surname FROM `##name` WHERE n_file = :ged_id AND n_id = :pid AND n_type = 'NAME'";
		$args = array(
			'ged_id' => WT_GED_ID,
			'pid' => $pid
		);
		$data = Database::prepare($sql)->execute($args)->fetchOne();
		return $data;
	}

	// Search within a multiple dimensional array
	private function searchArray($array, $key, $value) {
		$results = array();
		if (is_array($array)) {
			if (isset($array[$key]) && $array[$key] == $value) {
				$results[] = $array;
			}
			foreach ($array as $subarray) {
				$results = array_merge($results, $this->searchArray($subarray, $key, $value));
			}
		}
		return $results;
	}

	// Sort the array according to the $key['SORT'] input.
	private function sortArray($array, $sort_by) {

		$array_keys = array('tree', 'surname', 'display_name', 'pid', 'access_level', 'sort');

		foreach ($array as $pos => $val) {
			$tmp_array[$pos] = $val[$sort_by];
		}
		asort($tmp_array);

		$return_array = array();
		foreach ($tmp_array as $pos => $val) {
			foreach ($array_keys as $key) {
				$key = strtoupper($key);
				$return_array[$pos][$key] = $array[$pos][$key];
			}
		}
		return array_values($return_array);
	}

	private function getPageLink($pid) {
		global $WT_TREE;
		$link = '<a href="module.php?mod=' . $this->getName() . '&amp;mod_action=show&amp;ged=' . $WT_TREE->getNameHtml() . '&amp;rootid=' . $pid . '" target="_blank">';

		if ($this->options('use_fullname') == true) {
			$link .= I18N::translate('Descendants of %s', Individual::getInstance($pid)->getFullName());
		} else {
			$link .= I18N::translate('Descendants of the %s family', $this->getSurname($pid));
		}

		$link .= '</a>';

		return $link;
	}

	private function getCountryList() {
		$list = '';
		$countries = Database::prepare("SELECT SQL_CACHE p_place as country FROM `##places` WHERE p_parent_id=? AND p_file=?")
				->execute(array('0', WT_GED_ID))->fetchAll(PDO::FETCH_ASSOC);

		foreach ($countries as $country) {
			$list[$country['country']] = $country['country']; // set the country as key to display as option value.
		}
		return $list;
	}

	// Extend ModuleConfigInterface
	public function modAction($mod_action) {
		switch ($mod_action) {
			case 'admin_config':
				$this->config();
				break;
			case 'admin_search':
				$this->search();
				break;
			case 'admin_add':
				$this->add();
				break;
			case 'admin_update':
				$this->update();
				break;
			case 'admin_save':
				$this->save_options();
				break;
			case 'admin_reset':
				$this->reset_options();
				$this->config();
				break;
			case 'admin_delete':
				$this->delete();
				$this->config();
				break;
			case 'show':
				$this->show();
				break;
			case 'image_data':
				$this->getImageData();
				break;
			case 'pdf_data':
				include('pdf/data.php');
				break;
			case 'show_pdf':
				include('pdf/pdf.php');
				break;
			default:
				header('HTTP/1.0 404 Not Found');
		}
	}

	// Implement ModuleConfigInterface
	public function getConfigLink() {
		return 'module.php?mod=' . $this->getName() . '&amp;mod_action=admin_config';
	}

	private function getAdminPageJS($controller) {
		$controller->addInlineJavascript('
			// Fancy Tree View configuration page script
			function include_css(css_file) {
				var html_doc = document.getElementsByTagName("head")[0];
				var css = document.createElement("link");
				css.setAttribute("rel", "stylesheet");
				css.setAttribute("type", "text/css");
				css.setAttribute("href", css_file);
				html_doc.appendChild(css);
			}
			include_css("' . WT_MODULES_DIR . $this->getName() . '/themes/' . Theme::theme()->themeId() . '/style.css");

			// Close the alerts without removal (Bootstrap default)
			jQuery(".alert .close").on("click",function(){
				jQuery(this).parent().hide();
			});

			// dynamic title
			var treeName = jQuery("#tree option:selected").text();
			jQuery("#panel2 .panel-title a").text("' . I18N::translate('Options for') . '" + treeName);

			/*** FORM 1 ***/
			jQuery("#tree").change(function(){
				// get the config page for the selected tree
				var tree_name = jQuery(this).find("option:selected").data("ged");
				window.location = "module.php?mod=' . $this->getName() . '&mod_action=admin_config&ged=" + tree_name;
			});

			/*** FORM 2 ***/
			// add search values from form2 to form3
			jQuery("#ftv-search-form").on("submit", "form[name=form2]", function(e){
				e.preventDefault();
				var tree = jQuery("#tree").find("option:selected").val();
				var table = jQuery("#search-result-table");
				jQuery.ajax({
					type: "POST",
					url: "module.php?mod=' . $this->getName() . '&mod_action=admin_search&tree=" + tree,
					data: jQuery(this).serialize(),
					dataType: "json",
					success: function(data) {
						jQuery(".ui-autocomplete").hide();
						if(data.hasOwnProperty("error")) {
							jQuery("form[name=form3] table").hide();
							jQuery("#error .message").html(data.error).parent().fadeIn();
							jQuery("input#surname").val("").focus();
						}
						else {
							jQuery("#error").hide();
							table.find("#pid").val(data.pid);
							table.find("#sort").val(data.sort);
							table.find("#root span").html(data.root);
							table.find("#surname").val(data.surname);
							table.find("#surn label").text(data.surname);
							table.find("#title").html(data.title);
							table.show();
						}
					}
				});
			});

			/*** FORM 3 ***/
			// add search results to table
			jQuery("#ftv-search-form").on("submit", "form[name=form3]", function(e){
				e.preventDefault();
				var tree = jQuery("#tree").find("option:selected").val();
				jQuery.ajax({
					type: "POST",
					url: "module.php?mod=' . $this->getName() . '&mod_action=admin_add&tree=" + tree,
					data: jQuery(this).serialize(),
					success: function() {
						jQuery("#fancy-treeview-form").load("module.php?mod=' . $this->getName() . '&mod_action=admin_config #fancy-treeview-form form")
						jQuery("#search-result-table").fadeOut("slow");
						jQuery("input#surname").val("");
					}
				});
			});

			/*** FORM 3 AND 4 ***/
			// click on a surname to get an input textfield to change the surname to a more appropriate name.
			jQuery("#panel1").on("click", ".showname", function(){
				jQuery(this).hide();
				jQuery(this).next(".editname").show();
			});

			/*** FORM 4 ***/
			// make the table sortable
			jQuery("#fancy-treeview-form").sortable({items: ".sortme", forceHelperSize: true, forcePlaceholderSize: true, opacity: 0.7, cursor: "move", axis: "y"});

			//-- update the order numbers after drag-n-drop sorting is complete
			jQuery("#fancy-treeview-form").bind("sortupdate", function(event, ui) {
				jQuery("#"+jQuery(this).attr("id")+" input[id^=sort]").each(
					function (index, value) {
						value.value = index+1;
					}
				);
			});

			// update settings form4
			jQuery("#fancy-treeview-form").on("submit", "form[name=form4]", function(e){
				e.preventDefault();
				jQuery.ajax({
					type: "POST",
					url: "module.php?mod=' . $this->getName() . '&mod_action=admin_update",
					data: jQuery(this).serialize(),
					success: function() {
						jQuery("#fancy-treeview-form").load("module.php?mod=' . $this->getName() . '&mod_action=admin_config #fancy-treeview-form form", function(){
							var message = jQuery("#message-save-options");
							jQuery(this).before(message);
							message.fadeIn();
							var target = message.offset().top - 60;
							jQuery("html, body").animate({scrollTop:target}, 800);
							setTimeout(function() {
								message.fadeOut();
							}, 5000 );
						})
					}
				});
			});

			// delete row from form4
			jQuery("#fancy-treeview-form").on("click", "button[name=delete]", function(e){
				e.preventDefault()
				var key = jQuery(this).data("key");
				var row = jQuery(this).parents("tr");
				var rowCount = jQuery("#fancy-treeview-table > tbody > tr").length - 1;
				jQuery.ajax({
					type: "GET",
					url: "module.php?mod=' . $this->getName() . '&mod_action=admin_delete&key=" + key,
					success: function() {
						row.remove();
						if(rowCount === 0) {
							jQuery("#fancy-treeview-form form").remove();
						}
					}
				});
			});

			/*** FORM 5 ***/
			// update options
			jQuery("#ftv-options-form").on("submit", "form[name=form5]", function(e){
				e.preventDefault();
				var tree = jQuery("#tree").find("option:selected").val();
				jQuery.ajax({
					type: "POST",
					url: "module.php?mod=' . $this->getName() . '&mod_action=admin_save&tree=" + tree,
					data: jQuery(this).serialize(),
					success: function() {
							jQuery("#ftv-search-form").load("module.php?mod=' . $this->getName() . '&mod_action=admin_config #ftv-search-form form", function() {
								jQuery(this).find("#search-result-table").hide().removeClass("hidden");
							})
							jQuery("#fancy-treeview-form").load("module.php?mod=' . $this->getName() . '&mod_action=admin_config #fancy-treeview-form form")
							jQuery("#ftv-options-form").load("module.php?mod=' . $this->getName() . '&mod_action=admin_config #ftv-options-form form", function(){
							jQuery("#reset-options").hide();
							jQuery("#save-options").fadeIn();
							var target = jQuery("#save-options").offset().top - 60;
							jQuery("html, body").animate({scrollTop:target}, 800);
						})
					}
				});
			});

			// reset options
			jQuery("#ftv-options-form").on("reset", "form[name=form5]", function(e){
				e.preventDefault()
				var tree = jQuery("#tree").find("option:selected").val();
				jQuery.ajax({
					type: "GET",
					url: "module.php?mod=' . $this->getName() . '&mod_action=admin_reset&tree=" + tree,
					success: function() {
						jQuery("#ftv-search-form").load("module.php?mod=' . $this->getName() . '&mod_action=admin_config #ftv-search-form form", function() {
								jQuery(this).find("#search-result-table").hide().removeClass("hidden");
							})
							jQuery("#fancy-treeview-form").load("module.php?mod=' . $this->getName() . '&mod_action=admin_config #fancy-treeview-form form")
						jQuery("#ftv-options-form").load("module.php?mod=' . $this->getName() . '&mod_action=admin_config #ftv-options-form form", function(){
							jQuery("#save-options").hide();
							jQuery("#reset-options").fadeIn();
							var target = jQuery("#reset-options").offset().top - 60;
							jQuery("html, body").animate({scrollTop:target}, 800);
						})
					}
				});
			});

			jQuery("#ftv-options-form").on("click", "#resize_thumbs input[type=radio]", function(){
				var field = jQuery("#ftv-options-form").find("#thumb_size, #square_thumbs");
				jQuery(this).val() === "1" ? field.fadeIn() : field.fadeOut();
			});

			jQuery("#ftv-options-form").on("click", "#places input[type=radio]", function(){
				var field1 = jQuery("#ftv-options-form").find("#gedcom_places");
				var field2 = jQuery("#ftv-options-form").find("#country_list");
				if(jQuery(this).val() === "1") {
					field1.fadeIn();
					if(field1.find("input[type=radio]:checked").val() === "0") field2.fadeIn();
				}
				else {
					field1.fadeOut();
					field2.fadeOut();
				}
			});

			jQuery("#ftv-options-form").on("click", "#gedcom_places input[type=radio]", function(){
				var field = jQuery("#ftv-options-form").find("#country_list");
				jQuery(this).val() === "0" ? field.fadeIn() : field.fadeOut();
			});
			// end of Fancy Tree View configuration page script
		');
	}

	private function search() {
		Zend_Session::writeClose();
		// new settings
		$surname = Filter::post('surname');
		if ($surname) {
			$soundex_std = Filter::postBool('soundex_std');
			$soundex_dm = Filter::postBool('soundex_dm');

			$indis = $this->indis_array($surname, $soundex_std, $soundex_dm);
			usort($indis, __NAMESPACE__ . '\\Individual::compareBirthDate');

			if (isset($indis) && count($indis) > 0) {
				$pid = $indis[0]->getXref();
			} else {
				$result['error'] = I18N::translate('Error: The surname you entered doesn’t exist in this tree.');
			}
		}

		if (isset($pid)) {
			$FTV_SETTINGS = unserialize($this->getSetting('FTV_SETTINGS'));
			if ($this->searchArray($this->searchArray($FTV_SETTINGS, 'TREE', Filter::getInteger('tree')), 'PID', $pid)) {
				$result['error'] = I18N::translate('Error: The root person belonging to this surname already exists');
			} else {
				$root = Individual::getInstance($pid)->getFullName() . ' (' . Individual::getInstance($pid)->getLifeSpan() . ')';
				$title = $this->getPageLink($pid);

				$result = array(
					'access_level'	 => '2', // default access level = show to visitors
					'pid'			 => $pid,
					'root'			 => $root,
					'sort'			 => count($this->searchArray($FTV_SETTINGS, 'TREE', Filter::getInteger('tree'))) + 1,
					'surname'		 => $this->getSurname($pid),
					'title'			 => $title,
					'tree'			 => Filter::getInteger('tree')
				);
			}
		}
		echo json_encode($result);
	}

	private function add() {
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
	}

	Private function update() {
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

		$NEW_FTV_SETTINGS = $this->sortArray($FTV_SETTINGS, 'SORT');
		$this->setSetting('FTV_SETTINGS', serialize($NEW_FTV_SETTINGS));
	}

	private function save_options() {
		Zend_Session::writeClose();
		$FTV_OPTIONS = unserialize($this->getSetting('FTV_OPTIONS'));
		$FTV_OPTIONS[Filter::getInteger('tree')] = Filter::postArray('NEW_FTV_OPTIONS');
		$this->setSetting('FTV_OPTIONS', serialize($FTV_OPTIONS));
		Log::addConfigurationLog($this->getTitle() . ' config updated');
	}

	private function reset_options() {
		Zend_Session::writeClose();
		$FTV_OPTIONS = unserialize($this->getSetting('FTV_OPTIONS'));
		unset($FTV_OPTIONS[Filter::getInteger('tree')]);
		$this->setSetting('FTV_OPTIONS', serialize($FTV_OPTIONS));
		Log::addConfigurationLog($this->getTitle() . ' options set to default');
	}

	private function delete() {
		Zend_Session::writeClose();
		$FTV_SETTINGS = unserialize($this->getSetting('FTV_SETTINGS'));
		unset($FTV_SETTINGS[Filter::getInteger('key')]);
		$this->setSetting('FTV_SETTINGS', serialize($FTV_SETTINGS));
		Log::addConfigurationLog($this->getTitle() . ' item deleted');
	}

	private function message($id, $type, $message = '') {
		return
			'<div id="' . $id . '" class="alert alert-' . $type . ' alert-dismissible" style="display: none">' .
			'<button type="button" class="close" aria-label="' . I18N::translate('close') . '">' .
			'<span aria-hidden="true">&times;</span>' .
			'</button>' .
			'<span class="message">' . $message . '</span>' .
			'</div>';
	}

	// Radio buttons
	private function radio_buttons($name, $selected) {
		$values = array(
			0	 => I18N::translate('no'),
			1	 => I18N::translate('yes'),
		);

		return radio_buttons($name, $values, $selected, 'class="radio-inline"');
	}

	// Actions from the configuration page
	private function config() {
		global $WT_TREE;
		
		$controller = new PageController;
		$controller
			->restrictAccess(Auth::isAdmin())
			->setPageTitle('Fancy Tree View')
			->pageHeader()
			->addExternalJavascript(WT_AUTOCOMPLETE_JS_URL)
			->addInlineJavascript('autocomplete();');

		if (Filter::postBool('save') && Filter::checkCsrf()) {
			$this->update_settings($controller);
			$this->update_options();
		}

		// inline javascript
		$this->getAdminPageJS($controller);

		// get the settings for this tree
		$FTV_SETTINGS = unserialize($this->getSetting('FTV_SETTINGS'));
		?>

		<!-- ADMIN PAGE CONTENT -->
		<ol class="breadcrumb small">
			<li><a href="admin.php"><?php echo I18N::translate('Control panel'); ?></a></li>
			<li><a href="admin_modules.php"><?php echo I18N::translate('Module administration'); ?></a></li>
			<li class="active"><?php echo $controller->getPageTitle(); ?></li>
		</ol>
		<h2><?php echo $controller->getPageTitle(); ?></h2>
		<!-- *** FORM 1 *** -->
		<form class="form-horizontal" method="post" name="form1">
			<?php echo Filter::getCsrf(); ?>
			<input type="hidden" name="save" value="1">
			<!-- SELECT TREE -->
			<div class="form-group">
				<label class="control-label col-sm-1" for="tree">
					<?php echo I18N::translate('Family tree'); ?>
				</label>
				<div class="col-sm-4">
					<select id="tree" name="NEW_FIB_TREE" id="NEW_FIB_TREE" class="form-control">
						<?php foreach (Tree::getAll() as $tree): ?>
							<?php if ($tree->getTreeId() == WT_GED_ID): ?>
								<option value="<?php echo $tree->getTreeId(); ?>" data-ged="<?php echo $tree->getNameHtml(); ?>" selected="selected">
									<?php echo $tree->getTitleHtml(); ?>
								</option>
							<?php else: ?>
								<option value="<?php echo $tree->getTreeId(); ?>" data-ged="<?php echo $tree->getNameHtml(); ?>">
									<?php echo $tree->getTitleHtml(); ?>
								</option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		</form>
		<!-- PANEL GROUP ACCORDION -->
		<div class="panel-group" id="accordion">
			<!-- PANEL 1 -->
			<div class="panel panel-default" id="panel1">
				<div class="panel-heading">
					<h4 class="panel-title">
						<a data-toggle="collapse" data-target="#collapseOne" href="#collapseOne">
							<?php echo I18N::translate('Pages'); ?>
						</a>
					</h4>
				</div>
				<div id="collapseOne" class="panel-collapse collapse in">
					<div class="panel-body">
						<?php if (empty($FTV_SETTINGS) || (!empty($FTV_SETTINGS) && !$this->searchArray($FTV_SETTINGS, 'TREE', WT_GED_ID))): ?>
							<div class="alert alert-info alert-dismissible" role="alert">
								<button type="button" class="close" data-dismiss="alert" aria-label="' . I18N::translate('close') . '">
									<span aria-hidden="true">&times;</span>
								</button>
								<p class="small text-muted">
									<?php echo /* I18N: Help text for creating Fancy Tree View pages */ I18N::translate('Use the search form below to search for a root person. After a successfull search the Fancy Tree View page will be automatically created. You can add as many root persons as you want.'); ?>
								</p>
							</div>
						<?php endif; ?>
						<!-- *** FORM 2 *** -->
						<div id="ftv-search-form" class="form-group alert alert-info">
							<form class="form-inline" method="post" name="form2">
								<!-- SURNAME SEARCH FIELD -->
								<div class="form-group">
									<label class="control-label">
										<?php echo I18N::translate('Search root person'); ?>
									</label>
									<input
										class="form-control surname"
										data-autocomplete-type="SURN"
										dir="ltr"
										id="surname"
										name="surname"
										placeholder="<?php echo I18N::translate('Surname'); ?>"
										type="text"
										>
									<label class="checkbox-inline">
										<?php echo checkbox('soundex_std') . I18N::translate('Russell'); ?>
									</label>
									<label class="checkbox-inline">
										<?php echo checkbox('soudex_dm') . I18N::translate('Daitch-Mokotoff'); ?>
									</label>
								</div>
								<button name="search" class="btn btn-primary" type="submit">
									<i class="fa fa-search"></i>
									<?php echo I18N::translate('Search'); ?>
								</button>
							</form>
							<!-- *** FORM 3 *** -->
							<form class="form-horizontal" method="post" name="form3">
								<!-- TABLE -->
								<table id="search-result-table" class="table" style="display: none">
									<thead>
										<tr>
											<th><?php echo I18N::translate('Root person'); ?></th>
											<?php if (!$this->options('use_fullname')): ?>
												<th><?php echo I18N::translate('Surname in page title') ?></th>
											<?php endif; ?>
											<th><?php echo I18N::translate('Page title'); ?></th>
											<th><?php echo I18N::translate('Access level'); ?></th>
											<th><?php echo I18N::translate('Add'); ?></th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<!-- ROOT PERSONS FULL NAME -->
											<td id="root">
												<?php if ($this->options('use_fullname')): ?>
													<input
														id="surname"
														name="surname"
														type="hidden"
														value=""
														>
													<?php endif ?>
												<input
													id="pid"
													name="pid"
													type="hidden"
													value=""
													>
												<input
													id="sort"
													name="sort"
													type="hidden"
													value=""
													>
												<span></span>
											</td>
											<?php if (!$this->options('use_fullname')): ?>
												<!-- SURNAME IN PAGE TITLE -->
												<td id="surn">
													<label class="showname"></label>
													<input
														class="form-control editname"
														id="surname"
														name="surname"
														type="text"
														value=""
														>
												</td>
											<?php endif ?>
											<!-- PAGE TITLE -->
											<td id="title"></td>
											<!-- ACCESS LEVEL -->
											<td>
												<?php echo edit_field_access_level('access_level', 2, 'class="form-control"'); ?>
											</td>
											<!-- ADD BUTTON -->
											<td>
												<button	type="submit" name="add" class="btn btn-success btn-sm" title="<?php I18N::translate('Add'); ?>">
													<i class="fa fa-plus"></i>
												</button>
											</td>
										</tr>
									</tbody>
								</table>
							</form>
						</div>
						<?php echo $this->message("error", "danger"); ?>
						<div id="fancy-treeview-form" class="form-group">
							<?php if (!empty($FTV_SETTINGS) && $this->searchArray($FTV_SETTINGS, 'TREE', WT_GED_ID)): ?>
								<form class="form-horizontal" method="post" name="form4">
									<!-- TABLE -->
									<table id="fancy-treeview-table" class="table table-hover">
										<thead>
											<tr>
												<th><?php echo I18N::translate('Root person'); ?></th>
												<?php if (!$this->options('use_fullname')): ?>
													<th><?php echo I18N::translate('Surname in page title') ?></th>
												<?php endif; ?>
												<th><?php echo I18N::translate('Page title'); ?></th>
												<th><?php echo I18N::translate('Access level'); ?></th>
												<th><?php echo I18N::translate('Delete'); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ($FTV_SETTINGS as $key => $FTV_ITEM): ?>
												<?php if ($FTV_ITEM['TREE'] == WT_GED_ID): ?>
													<?php if (Individual::getInstance($FTV_ITEM['PID'])): ?>
														<tr class="sortme">
															<!-- ROOT PERSONS FULL NAME -->
															<td>
																<input
																	id="pid[<?php echo $key; ?>]"
																	name="pid[<?php echo $key; ?>]"
																	type="hidden"
																	value="<?php echo $FTV_ITEM['PID']; ?>"
																	>
																<input
																	id="sort[<?php echo $key; ?>]"
																	name="sort[<?php echo $key; ?>]"
																	type="hidden"
																	value="<?php echo $FTV_ITEM['SORT']; ?>"
																	>
																	<?php echo Individual::getInstance($FTV_ITEM['PID'])->getFullName() . ''; ?>
																(<?php echo Individual::getInstance($FTV_ITEM['PID'])->getLifeSpan(); ?>)
															</td>
															<?php if (!$this->options('use_fullname')): ?>
																<!-- SURNAME IN PAGE TITLE -->
																<td>
																	<label class="showname">
																		<?php echo $FTV_ITEM['SURNAME']; ?>
																	</label>
																	<input
																		class="form-control editname"
																		id="surname[<?php echo $key; ?>]"
																		name="surname[<?php echo $key; ?>]"
																		type="text"
																		value="<?php echo $FTV_ITEM['SURNAME']; ?>"
																		>
																</td>
															<?php endif ?>
															<!-- PAGE TITLE -->
															<td>
																<a href="module.php?mod=<?php echo $this->getName(); ?>&amp;mod_action=show&amp;ged=<?php echo $WT_TREE->getNameHtml(); ?>&amp;rootid=<?php echo $FTV_ITEM['PID']; ?>" target="_blank">
																	<?php
																	if ($this->options('use_fullname') == true) {
																		echo I18N::translate('Descendants of %s', Individual::getInstance($FTV_ITEM['PID'])->getFullName());
																	} else {
																		echo I18N::translate('Descendants of the %s family', $FTV_ITEM['SURNAME']);
																	}
																	?>
																</a>
															</td>
															<!-- ACCESS LEVEL -->
															<td>
																<?php echo edit_field_access_level('access_level[' . $key . ']', $FTV_ITEM['ACCESS_LEVEL'], 'class="form-control"'); ?>
															</td>
															<!-- DELETE BUTTON -->
															<td>
																<button	type="button" name="delete" class="btn btn-danger btn-sm" data-key="<?php echo $key ?>" title="<?php I18N::translate('Delete'); ?>">
																	<i class="fa fa-trash-o"></i>
																</button>
															</td>
														</tr>
													<?php else: ?>
														<tr>
															<!-- SURNAME -->
															<td class="error">
																<input
																	name="pid[<?php echo $key; ?>]"
																	type="hidden"
																	value="<?php echo $FTV_ITEM['PID']; ?>"
																	>
																	<?php echo $FTV_ITEM['SURNAME']; ?>
															</td>
															<!-- ERROR MESSAGE -->
															<td colspan="4" class="error">
																<?php echo I18N::translate('The person with root id %s doesn’t exist anymore in this tree', $FTV_ITEM['PID']); ?>
															</td>
															<!-- DELETE BUTTON -->
															<td>
																<button name="delete" type="button" class="btn btn-danger btn-sm" title="<?php I18N::translate('Delete'); ?>">
																	<i class="fa fa-trash-o"></i>
																</button>
															</td>
														</tr>
													<?php endif; ?>
												<?php endif; ?>
											<?php endforeach; ?>
										</tbody>
									</table>
									<!-- BUTTONS -->
									<button name="update" class="btn btn-primary" type="submit">
										<i class="fa fa-check"></i>
										<?php echo I18N::translate('Update'); ?>
									</button>
								</form>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

			<!-- PANEL 2 -->
			<div class="panel panel-default" id="panel2">
				<div class="panel-heading">
					<h4 class="panel-title">
						<a data-toggle="collapse" data-target="#collapseTwo" href="#collapseTwo" class="collapsed">
							<!-- Dynamic text here -->
						</a>
					</h4>
				</div>
				<div id="collapseTwo" class="panel-collapse collapse">
					<div class="panel-body">
						<?php echo $this->message('save-options', 'success', I18N::translate('The options for this tree are succesfully saved')); ?>
						<?php echo $this->message('reset-options', 'success', I18N::translate('The options for this tree are succesfully reset to the default settings')); ?>
						<div id="ftv-options-form" class="form-group">
							<form class="form-horizontal" method="post" name="form5">
								<!-- USE FULLNAME IN MENU -->
								<div class="form-group fullname">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Use fullname in menu'); ?>
									</label>
									<div class="col-sm-8">
										<?php echo $this->radio_buttons('NEW_FTV_OPTIONS[USE_FULLNAME]', $this->options('use_fullname')); ?>
									</div>
								</div>
								<!-- GENERATION BLOCKS -->
								<div class="form-group">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Number of generation blocks to show'); ?>
									</label>
									<div class="col-sm-4">
										<?php echo select_edit_control('NEW_FTV_OPTIONS[NUMBLOCKS]', array(I18N::translate('All'), '1', '2', '3', '4', '5', '6', '7', '8', '9', '10'), null, $this->options('numblocks'), 'class="form-control"'); ?>									</div>
									<p class="col-sm-8 col-sm-offset-4 small text-muted">
										<?php echo /* I18N: Help text for the “Number of generation blocks to show” configuration setting */ I18N::translate('This option is especially usefull for large trees. When you notice a slow page load, here you can set the number of generation blocks to load at once to a lower level. Below the last generation block a button will appear to add the next set of generation blocks. The new blocks will be added to the blocks already loaded. Clicking on a “follow” link in the last visible generation block, will also load the next set of generation blocks.'); ?>
									</p>
								</div>
								<!-- CHECK RELATIONSHIP -->
								<div class="form-group">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Check relationship between partners'); ?>
									</label>
									<div class="col-sm-8">
										<?php echo $this->radio_buttons('NEW_FTV_OPTIONS[CHECK_RELATIONSHIP]', $this->options('check_relationship')); ?>
									</div>
									<p class="col-sm-8 col-sm-offset-4 small text-muted">
										<?php echo /* I18N: Help text for the “Check relationship between partners” configuration setting */ I18N::translate('With this option turned on, the script checks if a (married) couple has the same ancestors. If a relationship between the partners is found, a text will appear between brackets after the spouses’ name to indicate the relationship. Note: this option can cause slower page loading, especially on large trees. If you notice such a behavior, reduce the number of generation blocks to load at once (see the previous option).'); ?>
									</p>
								</div>
								<!-- SHOW SINGLES -->
								<div class="form-group">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Show single persons'); ?>
									</label>
									<div class="col-sm-8">
										<?php echo $this->radio_buttons('NEW_FTV_OPTIONS[SHOW_SINGLES]', $this->options('show_singles')); ?>									</div>
									<p class="col-sm-8 col-sm-offset-4 small text-muted">
										<?php echo /* I18N: Help text for the “Show single persons” configuration setting */ I18N::translate('Turn this option on if you want to show single persons in the generation blocks. Single persons are persons without partner and children. With this option turned on, every child of a family will be shown in a detailed way in the next generation block.'); ?>
									</p>
								</div>
								<!-- SHOW PLACES -->
								<div id="places" class="form-group">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Show places?'); ?>
									</label>
									<div class="col-sm-8">
										<?php echo $this->radio_buttons('NEW_FTV_OPTIONS[SHOW_PLACES]', $this->options('show_places')); ?>
									</div>
								</div>
								<!-- USE GEDCOM PLACE SETTING -->
								<div id="gedcom_places" class="form-group <?php if (!$this->options('show_places')) echo 'hidden' ?>">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Use default Gedcom settings to abbreviate place names?'); ?>
									</label>
									<div class="col-sm-8">
										<?php echo $this->radio_buttons('NEW_FTV_OPTIONS[USE_GEDCOM_PLACES]', $this->options('use_gedcom_places')); ?>
									</div>
									<p class="col-sm-8 col-sm-offset-4 small text-muted">
										<?php echo /* I18N: Help text for the “Use default Gedcom settings to abbreviate place names” configuration setting */ I18N::translate('If you have ticked the “Show places” option, you can choose to use the default Gedcom settings to abbreviate placenames. If you don’t set this option, full place names will be shown.'); ?>
									</p>
								</div>
								<!-- GET COUNTRYLIST -->
								<?php if ($this->getCountrylist()): ?>
									<div id="country_list" class="form-group <?php if (!$this->options('show_places') || $this->options('use_gedcom_places')) echo 'hidden' ?>">
										<label class="control-label col-sm-4">
											<?php echo I18N::translate('Select your country'); ?>
										</label>
										<div class="col-sm-8">
											<?php echo select_edit_control('NEW_FTV_OPTIONS[COUNTRY]', $this->getCountryList(), '', $this->options('country'), 'class="form-control"'); ?>
										</div>
										<p class="col-sm-8 col-sm-offset-4 small text-muted">
											<?php echo /* I18N: Help text for the “Select your country” configuration setting */ I18N::translate('If you have ticked the “Show places” option but NOT the option to abbreviate placenames, you can set your own country here. Full places will be listed on the Fancy Tree View pages, but when a place includes the name of your own country, this name will be left out. If you don’t select a country then all countries will be shown, including your own.'); ?>
										</p>
									</div>
								<?php endif; ?>
								<!-- SHOW OCCUPATIONS -->
								<div class="form-group">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Show occupations'); ?>
									</label>
									<div class="col-sm-8">
										<?php echo $this->radio_buttons('NEW_FTV_OPTIONS[SHOW_OCCU]', $this->options('show_occu')); ?>
									</div>
								</div>
								<!-- RESIZE THUMBS -->
								<div id="resize_thumbs" class="form-group">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Resize thumbnails'); ?>
									</label>
									<div class="col-sm-8">
										<?php echo $this->radio_buttons('NEW_FTV_OPTIONS[RESIZE_THUMBS]', $this->options('resize_thumbs')); ?>
									</div>
									<p class="col-sm-8 col-sm-offset-4 small text-muted">
										<?php echo /* I18N: Help text for the “Use default Gedcom settings to abbreviate place names” configuration setting */ I18N::translate('Here you can choose to resize the default webtrees thumbnails especially for the Fancy Tree View pages. You can set a custom size in percentage or in pixels. If you choose “no” the default webtrees thumbnails will be used with the formats you have set on the tree configuration page.'); ?>									</p>
								</div>
								<!-- THUMB SIZE -->
								<div id="thumb_size" class="form-group <?php if (!$this->options('resize_thumbs')) echo 'hidden' ?>">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Thumbnail size'); ?>
									</label>
									<div class="row">
										<div class="col-sm-1">
											<input
												class="form-control"
												id="NEW_FTV_OPTIONS[THUMB_SIZE]"
												name="NEW_FTV_OPTIONS[THUMB_SIZE]"
												type="text"
												value="<?php echo $this->options('thumb_size'); ?>"
												>
										</div>
										<div class="col-sm-2">
											<?php echo select_edit_control('NEW_FTV_OPTIONS[THUMB_RESIZE_FORMAT]', array('1' => I18N::translate('percent'), '2' => I18N::translate('pixels')), null, $this->options('thumb_resize_format'), 'class="form-control"'); ?>
										</div>
									</div>
								</div>
								<!-- SQUARE THUMBS -->
								<div id="square_thumbs" class="form-group <?php if (!$this->options('resize_thumbs')) echo 'hidden' ?>">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Use square thumbnails'); ?>
									</label>
									<div class="col-sm-8">
										<?php echo $this->radio_buttons('NEW_FTV_OPTIONS[USE_SQUARE_THUMBS]', $this->options('use_square_thumbs')); ?>
									</div>
								</div>
								<!-- SHOW USERFORM -->
								<div class="form-group">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Show form to change start person'); ?>
									</label>
									<div class="col-sm-4">
										<?php echo edit_field_access_level('NEW_FTV_OPTIONS[SHOW_USERFORM]', $this->options('show_userform'), 'class="form-control"'); ?>
									</div>
								</div>
								<!-- SHOW PDF -->
								<div class="form-group">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Show PDF icon?'); ?>
									</label>
									<div class="col-sm-4">
										<?php echo edit_field_access_level('NEW_FTV_OPTIONS[SHOW_PDF_ICON]', $this->options('show_pdf_icon'), 'class="form-control"'); ?>
									</div>
									<p class="col-sm-8 col-sm-offset-4 small text-muted">
										<?php echo /* I18N: Help text for the “Show PDF icon” configuration setting */ I18N::translate('Currently the PDF option is only supported for LTR-languages. These are all languages in which the text is read from left to right. The PDF icon will be disabled when the user selects a RTL-language. In a RTL language the text is read from right to left.'); ?>
									</p>
								</div>
								<!-- BUTTONS -->
								<button name="save-options" class="btn btn-primary" type="submit">
									<i class="fa fa-check"></i>
									<?php echo I18N::translate('Save'); ?>
								</button>
								<button name="reset-options" class="btn btn-primary" type="reset">
									<i class="fa fa-recycle"></i>
									<?php echo I18N::translate('Reset'); ?>
								</button>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	// ************************************************* START OF FRONT PAGE ********************************* //
	// Show
	private function show() {
		global $controller, $TEXT_DIRECTION;
		$root = Filter::get('rootid', WT_REGEX_XREF); // the first pid
		$root_person = $this->get_individual($root);

		$controller = new PageController;
		if ($root_person && $root_person->canShowName()) {
			$controller
				->setPageTitle(/* I18N: %s is the surname of the root individual */ I18N::translate('Descendants of %s', $root_person->getFullName()))
				->pageHeader()
				->addExternalJavascript(WT_STATIC_URL . 'js/autocomplete.js')
				->addInlineJavascript('
					var pastefield; function paste_id(value) { pastefield.value=value; } // For the \'find indi\' link
					// setup numbers for scroll reference
					function addScrollNumbers() {
						jQuery(".generation-block:visible").each(function(){
							var gen = jQuery(this).data("gen");
							jQuery(this).find("a.scroll").each(function(){
								if(jQuery(this).text() == "" || jQuery(this).hasClass("add_num")) {
									var id = jQuery(this).attr("href");
									var fam_id = jQuery(id);
									var fam_id_index = fam_id.index() + 1;
									var gen_id_index = fam_id.parents(".generation-block").data("gen");
									if(fam_id.length > 0) {
										jQuery(this).text("' . I18N::translate('follow') . ' " + gen_id_index + "." + fam_id_index).removeClass("add_num");
									}
									else { // fam to follow is in a generation block after the last hidden block.
										jQuery(this).text("' . I18N::translate('follow') . '").addClass("add_num");
									}
								}
							});
						});
						if (jQuery(".generation-block.hidden").length > 0) { // there are next generations so prepare the links
							jQuery(".generation-block.hidden").prev().find("a.scroll").not(".header-link").addClass("link_next").removeClass("scroll");
						}
					}

					// remove button if there are no more generations to catch
					function btnRemove() {
						if (jQuery(".generation-block.hidden").length == 0) { // if there is no hidden block there is no next generation.
							jQuery("#btn_next").remove();
						}
					}

					// set style dynamically on parents blocks with an image
					function setImageBlock() {
						jQuery(".parents").each(function(){
							if(jQuery(this).find(".gallery").length > 0) {
								var height = jQuery(this).find(".gallery img").height() + 10 + "px";
								jQuery(this).css({"min-height" : height});
							}
						});
					}

					// Hide last generation block (only needed in the DOM for scroll reference. Must be set before calling addScrollNumbers function.)
					var numBlocks = ' . $this->options('numblocks') . ';
					var lastBlock = jQuery(".generation-block:last");
					if(numBlocks > 0 && lastBlock.data("gen") > numBlocks) {
						lastBlock.addClass("hidden").hide();
					}

					// add scroll numbers to visible generation blocks when page is loaded
					addScrollNumbers();

					// Remove button if there are no more generations to catch
					btnRemove();

					// Set css class on parents blocks with an image
					setImageBlock();

					// remove the empty hyphen on childrens lifespan if death date is unknown.
					jQuery("li.child .lifespan").html(function(index, html){
					    // this does not work without &nbsp;
						return html.replace("–<span title=\"&nbsp;\"></span>", "");
					});

					// prevent duplicate id\'s
					jQuery("li.family[id]").each(function(){
						var family = jQuery("[id="+this.id+"]");
						if(family.length>1){
							i = 1;
							family.each(function(){
							 	famID = jQuery(this).attr("id");
							  	anchor = jQuery("#fancy_treeview a.scroll[href$="+this.id+"]:first");
							  	anchor.attr("href", "#" + famID + "_" + i);
							  	jQuery(this).attr("id", famID + "_" + i);
							 	i++;
							});
						}
					});

					// scroll to anchors
					jQuery("#fancy_treeview-page").on("click", ".scroll", function(event){
						var id = jQuery(this).attr("href");
						if(jQuery(id).is(":hidden") || jQuery(id).length === 0) {
							jQuery(this).addClass("link_next").trigger("click");
							return false;
						}
						var offset = 60;
						var target = jQuery(id).offset().top - offset;
						jQuery("html, body").animate({scrollTop:target}, 1000);
						event.preventDefault();
					});

					// Print extra information about the non-married spouse (the father/mother of the children) in a tooltip
					jQuery(".tooltip").each(function(){
						var text = jQuery(this).next(".tooltip-text").html();
						jQuery(this).tooltip({
						   items: "[title]",
						   content: function() {
							 return text;
						   }
						});
					});

					//button or link to retrieve next generations
					jQuery("#fancy_treeview-page").on("click", "#btn_next, .link_next", function(event){
						if(jQuery(this).hasClass("link_next")) { // prepare for scrolling after new blocks are loaded
							var id = jQuery(this).attr("href");
							scroll = true
						}
						jQuery(".generation-block.hidden").remove(); // remove the last hidden block to retrieve the correct data from the previous last block
						var lastBlock = jQuery(".generation-block:last");
						var pids = lastBlock.data("pids");
						var gen  = lastBlock.data("gen");
						var url = jQuery(location).attr("pathname") + "?mod=' . $this->getName() . '&mod_action=show&rootid=' . $root . '&gen=" + gen + "&pids=" + pids;
						lastBlock.find("a.link_next").addClass("scroll").removeClass("link_next");
						lastBlock.after("<div class=\"loading-image\">");
						jQuery("#btn_next").hide();
						jQuery.get(url,
							function(data){

								var data = jQuery(data).find(".generation-block");
								jQuery(lastBlock).after(data);

								var count = data.length;
								if(count == ' . $this->options('numblocks') . ' + 1) {
									jQuery(".generation-block:last").addClass("hidden").hide(); // hidden block must be set before calling addScrollNumbers function.
								}

								// scroll
								addScrollNumbers();
								if (scroll == true) {
									var offset = 60;
									var target = jQuery(id).offset().top - offset;
									jQuery("html, body").animate({scrollTop:target}, 1000);
								}

								jQuery(".loading-image").remove();
								jQuery("#btn_next").show();

								// check if button has to be removed
								btnRemove();

								// check for parents blocks with images
								setImageBlock();
							}
						);
					});
				');

			if ($this->options('show_pdf_icon') >= WT_USER_ACCESS_LEVEL && $TEXT_DIRECTION == 'ltr') {
				$controller->addInlineJavascript('
						// convert page to pdf
						jQuery("#pdf").click(function(e){
							if (jQuery("#btn_next").length > 0) {
								jQuery("#dialog-confirm").dialog({
									resizable: false,
									width: 300,
						  			modal: true,
									buttons : {
										"' . I18N::translate('OK') . '" : function() {
											getPDF();
											jQuery(this).dialog("close");
										},
										"' . I18N::translate('Cancel') . '" : function() {
											jQuery(this).dialog("close");
										}
									}
								});
							}
							else {
								getPDF();
							}
						});

						function getPDF() {
							// get image source for default webtrees thumbs
							if(jQuery(".ftv-thumb").length == 0) {
								function qstring(key, url) {
									KeysValues = url.split(/[\?&]+/);
									for (i = 0; i < KeysValues.length; i++) {
										KeyValue= KeysValues[i].split("=");
										if (KeyValue[0] == key) {
											return KeyValue[1];
										}
									}
								}
								jQuery("a.gallery img").each(function(){
									var obj = jQuery(this);
									var src = obj.attr("src");
									var mid = qstring("mid", src);
									jQuery.ajax({
										type: "GET",
										url: "module.php?mod=' . $this->getName() . '&mod_action=image_data&mid=" + mid,
										async: false,
										success: function(data) {
											obj.addClass("wt-thumb").attr("src", data);
										}
									});
								});
							}

							// clone the content now
							var content = jQuery("#content").clone();

							//put image back behind the mediafirewall
							jQuery(".wt-thumb").each(function(){
								jQuery(this).attr("src", jQuery(this).parent().data("obje-url") + "&thumb=1");
							});

							//dompdf does not support ordered list, so we make our own
							jQuery(".generation-block", content).each(function(index) {
								var main = (index+1);
								jQuery(this).find(".generation").each(function(){
									jQuery(this).find("li.family").each(function(index){
										var i = (index+1)
										jQuery(this).find(".parents").prepend("<td class=\"index\">" + main + "." + i + ".</td>");
										jQuery(this).find("li.child").each(function(index) {
											jQuery(this).prepend("<span class=\"index\">" + main + "." + i + "." + (index+1) + ".</span>");
										});
									});
								});
							});

							// remove or unwrap all elements we do not need in pdf display
							jQuery("#pdf, form, #btn_next, #error, .header-link, .hidden, .tooltip-text", content).remove();
							jQuery(".generation.private", content).parents(".generation-block").remove();
							jQuery("a, span.SURN, span.date", content).contents().unwrap();
							jQuery("a", content).remove() //left-overs

							// Turn family blocks into a table for better display in pdf
							jQuery("li.family", content).each(function(){
								var obj = jQuery(this);
								obj.find(".desc").replaceWith("<td class=\"desc\">" + obj.find(".desc").html());
								obj.find("img").wrap("<td class=\"image\" style=\"width:" + obj.find("img").width() + "px\">");
								obj.find(".parents").replaceWith("<table class=\"parents\"><tr>" + obj.find(".parents").html());
							});

							var newContent = content.html();

							jQuery.ajax({
								type: "POST",
								url: "module.php?mod=' . $this->getName() . '&mod_action=pdf_data",
								data: { "pdfContent": newContent },
								csrf: WT_CSRF_TOKEN,
								success: function() {
									window.location.href = "module.php?mod=' . $this->getName() . '&mod_action=show_pdf&rootid=' . Filter::get('rootid') . '&title=' . urlencode(strip_tags($controller->getPageTitle())) . '#page=1";
								}
							});
						}
					');
			}

			if ($this->options('show_userform') >= WT_USER_ACCESS_LEVEL) {
				$controller->addInlineJavascript('
						jQuery("#new_rootid").autocomplete({
							source: "autocomplete.php?field=INDI",
							html: true
						});

						// submit form to change root id
						jQuery( "form#change_root" ).submit(function(e) {
							e.preventDefault();
							var new_rootid = jQuery("form #new_rootid").val();
							var url = jQuery(location).attr("pathname") + "?mod=' . $this->getName() . '&mod_action=show&rootid=" + new_rootid;
							jQuery.ajax({
								url: url,
								csrf: WT_CSRF_TOKEN,
								success: function() {
									window.location = url;
								},
								statusCode: {
									404: function() {
										var msg = "' . I18N::translate('This individual does not exist or you do not have permission to view it.') . '";
										jQuery("#error").text(msg).addClass("ui-state-error").show();
										setTimeout(function() {
											jQuery("#error").fadeOut("slow");
										}, 3000);
										jQuery("form #new_rootid")
											.val("")
											.focus();
									}
								}
							});
						});
					');
			}

			// add theme js
			$html = $this->includeJs();

			// Start page content
			$html .= '
					<div id="fancy_treeview-page">
						<div id="page-header"><h2>' . $controller->getPageTitle() . '</h2>';
			if ($this->options('show_pdf_icon') >= WT_USER_ACCESS_LEVEL && $TEXT_DIRECTION == 'ltr') {
				$html .= '
									<div id="dialog-confirm" title="' . I18N::translate('Generate PDF') . '" style="display:none">
										<p>' . I18N::translate('The pdf contains only visible generation blocks.') . '</p>
									</div>
									<a id="pdf" href="#"><i class="icon-mime-application-pdf"></i></a>';
			}
			$html .= '</div>
				<div id="page-body">';
			if ($this->options('show_userform') >= WT_USER_ACCESS_LEVEL) {
				$html .= '
							<form id="change_root">
								<label class="label">' . I18N::translate('Change root person') . '</label>
								<input type="text" name="new_rootid" id="new_rootid" size="10" maxlength="20" placeholder="' . I18N::translate('ID') . '"/>' .
					print_findindi_link('new_rootid') . '
								<input type="submit" id="btn_go" value="' . I18N::translate('Go') . '" />
							</form>
						<div id="error"></div>';
			}
			$html .= '
							<ol id="fancy_treeview">' . $this->print_page() . '</ol>
							<div id="btn_next"><input type="button" name="next" value="' . I18N::translate('next') . '"/></div>
						</div>
					</div>';

			// output
			ob_start();
			$html .= ob_get_clean();
			echo $html;
		} else {
			header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
			$controller->pageHeader();
			echo '<p class="ui-state-error">', I18N::translate('This individual does not exist or you do not have permission to view it.'), '</p>';
			exit;
		}
	}

	// Print functions
	private function print_page() {
		$root = Filter::get('rootid', WT_REGEX_XREF);
		$gen = Filter::get('gen', WT_REGEX_INTEGER);
		$pids = Filter::get('pids');
		$numblocks = $this->options('numblocks');

		if ($numblocks == 0) {
			$numblocks = 99;
		}

		$html = '';
		if (!isset($gen) && !isset($pids)) {
			$gen = 1;
			$numblocks = $numblocks - 1;
			$generation = array($root);
			$html .= $this->print_generation($generation, $gen);
		} else {
			$generation = explode('|', $pids);
		}

		$lastblock = $gen + $numblocks + 1; // + 1 to get one hidden block.
		while (count($generation) > 0 && $gen < $lastblock) {
			$pids = $generation;
			unset($generation);

			foreach ($pids as $pid) {
				$next_gen[] = $this->get_next_gen($pid);
			}

			foreach ($next_gen as $descendants) {
				if (count($descendants) > 0) {
					foreach ($descendants as $descendant) {
						if ($this->options('show_singles') == true || $descendant['desc'] == 1) {
							$generation[] = $descendant['pid'];
						}
					}
				}
			}

			if (!empty($generation)) {
				$gen++;
				$html .= $this->print_generation($generation, $gen);
				unset($next_gen, $descendants, $pids);
			} else {
				break;
			}
		}
		return $html;
	}

	private function print_generation($generation, $i) {
		
		// added data attributes to retrieve values easily with jquery (for scroll reference en next generations).
		$html = '<li class="block generation-block" data-gen="' . $i . '" data-pids="' . implode('|', $generation) . '">
					<div class="blockheader ui-state-default"><span class="header-title">' . I18N::translate('Generation') . ' ' . $i . '</span>';
		if ($i > 1) {
			$html .= '<a href="#body" class="header-link scroll">' . I18N::translate('back to top') . '</a>';
		}
		$html .= '	</div>';

		if ($this->check_privacy($generation, true)) {
			$html .= '<div class="blockcontent generation private">' . I18N::translate('The details of this generation are private.') . '</div>';
		} else {
			$html .= '<ol class="blockcontent generation">';
			$generation = array_unique($generation); // needed to prevent the same family added twice to the generation block (this is the case when parents have the same ancestors and are both members of the previous generation).

			foreach ($generation as $pid) {
				$individual = $this->get_individual($pid);

				// only list persons without parents in the same generation - if they have they will be listed in the next generation anyway.
				// This prevents double listings
				if (!$this->has_parents_in_same_generation($individual, $generation)) {
					$family = $this->get_family($individual);
					if (!empty($family)) {
						$id = $family->getXref();
					} else {
						if ($this->options('show_singles') == true || !$individual->getSpouseFamilies()) {
							$id = 'S' . $pid;
						} // Added prefix (S = Single) to prevent double id's.
					}
					$class = $individual->canShow() ? 'family' : 'family private';
					$html .= '<li id="' . $id . '" class="' . $class . '">' . $this->print_individual($individual) . '</li>';
				}
			}
			$html .= '</ol></li>';
		}
		return $html;
	}

	private function print_individual($individual) {
		global $WT_TREE;

		if ($individual->CanShow()) {
			$resize = $this->options('resize_thumbs') == 1 ? true : false;
			$html = '<div class="parents">' . $this->print_thumbnail($individual, $this->options('thumb_size'), $this->options('thumb_resize_format'), $this->options('use_square_thumbs'), $resize) . '<a id="' . $individual->getXref() . '" href="' . $individual->getHtmlUrl() . '"><p class="desc">' . $individual->getFullName() . '</a>';
			if ($this->options('show_occu') == true) {
				$html .= $this->print_fact($individual, 'OCCU');
			}

			$html .= $this->print_parents($individual) . $this->print_lifespan($individual);

			// get a list of all the spouses
			/*
			 * First, determine the true number of spouses by checking the family gedcom
			 */
			$spousecount = 0;
			foreach ($individual->getSpouseFamilies(WT_PRIV_HIDE) as $i => $family) {
				$spouse = $family->getSpouse($individual);
				if ($spouse && $spouse->canShow() && $this->getMarriage($family)) {
					$spousecount++;
				}
			}
			/*
			 * Now iterate thru spouses
			 * $spouseindex is used for ordinal rather than array index
			 * as not all families have a spouse
			 * $spousecount is passed rather than doing each time inside function get_spouse
			 */
			if ($spousecount > 0) {
				$spouseindex = 0;
				foreach ($individual->getSpouseFamilies(WT_PRIV_HIDE) as $i => $family) {
					$spouse = $family->getSpouse($individual);
					if ($spouse && $spouse->canShow() && $this->getMarriage($family)) {
						$html .= $this->print_spouse($family, $individual, $spouse, $spouseindex, $spousecount);
						$spouseindex++;
					}
				}
			}

			$html .= '</p></div>';

			// get children for each couple (could be none or just one, $spouse could be empty, includes children of non-married couples)
			foreach ($individual->getSpouseFamilies(WT_PRIV_HIDE) as $family) {
				$spouse = $family->getSpouse($individual);
				$html .= $this->print_children($family, $individual, $spouse);
			}

			return $html;
		} else {
			if ($WT_TREE->getPreference('SHOW_PRIVATE_RELATIONSHIPS')) {
				return I18N::translate('The details of this family are private.');
			}
		}
	}

	private function print_spouse($family, $individual, $spouse, $i, $count) {

		$html = ' ';

		if ($count > 1) {
			// we assume no one married more then five times.
			$wordcount = array(
				I18N::translate('first'),
				I18N::translate('second'),
				I18N::translate('third'),
				I18N::translate('fourth'),
				I18N::translate('fifth')
			);
			switch ($individual->getSex()) {
				case 'M':
					if ($i == 0) {
						$html .= /* I18N: %s is a number  */ I18N::translate('He married %s times', $count) . '. ';
					}
					$html .= /* I18N: %s is an ordinal */ I18N::translate('The %s time he married', $wordcount[$i]);
					break;
				case 'F':
					if ($i == 0) {
						$html .= /* I18N: %s is a number  */ I18N::translate('She married %s times', $count) . '. ';
					}
					$html .= /* I18N: %s is an ordinal */ I18N::translate('The %s time she married', $wordcount[$i]);
					break;
				default:
					if ($i == 0) {
						$html .= /* I18N: %s is a number  */ I18N::translate('This individual married %s times', $count) . '. ';
					}
					$html .= /* I18N: %s is an ordinal */ I18N::translate('The %s time this individual married', $wordcount[$i]);
					break;
			}
		} else {
			switch ($individual->getSex()) {
				case 'M':
					$html .= I18N::translate('He married');
					break;
				case 'F':
					$html .= I18N::translate('She married');
					break;
				default:
					$html .= I18N::translate('This individual married');
					break;
			}
		}

		$html .= ' <a href="' . $spouse->getHtmlUrl() . '">' . $spouse->getFullName() . '</a>';

		// Add relationship note
		if ($this->options('check_relationship')) {
			$relationship = $this->check_relationship($individual, $spouse, $family);
			if ($relationship) {
				$html .= ' (' . $relationship . ')';
			}
		}

		$html .= $this->print_parents($spouse);

		if (!$family->getMarriage()) { // use the default privatized function to determine if marriage details can be shown.
			$html .= '.';
		} else {
			// use the facts below only on none private records.
			if ($this->print_parents($spouse)) {
				$html .= ',';
			}
			$marrdate = $family->getMarriageDate();
			$marrplace = $family->getMarriagePlace();
			if ($marrdate && $marrdate->isOK()) {
				$html .= $this->print_date($marrdate);
			}
			if ($marrplace->getGedcomName()) {
				$html .= $this->print_place($marrplace->getGedcomName(), $family->getTree());
			}
			$html .= $this->print_lifespan($spouse, true);

			$div = $family->getFirstFact('DIV');
			if ($div) {
				$html .= $individual->getFullName() . ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ') . $spouse->getFullName() . ' ' . I18N::translate('were divorced') . $this->print_divorce_date($div) . '.';
			}
		}
		return $html;
	}

	private function print_children($family, $individual, $spouse) {
		$html = '';

		$match = null;
		if (preg_match('/\n1 NCHI (\d+)/', $family->getGedcom(), $match) && $match[1] == 0) {
			$html .= '<div class="children"><p>' . $individual->getFullName() . ' ';
			if ($spouse && $spouse->CanShow()) {
				$html .= /* I18N: Note the space at the end of the string */ I18N::translate('and ') . $spouse->getFullName() . ' ';
				$html .= I18N::translate_c('Two parents/one child', 'had');
			} else {
				$html .= I18N::translate_c('One parent/one child', 'had');
			}
			$html .= ' ' . I18N::translate('none') . ' ' . I18N::translate('children') . '.</p></div>';
		} else {
			$children = $family->getChildren();
			if ($children) {
				if ($this->check_privacy($children)) {
					$html .= '<div class="children"><p>' . $individual->getFullName() . ' ';
					// needs multiple translations for the word 'had' to serve different languages.
					if ($spouse && $spouse->CanShow()) {
						$html .= /* I18N: Note the space at the end of the string */ I18N::translate('and ') . $spouse->getFullName() . ' ';
						if (count($children) > 1) {
							$html .= I18N::translate_c('Two parents/multiple children', 'had');
						} else {
							$html .= I18N::translate_c('Two parents/one child', 'had');
						}
					} else {
						if (count($children) > 1) {
							$html .= I18N::translate_c('One parent/multiple children', 'had');
						} else {
							$html .= I18N::translate_c('One parent/one child', 'had');
						}
					}
					$html .= ' ' . /* I18N: %s is a number */ I18N::plural('%s child', '%s children', count($children), count($children)) . '.</p></div>';
				} else {
					$html .= '<div class="children"><p>' . I18N::translate('Children of ') . $individual->getFullName();
					if ($spouse && $spouse->CanShow()) {
						$html .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
						if (!$family->getMarriage()) {
							// check relationship first (If a relationship is found the information of this parent is printed elsewhere on the page.)
							if ($this->options('check_relationship')) {
								$relationship = $this->check_relationship($individual, $spouse, $family);
							}
							if (isset($relationship) && $relationship) {
								$html .= $spouse->getFullName() . ' (' . $relationship . ')';
							} else {
								// the non-married spouse is not mentioned in the parents div text or elsewhere on the page. So put a link behind the name.
								$html .= '<a class="tooltip" title="" href="' . $spouse->getHtmlUrl() . '">' . $spouse->getFullName() . '</a>';
								// Print info of the non-married spouse in a tooltip
								$html .= '<span class="tooltip-text">' . $this->print_tooltip($spouse) . '</span>';
							}
						} else {
							$html .= $spouse->getFullName();
						}
					}
					$html .= ':<ol>';

					foreach ($children as $child) {
						$html .= '<li class="child"><a href="' . $child->getHtmlUrl() . '">' . $child->getFullName() . '</a>';
						$pedi = $this->check_pedi($child, $family);

						if ($pedi) {
							$html .= ' <span class="pedi"> - ';
							switch ($pedi) {
								case 'foster':
									switch ($child->getSex()) {
										case 'F':
											$html .= I18N::translate_c('FEMALE', 'foster child');
											break;
										default:
											$html .= I18N::translate_c('MALE', 'foster child');
											break;
									}
									break;
								case 'adopted':
									switch ($child->getSex()) {
										case 'F':
											$html .= I18N::translate_c('FEMALE', 'adopted child');
											break;
										default:
											$html .= I18N::translate_c('MALE', 'adopted child');
											break;
									}
									break;
							}
							$html .= '</span>';
						}

						if ($child->CanShow() && ($child->getBirthDate()->isOK() || $child->getDeathdate()->isOK())) {
							$html .= '<span class="lifespan"> (' . $child->getLifeSpan() . ')</span>';
						}

						$child_family = $this->get_family($child);
						if ($child->canShow() && $child_family) {
							$html .= ' - <a class="scroll" href="#' . $child_family->getXref() . '"></a>';
						} else { // just go to the person details in the next generation (added prefix 'S'for Single Individual, to prevent double ID's.)
							if ($this->options('show_singles') == true) {
								$html .= ' - <a class="scroll" href="#S' . $child->getXref() . '"></a>';
							}
						}
						$html .= '</li>';
					}
					$html .= '</ol></div>';
				}
			}
		}
		return $html;
	}

	private function print_parents($individual) {
		$parents = $individual->getPrimaryChildFamily();
		if ($parents) {
			$pedi = $this->check_pedi($individual, $parents);

			$html = '';
			switch ($individual->getSex()) {
				case 'M':
					if ($pedi === 'foster') {
						$html .= ', ' . I18N::translate('foster son of') . ' ';
					} elseif ($pedi === 'adopted') {
						$html .= ', ' . I18N::translate('adopted son of') . ' ';
					} else {
						$html .= ', ' . I18N::translate('son of') . ' ';
					}
					break;
				case 'F':
					if ($pedi === 'foster') {
						$html .= ', ' . I18N::translate('foster daughter of') . ' ';
					} elseif ($pedi === 'adopted') {
						$html .= ', ' . I18N::translate('adopted daughter of') . ' ';
					} else {
						$html .= ', ' . I18N::translate('daughter of') . ' ';
					}
					break;
				default:
					if ($pedi === 'foster') {
						$html .= ', ' . I18N::translate('foster child of') . ' ';
					} elseif ($pedi === 'adopted') {
						$html .= ', ' . I18N::translate('adopted child of') . ' ';
					} else {
						$html .= ', ' . I18N::translate('child of') . ' ';
					}
			}

			$father = $parents->getHusband();
			$mother = $parents->getWife();

			if ($father) {
				$html .= $father->getFullName();
			}
			if ($father && $mother) {
				$html .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
			}
			if ($mother) {
				$html .= $mother->getFullName();
			}

			return $html;
		}
	}

	private function print_lifespan($individual, $is_spouse = false) {
		$html = '';
		$birthdate = $individual->getBirthDate();
		$deathdate = $individual->getDeathdate();
		$ageOfdeath = get_age_at_event(Date::GetAgeGedcom($birthdate, $deathdate), false);

		$birthdata = false;
		if ($birthdate->isOK() || $individual->getBirthPlace() != '') {
			$birthdata = true;
			if ($is_spouse == true) {
				$html .= '. ';
				if ($individual->isDead()) {
					$individual->getSex() == 'F' ? $html .= I18N::translate_c('PAST', 'She was born') : $html .= I18N::translate_c('PAST', 'He was born');
				} else {
					$individual->getSex() == 'F' ? $html .= I18N::translate_c('PRESENT', 'She was born') : $html .= I18N::translate_c('PRESENT', 'He was born');
				}
			} else {
				$this->print_parents($individual) || $this->print_fact($individual, 'OCCU') ? $html .= ', ' : $html .= ' ';
				if ($individual->isDead()) {
					$individual->getSex() == 'F' ? $html .= I18N::translate_c('PAST (FEMALE)', 'was born') : $html .= I18N::translate_c('PAST (MALE)', 'was born');
				} else {
					$individual->getSex() == 'F' ? $html .= I18N::translate_c('PRESENT (FEMALE)', 'was born') : $html .= I18N::translate_c('PRESENT (MALE)', 'was born');
				}
			}
			if ($birthdate->isOK()) {
				$html .= $this->print_date($birthdate);
			}
			if ($individual->getBirthPlace() != '') {
				$html .= $this->print_place($individual->getBirthPlace(), $individual->getTree());
			}
		}

		$deathdata = false;
		if ($deathdate->isOK() || $individual->getDeathPlace() != '') {
			$deathdata = true;

			if ($birthdata) {
				$html .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
				$individual->getSex() == 'F' ? $html .= I18N::translate_c('FEMALE', 'died') : $html .= I18N::translate_c('MALE', 'died');
			} else {
				$individual->getSex() == 'F' ? $html .= '. ' . I18N::translate('She died') : $html .= '. ' . I18N::translate('He died');
			}

			if ($deathdate->isOK()) {
				$html .= $this->print_date($deathdate);
			}
			if ($individual->getDeathPlace() != '') {
				$html .= $this->print_place($individual->getDeathPlace(), $individual->getTree());
			}

			if ($birthdate->isOK() && $deathdate->isOK()) {
				if (Date::getAge($birthdate, $deathdate, 0) < 2) {
					$html .= ' ' . /* I18N: %s is the age of death in days/months; %s is a string, e.g. at the age of 2 months */ I18N::translate_c('age in days/months', 'at the age of %s', $ageOfdeath);
				} else {
					$html .= ' ' . /* I18N: %s is the age of death in years; %s is a number, e.g. at the age of 40 */ I18N::translate_c('age in years', 'at the age of %s', $ageOfdeath);
				}
			}
		}

		if ($birthdata || $deathdata) {
			$html .= '. ';
		}

		return $html;
	}

	// some couples are known as not married but have children together. Print the info of the "spouse" parent in a tooltip.
	private function print_tooltip($individual) {
		$birthdate = $individual->getBirthDate();
		$deathdate = $individual->getDeathdate();
		$html = '';
		if ($birthdate->isOK()) {
			$html .= '<strong>' . I18N::translate('Birth') . ':</strong> ' . strip_tags($birthdate->Display());
		}
		if ($deathdate->isOK()) {
			$html .= '<br><strong>' . I18N::translate('Death') . ':</strong> ' . strip_tags($deathdate->Display());
		}

		$parents = $individual->getPrimaryChildFamily();
		if ($parents) {
			$father = $parents->getHusband();
			$mother = $parents->getWife();
			if ($father) {
				$html .= '<br><strong>' . I18N::translate('Father') . ':</strong> ' . strip_tags($father->getFullName());
			}
			if ($mother) {
				$html .= '<br><strong>' . I18N::translate('Mother') . ':</strong> ' . strip_tags($mother->getFullName());
			}
		}
		return $html;
	}

	private function print_thumbnail($individual, $thumbsize, $resize_format, $square, $resize) {
		$mediaobject = $individual->findHighlightedMedia();
		if ($mediaobject) {
			$html = '';
			if ($resize == true) {
				$mediasrc = $resize_format == 1 ? $mediaobject->getServerFilename('thumb') : $mediaobject->getServerFilename('main');
				$thumbwidth = $thumbsize; $thumbheight = $thumbsize;
				$mediatitle = strip_tags($individual->getFullName());

				$type = $mediaobject->mimeType();
				if ($type == 'image/jpeg' || $type == 'image/png') {

					if (!list($width_orig, $height_orig) = @getimagesize($mediasrc)) {
						return null;
					}

					switch ($type) {
						case 'image/jpeg':
							$image = @imagecreatefromjpeg($mediasrc);
							break;
						case 'image/png':
							$image = @imagecreatefrompng($mediasrc);
							break;
					}

					// fallback if image is in the database but not on the server
					if (isset($width_orig) && isset($height_orig)) {
						$ratio_orig = $width_orig / $height_orig;
					} else {
						$ratio_orig = 1;
					}

					if ($resize_format == 1) {
						$thumbwidth = $thumbwidth / 100 * $width_orig;
						$thumbheight = $thumbheight / 100 * $height_orig;
					}

					if ($square == true) {
						$thumbheight = $thumbwidth;
						if ($ratio_orig < 1) {
							$new_height = $thumbwidth / $ratio_orig;
							$new_width = $thumbwidth;
						} else {
							$new_width = $thumbheight * $ratio_orig;
							$new_height = $thumbheight;
						}
					} else {
						if ($resize_format == 1) {
							$new_width = $thumbwidth;
							$new_height = $thumbheight;
						} elseif ($width_orig > $height_orig) {
							$new_height = $thumbheight / $ratio_orig;
							$new_width = $thumbwidth;
						} elseif ($height_orig > $width_orig) {
							$new_width = $thumbheight * $ratio_orig;
							$new_height = $thumbheight;
						} else {
							$new_width = $thumbwidth;
							$new_height = $thumbheight;
						}
					}
					$process = @imagecreatetruecolor(round($new_width), round($new_height));
					if ($type == 'image/png') { // keep transparancy for png files.
						imagealphablending($process, false);
						imagesavealpha($process, true);
					}
					@imagecopyresampled($process, $image, 0, 0, 0, 0, $new_width, $new_height, $width_orig, $height_orig);

					$thumb = $square == true ? imagecreatetruecolor($thumbwidth, $thumbheight) : imagecreatetruecolor($new_width, $new_height);
					if ($type == 'image/png') {
						imagealphablending($thumb, false);
						imagesavealpha($thumb, true);
					}
					@imagecopyresampled($thumb, $process, 0, 0, 0, 0, $thumbwidth, $thumbheight, $thumbwidth, $thumbheight);

					@imagedestroy($process);
					@imagedestroy($image);

					$width = $square == true ? round($thumbwidth) : round($new_width);
					$height = $square == true ? round($thumbheight) : round($new_height);
					ob_start(); $type = 'image/png' ? imagepng($thumb, null, 9) : imagejpeg($thumb, null, 100); $newThumb = ob_get_clean();
					$html = '<a' .
						' class="' . 'gallery' . '"' .
						' href="' . $mediaobject->getHtmlUrlDirect('main') . '"' .
						' type="' . $mediaobject->mimeType() . '"' .
						' data-obje-url="' . $mediaobject->getHtmlUrl() . '"' .
						' data-obje-note="' . htmlspecialchars($mediaobject->getNote()) . '"' .
						' data-obje-xref="' . $mediaobject->getXref() . '"' .
						' data-title="' . Filter::escapeHtml($mediaobject->getFullName()) . '"' .
						'><img class="ftv-thumb" src="data:' . $mediaobject->mimeType() . ';base64,' . base64_encode($newThumb) . '" dir="auto" title="' . $mediatitle . '" alt="' . $mediatitle . '" width="' . $width . '" height="' . $height . '"/></a>'; // need size to fetch it with jquery (for pdf conversion)
				}
			} else {
				$html = $mediaobject->displayImage();
			}
			return $html;
		}
	}

	private function print_date($date) {
		if ($date->qual1 || $date->qual2) {
			return ' ' . $date->Display();
		}
		if ($date->MinDate()->d > 0) {
			return ' ' . /* I18N: Note the space at the end of the string */ I18N::translate_c('before dateformat dd-mm-yyyy', 'on ') . $date->Display();
		}
		if ($date->MinDate()->m > 0) {
			return ' ' . /* I18N: Note the space at the end of the string */ I18N::translate_c('before dateformat mmm yyyy', 'in ') . $date->Display();
		}
		if ($date->MinDate()->y > 0) {
			return ' ' . /* I18N: Note the space at the end of the string */ I18N::translate_c('before dateformat yyyy', 'in ') . $date->Display();
		}
	}

	private function print_divorce_date($div) {
		// Only display if it has a date
		if ($div->getDate()->isOK() && $div->canShow()) {
			return $this->print_date($div->getDate());
		}
	}

	private function print_fact($individual, $tag) {
		$facts = $individual->getFacts();
		foreach ($facts as $fact) {
			if ($fact->getTag() == $tag) {
				$html = ', ' . rtrim($fact->getValue(), ".");
				return $html;
			}
		}
	}

	private function print_place($place, $tree) {
		if ($this->options('show_places') == true) {
			$place = new Place($place, $tree);
			$html = ' ' . /* I18N: Note the space at the end of the string */ I18N::translate_c('before placesnames', 'in ');
			if ($this->options('use_gedcom_places') == true) {
				$html .= $place->getShortName();
			} else {
				$country = $this->options('country');
				$new_place = array_reverse(explode(", ", $place->getGedcomName()));
				if (!empty($country) && $new_place[0] == $country) {
					unset($new_place[0]);
					$html .= '<span dir="auto">' . Filter::escapeHtml(implode(', ', array_reverse($new_place))) . '</span>';
				} else {
					$html .= $place->getFullName();
				}
			}
			return $html;
		}
	}

	// Other functions
	private function get_individual($pid) {
		$individual = Individual::getInstance($pid);
		return $individual;
	}

	private function get_family($individual) {
		foreach ($individual->getSpouseFamilies(WT_PRIV_HIDE) as $family) {
			return $family;
		}
	}

	private function get_next_gen($pid) {
		$individual = $this->get_individual($pid);
		foreach ($individual->getSpouseFamilies() as $family) {
			$children = $family->getChildren();
			if ($children) {
				foreach ($children as $key => $child) {
					$key = $family->getXref() . '-' . $key; // be sure the key is unique.
					$ng[$key]['pid'] = $child->getXref();
					$child->getSpouseFamilies(WT_PRIV_HIDE) ? $ng[$key]['desc'] = 1 : $ng[$key]['desc'] = 0;
				}
			}
		}
		if (isset($ng)) {
			return $ng;
		}
	}

	// check if a person has parents in the same generation
	private function has_parents_in_same_generation($individual, $generation) {
		$parents = $individual->getPrimaryChildFamily();
		if ($parents) {
			$father = $parents->getHusband();
			$mother = $parents->getWife();
			if ($father) {
				$father = $father->getXref();
			}
			if ($mother) {
				$mother = $mother->getXref();
			}
			if (in_array($father, $generation) || in_array($mother, $generation)) {
				return true;
			}
		}
	}

	// check (blood) relationship between partners
	private function check_relationship($individual, $spouse, $family) {
		$count = count($family->getChildren());
		for ($i = 0; $i <= $count; $i++) { // the number of paths is equal to the number of children, because every relationship is checked through each child.
			// and we need the relationship from the next path.
			$nodes = get_relationship($individual, $spouse, false, 0, $i);

			if (!is_array($nodes)) {
				return '';
			}

			$path = array_slice($nodes['relations'], 1);

			$combined_path = '';
			$display = false;
			foreach ($path as $key => $rel) {
				$rel_to_exclude = array('son', 'daughter', 'child'); // don't return the relationship path through the children
				if ($key == 0 && in_array($rel, $rel_to_exclude)) {
					$display = false;
					break;
				}
				$rel_to_find = array('sister', 'brother', 'sibling'); // one of these relationships must be in the path
				if (in_array($rel, $rel_to_find)) {
					$display = true;
					break;
				}
			}

			if ($display == true) {
				foreach ($path as $rel) {
					$combined_path.=substr($rel, 0, 3);
				}
				return get_relationship_name_from_path($combined_path, $individual, $spouse);
			}
		}
	}

	private function check_privacy($record, $xrefs = false) {
		$count = 0;
		foreach ($record as $individual) {
			if ($xrefs) {
				$individual = $this->get_individual($individual);
			}
			if ($individual->CanShow()) {
				$count++;
			}
		}
		if ($count < 1) {
			return true;
		}
	}

	// Determine if the family parents are married. Don't use the default function because we want to privatize the record but display the name and the parents of the spouse if the spouse him/herself is not private.
	private function getMarriage($family) {
		$record = GedcomRecord::getInstance($family->getXref());
		foreach ($record->getFacts('MARR', false, WT_PRIV_HIDE) as $fact) {
			if ($fact) {
				return true;
			}
		}
	}

	// Check if this person is an adopted or foster child
	private function check_pedi($individual, $parents) {
		$pedi = "";
		foreach ($individual->getFacts('FAMC') as $fact) {
			if ($fact->getTarget() === $parents) {
				$pedi = $fact->getAttribute('PEDI');
				break;
			}
		}
		return $pedi;
	}

	private function getImageData() {
		Zend_Session::writeClose();
		header('Content-type: text/html; charset=UTF-8');
		$xref = Filter::get('mid');
		$mediaobject = Media::getInstance($xref);
		if ($mediaobject) {
			echo $mediaobject->getServerFilename();
		}
	}

	// ************************************************* START OF MENU ********************************* //
	// Implement ModuleMenuInterface
	public function defaultMenuOrder() {
		return 10;
	}

	// Implement ModuleMenuInterface
	public function getMenu() {
		global $controller, $SEARCH_SPIDER;

		static $menu;

		// Function has already run
		if ($menu !== null) {
			return $menu;
		}

		$FTV_SETTINGS = unserialize($this->getSetting('FTV_SETTINGS'));

		if (!empty($FTV_SETTINGS)) {
			if ($SEARCH_SPIDER) {
				return null;
			}

			foreach ($FTV_SETTINGS as $FTV_ITEM) {
				if ($FTV_ITEM['TREE'] == WT_GED_ID && $FTV_ITEM['ACCESS_LEVEL'] >= WT_USER_ACCESS_LEVEL) {
					$FTV_GED_SETTINGS[] = $FTV_ITEM;
				}
			}
			if (!empty($FTV_GED_SETTINGS)) {
				// load the module stylesheets
				echo $this->getStylesheet();

				$menu = new Menu(I18N::translate('Tree view'), 'module.php?mod=' . $this->getName() . '&amp;mod_action=show&amp;rootid=' . $FTV_GED_SETTINGS[0]['PID'], 'menu-fancy_treeview');

				foreach ($FTV_GED_SETTINGS as $FTV_ITEM) {
					if (Individual::getInstance($FTV_ITEM['PID'])) {
						if ($this->options('use_fullname') == true) {
							$submenu = new Menu(I18N::translate('Descendants of %s', Individual::getInstance($FTV_ITEM['PID'])->getFullName()), 'module.php?mod=' . $this->getName() . '&amp;mod_action=show&amp;rootid=' . $FTV_ITEM['PID'], 'menu-fancy_treeview-' . $FTV_ITEM['PID']);
						} else {
							$submenu = new Menu(I18N::translate('Descendants of the %s family', $FTV_ITEM['SURNAME']), 'module.php?mod=' . $this->getName() . '&amp;mod_action=show&amp;rootid=' . $FTV_ITEM['PID'], 'menu-fancy_treeview-' . $FTV_ITEM['PID']);
						}
						$menu->addSubmenu($submenu);
					}
				}
				$controller->addInlineJavascript('jQuery(".fancy-treeview-script").remove();');
				return $menu;
			}
		}
	}

	private function getStylesheet() {
		$theme_dir = WT_MODULES_DIR . $this->getName() . '/themes/';
		$stylesheet = '';
		if (file_exists($theme_dir . Theme::theme()->themeId() . '/menu.css')) {
			$stylesheet .= $this->includeCss($theme_dir . Theme::theme()->themeId() . '/menu.css', 'screen');
		}

		if (Filter::get('mod') == $this->getName()) {
			$stylesheet .= $this->includeCss($theme_dir . 'base/style.css');
			$stylesheet .= $this->includeCss($theme_dir . 'base/print.css', 'print');
			if (file_exists($theme_dir . Theme::theme()->themeId() . '/style.css')) {
				$stylesheet .= $this->includeCss($theme_dir . Theme::theme()->themeId() . '/style.css', 'screen');
			}
		}
		return $stylesheet;
	}

	private function includeJs() {
		global $controller;
		// some files needs an extra js script
		if (file_exists(WT_STATIC_URL . WT_MODULES_DIR . $this->getName() . '/themes/' . Theme::theme()->themeId() . '/' . Theme::theme()->themeId() . '.js')) {
			$controller->addExternalJavascript(WT_MODULES_DIR . $this->getName() . '/themes/' . Theme::theme()->themeId() . '/' . Theme::theme()->themeId() . '.js');
		}
	}

	private function includeCss($css, $type = 'all') {
		return
			'<script class="fancy-treeview-script">
				var newSheet=document.createElement("link");
				newSheet.setAttribute("href","' . $css . '");
				newSheet.setAttribute("type","text/css");
				newSheet.setAttribute("rel","stylesheet");
				newSheet.setAttribute("media","' . $type . '");
				document.getElementsByTagName("head")[0].appendChild(newSheet);
			</script>';
	}

}

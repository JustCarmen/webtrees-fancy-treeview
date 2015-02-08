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

	// location of the fancy treeview module files.
	var $_dir;

	/** {@inheritdoc} */
	public function __construct() {
		parent::__construct();

		$this->_dir = WT_MODULES_DIR . $this->getName();

		// Load the module class
		require_once $this->_dir . '/fancytreeview.php';


		// Load any local user translations
		if (is_dir($this->_dir . '/language')) {
			if (file_exists($this->_dir . '/language/' . WT_LOCALE . '.mo')) {
				I18N::addTranslation(
					new Zend_Translate('gettext', $this->_dir . '/language/' . WT_LOCALE . '.mo', WT_LOCALE)
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
	public function modAction($mod_action) {
		$ftv = new FancyTreeView;
		switch ($mod_action) {
		case 'admin_config':
			global $WT_TREE;

			$controller = new PageController;
			$controller
				->restrictAccess(Auth::isAdmin())
				->setPageTitle('Fancy Tree View')
				->pageHeader();

			if (Filter::postBool('save') && Filter::checkCsrf()) {
				$this->update_settings($controller);
				$this->update_options();
			}

			// add javascript files and scripts
			$ftv->includeJs($controller, 'admin');

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
							<?php if (empty($FTV_SETTINGS) || (!empty($FTV_SETTINGS) && !$ftv->searchArray($FTV_SETTINGS, 'TREE', WT_GED_ID))): ?>
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
												<?php if (!$ftv->options('use_fullname')): ?>
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
													<?php if ($ftv->options('use_fullname')): ?>
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
												<?php if (!$ftv->options('use_fullname')): ?>
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
							<?php echo $ftv->addMessage("error", "danger", '', true); ?>
							<div id="fancy-treeview-form" class="form-group">
								<?php if (!empty($FTV_SETTINGS) && $ftv->searchArray($FTV_SETTINGS, 'TREE', WT_GED_ID)): ?>
									<form class="form-horizontal" method="post" name="form4">
										<!-- TABLE -->
										<table id="fancy-treeview-table" class="table table-hover">
											<thead>
												<tr>
													<th><?php echo I18N::translate('Root person'); ?></th>
													<?php if (!$ftv->options('use_fullname')): ?>
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
																<?php if (!$ftv->options('use_fullname')): ?>
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
																		if ($ftv->options('use_fullname') == true) {
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
							<?php echo $ftv->addMessage('save-options', 'success', I18N::translate('The options for this tree are succesfully saved')); ?>
							<?php echo $ftv->addMessage('reset-options', 'success', I18N::translate('The options for this tree are succesfully reset to the default settings')); ?>
							<div id="ftv-options-form" class="form-group">
								<form class="form-horizontal" method="post" name="form5">
									<!-- USE FULLNAME IN MENU -->
									<div class="form-group fullname">
										<label class="control-label col-sm-4">
											<?php echo I18N::translate('Use fullname in menu'); ?>
										</label>
										<div class="col-sm-8">
											<?php echo $ftv->radioButtons('NEW_FTV_OPTIONS[USE_FULLNAME]', $ftv->options('use_fullname')); ?>
										</div>
									</div>
									<!-- GENERATION BLOCKS -->
									<div class="form-group">
										<label class="control-label col-sm-4">
											<?php echo I18N::translate('Number of generation blocks to show'); ?>
										</label>
										<div class="col-sm-4">
											<?php echo select_edit_control('NEW_FTV_OPTIONS[NUMBLOCKS]', array(I18N::translate('All'), '1', '2', '3', '4', '5', '6', '7', '8', '9', '10'), null, $ftv->options('numblocks'), 'class="form-control"'); ?>									</div>
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
											<?php echo $ftv->radioButtons('NEW_FTV_OPTIONS[CHECK_RELATIONSHIP]', $ftv->options('check_relationship')); ?>
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
											<?php echo $ftv->radioButtons('NEW_FTV_OPTIONS[SHOW_SINGLES]', $ftv->options('show_singles')); ?>									</div>
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
											<?php echo $ftv->radioButtons('NEW_FTV_OPTIONS[SHOW_PLACES]', $ftv->options('show_places')); ?>
										</div>
									</div>
									<!-- USE GEDCOM PLACE SETTING -->
									<div id="gedcom_places" class="form-group <?php if (!$ftv->options('show_places')) echo 'hidden' ?>">
										<label class="control-label col-sm-4">
											<?php echo I18N::translate('Use default Gedcom settings to abbreviate place names?'); ?>
										</label>
										<div class="col-sm-8">
											<?php echo $ftv->radioButtons('NEW_FTV_OPTIONS[USE_GEDCOM_PLACES]', $ftv->options('use_gedcom_places')); ?>
										</div>
										<p class="col-sm-8 col-sm-offset-4 small text-muted">
											<?php echo /* I18N: Help text for the “Use default Gedcom settings to abbreviate place names” configuration setting */ I18N::translate('If you have ticked the “Show places” option, you can choose to use the default Gedcom settings to abbreviate placenames. If you don’t set this option, full place names will be shown.'); ?>
										</p>
									</div>
									<!-- GET COUNTRYLIST -->
									<?php if ($ftv->getCountrylist()): ?>
										<div id="country_list" class="form-group <?php if (!$ftv->options('show_places') || $ftv->options('use_gedcom_places')) echo 'hidden' ?>">
											<label class="control-label col-sm-4">
												<?php echo I18N::translate('Select your country'); ?>
											</label>
											<div class="col-sm-8">
												<?php echo select_edit_control('NEW_FTV_OPTIONS[COUNTRY]', $ftv->getCountryList(), '', $ftv->options('country'), 'class="form-control"'); ?>
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
											<?php echo $ftv->radioButtons('NEW_FTV_OPTIONS[SHOW_OCCU]', $ftv->options('show_occu')); ?>
										</div>
									</div>
									<!-- RESIZE THUMBS -->
									<div id="resize_thumbs" class="form-group">
										<label class="control-label col-sm-4">
											<?php echo I18N::translate('Resize thumbnails'); ?>
										</label>
										<div class="col-sm-8">
											<?php echo $ftv->radioButtons('NEW_FTV_OPTIONS[RESIZE_THUMBS]', $ftv->options('resize_thumbs')); ?>
										</div>
										<p class="col-sm-8 col-sm-offset-4 small text-muted">
											<?php echo /* I18N: Help text for the “Use default Gedcom settings to abbreviate place names” configuration setting */ I18N::translate('Here you can choose to resize the default webtrees thumbnails especially for the Fancy Tree View pages. You can set a custom size in percentage or in pixels. If you choose “no” the default webtrees thumbnails will be used with the formats you have set on the tree configuration page.'); ?>									</p>
									</div>
									<!-- THUMB SIZE -->
									<div id="thumb_size" class="form-group <?php if (!$ftv->options('resize_thumbs')) echo 'hidden' ?>">
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
													value="<?php echo $ftv->options('thumb_size'); ?>"
													>
											</div>
											<div class="col-sm-2">
												<?php echo select_edit_control('NEW_FTV_OPTIONS[THUMB_RESIZE_FORMAT]', array('1' => I18N::translate('percent'), '2' => I18N::translate('pixels')), null, $ftv->options('thumb_resize_format'), 'class="form-control"'); ?>
											</div>
										</div>
									</div>
									<!-- SQUARE THUMBS -->
									<div id="square_thumbs" class="form-group <?php if (!$ftv->options('resize_thumbs')) echo 'hidden' ?>">
										<label class="control-label col-sm-4">
											<?php echo I18N::translate('Use square thumbnails'); ?>
										</label>
										<div class="col-sm-8">
											<?php echo $ftv->radioButtons('NEW_FTV_OPTIONS[USE_SQUARE_THUMBS]', $ftv->options('use_square_thumbs')); ?>
										</div>
									</div>
									<!-- SHOW USERFORM -->
									<div class="form-group">
										<label class="control-label col-sm-4">
											<?php echo I18N::translate('Show form to change start person'); ?>
										</label>
										<div class="col-sm-4">
											<?php echo edit_field_access_level('NEW_FTV_OPTIONS[SHOW_USERFORM]', $ftv->options('show_userform'), 'class="form-control"'); ?>
										</div>
									</div>
									<!-- SHOW PDF -->
									<div class="form-group">
										<label class="control-label col-sm-4">
											<?php echo I18N::translate('Show PDF icon?'); ?>
										</label>
										<div class="col-sm-4">
											<?php echo edit_field_access_level('NEW_FTV_OPTIONS[SHOW_PDF_ICON]', $ftv->options('show_pdf_icon'), 'class="form-control"'); ?>
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
			break;

		case 'admin_search':
			Zend_Session::writeClose();
			// new settings
			$surname = Filter::post('surname');
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
					$result['error'] = I18N::translate('Error: The root person belonging to this surname already exists');
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
			header('Location: ' . $this->getConfigLink());
			break;

		case 'admin_delete':
			Zend_Session::writeClose();
			$FTV_SETTINGS = unserialize($this->getSetting('FTV_SETTINGS'));
			unset($FTV_SETTINGS[Filter::getInteger('key')]);
			$this->setSetting('FTV_SETTINGS', serialize($FTV_SETTINGS));
			Log::addConfigurationLog($this->getTitle() . ' item deleted');
			header('Location: ' . $this->getConfigLink());
			break;

		case 'show':
			global $controller;

			$controller = new PageController;
			
			$root_person = $ftv->getIndividual($ftv->rootId());
			if ($root_person && $root_person->canShowName()) {
				$controller
					->setPageTitle(/* I18N: %s is the surname of the root individual */ I18N::translate('Descendants of %s', $root_person->getFullName()))
					->pageHeader();

				// add javascript files and scripts
				$ftv->includeJs($controller, 'fancytreeview');
				?>

				<!-- FANCY TREE VIEW CONTENT -->
				<div id="fancy_treeview-page">
					<div id="page-header">
						<h2><?php echo $controller->getPageTitle(); ?></h2>
						<?php if ($ftv->options('show_pdf_icon') >= WT_USER_ACCESS_LEVEL && I18N::direction() === 'ltr'): ?>
						<div id="dialog-confirm" title="<?php echo I18N::translate('Generate PDF'); ?>" style="display:none">
							<p><?php echo I18N::translate('The pdf contains only visible generation blocks.'); ?></p>
						</div>
						<a id="pdf" href="#"><i class="icon-mime-application-pdf"></i></a>
						<?php endif; ?>
					</div>
					<div id="page-body">';
						<?php if ($ftv->options('show_userform') >= WT_USER_ACCESS_LEVEL): ?>
						<form id="change_root">
							<label class="label"><?php echo I18N::translate('Change root person'); ?></label>
							<input
								type="text"
								name="new_rootid"
								id="new_rootid"
								size="10"
								maxlength="20"
								placeholder="<?php echo I18N::translate('ID'); ?>"
								>
							<?php echo print_findindi_link('new_rootid'); ?>
							<input
								type="submit"
								id="btn_go"
								value="<?php echo I18N::translate('Go'); ?>"
								>
						</form>
						<div id="error"></div>';
						<?php endif; ?>
						<ol id="fancy_treeview"><?php echo $ftv->printPage(); ?></ol>
						<div id="btn_next">
							<input
								type="button"
								name="next"
								value="<?php echo I18N::translate('next'); ?>"
								>
						</div>
					</div>
				</div>
			<?php
			} else {
				http_response_code(404);				
				$controller->pageHeader();
				echo $ftv->addMessage('alert', 'warning', I18N::translate('This individual does not exist or you do not have permission to view it.'));
				
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
			header('HTTP/1.0 404 Not Found');
		}
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
	public function getMenu() {
		global $controller, $SEARCH_SPIDER;

		$ftv = new FancyTreeView;
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
				if (Theme::theme()->themeId() !== '_administration') {
					echo $ftv->getStylesheet();
				}

				$menu = new Menu(I18N::translate('Tree view'), 'module.php?mod=' . $this->getName() . '&amp;mod_action=show&amp;rootid=' . $FTV_GED_SETTINGS[0]['PID'], 'menu-fancy_treeview');

				foreach ($FTV_GED_SETTINGS as $FTV_ITEM) {
					if (Individual::getInstance($FTV_ITEM['PID'])) {
						if ($ftv->options('use_fullname') == true) {
							$submenu = new Menu(I18N::translate('Descendants of %s', Individual::getInstance($FTV_ITEM['PID'])->getFullName()), 'module.php?mod=' . $this->getName() . '&amp;mod_action=show&amp;rootid=' . $FTV_ITEM['PID'], 'menu-fancy_treeview-' . $FTV_ITEM['PID']);
						} else {
							$submenu = new Menu(I18N::translate('Descendants of the %s family', $FTV_ITEM['SURNAME']), 'module.php?mod=' . $this->getName() . '&amp;mod_action=show&amp;rootid=' . $FTV_ITEM['PID'], 'menu-fancy_treeview-' . $FTV_ITEM['PID']);
						}
						$menu->addSubmenu($submenu);
					}
				}

				if (Theme::theme()->themeId() !== '_administration') {
					$controller->addInlineJavascript('jQuery(".fancy-treeview-script").remove();');
				}

				return $menu;
			}
		}
	}

}

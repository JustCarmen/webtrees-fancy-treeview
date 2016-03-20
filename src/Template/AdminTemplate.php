<?php
/*
 * webtrees: online genealogy
 * Copyright (C) 2016 webtrees development team
 * Copyright (C) 2016 JustCarmen
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace JustCarmen\WebtreesAddOns\FancyTreeview\Template;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Controller\PageController;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\Functions\FunctionsEdit;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Tree;
use JustCarmen\WebtreesAddOns\FancyTreeview\FancyTreeviewClass;

class AdminTemplate extends FancyTreeviewClass {

	protected function pageContent() {
		$controller = new PageController;
		return
			$this->pageHeader($controller) .
			$this->pageBody($controller);
	}

	private function pageHeader(PageController $controller) {
		$controller
			->restrictAccess(Auth::isAdmin())
			->setPageTitle(I18N::translate('Fancy Treeview'))
			->pageHeader();

		// add javascript and styleseheet
		$this->includeJs($controller, 'admin');
		echo $this->getStylesheet();
	}

	private function pageBody(PageController $controller) {
		$FTV_SETTINGS = unserialize($this->getSetting('FTV_SETTINGS'));
		?>
		<!-- ADMIN PAGE CONTENT -->
		<ol class="breadcrumb small">
			<li><a href="admin.php"><?php echo I18N::translate('Control panel') ?></a></li>
			<li><a href="admin_modules.php"><?php echo I18N::translate('Module administration') ?></a></li>
			<li class="active"><?php echo $controller->getPageTitle() ?></li>
		</ol>
		<h2><?php echo $controller->getPageTitle() ?></h2>

		<!-- *** FORM 1 *** -->
		<form class="form-horizontal" method="post" name="form1">
			<?php echo Filter::getCsrf() ?>
			<!-- SELECT TREE -->
			<div class="form-group">
				<label class="control-label col-sm-2" for="tree">
					<?php echo I18N::translate('Family tree') ?>
				</label>
				<div class="col-sm-4">
					<select id="tree" name="NEW_FIB_TREE" class="form-control">
						<?php foreach (Tree::getAll() as $tree): ?>
							<?php if ($tree->getTreeId() == $this->tree_id): ?>
								<option value="<?php echo $tree->getTreeId(); ?>" data-ged="<?php echo $tree->getNameHtml() ?>" selected="selected">
									<?php echo $tree->getTitleHtml() ?>
								</option>
							<?php else: ?>
								<option value="<?php echo $tree->getTreeId(); ?>" data-ged="<?php echo $tree->getNameHtml() ?>">
									<?php echo $tree->getTitleHtml() ?>
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
						<a data-toggle="collapse" data-target="#collapseOne" href="#">
							<?php echo I18N::translate('Pages') ?>
						</a>
					</h4>
				</div>
				<div id="collapseOne" class="panel-collapse collapse in">
					<div class="panel-body">
						<?php if (empty($FTV_SETTINGS) || (!empty($FTV_SETTINGS) && !$this->searchArray($FTV_SETTINGS, 'TREE', $this->tree_id))): ?>
							<div class="alert alert-info alert-dismissible" role="alert">
								<button type="button" class="close" data-dismiss="alert" aria-label="' . I18N::translate('close') . '">
									<span aria-hidden="true">&times;</span>
								</button>
								<p class="small text-muted">
									<?php echo /* I18N: Help text for creating Fancy Treeview pages */ I18N::translate('Use the search form below to search for a root person. After a successful search the Fancy Treeview page will be automatically created. You can add as many root persons as you want.') ?>
								</p>
							</div>
						<?php endif; ?>
						<!-- *** FORM 2 *** -->
						<div id="ftv-search-form" class="form-group alert alert-info">
							<form class="form-inline" method="post" name="form2">
								<!-- SURNAME SEARCH FIELD -->
								<div class="form-group">
									<label class="control-label">
										<?php echo I18N::translate('Search root person') ?>
									</label>
									<input
										class="form-control"
										data-autocomplete-type="SURN"
										id="surname-search"
										name="SURNAME"
										placeholder="<?php echo I18N::translate('Surname') ?>"
										type="text"
										>
									<label class="checkbox-inline">
										<?php echo FunctionsEdit::checkbox('soundex_std') . I18N::translate('Russell') ?>
									</label>
									<label class="checkbox-inline">
										<?php echo FunctionsEdit::checkbox('soudex_dm') . I18N::translate('Daitch-Mokotoff') ?>
									</label>
									<button name="search" class="btn btn-primary" type="submit">
										<i class="fa fa-search"></i>
										<?php echo I18N::translate('search') ?>
									</button>
								</div>
								<!-- PID SEARCH FIELD -->
								<?php $class = I18N::direction() === 'rtl' ? 'pull-left' : 'pull-right'; ?>
								<div class="form-group <?php echo $class ?>">
									<label class="control-label" for="pid-search">
										<?php echo I18N::translate('Or enter an ID') ?>
									</label>
									<input
										class="form-control"
										data-autocomplete-type="INDI"
										id="pid-search"
										name="PID"
										placeholder="<?php echo I18N::translate('Search ID by name') ?>"
										type="text"
										value=""
										>
									<button name="Ok" class="btn btn-primary" type="submit">
										<i class="fa fa-check"></i>
										<?php echo I18N::translate('ok') ?>
									</button>
								</div>
							</form>
							<!-- *** FORM 3 *** -->
							<form class="form-horizontal" method="post" name="form3">
								<!-- TABLE -->
								<table id="search-result-table" class="table" style="display: none">
									<thead>
										<tr>
											<th><?php echo I18N::translate('Root person') ?></th>
											<?php if (!$this->options('use_fullname')): ?>
												<th><?php echo I18N::translate('Surname in page title') ?></th>
											<?php endif; ?>
											<th><?php echo I18N::translate('Page title') ?></th>
											<th><?php echo I18N::translate('Access level') ?></th>
											<th><?php echo I18N::translate('Add') ?></th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<!-- ROOT PERSONS FULL NAME -->
											<td id="root">
												<?php if ($this->options('use_fullname')): ?>
													<input
														name="surname"
														type="hidden"
														value=""
														>
													<?php endif ?>
												<input
													name="pid"
													type="hidden"
													value=""
													>
												<input
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
												<?php echo FunctionsEdit::editFieldAccessLevel('access_level', 2, 'class="form-control"') ?>
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
						<?php echo $this->addMessage("error", "danger", true) ?>
						<?php echo $this->addMessage('update-settings', 'success', true, I18N::translate('The settings for this tree are succesfully updated')) ?>
						<div id="fancy-treeview-form" class="form-group">
							<?php if (!empty($FTV_SETTINGS) && $this->searchArray($FTV_SETTINGS, 'TREE', $this->tree_id)): ?>
								<form class="form-horizontal" method="post" name="form4">
									<!-- TABLE -->
									<table id="fancy-treeview-table" class="table table-hover">
										<thead>
											<tr>
												<th><?php echo I18N::translate('Root person') ?></th>
												<?php if (!$this->options('use_fullname')): ?>
													<th><?php echo I18N::translate('Surname in page title') ?></th>
												<?php endif; ?>
												<th><?php echo I18N::translate('Page title') ?></th>
												<th><?php echo I18N::translate('Access level') ?></th>
												<th><?php echo I18N::translate('Delete') ?></th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ($FTV_SETTINGS as $key => $this_ITEM): ?>
												<?php if ($this_ITEM['TREE'] == $this->tree_id): ?>
													<?php if (Individual::getInstance($this_ITEM['PID'], $this->tree)): ?>
														<tr class="sortme">
															<!-- ROOT PERSONS FULL NAME -->
															<td>
																<input
																	name="pid[<?php echo $key ?>]"
																	type="hidden"
																	value="<?php echo $this_ITEM['PID'] ?>"
																	>
																<input
																	name="sort[<?php echo $key ?>]"
																	type="hidden"
																	value="<?php echo $this_ITEM['SORT'] ?>"
																	>
																	<?php echo Individual::getInstance($this_ITEM['PID'], $this->tree)->getFullName() . '' ?>
																(<?php echo Individual::getInstance($this_ITEM['PID'], $this->tree)->getLifeSpan() ?>)
															</td>
															<?php if (!$this->options('use_fullname')): ?>
																<!-- SURNAME IN PAGE TITLE -->
																<td>
																	<label class="showname">
																		<?php echo $this_ITEM['SURNAME'] ?>
																	</label>
																	<input
																		class="form-control editname"
																		name="surname[<?php echo $key ?>]"
																		type="text"
																		value="<?php echo $this_ITEM['SURNAME'] ?>"
																		>
																</td>
															<?php endif ?>
															<!-- PAGE TITLE -->
															<td>
																<a href="module.php?mod=<?php echo $this->getName(); ?>&amp;mod_action=page&amp;ged=<?php echo $this->tree->getNameHtml(); ?>&amp;rootid=<?php echo $this_ITEM['PID'] ?>" target="_blank">
																	<?php
																	if ($this->options('use_fullname') == true) {
																		echo I18N::translate('Descendants of %s', Individual::getInstance($this_ITEM['PID'], $this->tree)->getFullName());
																	} else {
																		echo I18N::translate('Descendants of the %s family', $this_ITEM['SURNAME']);
																	}
																	?>
																</a>
															</td>
															<!-- ACCESS LEVEL -->
															<td>
																<?php echo FunctionsEdit::editFieldAccessLevel('access_level[' . $key . ']', $this_ITEM['ACCESS_LEVEL'], 'class="form-control"') ?>
															</td>
															<!-- DELETE BUTTON -->
															<td>
																<button	type="button" name="delete" class="btn btn-danger btn-sm" data-key="<?php echo $key ?>" title="<?php I18N::translate('Delete') ?>">
																	<i class="fa fa-trash-o"></i>
																</button>
															</td>
														</tr>
													<?php else: ?>
														<tr>
															<!-- SURNAME -->
															<td class="error">
																<input
																	name="pid[<?php echo $key ?>]"
																	type="hidden"
																	value="<?php echo $this_ITEM['PID'] ?>"
																	>
																	<?php echo $this_ITEM['SURNAME'] ?>
															</td>
															<!-- ERROR MESSAGE -->
															<td colspan="4" class="error">
																<?php echo I18N::translate('The person with root id %s doesn’t exist anymore in this tree', $this_ITEM['PID']) ?>
															</td>
															<!-- DELETE BUTTON -->
															<td>
																<button name="delete" type="button" class="btn btn-danger btn-sm" data-key="<?php echo $key ?>" title="<?php I18N::translate('Delete') ?>">
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
										<?php echo I18N::translate('update') ?>
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
						<a data-toggle="collapse" data-target="#collapseTwo" href="#" class="collapsed">
							<?php echo I18N::translate('Options for %s', $this->tree->getTitleHtml()) ?>
						</a>
					</h4>
				</div>
				<div id="collapseTwo" class="panel-collapse collapse">
					<div class="panel-body">
						<?php echo $this->addMessage('save-options', 'success', true, I18N::translate('The options for this tree are succesfully saved')) ?>
						<?php echo $this->addMessage('reset-options', 'success', true, I18N::translate('The options for this tree are succesfully reset to the default settings')) ?>
						<?php echo $this->addMessage('copy-options', 'success', true, I18N::translate('The options for this tree are succesfully saved and copied to all other trees')) ?>
						<div id="ftv-options-form" class="form-group">
							<form class="form-horizontal" method="post" name="form5">
								<!-- USE FULLNAME IN MENU -->
								<div class="form-group fullname">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Use fullname in menu') ?>
									</label>
									<div class="col-sm-8">
										<?php echo FunctionsEdit::editFieldYesNo('NEW_FTV_OPTIONS[USE_FULLNAME]', $this->options('use_fullname'), 'class="radio-inline"') ?>
									</div>
								</div>
								<!-- GENERATION BLOCKS -->
								<div class="form-group">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Number of generation blocks to show') ?>
									</label>
									<div class="col-sm-4">
										<?php echo FunctionsEdit::selectEditControl('NEW_FTV_OPTIONS[NUMBLOCKS]', array(I18N::translate('All'), '1', '2', '3', '4', '5', '6', '7', '8', '9', '10'), null, $this->options('numblocks'), 'class="form-control"') ?>									</div>
									<p class="col-sm-8 col-sm-offset-4 small text-muted">
										<?php echo /* I18N: Help text for the “Number of generation blocks to show” configuration setting */ I18N::translate('This option is especially usefull for large trees. When you notice a slow page load, here you can set the number of generation blocks to load at once to a lower level. Below the last generation block a button will appear to add the next set of generation blocks. The new blocks will be added to the blocks already loaded. Clicking on a “follow” link in the last visible generation block, will also load the next set of generation blocks.') ?>
									</p>
								</div>
								<!-- SHOW SINGLES -->
								<div class="form-group">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Show single persons') ?>
									</label>
									<div class="col-sm-8">
										<?php echo FunctionsEdit::editFieldYesNo('NEW_FTV_OPTIONS[SHOW_SINGLES]', $this->options('show_singles'), 'class="radio-inline"') ?>									</div>
									<p class="col-sm-8 col-sm-offset-4 small text-muted">
										<?php echo /* I18N: Help text for the “Show single persons” configuration setting */ I18N::translate('Turn this option on if you want to show single persons in the generation blocks. Single persons are persons without partner and children. With this option turned on, every child of a family will be shown in a detailed way in the next generation block.') ?>
									</p>
								</div>
								<!-- CHECK RELATIONSHIP -->
								<div class="form-group">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Check relationship between partners') ?>
									</label>
									<div class="col-sm-8">
										<?php echo FunctionsEdit::editFieldYesNo('NEW_FTV_OPTIONS[CHECK_RELATIONSHIP]', $this->options('check_relationship'), 'class="radio-inline"') ?>
									</div>
									<p class="col-sm-8 col-sm-offset-4 small text-muted">
										<?php echo /* I18N: Help text for the “Check relationship between partners” configuration setting */ I18N::translate('With this option turned on, the script checks if a (married) couple has the same ancestors. If a relationship between the partners is found, a text will appear between brackets after the spouses’ name to indicate the blood relationship.') ?></p>
									<p class="col-sm-8 col-sm-offset-4 small text-muted">
										<?php echo /* I18N: Warning when using the “Check relationship between partners” configuration setting */ I18N::translate('<strong>Note</strong>: this option can be time and/or memory consuming, especially on large trees. It can cause very slow page loading or an ’execution time out error’ on your server. If you notice such a behavior, reduce the number of generation blocks to load at once or don’t use it in combination with the option to show singles (see the previous options). If you still experience any problems, don’t use this option at all.') ?>
									</p>
								</div>
								<!-- SHOW PLACES -->
								<div id="places" class="form-group">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Show places?') ?>
									</label>
									<div class="col-sm-8">
										<?php echo FunctionsEdit::editFieldYesNo('NEW_FTV_OPTIONS[SHOW_PLACES]', $this->options('show_places'), 'class="radio-inline"') ?>
									</div>
								</div>
								<!-- USE GEDCOM PLACE SETTING -->
								<div id="gedcom_places" class="form-group<?php if (!$this->options('show_places')) echo ' collapse' ?>">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Use default GEDCOM settings to abbreviate place names?') ?>
									</label>
									<div class="col-sm-8">
										<?php echo FunctionsEdit::editFieldYesNo('NEW_FTV_OPTIONS[USE_GEDCOM_PLACES]', $this->options('use_gedcom_places'), 'class="radio-inline"') ?>
									</div>
									<p class="col-sm-8 col-sm-offset-4 small text-muted">
										<?php echo /* I18N: Help text for the “Use default GEDCOM settings to abbreviate place names” configuration setting */ I18N::translate('If you have ticked the “Show places” option, you can choose to use the default GEDCOM settings to abbreviate placenames. If you don’t set this option, full place names will be shown.') ?>
									</p>
								</div>
								<!-- GET COUNTRYLIST -->
								<?php if ($this->getCountrylist()): ?>
									<div id="country_list" class="form-group<?php if (!$this->options('show_places') || $this->options('use_gedcom_places')) echo ' collapse' ?>">
										<label class="control-label col-sm-4">
											<?php echo I18N::translate('Select your country') ?>
										</label>
										<div class="col-sm-8">
											<?php echo FunctionsEdit::selectEditControl('NEW_FTV_OPTIONS[COUNTRY]', $this->getCountryList(), '', $this->options('country'), 'class="form-control"') ?>
										</div>
										<p class="col-sm-8 col-sm-offset-4 small text-muted">
											<?php echo /* I18N: Help text for the “Select your country” configuration setting */ I18N::translate('If you have ticked the “Show places” option but NOT the option to abbreviate placenames, you can set your own country here. Full places will be listed on the Fancy Treeview pages, but when a place includes the name of your own country, this name will be left out. If you don’t select a country then all countries will be shown, including your own.') ?>
										</p>
									</div>
								<?php endif; ?>
								<!-- SHOW OCCUPATIONS -->
								<div class="form-group">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Show occupations') ?>
									</label>
									<div class="col-sm-8">
										<?php echo FunctionsEdit::editFieldYesNo('NEW_FTV_OPTIONS[SHOW_OCCU]', $this->options('show_occu'), 'class="radio-inline"') ?>
									</div>
								</div>
								<!-- RESIZE THUMBS -->
								<div id="resize_thumbs" class="form-group">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Resize thumbnails') ?>
									</label>
									<div class="col-sm-8">
										<?php echo FunctionsEdit::editFieldYesNo('NEW_FTV_OPTIONS[RESIZE_THUMBS]', $this->options('resize_thumbs'), 'class="radio-inline"') ?>
									</div>
									<p class="col-sm-8 col-sm-offset-4 small text-muted">
										<?php echo /* I18N: Help text for the “Resize thumbnails” configuration setting */ I18N::translate('Here you can choose to resize the default webtrees thumbnails especially for the Fancy Treeview pages. You can set a custom size in percentage or in pixels. If you choose “no” the default webtrees thumbnails will be used with the formats you have set on the tree configuration page.') ?>									</p>
								</div>
								<!-- THUMB SIZE -->
								<div id="thumb_size" class="form-group<?php if (!$this->options('resize_thumbs')) echo ' collapse' ?>">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Thumbnail size') ?>
									</label>
									<div class="row">
										<div class="col-sm-1">
											<input
												class="form-control"
												id="NEW_FTV_OPTIONS[THUMB_SIZE]"
												name="NEW_FTV_OPTIONS[THUMB_SIZE]"
												type="text"
												value="<?php echo $this->options('thumb_size') ?>"
												>
										</div>
										<div class="col-sm-2">
											<?php echo FunctionsEdit::selectEditControl('NEW_FTV_OPTIONS[THUMB_RESIZE_FORMAT]', array('1' => I18N::translate('percent'), '2' => I18N::translate('pixels')), null, $this->options('thumb_resize_format'), 'class="form-control"') ?>
										</div>
									</div>
								</div>
								<!-- SQUARE THUMBS -->
								<div id="square_thumbs" class="form-group<?php if (!$this->options('resize_thumbs')) echo ' collapse' ?>">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Use square thumbnails') ?>
									</label>
									<div class="col-sm-8">
										<?php echo FunctionsEdit::editFieldYesNo('NEW_FTV_OPTIONS[USE_SQUARE_THUMBS]', $this->options('use_square_thumbs'), 'class="radio-inline"') ?>
									</div>
								</div>
								<!-- SHOW USERFORM -->
								<div class="form-group">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Show form to change start person') ?>
									</label>
									<div class="col-sm-4">
										<?php echo FunctionsEdit::editFieldAccessLevel('NEW_FTV_OPTIONS[SHOW_USERFORM]', $this->options('show_userform'), 'class="form-control"') ?>
									</div>
								</div>
								<!-- SHOW FANCY TREEVIEW ON INDI PAGE -->
								<div class="form-group">
									<label class="control-label col-sm-4">
										<?php echo I18N::translate('Show a Fancy Treeview tab on the individual page') ?>
									</label>
									<div class="col-sm-8">
										<?php echo FunctionsEdit::editFieldYesNo('NEW_FTV_OPTIONS[FTV_TAB]', $this->options('ftv_tab'), 'class="radio-inline"') ?>
									</div>
									<p class="col-sm-8 col-sm-offset-4 small text-muted">
										<?php echo /* I18N: Help text for the “Show Fancy Treeview on Indi Page” configuration setting */ I18N::translate('If you enable this option, a Fancy Treeview tab with the title “Descendants” will be shown on the individual page. The tab will describe the current individual with his family and the next two generations (if there are any). If this person has more descendants then the two generations shown, a link will be displayed to the full Fancy Treeview Page with this person as root person and all his descendants.') ?>
									</p>									
								</div>
								<!-- BUTTONS -->
								<div class="form-group">
									<div class="col-md-6">
										<button name="save-options" class="btn btn-primary" type="submit">
											<i class="fa fa-check"></i>
											<?php echo I18N::translate('save') ?>
										</button>
										<button name="reset-options" class="btn btn-primary" type="reset">
											<i class="fa fa-recycle"></i>
											<?php echo I18N::translate('reset') ?>
										</button>
									</div>
									<?php if (count(Tree::getAll()) > 1): ?>
									<div class="col-md-6 text-right">
										<button id="save-and-copy" name="copy-options" class="btn btn-primary" type="button">
											<i class="fa fa-check"></i>
											<?php echo I18N::translate('save and copy options to other trees') ?>
										</button>
									</div>
									<?php endif; ?>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

}

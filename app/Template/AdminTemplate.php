<?php
/*
 * webtrees: online genealogy
 * Copyright (C) 2018 JustCarmen (http://justcarmen.nl)
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
use Fisharebest\Webtrees\Bootstrap4;
use Fisharebest\Webtrees\Controller\PageController;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\Functions\FunctionsEdit;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Theme;
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
		->pageHeader()
		->addExternalJavascript($this->directory . '/js/admin.js');

		echo $this->includeCss();
	}

	private function pageBody(PageController $controller) {
		global $WT_TREE;

		echo Bootstrap4::breadcrumbs([
			route('admin-control-panel') => I18N::translate('Control panel'),
			route('admin-modules')       => I18N::translate('Module administration'),
		], $controller->getPageTitle()); ?>

		<div class="fancy-treeview">
		  <div class="fancy-treeview-admin">
			<h1><?= $controller->getPageTitle() ?></h1>

			<!-- *** FORM 1 *** -->
			<form class="form-horizontal" name="form1">
			  <?= Filter::getCsrf() ?>
			  <!-- SELECT TREE -->
			  <div class="row form-group">
				<label class="tree-label col-form-label col-sm-2" for="tree">
				  <?= I18N::translate('Family tree') ?>
				</label>
				<div class="col-sm-4">
				  <?= Bootstrap4::select(Tree::getNameList(), $WT_TREE->getName(), ['id' => 'tree', 'name' => 'NEW_FIB_TREE']) ?>
				</div>
			  </div>
			</form>

			<div id="accordion" role="tablist" aria-multiselectable="true">
			  <div class="card">
				<div class="card-header" role="tab" id="card-pages-header">
				  <h5 class="mb-0">
					<a data-toggle="collapse" data-parent="#accordion" href="#card-pages-content" aria-expanded="true" aria-controls="card-pages-content">
					  <?= I18N::translate('Pages') ?>
					</a>
				  </h5>
				</div>
				<div id="card-pages-content" class="collapse show" role="tabpanel" aria-labelledby="card-pages-header">
				  <div class="card-body">
					<?php
					$FTV_SETTINGS = unserialize($this->getPreference('FTV_SETTINGS'));
		if (empty($FTV_SETTINGS) || (!empty($FTV_SETTINGS) && !$this->searchArray($FTV_SETTINGS, 'TREE', $this->tree()->getTreeId()))) {
			$html = /* I18N: Help text for creating Fancy Treeview pages */ I18N::translate('Use the search form below to search for a root person. After a successful search the Fancy Treeview page will be automatically created. You can add as many root persons as you want.');
			echo Theme::theme()->htmlAlert($html, 'info', true);
		} ?>

					<!-- *** FORM 2 *** -->
					<div id="ftv-search-form" class="form-group alert alert-info">
					  <form class="form-horizontal" name="form2">
						<!-- SURNAME SEARCH FIELD -->
						<div class="row form-group">
						  <label class="col-sm-3 col-form-label">
							<?= I18N::translate('Search root person') ?>
						  </label>
						  <div class="col-sm-3">
							<input
							  class="form-control"
							  data-autocomplete-type="SURN"
							  id="surname-search"
							  name="SURNAME"
							  placeholder="<?= I18N::translate('Surname') ?>"
							  type="text"
							  >
						  </div>
						  <div class="col-sm-3">
							<?= Bootstrap4::checkbox(I18N::translate('Russell'), true, ['name' => 'soundex_std']) ?>
							<?= Bootstrap4::checkbox(I18N::translate('Daitch-Mokotoff'), true, ['name' => 'soundex_dm']) ?>
						  </div>
						  <button name="search" class="btn btn-primary col-sm-1" type="submit">
							<i class="fa fa-search"></i>
							<?= I18N::translate('search') ?>
						  </button>
						</div>
						<!-- PID SEARCH FIELD -->
						<div class="row form-group">
						  <label class="col-sm-3 col-form-label" for="pid-search">
							<?= I18N::translate('Or enter a name') ?>
						  </label>
						  <div class="col-sm-6">
							<?= FunctionsEdit::formControlIndividual($WT_TREE, null, ['id' => 'pid-search', 'name' => 'PID']) ?>
						  </div>
						  <button name="Ok" class="btn btn-primary col-sm-1" type="submit">
							<i class="fa fa-check"></i>
							<?= I18N::translate('ok') ?>
						  </button>
						</div>
					  </form>

					  <!-- *** FORM 3 *** -->
					  <form class="form-inline" name="form3">
						<!-- TABLE -->
						<table id="search-result-table" class="table" style="display: none">
						  <thead>
							<tr>
							  <th><?= I18N::translate('Root person') ?></th>
							  <?php if (!$this->options('use_fullname')): ?>
								<th><?= I18N::translate('Surname in page title') ?></th>
							  <?php endif; ?>
							  <th><?= I18N::translate('Page title') ?></th>
							  <th><?= I18N::translate('Access level') ?></th>
							  <th><?= I18N::translate('Add') ?></th>
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
								  <span class="showname" data-toggle="tooltip" data-animation="false" data-container="body" title="<?= I18N::translate('Click to edit this name') ?>"></span>
								  <label class="sr-only"><?= I18N::translate('Edit') ?></label>
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
								<?= Bootstrap4::select(FunctionsEdit::optionsAccessLevels(), 2, ['name' => 'access_level']); ?>
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
					<?= $this->addMessage("error", "danger", true) ?>
					<?= $this->addMessage('update-settings', 'success', true, I18N::translate('The settings for this tree are succesfully updated')) ?>
					<div id="fancy-treeview-form" class="form-group">
					  <?php if (!empty($FTV_SETTINGS) && $this->searchArray($FTV_SETTINGS, 'TREE', $this->tree()->getTreeId())): ?>

						<!-- *** FORM 4 *** -->
						<form class="form-horizontal" name="form4">
						  <!-- TABLE -->
						  <table id="fancy-treeview-table" class="table table-hover">
							<thead>
							  <tr>
								<th><?= I18N::translate('Root person') ?></th>
								<?php if (!$this->options('use_fullname')): ?>
								  <th><?= I18N::translate('Surname in page title') ?></th>
								<?php endif; ?>
								<th><?= I18N::translate('Page title') ?></th>
								<th><?= I18N::translate('Access level') ?></th>
								<th><?= I18N::translate('Delete') ?></th>
							  </tr>
							</thead>
							<tbody id="ftv-sort">
							  <?php foreach ($FTV_SETTINGS as $key => $this_ITEM): ?>
								<?php if ($this_ITEM['TREE'] == $this->tree()->getTreeId()): ?>
								  <?php if (Individual::getInstance($this_ITEM['PID'], $this->tree())): ?>
									<tr class="sortme">
									  <!-- ROOT PERSONS FULL NAME -->
									  <td>
										<input
										  name="pid[<?= $key ?>]"
										  type="hidden"
										  value="<?= $this_ITEM['PID'] ?>"
										  >
										<input
										  name="sort[<?= $key ?>]"
										  type="hidden"
										  value="<?= $this_ITEM['SORT'] ?>"
										  >
										  <?= Individual::getInstance($this_ITEM['PID'], $this->tree())->getFullName() . '' ?>
										(<?= Individual::getInstance($this_ITEM['PID'], $this->tree())->getLifeSpan() ?>)
									  </td>
									  <?php if (!$this->options('use_fullname')): ?>
										<!-- SURNAME IN PAGE TITLE -->
										<td>
										  <span class="showname" data-toggle="tooltip" data-animation="false" data-container="body" title="<?= I18N::translate('Click to edit this name') ?>"><?= $this_ITEM['SURNAME'] ?></span>
										  <label class="sr-only"><?= I18N::translate('Edit') ?></label>
										  <input
											class="form-control editname"
											name="surname[<?= $key ?>]"
											type="text"
											value="<?= $this_ITEM['SURNAME'] ?>"
											>
										</td>
									  <?php endif ?>
									  <!-- PAGE TITLE -->
									  <td>
										<a href="module.php?mod=<?= $this->getName(); ?>&amp;mod_action=page&amp;ged=<?= e($this->tree()->getName()); ?>&amp;rootid=<?= $this_ITEM['PID'] ?>" target="_blank">
										  <?php
										  if ($this->options('use_fullname') == true) {
										  	echo I18N::translate('Descendants of %s', Individual::getInstance($this_ITEM['PID'], $this->tree())->getFullName());
										  } else {
										  	echo I18N::translate('Descendants of the %s family', $this_ITEM['SURNAME']);
										  } ?>
										</a>
									  </td>
									  <!-- ACCESS LEVEL -->
									  <td>
										<?= Bootstrap4::select(FunctionsEdit::optionsAccessLevels(), $this_ITEM['ACCESS_LEVEL'], ['name' => 'access_level[' . $key . ']']) ?>
									  </td>
									  <!-- DELETE BUTTON -->
									  <td>
										<button	type="button" name="delete" class="btn btn-danger btn-sm" data-key="<?= $key ?>" title="<?php I18N::translate('Delete') ?>">
										  <i class="fa fa-trash-o"></i>
										</button>
									  </td>
									</tr>
								  <?php else: ?>
									<tr>
									  <!-- SURNAME -->
									  <td class="error">
										<input
										  name="pid[<?= $key ?>]"
										  type="hidden"
										  value="<?= $this_ITEM['PID'] ?>"
										  >
										  <?= $this_ITEM['SURNAME'] ?>
									  </td>
									  <!-- ERROR MESSAGE -->
									  <td colspan="4" class="error">
										<?= I18N::translate('This individual doesn’t exist anymore in this tree') ?>
									  </td>
									  <!-- DELETE BUTTON -->
									  <td>
										<button name="delete" type="button" class="btn btn-danger btn-sm" data-key="<?= $key ?>" title="<?php I18N::translate('Delete') ?>">
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
							<?= I18N::translate('update') ?>
						  </button>
						</form>
					  <?php endif; ?>
					</div>
				  </div>
				</div>
			  </div>

			  <div class="card">
				<div class="card-header" role="tab" id="card-options-header">
				  <h5 class="mb-0">
					<a data-toggle="collapse" data-parent="#accordion" href="#card-options-content" aria-expanded="true" aria-controls="card-options-content">
					  <?= I18N::translate('Options for %s', e($this->tree()->getTitle())) ?>
					</a>
				  </h5>
				</div>
				<div id="card-options-content" class="collapse" role="tabpanel" aria-labelledby="card-options-header">
				  <div class="card-body">
					<?= $this->addMessage('save-options', 'success', true, I18N::translate('The options for this tree are succesfully saved')) ?>
					<?= $this->addMessage('reset-options', 'success', true, I18N::translate('The options for this tree are succesfully reset to the default settings')) ?>
					<?= $this->addMessage('copy-options', 'success', true, I18N::translate('The options for this tree are succesfully saved and copied to all other trees')) ?>
					<div id="ftv-options-form" class="form-group">

					  <!-- *** FORM 5 *** -->
					  <form class="form-horizontal" name="form5">
						<!-- USE FULLNAME IN MENU -->
						<div class="row form-group fullname">
						  <label class="col-form-label col-sm-4">
							<?= I18N::translate('Use fullname in menu') ?>
						  </label>
						  <div class="col-sm-8">
							<?= Bootstrap4::radioButtons('NEW_FTV_OPTIONS[USE_FULLNAME]', FunctionsEdit::optionsNoYes(), $this->options('use_fullname'), true) ?>
						  </div>
						</div>
						<!-- GENERATION BLOCKS -->
						<div class="row form-group">
						  <label class="col-form-label col-sm-4">
							<?= I18N::translate('Number of generation blocks to show') ?>
						  </label>
						  <div class="col-sm-4">
							<?= Bootstrap4::select([I18N::translate('All'), '1', '2', '3', '4', '5', '6', '7', '8', '9', '10'], $this->options('generations'), ['name' => 'NEW_FTV_OPTIONS[GENERATIONS]']) ?>									</div>
						  <p class="col-sm-8 offset-sm-4 small text-muted">
							<?= /* I18N: Help text for the “Number of generation blocks to show” configuration setting */ I18N::translate('This option is especially usefull for large trees. When you notice a slow page load, here you can set the number of generation blocks to load at once to a lower level. Below the last generation block a button will appear to add the next set of generation blocks. The new blocks will be added to the blocks already loaded. Clicking on a “follow” link in the last visible generation block, will also load the next set of generation blocks.') ?>
						  </p>
						</div>
						<!-- SHOW SINGLES -->
						<div class="row form-group">
						  <label class="col-form-label col-sm-4">
							<?= I18N::translate('Show singles') ?>
						  </label>
						  <div class="col-sm-8">
							<?= Bootstrap4::radiobuttons('NEW_FTV_OPTIONS[SHOW_SINGLES]', FunctionsEdit::optionsNoYes(), $this->options('show_singles'), true) ?>									</div>
						  <p class="col-sm-8 offset-sm-4 small text-muted">
							<?= /* I18N: Help text for the “Show singles” configuration setting */ I18N::translate('Turn this option on if you want to display singles in the next generation blocks. Singles are individuals without partner and children. With this option turned on, every child of a family will be displayed in a detailed way in the next generation block.') ?>
						  </p>
						</div>
						<!-- CHECK RELATIONSHIP -->
						<div class="row form-group">
						  <label class="col-form-label col-sm-4">
							<?= I18N::translate('Check relationship between partners') ?>
						  </label>
						  <div class="col-sm-8">
							<?= Bootstrap4::radioButtons('NEW_FTV_OPTIONS[CHECK_RELATIONSHIP]', FunctionsEdit::optionsNoYes(), $this->options('check_relationship'), true) ?>
						  </div>
						  <p class="col-sm-8 offset-sm-4 small text-muted">
							<?= /* I18N: Help text for the “Check relationship between partners” configuration setting */ I18N::translate('With this option turned on, the script checks if a (married) couple has the same ancestors. If a relationship between the partners is found, a text will appear between brackets after the spouses’ name to indicate the blood relationship.') ?></p>
						  <p class="col-sm-8 offset-sm-4 small text-muted">
							<?= /* I18N: Warning when using the “Check relationship between partners” configuration setting */ I18N::translate('<strong>Note</strong>: this option can be time and/or memory consuming, especially on large trees. It can cause very slow page loading or an ’execution time out error’ on your server. If you notice such a behavior, reduce the number of generation blocks to load at once or don’t use it in combination with the option to show singles (see the previous options). If you still experience any problems, don’t use this option at all.') ?>
						  </p>
						</div>
						<!-- SHOW PLACES -->
						<div id="places" class="row form-group">
						  <label class="col-form-label col-sm-4">
							<?= I18N::translate('Show places') ?>
						  </label>
						  <div class="col-sm-8">
							<?= Bootstrap4::radioButtons('NEW_FTV_OPTIONS[SHOW_PLACES]', FunctionsEdit::optionsNoYes(), $this->options('show_places'), true) ?>
						  </div>
						</div>
						<!-- USE GEDCOM PLACE SETTING -->
						<div id="gedcom_places" class="row form-group<?php if (!$this->options('show_places')) {
										  	echo ' collapse';
										  } ?>">
						  <label class="col-form-label col-sm-4">
							<?= I18N::translate('Use default GEDCOM settings to abbreviate place names') ?>
						  </label>
						  <div class="col-sm-8">
							<?= Bootstrap4::radioButtons('NEW_FTV_OPTIONS[USE_GEDCOM_PLACES]', FunctionsEdit::optionsNoYes(), $this->options('use_gedcom_places'), true) ?>
						  </div>
						  <p class="col-sm-8 offset-sm-4 small text-muted">
							<?= /* I18N: Help text for the “Use default GEDCOM settings to abbreviate place names” configuration setting */ I18N::translate('If you have ticked the “Show places” option, you can choose to use the default GEDCOM settings to abbreviate placenames. If you don’t set this option, full place names will be shown.') ?>
						  </p>
						</div>
						<!-- GET COUNTRYLIST -->
						<?php if ($this->getCountrylist()): ?>
						  <div id="country_list" class="row form-group<?php if (!$this->options('show_places') || $this->options('use_gedcom_places')) {
										  	echo ' collapse';
										  } ?>">
							<label class="col-form-label col-sm-4">
							  <?= I18N::translate('Select your country') ?>
							</label>
							<div class="col-sm-8">
							  <?= Bootstrap4::select($this->getCountryList(), $this->options('country'), ['name' => 'NEW_FTV_OPTIONS[COUNTRY]']) ?>
							</div>
							<p class="col-sm-8 offset-sm-4 small text-muted">
							  <?= /* I18N: Help text for the “Select your country” configuration setting */ I18N::translate('If you have ticked the “Show places” option but NOT the option to abbreviate placenames, you can set your own country here. Full places will be listed on the Fancy Treeview pages, but when a place includes the name of your own country, this name will be left out. If you don’t select a country then all countries will be shown, including your own.') ?>
							</p>
						  </div>
						<?php endif; ?>
						<!-- SHOW OCCUPATIONS -->
						<div class="row form-group">
						  <label class="col-form-label col-sm-4">
							<?= I18N::translate('Show occupations') ?>
						  </label>
						  <div class="col-sm-8">
							<?= Bootstrap4::radioButtons('NEW_FTV_OPTIONS[SHOW_OCCU]', FunctionsEdit::optionsNoYes(), $this->options('show_occu'), true) ?>
						  </div>
						</div>
						<!-- THUMBNAIL WIDTH -->
						<div id="thumbnail_width" class="row form-group">
						  <label class="col-form-label col-sm-4">
							<?= I18N::translate('Thumbnail width in pixels') ?>
						  </label>
						  <div class="form-inline col-sm-8">
							<input
							  class="form-control mr-4"
							  id="NEW_FTV_OPTIONS[THUMBNAIL_SIZE]"
							  name="NEW_FTV_OPTIONS[THUMBNAIL_WIDTH]"
							  type="text"
							  value="<?= $this->options('thumbnail_width') ?>"
							  >
						  </div>
						  <p class="col-sm-8 offset-sm-4 small text-muted">
							<?= /* I18N: Help text for the “Thumbnail width in pixels” configuration setting */ I18N::translate('Here you can set the width of the thumbnails which will be displayed on the Fancy Treeview page. The height will be automatically calculated.') ?>
						  </p>
						</div>
						<!-- CROP THUMBNAILS -->
						<div id="crop-thumbnails" class="row form-group">
						  <label class="col-form-label col-sm-4">
							<?= I18N::translate('Crop thumbnails') ?>
						  </label>
						  <div class="col-sm-8">
							<?= Bootstrap4::radiobuttons('NEW_FTV_OPTIONS[CROP_THUMBNAILS]', FunctionsEdit::optionsNoYes(), $this->options('crop_thumbnails'), true) ?>
						  </div>
						  <p class="col-sm-8 offset-sm-4 small text-muted">
							<?= /* I18N: Help text for the “Crop thumbnails” configuration setting */ I18N::translate('If you choose “yes” the thumbnails will be resized to square thumbnails (same width and height) and cropped if neccessary. If you choose “no” the thumbnails will be resized proportionally.') ?>
						  </p>
						</div>
						<!-- SHOW USERFORM -->
						<div class="row form-group">
						  <label class="col-form-label col-sm-4">
							<?= I18N::translate('Show form to change start person') ?>
						  </label>
						  <div class="col-sm-4">
							<?= Bootstrap4::select(FunctionsEdit::optionsAccessLevels(), $this->options('show_userform'), ['name' => 'NEW_FTV_OPTIONS[SHOW_USERFORM]']) ?>
						  </div>
						</div>
						<!-- SHOW FANCY TREEVIEW ON INDI PAGE -->
						<div class="row form-group">
						  <label class="col-form-label col-sm-4">
							<?= I18N::translate('Show a Fancy Treeview tab on the individual page') ?>
						  </label>
						  <div class="col-sm-8">
							<?= Bootstrap4::radiobuttons('NEW_FTV_OPTIONS[FTV_TAB]', FunctionsEdit::optionsNoYes(), $this->options('ftv_tab'), true) ?>
						  </div>
						  <p class="col-sm-8 offset-sm-4 small text-muted">
							<?= /* I18N: Help text for the “Show Fancy Treeview on Indi Page” configuration setting */ I18N::translate('If you enable this option, a Fancy Treeview tab with the title “Descendants” will be shown on the individual page. The tab will describe the current individual with his family and the next two generations (if there are any). If this individual has more descendants then the two generations shown, a link will be displayed to the full Fancy Treeview Page where this individual will be displayed with all his descendants.') ?>
						  </p>
						</div>
						<!-- BUTTONS -->
						<div class="row form-group">
						  <div class="col-md-6">
							<button name="save-options" class="btn btn-primary" type="submit">
							  <i class="fa fa-check"></i>
							  <?= I18N::translate('save') ?>
							</button>
							<button name="reset-options" class="btn btn-primary" type="reset">
							  <i class="fa fa-recycle"></i>
							  <?= I18N::translate('reset') ?>
							</button>
						  </div>
						  <?php if (count(Tree::getAll()) > 1): ?>
							<div class="col-md-6 text-right">
							  <button id="save-and-copy" name="copy-options" class="btn btn-primary" type="button">
								<i class="fa fa-check"></i>
								<?= I18N::translate('save and copy options to other trees') ?>
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
		  </div>
		</div>
		<?php
	}
}

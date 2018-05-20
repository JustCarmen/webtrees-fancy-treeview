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
use Fisharebest\Webtrees\Controller\BaseController;
use Fisharebest\Webtrees\Controller\PageController;
use Fisharebest\Webtrees\Functions\FunctionsEdit;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Theme;
use JustCarmen\WebtreesAddOns\FancyTreeview\FancyTreeviewClass;

class PageTemplate extends FancyTreeviewClass {
	protected function pageContent() {
		global $controller;
		$controller = new PageController;

		if ($this->getRootPerson() && $this->getRootPerson()->canShowName()) {
			return
		  $this->pageHeader($controller) .
		  $this->pageBody($controller);
		} else {
			return $this->pageMessage($controller);
		}
	}

	protected function pageTitle() {
		return /* I18N: %s is the name of the root individual */ I18N::translate('Descendants of %s', $this->getRootPerson()->getFullName());
	}

	protected function pageHeader(PageController $controller) {
		$controller
		->setPageTitle($this->pageTitle())
		->pageHeader()
		->addInlineJavascript('var FTV_GENERATIONS = "' . $this->options('generations') . '";', BaseController::JS_PRIORITY_HIGH)
		->addExternalJavascript($this->directory . '/js/page.js');

		if ($this->pdf()) {
			$this->pdf()->includeJs($controller);
		}
	}

	protected function pageBody(PageController $controller) {
		global $WT_TREE;

		ob_start(); ?>

    <!-- FANCY TREEVIEW PAGE -->
    <div class="fancy-treeview container theme-<?= Theme::theme()->themeId() ?>">
      <div id="fancy-treeview-page" class="fancy-treeview-page">
        <div class="page-header d-flex">
          <h2 class="text-center col"><?= $controller->getPageTitle() ?></h2>
          <?php
		  if ($this->pdf()) {
		  	echo $this->pdf()->getPdfIcon();
		  } ?>
        </div>
        <?php
		if ($this->pdf()) {
			echo $this->pdf()->getPdfWaitingMessage();
		} ?>
        <div class="page-body px-3">
          <?php if ($this->options('show_userform') >= Auth::accessLevel($this->tree())): ?>
            <form id="change-root">
              <div class="row form-group justify-content-end jc-change-root">
                <label class="col-form-label col-md-4"><?= I18N::translate('Change root person') ?></label>
                <div class="col-md-3">
                  <div class="input-group">
                    <?= FunctionsEdit::formControlIndividual($WT_TREE, null, ['id' => 'new-pid', 'name' => 'PID']) ?>
                    <span class="input-group-btn">
                      <button name="btn-go" class="btn btn-primary" type="submit">
                        <?= I18N::translate('Go') ?>
                      </button>
                    </span>
                  </div>
                </div>
              </div>
            </form>
            <div id="error"></div>
          <?php endif; ?>
          <ol class="fancy-treeview-content m-0 p-0"><?= $this->pageBodyContent() ?></ol>
          <?php if ($this->generations() > 0 && $this->generation > $this->generations()): ?>
            <div id="btn-next" class="d-flex justify-content-end">
              <input
                class="btn btn-primary"
                type="button"
                name="next"
                value="<?= I18N::translate('next') ?>"
                >
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php
	ob_get_contents();
	}

	protected function pageBodyContent() {
		return $this->printPage();
	}

	private function pageMessage($controller) {
		http_response_code(404);
		$controller->pageHeader();
		echo $this->addMessage('alert', 'warning', false, I18N::translate('This individual does not exist or you do not have permission to view it.'));
		return;
	}
}

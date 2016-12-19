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
use Fisharebest\Webtrees\I18N;
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
		/* I18N: %s is the name of the root individual */ I18N::translate('Descendants of %s', $this->getRootPerson()->getFullName());
	}

	protected function pageHeader(PageController $controller) {
		$controller
			->setPageTitle($this->pageTitle())
			->pageHeader();

		// add javascript files and scripts
		$this->includeJs($controller, 'page');
		if ($this->pdf()) {
			$this->pdf()->includeJs($controller);
		}
	}

	protected function pageBody(PageController $controller) {
		?>
		<!-- FANCY TREEVIEW PAGE -->
		<div id="fancy_treeview-page">
			<div id="page-header">
				<h2><?php echo $controller->getPageTitle() ?></h2>
				<?php
				if ($this->pdf()) {
					echo $this->pdf()->getPdfIcon();
				}
				?>
			</div>
			<div id="page-body">
				<?php
				if ($this->pdf()) {
					echo $this->pdf()->getPdfWaitingMessage();
				}
				?>
				<?php if ($this->options('show_userform') >= Auth::accessLevel($this->tree())): ?>
					<form id="change_root">
						<label class="label"><?php echo I18N::translate('Change root person') ?></label>
						<input
							data-autocomplete-type="INDI"
							id="new_rootid"
							name="new_rootid"
							placeholder="<?php echo I18N::translate('Search ID by name') ?>"
							type="text"
							>
						<input
							id="btn_go"
							class="btn btn-primary btn-sm"
							name="btn_go"
							type="submit"
							value="<?php echo I18N::translate('Go') ?>"
							>
					</form>
					<div id="error"></div>
				<?php endif; ?>
					<ol id="fancy_treeview"><?php echo $this->pageBodyContent() ?></ol>
				<div id="btn_next">
					<input
						class="btn btn-primary"
						type="button"
						name="next"
						value="<?php echo I18N::translate('next') ?>"
						>
				</div>
			</div>
		</div>
		<?php
	}

	protected function pageBodyContent() {
		return $this->printPage($this->options('numblocks'));
	}

	private function pageMessage($controller) {
		http_response_code(404);
		$controller->pageHeader();
		echo $this->addMessage('alert', 'warning', false, I18N::translate('This individual does not exist or you do not have permission to view it.'));
		return;
	}

}

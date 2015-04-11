<?php
namespace Fisharebest\Webtrees;

/*
 * webtrees: online genealogy
 * Copyright (C) 2015 webtrees development team
 * Copyright (C) 2015 JustCarmen
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
?>

<!-- FANCY TREE VIEW PAGE -->
<div id="fancy_treeview-page">
	<div id="page-header">
		<h2><?php echo $controller->getPageTitle(); ?></h2>
		<?php if ($ftv->options('show_pdf_icon') >= Auth::accessLevel($WT_TREE)): ?>
		<div id="dialog-confirm" title="<?php echo I18N::translate('Generate PDF'); ?>" style="display:none">
			<p><?php echo I18N::translate('The pdf contains only visible generation blocks.'); ?></p>
		</div>
		<a id="pdf" href="#"><i class="icon-mime-application-pdf"></i></a>
		<?php endif; ?>
	</div>
	<div id="page-body">
		<?php if ($ftv->options('show_userform') >= Auth::accessLevel($WT_TREE)): ?>
		<form id="change_root">
			<label class="label"><?php echo I18N::translate('Change root person'); ?></label>
			<input
				data-autocomplete-type="INDI"
				id="new_rootid"
				name="new_rootid"
				placeholder="<?php echo I18N::translate('Search ID by name'); ?>"
				type="text"
				>
			<input
				id="btn_go"
				name="btn_go"
				type="submit"
				value="<?php echo I18N::translate('Go'); ?>"
				>
		</form>
		<div id="error"></div>
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

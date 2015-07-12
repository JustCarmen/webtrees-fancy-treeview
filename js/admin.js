/*
 * Fancy Treeview admin configuration page script
 *
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

/* global ModuleName */

// Close the alerts without removal (Bootstrap default)
jQuery(".alert .close").on("click", function () {
	jQuery(this).parent().hide();
});

/*** FORM 1 ***/
jQuery("#tree").change(function () {
	// get the config page for the selected tree
	var tree_name = jQuery(this).find("option:selected").data("ged");
	window.location = "module.php?mod=" + ModuleName + "&mod_action=admin_config&ged=" + tree_name;
});

/*** FORM 2 ***/
// add search values from form2 to form3
jQuery("#ftv-search-form").on("submit", "form[name=form2]", function (e) {
	e.preventDefault();
	var tree = jQuery("#tree").find("option:selected").val();
	var table = jQuery("#search-result-table");
	jQuery.ajax({
		type: "POST",
		url: "module.php?mod=" + ModuleName + "&mod_action=admin_search&tree=" + tree,
		data: jQuery(this).serialize(),
		dataType: "json",
		success: function (data) {
			jQuery(".ui-autocomplete").hide();
			if (data.hasOwnProperty("error")) {
				jQuery("form[name=form3] table").hide();
				jQuery("#error .message").html(data.error).parent().fadeIn();
				jQuery("input#PID").val("");
				jQuery("input#SURNAME").val("").focus();
			} else {
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
/** @param {event} e */
jQuery("#ftv-search-form").on("submit", "form[name=form3]", function (e) {
	e.preventDefault();
	var tree = jQuery("#tree").find("option:selected").val();
	jQuery.ajax({
		type: "POST",
		url: "module.php?mod=" + ModuleName + "&mod_action=admin_add&tree=" + tree,
		data: jQuery(this).serialize(),
		success: function () {
			jQuery("#fancy-treeview-form").load("module.php?mod=" + ModuleName + "&mod_action=admin_config #fancy-treeview-form form");
			jQuery("#ftv-search-form input").val("");
			jQuery("#search-result-table").fadeOut("slow");
		}
	});
});

/*** FORM 3 AND 4 ***/
// click on a surname to get an input textfield to change the surname to a more appropriate name.
jQuery("#panel1").on("click", ".showname", function () {
	jQuery(this).hide();
	jQuery(this).next(".editname").show();
});

/*** FORM 4 ***/
// make the table sortable
jQuery("#fancy-treeview-form").sortable({
	items: ".sortme",
	forceHelperSize: true,
	forcePlaceholderSize: true,
	opacity: 0.7,
	cursor: "move",
	axis: "y"
});

//-- update the order numbers after drag-n-drop sorting is complete
jQuery("#fancy-treeview-form").bind("sortupdate", function (event, ui) {
	jQuery("#" + jQuery(this).attr("id") + " input[name^=sort]").each(

	function (index, value) {
		value.value = index + 1;
	});
});

// update settings form4
jQuery("#fancy-treeview-form").on("submit", "form[name=form4]", function (e) {
	e.preventDefault();
	jQuery.ajax({
		type: "POST",
		url: "module.php?mod=" + ModuleName + "&mod_action=admin_update",
		data: jQuery(this).serialize(),
		success: function () {
			jQuery("#fancy-treeview-form").load("module.php?mod=" + ModuleName + "&mod_action=admin_config #fancy-treeview-form form", function () {
				jQuery("#update-settings").fadeIn();
				var target = jQuery("#update-settings").offset().top - 60;
				jQuery("html, body").animate({
					scrollTop: target
				}, 800);
			});
		}
	});
});

// delete row from form4
jQuery("#fancy-treeview-form").on("click", "button[name=delete]", function (e) {
	e.preventDefault();
	var key = jQuery(this).data("key");
	var row = jQuery(this).parents("tr");
	var rowCount = jQuery("#fancy-treeview-table > tbody > tr").length - 1;
	jQuery.ajax({
		type: "GET",
		url: "module.php?mod=" + ModuleName + "&mod_action=admin_delete&key=" + key,
		success: function () {
			row.remove();
			if (rowCount === 0) {
				jQuery("#fancy-treeview-form form").remove();
			}
			jQuery("#update-settings").fadeIn();
			var target = jQuery("#update-settings").offset().top - 60;
			jQuery("html, body").animate({
				scrollTop: target
			}, 800);
		}
	});

});

/*** FORM 5 ***/
// update options
/** @param {event} e */
jQuery("#ftv-options-form").on("submit", "form[name=form5]", function (e) {
	e.preventDefault();
	var tree = jQuery("#tree").find("option:selected").val();
	jQuery.ajax({
		type: "POST",
		url: "module.php?mod=" + ModuleName + "&mod_action=admin_save&tree=" + tree,
		data: jQuery(this).serialize(),
		success: function () {
			jQuery("#ftv-search-form").load("module.php?mod=" + ModuleName + "&mod_action=admin_config #ftv-search-form form", function () {
				jQuery(this).find("#search-result-table").hide().removeClass("hidden");
			});
			jQuery("#fancy-treeview-form").load("module.php?mod=" + ModuleName + "&mod_action=admin_config #fancy-treeview-form form");
			jQuery("#ftv-options-form").load("module.php?mod=" + ModuleName + "&mod_action=admin_config #ftv-options-form form", function () {
				jQuery("#reset-options").hide();
				jQuery("#save-options").fadeIn();
				var target = jQuery("#save-options").offset().top - 60;
				jQuery("html, body").animate({
					scrollTop: target
				}, 800);
			});
		}
	});
});

// reset options
jQuery("#ftv-options-form").on("reset", "form[name=form5]", function (e) {
	e.preventDefault();
	var tree = jQuery("#tree").find("option:selected").val();
	jQuery.ajax({
		type: "GET",
		url: "module.php?mod=" + ModuleName + "&mod_action=admin_reset&tree=" + tree,
		success: function () {
			jQuery("#ftv-search-form").load("module.php?mod=" + ModuleName + "&mod_action=admin_config #ftv-search-form form", function () {
				jQuery(this).find("#search-result-table").hide().removeClass("hidden");
			});
			jQuery("#fancy-treeview-form").load("module.php?mod=" + ModuleName + "&mod_action=admin_config #fancy-treeview-form form");
			jQuery("#ftv-options-form").load("module.php?mod=" + ModuleName + "&mod_action=admin_config #ftv-options-form form", function () {
				jQuery("#save-options").hide();
				jQuery("#reset-options").fadeIn();
				var target = jQuery("#reset-options").offset().top - 60;
				jQuery("html, body").animate({
					scrollTop: target
				}, 800);
			});
		}
	});
});

jQuery("#ftv-options-form").on("click", "#resize_thumbs input[type=radio]", function () {
	var field = jQuery("#ftv-options-form").find("#thumb_size, #square_thumbs");
	jQuery(this).val() === "1" ? field.fadeIn() : field.fadeOut();
});

jQuery("#ftv-options-form").on("click", "#places input[type=radio]", function () {
	var field1 = jQuery("#ftv-options-form").find("#gedcom_places");
	var field2 = jQuery("#ftv-options-form").find("#country_list");
	if (jQuery(this).val() === "1") {
		field1.fadeIn();
		if (field1.find("input[type=radio]:checked").val() === "0") field2.fadeIn();
	} else {
		field1.fadeOut();
		field2.fadeOut();
	}
});

jQuery("#ftv-options-form").on("click", "#gedcom_places input[type=radio]", function () {
	var field = jQuery("#ftv-options-form").find("#country_list");
	jQuery(this).val() === "0" ? field.fadeIn() : field.fadeOut();
});
/*!
 * webtrees: online genealogy
 * Copyright (C) 2018 JustCarmen (http://justcarmen.nl)
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

var moduleName = qstring('mod');

// Sortable table - https://github.com/RubaXa/Sortable
// Script: https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.6.0/Sortable.min.js
$.fn.jcSort = function() {
  // only use function if external javascript is loaded
  if (typeof Sortable === "function") {
    var sortRows = this[0];
    new Sortable(sortRows, {
      animation: 150,
      draggable: ".sortme",
      onSort: function(evt) {
        $(evt.item).parent().find('input[name^=sort]').each(function(index, value) {
          value.value = index + 1;
        });
      }
    });
  }
};

// Scroll function
$.fn.jcScroll = function(options) {
  var defaults = {
    offset: 60,
    time: 800
  };
  var option = $.extend(defaults, options);

  var target = $(this).offset().top - option.offset;
  this.fadeIn();
  $("html, body").animate({
    scrollTop: target
  }, option.time);
  return false;
};

// Get querystring
function qstring(key, url) {
  'use strict';
  var KeysValues, KeyValue, i;
  if (url === null || url === undefined) {
    url = window.location.href;
  }
  KeysValues = url.split(/[\?&]+/);
  for (i = 0; i < KeysValues.length; i++) {
    KeyValue = KeysValues[i].split("=");
    if (KeyValue[0] === key) {
      return KeyValue[1];
    }
  }
}

// Close alerts without removal so we can use them again
$(".alert").on("click", ".close", function() {
  $(this).parents(".alert-message").hide();
  return false;
});

// Activate tooltips
$('[data-toggle="tooltip"]').tooltip();

// =============================================================================
// Fancy Treeview admin
// =============================================================================

// Rebind functions after ajax call (all forms)
$(document).ajaxComplete(function() {
  $("#ftv-sort").jcSort();
  $('[data-toggle="tooltip"]').tooltip();
});

/*** FORM 1 ***/
$("#tree").change(function() {
	// get the config page for the selected tree
	var tree = $(this).find("option:selected").val();
	window.location = "module.php?mod=" + moduleName + "&mod_action=admin_config&ged=" + tree;
});

/*** FORM 2 ***/
// add search values from form2 to form3
$("#ftv-search-form").on("submit", "form[name=form2]", function(e) {
	e.preventDefault();
	var tree = $("#tree").find("option:selected").val();
	var table = $("#search-result-table");
	$.ajax({
		type: "POST",
		url: "module.php?mod=" + moduleName + "&mod_action=admin_search&ged=" + tree,
		data: $(this).serialize(),
		dataType: "json",
		success: function(data) {
			if (data.hasOwnProperty("error")) {
				$("form[name=form3] table").hide();
				$("#error").find(".message").text(data.error).end().fadeIn();
			} else {
				$("#error").hide();
				table.find("input[name=pid]").val(data.pid);
				table.find("input[name=sort]").val(data.sort);
				table.find("#root span").html(data.root);
				table.find("input[name=surname]").val(data.surname);
				table.find(".showname").text(data.surname).show();
        table.find(".editname").hide();
				table.find("#title").html(data.title);
				table.show();
			}
			$("#pid-search").val(null).trigger("change"); // select2 selectbox
			$("#surname-search").val(null).focus();
		}
	});
});

/*** FORM 3 ***/
// add search results to table
/** @param {event} e */
$("#ftv-search-form").on("submit", "form[name=form3]", function(e) {
	e.preventDefault();
	var tree = $("#tree").find("option:selected").val();
	$.ajax({
		type: "POST",
		url: "module.php?mod=" + moduleName + "&mod_action=admin_add&ged=" + tree,
		data: $(this).serialize(),
		success: function() {
			$("#fancy-treeview-form").load("module.php?mod=" + moduleName + "&mod_action=admin_config #fancy-treeview-form form");
			$("#ftv-search-form input").val("");
			$("#search-result-table").fadeOut("slow");
		}
	});
});

/*** FORM 3 AND 4 ***/
// click on a surname to get an input textfield to change the surname to a more appropriate name.
$(".fancy-treeview-admin").on("click", ".showname", function() {
	$(this).hide();
	$(this).parent().find(".editname").show();
});

/*** FORM 4 ***/
$("#ftv-sort").jcSort();

// update settings form4
$("#fancy-treeview-form").on("submit", "form[name=form4]", function(e) {
	e.preventDefault();
	$.ajax({
		type: "POST",
		url: "module.php?mod=" + moduleName + "&mod_action=admin_update",
		data: $(this).serialize(),
		success: function() {
			$("#fancy-treeview-form").load("module.php?mod=" + moduleName + "&mod_action=admin_config #fancy-treeview-form form", function() {
				$("#update-settings").jcScroll();
			});
		}
	});
});

// delete row from form4
$("#fancy-treeview-form").on("click", "button[name=delete]", function(e) {
	e.preventDefault();
	var key = $(this).data("key");
	var row = $(this).parents("tr");
	var rowCount = $("#fancy-treeview-table > tbody > tr").length - 1;
	$.ajax({
		type: "GET",
		url: "module.php?mod=" + moduleName + "&mod_action=admin_delete&key=" + key,
		success: function() {
			row.remove();
			if (rowCount === 0) {
				$("#fancy-treeview-form form").remove();
			}
			$("#update-settings").jcScroll();
		}
	});

});

/*** FORM 5 ***/
// update options
/** @param {event} e */
$("#ftv-options-form").on("submit", "form[name=form5]", function(e) {
	e.preventDefault();
	var tree = $("#tree").find("option:selected").val();
	$.ajax({
		type: "POST",
		url: "module.php?mod=" + moduleName + "&mod_action=admin_save&tree=" + tree,
		data: $(this).serialize(),
		success: function() {
			$("#ftv-search-form").load("module.php?mod=" + moduleName + "&mod_action=admin_config #ftv-search-form form", function() {
				$(this).find("#search-result-table").hide().removeClass("hidden");
			});
			$("#fancy-treeview-form").load("module.php?mod=" + moduleName + "&mod_action=admin_config #fancy-treeview-form form");
			$("#ftv-options-form").load("module.php?mod=" + moduleName + "&mod_action=admin_config #ftv-options-form form", function() {
				$("#reset-options, #copy-options").hide();
				$("#save-options").jcScroll();
			});
		}
	});
});

// reset options
$("#ftv-options-form").on("reset", "form[name=form5]", function(e) {
	e.preventDefault();
	var tree = $("#tree").find("option:selected").val();
	$.ajax({
		type: "GET",
		url: "module.php?mod=" + moduleName + "&mod_action=admin_reset&tree=" + tree,
		success: function() {
			$("#ftv-search-form").load("module.php?mod=" + moduleName + "&mod_action=admin_config #ftv-search-form form", function() {
				$(this).find("#search-result-table").hide().removeClass("hidden");
			});
			$("#fancy-treeview-form").load("module.php?mod=" + moduleName + "&mod_action=admin_config #fancy-treeview-form form");
			$("#ftv-options-form").load("module.php?mod=" + moduleName + "&mod_action=admin_config #ftv-options-form form", function() {
				$("#save-options, #copy-options").hide();
				$("#reset-options").jcScroll();
			});
		}
	});
});

// copy options to other trees
$("#ftv-options-form").on("click", "#save-and-copy", function() {
	$.ajax({
		type: "POST",
		url: "module.php?mod=" + moduleName + "&mod_action=admin_copy",
		data: $("form[name=form5]").serialize(),
		success: function() {
			$("#ftv-search-form").load("module.php?mod=" + moduleName + "&mod_action=admin_config #ftv-search-form form", function() {
				$(this).find("#search-result-table").hide().removeClass("hidden");
			});
			$("#fancy-treeview-form").load("module.php?mod=" + moduleName + "&mod_action=admin_config #fancy-treeview-form form");
			$("#ftv-options-form").load("module.php?mod=" + moduleName + "&mod_action=admin_config #ftv-options-form form", function() {
				$("#save-options, #reset-options").hide();
				$("#copy-options").jcScroll();
			});
		}
	});
});

$("#ftv-options-form").on("click", "#places input[type=radio]", function() {
	var field1 = $("#ftv-options-form").find("#gedcom_places");
	var field2 = $("#ftv-options-form").find("#country_list");
	if ($(this).val() === "1") {
		field1.fadeIn();
		if (field1.find("input[type=radio]:checked").val() === "0")
			field2.fadeIn();
	} else {
		field1.fadeOut();
		field2.fadeOut();
	}
});

$("#ftv-options-form").on("click", "#gedcom_places input[type=radio]", function() {
	var field = $("#ftv-options-form").find("#country_list");
	$(this).val() === "0" ? field.fadeIn() : field.fadeOut();
});

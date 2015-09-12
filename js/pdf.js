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

/* global ModuleName, WT_CSRF_TOKEN, RootID, PageTitle */

// convert page to pdf
jQuery("#pdf").click(function () {
	if (jQuery("#btn_next").length > 0) {
		jQuery("#dialog-confirm").dialog({
			resizable: false,
			width: 300,
			modal: true,
			buttons: {
				"ok": function () {
					createPDF();
					jQuery(this).dialog("close");
				},
				"cancel": function () {
					jQuery(this).dialog("close");
				}
			}
		});
		jQuery('.ui-dialog-buttonpane button:contains(ok)').html(TextOk);
		jQuery('.ui-dialog-buttonpane button:contains(cancel)').html(TextCancel);
	} else {
		createPDF();
	}
});

function createPDF() {
	// clone the content now
	jQuery("body").append('<div id="pdf-content">');
	jQuery("#pdf-content").append(jQuery("#fancy_treeview-page").clone()).hide();

	var content = jQuery("#pdf-content");
	var counter = jQuery("a.gallery img", content).length;

	if (counter > 0) {
		function qstring(key, url) {
			KeysValues = url.split(/[\?&]+/);
			for (i = 0; i < KeysValues.length; i++) {
				KeyValue = KeysValues[i].split("=");
				if (KeyValue[0] === key) {
					return KeyValue[1];
				}
			}
		}
		jQuery("a.gallery img", content).each(function () {
			var mid = qstring("mid", jQuery(this).attr("src"));
			var thumb = qstring("thumb", jQuery(this).attr("src"));
			jQuery.ajax({
				type: "GET",
				url: "module.php?mod=" + ModuleName + "&mod_action=pdf_thumb_data&mid=" + mid + "&thumb=" + thumb,
				context: this,
				success: function (data) {
					jQuery(this).attr("src", data);
					counter--;
					if (counter === 0) {
						getPDF();
					}
				}
			});
		});
	} else {
		getPDF();
	}
}

function getPDF() {
	jQuery.when(modifyContent()).then(function () {
		jQuery.ajax({
			type: "POST",
			url: "module.php?mod=" + ModuleName + "&mod_action=pdf_data",
			data: {
				"pdfContent": jQuery("#new-pdf-content").html()
			},
			csrf: WT_CSRF_TOKEN,
			success: function () {
				jQuery("#pdf-content, #new-pdf-content").remove();
				window.location.href = "module.php?mod=" + ModuleName + "&mod_action=show_pdf&rootid=" + RootID + "&title=" + PageTitle;
			}
		});
	});
}

function modifyContent() {
	var content = jQuery("#pdf-content");
	
	// first reset the special blockheader in the colors and clouds theme back to default
	jQuery("table.blockheader", content).each(function () {
		jQuery(this).replaceWith('<div class="blockheader">' + jQuery(this).html() + '</div>');
	});

	// remove or unwrap all elements we do not need in pdf display
	jQuery(".hidden, .header-link, .tooltip-text", content).remove();
	jQuery(".generation.private", content).parents(".generation-block").remove();
	jQuery(".generation-block", content).removeAttr("data-gen data-pids");
	jQuery(".blockheader", content).removeClass("ui-state-default");
	jQuery("a, span.SURN, span.date", content).contents().unwrap();
	jQuery("a", content).remove(); //left-overs

	// mPDF doesn't support dir="auto", so set the textdirection to rtl if needed.
	if (textDirection === "rtl") {
		jQuery("span[dir=auto]", content).each(function () {
			jQuery(this).attr("dir", "rtl")
		})
	}

	// Set some extra classes
	jQuery(".parents", content).each(function () {
		jQuery(".NAME:first", this).addClass("parents-name");
	})
	jQuery(".children p", content).addClass("children-text");

	// Turn blocks into a table for better display in pdf
	jQuery(".family", content).each(function () {
		var obj = jQuery(this);
		obj.find(".desc").replaceWith("<td class=\"desc\">" + obj.find(".desc").html());
		obj.find("img").wrap("<td class=\"image\" style=\"width:" + obj.find("img").width() + "px\">");
		obj.find(".parents").replaceWith("<table class=\"parents\"><tr>" + obj.find(".parents").html());
		obj.find(".child").each(function () {
			jQuery(this).replaceWith("<tr><td>" + jQuery(this).html());
		});
		obj.find(".children ol").each(function () {
			jQuery(this).replaceWith('<table class="children-list">' + jQuery(this).html());
		});
	});

	jQuery(".private", content).each(function () {
		jQuery(this).append("<table class=\"parents\"><tr><td>" + jQuery(this).text());
	});

	//mPDF does not support multilevel ordered list, so we make our own
	jQuery(".generation-block", content).each(function (index) {
		var main = (index + 1);
		jQuery(this).find(".generation").each(function () {
			jQuery(this).find(".family").each(function (index) {
				var i = (index + 1);
				if (textDirection === "rtl") {
					var dot = "";
				} else {
					var dot = ".";
				}
				jQuery(this).find(".parents tr").prepend("<td class=\"index\">" + main + "." + i + dot + " </td>");
				jQuery(this).find(".children tr").each(function (index) {
					jQuery(this).prepend("<td class=\"index\">" + main + "." + i + "." + (index + 1) + dot + "  </td>");
				});
			});
		});
	});

	// Simplify the output
	content.after('<div id="new-pdf-content">');
	var pdf_content = jQuery("#new-pdf-content").hide();
	pdf_content.append(jQuery("h2", content));
	jQuery(".blockheader, .parents, .children-text, .children-list", content).each(function () {
		jQuery(this).appendTo(pdf_content);
	});

	jQuery("h2, .blockheader, .parents, .children-text, .children-list", pdf_content).after('\n');
}
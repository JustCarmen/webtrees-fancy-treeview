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

// convert page to pdf
jQuery("#pdf").click(function(){
	if (jQuery("#btn_next").length > 0) {
		var $dialog = jQuery("#dialog-confirm").dialog({
			resizable: false,
			width: 300,
			modal: true,
			buttons : {
				"ok" : function() {
					getPDF();
					jQuery(this).dialog("close");
				},
				"cancel" : function() {
					jQuery(this).dialog("close");
				}
			}
		});
		jQuery('.ui-dialog-buttonpane button:contains(ok)').html(TextOk);
		jQuery('.ui-dialog-buttonpane button:contains(cancel)').html(TextCancel);
	}
	else {
		getPDF();
	}
});

function getPDF() {
	// get image source for default webtrees thumbs
	if(jQuery(".ftv-thumb").length === 0) {
		function qstring(key, url) {
			KeysValues = url.split(/[\?&]+/);
			for (i = 0; i < KeysValues.length; i++) {
				KeyValue= KeysValues[i].split("=");
				if (KeyValue[0] === key) {
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
				url: "module.php?mod=" + ModuleName + "&mod_action=image_data&mid=" + mid,
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
		jQuery(this).attr("src", jQuery(this).parent().attr("href") + "&thumb=1");
	});

	//dompdf does not support ordered list, so we make our own
	jQuery(".generation-block", content).each(function(index) {
		var main = (index+1);
		jQuery(this).find(".generation").each(function(){
			jQuery(this).find("li.family").each(function(index){
				var i = (index+1);
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
	jQuery("a", content).remove(); //left-overs

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
		url: "module.php?mod=" + ModuleName + "&mod_action=pdf_data",
		data: { "pdfContent": newContent },
		csrf: WT_CSRF_TOKEN,
		success: function() {
			window.location.href = "module.php?mod=" + ModuleName + "&mod_action=show_pdf&rootid=" + RootID + "&title=" + PageTitle + "#page=1";
		}
	});
}


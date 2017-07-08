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

// =============================================================================
// Fancy Treeview Page
// =============================================================================

// prevent duplicate id\'s - a family may appear twice when the parents are related
$("li.family[id]").each(function() {
	var family = $("[id=" + this.id + "]");
	if (family.length > 1) {
		i = 1;
		family.each(function() {
			var famId = $(this).attr("id");
      $(this).attr("id", famId + "-" + i);
      // renumber the anchors
			var anchor = $("#fancy-treeview-page a.scroll[href$=" + this.id + "]:first");
			anchor.attr("href", "#" + famId + "-" + i);
			i++;
		});
	}
});

// scroll to anchors
$("#fancy-treeview-page").on("click", ".scroll", function() {
  if ($(this).hasClass("link-next")) {
    return false;
  }
	var famId = $(this).attr("href");
	if ($(famId).is(":hidden") || $(famId).length === 0) {
		$(this).addClass("link-next").trigger("click");
		return false;
	}
	$(famId).jcScroll();
});

//button or link to retrieve next generations
$("#fancy-treeview-page").on("click", "#btn-next input, .link-next", function() {

	if ($(this).hasClass("link-next")) { // prepare for scrolling after new blocks are loaded
		var famId = $(this).attr("href");
		scroll = true;
	}

	// remove the last hidden block to retrieve the correct data from the previous last block
	$(".generation-block-hidden").remove();

  var rootId = $(".generation-block:first").data("pids");
	var lastBlock = $(".generation-block:last");
	var pids = lastBlock.data("pids");
	var gen = lastBlock.data("gen");

	lastBlock.find("a.link-next").removeClass("link-next");
	lastBlock.after("<div class=\"loading-image\">");
	$("#btn-next").hide();

  $.ajax({
		type: "GET",
		url: "module.php?mod=" + moduleName + "&mod_action=page&ged=" + WT_GEDCOM + "&rootid=" + rootId + "&gen=" + gen + "&pids=" + pids,
		success: function(data) {
			var blocks = $(".generation-block", data);
      $(lastBlock).after(blocks);

      if (blocks.length < parseInt(FTV_GENERATIONS) + 1) {
        $(".generation-block").removeClass("generation-block-hidden");
        $("#btn-next").remove();
      } else {
        $(".generation-block:not(:last)").removeClass("generation-block-hidden");
        $("#btn-next").show();
      }

      $(".loading-image").remove();

      // scroll
      if (scroll === true) {
        $(famId).jcScroll();
      }
		}
	});
});

/*** FTV PAGE FORM ***/
// Get the page for the root person of choice
$( "#change-root" ).submit(function(e) {
  e.preventDefault();
  var rootId = $("#new-pid").val();
  window.location = "module.php?mod=" + moduleName + "&mod_action=page&ged=" + WT_GEDCOM + "&rootid=" + rootId;
});

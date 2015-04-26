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

// set style dynamically on parents blocks with an image
jQuery(".parents").each(function () {
	if (jQuery(this).find(".gallery").length > 0) {
		var height = jQuery(this).find(".gallery img").height() + 10 + "px";
		jQuery(this).css({
			"min-height": height
		});
	}
});

// remove the empty hyphen on childrens lifespan if death date is unknown.
jQuery(".lifespan span:last-child").each(function () {
	if (jQuery(this).attr("title") === "") {
		jQuery(this).parent().html(jQuery(this).prev("span")).prepend(" (").append(")");
	}
});
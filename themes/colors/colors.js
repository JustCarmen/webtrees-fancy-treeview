/* Script file for the Fancy Tree View Pages
/* Extra js file for this theme to set some special styles
** Copyright (C) 2013 JustCarmen.
*/

jQuery.noConflict();

jQuery(document).ready(function($){
	function setStyle() {
		$('li.generation-block').each(function(){
			var blockheader = $(this).find(".blockheader").addClass("remove");
			$(this).prepend('<table class="blockheader" cellspacing="0" cellpadding="0"><tbody><tr><td class="blockh1"></td><td class="blockh2"><div class="blockhc">' + blockheader.text() + '</div></td><td class="blockh3"></td>')
			$('.blockheader.remove').remove();
		});
	}
	setStyle();
	$(document).ajaxComplete(function() {
		setStyle();
	})	
});
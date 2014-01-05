/* Script file for the Fancy Tree View Pages
/* Extra js file for this theme to set some special styles
** Copyright (C) 2014 JustCarmen.
*/
jQuery.noConflict();

jQuery(document).ready(function($){
	function setStyle() {
		$('li.generation-block div.blockheader').each(function(){
			$(this).replaceWith('<table class="blockheader" cellspacing="0" cellpadding="0"><tbody><tr><td class="blockh1"></td><td class="blockh2"><div class="blockhc">' + $(this).html() + '</div></td><td class="blockh3"></td>');
		});
	}
	setStyle();
	$(document).ajaxComplete(function() {
		setStyle();
	})
});
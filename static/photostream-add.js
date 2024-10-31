// this file only gets loaded on the in when the advanced form is displayed
jQuery(document).ready( function($) {
	// Delete the photostream
	tagBox.init();
	
	var advanced_settings_hidden = true;
	$("#advance-settings-toggle").click( function(e) {
		e.preventDefault();
		$(".advance-settings").slideToggle( 100 );
		
		advanced_settings_hidden = (advanced_settings_hidden ? false: true );
		var link_text = ( advanced_settings_hidden ? photostream_add.show : photostream_add.hide );
		$(this).text( link_text );
	});
});
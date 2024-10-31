/**
 * Nanobar: http://nanobar.micronube.com/ MIT licence
 */
var Nanobar=function(){var e,d,f,c,g=document.head||document.getElementsByTagName("head")[0];e=function(){var a=document.getElementById("nanobar-style");null===a&&(a=document.createElement("style"),a.type="text/css",a.id="nanobar-style",g.insertBefore(a,g.firstChild),a.styleSheet?a.styleSheet.cssText=".nanobar{float:left;width:100%;height:4px;z-index:9999;}.nanobarbar{width:0;height:100%;float:left;transition:all .3s;}":a.appendChild(document.createTextNode(".nanobar{float:left;width:100%;height:4px;z-index:9999;}.nanobarbar{width:0;height:100%;float:left;transition:all .3s;}")))};
d=function(){var a=document.createElement("fakeelement"),b={transition:"transitionend",OTransition:"oTransitionEnd",MozTransition:"transitionend",WebkitTransition:"webkitTransitionEnd"},c;for(c in b)if(void 0!==a.style[c])return b[c]}();f=function(a){var b=document.createElement("div");b.setAttribute("class","nanobarbar");b.style.background=a.opts.bg;b.setAttribute("on","1");a.cont.appendChild(b);d&&b.addEventListener(d,function(){"100%"===b.style.width&&"1"===b.getAttribute("on")&&(b.setAttribute("on",
0),a.bars.pop(),b.style.height=0,setTimeout(function(){a.cont.removeChild(b)},300))});return b};c=function(a){a=this.opts=a||{};var b;a.bg=a.bg||"#000";this.bars=[];e();b=this.cont=document.createElement("div");b.setAttribute("class","nanobar");a.id&&(b.id=a.id);a.target?b.style.position="relative":(b.style.position="fixed",b.style.top="0");a.target?a.target.insertBefore(b,a.target.firstChild):document.getElementsByTagName("body")[0].appendChild(b);return this.init()};c.prototype.init=function(){var a=
f(this);this.bars.unshift(a)};c.prototype.go=function(a){this.bars[0].style.width=a+"%";100==a&&this.init()};return c}();


var Photostream_Import = ( function( data ) {
	var nanobar = new Nanobar( { bg: '#7AD03A', target: document.getElementById('progress-bar') } );

	var totalPhotos = data.photos.length;

	var addImage = function ( html, id ) {
		var image = jQuery('<li class="attachment-wrap"><div class="attachment-item">'+html+"</div></li>" );
		jQuery( "#gallery-"+id ).append( image );

	}
	var currentTitle = jQuery('title').text();

	var progress_bar = jQuery("#progress-bar-status");

	var lockScreen = function () {
		return data.close;
	};

	var  updateStatus = function(){
		jQuery(".nanobar").hide();
		var update  = jQuery('<div class="updated below-h2"><p>'+data.finished+'</p></div>' ).fadeIn( 'slow' );
		progress_bar.html( update );
	}

	var importPhoto = function( photo ) {

		window.onbeforeunload = lockScreen;

		jQuery.ajax({
			type: 'POST',
			url:  ajaxurl,
			data: { action: "photostream_import_media", group_id: photo['group_id'], photo_id: photo['photoGuid'], stream: data.stream_key },
			success: function( response ) {
				var currentCount = data.photos.length;
				addImage( response.html, response.group_id );

				if( currentCount ) {
					var percent = ( ( totalPhotos - currentCount ) / totalPhotos * 100 );
					progress_bar.html( Math.round( percent ) +'% '+data.done );
					nanobar.go( percent );
					jQuery('title').text( Math.round( percent ) + '% ' + data.done + ' - ' + currentTitle );

					importPhoto(  data.photos.shift() );
				} else {
					nanobar.go( 100 );
					window.onbeforeunload = null;
					setTimeout( updateStatus , 300 );
					jQuery('title').text( '100% ' + data.done + ' - ' + currentTitle );
					
				}
			},
			error: function( response , text_status, error) {
				// console.log( response , text_status, error );
			}
		});
	} 

	var start = function() {
		console.log( 'run' );
		progress_bar.html( data.start_text );
		importPhoto( data.photos.shift() );

	};

	// expose the media 
	return { init: start };
})( photostreamImport );

Photostream_Import.init(); 
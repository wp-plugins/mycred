/**
 * myCRED Points for Link Clicks jQuery Scripts
 * @since 0.1
 * @version 1.3.1
 */
jQuery(function($) {
	var mycred_click = function( href, title, target, skey ) {
		$.ajax({
			type : "POST",
			data : {
				action : 'mycred-click-points',
				url    : href,
				token  : myCREDgive.token,
				etitle : title,
				key    : skey
			},
			dataType : "JSON",
			url : myCREDgive.ajaxurl,
			success    : function( data ) {
				//console.log( data );
				window.location.href = href;
			},
			error      : function( jqXHR, textStatus, errorThrown ) {
				// Debug
				//console.log( jqXHR );
				//console.log( 'textStatus: ' + textStatus + ' | errorThrown: ' + errorThrown );
			}
		});
	};
	
	$('.mycred-points-link').click(function(){
		var target = $(this).attr( 'target' );
		//console.log( target );
		if ( typeof target === 'undefined' ) {
			target = 'self';
		}
		
		mycred_click( $(this).attr( 'href' ), $(this).text(), target, $(this).attr( 'data-key' ) );
		
		if ( target == 'self' ) return false;
	});
});
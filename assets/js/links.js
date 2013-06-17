/**
 * myCRED Points for Link Clicks jQuery Scripts
 * @since 0.1
 * @version 1.0
 */
jQuery(function($) {
	var mycred_click = function( points, href, id ) {
		//alert( 'You gained '+points+' for clicking on this link. Reference: '+href );
		$.ajax({
			type : "POST",
			data : {
				action    : 'mycred-click-points',
				amount    : points,
				url       : href,
				eid       : id,
				token     : myCREDgive.token
			},
			dataType : "JSON",
			url : myCREDgive.ajaxurl,
			// Before we start
			beforeSend : function() {},
			// On Successful Communication
			success    : function( data ) {
				// Security token could not be verified.
				if ( data == 'alert' ) {
					alert( 'You gained '+points+' points for clicking on this link.' );
				}
			},
			// Error (sent to console)
			error      : function( jqXHR, textStatus, errorThrown ) {
				// Debug - uncomment to use
				//console.log( jqXHR );
			}
		});
	};
	
	$('.mycred-points-link').click(function(){
		if ( $(this).attr( 'id' ) && $(this).attr( 'id' ) != '' ) {
			mycred_click( $(this).attr( 'data-amount' ), $(this).attr( 'href' ), $(this).attr( 'id' ) );
		}
		else {
			mycred_click( $(this).attr( 'data-amount' ), $(this).attr( 'href' ), '' );
		}
	});
});
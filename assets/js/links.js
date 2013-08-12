/**
 * myCRED Points for Link Clicks jQuery Scripts
 * @since 0.1
 * @version 1.1
 */
jQuery(function($) {
	var mycred_click = function( points, href, id ) {
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
				setTimeout(function(){ window.location.href = href; }, 1000 );
			},
			// Error (sent to console)
			error      : function( jqXHR, textStatus, errorThrown ) {
				// Debug - uncomment to use
				//console.log( jqXHR );
				setTimeout(function(){ window.location.href = href; }, 1000 );
			}
		});
	};
	
	$('.mycred-points-link').click(function(){
		if ( $(this).attr( 'id' ) && $(this).attr( 'id' ) != '' ) {
			mycred_click( $(this).attr( 'data-amount' ), $(this).attr( 'href' ), $(this).attr( 'id' ) );
			return false;
		}
		else {
			mycred_click( $(this).attr( 'data-amount' ), $(this).attr( 'href' ), '' );
			return false;
		}
	});
});
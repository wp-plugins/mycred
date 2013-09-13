/**
 * myCRED Points for Link Clicks jQuery Scripts
 * @since 0.1
 * @version 1.2
 */
jQuery(function($) {
	var mycred_click = function( points, href, id, title ) {
		$.ajax({
			type : "POST",
			data : {
				action    : 'mycred-click-points',
				amount    : points,
				url       : href,
				eid       : id,
				token     : myCREDgive.token,
				etitle    : title
			},
			dataType : "JSON",
			url : myCREDgive.ajaxurl
		});
	};
	
	$('.mycred-points-link').click(function(){
		var id = $(this).attr( 'id' );
		if ( typeof id === 'undefined' && id === false ) {
			id = '';
		}
		
		mycred_click( $(this).attr( 'data-amount' ), $(this).attr( 'href' ), id, $(this).text() );
	});
});
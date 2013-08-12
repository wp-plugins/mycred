/**
 * myCRED Inline Edit
 * @since 1.2
 * @version 1.0
 */
jQuery(function($) {
	var username = '';
	var user_id = '';
	var current = '';
	var current_el = '';

	$(document).ready( function() {
		$('#edit-mycred-balance').dialog({
			dialogClass: 'mycred-update-balance',
			draggable: true,
			autoOpen: false,
			title: myCREDedit.title,
			closeText: myCREDedit.close,
			modal: true,
			width: 500,
			height: 'auto',
			resizable: false,
			show: {
				effect: 'slide',
				direction: 'up',
				duration: 250
			},
			hide: {
				effect: 'slide',
				direction: 'up',
				duration: 250
			}
		});
	});
	
	$( 'a.mycred-open-points-editor' ).click( function() {
		username = $(this).parent().parent().parent().siblings( 'td.column-username' ).find( 'strong>a' ).text();
		user_id = $(this).attr( 'data-userid' );
		current = $(this).attr( 'data-current' );
		
		$( '#edit-mycred-balance' ).dialog( 'open' );
		
		var username_el = $( '#edit-mycred-balance #mycred-username' );
		username_el.empty();
		username_el.text( username );
		
		var userid_el = $( '#edit-mycred-balance #mycred-userid' );
		userid_el.empty();
		userid_el.text( user_id );
		
		current_el = $( '#edit-mycred-balance #mycred-current' );
		current_el.empty();
		current_el.text( current );
	});
	
	$( '#mycred-update-users-balance-submit' ).click( function() {
		var button = $(this);
		var label = button.val();
		var amount_el = $( 'input#mycred-update-users-balance-amount' );
		var entry_el = $( 'input#mycred-update-users-balance-entry' );
		
		$.ajax({
			type : "POST",
			data : {
				action: 'mycred-inline-edit-users-balance',
				token: $( 'input#mycred-update-users-balance-token' ).val(),
				user: user_id,
				amount: amount_el.val(),
				entry: entry_el.val()
			},
			dataType : "JSON",
			url : myCREDedit.ajaxurl,
			// Before we start
			beforeSend : function() {
				current_el.removeClass( 'done' );
				entry_el.removeClass( 'error' );
				amount_el.removeClass( 'error' );
				
				button.attr( 'value', myCREDedit.working );
				button.attr( 'disabled', 'disabled' );
			},
			// On Successful Communication
			success    : function( data ) {
				// Debug
				console.log( data );
				
				if ( data.status == 'OK' ) {
					current_el.addClass( 'done' );
					current_el.text( data.current );
					amount_el.val( '' );
					entry_el.val( '' );
					$( 'div#mycred-user-'+user_id+'-balance span' ).text( data.current );
				}
				else if ( data.status == 'ERROR_1' ) {
					$( '#edit-mycred-balance' ).dialog( 'destroy' );
				}
				else if ( data.status == 'ERROR_2' ) {
					alert( data.current );
					amount_el.val( '' );
					entry_el.val( '' );
				}
				else if ( data.status == 'ERROR_3' ) {
					entry_el.addClass( 'error' );
					entry_el.attr( 'title', data.current );
				}
				else if ( data.status == 'ERROR_4' ) {
					amount_el.addClass( 'error' );
					amount_el.attr( 'title', data.current );
				}
				
				button.attr( 'value', label );
				button.removeAttr( 'disabled' );
			},
			// Error (sent to console)
			error      : function( jqXHR, textStatus, errorThrown ) {
				// Debug
				console.log( jqXHR + ':' + textStatus + ':' + errorThrown );
				
				button.attr( 'value', label );
				button.removeAttr( 'disabled' );
			}
		});
	});
});
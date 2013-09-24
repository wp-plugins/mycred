/**
 * myCRED Management Scripts
 * @since 1.3
 * @version 1.0
 */
jQuery(function($) {
	
	var mycred_action_empty_log = function( button ) {
		var label = button.val();
		$.ajax({
			type : "POST",
			data : {
				action : 'mycred-action-empty-log',
				token  : myCREDmanage.token
			},
			dataType : "JSON",
			url : myCREDmanage.ajaxurl,
			beforeSend : function() {
				button.attr( 'value', myCREDmanage.working );
				button.attr( 'disabled', 'disabled' );
			},
			success : function( data ) {
				console.log( data );
				
				if ( data.status == 'OK' ) {
					$( 'input#mycred-manage-table-rows' ).val( data.rows );
					button.val( myCREDmanage.done );
					button.removeClass( 'button-primary' );
				}
				else {
					button.val( label );
					button.removeAttr( 'disabled' );
					alert( data.rows );
				}
			},
			error   : function( jqXHR, textStatus, errorThrown ) {
				// Debug
				console.log( textStatus + ':' + errorThrown );
				button.attr( 'value', label );
				button.removeAttr( 'disabled' );
			}
		});
	};
	
	$( 'input#mycred-manage-action-empty-log' ).click(function(){
		// Confirm action
		if ( confirm( myCREDmanage.confirm_log ) ) {
			mycred_action_empty_log( $(this) );
		}
	});
	
	var mycred_action_reset_balance = function( button ) {
		var label = button.val();
		$.ajax({
			type : "POST",
			data : {
				action : 'mycred-action-reset-accounts',
				token  : myCREDmanage.token
			},
			dataType : "JSON",
			url : myCREDmanage.ajaxurl,
			beforeSend : function() {
				button.attr( 'value', myCREDmanage.working );
				button.attr( 'disabled', 'disabled' );
			},
			success : function( data ) {
				console.log( data );
				
				if ( data.status == 'OK' ) {
					button.val( myCREDmanage.done );
					button.removeClass( 'button-primary' );
				}
				else {
					button.val( label );
					button.removeAttr( 'disabled' );
					alert( data.rows );
				}
			},
			error   : function( jqXHR, textStatus, errorThrown ) {
				// Debug
				console.log( textStatus + ':' + errorThrown );
				button.attr( 'value', label );
				button.removeAttr( 'disabled' );
			}
		});
	};
	
	$( 'input#mycred-manage-action-reset-accounts' ).click(function(){
		// Confirm action
		if ( confirm( myCREDmanage.confirm_reset ) ) {
			mycred_action_reset_balance( $(this) );
		}
	});
});
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
	
	$('#export-points').dialog({ dialogClass: 'mycred-export-points', draggable: false, autoOpen: false, closeText: myCREDmanage.export_close, title: myCREDmanage.export_title, modal: true, width: 500, resizable: false,
			show: { effect: 'slide', direction: 'up', duration: 250 },
			hide: { effect: 'slide', direction: 'up', duration: 250 }
		});
	
	$( '#mycred-export-users-points' ).click( function() {
		$( '#export-points' ).dialog( 'open' );
	});
	
	var mycred_action_export_balances = function( button ) {
		var label = button.val();
		$.ajax({
			type : "POST",
			data : {
				action : 'mycred-action-export-balances',
				token  : myCREDmanage.token,
				identify : $( '#mycred-export-identify-by' ).val(),
				log_temp : $( '#mycred-export-log-template' ).val()
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
					button.val( myCREDmanage.exporting );
					setTimeout(function(){
						window.location.href = data.string;
						button.val( myCREDmanage.done );
					}, 2000 );
					setTimeout(function(){
						button.removeAttr( 'disabled' );
						button.val( label );
					}, 4000 );
				}
				else {
					button.val( label );
					button.before( data.string );
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
	
	$( '#mycred-run-exporter' ).click(function(){
		mycred_action_export_balances( $(this) );
	});
	
	var mycred_generate_key = function() {
		$.ajax({
			type : "POST",
			data : {
				action : 'mycred-action-generate-key',
				token  : myCREDmanage.token
			},
			dataType : "JSON",
			url : myCREDmanage.ajaxurl,
			success : function( data ) {
				$( '#myCRED-remote-key' ).val( data );
				$( '#mycred-length-counter' ).text( data.length );
			},
			error   : function( jqXHR, textStatus, errorThrown ) {
				// Debug
				console.log( textStatus + ':' + errorThrown );
			}
		});
	}
	
	$( '#mycred-generate-api-key' ).click(function(){
		mycred_generate_key();
	});
	
	$( '#myCRED-remote-key' ).change(function(){
		$( '#mycred-length-counter' ).text( $(this).val().length );
	});
	
	$( '#myCRED-remote-key' ).keyup(function(){
		$( '#mycred-length-counter' ).text( $(this).val().length );
	});
});
/**
 * myCRED Transfer jQuery
 * Handles transfer requests and autocomplete of user login names.
 *
 * @requires jQuery
 * @requires jQuery UI
 * @requires jQuery Autocomplete
 * @since 0.1
 * @version 1.0.21
 */
jQuery(function($){
	// Transfer function
	var transfer_creds = function( to, creds, label ) {
		$.ajax({
			type : "POST",
			data : {
				action    : 'mycred-transfer-creds',
				sender    : myCRED.user_id,
				recipient : to,
				amount    : creds,
				token     : myCRED.token
			},
			dataType : "JSON",
			url : myCRED.ajaxurl,
			// Before we start
			beforeSend : function() {
				// Prevent users from clicking multiple times
				$('.mycred-click').attr( 'value', myCRED.working );
				$('.mycred-click').attr( 'disabled', 'disabled' );
			},
			// On Successful Communication
			success    : function( data ) {
				// Debug - uncomment to use
				//console.log( data );
				// Remove disable
				$('.mycred-click').attr( 'value', label );
				$('.mycred-click').removeAttr( 'disabled' );
				// Security token could not be verified.
				if ( data == 'error_1' ) {
					alert( myCRED.error_1 );
				}
				// Communications error.
				else if ( data == 'error_2' ) {
					alert( myCRED.error_2 );
				}
				// Recipient not found.
				else if ( data == 'error_3' ) {
					alert( myCRED.error_3 );
				}
				// Trying to send to excluded user.
				else if ( data == 'error_4' ) {
					alert( myCRED.error_4 );
				}
				// Incorrect amount.
				else if ( data == 'error_5' ) {
					alert( myCRED.error_5 );
				}
				// This myCRED Add-on has not yet been setup!
				else if ( data == 'error_6' ) {
					alert( myCRED.error_6 );
				}
				// Insufficient funds.
				else if ( data == 'error_7' ) {
					alert( myCRED.error_7 );
				}
				// Transfer Limit exceeded.
				else if ( data == 'error_8' ) {
					alert( myCRED.error_8 );
				}
				// Requested Amount will exceed limit.
				else if ( data == 'error_9' ) {
					alert( myCRED.error_9 );
				}
				// Transfer Completed.
				else if ( data == 'ok' ) {
					alert( myCRED.completed );
					location.reload();
				}
			},
			// Error (sent to console)
			error      : function( jqXHR, textStatus, errorThrown ) {
				alert( myCRED.error_2 );
				location.reload();
				// Debug - uncomment to use
				//console.log( jqXHR );
			}
		});
	};
	
	// Autocomplete
	// @api http://api.jqueryui.com/autocomplete/
	var cache = {};
	$('input.mycred-autofill').autocomplete({
		minLength: 2,
		source: function( request, response ) {
			var term = request.term;
			if ( term in cache ) {
				response( cache[ term ] );
				return;
			}
			
			var send = {
				action : "mycred-autocomplete",
				me     : myCRED.user_id,
				token  : myCRED.atoken,
				string : request
			};
			$.getJSON( myCRED.ajaxurl, send, function( data, status, xhr ) {
				cache[ term ] = data;
				// Debug - uncomment to use
				//console.log( data );
				//console.log( status );
				response( data );
			});
		},
		messages: {
			noResults: '',
			results: function() {}
		},
		appendTo : 'div.transfer-to'
	});
	
	// Attempt Transfer
	$('.mycred-click').click(function(){
		// To:
		var receipient = $(this).parent().prev().children( 'div' ).children( 'input' );
		var to = $(receipient).val();
		// Debug - uncomment to use
		//console.log( to );

		// Amount:
		var amount = $(this).prev().children( 'input' );
		var creds = $(amount).val();
		// Debug - uncomment to use
		//console.log( creds );

		// If elements are not emepty attempt transfer
		if ( to != '' && creds != '' ) {
			transfer_creds( to, creds, $(this).attr( 'value' ) );
		}
	});
});
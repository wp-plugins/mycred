/**
 * myCRED Transfer jQuery
 * Handles transfer requests and autocomplete of user login names.
 *
 * @requires jQuery
 * @requires jQuery UI
 * @requires jQuery Autocomplete
 * @since 0.1
 * @version 1.3
 */
jQuery(function($){
	// Transfer function
	var transfer_creds = function( submitted_form, label ) {
		$.ajax({
			type : "POST",
			data : {
				action    : 'mycred-transfer-creds',
				form      : submitted_form,
				token     : myCRED.token
			},
			dataType : "JSON",
			url : myCRED.ajaxurl,
			// Before we start
			beforeSend : function() {
				// Prevent users from clicking multiple times
				$( '.mycred-click' ).val( myCRED.working );
				$( '.mycred-click' ).attr( 'disabled', 'disabled' );
			},
			// On Successful Communication
			success    : function( data ) {
				$( '.mycred-click' ).val( label );
				$( '.mycred-click' ).removeAttr( 'disabled' );
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
				// Transfer Completed.
				else if ( data == 'ok' ) {
					alert( myCRED.completed );

					if ( myCRED.reload == '1' )
						location.reload();
				}
				else {
					$('.mycred-click').attr( 'value', data );
					if ( myCRED.reload == '1' )
						location.reload();
				}
			}
		});
	};
	
	// Autocomplete
	// @api http://api.jqueryui.com/autocomplete/
	var cache = {};
	$( 'input.mycred-autofill' ).autocomplete({
		minLength: 2,
		source: function( request, response ) {
			var term = request.term;
			if ( term in cache ) {
				response( cache[ term ] );
				return;
			}
			
			var send = {
				action : "mycred-autocomplete",
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
	$( '.mycred-click' ).click(function(){

		// The form
		var the_form = $(this).parent().parent().parent();

		// To:
		var receipient = $(this).parent().prev().children( 'div' ).children( 'input' ).val();

		// Amount:
		var creds = $(this).prev().children( 'input[name=mycred-transfer-amount]' ).val();

		// If elements are not emepty attempt transfer
		if ( receipient != '' && creds != '' ) {
			transfer_creds( the_form.serialize(), $(this).val() );
		}

	});
});
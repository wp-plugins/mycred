/**
 * myCRED Edit Log Scripts
 * These scripts are used to edit or delete entries
 * in the myCRED Log.
 * @since 1.4
 * @version 1.0
 */
jQuery(function($) {

	/**
	 * Click To Toggle Script
	 */
	$( '.click-to-toggle' ).click(function(){
		var target = $(this).attr( 'data-toggle' );
		$( '#' + target ).toggle();
	});

	/**
	 * Delete Log Entry AJAX caller
	 */
	var mycred_delete_log_entry = function( rowid, button ) {
		$.ajax({
			type       : "POST",
			data       : {
				action    : 'mycred-delete-log-entry',
				token     : myCREDLog.tokens.delete_row,
				row       : rowid
			},
			dataType   : "JSON",
			url        : myCREDLog.ajaxurl,
			success    : function( response ) {
				// Debug
				//console.log( response );

				var parentrow = button.parent().parent().parent();
				var actioncol = button.parent().parent();

				if ( response.success ) {
					actioncol.empty();
					actioncol.text( response.data );

					parentrow.addClass( 'deleted-row' );
					parentrow.fadeOut( 3000, function(){ parentrow.remove(); });
				}
				else {
					actioncol.empty();
					actioncol.text( response.data );
				}
			},
			error      : function( jqXHR, textStatus, errorThrown ) {
				// Debug
				//console.log( jqXHR );
				//console.log( 'textStatus: ' + textStatus + ' | errorThrown: ' + errorThrown );
			}
		});
	}

	/**
	 * Log Entry Deletion Trigger
	 */
	$( '.mycred-delete-row' ).click(function(){
		// Require user to confirm deletion
		if ( ! confirm( myCREDLog.messages.delete_row ) )
			return false;

		// Execute AJAX call
		mycred_delete_log_entry( $(this).attr( 'data-id' ), $(this) );
	});

	var log_row_id = '';
	var log_user = '';
	var log_time = '';
	var log_cred = '';

	var log_entry_raw = '';
	var log_entry = '';

	/**
	 * Setup Log Editor Modal
	 */
	$('#edit-mycred-log-entry').dialog({
		dialogClass : 'mycred-edit-log-entry',
		draggable   : true,
		autoOpen    : false,
		title       : myCREDLog.title,
		closeText   : myCREDLog.close,
		modal       : true,
		width       : 500,
		height      : 'auto',
		resizable   : false,
		show        : {
			effect     : 'slide',
			direction  : 'up',
			duration   : 250
		},
		hide        : {
			effect     : 'slide',
			direction  : 'up',
			duration   : 250
		}
	});

	/**
	 * Edit Modal Trigger
	 */
	$( '.mycred-open-log-entry-editor' ).click( function() {

		// Get the details we want to show
		log_row_id = $(this).attr( 'data-id' );
		log_user = $(this).parent().siblings( 'td.column-username' ).children( 'span' ).text();
		log_time = $(this).parent().siblings( 'td.column-time' ).text();
		log_cred = $(this).parent().siblings( 'td.column-creds' ).text();

		log_entry_raw = $(this).parent().siblings( 'td.column-entry' ).children( 'div.raw' ).text();
		log_entry = $(this).parent().siblings( 'td.column-entry' ).children( 'div.entry' ).text();

		// Show the modal window
		$( '#edit-mycred-log-entry' ).dialog( 'open' );

		// Populate the form
		var username_el = $( '#edit-mycred-log-entry #mycred-username' );
		username_el.empty();
		username_el.text( log_user );

		var time_el = $( '#edit-mycred-log-entry #mycred-time' );
		time_el.empty();
		time_el.text( log_time );

		var creds_el = $( '#edit-mycred-log-entry #mycred-creds' );
		creds_el.empty();
		creds_el.text( log_cred );

		var entry_el = $( '#edit-mycred-log-entry #mycred-raw-entry' );
		entry_el.val( '' );
		entry_el.val( log_entry );

		var raw_entry_el = $( '#edit-mycred-log-entry #mycred-new-entry' );
		raw_entry_el.val( '' );
		raw_entry_el.val( log_entry_raw );
		
		$( 'input#mycred-log-row-id' ).val( log_row_id );

	});

	/**
	 * Edit AJAX Call
	 */
	var mycred_update_log_entry = function( rowid, entry, button ) {
		var button_label = button.val();

		$.ajax({
			type       : "POST",
			data       : {
				action    : 'mycred-update-log-entry',
				token     : myCREDLog.tokens.update_row,
				row       : rowid,
				new_entry : entry
			},
			dataType   : "JSON",
			url        : myCREDLog.ajaxurl,
			beforeSend : function() {
			
				button.removeClass( 'button-primary' );
				button.addClass( 'button-secondary' );
				button.val( myCREDLog.working );
			},
			success    : function( response ) {
				// Debug
				console.log( response );

				var effected_row = $( '#mycred-log-entry-' + response.data.row_id );
				button.removeClass( 'button-secondary' );

				if ( response.success ) {
					effected_row.addClass( 'updated-row' );

					effected_row.children( 'td.column-entry' ).children( 'div.raw' ).empty();
					effected_row.children( 'td.column-entry' ).children( 'div.raw' ).text( response.data.new_entry_raw );

					$( '#edit-mycred-log-entry #mycred-raw-entry' ).val( response.data.new_entry );

					effected_row.children( 'td.column-entry' ).children( 'div.entry' ).empty();
					effected_row.children( 'td.column-entry' ).children( 'div.entry' ).text( response.data.new_entry );

					$( '#edit-mycred-log-entry #mycred-new-entry' ).val( response.data.new_entry_raw );

					button.val( response.data.label );
					setTimeout(function(){ button.val( button_label ); button.addClass( 'button-primary' ); }, 5000 );
				}
				else {
					button.val( response.data );
					setTimeout(function(){ button.val( button_label ); button.addClass( 'button-primary' ); }, 5000 );
				}
			},
			error      : function( jqXHR, textStatus, errorThrown ) {
				// Debug
				//console.log( jqXHR );
				//console.log( 'textStatus: ' + textStatus + ' | errorThrown: ' + errorThrown );
			}
		});
	}

	/**
	 * Edit AJAX Call Trigger
	 */
	$( '#mycred-update-log-entry' ).click( function() {
		mycred_update_log_entry( $(this).next().val(), $( 'input#mycred-new-entry' ).val(), $(this) );
	});

});
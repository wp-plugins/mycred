/**
 * myCRED Points for Viewing Videos
 * @since 1.2
 * @version 1.0
 */
var timer = 0;

var duration = {};
var interval = {};
var actions = {};
var seconds = {};
var amount = {};
var logic = {};
var done = {};

/**
 * onYouTubePlayerReady
 * For the YouTube API hooking into onStateChange and to get the durration of a video.
 * @since 1.2
 * @version 1.0
 */
function onYouTubePlayerReady( id ) {
	// Define Player
	var player = document.getElementById( id );

	// Duration
	duration[ id ] = player.getDuration();

	// Listen in on state changes
	player.addEventListener( 'onStateChange', 'mycred_video_'+id );
}

/**
 * Ajax Call
 * Calls home to report video views and award points
 * @since 1.2
 * @version 1.0
 */
function mycred_video_points( id, state, length, watched, uactions, eamount, elogic, einterval ) {
	if ( myCREDvideo.user_id == 0 ) return false;
	// Make sure we are not done
	if ( done[ id ] === undefined ) {
		// Debug
		//console.log( 'Received ID: '+id );

		// Ajax
		jQuery.ajax({
			type : "POST",
			data : {
				action       : 'mycred-video-points',
				token        : myCREDvideo.token,
				amount       : eamount,
				logic        : elogic,
				interval     : einterval,

				video_id     : id,
				video_state  : state,
				video_length : length,

				user_id      : myCREDvideo.user_id,
				user_watched : watched,
				user_actions : uactions
			},
			dataType : "JSON",
			url : myCREDvideo.ajaxurl,
			// Before we start
			beforeSend : function() {},
			// On Successful Communication
			success    : function( data ) {
				console.log( data );
				// If maxed out, add this id to done[] so prevent further calls
				// at least until the page is re-loaded.
				if ( data.status === 'max' ) {
					done[ data.video_id ] = data.amount;
				}
			},
			// Error (sent to console)
			error      : function( jqXHR, textStatus, errorThrown ) {
				console.log( jqXHR+':'+textStatus+':'+errorThrown );
			}
		});
	}
}

/**
 * Handle YouTube States
 * @see https://developers.google.com/youtube/js_api_reference
 * @since 1.2
 * @version 1.0
 */
function mycred_youtube_state( id, state, custom_amount, custom_logic, custom_interval )
{
	var videoid = id;
	var videostate = state;

	if ( actions[ id ] === undefined ) {
		actions[ id ] = '';
	}
	if ( seconds[ id ] === undefined ) {
		seconds[ id ] = 0;
	}

	// Amount override
	if ( custom_amount == 'def' ) {
		amount[ id ] = myCREDvideo.amount;
	}
	else {
		amount[ id ] = parseInt( custom_amount, 10 );
	}

	// Logic override
	if ( custom_logic == 'def' ) {
		logic[ id ] = myCREDvideo.logic;
	}
	else {
		logic[ id ] = custom_logic.toString();
	}

	// Interval override
	if ( custom_interval == 'def' ) {
		interval[ id ] = parseInt( myCREDvideo.interval, 10 );
	}
	else {
		interval[ id ] = parseInt( custom_interval, 10 );
	}

	// Ready
	if ( state != '-1' ) {

		// Points when video starts
		if ( logic[ id ] == 'play' ) {
			// As soon as we start playing we award points
			if ( state == 1 ) {
				mycred_video_points( videoid, videostate, duration[ videoid ], '', '', amount[ id ], logic[ id ], '' );
			}
		}

		// Points first when video has ended
		else if ( logic[ id ] == 'full' ) {

			actions[ id ] = actions[ id ]+state.toString();

			// Play
			if ( state == 1 ) {
				// Start timer
				timer = setInterval( function() {
					seconds[ id ] = seconds[ id ] + 1;
				}, 1000 );
			}

			// Finished
			else if ( state == 0 ) {
				// Stop timer
				clearInterval( timer );

				// Notify myCRED
				mycred_video_points( videoid, videostate, duration[ videoid ], seconds[ videoid ], actions[ videoid ], amount[ id ], logic[ id ], '' );

				// Reset
				seconds[ id ] = 0;
				actions[ id ] = '';
			}

			// All else
			else {
				// Stop Timer
				clearInterval( timer );
			}
		}

		// Points per x number of seconds played
		else if ( logic[ id ] == 'interval' ) {
			// Update actions
			actions[ id ] = actions[ id ]+state.toString();

			// Video is playing
			if ( state == 1 ) {
				// Start timer
				timer = window.setInterval( function() {
					var laps = parseInt( interval[ id ] / 1000, 10 );
					seconds[ id ] = seconds[ id ] + laps;
					mycred_video_points( videoid, videostate, duration[ videoid ], seconds[ videoid ], actions[ videoid ], amount[ id ], logic[ id ], interval[ id ] );
				}, interval[ id ] );
			}

			// Video has ended
			else if ( state == 0 ) {
				clearInterval( timer );
				mycred_video_points( videoid, videostate, duration[ videoid ], seconds[ videoid ], actions[ videoid ], amount[ id ], logic[ id ], interval[ id ] );

				seconds[ id ] = 0;
				actions[ id ] = '';
			}

			// All else
			else {
				// Stop Timer
				clearInterval( timer );
			}	
		}
	}
	
	// Debug
	//console.log( 'Video ID: ' + id + ' Actions: ' + actions[ videoid ] + ' Seconds: ' + seconds[ videoid ] + ' Logic: ' + logic[ id ] );
}
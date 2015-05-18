if ( "undefined" !== typeof jQuery ) jQuery( document ).ready( function($) {
	var runButton   = $( "input[name='do_tribe_cleanup']" );
	var confirmBox  = $( "input#confirm-risk" );
	var keepWorking = $( "input#tribe_events_continue_cleanup" );

	// Hide the run button by default
	runButton.fadeOut( 1 );

	// Ensure the acknowledgement checkbox is checked before showing the button
	confirmBox.change( function() {
		if ( "checked" === confirmBox.attr( "checked" ) ) runButton.fadeIn( "fast" );
		else runButton.fadeOut( "fast" );
	} );

	// Support long cleanup operations
	if ( 1 === keepWorking.length ) {
		window.location = keepWorking.val();
	}
} );
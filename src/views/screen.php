<?php
/**
 * @var $plugin TribeEventsCleanup
 */

$no_acknowledgement = isset( $plugin->warnings['no_risk_acknowledgement'] );
?>

<div class="wrap tribe-events-cleanup">
	<h2> <?php _e( 'The Events Calendar: Cleanup Tool', 'tribe-events-cleanup' ) ?> </h2>

	<?php
	include 'notices.php';
	include 'counts.php';
	?>

	<?php if ( isset( $plugin->warnings['tec_active'] ) ) : ?>

		<p> <em> <?php _e( 'You cannot run this tool until The Events Calendar has been deactivated!', 'tribe-events-cleanup' ) ?> </em> </p>

	<?php elseif ( $plugin->has_found_event_data() && ! $plugin->cleanup_in_progress() ): ?>

		<form method="post">

			<p class="risk-warning"> <label for="confirm-risk" <?php if ( $no_acknowledgement ) echo 'class="emphasize"' ?>>
				<input id="confirm-risk" name="confirm_risk" value="1" type="checkbox" />
				<?php _e( '<strong> Removal of data is not without risk. </strong> I confirm that I have made a complete backup of my site, that I understand how to restore that backup and have taken all other appropriate precautions.', 'tribe-events-cleanup ' ) ?>
			</label> </p>

			<?php wp_nonce_field( 'tribe_cleanup', 'confirm_cleanup' ) ?>
			<input type="submit" name="do_tribe_cleanup" value="<?php esc_attr_e( 'Run cleanup tool', 'tribe-events-cleanup' ) ?>" class="button-secondary" />

		</form>

	<?php elseif ( $plugin->cleanup_in_progress() ): ?>

		<p> <?php _e( 'The cleanup is still in progress &ndash; please be patient! Remember you can leave this tab open in the background while you work.', 'tribe-events-cleanup' ) ?> </p>
		<input type="hidden" id="tribe_events_continue_cleanup" value="<?php esc_attr_e( $plugin->reload_link() ) ?>" />

	<?php endif ?>
</div>
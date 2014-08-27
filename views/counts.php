<?php if ( $plugin->has_found_event_data() ): ?>

	<p> <?php _e( 'We&#8217;ve found some events data within your database:', 'tribe-events-cleanup' ) ?> </p>

	<table>
		<tr>
			<td> <?php _e( 'Events', 'tribe-events-cleanup' ) ?> </td>
			<td class="count"> <strong> <?php esc_html_e( $plugin->counts->events ) ?> </strong> </td>
		</tr>

		<tr>
			<td> <?php _e( 'Venues', 'tribe-events-cleanup' ) ?> </td>
			<td class="count"> <strong> <?php esc_html_e( $plugin->counts->venues ) ?> </strong> </td>
		</tr>

		<tr>
			<td> <?php _e( 'Organizers', 'tribe-events-cleanup' ) ?> </td>
			<td class="count"> <strong> <?php esc_html_e( $plugin->counts->organizers ) ?> </strong> </td>
		</tr>

		<tr>
			<td> <?php _e( 'Options/settings', 'tribe-events-cleanup' ) ?> </td>
			<td class="count"> <strong> <?php esc_html_e( $plugin->counts->options ) ?> </strong> </td>
		</tr>

		<tr>
			<td> <?php _e( 'User capabilities', 'tribe-events-cleanup' ) ?> </td>
			<td class="count"> <strong> <?php esc_html_e( $plugin->counts->capabilities ) ?> </strong> </td>
		</tr>
	</table>

<?php endif ?>
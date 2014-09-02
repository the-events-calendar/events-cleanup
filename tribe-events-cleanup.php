<?php
/*
Plugin Name: The Events Calendar: Cleanup Tool
Description: Easy to use tool helping you to remove all database content relating to The Events Calendar plugin.
Version: 1.0
Author: Modern Tribe, Inc.
Author URI: http://m.tri.be/1x
Text Domain: tribe-events-cleanup
License: GPLv2 or later

Copyright 2009-2012 by Modern Tribe Inc and the contributors

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

class TribeEventsCleanup {
	const EVENTS = 'tribe_events';
	const VENUES = 'tribe_venue';
	const ORGANIZERS = 'tribe_organizer';

	protected $plugin_dir = '';
	protected $plugin_url = '';
	protected $warnings = array();
	protected $notices = array();
	protected $counts;

	protected $capability = 'delete_plugins';
	protected $timeout = 4;
	protected $clock = 0;
	protected $batch = 20;
	protected $in_progress = false;


	public function __construct() {
		$this->config();
		$this->setup();
	}

	protected function config() {
		$this->plugin_dir = dirname( __FILE__ );
		$this->plugin_url = plugin_dir_url( __FILE__ );

		$this->capability = apply_filters( 'tribe_events_cleanup_min_cap', $this->capability );
		$this->timeout = (int) apply_filters( 'tribe_events_cleanup_timeout', $this->timeout );
		$this->batch = (int) apply_filters( 'tribe_events_cleanup_batch', $this->batch );
	}

	protected function setup() {
		add_action( 'admin_init', array( $this, 'get_counts' ) );
		add_action( 'admin_init', array( $this, 'do_cleanup' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_screen' ) );
		add_action( 'admin_print_styles-tools_page_tribe-events-cleanup', array( $this, 'enqueue_resources' ) );
	}

	public function get_counts() {
		$this->counts = (object) array(
			'events' => $this->count_up( self::EVENTS ),
			'venues' => $this->count_up( self::VENUES ),
			'organizers' => $this->count_up( self::ORGANIZERS ),
			'options' => $this->settings_count(),
			'capabilities' => $this->caps_count()
		);
	}

	public function has_found_event_data() {
		$this->get_counts();

		foreach ( $this->counts as $count ) {
			if ( (int) $count > 0 ) return true;
		}
		return false;
	}

	public function cleanup_in_progress() {
		return $this->in_progress;
	}

	protected function count_up( $post_type ) {
		global $wpdb;
		$query = "SELECT COUNT(*) FROM $wpdb->posts WHERE `post_type` = '%s' LIMIT 1;";
		return absint( $wpdb->get_var( $wpdb->prepare( $query, $post_type	) ) );
	}

	/**
	 * We look for and count the number of option table entries where the option name
	 * itself contains both 'tribe' and 'events'.
	 *
	 * This avoids the need to maintain and keep in sync a whitelist of actual options.
	 *
	 * @return int
	 */
	protected function settings_count() {
		global $wpdb;
		$query = "SELECT COUNT(*) FROM $wpdb->options WHERE `option_name` LIKE '%tribe%events%';";
		return absint( $wpdb->get_var( $query ) );
	}

	/**
	 * Counts the number of currently registered user role capabilities that "look tribal" and
	 * are likely to have been generated in relation to The Events Calendar/Events Calendar PRO,
	 * etc.
	 *
	 * @return int
	 */
	protected function caps_count() {
		global $wp_roles;
		$count = 0;

		foreach ( $wp_roles->roles as $role_slug => $user_role ) {
			foreach ( $user_role['capabilities'] as $capability => $on ) {
				if ( $this->looks_tribal( $capability ) ) $count++;
			}
		}

		return $count;
	}

	/**
	 * If the provided string contains 'tribe' and one of 'event', 'venue' or
	 * 'organizer' it will return true.
	 *
	 * @param $string
	 * @return bool
	 */
	protected function looks_tribal( $string ) {
		$string = strtolower( $string );
		if ( false === strpos( $string, 'tribe' ) ) return false;
		if ( false !== strpos( $string, 'event' ) ) return true;
		if ( false !== strpos( $string, 'venue' ) ) return true;
		if ( false !== strpos( $string, 'organizer' ) ) return true;
		return false;
	}

	/**
	 * Position our menu entry within the existing Tools section.
	 */
	public function add_admin_screen() {
		$page_title = __( 'The Events Calendar: Cleanup Tool', 'tribe-events-cleanup' );
		$menu_title = __( 'Events Cleanup', 'tribe-events-cleanup' );
		$callback   = array( $this, 'admin_screen' );

		add_management_page( $page_title, $menu_title, $this->capability, 'tribe-events-cleanup', $callback );
	}

	/**
	 * Test to see if A) The Events Calendar is still active and B) if there is in fact
	 * any data to be cleaned up.
	 */
	public function basic_checks() {
		if ( class_exists( 'TribeEvents' ) ) {
			$warning = __( '<strong> The Events Calendar %s still appears to be active. </strong> Please deactivate The Events Calendar plus any related add-ons prior to using this tool!', 'tribe-events-cleanup' );
			$this->warnings['tec_active'] = sprintf( $warning, TribeEvents::VERSION );
		}
		if ( ! $this->in_progress && ! $this->has_found_event_data() ) {
			$this->notices['already_clean'] = __( 'No event data found: your site is as clean as a whistle!', 'tribe-events-cleanup' );
		}
	}

	public function admin_screen() {
		$this->basic_checks();
		$plugin = $this;
		include $this->plugin_dir . '/views/screen.php';
	}

	public function enqueue_resources() {
		wp_enqueue_script( 'tribe-events-cleanup', $this->plugin_url . '/resources/tribe-events-cleanup.js', 'jquery', false, true );
		wp_enqueue_style( 'tribe-events-cleanup', $this->plugin_url . '/resources/tribe-events-cleanup.css' );
	}

	public function do_cleanup() {
		if ( ! $this->pre_cleanup_checks() ) return;

		// Keep cleaning so long as we haven't exceeded the timeout (or number of items to be processed in a single batch)
		while ( $this->time_left() && $this->job_incomplete() ) {
			$this->keep_cleaning();
		}

		// If the cleanup needs to spand multiple requests then inform the customer that work is still in progress
		if ( $this->job_incomplete() ) {
			$spinner = '<img src="' . get_admin_url( null, 'images/spinner.gif' ) . '" alt="Working" />';
			$this->notices[] = __( 'Clean-up still in progress&hellip;', 'tribe-events-cleanup' ) . $spinner;
		}
		else {
			$this->in_progress = false;
		}
	}

	protected function pre_cleanup_checks() {
		global $pagenow;

		if ( ! isset( $_REQUEST['do_tribe_cleanup'] ) || 'tools.php' !== $pagenow || ! current_user_can( $this->capability ) ) {
			return false;
		}

		check_admin_referer( 'tribe_cleanup', 'confirm_cleanup' );

		if ( ! isset( $_REQUEST['confirm_risk'] ) ) {
			$this->warnings['no_risk_acknowledgement'] = __( 'Before running the cleanup tool, please acknowledge that you have taken appropriate precautions!', 'tribe-events-cleanup' );
			return false;
		}

		return true;
	}

	/**
	 * Tracks time spent cleaning (to avoid hitting the timeout limit).
	 *
	 * @return bool
	 */
	protected function time_left() {
		if ( 0 === $this->clock ) $this->clock = time();
		return ( time() - $this->clock < $this->timeout );
	}

	/**
	 * Determines if more work still needs to be done to complete the cleanup.
	 *
	 * @return bool
	 */
	protected function job_incomplete() {
		$this->get_counts();
		$counts = (array) $this->counts;
		return max( $counts ) > 0;
	}

	/**
	 * Generate a URL that triggers a continuation of the cleanup process.
	 *
	 * @return string
	 */
	public function reload_link() {
		$current_page = admin_url( $GLOBALS['pagenow'] . '?page=tribe-events-cleanup' );
		$params = array(
			'confirm_cleanup' => wp_create_nonce( 'tribe_cleanup' ),
			'do_tribe_cleanup' => 1,
			'confirm_risk' => 1,
		);
		$url = add_query_arg( $params, $current_page );
		return esc_url( $url );
	}

	protected function keep_cleaning() {
		$this->in_progress = true;

		if ( $this->counts->events > 0 ) $this->clean( self::EVENTS );
		elseif ( $this->counts->venues > 0 ) $this->clean( self::VENUES );
		elseif ( $this->counts->organizers > 0 ) $this->clean( self::ORGANIZERS );
		elseif ( $this->counts->capabilities > 0 ) $this->clean_caps();
		elseif ( $this->counts->options > 0 ) $this->clean_options();
		else flush_rewrite_rules( true );
	}

	/**
	 * Carries out a purge of posts of the specified post type.
	 *
	 * @param $post_type
	 */
	protected function clean( $post_type ) {
		global $wpdb;
		$query = "SELECT ID FROM $wpdb->posts WHERE `post_type` = '%s' LIMIT %d;";
		$post_ids = $wpdb->get_col( $wpdb->prepare( $query, $post_type, $this->batch ) );
		if ( !is_array( $post_ids ) || empty( $post_ids) ) return;
		foreach ( $post_ids as $id ) wp_delete_post( $id, true );
	}

	/**
	 * Removes user role capabilities that look as though they relate to The Events Calendar.
	 */
	protected function clean_caps() {
		global $wp_roles;

		foreach ( $wp_roles->roles as $role_slug => $user_role ) {
			foreach ( $user_role['capabilities'] as $capability => $on ) {
				if ( $this->looks_tribal( $capability ) ) {
					$this->clean_cap( $role_slug, $capability );
				}
			}
		}
	}

	protected function clean_cap( $role, $capability ) {
		$role = get_role( $role );
		if ( null === $role ) return;
		$role->remove_cap( $capability );
	}

	protected function clean_options() {
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->options WHERE `option_name` LIKE '%tribe%events%';" );
	}
}

new TribeEventsCleanup;
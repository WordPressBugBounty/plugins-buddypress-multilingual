<?php
/**
 * Plugin Name: BuddyPress Multilingual
 * Plugin URI: http://wpml.org/?page_id=2890
 * Description: BuddyPress Multilingual. <a href="http://wpml.org/?page_id=2890">Documentation</a>.
 * Author: OnTheGoSystems
 * Author URI: http://www.onthegosystems.com
 * Version: 1.8.1
 *
 * @package bpml
 */

define( 'BPML_VERSION', '1.8.1' );
define( 'BPML_RELPATH', plugins_url( '', __FILE__ ) );
define( 'BPML_PATH', __DIR__ );

require_once BPML_PATH . '/includes/functions.php';

if ( ! class_exists( 'WPML_Core_Version_Check' ) ) {
	require_once BPML_PATH . '/vendor/wpml-shared/wpml-lib-dependencies/src/dependencies/class-wpml-core-version-check.php';
}

if ( ! WPML_Core_Version_Check::is_ok( BPML_PATH . '/wpml-dependencies.json' ) ) {
	add_action( 'admin_notices', 'bpml_admin_notice_required_plugins' );
	return;
}

require_once BPML_PATH . '/vendor/autoload.php';

add_action( 'plugins_loaded', 'bpml_init', 11 );

function bpml_init() {
	if ( ( defined( 'BP_VERSION' ) || defined( 'BP_PLATFORM_VERSION' ) ) && did_action( 'wpml_loaded' ) && did_action( 'wpml_st_loaded' ) ) {
		if ( bpml_is_langauge_as_param() ) {
			add_action( 'admin_notices', 'bpml_admin_notice_wpml_settings' );
		} else {
			// phpcs:disable WordPress.Security.NonceVerification
			$apply_filters = false;

			/*
			 * Check if frontend BP AJAX request
			 * BPML attaches ?lang=[code]&bpml_filter=true to admin ajax url using:
			 * add_filter('bp_core_ajax_url', 'BPML_Filters::core_ajax_url_filter');
			 */
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_GET['bpml_filter'] ) ) {
				$apply_filters = true;
			}

			/*
			 * Heartbeat WP API - BP latest activity AJAX status update
			 * Displayed on activity page, AJAX updated list of activities.
			 * Hooks 'heartbeat_received' and 'heartbeat_nopriv_received'
			 * cannot be used because filters need to be applied earlier.
			 */
			if ( isset( $_POST['action'] ) && 'heartbeat' === $_POST['action']
				&& isset( $_POST['screen_id'] ) && 'front' === $_POST['screen_id']
				&& ! empty( $_POST['data']['bp_activity_last_recorded'] ) ) {
				$apply_filters = true;
			}

			if ( defined( 'DOING_AJAX' )
				&& isset( $_POST['action'] )
				&& in_array( $_POST['action'], [ 'bp_cover_image_upload', 'post_update' ], true )
			) {
				$apply_filters = true;
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			$classes = [ WPML\BuddyPress\Upgrade::class ];

			// Always on frontend.
			if ( ! is_admin() || $apply_filters ) {
				$classes[] = BPML_Filters::class;

				// Verbose page rewrite rules.
				if ( defined( 'BPML_USE_VERBOSE_PAGE_RULES' ) && BPML_USE_VERBOSE_PAGE_RULES ) {
					add_action( 'init', 'bpml_use_verbose_rules' );
					add_filter( 'page_rewrite_rules', 'bpml_page_rewrite_rules_filter' );
					add_filter( 'rewrite_rules_array', 'bpml_rewrite_rules_array_filter' );
				}
			}

			$classes[] = BPML_XProfile::class;
			$classes[] = BPML_Compatibility::class;
			$classes[] = WPML\BuddyPress\Groups::class;
			$classes[] = WPML\BuddyPress\Members::class;
			$classes[] = WPML\BuddyPress\GroupTypes::class;
			$classes[] = WPML\BuddyPress\Activities::class;
			$classes[] = WPML\BuddyPress\ProfileTypes::class;

			$loader = new WPML_Action_Filter_Loader();
			$loader->load( $classes );
		}
	} elseif ( is_admin() ) {
		add_action( 'admin_notices', 'bpml_admin_notice_required_plugins' );
	}
}

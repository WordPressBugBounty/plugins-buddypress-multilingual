<?php
/**
 * Plugin Name: WPML Multilingual for BuddyPress and BuddyBoss
 * Plugin URI: https://wpml.org/documentation/wpml-core-and-add-on-plugins/buddypress-multilingual/
 * Description: WPML Multilingual for BuddyPress and BuddyBoss | <a href="https://wpml.org/documentation/wpml-core-and-add-on-plugins/buddypress-multilingual/">Documentation</a>.
 * Author: OnTheGoSystems
 * Author URI: http://www.onthegosystems.com
 * Version: 5.0.0
 * Plugin Slug: buddypress-multilingual
 * Text Domain: bpml
 * Domain Path: /languages
 *
 * @package bpml
 */

define( 'BPML_VERSION', '5.0.0' );
define( 'BPML_RELPATH', plugins_url( '', __FILE__ ) );
define( 'BPML_PATH', __DIR__ );
define( 'BPML_FILE', __FILE__ );

require_once BPML_PATH . '/includes/functions.php';

add_action( 'init', 'bpml_load_textdomain' );

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
			$apply_filters = false;

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_GET['bpml_filter'] ) ) {
				$apply_filters = true;
			}

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

			$classes = [ WPML\BuddyPress\Upgrade::class ];

			if ( ! is_admin() || $apply_filters ) {
				$classes[] = BPML_Filters::class;

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
			$classes[] = WPML\BuddyPress\ActivityTopics::class;
			$classes[] = WPML\BuddyPress\Documents::class;
			$classes[] = WPML\BuddyPress\Albums::class;
			$classes[] = WPML\BuddyPress\StringTranslationPolicy::class;

			if ( class_exists( 'BP_Xprofile_CFTR' ) ) {
				$classes[] = WPML\BuddyPress\Addons\BPXprofileCustomFieldTypes::class;
			}

			$loader = new WPML_Action_Filter_Loader();
			$loader->load( $classes );
		}
	} elseif ( is_admin() ) {
		add_action( 'admin_notices', 'bpml_admin_notice_required_plugins' );
	}
}

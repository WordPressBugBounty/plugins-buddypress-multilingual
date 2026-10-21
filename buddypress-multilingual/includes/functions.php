<?php

function bpml_load_textdomain() {
	load_plugin_textdomain( 'bpml', false, dirname( plugin_basename( BPML_FILE ) ) . '/languages' );
}

function bpml_is_buddyboss() {
	return defined( 'BP_PLATFORM_VERSION' );
}

function bpml_use_verbose_rules() {
	global $wp_rewrite;
	$wp_rewrite->use_verbose_page_rules = true;
}

function bpml_page_rewrite_rules_filter( $page_rewrite_rules ) {
	$GLOBALS['bpml_page_rewrite_rules'] = $page_rewrite_rules;
	return [];
}

function bpml_rewrite_rules_array_filter( $rewrite_rules ) {
	return $GLOBALS['bpml_page_rewrite_rules'] + $rewrite_rules;
}

function bpml_is_language_per_domain() {
	return (int) apply_filters( 'wpml_setting', null, 'language_negotiation_type' ) === 2;
}

function bpml_is_language_per_dir() {
	return (int) apply_filters( 'wpml_setting', null, 'language_negotiation_type', false ) === 1;
}

function bpml_is_default_language_in_directory() {
	$settings = apply_filters( 'wpml_setting', null, 'urls' );
	return (int) apply_filters( 'wpml_setting', null, 'language_negotiation_type' ) === 1 && ! empty( $settings['directory_for_default_language'] );
}

function bpml_is_langauge_as_param() {
	return (int) apply_filters( 'wpml_setting', null, 'language_negotiation_type' ) === 3;
}

function bpml_admin_notice_required_plugins() {
	$link_url = 'https://wpml.org/faq/how-to-add-string-translation-to-your-site/?utm_source=plugin&utm_medium=gui&utm_campaign=bpml';
	$message  = sprintf(
		/* translators: Warning notice shown in the WordPress admin when a plugin this one needs is missing. %1$s: opening link tag, %2$s: closing link tag; the link opens WPML's documentation. Every product name stays in English. */
		esc_html__( 'To use WPML Multilingual for BuddyPress and BuddyBoss, enable the WPML Multilingual CMS plugin and %1$sString Translation add-on%2$s alongside BuddyPress or BuddyBoss.', 'bpml' ),
		'<a href="' . esc_url( $link_url ) . '" class="wpml-external-link" target="_blank">',
		'</a>'
	);
	echo '<div class="notice notice-warning"><p>' . $message . '</p></div>';
}

function bpml_admin_notice_wpml_settings() {
	echo '<div class="message updated"><p>'
	/* translators: Warning notice shown in the WordPress admin when the site's language URL format is not the one this plugin needs. "Different languages in directories" is an option, and "WPML > Languages > Language URL Format" a path through the WordPress admin: both should read as those screens are named in this language. */
	. esc_html__( 'For WPML Multilingual for BuddyPress and BuddyBoss to work you must set "Different languages in directories" in WPML > Languages > Language URL Format', 'bpml' )
	. '</p></div>';
}

function bpml_refresh_language_caches( $language ) {
	if ( ! $language ) {
		return;
	}

	do_action( 'wpml_switch_language', $language );
	do_action( 'wpml_switch_language', null );
}

function bpml_sanitize_string_name( $string, $max_length = 30 ) {
	$max_length = apply_filters( 'bpml_xprofile_string_name_max_length', $max_length );
	$string     = sanitize_text_field( $string );
	if ( strlen( $string ) > $max_length ) {
		$string = substr( $string, 0, strrpos( substr( $string, 0, $max_length ), ' ' ) );
	}
	return sanitize_title( $string );
}

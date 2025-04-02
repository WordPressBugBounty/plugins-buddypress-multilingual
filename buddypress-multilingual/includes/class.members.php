<?php

namespace WPML\BuddyPress;

class Members implements \IWPML_Frontend_Action {

	public function add_hooks() {
		add_filter( 'wpml_sl_blacklist_requests', array( $this, 'reserved_requests' ) );
	}

	/**
	 * @param string[] $requests
	 *
	 * @return string[]
	 */
	public function reserved_requests( $requests ) {
		$pages_display_as_translated = apply_filters( 'wpml_is_display_as_translated_post_type', false, 'page' );
		if ( ! $pages_display_as_translated ) {
			return $requests;
		}

		$bp_pages         = bp_core_get_directory_pages();
		$members_page_uri = get_page_uri( $bp_pages->members->id );

		// Note that this page might have parent pages!
		$requests[] = '/' . str_replace( '/', '\/', $members_page_uri ) . '\/(.*)/';
		return $requests;
	}

}

<?php

class BPML_Compatibility implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	public function add_hooks() {
		add_action( 'bp_init', [ $this, 'buddydrive' ], 5 );
	}

	public function buddydrive() {
		if ( class_exists( 'BuddyDrive' ) ) {
			$bp_current_component = bp_current_component();
			if ( in_array( $bp_current_component, [ 'buddydrive', 'groups' ], true ) ) {
				add_filter( 'bpml_redirection_page_id', [ $this, 'buddydrive_redirection_page_filter' ], 10, 4 );
			}
		}
	}

	public function buddydrive_redirection_page_filter( $page_id, $bp_current_component, $bp_current_action, $bp_pages ) {
		if (
			'buddydrive' === $bp_current_component
			&& in_array( $bp_current_action, [ 'files', 'friends', 'members' ], true )
			&& isset( $bp_pages->members->id )
		) {
			$page_id = $bp_pages->members->id;
		} elseif (
			'groups' === $bp_current_component
			&& 'buddydrive' === $bp_current_action
			&& isset( $bp_pages->groups->id )
		) {
			$page_id = $bp_pages->groups->id;
		}

		return $page_id;
	}
}

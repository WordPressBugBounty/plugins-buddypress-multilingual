<?php

namespace WPML\BuddyPress;

use WPML\FP\Obj;
use WPML\FP\Fns;

class Groups implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const FIELDS     = [ 'name', 'description' ];
	const TEXTDOMAIN = 'Buddypress Multilingual';

	const FRONTEND_EDIT_NONCE = 'groups_edit_group_details';

	public function add_hooks() {
		add_action( 'groups_group_after_save', [ $this, 'registerStrings' ] );

		foreach ( self::FIELDS as $field ) {
			add_filter( 'bp_get_group_' . $field, $this->translate( $field ), 10, 2 );
		}

		add_filter( 'groups_get_group', [ $this, 'translateStrings' ] );
		add_filter( 'groups_get_groups', [ $this, 'translateGroups' ] );

		add_filter( 'bp_get_group_description_excerpt', [ $this, 'translateExcerpt' ], 10, 2 );
		add_filter( 'bp_nouveau_get_group_description_excerpt', [ $this, 'translateExcerpt' ], 10, 2 );

		add_filter( 'bp_after_groups_edit_base_group_details_parse_args', [ $this, 'saveEditedGroupFields'] );
	}

	/**
	 * @param \BP_Groups_Group|array $group
	 */
	public function registerStrings( $group ) {
		// TODO Check when the current language is not the default one
		// and if coming from the frontend.
		// I assume that all backend-created groups go to default language,
		// but frontend-created groups go to the current language!
		$get = Obj::prop( Fns::__, $group );

		$currentLanguage = null;
		if ( ! is_admin() ) {
			$currentLanguage = apply_filters( 'wpml_current_language', $currentLanguage );
		}

		foreach ( self::FIELDS as $field ) {
			do_action(
				'wpml_register_single_string',
				self::TEXTDOMAIN,
				self::getName( $get( 'id' ), $field ),
				wp_unslash( $get( $field ) ),
				true,
				$currentLanguage
			);
		}
	}

	/**
	 * @param string $field
	 *
	 * @return \Closure (string, BP_Groups_Group|array) -> string
	 */
	public function translate( $field ) {
		return function( $value, $group ) use ( $field ) {
			return apply_filters(
				'wpml_translate_single_string',
				$value,
				self::TEXTDOMAIN,
				self::getName( Obj::prop( 'id', $group ), $field )
			);
		};
	}

	/**
	 * @param \BP_Groups_Group $group
	 *
	 * @return \BP_Groups_Group
	 */
	public function translateStrings( $group ) {
		foreach ( self::FIELDS as $field ) {
			$getTranslation = $this->translate( $field );
			$group->$field  = $getTranslation( $group->$field, $group );
		}
		return $group;
	}

	/**
	 * @param array{'groups': \BP_Groups_Group[]|int[], "total": int} $groups
	 *
	 * @return array{'groups': \BP_Groups_Group[]|int[], "total": int}
	 */
	public function translateGroups( $groups ) {
		if ( ! isset( $groups['groups'] ) ) {
			return $groups;
		}

		foreach ( $groups['groups'] as &$group ) {
			if ( ! $group instanceof \BP_Groups_Group ) {
				continue;
			}
			$group = $this->translateStrings( $group );
		}

		return $groups;
	}

	/**
	 * @param int    $id
	 * @param string $field
	 *
	 * @return string
	 */
	public static function getName( $id, $field ) {
		return sprintf( 'Group #%d %s', $id, $field );
	}

	/**
	 * @param string $excerpt
	 * @param object $group
	 *
	 * @return string
	 */
	public function translateExcerpt( $excerpt, $group ) {
		$getTranslation = $this->translate( 'description' );

		return bp_create_excerpt( $getTranslation( $excerpt, $group ), strlen( $excerpt ) );
	}

	/**
	 * @param array $args
	 *
	 * @return array
	 */
	public function saveEditedGroupFields( $args ) {
		if ( is_admin() ) {
			// Only when editing a group in the frontend.
			return $args;
		}
		if ( ! check_admin_referer( self::FRONTEND_EDIT_NONCE ) ) {
			// Only when editing a group in the frontend.
			return $args;
		}

		$groupId = Obj::prop( 'group_id', $args );
		if ( ! $groupId ) {
			return $args;
		}

		if ( ! function_exists( 'icl_add_string_translation' ) ) {
			return $args;
		}

		$defaultLanguage = apply_filters( 'wpml_default_language', null );
		$currentLanguage = apply_filters( 'wpml_current_language', null );

		remove_filter( 'groups_get_group', [ $this, 'translateStrings' ] );
		$originalGroup = groups_get_group( $groupId );
		add_filter( 'groups_get_group', [ $this, 'translateStrings' ] );

		foreach ( self::FIELDS as $field ) {
			$stringData = array(
				'context' => self::TEXTDOMAIN,
				'name'    => self::getName( $groupId, $field ),
			);

			$stringId = apply_filters( 'wpml_string_id', null, $stringData );
			if ( ! $stringId ) {
				$args[ $field ]  = $originalGroup->$field;
				continue;
			}

			$stringLanguage = apply_filters( 'wpml_get_string_language', null, self::TEXTDOMAIN, self::getName( $groupId, $field ) );
			if ( $stringLanguage !== $currentLanguage ) {
				// The 'wpml_add_string_translation' action is only registered in the backend.
				$translatedStringId = icl_add_string_translation( $stringId, $currentLanguage, $args[ $field ], ICL_TM_COMPLETE );
				$args[ $field ]  = $originalGroup->$field;
			}
		}

		// Refresh cache and textdomain files.
		do_action( 'wpml_switch_language', $currentLanguage );

		return $args;
	}

}

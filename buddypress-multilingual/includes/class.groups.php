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

	public function registerStrings( $group ) {
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
				Sanitizer::groupField( $field, wp_unslash( $get( $field ) ) ),
				true,
				$currentLanguage
			);
		}
	}

	public function translate( $field ) {
		return function( $value, $group ) use ( $field ) {
			$translation = apply_filters(
				'wpml_translate_single_string',
				$value,
				self::TEXTDOMAIN,
				self::getName( Obj::prop( 'id', $group ), $field )
			);

			if ( $translation === $value ) {
				return $value;
			}

			return Sanitizer::groupField( $field, $translation );
		};
	}

	public function translateStrings( $group ) {
		foreach ( self::FIELDS as $field ) {
			$getTranslation = $this->translate( $field );
			$group->$field  = $getTranslation( $group->$field, $group );
		}
		return $group;
	}

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

	public static function getName( $id, $field ) {
		return sprintf( 'Group #%d %s', $id, $field );
	}

	public function translateExcerpt( $excerpt, $group ) {
		$getTranslation = $this->translate( 'description' );

		return bp_create_excerpt( $getTranslation( $excerpt, $group ), strlen( $excerpt ) );
	}

	public function saveEditedGroupFields( $args ) {
		if ( is_admin() ) {
			return $args;
		}
		if ( ! $this->hasFrontendEditNonce() ) {
			return $args;
		}

		$groupId = Obj::prop( 'group_id', $args );
		if ( ! $groupId ) {
			return $args;
		}

		if ( ! $this->currentUserCanEditGroup( $groupId ) ) {
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
			if ( ! is_string( Obj::prop( $field, $args ) ) ) {
				continue;
			}

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
				$translation = Sanitizer::groupField( $field, wp_unslash( $args[ $field ] ) );
				StringTranslationWrite::objectTranslation(
					$stringId,
					$currentLanguage,
					$translation,
					'bp-group:' . $groupId,
					'frontend-group-admin'
				);
				$args[ $field ]  = $originalGroup->$field;
			}
		}

		do_action( 'wpml_switch_language', $currentLanguage );

		return $args;
	}

	private function hasFrontendEditNonce() {
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';

		return (bool) wp_verify_nonce( $nonce, self::FRONTEND_EDIT_NONCE );
	}

	public static function currentUserCanManageById( $groupId ) {
		return ( new self() )->currentUserCanEditGroup( (int) $groupId );
	}

	private function currentUserCanEditGroup( $groupId ) {
		$userId = get_current_user_id();
		if ( ! $userId ) {
			return false;
		}

		if ( function_exists( 'groups_is_user_admin' ) && groups_is_user_admin( $userId, $groupId ) ) {
			return true;
		}

		if ( function_exists( 'bp_current_user_can' ) && bp_current_user_can( 'bp_moderate' ) ) {
			return true;
		}

		return false;
	}

}

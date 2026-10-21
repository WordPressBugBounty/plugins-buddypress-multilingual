<?php

namespace WPML\BuddyPress;

class Upgrade implements \IWPML_Backend_Action {

	const VERSION_KEY = 'bpml_version';

	const AVAILABLE_UPGRADES = [
		'1.6.2' => 'register_group_strings',
		'1.9.4' => 'cleanGroupStringTranslations',
	];

	public function add_hooks() {
		add_action( 'init', [ $this, 'run' ] );
	}

	public function run() {
		$installedVersion = get_option( self::VERSION_KEY, '0.0.0' );
		if ( version_compare( BPML_VERSION, $installedVersion ) > 0 ) {
			foreach ( self::AVAILABLE_UPGRADES as $version => $method ) {
				if ( version_compare( $version, $installedVersion ) > 0 ) {
					call_user_func( [ $this, $method ] );
				}
			}
			update_option( self::VERSION_KEY, BPML_VERSION );
		}
	}

	public function register_group_strings() {
		if ( bp_is_active( 'groups' ) ) {
			$groups = \groups_get_groups( [ 'per_page' => -1 ] );
			foreach ( $groups['groups'] as $group ) {
				do_action_ref_array( 'groups_group_after_save', [ $group ] );
			}
		}
	}

	public function cleanGroupStringTranslations() {
		if ( ! bp_is_active( 'groups' ) ) {
			return;
		}

		if ( ! function_exists( 'icl_add_string_translation' ) || ! function_exists( 'icl_get_string_translations_by_id' ) ) {
			return;
		}

		$groups = \groups_get_groups( [ 'per_page' => -1 ] );
		foreach ( $groups['groups'] as $group ) {
			foreach ( Groups::FIELDS as $field ) {
				$this->cleanStringTranslations( Groups::getName( $group->id, $field ), $field );
			}
		}
	}

	private function cleanStringTranslations( $stringName, $field ) {
		$stringId = apply_filters(
			'wpml_string_id',
			null,
			[
				'context' => Groups::TEXTDOMAIN,
				'name'    => $stringName,
			]
		);

		if ( ! $stringId ) {
			return;
		}

		foreach ( icl_get_string_translations_by_id( $stringId ) as $language => $translation ) {
			if ( ! isset( $translation['value'] ) ) {
				continue;
			}

			$cleanValue = Sanitizer::groupField( $field, $translation['value'] );
			if ( $cleanValue === $translation['value'] ) {
				continue;
			}

			icl_add_string_translation( $stringId, $language, $cleanValue, $translation['status'] );
		}
	}

}

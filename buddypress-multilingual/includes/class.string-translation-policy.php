<?php

namespace WPML\BuddyPress;

class StringTranslationPolicy implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const TEXTDOMAIN = 'Buddypress Multilingual';

	private static function sources() {
		return [
			'frontend-group-admin'  => [
				'prefix'    => 'bp-group:',
				'fields'    => Groups::FIELDS,
				'name'      => [ Groups::class, 'getName' ],
				'authority' => [ Groups::class, 'currentUserCanManageById' ],
			],
			'frontend-album-owner'  => [
				'prefix'    => 'bp-media-album:',
				'fields'    => [ 'title' ],
				'name'      => [ Albums::class, 'getName' ],
				'authority' => [ Albums::class, 'currentUserCanManageById' ],
			],
			'frontend-folder-owner' => [
				'prefix'    => 'bp-document-folder:',
				'fields'    => [ 'title' ],
				'name'      => [ Documents::class, 'getName' ],
				'authority' => [ Documents::class, 'currentUserCanManageById' ],
			],
		];
	}

	public function add_hooks() {
		add_filter( 'wpml_st_object_string_translation_authorized', [ $this, 'authorize' ], 10, 4 );
	}

	public function authorize( $allowed, $stringId, $language, $provenance = [] ) {
		if ( true === $allowed ) {
			return true;
		}

		$principal = isset( $provenance['principal'] ) ? $provenance['principal'] : '';
		$source    = isset( $provenance['source'] ) ? $provenance['source'] : '';

		$sources = self::sources();
		if ( ! isset( $sources[ $principal ] ) ) {
			return false;
		}

		$spec = $sources[ $principal ];

		$objectId = self::sourceObjectId( $source, $spec['prefix'] );
		if ( ! $objectId ) {
			return false;
		}

		if ( ! $this->stringBelongsToObject( (int) $stringId, $objectId, $spec ) ) {
			return false;
		}

		if ( ! (bool) call_user_func( $spec['authority'], $objectId ) ) {
			return false;
		}

		return true;
	}

	private static function sourceObjectId( $source, $prefix ) {
		if ( ! is_string( $source ) || 0 !== strpos( $source, $prefix ) ) {
			return 0;
		}

		$id = substr( $source, strlen( $prefix ) );
		if ( ! ctype_digit( (string) $id ) ) {
			return 0;
		}

		return (int) $id;
	}

	private function stringBelongsToObject( $stringId, $objectId, array $spec ) {
		if ( ! $stringId ) {
			return false;
		}

		foreach ( $spec['fields'] as $field ) {
			$name       = call_user_func( $spec['name'], $objectId, $field );
			$candidate  = (int) apply_filters(
				'wpml_string_id',
				null,
				[
					'context' => self::TEXTDOMAIN,
					'name'    => $name,
				]
			);

			if ( $candidate && $candidate === $stringId ) {
				return true;
			}
		}

		return false;
	}
}

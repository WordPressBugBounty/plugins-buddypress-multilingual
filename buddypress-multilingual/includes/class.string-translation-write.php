<?php

namespace WPML\BuddyPress;

class StringTranslationWrite {

	public static function objectTranslation( $stringId, $language, $value, $source, $principal ) {
		if ( ! function_exists( 'wpml_st_set_authorized_object_translation' ) ) {
			return false;
		}

		return wpml_st_set_authorized_object_translation(
			$stringId,
			$language,
			$value,
			[
				'principal' => $principal,
				'source'    => $source,
			]
		);
	}
}

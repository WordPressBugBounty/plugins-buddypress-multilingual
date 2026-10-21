<?php

namespace WPML\BuddyPress;

class Sanitizer {

	public static function groupName( $value ) {
		$value = self::defaultKses( $value );

		if ( function_exists( 'bb_core_remove_unfiltered_html' ) ) {
			$value = bb_core_remove_unfiltered_html( $value );
		}

		return $value;
	}

	public static function groupDescription( $value ) {
		if ( function_exists( 'bp_groups_filter_kses' ) ) {
			return bp_groups_filter_kses( $value );
		}

		return self::defaultKses( $value );
	}

	public static function groupField( $field, $value ) {
		return 'description' === $field ? self::groupDescription( $value ) : self::groupName( $value );
	}

	public static function plainText( $value ) {
		$value = (string) $value;

		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}

		return trim( strip_tags( $value ) );
	}

	public static function xprofileLabel( $value ) {
		return self::defaultKses( $value );
	}

	public static function profileFieldValue( $value, $fieldId = null ) {
		if ( function_exists( 'xprofile_filter_kses' ) ) {
			return xprofile_filter_kses( $value, null, $fieldId );
		}

		return self::defaultKses( $value );
	}

	private static function defaultKses( $value ) {
		if ( function_exists( 'wp_kses' ) && function_exists( 'wp_kses_allowed_html' ) ) {
			return wp_kses( (string) $value, wp_kses_allowed_html( 'data' ) );
		}

		return strip_tags( (string) $value );
	}

}

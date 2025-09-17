<?php

namespace WPML\BuddyPress\Addons;

use WPML\FP\Obj;

class BPXprofileCustomFieldTypes implements \IWPML_Frontend_Action, \IWPML_Backend_Action, \IWPML_DIC_Action {

	const TRANSLATABLE          = 'translatable';
	const DISPLAY_AS_TRANSLATED = 'display_as_translated';
	const NOT_TRANSLATABLE      = 'not_translatable';

	public function add_hooks() {
		add_filter( 'bp_get_the_profile_field_multiselect_custom_post_type_selected', [ $this, 'multiselectCustomPostTypeSelected' ], 1, 3 );
		add_filter( 'bp_xprofile_field_multiselect_custom_post_type_value', [ $this, 'multiselectCustomPostTypeValue' ], 1, 2 );

		add_filter( 'bp_get_the_profile_field_select_custom_post_type_selected', [ $this, 'selectCustomPostTypeSelected' ], 1, 3 );
		add_filter( 'bp_xprofile_field_select_custom_post_type_value', [ $this, 'selectCustomPostTypeValue' ], 1, 2 );

		add_filter( 'bp_get_the_profile_field_multiselect_custom_taxonomy_selected', [ $this, 'multiselectCustomTaxonomySelected' ], 1, 3 );
		add_filter( 'bp_get_the_profile_field_select_custom_taxonomy_selected', [ $this, 'selectCustomTaxonomySelected' ], 1, 3 );

		add_filter( 'bp_xprofile_set_field_data_pre_validate', [ $this, 'onSave' ], 1, 3 );
	}

	/**
	 * @param string $postType
	 *
	 * @return string
	 */
	private static function getPostTypeTranslationMode( $postType ) {
		if ( apply_filters( 'wpml_is_display_as_translated_post_type', false, $postType ) ) {
			return self::DISPLAY_AS_TRANSLATED;
		} elseif ( apply_filters( 'wpml_is_translated_post_type', false, $postType ) ) {
			return self::TRANSLATABLE;
		}

		return self::NOT_TRANSLATABLE;
	}

	/**
	 * @param string $taxonomy
	 *
	 * @return string
	 */
	private static function getTaxonomyTranslationMode( $taxonomy ) {
		global $sitepress;
		if ( ! $sitepress ) {
			return self::NOT_TRANSLATABLE;
		}

		if ( $sitepress->is_display_as_translated_taxonomy( $taxonomy ) ) {
			return self::DISPLAY_AS_TRANSLATED;
		} elseif ( apply_filters( 'wpml_is_translated_taxonomy', false, $taxonomy ) ) {
			return self::TRANSLATABLE;
		}

		return self::NOT_TRANSLATABLE;
	}

	/**
	 * @param int[]  $selected
	 * @param array  $args
	 * @param string $postType
	 *
	 * @return int[]
	 */
	public function multiselectCustomPostTypeSelected( $selected, $args, $postType ) {
		if ( empty( $selected ) ) {
			return $selected;
		}

		if ( ! is_array( $selected ) ) {
			return $selected;
		}

		$translationMode = self::getPostTypeTranslationMode( $postType );
		if ( self::NOT_TRANSLATABLE === $translationMode ) {
			return $selected;
		}

		foreach ( $selected as &$postId ) {
			$postId = apply_filters( 'wpml_object_id', $postId, $postType, self::DISPLAY_AS_TRANSLATED === $translationMode );
		}

		return array_filter( $selected );
	}

	/**
	 * @param int[] $postIds
	 * @param int   $fieldId
	 *
	 * @return int[]
	 */
	public function multiselectCustomPostTypeValue( $postIds, $fieldId ) {
		if ( empty( $postIds ) ) {
			return $postIds;
		}

		if ( ! is_array( $postIds ) ) {
			return $postIds;
		}

		if ( empty( $fieldId ) ) {
			return $postIds;
		}

		if ( ! function_exists( 'bp_xprofile_get_meta' ) ) {
			return $postIds;
		}

		$postType = bp_xprofile_get_meta( $fieldId, 'field', 'selected_post_type', true );
		if ( ! $postType ) {
			return $postIds;
		}

		$translationMode = self::getPostTypeTranslationMode( $postType );
		if ( self::NOT_TRANSLATABLE === $translationMode ) {
			return $postIds;
		}

		foreach ( $postIds as &$postId ) {
			$postId = apply_filters( 'wpml_object_id', $postId, $postType, self::DISPLAY_AS_TRANSLATED === $translationMode );
		}

		return array_filter( $postIds );
	}

	/**
	 * @param int    $selected
	 * @param array  $args
	 * @param string $postType
	 *
	 * @return int
	 */
	public function selectCustomPostTypeSelected( $selected, $args, $postType ) {
		if ( empty( $selected ) ) {
			return $selected;
		}

		$translationMode = self::getPostTypeTranslationMode( $postType );
		if ( self::NOT_TRANSLATABLE === $translationMode ) {
			return $selected;
		}

		$selected = apply_filters( 'wpml_object_id', $selected, $postType, self::DISPLAY_AS_TRANSLATED === $translationMode );

		return $selected ?? '';
	}

	/**
	 * @param int $postId
	 * @param int $fieldId
	 *
	 * @return int
	 */
	public function selectCustomPostTypeValue( $postId, $fieldId ) {
		if ( empty( $postId ) ) {
			return $postId;
		}

		$postType = get_post_type( $postId );
		if ( ! $postType ) {
			return $postId;
		}

		$translationMode = self::getPostTypeTranslationMode( $postType );
		if ( self::NOT_TRANSLATABLE === $translationMode ) {
			return $postId;
		}

		$postId = apply_filters( 'wpml_object_id', $postId, $postType, self::DISPLAY_AS_TRANSLATED === $translationMode );

		return $postId ?? '';
	}

	/**
	 * @param int[]  $selected
	 * @param array  $args
	 * @param string $taxonomy
	 *
	 * @return int[]
	 */
	public function multiselectCustomTaxonomySelected( $selected, $args, $taxonomy ) {
		if ( empty( $selected ) ) {
			return $selected;
		}

		if ( ! is_array( $selected ) ) {
			return $selected;
		}

		global $sitepress;
		if ( ! $sitepress ) {
			return $selected;
		}

		$translationMode = self::getTaxonomyTranslationMode( $taxonomy );
		if ( self::NOT_TRANSLATABLE === $translationMode ) {
			return $selected;
		}

		foreach ( $selected as &$termId ) {
			$termId = apply_filters( 'wpml_object_id', $termId, $taxonomy, self::DISPLAY_AS_TRANSLATED === $translationMode );
		}
		return array_filter( $selected );
	}

	/**
	 * @param int    $selected
	 * @param array  $args
	 * @param string $taxonomy
	 *
	 * @return int
	 */
	public function selectCustomTaxonomySelected( $selected, $args, $taxonomy ) {
		if ( empty( $selected ) ) {
			return $selected;
		}

		global $sitepress;
		if ( ! $sitepress ) {
			return $selected;
		}

		$translationMode = self::getTaxonomyTranslationMode( $taxonomy );
		if ( self::NOT_TRANSLATABLE === $translationMode ) {
			return $selected;
		}

		$selected = apply_filters( 'wpml_object_id', $selected, $taxonomy, self::DISPLAY_AS_TRANSLATED === $translationMode );

		return $selected ?? '';
	}

	/**
	 * Save field values always in the default language, for consistency.
	 *
	 * @param mixed                   $value        Value passed to xprofile_set_field_data().
	 * @param \BP_XProfile_Field      $field        Field object.
	 * @param \BP_XProfile_Field_Type $fieldTypeObj Field type object.
	 *
	 * @return mixed
	 */
	public function onSave( $value, $field, $fieldTypeObj ) {
		$relevantFieldTypes = [
			'BPXProfileCFTR\Field_Types\Field_Type_Select_Post_Type',
			'BPXProfileCFTR\Field_Types\Field_Type_Multi_Select_Post_Type',
			'BPXProfileCFTR\Field_Types\Field_Type_Select_Taxonomy',
			'BPXProfileCFTR\Field_Types\Field_Type_Multi_Select_Taxonomy',
		];
		foreach ( $relevantFieldTypes as $relevantType ) {
			if ( ! class_exists( $relevantType ) ) {
				return $value;
			}
		}

		if (
			/** @phpstan-ignore-next-line */
			! $fieldTypeObj instanceof \BPXProfileCFTR\Field_Types\Field_Type_Select_Post_Type &&
			/** @phpstan-ignore-next-line */
			! $fieldTypeObj instanceof \BPXProfileCFTR\Field_Types\Field_Type_Multi_Select_Post_Type &&
			/** @phpstan-ignore-next-line */
			! $fieldTypeObj instanceof \BPXProfileCFTR\Field_Types\Field_Type_Select_Taxonomy &&
			/** @phpstan-ignore-next-line */
			! $fieldTypeObj instanceof \BPXProfileCFTR\Field_Types\Field_Type_Multi_Select_Taxonomy
		) {
			return $value;
		}

		if ( ! function_exists( 'bp_xprofile_get_meta' ) ) {
			return $value;
		}

		$defaultLanguage = apply_filters( 'wpml_default_language', null );
		$currentLanguage = apply_filters( 'wpml_current_language', null );
		if ( $defaultLanguage === $currentLanguage ) {
			return $value;
		}

		$fieldId             = $field->id;
		$fieldSelectedObject = null;
		$translationMode     = self::NOT_TRANSLATABLE;
		if (
			/** @phpstan-ignore-next-line */
			$fieldTypeObj instanceof \BPXProfileCFTR\Field_Types\Field_Type_Select_Post_Type ||
			/** @phpstan-ignore-next-line */
			$fieldTypeObj instanceof \BPXProfileCFTR\Field_Types\Field_Type_Multi_Select_Post_Type
		) {
			$fieldSelectedObject = bp_xprofile_get_meta( $fieldId, 'field', 'selected_post_type', true );
			if ( ! $fieldSelectedObject ) {
				return $value;
			}
			$translationMode = self::getPostTypeTranslationMode( $fieldSelectedObject );
		}
		if (
			/** @phpstan-ignore-next-line */
			$fieldTypeObj instanceof \BPXProfileCFTR\Field_Types\Field_Type_Select_Taxonomy ||
			/** @phpstan-ignore-next-line */
			$fieldTypeObj instanceof \BPXProfileCFTR\Field_Types\Field_Type_Multi_Select_Taxonomy
		) {
			$fieldSelectedObject = bp_xprofile_get_meta( $fieldId, 'field', 'selected_taxonomy', true );
			if ( ! $fieldSelectedObject ) {
				return $value;
			}
			$translationMode = self::getTaxonomyTranslationMode( $fieldSelectedObject );
		}

		if ( ! $fieldSelectedObject ) {
			return $value;
		}
		if ( self::NOT_TRANSLATABLE === $translationMode ) {
			return $value;
		}

		if (
			/** @phpstan-ignore-next-line */
			$fieldTypeObj instanceof \BPXProfileCFTR\Field_Types\Field_Type_Select_Post_Type ||
			/** @phpstan-ignore-next-line */
			$fieldTypeObj instanceof \BPXProfileCFTR\Field_Types\Field_Type_Select_Taxonomy
		) {
			$value = apply_filters( 'wpml_object_id', $value, $fieldSelectedObject, self::DISPLAY_AS_TRANSLATED === $translationMode, $defaultLanguage );
			return $value ?? '';
		}

		if (
			/** @phpstan-ignore-next-line */
			$fieldTypeObj instanceof \BPXProfileCFTR\Field_Types\Field_Type_Multi_Select_Post_Type ||
			/** @phpstan-ignore-next-line */
			$fieldTypeObj instanceof \BPXProfileCFTR\Field_Types\Field_Type_Multi_Select_Taxonomy
		) {
			foreach ( $value as &$valueId ) {
				$valueId = apply_filters( 'wpml_object_id', $valueId, $fieldSelectedObject, self::DISPLAY_AS_TRANSLATED === $translationMode, $defaultLanguage );
			}
			$value = array_filter( $value );
		}

		return $value;
	}

}

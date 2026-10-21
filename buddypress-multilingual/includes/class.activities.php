<?php

namespace WPML\BuddyPress;

use WPML\FP\Obj;

class Activities implements \IWPML_Frontend_Action, \IWPML_Backend_Action, \IWPML_DIC_Action {

	private $wpdb;

	const ACTIVITY_FEED_POST_TYPE_PREFIX           = 'new_blog_';
	const ACTIVITY_FEED_POST_TYPE_LIKE_PLACEHOLDER = 'new_blog_%';
	const ELEMENT_TYPE_LIKE_PLACEHOLDER            = 'post_%';

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	private function maybeAddActivityQueryFilters() {
		if ( ! is_admin() ) {
			return true;
		}

		if (
			defined( 'DOING_AJAX' ) && DOING_AJAX
			&& 'activity_filter' === Obj::prop( 'action', $_POST )
		) {
			return true;
		}

		return false;
	}

	public function add_hooks() {
		if ( $this->maybeAddActivityQueryFilters() ) {
			add_filter( 'bp_activity_get_join_sql', [ $this, 'getJoinSql' ] );
			add_filter( 'bp_activity_get_where_conditions', [ $this, 'getWhereConditions' ] );
		}
		add_action( 'init', [ $this, 'forceTranslatedPostsActivities' ], 1 );
	}

	private function getCurrentLanguage() {
		return apply_filters( 'wpml_current_language', null );
	}

	private function getDefaultLanguage() {
		return apply_filters( 'wpml_default_language', null );
	}

	private function getPostTypeActivityType( $postType ) {
		return self::ACTIVITY_FEED_POST_TYPE_PREFIX . $postType;
	}

	private function getTypesByTranslationMode( $postTypes ) {
		$result = [
			'translated'    => [],
			'asTranslated'  => [],
			'notTranslated' => [],
		];

		foreach ( $postTypes as $postType ) {
			if ( apply_filters( 'wpml_is_display_as_translated_post_type', false, $postType ) ) {
				$result['asTranslated'][] = $this->getPostTypeActivityType( $postType );
			} else if ( apply_filters( 'wpml_is_translated_post_type', false, $postType ) ) {
				$result['translated'][] = $this->getPostTypeActivityType( $postType );
			} else {
				$result['notTranslated'][] = $this->getPostTypeActivityType( $postType );
			}
		}

		return $result;
	}

	public function getJoinSql( $joinSql ) {
		$currentLanguage = $this->getCurrentLanguage();
		$defaultLanguage = $this->getDefaultLanguage();

		if ( ! $currentLanguage || ! $defaultLanguage ) {
			return $joinSql;
		}

		$joinSql .= "
			LEFT JOIN {$this->wpdb->prefix}icl_translations bpml_translations
			ON bpml_translations.element_id = a.secondary_item_id
			AND bpml_translations.element_type LIKE '" . self::ELEMENT_TYPE_LIKE_PLACEHOLDER . "'
			AND a.type LIKE '" . self::ACTIVITY_FEED_POST_TYPE_LIKE_PLACEHOLDER . "'
			";

		return $joinSql;
	}

	public function getWhereConditions( $whereConditions ) {
		$currentLanguage = $this->getCurrentLanguage();
		$defaultLanguage = $this->getDefaultLanguage();

		if ( ! $currentLanguage || ! $defaultLanguage ) {
			return $whereConditions;
		}

		$postTypes = get_post_types();

		$typesByTranslationMode = $this->getTypesByTranslationMode( $postTypes );
		$multilingualConditions = [ 'bpml_translations.element_id IS NULL' ];

		if ( ! empty( $typesByTranslationMode['translated'] ) ) {
			$multilingualConditions[] = $this->wpdb->prepare(
				'( a.type IN (' . wpml_prepare_in( $typesByTranslationMode['translated'] ) . ') AND bpml_translations.language_code = %s )',
				$currentLanguage
			);
		}

		if ( ! empty( $typesByTranslationMode['asTranslated'] ) ) {
			if ( $currentLanguage === $defaultLanguage ) {
				$multilingualConditions[] = $this->wpdb->prepare(
					'( a.type IN (' . wpml_prepare_in( $typesByTranslationMode['asTranslated'] ) . ') AND bpml_translations.language_code = %s )',
					$currentLanguage
				);
			} else {
				$multilingualConditions[]  = $this->wpdb->prepare(
					"(
						a.type IN (" . wpml_prepare_in( $typesByTranslationMode['asTranslated'] ) . ") 
						AND (
							bpml_translations.language_code = %s
							OR (
								bpml_translations.language_code = %s
								AND (
									( SELECT COUNT( element_id )
										FROM {$this->wpdb->prefix}icl_translations
										WHERE trid = bpml_translations.trid
										AND language_code = %s
									) = 0
								)
							)
						)
					)",
					$currentLanguage,
					$defaultLanguage,
					$currentLanguage
				);
			}
		}

		$multilingualConditionsSql                      = '( ' . implode( ' OR ', $multilingualConditions ) . ' )';
		$whereConditions[ 'bpml_activity_by_language' ] = $multilingualConditionsSql;

		return $whereConditions;
	}

	public function forceTranslatedPostsActivities() {
		if ( ! class_exists( 'BB_WPML_Helpers' ) || ! method_exists( 'BB_WPML_Helpers', 'instance' ) ) {
			return;
		}
		$bbWpmlHelpers = \BB_WPML_Helpers::instance();
		if ( ! method_exists( $bbWpmlHelpers, 'bb_prevent_translated_post_activities' ) ) {
			return;
		}
		remove_filter( 'bp_init', array( $bbWpmlHelpers, 'bb_prevent_translated_post_activities' ) );
	}

}

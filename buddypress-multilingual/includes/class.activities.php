<?php

namespace WPML\BuddyPress;

class Activities implements \IWPML_Frontend_Action, \IWPML_DIC_Action {
	/**
	 * @var \wpdb
	 */
	private $wpdb;

	const ACTIVITY_TYPES_FILTERED_BY_LANGUAGE = [ 'new_blog_post' ];

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function add_hooks() {
		add_filter( 'bp_activity_get_join_sql', [ $this, 'filtering_activity_set_join_sql' ] );
		add_filter( 'bp_activity_get_where_conditions', [ $this, 'filtering_activity_set_where_condition_sql' ] );
	}

	/**
	 * Adds a join to the table containing information about the language of a given element
	 * Is required by @see filtering_activity_set_where_condition_sql()
	 *
	 * @param string $join_sql JOIN clause.
	 *
	 * @return string $join_sql
	 *
	 * @since BuddyPressML 1.8.0
	 */
	public function filtering_activity_set_join_sql( $join_sql ) {
		$currentLanguage = $this->getCurrentLanguage();

		if ( ! $currentLanguage ) {
			return $join_sql;
		}

		$join_sql .= " LEFT JOIN {$this->wpdb->prefix}icl_translations bpml_translations"
			. ' ON bpml_translations.element_id = a.secondary_item_id'
			. "  AND bpml_translations.element_type LIKE 'post_%'"
			. '  AND a.type IN ( ' . wpml_prepare_in( self::ACTIVITY_TYPES_FILTERED_BY_LANGUAGE ) . ' )';

		return $join_sql;
	}

	/**
	 * Filters the visibility of activity items of a given type only in the selected language
	 * Requires @see filtering_activity_set_join_sql()
	 *
	 * @param array $where_conditions Current conditions for MySQL WHERE statement.
	 *
	 * @return array $where_conditions
	 *
	 * @since BuddyPressML 1.8.0
	 */
	public function filtering_activity_set_where_condition_sql( $where_conditions ) {
		$currentLanguage = $this->getCurrentLanguage();

		if ( ! $currentLanguage ) {
			return $where_conditions;
		}

		$filtering_activity_sql = '( '
			. $this->wpdb->prepare( 'bpml_translations.language_code = %s', $currentLanguage )
			. ' OR bpml_translations.element_id IS NULL'
			. ' ) ';

		$where_conditions[ 'bpml_filter_activity_by_language' ] = $filtering_activity_sql;

		return $where_conditions;
	}

	/**
	 * @return string|null
	 */
	private function getCurrentLanguage() {
		return apply_filters( 'wpml_current_language', null );
	}
}

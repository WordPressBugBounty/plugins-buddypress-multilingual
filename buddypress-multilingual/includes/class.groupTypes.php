<?php

namespace WPML\BuddyPress;

class GroupTypes implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const BUDDYBOSS_GROUP_TYPE_KIND  = 'BuddyBoss Group Type';
	const BUDDYPRESS_GROUP_TYPE_KIND = 'BuddyPress Group Type';
	const GROUP_TYPE_POST_TYPE       = 'bp-group-type';
	const GROUP_TYPE_TAXONOMY        = 'bp_group_type';

	const ROLE_LABEL_KEYS = [
		'organizer_plural_label_name',
		'organizer_singular_label_name',
		'moderator_plural_label_name',
		'moderator_singular_label_name',
		'member_plural_label_name',
		'member_singular_label_name',
	];
	const CUSTOM_FIELDS   = [
		'_bp_group_type_label_name'          => 'label_name',
		'_bp_group_type_label_singular_name' => 'singular_label',
		'_bp_group_type_role_labels'         => 'role_labels',
	];

	public function add_hooks() {
		/*
		* This feature works differently on BuddyBoss and BuddyPress, so we need to handle it differently in each case.
		* - On BuddyPress, the strings are stored in the `bp_group_types` taxonomy.
		* - On BuddyBoss, the strings are stored in the `bp-group-type` post type (which are linked to the taxonomy).
		*
		* Both uses the bp_groups_get_group_types filter. So we can keep it out of the if/else block.
		*/
		add_filter( 'bp_groups_get_group_types', [ $this, 'translateGroupTypesList' ], 10, 3 );
		
		if ( $this->buddyBossFunctionsExists() ) {
			add_action( 'wp_after_insert_post', [ $this, 'registerGroupTypeStrings' ], 10, 3 );
			add_filter( 'bp_group_organizer_label_text', [ $this, 'translateLabelTextFilter' ], 10, 3 );
			add_filter( 'bp_group_moderator_label_text', [ $this, 'translateLabelTextFilter' ], 10, 3 );
			add_filter( 'bp_group_member_label_text', [ $this, 'translateLabelTextFilter' ], 10, 3 );

			foreach ( self::ROLE_LABEL_KEYS as $labelName ) {
				add_filter( "bp_{$labelName}", [ $this, 'translateGroupLabel' ], 10, 3 );
			}

			add_action( 'before_delete_post', [ $this, 'deleteGroupTypeStrings' ] );
		} else {
			add_action( 'updated_term_meta', [ $this, 'registerGroupTypeTermStrings' ], 10, 4 );
			add_action( 'added_term_meta', [ $this, 'registerGroupTypeTermStrings' ], 10, 4 );
			add_action( 'delete_term', [ $this, 'deleteGroupTypeTermStrings' ], 10, 4 );
		}

	}

	/**
	 * @param int      $postID
	 * @param \WP_Post $post
	 * @param bool     $update
	 */
	public function registerGroupTypeStrings( $postID, $post, $update ) {
		if ( self::GROUP_TYPE_POST_TYPE !== $post->post_type || wp_is_post_revision( $postID ) ) {
			return;
		}

		$package = [
			'kind'      => $this->getGroupTypeKind(),
			'name'      => $postID,
			'title'     => $post->post_title,
			'edit_link' => get_edit_post_link( $postID ),
			'view_link' => '',
		];

		do_action( 'wpml_start_string_package_registration', $package );

		$this->registerCustomFields( $postID, $package );

		do_action( 'wpml_delete_unused_package_strings', $package );
	}

	/**
	 * @param int   $postID
	 * @param array $package
	 */
	protected function registerCustomFields( $postID, $package ) {
		foreach ( self::CUSTOM_FIELDS as $metaKey => $name ) {
			$metaValue = get_post_meta( $postID, $metaKey, true );
			if ( ! $metaValue ) {
				continue;
			}

			if ( is_array( $metaValue ) ) {
				foreach ( $metaValue as $key => $value ) {
					if ( is_string( $value ) && in_array( $key, self::ROLE_LABEL_KEYS, true ) ) {
						$this->registerString( $value, $key, $package );
					}
				}
			} else {
				$this->registerString( $metaValue, $name, $package );
			}
		}
	}

	/**
	 * @param string $value
	 * @param string $name
	 * @param array  $package
	 */
	protected function registerString( $value, $name, $package ) {
		do_action(
			'wpml_register_string',
			wp_unslash( $value ),
			$name,
			$package,
			$name,
			'LINE'
		);
	}

	/**
	 * @param string $text
	 * @param string $name
	 * @param array  $package
	 *
	 * @return string
	 */
	protected function translateString( $text, $name, $package ) {
		return apply_filters(
			'wpml_translate_string',
			$text,
			$name,
			$package
		);
	}

	/**
	 * @param array  $types
	 * @param array  $args
	 * @param string $operator
	 *
	 * @return array
	 */
	public function translateGroupTypesList( $types, $args, $operator ) {
		if ( empty( $types ) ) {
			return $types;
		}

		foreach ( $types as $key => $type ) {
			$groupTypeID = $this->getGroupTypeId( $key );
			if ( $groupTypeID ) {
				$package = [
					'kind' => $this->getGroupTypeKind(),
					'name' => $groupTypeID,
				];

				if ( isset( $type->labels ) && is_array( $type->labels ) ) {
					if ( isset( $type->labels['name'] ) ) {
						$type->labels['name'] = $this->translateString( $type->labels['name'], 'label_name', $package );
					}

					if ( isset( $type->labels['singular_name'] ) ) {
						$type->labels['singular_name'] = $this->translateString( $type->labels['singular_name'], 'singular_label', $package );
					}
				}

				$types[ $key ] = $type;
			}
		}
		return $types;
	}

	/**
	 * @param string $groupType
	 *
	 * @return int
	 */
	protected function getGroupTypeId( $groupType ) {
		if ( $this->buddyBossFunctionsExists() ) {
			return bp_group_get_group_type_id( $groupType );
		}

		$term = get_term_by( 'slug', $groupType, self::GROUP_TYPE_TAXONOMY );
		return $term ? $term->term_id : 0;
	}

	/**
	 * @return bool
	 */
	protected function buddyBossFunctionsExists() {
		return function_exists( 'bp_groups_get_group_type' ) && function_exists( 'bp_group_get_group_type_id' );
	}

	/**
	 * @param string $buttonText
	 * @param int    $groupID
	 * @param string $roleKey
	 *
	 * @return string
	 */
	public function translateLabelTextFilter( $buttonText, $groupID, $roleKey ) {
		$stringName = $this->getStringNameByFilter( $roleKey );
		return $this->translateGroupLabel( $buttonText, $groupID, $stringName );
	}

	/**
	 * @param string $roleKey
	 *
	 * @return string
	 */
	protected function getStringNameByFilter( $roleKey ) {
		$currentFilter = current_filter();

		switch ( $currentFilter ) {
			case 'bp_group_organizer_label_text':
				return 'organizer_singular_label_name';
			case 'bp_group_moderator_label_text':
				return 'moderator_singular_label_name';
			case 'bp_group_member_label_text':
				return 'member_singular_label_name';
			default:
				return $roleKey;
		}
	}

	/**
	 * @param string $text
	 * @param int    $groupID
	 * @param string $name
	 *
	 * @return string
	 */
	public function translateGroupLabel( $text, $groupID, $name ) {

		$groupType   = bp_groups_get_group_type( $groupID );
		$groupTypeID = $this->getGroupTypeId( $groupType );

		if ( ! $groupTypeID ) {
			return $text;
		}

		$package = [
			'kind' => $this->getGroupTypeKind(),
			'name' => $groupTypeID,
		];

		return $this->translateString( $text, $name, $package );
	}

	/**
	 * @param int $postID
	 */
	public function deleteGroupTypeStrings( $postID ) {
		if ( get_post_type( $postID ) !== self::GROUP_TYPE_POST_TYPE ) {
			return;
		}

		do_action(
			'wpml_delete_package',
			$postID,
			self::BUDDYBOSS_GROUP_TYPE_KIND
		);
	}

	/**
	 * @param int    $metaID
	 * @param int    $termID
	 * @param string $metaKey
	 * @param mixed  $metaValue
	 */
	public function registerGroupTypeTermStrings( $metaID, $termID, $metaKey, $metaValue ) {
		if ( ! in_array( $metaKey, [ 'bp_type_name', 'bp_type_singular_name' ], true ) ) {
			return;
		}

		$term = get_term( $termID );
		if ( ! $term || self::GROUP_TYPE_TAXONOMY !== $term->taxonomy ) {
			return;
		}

		$package = [
			'kind'      => self::BUDDYPRESS_GROUP_TYPE_KIND,
			'name'      => $termID,
			'title'     => $term->name,
			'edit_link' => get_edit_term_link( $termID, self::GROUP_TYPE_TAXONOMY ),
			'view_link' => '',
		];

		do_action( 'wpml_start_string_package_registration', $package );

		$typeName = get_term_meta( $termID, 'bp_type_name', true );
		if ( $typeName ) {
			$this->registerString( $typeName, 'label_name', $package );
		}

		$typeSingular = get_term_meta( $termID, 'bp_type_singular_name', true );
		if ( $typeSingular ) {
			$this->registerString( $typeSingular, 'singular_label', $package );
		}

		do_action( 'wpml_delete_unused_package_strings', $package );
	}

	/**
	 * @param int    $termID
	 * @param int    $ttID
	 * @param string $taxonomy
	 * @param mixed  $deletedTerm
	 */
	public function deleteGroupTypeTermStrings( $termID, $ttID, $taxonomy, $deletedTerm ) {
		if ( self::GROUP_TYPE_TAXONOMY !== $taxonomy ) {
			return;
		}

		do_action(
			'wpml_delete_package',
			$termID,
			self::BUDDYPRESS_GROUP_TYPE_KIND
		);
	}

	/**
	 * @return string
	 */
	protected function getGroupTypeKind() {
		return $this->buddyBossFunctionsExists()
		? self::BUDDYBOSS_GROUP_TYPE_KIND
		: self::BUDDYPRESS_GROUP_TYPE_KIND;
	}

}

<?php

namespace WPML\BuddyPress;

class ProfileTypes implements \IWPML_Backend_Action, \IWPML_Frontend_Action {
	const PROFILE_TYPE_KIND      = 'BuddyPress Profile Type';
	const PROFILE_TYPE_POST_TYPE = 'bp-member-type';
	const PROFILE_TYPE_TAXONOMY  = 'bp_member_type';
	const CUSTOM_FIELDS          = [
		'_bp_member_type_label_name'          => 'plural_label',
		'_bp_member_type_label_singular_name' => 'singular_label',
	];
	const TERM_FIELDS            = [
		'bp_type_name'          => 'plural_label',
		'bp_type_singular_name' => 'singular_label',
	];

	public function add_hooks() {
		add_filter( 'bp_get_member_types', [ $this, 'translateProfileTypesList' ], 10, 3 );

		if ( bpml_is_buddyboss() ) {
			add_action( 'wp_after_insert_post', [ $this, 'registerProfileTypeStrings' ], 10, 3 );
			add_action( 'before_delete_post', [ $this, 'deleteProfileTypeStrings' ] );
			add_filter( 'get_post_metadata', [ $this, 'translateProfileTypeMetaFields' ], 10, 4 );

		} else {
			add_action( 'updated_term_meta', [ $this, 'registerProfileTypeTermStrings' ], 10, 4 );
			add_action( 'added_term_meta', [ $this, 'registerProfileTypeTermStrings' ], 10, 4 );
			add_action( 'delete_term', [ $this, 'deleteProfileTypeTermStrings' ], 10, 4 );
		}
	}

	/**
	 * @param array  $types
	 * @param array  $args
	 * @param string $operator
	 *
	 * @return array
	 */
	public function translateProfileTypesList( $types, $args, $operator ) {
		if ( empty( $types ) ) {
			return $types;
		}

		foreach ( $types as $key => $type ) {
			$profileTypeID = $this->getProfileTypeId( $key );
			if ( $profileTypeID ) {
				$package = [
					'kind' => self::PROFILE_TYPE_KIND,
					'name' => $profileTypeID,
				];

				if ( isset( $type->labels ) && is_array( $type->labels ) ) {
					if ( isset( $type->labels['name'] ) ) {
						$type->labels['name'] = $this->translateString(
							$type->labels['name'],
							'plural_label',
							$package
						);
					}

					if ( isset( $type->labels['singular_name'] ) ) {
						$type->labels['singular_name'] = $this->translateString(
							$type->labels['singular_name'],
							'singular_label',
							$package
						);
					}
				}

				$types[ $key ] = $type;
			}
		}
		return $types;
	}

	/**
	 * @param mixed  $value
	 * @param int    $postID
	 * @param string $metaKey
	 * @param bool   $single
	 *
	 * @return mixed
	 */
	public function translateProfileTypeMetaFields( $value, $postID, $metaKey, $single ) {
		if ( is_admin() || ! array_key_exists( $metaKey, self::CUSTOM_FIELDS ) || get_post_type( $postID ) !== self::PROFILE_TYPE_POST_TYPE ) {
			return $value;
		}

		remove_filter( 'get_post_metadata', [ $this, 'translateProfileTypeMetaFields' ], 10 );
		$originalValue = get_post_meta( $postID, $metaKey, true );
		add_filter( 'get_post_metadata', [ $this, 'translateProfileTypeMetaFields' ], 10, 4 );

		if ( ! $originalValue ) {
			return $value;
		}

		$package = [
			'kind' => self::PROFILE_TYPE_KIND,
			'name' => $postID,
		];

		$stringName = self::CUSTOM_FIELDS[ $metaKey ] ?? null;
		if ( $stringName ) {
			return $this->translateString( $originalValue, $stringName, $package );
		}

		return $value;
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
	 * @param int    $metaID
	 * @param int    $termID
	 * @param string $metaKey
	 * @param mixed  $metaValue
	 */
	public function registerProfileTypeTermStrings( $metaID, $termID, $metaKey, $metaValue ) {
		if ( ! in_array( $metaKey, array_keys( self::TERM_FIELDS ), true ) ) {
			return;
		}

		$term = get_term( $termID );
		if ( ! $term || self::PROFILE_TYPE_TAXONOMY !== $term->taxonomy ) {
			return;
		}

		$package = [
			'kind'      => self::PROFILE_TYPE_KIND,
			'name'      => $termID,
			'title'     => $term->name,
			'edit_link' => get_edit_term_link( $termID, self::PROFILE_TYPE_TAXONOMY ),
			'view_link' => '',
		];

		do_action( 'wpml_start_string_package_registration', $package );

		foreach ( self::TERM_FIELDS as $metaKey => $stringName ) {
			$value = get_term_meta( $termID, $metaKey, true );
			if ( $value ) {
				$this->registerString( $value, $stringName, $package );
			}
		}

		do_action( 'wpml_delete_unused_package_strings', $package );
	}

	/**
	 * @param int      $postID
	 * @param \WP_Post $post
	 * @param bool     $update
	 */
	public function registerProfileTypeStrings( $postID, $post, $update ) {
		if ( self::PROFILE_TYPE_POST_TYPE !== $post->post_type || wp_is_post_revision( $postID ) ) {
			return;
		}

		$package = [
			'kind'      => self::PROFILE_TYPE_KIND,
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

			$this->registerString( $metaValue, $name, $package );
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
	 * @param int    $termID
	 * @param int    $ttID
	 * @param string $taxonomy
	 * @param mixed  $deletedTerm
	 */
	public function deleteProfileTypeTermStrings( $termID, $ttID, $taxonomy, $deletedTerm ) {
		if ( self::PROFILE_TYPE_TAXONOMY !== $taxonomy ) {
			return;
		}

		do_action(
			'wpml_delete_package',
			$termID,
			self::PROFILE_TYPE_KIND
		);
	}

	/**
	 * @param int $postID
	 */
	public function deleteProfileTypeStrings( $postID ) {
		if ( get_post_type( $postID ) !== self::PROFILE_TYPE_POST_TYPE ) {
			return;
		}

		do_action(
			'wpml_delete_package',
			$postID,
			self::PROFILE_TYPE_KIND
		);
	}

	/**
	 * @param string $memberType
	 *
	 * @return int
	 */
	protected function getProfileTypeId( $memberType ) {
		if ( bpml_is_buddyboss() ) {
			$args = [
				'post_type'  => self::PROFILE_TYPE_POST_TYPE,
				'meta_query' => [
					[
						'key'   => '_bp_member_type_key',
						'value' => $memberType,
					],
				],
			];

			$profile_type_query = new \WP_Query( $args );
			$posts              = $profile_type_query->posts;
			return $posts[0]->ID ?? 0;
		}

		$term = get_term_by( 'slug', $memberType, self::PROFILE_TYPE_TAXONOMY );
		return $term ? $term->term_id : 0;
	}
}

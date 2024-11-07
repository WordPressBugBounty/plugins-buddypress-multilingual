<?php
/**
 * Translates group and profile field labels.
 */
class BPML_XProfile implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	/**
	 * @var string
	 */
	private $context = \WPML\BuddyPress\Groups::TEXTDOMAIN;

	/**
	 * @var string
	 */
	private $field_string_prefix = 'profile field ';

	/**
	 * @var string
	 */
	private $group_string_prefix = 'profile group ';

	const PRIORITY_BEFORE_NAME_REPLACE = 9;
	const FIELD_TYPES_WITH_OPTIONS     = [ 'radio', 'checkbox', 'selectbox', 'multiselectbox' ];

	public function add_hooks() {

		add_action( 'bp_init', [ $this, 'bp_init' ] );

		// AJAX string registration.
		add_action( 'wp_ajax_bpml_register_fields', [ $this, 'ajax_register' ] );

		// Register actions.
		add_action( 'xprofile_fields_saved_field', [ $this, 'saved_field_action' ] );
		add_action( 'xprofile_fields_deleted_field', [ $this, 'deleted_field_action' ] );
		add_action( 'xprofile_groups_saved_group', [ $this, 'saved_group_action' ] );
		add_action( 'xprofile_groups_deleted_group', [ $this, 'deleted_group_action' ] );
		add_action( 'update_xprofile_field_meta', [ $this, 'update_alternate_name' ], 10, 4 );
		add_action( 'add_xprofile_field_meta', [ $this, 'add_alternate_name' ], 10, 3 );
		add_action( 'delete_xprofile_field_meta', [ $this, 'delete_alternate_name' ], 10, 3 );

		// Translation filters.
		add_filter( 'bp_get_the_profile_field_name', [ $this, 'translate_name' ], self::PRIORITY_BEFORE_NAME_REPLACE );
		add_filter( 'bp_get_the_profile_field_alternate_name', [ $this, 'translate_alternate_name' ] );
		add_filter( 'bp_get_the_profile_field_description', [ $this, 'translate_description' ] );
		add_filter( 'bp_xprofile_field_get_children', [ $this, 'translate_options' ] );
		add_filter( 'bp_get_the_profile_field_options_checkbox', [ $this, 'translate_checkbox' ], 0, 5 );
		add_filter( 'bp_get_the_profile_field_options_radio', [ $this, 'translate_radio' ], 0, 5 );
		add_filter( 'bp_get_the_profile_field_options_multiselect', [ $this, 'translate_multiselect_option' ], 0, 5 );
		add_filter( 'bp_get_the_profile_field_options_select', [ $this, 'translate_select_option' ], 0, 5 );
		add_filter( 'bp_get_the_profile_field_value', [ $this, 'translate_value_profile_view' ], 9, 2 );
		add_filter( 'bp_get_the_profile_group_name', [ $this, 'translate_group_name' ] );
		add_filter( 'bp_get_member_profile_data', [ $this, 'translate_data' ], 10, 2 );
	}

	public function bp_init() {
		// BP Profile Fields admin screen.
		if ( isset( $_GET['page'] ) && 'bp-profile-setup' === $_GET['page'] ) {  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// Scan needed check.
			if ( $this->is_scan_needed() ) {
				add_action( 'admin_notices', [ $this, 'scan_needed_warning' ] );
				wp_enqueue_script( 'bpml', BPML_RELPATH . '/js/admin.js', [ 'jquery' ], BPML_VERSION, true );
			}
		}
	}

	public function register_fields() {
		$groups = bp_xprofile_get_groups( [ 'fetch_fields' => true ] );
		if ( $groups ) {
			foreach ( $groups as $group ) {
				$this->saved_group_action( $group );
				if ( ! empty( $group->fields ) && is_array( $group->fields ) ) {
					foreach ( $group->fields as $field ) {
						$this->saved_field_action( $field );
						$meta = bp_xprofile_get_meta( $field->id, 'field', 'alternate_name', true );
						if ( $meta ) {
							$this->add_alternate_name( $field->id, 'alternate_name', $meta );
						}
					}
				}
			}
		}
	}

	/**
	 * @param BP_XProfile_Field $field
	 */
	public function saved_field_action( $field ) {
		// Happens that new field has no accesible 'id' property.
		if ( empty( $field->id ) ) {
			$field_id = xprofile_get_field_id_from_name( $field->name );
			if ( $field_id ) {
				$field->id = $field_id;
			} else {
				return;
			}
		}
		// Register name.
		if ( ! empty( $field->name ) ) {
			do_action(
				'wpml_register_single_string',
				$this->context,
				"{$this->field_string_prefix}{$field->id} name",
				stripslashes( $field->name )
			);
		}
		// Register description.
		if ( ! empty( $field->description ) ) {
			do_action(
				'wpml_register_single_string',
				$this->context,
				"{$this->field_string_prefix}{$field->id} description",
				stripslashes( $field->description )
			);
		}
		// Register options.
		if ( in_array( $field->type, self::FIELD_TYPES_WITH_OPTIONS, true ) ) {
			$bp_field = xprofile_get_field( $field->id );
			$options  = $bp_field->get_children();
			foreach ( $options as $option ) {
				if ( ! empty( $option->name ) ) {
					do_action(
						'wpml_register_single_string',
						$this->context,
						$this->sanitize_option_basename( $option, $field->id ) . ' name',
						stripslashes( $option->name )
					);
				}
				if ( ! empty( $option->description ) ) {
					do_action(
						'wpml_register_single_string',
						$this->context,
						$this->sanitize_option_basename( $option, $field->id ) . ' description',
						stripslashes( $option->description )
					);
				}
			}
		}
	}

	/**
	 * @param BP_XProfile_Field $field
	 */
	public function deleted_field_action( $field ) {
		if ( function_exists( 'icl_unregister_string' ) ) {
			// Unregister name.
			if ( ! empty( $field->name ) ) {
				icl_unregister_string( $this->context, "{$this->field_string_prefix}{$field->id} name" );
			}
			// Unregister description.
			if ( ! empty( $field->description ) ) {
				icl_unregister_string( $this->context, "{$this->field_string_prefix}{$field->id} description" );
			}
			// Unregister options.
			if ( in_array( $field->type, self::FIELD_TYPES_WITH_OPTIONS, true ) ) {
				$bp_field = xprofile_get_field( $field->id );
				$options  = $bp_field->get_children();
				foreach ( $options as $option ) {
					if ( ! empty( $option->name ) ) {
						icl_unregister_string( $this->context, $this->sanitize_option_basename( $option, $field->id ) . ' name' );
					}
					if ( ! empty( $option->description ) ) {
						icl_unregister_string( $this->context, $this->sanitize_option_basename( $option, $field->id ) . ' description' );
					}
				}
			}
		}
	}

	/**
	 * @param BP_XProfile_Group $group
	 */
	public function saved_group_action( $group ) {
		// Register name.
		if ( ! empty( $group->name ) ) {
			do_action( 'wpml_register_single_string', $this->context, "{$this->group_string_prefix}{$group->id} name", $group->name );
		}
		// Register description.
		if ( ! empty( $group->description ) ) {
			do_action( 'wpml_register_single_string', $this->context, "{$this->group_string_prefix}{$group->id} description", $group->description );
		}
	}

	/**
	 * @param BP_XProfile_Group $group
	 */
	public function deleted_group_action( $group ) {
		if ( function_exists( 'icl_unregister_string' ) ) {
			// Unregister name.
			if ( ! empty( $group->name ) ) {
				icl_unregister_string( $this->context, "{$this->group_string_prefix}{$group->id} name" );
			}
			// Unregister description.
			if ( ! empty( $group->description ) ) {
				icl_unregister_string( $this->context, "{$this->group_string_prefix}{$group->id} description" );
			}
		}
	}

	/**
	 * @param int    $meta_id
	 * @param int    $field_id
	 * @param string $key
	 * @param string $value
	 */
	public function update_alternate_name( $meta_id, $field_id, $key, $value ) {
		$this->add_alternate_name( $field_id, $key, $value );
	}

	/**
	 * @param int    $field_id
	 * @param string $key
	 * @param string $value
	 */
	public function add_alternate_name( $field_id, $key, $value ) {
		if ( 'alternate_name' === $key && $value ) {
			do_action( 'wpml_register_single_string', $this->context, "{$this->field_string_prefix}{$field_id} alternate name", stripslashes( $value ) );
		}
	}

	/**
	 * @param array  $meta_ids
	 * @param int    $field_id
	 * @param string $key
	 */
	public function delete_alternate_name( $meta_ids, $field_id, $key ) {
		if ( function_exists( 'icl_unregister_string' ) ) {
			if ( 'alternate_name' === $key ) {
				icl_unregister_string( $this->context, "{$this->field_string_prefix}{$field_id} alternate name" );
			}
		}
	}

	/**
	 * @param string $value
	 * @param string $name
	 *
	 * @return string
	 */
	private function translate( $value, $name ) {
		return stripslashes( apply_filters( 'wpml_translate_single_string', $value, $this->context, $name ) );
	}

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	public function translate_name( $name ) {
		global $field;

		return $this->translate( $name, $this->field_string_prefix . $field->id . ' name' );
	}

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	public function translate_alternate_name( $name ) {
		global $field;

		return $this->translate( $name, $this->field_string_prefix . $field->id . ' alternate name' );
	}

	/**
	 * @param string $description
	 *
	 * @return string
	 */
	public function translate_description( $description ) {
		global $field;

		return $this->translate( $description, $this->field_string_prefix . $field->id . ' description' );
	}

	/**
	 * @param array $options
	 *
	 * @return array
	 */
	public function translate_options( $options ) {
		global $field;

		foreach ( $options as &$option ) {
			// Just translate description. Name can messup forms.
			if ( ! empty( $option->description ) ) {
				$option->description = $this->translate( $option->description, $this->sanitize_option_basename( $option, $field->id ) . ' description' );
			}
		}

		return $options;
	}

	/**
	 * @param object $option
	 * @param int    $field_id
	 *
	 * @return string
	 */
	private function translate_option_name( $option, $field_id ) {
		if ( ! empty( $option->name ) ) {
			return $this->translate( $option->name, $this->sanitize_option_basename( $option, $field_id ) . ' name' );
		}

		return isset( $option->name ) ? $option->name : '';
	}

	/**
	 * @param object $option
	 * @param int    $field_id
	 *
	 * @return string
	 */
	private function sanitize_option_basename( $option, $field_id ) {
		$sanitized_string = bpml_sanitize_string_name( $option->name, 30 );

		return $this->field_string_prefix . $field_id . " - option '" . $sanitized_string . "'";
	}

	/**
	 * Adjusts HTML output for radio field.
	 *
	 * @param string $html     Option tag for current value being rendered.
	 * @param object $option   Current option being rendered for.
	 * @param int    $field_id ID of the field object being rendered.
	 * @param string $selected Current selected value.
	 * @param string $k        Current index in the foreach loop.
	 *
	 * @return string
	 */
	public function translate_radio( $html, $option, $field_id, $selected, $k ) {
		$label = $this->translate_option_name( $option, $field_id );

		return preg_replace( "/\>{$option->name}\<\/label\>/", ">{$label}</label>", $html, 1 );
	}

	/**
	 * Adjusts HTML output for checkbox field.
	 *
	 * @param string $html     Option tag for current value being rendered.
	 * @param object $option   Current option being rendered for.
	 * @param int    $field_id ID of the field object being rendered.
	 * @param string $selected Current selected value.
	 * @param string $k        Current index in the foreach loop.
	 *
	 * @return string
	 */
	public function translate_checkbox( $html, $option, $field_id, $selected, $k ) {
		return $this->translate_radio( $html, $option, $field_id, $selected, $k );
	}

	/**
	 * Adjusts HTML output for select field.
	 *
	 * @param string $html     Option tag for current value being rendered.
	 * @param object $option   Current option being rendered for.
	 * @param int    $field_id ID of the field object being rendered.
	 * @param string $selected Current selected value.
	 * @param string $k        Current index in the foreach loop.
	 *
	 * @return string
	 */
	public function translate_select_option( $html, $option, $field_id, $selected, $k ) {
		$label = $this->translate_option_name( $option, $field_id );
		return preg_replace( '/"\>(.*)\<\/option\>/', "\">{$label}</option>", $html );
	}

	/**
	 * Adjusts HTML output for multiselect field.
	 *
	 * @param string $html     Option tag for current value being rendered.
	 * @param object $option   Current option being rendered for.
	 * @param int    $field_id ID of the field object being rendered.
	 * @param string $selected Current selected value.
	 * @param string $k        Current index in the foreach loop.
	 *
	 * @return string
	 */
	public function translate_multiselect_option( $html, $option, $field_id, $selected, $k ) {
		return $this->translate_select_option( $html, $option, $field_id, $selected, $k );
	}

	/**
	 * Filters field values on profile view template.
	 *
	 * @param string $value
	 * @param string $field_type
	 *
	 * @return string
	 */
	public function translate_value_profile_view( $value, $field_type ) {
		global $field;

		// Only for fields with options.
		if ( in_array( $field_type, self::FIELD_TYPES_WITH_OPTIONS, true ) ) {
			$bp_field = xprofile_get_field( $field->id );
			$options  = $bp_field->get_children();
			switch ( $field_type ) {
				case 'radio':
				case 'selectbox':
					$_value = false;
					foreach ( $options as $option ) {
						if ( isset( $option->name ) && $option->name === $field->data->value ) {
							$_value = $this->translate_option_name( $option, $field->id );
						}
					}
					if ( $_value ) {
						// Expected format is search link.
						$value = str_replace( ">{$field->data->value}</a>", ">{$_value}</a>", $value, $count );
						if ( ! $count ) {
							$value = $_value;
						}
					}
					break;

				case 'multiselectbox':
				case 'checkbox':
					foreach ( $options as $option ) {
						$_value = $this->translate_option_name( $option, $field->id );
						// Expected format is search link.
						$value = str_replace( ">{$option->name}</a>", ">{$_value}</a>", $value, $count );
						// CSV list.
						if ( ! $count && strpos( $value, $option->name ) !== false ) {
							$_ex_values = explode( ',', $value );
							if ( ! empty( $_ex_values ) ) {
								foreach ( $_ex_values as &$v ) {
									if ( trim( $v ) === $option->name ) {
										$v = $_value;
									}
								}
								$value = implode( ', ', $_ex_values );
							}
						}
					}
					break;

				default:
					break;
			}
		}

		return $value;
	}

	/**
	 * @param string $group_name
	 *
	 * @return string
	 */
	public function translate_group_name( $group_name ) {
		global $wpdb, $bp;

		$cache_key = 'bpml_xprofile_group_id_by_name_' . md5( $group_name );
		$group_id  = wp_cache_get( $cache_key );
		if ( false === $group_id ) {
			$group_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$bp->profile->table_name_groups} WHERE name=%s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$group_name
				)
			);
			wp_cache_set( $cache_key, $group_id );
		}

		return $group_id ? $this->translate( $group_name, $this->group_string_prefix . $group_id . ' name' ) : $group_name;
	}

	/**
	 * Translates radio/checkbox values in member profile data.
	 *
	 * @param array $data
	 * @param array $args
	 *
	 * @return array
	 */
	public function translate_data( $data, $args ) {
		$field_id = xprofile_get_field_id_from_name( $args['field'] );
		if ( $field_id ) {
			$field = xprofile_get_field( $field_id, null, false );
			if ( $field && in_array( $field->type_obj->field_obj->type, self::FIELD_TYPES_WITH_OPTIONS, true ) ) {
				$data = $this->translate_option_name( (object) [ 'name' => $data ], $field_id );
			}
		}

		return $data;
	}

	protected function verify_nonce() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'bpml-xprofile' ) ) {
			die( '0' );
		}
		return true;
	}

	protected function is_scan_needed() {
		if ( function_exists( 'icl_st_is_registered_string' ) ) {
			$groups = bp_xprofile_get_groups( [ 'fetch_fields' => true ] );
			foreach ( $groups as $group ) {
				$is_registered = icl_st_is_registered_string( $this->context, "{$this->group_string_prefix}{$group->id} name" );
				if ( ! $is_registered ) {
					return true;
				}
				if ( ! empty( $group->fields ) && is_array( $group->fields ) ) {
					foreach ( $group->fields as $field ) {
						$is_registered = icl_st_is_registered_string( $this->context, "{$this->field_string_prefix}{$field->id} name" );
						if ( ! $is_registered ) {
							return true;
						}
						$meta = bp_xprofile_get_meta( $field->id, 'field', 'alternate_name', true );
						if ( $meta ) {
							$is_registered = icl_st_is_registered_string( $this->context, "{$this->field_string_prefix}{$field->id} alternate name" );
							if ( ! $is_registered ) {
								return true;
							}
						}
					}
				}
			}
		}
		return false;
	}

	public function scan_needed_warning() {
		echo '<div class="updated error"><p>'
			. esc_html__( 'Buddypress Multilingual: some profile fields are not registered for translation', 'bpml' )
			. '&nbsp;<a class="button edit js-bpml-register-fields" href="javascript:void(0)" data-bpml="nonce='
			. esc_html( wp_create_nonce( 'bpml-xprofile' ) )
			. '&action=bpml_register_fields">'
			. esc_html__( 'Register fields', 'bpml' ) . '</a>'
			. '</p></div>';
	}

	public function ajax_register() {
		$response = '0';
		if ( $this->verify_nonce() ) {
			$this->register_fields();
			$response = __( 'Fields registered', 'bpml' );
		}
		die( esc_html( $response ) );
	}

}

<?php
/**
 * Enables BP multilingual components on frontend using various filters.
 */
class BPML_Filters implements \IWPML_Backend_Action, \IWPML_Frontend_Action {
	/**
	 * @var array|null
	 */
	private $ls_languages;

	public function add_hooks() {
		// Filter BP AJAX URL (add query args 'lang' and '_bpml_ac').
		add_filter( 'bp_core_ajax_url', [ $this, 'core_ajax_url_filter' ] );
		// Filter language switcher.
		add_filter( 'icl_ls_languages', [ $this, 'icl_ls_languages_filter' ], 99 );
		// Adjust BP pages IDs.
		add_filter( 'bp_core_get_directory_page_ids', [ $this, 'filter_page_ids' ], 0 );
		// WPML Convert URL.
		add_filter( 'bp_core_get_root_domain', [ $this, 'core_get_root_domain_filter' ], 0 );
		add_filter( 'bp_uri', [ $this, 'uri_filter' ], 0 );
		// Remove WPML post availability.
		add_action( 'bp_ready', [ $this, 'remove_wpml_post_availability_hook' ] );

		add_action(
			'bp_init',
			function() {
				if ( bp_is_activity_component() || bp_is_groups_component() || has_filter( 'bpml_redirection_page_id' ) ) {
					add_filter( 'parse_query', [ $this, 'wpml_fix_redirection' ], 5 );
				}
			},
			999
		);

		// Add hooks related to the forum feature
		$this->add_forum_hooks();

	}

	public function add_forum_hooks() {
		if ( apply_filters( 'wpml_is_translated_post_type', null, 'forum' ) ) {
			add_filter( 'bbp_get_forum_id', [ $this, 'convert_post_id' ], 10, 2 );
			add_filter( 'bbp_get_topic_forum_id', [ $this, 'convert_post_id' ], 10, 2 );
			add_filter( 'bbp_get_reply_forum_id', [ $this, 'convert_post_id' ], 10, 2 );
		}
		if ( apply_filters( 'wpml_is_translated_post_type', null, 'topic' ) ) {
			add_filter( 'bbp_get_topic_id', [ $this, 'convert_post_id' ], 10, 2 );
			add_filter( 'bbp_get_reply_topic_id', [ $this, 'convert_post_id' ], 10, 2 );
		}
		if ( apply_filters( 'wpml_is_translated_post_type', null, 'reply' ) ) {
			add_filter( 'bbp_get_reply_to', [ $this, 'convert_post_id' ], 10, 2 );
			add_filter( 'bbp_get_reply_id', [ $this, 'convert_post_id' ], 10, 2 );
			add_filter( 'bbp_get_reply_to_id', [ $this, 'convert_post_id' ], 10, 2 );
		}
	}

	/**
	 * Filters site_url() calls.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	public function site_url_filter( $url ) {
		global $sitepress;
		return rtrim( $sitepress->convert_url( $url ), '/' );
	}

	/**
	 * Filters BuddyPress root domain.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	public function core_get_root_domain_filter( $url ) {
		return $this->site_url_filter( $url );
	}

	/**
	 * Filters bp_uri.
	 *
	 * This URI is important for BuddyPress.
	 * By that it determines some components and actions.
	 * We remove language component so BP can determine things right.
	 *
	 * @todo Review regex.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	public function uri_filter( $url ) {
		if ( bpml_is_language_per_domain() ) {
			return $url;
		}
		$language = apply_filters( 'wpml_current_language', null );
		return preg_replace( '/(\/|^)' . $language . '\//', '$1', $url, 1 );
	}

	/**
	 * WPML language switcher filter.
	 *
	 * Appends BP URI components to language switcher base URLs
	 * and fixes bug with $wp_query->queried_object_id provoked by BP.
	 *
	 * @param array $languages
	 *
	 * @return array
	 */
	public function icl_ls_languages_filter( $languages ) {
		global $sitepress, $bp, $wp_query;

		if ( ! bp_is_blog_page() ) {

			if ( ! is_null( $this->ls_languages ) ) {
				return $this->ls_languages;
			}

			// Set page.
			if ( ! empty( $wp_query->queried_object_id ) && get_post_type( $wp_query->queried_object_id ) === 'page' ) {
				$page = get_post( $wp_query->queried_object_id );
			} else {
				$pagename = $bp->unfiltered_uri[ $bp->unfiltered_uri_offset ];
				$args     = [
					'name'           => $pagename,
					'post_type'      => 'page',
					'posts_per_page' => 1,
				];
				$posts    = get_posts( $args );
				$page     = array_shift( $posts );
			}

			if ( ! empty( $page->ID ) ) {
				/*
				* If languages are empty (WPML failed in setting language switcher data)
				* re-create language switcher data.
				*
				* Only case so far known is when WP_Query queried_object is messed up by BP.
				* BP sets queried object to be BP content type, but it's fake WP_Post without ID.
				*/
				// @todo Add persistent message for admin to report and mark as deprecated
				if ( empty( $languages )
						&& method_exists( $sitepress, 'set_wp_query' )
						&& method_exists( $sitepress, 'get_ls_languages' ) ) {

					// Clone original $wp_query.
					$_wp_query = clone $wp_query;
					// Fix query.
					$wp_query->queried_object_id = $page->ID;
					$wp_query->queried_object    = $page;
					$sitepress->set_wp_query();
					remove_filter( 'icl_ls_languages', [ $this, 'icl_ls_languages_filter' ], 99 );
					// Re-create language switcher data.
					$languages = $sitepress->get_ls_languages();
					add_filter( 'icl_ls_languages', [ $this, 'icl_ls_languages_filter' ], 99 );
					// Restore $wp_query.
					unset( $wp_query );
					$wp_query = clone $_wp_query; // phpcs:ignore WordPress.Variables.GlobalVariables.OverrideProhibited
					unset( $_wp_query );
					$sitepress->set_wp_query();
				}

				/*
				* Append all URI components after base component.
				* For example member screen:
				* {http://localhost/es/miembros}/{keir/profile/view/}
				*/
				if ( is_array( $languages ) && get_option( 'permalink_structure' ) !== '' ) {
					$unfiltered_uri   = $bp->unfiltered_uri;
					$offset           = intval( $bp->unfiltered_uri_offset ) + 1;
					$append_array     = array_slice( $unfiltered_uri, $offset );
					$append           = implode( '/', $append_array );
					$current_language = apply_filters( 'wpml_current_language', null );

					$add_get_parameters = [];
					$parameters_to_copy = apply_filters(
						'icl_lang_sel_copy_parameters',
						array_map(
							'trim',
							explode( ',', wpml_get_setting_filter( '', 'icl_lang_sel_copy_parameters' ) )
						)
					);

					if ( $parameters_to_copy ) {
						foreach ( $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
							if ( in_array( $k, $parameters_to_copy, true ) ) {
								$add_get_parameters[ $k ] = $v;
							}
						}
					}

					foreach ( $languages as $code => &$language ) {
						$translated_page_id = apply_filters( 'wpml_object_id', $page->ID, 'page', false, $code );
						if ( $translated_page_id ) {
							do_action( 'wpml_switch_language', $code );
							$page_permalink = untrailingslashit( get_permalink( $translated_page_id ) );
							do_action( 'wpml_switch_language', $current_language );
							$language['url'] = user_trailingslashit( "{$page_permalink}/{$append}" );
						}

						if ( $add_get_parameters ) {
							$language['url'] = add_query_arg( $add_get_parameters, $language['url'] );
						}
					}
				}
			}
			$this->ls_languages = $languages;
		}

		return $languages;
	}

	public function remove_wpml_post_availability_hook() {
		if ( ! bp_is_blog_page() ) {
			add_filter( 'wpml_ls_post_alternative_languages', '__return_empty_string' );
		}
	}

	public function core_ajax_url_filter( $url ) {
		$url = add_query_arg(
			[
				'lang'        => apply_filters( 'wpml_current_language', null ),
				'bpml_filter' => 'true',
			],
			$url
		);
		return $url;
	}

	public function filter_page_ids( $page_ids = [] ) {
		foreach ( $page_ids as $k => &$page_id ) {
			$page_id = apply_filters( 'wpml_object_id', $page_id, 'page', true );
		}
		return $page_ids;
	}

	/**
	 * @param int $post_id Post ID
	 *
	 * @return int Converted post ID
	 */
	public function convert_post_id( $post_id ) {
		return apply_filters( 'wpml_object_id', $post_id, get_post_type( $post_id ), true );
	}

	/**
	 * Fixes redirection caused by best match.
	 * WPML_Name_Query_Filter_Translated::select_best_match()
	 *
	 * Affected views:
	 * 1. Profile activity /members/admin/activity/ (Profile > Activity)
	 * 2. Single activity /activity/p/6/
	 * 3. Group members /groups/group-name/members/
	 * 4. Profile groups /members/admin/groups/ (Profile > Groups)
	 * 5. Profile group activity /members/admin/activity/groups/ (Profile > sub Groups)
	 *
	 * @param WP_Query $q
	 *
	 * @return WP_Query
	 */
	public function wpml_fix_redirection( $q ) {
		if ( ! defined( 'DOING_AJAX' ) && ! bp_is_blog_page()
			 && (bool) $q->get( 'page_id' ) === false
			 && (bool) $q->get( 'pagename' ) === true ) {

			$bp_current_component = bp_current_component();
			$bp_current_action    = bp_current_action();
			$bp_pages             = bp_core_get_directory_pages();

			if ( 'activity' === $bp_current_component && 'just-me' === $bp_current_action ) {
				if ( isset( $bp_pages->members->id ) ) {
					$q->set( 'page_id', $bp_pages->members->id );
				}
			} elseif ( 'activity' === $bp_current_component && ( 'p' === $bp_current_action || is_numeric( $bp_current_action ) ) ) {
				if ( isset( $bp_pages->members->id ) ) {
					$q->set( 'page_id', $bp_pages->members->id );
				}
			} elseif ( 'groups' === $bp_current_component && 'members' === $bp_current_action ) {
				if ( isset( $bp_pages->groups->id ) ) {
					$q->set( 'page_id', $bp_pages->groups->id );
				}
			} elseif ( 'groups' === $bp_current_component && 'my-groups' === $bp_current_action ) {
				if ( isset( $bp_pages->members->id ) ) {
					$q->set( 'page_id', $bp_pages->members->id );
				}
			} elseif ( 'activity' === $bp_current_component && 'groups' === $bp_current_action ) {
				if ( isset( $bp_pages->members->id ) ) {
					$q->set( 'page_id', $bp_pages->members->id );
				}
			} else {
				$page_id = apply_filters( 'bpml_redirection_page_id', null, $bp_current_component, $bp_current_action, $bp_pages );
				if ( $page_id ) {
					$q->set( 'page_id', $page_id );
				}
			}
		}

		return $q;
	}

}

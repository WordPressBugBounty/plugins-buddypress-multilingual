<?php

use WPML\FP\Obj;

class BPML_Filters implements \IWPML_Backend_Action, \IWPML_Frontend_Action {
	private $ls_languages;

	public function add_hooks() {
		add_filter( 'bp_core_ajax_url', [ $this, 'core_ajax_url_filter' ] );
		add_filter( 'icl_ls_languages', [ $this, 'icl_ls_languages_filter' ], 99 );
		add_filter( 'bp_core_get_directory_page_ids', [ $this, 'filter_page_ids' ], 0 );
		add_filter( 'bp_core_get_root_domain', [ $this, 'core_get_root_domain_filter' ], 0 );
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

		$this->add_forum_hooks();

		add_action( 'init', [ $this, 'makeDirectoryPagesNonPersistent' ], 1 );

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

	public function site_url_filter( $url ) {
		global $sitepress;
		return rtrim( $sitepress->convert_url( $url ), '/' );
	}

	public function core_get_root_domain_filter( $url ) {
		return $this->site_url_filter( $url );
	}

	public function icl_ls_languages_filter( $languages ) {
		global $sitepress, $bp, $wp_query;

		if ( ! bp_is_blog_page() ) {

			if ( ! is_null( $this->ls_languages ) ) {
				return $this->ls_languages;
			}

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
				$defaultLanguage = apply_filters( 'wpml_default_language', null );
				$bpPages         = bp_core_get_directory_pages();
				$bpComponentPage = null;

				$recreateLanguages     = false;
				$recreateLanguagesArgs = [];

				if ( empty( $languages ) ) {
					$recreateLanguages = true;
				}

				if ( (int) Obj::path( ['activity', 'id'], $bpPages ) === $page->ID ) {
					$bpComponentPage = (int) $bpPages->activity->id;
				}

				if ( (int) Obj::path( ['members', 'id'], $bpPages ) === $page->ID ) {
					$bpComponentPage = (int) $bpPages->members->id;
				}

				if ( (int) Obj::path( ['groups', 'id'], $bpPages ) === $page->ID ) {
					$bpComponentPage = (int) $bpPages->groups->id;
				}

				if ( (int) Obj::path( ['forums', 'id'], $bpPages ) === $page->ID ) {
					$bpComponentPage = (int) $bpPages->forums->id;
				}

				if ( $bpComponentPage ) {
					$recreateLanguages     = true;
					$recreateLanguagesArgs = [ 'skip_missing' => false ];
				}

				if ( $recreateLanguages
						&& method_exists( $sitepress, 'set_wp_query' )
						&& method_exists( $sitepress, 'get_ls_languages' ) ) {

					$_wp_query = clone $wp_query;
					$wp_query->queried_object_id = $page->ID;
					$wp_query->queried_object    = $page;
					$sitepress->set_wp_query();
					remove_filter( 'icl_ls_languages', [ $this, 'icl_ls_languages_filter' ], 99 );
					$languages = $sitepress->get_ls_languages( $recreateLanguagesArgs );
					add_filter( 'icl_ls_languages', [ $this, 'icl_ls_languages_filter' ], 99 );
					unset( $wp_query );
					$wp_query = clone $_wp_query;
					unset( $_wp_query );
					$sitepress->set_wp_query();
				}

				if ( is_array( $languages ) && get_option( 'permalink_structure' ) !== '' ) {
					$unfiltered_uri = $bp->unfiltered_uri;
					$offset         = intval( $bp->unfiltered_uri_offset ) + 1;
					$append_array   = array_slice( $unfiltered_uri, $offset );
					$append         = implode( '/', $append_array );

					$add_get_parameters = [];
					$parameters_to_copy = apply_filters(
						'icl_lang_sel_copy_parameters',
						array_map(
							'trim',
							explode( ',', wpml_get_setting_filter( '', 'icl_lang_sel_copy_parameters' ) )
						)
					);

					if ( $parameters_to_copy ) {
						foreach ( $_GET as $k => $v ) {
							if ( in_array( $k, $parameters_to_copy, true ) ) {
								$add_get_parameters[ $k ] = $v;
							}
						}
					}

					foreach ( $languages as $code => &$language ) {
						$translated_page_id = apply_filters( 'wpml_object_id', $page->ID, 'page', false, $code );
						if ( $translated_page_id ) {
							$page_permalink  = untrailingslashit( $this->get_permalink_in_language( $translated_page_id, $code ) );
							$language['url'] = user_trailingslashit( "{$page_permalink}/{$append}" );
						} else if ( $bpComponentPage ) {
							$page_permalink  = $this->get_permalink_in_language( $bpComponentPage, $defaultLanguage );
							$page_permalink  = untrailingslashit( $sitepress->convert_url( $page_permalink, $code ) );
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

	private function get_permalink_in_language( $page_id, $language ) {
		do_action( 'wpml_switch_language', $language );

		try {
			return get_permalink( $page_id );
		} finally {
			do_action( 'wpml_switch_language', null );
		}
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

	public function convert_post_id( $post_id ) {
		return apply_filters( 'wpml_object_id', $post_id, get_post_type( $post_id ), true );
	}

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

	public function makeDirectoryPagesNonPersistent() {
		wp_cache_add_non_persistent_groups( 'bp_pages' );
		wp_cache_delete( 'directory_pages', 'bp_pages' );
	}

}

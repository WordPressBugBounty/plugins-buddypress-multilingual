<?php

namespace WPML\BuddyPress;

use WPML\FP\Obj;

class Albums implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const TEXTDOMAIN = 'Buddypress Multilingual';

	private $processedOnSave     = [];
	private $replacedTitlesOnSave = [];

	public function add_hooks() {
		add_action( 'bp_media_album_before_save', [ $this, 'beforeSave' ], 1 );
		add_action( 'bp_media_album_after_save', [ $this, 'afterSave' ], 1 );

		add_filter( 'bp_get_album_title', [ $this, 'translateAlbumTitle' ], 1, 2 );
		add_filter( 'bp_media_album_prefetch_object_data', [ $this, 'translateAlbumsData' ] );
	}

	public static function getName( $albumId, $field ) {
		return sprintf( 'Media album #%d %s', $albumId, $field );
	}

	public function beforeSave( $album ) {
		$albumId = Obj::prop( 'id', $album );
		if ( empty ( $albumId ) ) {
			return;
		}

		$title      = Obj::prop( 'title', $album );
		$stringName = self::getName( $albumId, 'title' );

		$stringData = array(
			'context' => self::TEXTDOMAIN,
			'name'    => $stringName,
		);

		$stringId = apply_filters( 'wpml_string_id', null, $stringData );
		if ( ! $stringId ) {
			return;
		}

		$currentLanguage = apply_filters( 'wpml_current_language', null );
		$stringLanguage  = apply_filters( 'wpml_get_string_language', null, self::TEXTDOMAIN, $stringName );

		if ( $stringLanguage === $currentLanguage ) {
			$this->registerTitle( $albumId, $title, $currentLanguage );
		} else {
			if ( $this->canManageAlbum( $album ) ) {
				$this->registerTitleTranslation( $stringId, Sanitizer::plainText( wp_unslash( $title ) ), $currentLanguage, $albumId );
			}
			$this->replaceTitleWithOriginal( $album, $albumId, $title );
		}

		$this->processedOnSave[] = $albumId;
	}

	public function afterSave( $album ) {
		$albumId = Obj::prop( 'id', $album );
		if ( empty ( $albumId ) ) {
			return;
		}

		$title           = Obj::prop( 'title', $album );
		$currentLanguage = apply_filters( 'wpml_current_language', null );

		$this->registerTitle( $albumId, $title, $currentLanguage );
		$this->restoreTitle( $album, $albumId );

		bpml_refresh_language_caches( $currentLanguage );

		$this->processedOnSave[] = $albumId;
	}

	private function registerTitle( $albumId, $title, $stringLanguage ) {
		if ( in_array( $albumId, $this->processedOnSave ) ) {
			return;
		}

		do_action(
			'wpml_register_single_string',
			self::TEXTDOMAIN,
			self::getName( $albumId, 'title' ),
			Sanitizer::plainText( wp_unslash( $title ) ),
			true,
			$stringLanguage
		);
	}

	public static function currentUserCanManageById( $albumId ) {
		if ( ! get_current_user_id() ) {
			return false;
		}

		if ( ! class_exists( '\BP_Media_Album' ) ) {
			return false;
		}

		$album = new \BP_Media_Album( (int) $albumId );
		if ( empty( Obj::prop( 'id', $album ) ) ) {
			return false;
		}

		return ( new self() )->canManageAlbum( $album );
	}

	private function canManageAlbum( $album ) {
		$userId = get_current_user_id();
		if ( ! $userId ) {
			return false;
		}

		if ( function_exists( 'bp_album_user_can_edit' ) ) {
			return (bool) bp_album_user_can_edit( $album );
		}

		if ( function_exists( 'bp_current_user_can' ) && bp_current_user_can( 'bp_moderate' ) ) {
			return true;
		}

		$groupId = (int) Obj::prop( 'group_id', $album );
		if ( $groupId ) {
			return function_exists( 'groups_can_user_manage_albums' ) && groups_can_user_manage_albums( $userId, $groupId );
		}

		return (int) Obj::prop( 'user_id', $album ) === $userId;
	}

	private function registerTitleTranslation( $stringId, $title, $stringLanguage, $albumId ) {
		StringTranslationWrite::objectTranslation(
			$stringId,
			$stringLanguage,
			$title,
			'bp-media-album:' . $albumId,
			'frontend-album-owner'
		);
	}

	private function replaceTitleWithOriginal( &$album, $albumId, $title ) {
		global $wpdb;
		$tableName     = $this->getMediaAlbumsTable();
		$originalTitle = $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT title FROM {$tableName}
				WHERE id = %d
				",
				$albumId
			)
		);

		$this->replacedTitlesOnSave[ $albumId ] = $title;
		$album->title                           = $originalTitle;
	}

	private function getMediaAlbumsTable() {
		$bp = buddypress();
		return $bp->media->table_name_albums;
	}

	private function restoreTitle( &$album, $albumId ) {
		if ( isset( $this->replacedTitlesOnSave[ $albumId ] ) ) {
			$album->title = $this->replacedTitlesOnSave[ $albumId ];
		}
	}

	public function translateAlbumTitle( $title, $albumId = null ) {
		if ( ! $albumId ) {
			return $title;
		}

		$translation = apply_filters(
			'wpml_translate_single_string',
			$title ,
			self::TEXTDOMAIN,
			self::getName( $albumId, 'title' )
		);

		if ( $translation === $title ) {
			return $title;
		}

		return Sanitizer::plainText( $translation );
	}

	public function translateAlbumsData( $albums ) {
		foreach ( $albums as &$album ) {
			if ( ! isset( $album->id ) || ! isset( $album->title ) ) {
				continue;
			}
			$album->title = $this->translateAlbumTitle( $album->title, $album->id );
		}

		return $albums;
	}

}

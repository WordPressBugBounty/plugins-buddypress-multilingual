<?php

namespace WPML\BuddyPress;

use WPML\FP\Obj;

class Albums implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const TEXTDOMAIN = 'Buddypress Multilingual';

	private $processedOnSave     = [];
	private $replacedTitlesOnSave = [];

	public function add_hooks() {
		// Register strings and translations on frontend creation/edition.
		add_action( 'bp_media_album_before_save', [ $this, 'beforeSave' ], 1 );
		add_action( 'bp_media_album_after_save', [ $this, 'afterSave' ], 1 );

		add_filter( 'bp_get_album_title', [ $this, 'translateAlbumTitle' ], 1, 2 );
		add_filter( 'bp_media_album_prefetch_object_data', [ $this, 'translateAlbumsData' ] );
	}

	/**
	 * @param int|string $albumId
	 * @param string     $field
	 *
	 * @return string
	 */
	public static function getName( $albumId, $field ) {
		return sprintf( 'Media album #%d %s', $albumId, $field );
	}

	/**
	 * @param \BP_Media_Album $album Passed by reference.
	 */
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
			// Register string in current language.
			return;
		}

		$currentLanguage = apply_filters( 'wpml_current_language', null );
		$stringLanguage  = apply_filters( 'wpml_get_string_language', null, self::TEXTDOMAIN, $stringName );

		if ( $stringLanguage === $currentLanguage ) {
			// Register the struing as it might have changed.
			$this->registerTitle( $albumId, $title, $currentLanguage );
		} else {
			// Register the translationm for the string as it might have changed.
			$this->registerTitleTranslation( $stringId, $title, $currentLanguage );
			// Replace the title with the original one so it is not overriden with the translation.
			$this->replaceTitleWithOriginal( $album, $albumId, $title );
		}

		$this->processedOnSave[] = $albumId;
	}

	/**
	 * @param \BP_Media_Album $album Passed by reference.
	 */
	public function afterSave( $album ) {
		$albumId = Obj::prop( 'id', $album );
		if ( empty ( $albumId ) ) {
			return;
		}

		$title           = Obj::prop( 'title', $album );
		$currentLanguage = apply_filters( 'wpml_current_language', null );

		// If the ID was not processed by beforeSave, it means that it is a new item: register its title.
		$this->registerTitle( $albumId, $title, $currentLanguage );
		// Check if the title was replaced with the original one, and restore it to the current value.
		$this->restoreTitle( $album, $albumId );

		// Refresh cache and textdomain files.
		do_action( 'wpml_switch_language', $currentLanguage );

		$this->processedOnSave[] = $albumId;
	}

	/**
	 * @param int|string $albumId
	 * @param string     $title
	 * @param string     $stringLanguage
	 */
	private function registerTitle( $albumId, $title, $stringLanguage ) {
		if ( in_array( $albumId, $this->processedOnSave ) ) {
			return;
		}

		do_action(
			'wpml_register_single_string',
			self::TEXTDOMAIN,
			self::getName( $albumId, 'title' ),
			wp_unslash( $title ),
			true,
			$stringLanguage
		);
	}

	/**
	 * @param int    $stringId
	 * @param string $title
	 * @param string $stringLanguage
	 */
	private function registerTitleTranslation( $stringId, $title, $stringLanguage ) {
		icl_add_string_translation( $stringId, $stringLanguage, $title, ICL_TM_COMPLETE );
	}

	/**
	 * @param \BP_Media_Album $album Passed by reference.
	 * @param int|string      $albumId
	 * @param string          $title
	 */
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

	/**
	 * @return string
	 */
	private function getMediaAlbumsTable() {
		$bp = buddypress();
		/** @phpstan-ignore-next-line */
		return $bp->media->table_name_albums;
	}

	/**
	 * @param \BP_Media_Album $album Passed by reference.
	 * @param int|string      $albumId
	 */
	private function restoreTitle( &$album, $albumId ) {
		if ( isset( $this->replacedTitlesOnSave[ $albumId ] ) ) {
			$album->title = $this->replacedTitlesOnSave[ $albumId ];
		}
	}

	/**
	 * @param string     $title
	 * @param int|string $albumId
	 *
	 * @return string
	 */
	public function translateAlbumTitle( $title, $albumId = null ) {
		if ( ! $albumId ) {
			return $title;
		}

		return apply_filters(
			'wpml_translate_single_string',
			$title ,
			self::TEXTDOMAIN,
			self::getName( $albumId, 'title' )
		);
	}

	/**
	 * @param array<int,object|int> $albums
	 *
	 * @return array<int,object|int>
	 */
	public function translateAlbumsData( $albums ) {
		foreach ( $albums as &$album ) {
			if ( ! isset( $album->id ) || ! isset( $album->title ) ) {
				continue;
			}
			$album->title = apply_filters(
				'wpml_translate_single_string',
				$album->title ,
				self::TEXTDOMAIN,
				self::getName( $album->id, 'title' )
			);
		}

		return $albums;
	}

}

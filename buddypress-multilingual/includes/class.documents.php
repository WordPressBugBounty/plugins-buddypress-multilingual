<?php

namespace WPML\BuddyPress;

use WPML\FP\Obj;

class Documents implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const TEXTDOMAIN = 'Buddypress Multilingual';

	private $processedOnSave     = [];
	private $replacedTitlesOnSave = [];

	public function add_hooks() {
		// Register strings and translations on frontend creation/edition.
		add_action( 'bp_document_folder_before_save', [ $this, 'beforeSave' ], 1 );
		add_action( 'bp_document_folder_after_save', [ $this, 'afterSave' ], 1 );
		// Apply translations.
		add_filter( 'bp_get_folder_title', [ $this, 'translateFolderTitle' ], 1, 2 );
		add_filter( 'bp_get_folder_folder_title', [ $this, 'translateFolderTitle' ], 1, 2 );
		add_filter( 'bp_populate_folder_title', [ $this, 'translateFolderTitle' ], 1, 2 );
		add_filter( 'bp_document_folder_breadcrumb_element', [ $this, 'translateFolderBreadcrumb' ], 1 );
		add_filter( 'bp_document_folder_prefetch_object_data', [ $this, 'translateFoldersData' ] );
	}

	/**
	 * @param int|string $folderId
	 * @param string     $field
	 *
	 * @return string
	 */
	public static function getName( $folderId, $field ) {
		return sprintf( 'Document folder #%d %s', $folderId, $field );
	}

	/**
	 * @param \BP_Document_Folder $folder Passed by reference.
	 */
	public function beforeSave( $folder ) {
		$folderId = Obj::prop( 'id', $folder );
		if ( empty ( $folderId ) ) {
			return;
		}

		$title      = Obj::prop( 'title', $folder );
		$stringName = self::getName( $folderId, 'title' );

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
			$this->registerTitle( $folderId, $title, $currentLanguage );
		} else {
			// Register the translationm for the string as it might have changed.
			$this->registerTitleTranslation( $stringId, $title, $currentLanguage );
			// Replace the folder title with the original one so it is not overriden with the translation.
			$this->replaceTitleWithOriginal( $folder, $folderId, $title );
		}

		$this->processedOnSave[] = $folderId;
	}

	/**
	 * @param \BP_Document_Folder $folder Passed by reference.
	 */
	public function afterSave( $folder ) {
		$folderId = Obj::prop( 'id', $folder );
		if ( empty ( $folderId ) ) {
			return;
		}

		$title           = Obj::prop( 'title', $folder );
		$currentLanguage = apply_filters( 'wpml_current_language', null );

		// If the folder ID was not processed by beforeSaveFolder, it means that it is a new folder: register its title.
		$this->registerTitle( $folderId, $title, $currentLanguage );
		// Check if the folder title was replaced with the original one, and restore it to the current value.
		$this->restoreTitle( $folder, $folderId );

		// Refresh cache and textdomain files.
		do_action( 'wpml_switch_language', $currentLanguage );

		$this->processedOnSave[] = $folderId;
	}

	/**
	 * @param int|string $folderId
	 * @param string     $title
	 * @param string     $stringLanguage
	 */
	private function registerTitle( $folderId, $title, $stringLanguage ) {
		if ( in_array( $folderId, $this->processedOnSave ) ) {
			return;
		}

		do_action(
			'wpml_register_single_string',
			self::TEXTDOMAIN,
			self::getName( $folderId, 'title' ),
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
	 * @param \BP_Document_Folder $folder Passed by reference.
	 * @param int|string          $folderId
	 * @param string              $title
	 */
	private function replaceTitleWithOriginal( &$folder, $folderId, $title ) {
		global $wpdb;
		$tableName     = $this->getDocumentFoldersTable();
		$originalTitle = $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT title FROM {$tableName}
				WHERE id = %d
				",
				$folderId
			)
		);

		$this->replacedTitlesOnSave[ $folderId ] = $title;
		$folder->title                           = $originalTitle;
	}

	/**
	 * @return string
	 */
	private function getDocumentFoldersTable() {
		$bp = buddypress();
		/** @phpstan-ignore-next-line */
		return $bp->document->table_name_folder;
	}

	/**
	 * @param \BP_Document_Folder $folder Passed by reference.
	 * @param int|string          $folderId
	 */
	private function restoreTitle( &$folder, $folderId ) {
		if ( isset( $this->replacedTitlesOnSave[ $folderId ] ) ) {
			$folder->title = $this->replacedTitlesOnSave[ $folderId ];
		}
	}

	/**
	 * @param string     $title
	 * @param int|string $folderId
	 *
	 * @return string
	 */
	public function translateFolderTitle( $title, $folderId = null ) {
		if ( ! $folderId ) {
			return $title;
		}

		return apply_filters(
			'wpml_translate_single_string',
			$title ,
			self::TEXTDOMAIN,
			self::getName( $folderId, 'title' )
		);
	}

	/**
	 * @param array $element
	 *
	 * @return array
	 */
	public function translateFolderBreadcrumb( $element ) {
		if ( ! isset( $element['id'] ) || ! isset( $element['title'] ) ) {
			return $element;
		}

		$element['title'] = apply_filters(
			'wpml_translate_single_string',
			$element['title'] ,
			self::TEXTDOMAIN,
			self::getName( $element['id'], 'title' )
		);
		return $element;
	}

	/**
	 * @param array<int,object|int> $folders
	 *
	 * @return array<int,object|int>
	 */
	public function translateFoldersData( $folders ) {
		foreach ( $folders as &$folder ) {
			if ( ! isset( $folder->id ) || ! isset( $folder->title ) ) {
				continue;
			}
			$folder->title = apply_filters(
				'wpml_translate_single_string',
				$folder->title ,
				self::TEXTDOMAIN,
				self::getName( $folder->id, 'title' )
			);
		}

		return $folders;
	}

}

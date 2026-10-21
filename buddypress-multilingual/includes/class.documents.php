<?php

namespace WPML\BuddyPress;

use WPML\FP\Obj;

class Documents implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const TEXTDOMAIN = 'Buddypress Multilingual';

	private $processedOnSave     = [];
	private $replacedTitlesOnSave = [];

	public function add_hooks() {
		add_action( 'bp_document_folder_before_save', [ $this, 'beforeSave' ], 1 );
		add_action( 'bp_document_folder_after_save', [ $this, 'afterSave' ], 1 );
		add_filter( 'bp_get_folder_title', [ $this, 'translateFolderTitle' ], 1, 2 );
		add_filter( 'bp_get_folder_folder_title', [ $this, 'translateFolderTitle' ], 1, 2 );
		add_filter( 'bp_populate_folder_title', [ $this, 'translateFolderTitle' ], 1, 2 );
		add_filter( 'bp_document_folder_breadcrumb_element', [ $this, 'translateFolderBreadcrumb' ], 1 );
		add_filter( 'bp_document_folder_prefetch_object_data', [ $this, 'translateFoldersData' ] );
	}

	public static function getName( $folderId, $field ) {
		return sprintf( 'Document folder #%d %s', $folderId, $field );
	}

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
			return;
		}

		$currentLanguage = apply_filters( 'wpml_current_language', null );
		$stringLanguage  = apply_filters( 'wpml_get_string_language', null, self::TEXTDOMAIN, $stringName );

		if ( $stringLanguage === $currentLanguage ) {
			$this->registerTitle( $folderId, $title, $currentLanguage );
		} else {
			if ( $this->canManageFolder( $folder ) ) {
				$this->registerTitleTranslation( $stringId, Sanitizer::plainText( wp_unslash( $title ) ), $currentLanguage, $folderId );
			}
			$this->replaceTitleWithOriginal( $folder, $folderId, $title );
		}

		$this->processedOnSave[] = $folderId;
	}

	public function afterSave( $folder ) {
		$folderId = Obj::prop( 'id', $folder );
		if ( empty ( $folderId ) ) {
			return;
		}

		$title           = Obj::prop( 'title', $folder );
		$currentLanguage = apply_filters( 'wpml_current_language', null );

		$this->registerTitle( $folderId, $title, $currentLanguage );
		$this->restoreTitle( $folder, $folderId );

		bpml_refresh_language_caches( $currentLanguage );

		$this->processedOnSave[] = $folderId;
	}

	private function registerTitle( $folderId, $title, $stringLanguage ) {
		if ( in_array( $folderId, $this->processedOnSave ) ) {
			return;
		}

		do_action(
			'wpml_register_single_string',
			self::TEXTDOMAIN,
			self::getName( $folderId, 'title' ),
			Sanitizer::plainText( wp_unslash( $title ) ),
			true,
			$stringLanguage
		);
	}

	public static function currentUserCanManageById( $folderId ) {
		if ( ! get_current_user_id() ) {
			return false;
		}

		if ( ! class_exists( '\BP_Document_Folder' ) ) {
			return false;
		}

		$folder = new \BP_Document_Folder( (int) $folderId );
		if ( empty( Obj::prop( 'id', $folder ) ) ) {
			return false;
		}

		return ( new self() )->canManageFolder( $folder );
	}

	private function canManageFolder( $folder ) {
		$userId = get_current_user_id();
		if ( ! $userId ) {
			return false;
		}

		if ( function_exists( 'bp_folder_user_can_edit' ) ) {
			return (bool) bp_folder_user_can_edit( $folder );
		}

		if ( function_exists( 'bp_current_user_can' ) && bp_current_user_can( 'bp_moderate' ) ) {
			return true;
		}

		$groupId = (int) Obj::prop( 'group_id', $folder );
		if ( $groupId ) {
			return function_exists( 'groups_can_user_manage_document' ) && groups_can_user_manage_document( $userId, $groupId );
		}

		return (int) Obj::prop( 'user_id', $folder ) === $userId;
	}

	private function registerTitleTranslation( $stringId, $title, $stringLanguage, $folderId ) {
		StringTranslationWrite::objectTranslation(
			$stringId,
			$stringLanguage,
			$title,
			'bp-document-folder:' . $folderId,
			'frontend-folder-owner'
		);
	}

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

	private function getDocumentFoldersTable() {
		$bp = buddypress();
		return $bp->document->table_name_folder;
	}

	private function restoreTitle( &$folder, $folderId ) {
		if ( isset( $this->replacedTitlesOnSave[ $folderId ] ) ) {
			$folder->title = $this->replacedTitlesOnSave[ $folderId ];
		}
	}

	public function translateFolderTitle( $title, $folderId = null ) {
		if ( ! $folderId ) {
			return $title;
		}

		$translation = apply_filters(
			'wpml_translate_single_string',
			$title ,
			self::TEXTDOMAIN,
			self::getName( $folderId, 'title' )
		);

		if ( $translation === $title ) {
			return $title;
		}

		return Sanitizer::plainText( $translation );
	}

	public function translateFolderBreadcrumb( $element ) {
		if ( ! isset( $element['id'] ) || ! isset( $element['title'] ) ) {
			return $element;
		}

		$element['title'] = $this->translateFolderTitle( $element['title'], $element['id'] );
		return $element;
	}

	public function translateFoldersData( $folders ) {
		foreach ( $folders as &$folder ) {
			if ( ! isset( $folder->id ) || ! isset( $folder->title ) ) {
				continue;
			}
			$folder->title = $this->translateFolderTitle( $folder->title, $folder->id );
		}

		return $folders;
	}

}

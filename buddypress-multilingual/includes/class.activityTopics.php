<?php

namespace WPML\BuddyPress;

use WPML\FP\Obj;

class ActivityTopics implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const TEXTDOMAIN = 'Buddypress Multilingual';

	public function add_hooks() {
		add_action( 'bb_topic_after_added', [ $this, 'afterAdded' ] );
		add_action( 'bb_topic_after_updated', [ $this, 'afterUpdated' ] );

		add_filter( 'bb_topics_prefetch_object_data', [ $this, 'translateTopicsData' ] );
		add_filter( 'bb_get_activity_topic_data', [ $this, 'translateTopicData' ] );
	}

	/**
	 * @param object $topic
	 */
	public function afterAdded( $topic ) {
		$id      = Obj::prop( 'id', $topic );
		$topicId = Obj::prop( 'topic_id', $topic );

		if ( $id !== $topicId ) {
			// Only register when the ID matches the topicID, meaning it is a newly added topic.
			// When editing a topic and changing its name, we will reach here with different values:
			// we will manage that case in bb_topic_after_updated.
			return;
		}

		$this->registerTopicName( $topic );
	}

	/**
	 * @param object $topic
	 */
	public function afterUpdated( $topic ) {
		$id      = Obj::prop( 'id', $topic );
		$topicId = Obj::prop( 'topic_id', $topic );

		$this->registerTopicName( $topic );
	}

	/**
	 * @param object $topic
	 */
	private function registerTopicName( $topic ) {
		$topicId = Obj::prop( 'topic_id', $topic );
		$name    = Obj::prop( 'name', $topic );
		do_action(
			'wpml_register_single_string',
			self::TEXTDOMAIN,
			self::getName( $topicId, 'name' ),
			wp_unslash( $name )
		);
	}

	/**
	 * @param array<int,object|int|null> $topics
	 *
	 * @return array<int,object|int|null>
	 */
	public function translateTopicsData( $topics ) {
		foreach ( $topics as &$topic ) {
			$topic = $this->translateTopicData( $topic );
		}

		return $topics;
	}

	/**
	 * @param object|int|null $topic
	 *
	 * @return object|int|null
	 */
	public function translateTopicData( $topic ) {
		if ( ! isset( $topic->topic_id ) || ! isset( $topic->name ) ) {
			return $topic;
		}

		$topic->name = apply_filters(
			'wpml_translate_single_string',
			$topic->name,
			self::TEXTDOMAIN,
			self::getName( $topic->topic_id, 'name' )
		);

		return $topic;
	}

	/**
	 * @param int    $topicId
	 * @param string $field
	 *
	 * @return string
	 */
	public static function getName( $topicId, $field ) {
		return sprintf( 'Activity topic #%d %s', $topicId, $field );
	}

}

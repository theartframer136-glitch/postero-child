<?php

namespace QuadLayers\IGG\Models;

use QuadLayers\IGG\Entity\Feed;
use QuadLayers\WP_Orm\Builder\CollectionRepositoryBuilder;

/**
 * Models_Feeds Class
 */
class Feeds {

	protected static $instance;
	protected $repository;
	/**
	 * Instagram URL
	 *
	 * @var string
	 */
	public $instagram_url = 'https://www.instagram.com';

	public function __construct() {

		$builder = ( new CollectionRepositoryBuilder() )
		->setTable( 'insta_gallery_feeds' )
		->setEntity( Feed::class )
		->setAutoIncrement( true );

		$this->repository = $builder->getRepository();
	}

	/**
	 * Function to sanitize instagram feed
	 *
	 * @param string $feed Feed string to be sanitized.
	 * @return string
	 */
	protected function sanitize_instagram_feed( $feed ) {

		// Removing @, # and trimming input
		// ---------------------------------------------------------------------

		$feed = sanitize_text_field( $feed );

		$feed = trim( $feed );
		$feed = str_replace( '@', '', $feed );
		$feed = str_replace( '#', '', $feed );
		$feed = str_replace( $this->instagram_url, '', $feed );
		$feed = str_replace( '/explore/tags /', '', $feed );
		$feed = str_replace( '/', '', $feed );

		return $feed;
	}

	/**
	 * Function to get feed by id
	 *
	 * @return array
	 */
	public function get( $id ) {
		$entity = $this->repository->find( $id );
		if ( $entity ) {
			$feed = $entity->getProperties();

			// Ensure responsive settings exist for backward compatibility
			if ( ! isset( $feed['responsive'] ) ) {
				$feed['responsive'] = array(
					'desktop'     => array(
						'columns' => isset( $feed['columns'] ) ? $feed['columns'] : 3,
						'spacing' => isset( $feed['spacing'] ) ? $feed['spacing'] : 10,
					),
					'tablet'      => array(
						'columns' => 2,
						'spacing' => 8,
					),
					'mobile'      => array(
						'columns' => 1,
						'spacing' => 6,
					),
					'breakpoints' => array(
						'tablet' => 768,
						'mobile' => 480,
					),
				);
			}

			return $feed;
		}
	}

	/**
	 * Function to create new feed
	 *
	 * @param array $feed_data New feed data.
	 * @return array|false
	 */
	public function create( $feed_data ) {

		if ( isset( $feed_data['id'] ) ) {
			unset( $feed_data['id'] );
		}

		$feed_data['tag'] = $this->sanitize_instagram_feed( $feed_data['tag'] );

		// Make sure responsive is set for new feeds
		if ( ! isset( $feed_data['responsive'] ) ) {
			$feed_data['responsive'] = array(
				'desktop'     => array(
					'columns' => isset( $feed_data['columns'] ) ? $feed_data['columns'] : 3,
					'spacing' => isset( $feed_data['spacing'] ) ? $feed_data['spacing'] : 10,
				),
				'tablet'      => array(
					'columns' => 2,
					'spacing' => 8,
				),
				'mobile'      => array(
					'columns' => 1,
					'spacing' => 6,
				),
				'breakpoints' => array(
					'tablet' => 768,
					'mobile' => 480,
				),
			);
		}

		$entity = $this->repository->create( $feed_data );

		if ( ! $entity ) {
			throw new \Exception( esc_html__( 'Error creating feed.', 'insta-gallery' ), 400 );
		}

		return $entity->getProperties();
	}

	/**
	 * Function to edit feed
	 *
	 * @param array $feed New feed data to replace old one.
	 * @return boolean
	 */
	public function update( $id, $data ) {
		$entity = $this->repository->update( $id, $data );
		if ( $entity ) {
			return $entity->getProperties();
		}
	}

	/**
	 * Function to delete a feed
	 *
	 * @param int $id Feed id to be deleted.
	 * @return boolean
	 */
	public function delete( $id ) {
		return $this->repository->delete( $id );
	}

	/**
	 * Function to get all feeds
	 *
	 * @return array
	 */
	public function get_all() {
		$entities = $this->repository->findAll();
		if ( ! $entities ) {
			return;
		}
		$feeds = array();
		foreach ( $entities as $entity ) {
			$feed = $entity->getProperties();

			// Ensure responsive settings exist for backward compatibility
			if ( ! isset( $feed['responsive'] ) ) {
				$feed['responsive'] = array(
					'desktop'     => array(
						'columns' => isset( $feed['columns'] ) ? $feed['columns'] : 3,
						'spacing' => isset( $feed['spacing'] ) ? $feed['spacing'] : 10,
					),
					'tablet'      => array(
						'columns' => 2,
						'spacing' => 8,
					),
					'mobile'      => array(
						'columns' => 1,
						'spacing' => 6,
					),
					'breakpoints' => array(
						'tablet' => 768,
						'mobile' => 480,
					),
				);
			}

			$feeds[] = $feed;
		}
		return $feeds;
	}

	/**
	 * Clears the account_id field on all feeds that reference a deleted account.
	 *
	 * @param string $account_id The account ID that was deleted.
	 * @return void
	 */
	public function clear_account_references( string $account_id ) {
		$feeds = $this->get_all();

		if ( empty( $feeds ) ) {
			return;
		}

		foreach ( $feeds as $feed ) {
			if ( isset( $feed['account_id'] ) && (string) $feed['account_id'] === $account_id ) {
				$this->repository->update( $feed['id'], array( 'account_id' => '' ) );
			}
		}
	}

	/**
	 * Function to delete all feeds
	 *
	 * @return boolean
	 */
	public function delete_all() {
		return $this->repository->truncate();
	}

	/**
	 * Function to get default feed arguments
	 *
	 * @return array
	 */
	public function get_args() {
		$feed = new Feed();
		return $feed->getProperties();
	}

	/**
	 * Get instance
	 *
	 * @return Feeds
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

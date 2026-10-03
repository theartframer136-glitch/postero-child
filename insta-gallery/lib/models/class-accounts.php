<?php

namespace QuadLayers\IGG\Models;

use QuadLayers\WP_Orm\Builder\CollectionRepositoryBuilder;
use QuadLayers\IGG\Entity\Account;
use QuadLayers\IGG\Models\Feeds as Models_Feeds;
use QuadLayers\IGG\Api\Fetch\Business\Access_Token\Refresh as Api_Fetch_Business_Refresh_Access_Token;
use QuadLayers\IGG\Api\Fetch\Personal\Access_Token\Refresh as Api_Fetch_Personal_Refresh_Access_Token;

/**
 * Models_Feeds Class
 */
class Accounts {

	protected static $instance;
	protected $repository;
	/**
	 * Set the max attemps to renew access_token to prevents API abuse.
	 *
	 * @var integer
	 */
	protected static $access_token_max_renew_attemps = 3;

	public function __construct() {

		$builder = ( new CollectionRepositoryBuilder() )
		->setTable( 'insta_gallery_accounts' )
		->setEntity( Account::class )
		->setAutoIncrement( false );

		$this->repository = $builder->getRepository();
	}

	public function get_args() {
		return ( new Account() )->getDefaults();
	}

	public function get( string $id ) {
		// Pure read. Callers that need a renewal (e.g. the cron in class-plugin.php)
		// must invoke is_access_token_renewed() explicitly — letting get() trigger
		// renewals as a side effect bumps renew_attempts on every existence check
		// and can push an account past the threshold into a permanent deadlock.
		$entity = $this->repository->find( $id );

		if ( ! $entity ) {
			return;
		}

		return $entity->getProperties();
	}

	public function delete( string $id ) {
		$status = $this->repository->delete( $id );
		if ( $status ) {
			$args = array( $id );
			wp_clear_scheduled_hook( 'qligg_cron_account', $args );
			Models_Feeds::instance()->clear_account_references( $id );
		}
		return $status;
	}

	public function update( string $id, array $account ) {
		$entity = $this->repository->update( $id, $account );

		if ( $entity ) {
			return $entity->getProperties();
		}
	}

	/**
	 * Persist an OAuth callback payload (proxy ships raw `expires_in` instead of
	 * the precomputed `access_token_expiration_date`). Resets the renew counter
	 * and runs clean_token so a reconnect cannot inherit the deadlock state from
	 * a previous failed cron cycle.
	 */
	public function update_from_oauth_callback( string $id, array $account ) {
		$account['access_token']                 = $this->clean_token( $account['access_token'] );
		$account['access_token_type']            = $this->get_token_type( $account );
		$account['access_token_expiration_date'] = $this->calculate_expiration_date( $account['expires_in'] );
		$account['access_token_expires_in']      = $account['expires_in'];
		$account['access_token_renew_attempts']  = 0;
		unset( $account['expires_in'] );

		return $this->update( $id, $account );
	}

	public function create( array $account_data ) {

		// Case Add Basic Account button.
		if ( isset( $account_data['id'], $account_data['access_token'], $account_data['expires_in'], $account_data['access_token_type'] ) ) {

			$entity = $this->repository->create(
				array(
					'id'                           => $account_data['id'],
					'access_token'                 => $this->clean_token( $account_data['access_token'] ),
					'access_token_renew_attempts'  => 0,
					'access_token_expiration_date' => $this->calculate_expiration_date( $account_data['expires_in'] ),
					'access_token_expires_in'      => $account_data['expires_in'],
					'access_token_type'            => $this->get_token_type( $account_data ),
				)
			);

			if ( ! $entity ) {
				throw new \Exception( esc_html__( 'Error creating account.', 'insta-gallery' ), 400 );
			}

			$args = array(
				$account_data['id'],
			);
			if ( ! wp_next_scheduled( 'qligg_cron_account', $args ) ) {
				wp_schedule_event(
					time(),
					'qligg_dynamic_token_check',
					'qligg_cron_account',
					$args
				);
			}
			return $entity->getProperties();

		}

		$response = $this->get_renewed_access_token( $account_data['access_token'] );

		if ( isset( $response['error'], $response['message'] ) ) {
			throw new \Exception( $response['message'], $response['code'] );
		}

		if ( ! isset( $response['expires_in'], $response['access_token'] ) ) {
			throw new \Exception( esc_html__( 'Unable to create account.', 'insta-gallery' ), 400 );
		}

		$entity = $this->repository->create(
			array(
				'id'                           => $account_data['id'],
				'access_token'                 => $this->clean_token( $response['access_token'] ),
				'access_token_renew_attempts'  => 0,
				'access_token_expiration_date' => $this->calculate_expiration_date( $response['expires_in'] ),
				'access_token_expires_in'      => $response['expires_in'],
				'access_token_type'            => $this->get_token_type( $response ),
			)
		);

		if ( ! $entity ) {
			throw new \Exception( esc_html__( 'Error creating account.', 'insta-gallery' ), 400 );
		}

		$args = array(
			$account_data['id'],
		);
		if ( ! wp_next_scheduled( 'qligg_cron_account', $args ) ) {

			wp_schedule_event(
				time(),
				'qligg_dynamic_token_check',
				'qligg_cron_account',
				$args
			);
		}
		return $entity->getProperties();
	}

	public function get_all() {
		$entities = $this->repository->findAll();
		if ( ! $entities ) {
			return;
		}
		$accounts = array();
		foreach ( $entities as $entity ) {
			$accounts[] = $entity->getProperties();
		}
		return $accounts;
	}

	public function delete_all() {
		return $this->repository->deleteAll();
	}

	/**
	 * Function to get account access_token type
	 *
	 * @param array $account
	 * @return string
	 */
	protected function get_token_type( $account ) {
		if ( substr( $account['access_token'], 0, 2 ) === 'IG' ) {
			return 'PERSONAL';
		}
		return 'BUSINESS';
	}

	/**
	 * Function to clean token
	 *
	 * @param string $maybe_dirty
	 * @return string
	 */
	protected function clean_token( $maybe_dirty ) {
		$maybe_dirty = (string) $maybe_dirty;

		if ( substr_count( $maybe_dirty, '.' ) < 3 ) {
			return str_replace( '634hgdf83hjdj2', '', $maybe_dirty );
		}

		$parts     = explode( '.', trim( $maybe_dirty ) );
		$last_part = $parts[2] . $parts[3];
		$cleaned   = $parts[0] . '.' . base64_decode( $parts[1] ) . '.' . base64_decode( $last_part );

		return $cleaned;
	}

	/**
	 * Function to renew access token
	 *
	 * @param string  $access_token Account access_token.
	 * @param integer $access_token_renew_attempts Account access_token renew attemps.
	 * @return array
	 */
	public function get_renewed_access_token( $access_token, $access_token_renew_attempts = 0 ) {

		if ( substr( $access_token, 0, 2 ) === 'IG' ) {
			return ( new Api_Fetch_Personal_Refresh_Access_Token() )->get_data( $access_token, $access_token_renew_attempts );
		}

		return ( new Api_Fetch_Business_Refresh_Access_Token() )->get_data( $access_token, $access_token_renew_attempts );
	}

	/**
	 * Function to increase access_token_renew_attempts
	 *
	 * @param array $account Account to increase access_token_renew_attempts property.
	 * @return void
	 */
	protected function access_token_renew_attemps_increase( $account ) {
		// max(0, ...) prevents the entity's -1 sentinel default (see Account)
		// from collapsing the first failure into a stored 0 instead of 1.
		$account['access_token_renew_attempts'] = max( 0, intval( $account['access_token_renew_attempts'] ) ) + 1;
		$this->update( $account['id'], $account );
	}

	/**
	 * Function to calculate expiration date based on current time and expires_in property
	 *
	 * @param int $expires_in Time lapse to expire.
	 * @return int
	 */
	public function calculate_expiration_date( $expires_in ) {
		return time() + $expires_in - 1;
	}

	/**
	 * Function to validate account's access_token
	 *
	 * @param array $account Account to validate access_token.
	 * @return array|false
	 */
	public function is_access_token_renewed( $account ) {

		if ( $this->access_token_renew_attemps_exceded( $account ) ) {
			return false;
		}

		$response = $this->get_renewed_access_token( $account['access_token'], $account['access_token_renew_attempts'] );

		/**
		 * Validate response
		 */
		if ( isset( $response['error'] ) || ! isset( $response['expires_in'] ) || ! isset( $response['access_token'] ) ) {
			$this->access_token_renew_attemps_increase( $account );
			return false;
		}

		if ( $account['access_token_expiration_date'] >= $this->calculate_expiration_date( $response['expires_in'] ) ) {
			return false;
		}

		$account['access_token_renew_attempts']  = 0;
		$account['access_token']                 = $this->clean_token( $response['access_token'] );
		$account['access_token_expiration_date'] = $this->calculate_expiration_date( $response['expires_in'] );
		$account                                 = $this->update( $account['id'], $account );

		if ( $account ) {
			return true;
		}

		return false;
	}

	/**
	 * Function to check if account access_token is expired
	 *
	 * @param array $account Account to check it's access_token expiration.
	 * @return boolean
	 */
	protected function is_access_token_expired( $account ) {

		if ( ( $account['access_token_expiration_date'] - time() ) < DAY_IN_SECONDS * 5 ) {
			return true;
		}

		return false;
	}

	/**
	 * Function to check if access_token_renew_attempts property exceded access_token_max_renew_attemps
	 *
	 * @param array $account Account to check if access_token_renew_attempts property is exceded.
	 * @return boolean
	 */
	protected function access_token_renew_attemps_exceded( $account ) {
		if ( intval( $account['access_token_renew_attempts'] ) > self::$access_token_max_renew_attemps ) {
			return true;
		}
		return false;
	}

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

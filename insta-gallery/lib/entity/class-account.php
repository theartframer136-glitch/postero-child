<?php
namespace QuadLayers\IGG\Entity;

use QuadLayers\WP_Orm\Entity\CollectionEntity;

class Account extends CollectionEntity {
	public static $primaryKey      		 = 'id'; //phpcs:ignore
	public $id                           = '';
	public $username                     = '';
	public $profile_picture_url          = '';
	public $access_token                 = '';
	public $access_token_type            = '';
	public $access_token_expiration_date = 0;
	public $access_token_expires_in      = 0;
	// Sentinel default (-1, "uninitialized") so the ORM's diff-save still
	// persists the counter when it is reset to 0 — otherwise a reset would
	// silently vanish from the raw option (CollectionMapper::toArray drops
	// fields equal to their default), hiding the recovery from anything that
	// reads the option directly. Code paths always coerce the value through
	// intval(), so the sentinel never reaches a real counter check.
	public $access_token_renew_attempts  = -1;
}

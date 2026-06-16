<?php
/**
 * Action result value object.
 *
 * @package BulkActionsManager
 */

namespace BAM\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Action_Result
 */
class Action_Result {

	/**
	 * Success flag.
	 *
	 * @var bool
	 */
	public $success;

	/**
	 * Error message.
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Constructor.
	 *
	 * @param bool   $success Success.
	 * @param string $message Message.
	 */
	public function __construct( $success, $message = '' ) {
		$this->success = $success;
		$this->message = $message;
	}
}

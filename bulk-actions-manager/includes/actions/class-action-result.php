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

	const STATUS_SUCCESS = 'success';
	const STATUS_SKIPPED = 'skipped';
	const STATUS_FAILED  = 'failed';

	/**
	 * Outcome status: success, skipped, or failed.
	 *
	 * @var string
	 */
	public $status;

	/**
	 * Human-readable message.
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Machine-readable code slug.
	 *
	 * @var string
	 */
	public $code;

	/**
	 * Legacy success flag (true only for success status).
	 *
	 * @var bool
	 */
	public $success;

	/**
	 * Constructor (legacy bool API).
	 *
	 * @param bool   $success Success.
	 * @param string $message Message.
	 */
	public function __construct( $success, $message = '' ) {
		$this->status  = $success ? self::STATUS_SUCCESS : self::STATUS_FAILED;
		$this->message = $message;
		$this->code    = '';
		$this->success = (bool) $success;
	}

	/**
	 * @param string $message Message.
	 * @param string $code    Code.
	 * @return self
	 */
	public static function success( $message = '', $code = '' ) {
		$result          = new self( true, $message );
		$result->status  = self::STATUS_SUCCESS;
		$result->code    = $code;
		$result->success = true;
		return $result;
	}

	/**
	 * @param string $message Message.
	 * @param string $code    Code.
	 * @return self
	 */
	public static function skipped( $message, $code = '' ) {
		$result          = new self( false, $message );
		$result->status  = self::STATUS_SKIPPED;
		$result->code    = $code;
		$result->success = false;
		return $result;
	}

	/**
	 * @param string $message Message.
	 * @param string $code    Code.
	 * @return self
	 */
	public static function failed( $message, $code = '' ) {
		$result          = new self( false, $message );
		$result->status  = self::STATUS_FAILED;
		$result->code    = $code;
		$result->success = false;
		return $result;
	}

	/**
	 * Normalized status for the processor.
	 *
	 * @return string success|skipped|failed
	 */
	public function get_status() {
		if ( in_array( $this->status, array( self::STATUS_SUCCESS, self::STATUS_SKIPPED, self::STATUS_FAILED ), true ) ) {
			return $this->status;
		}
		return $this->success ? self::STATUS_SUCCESS : self::STATUS_FAILED;
	}

	/**
	 * @return string
	 */
	public function get_message() {
		return (string) $this->message;
	}

	/**
	 * @return string
	 */
	public function get_code() {
		return (string) $this->code;
	}
}

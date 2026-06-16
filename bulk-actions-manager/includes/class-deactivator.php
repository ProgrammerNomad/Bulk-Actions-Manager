<?php
/**
 * Plugin deactivation handler.
 *
 * @package BulkActionsManager
 */

namespace BAM;

use BAM\Cron\Cron_Scheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Class Deactivator
 */
class Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		Cron_Scheduler::clear_events();
		flush_rewrite_rules();
	}
}

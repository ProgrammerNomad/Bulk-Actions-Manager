<?php
/**
 * Plugin activation handler.
 *
 * @package BulkActionsManager
 */

namespace BAM;

use BAM\Database\Schema;
use BAM\Utils\Capabilities;
use BAM\Cron\Cron_Scheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Class Activator
 */
class Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		Schema::create_tables();
		update_option( 'bam_db_version', BAM_DB_VERSION );

		Capabilities::add_to_administrator();

		if ( false === get_option( 'bam_settings' ) ) {
			update_option( 'bam_settings', Settings::defaults() );
		}

		Cron_Scheduler::schedule_events();

		flush_rewrite_rules();
	}
}

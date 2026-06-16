<?php
/**
 * Job ETA estimator.
 *
 * @package BulkActionsManager
 */

namespace BAM\Jobs;

defined( 'ABSPATH' ) || exit;

/**
 * Class Job_Estimator
 */
class Job_Estimator {

	/**
	 * Estimate remaining seconds.
	 *
	 * @param object $job Job row.
	 * @return int
	 */
	public static function estimate( $job ) {
		if ( empty( $job->started_at ) || (int) $job->processed_items <= 0 ) {
			return 0;
		}

		$started   = strtotime( $job->started_at );
		$elapsed   = max( 1, time() - $started );
		$rate      = $job->processed_items / $elapsed;
		$remaining = max( 0, (int) $job->total_items - (int) $job->processed_items );

		return $rate > 0 ? (int) ceil( $remaining / $rate ) : 0;
	}
}

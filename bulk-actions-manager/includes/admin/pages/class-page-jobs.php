<?php
/**
 * Jobs list admin page.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

defined( 'ABSPATH' ) || exit;

/**
 * Class Page_Jobs
 */
class Page_Jobs extends Page_Base {

	/**
	 * Render jobs page.
	 */
	public static function render() {
		$job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $job_id ) {
			self::render_detail( $job_id );
			return;
		}

		self::header( __( 'Jobs', 'bulk-actions-manager' ) );
		?>
		<div id="bam-jobs-list">
			<ul class="subsubsub" id="bam-jobs-filter">
				<li><a href="#" data-status=""><?php esc_html_e( 'All', 'bulk-actions-manager' ); ?></a> |</li>
				<li><a href="#" data-status="running"><?php esc_html_e( 'Running', 'bulk-actions-manager' ); ?></a> |</li>
				<li><a href="#" data-status="queued"><?php esc_html_e( 'Queued', 'bulk-actions-manager' ); ?></a> |</li>
				<li><a href="#" data-status="completed"><?php esc_html_e( 'Completed', 'bulk-actions-manager' ); ?></a> |</li>
				<li><a href="#" data-status="failed"><?php esc_html_e( 'Failed', 'bulk-actions-manager' ); ?></a> |</li>
				<li><a href="#" data-status="paused"><?php esc_html_e( 'Paused', 'bulk-actions-manager' ); ?></a></li>
			</ul>
			<table class="widefat striped" id="bam-jobs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'bulk-actions-manager' ); ?></th>
						<th><?php esc_html_e( 'Name', 'bulk-actions-manager' ); ?></th>
						<th><?php esc_html_e( 'Action', 'bulk-actions-manager' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bulk-actions-manager' ); ?></th>
						<th><?php esc_html_e( 'Records', 'bulk-actions-manager' ); ?></th>
						<th><?php esc_html_e( 'Created', 'bulk-actions-manager' ); ?></th>
						<th><?php esc_html_e( 'Finished', 'bulk-actions-manager' ); ?></th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
		<?php
		self::footer();
	}

	/**
	 * Render job detail view.
	 *
	 * @param int $job_id Job ID.
	 */
	private static function render_detail( $job_id ) {
		self::header( sprintf(
			/* translators: %d: job ID */
			__( 'Job #%d', 'bulk-actions-manager' ),
			$job_id
		) );
		?>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=bam-jobs' ) ); ?>">&larr; <?php esc_html_e( 'Back to Jobs', 'bulk-actions-manager' ); ?></a></p>
		<div id="bam-job-detail" data-job-id="<?php echo esc_attr( (string) $job_id ); ?>">
			<div class="bam-loading"><?php esc_html_e( 'Loading...', 'bulk-actions-manager' ); ?></div>
		</div>
		<?php
		self::footer();
	}
}

<?php
/**
 * Dashboard admin page.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

use BAM\Admin\Admin_UI;
use BAM\Admin\Dashboard_Data;

defined( 'ABSPATH' ) || exit;

/**
 * Class Page_Dashboard
 */
class Page_Dashboard extends Page_Base {

	/**
	 * Render dashboard page.
	 */
	public static function render() {
		$data = Dashboard_Data::get();

		self::header( __( 'Bulk Actions Manager', 'bulk-actions-manager' ) );

		self::render_jobs_postbox(
			'bam-recent-jobs',
			__( 'Recent Jobs', 'bulk-actions-manager' ),
			$data['recent_jobs'],
			admin_url( 'admin.php?page=bam-new-job' )
		);

		echo '<div id="dashboard-widgets" class="metabox-holder columns-2">';
		echo '<div class="postbox-container">';
		self::render_compact_jobs_postbox(
			'bam-running-jobs',
			__( 'Running Jobs', 'bulk-actions-manager' ),
			$data['running_jobs'],
			admin_url( 'admin.php?page=bam-jobs&status=running' )
		);
		echo '</div>';
		echo '<div class="postbox-container">';
		self::render_compact_jobs_postbox(
			'bam-undoable-jobs',
			__( 'Undoable Jobs', 'bulk-actions-manager' ),
			$data['undoable_jobs'],
			admin_url( 'admin.php?page=bam-jobs&status=completed' )
		);
		echo '</div>';
		echo '</div>';

		Admin_UI::postbox_open( 'bam-system-status', __( 'System Status', 'bulk-actions-manager' ) );
		self::render_system_status( $data['system_status'], $data['counts'] );
		Admin_UI::postbox_close();

		self::footer();
	}

	/**
	 * Full-width recent jobs table.
	 *
	 * @param string             $id       Postbox ID.
	 * @param string             $title    Title.
	 * @param array<int, object> $jobs     Jobs.
	 * @param string             $empty_cta Empty state CTA URL.
	 */
	private static function render_jobs_postbox( $id, $title, array $jobs, $empty_cta ) {
		Admin_UI::postbox_open( $id, $title );
		if ( empty( $jobs ) ) {
			printf(
				'<p class="description">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'No jobs yet.', 'bulk-actions-manager' ),
				esc_url( $empty_cta ),
				esc_html__( 'Create your first job', 'bulk-actions-manager' )
			);
			Admin_UI::postbox_close();
			return;
		}
		self::render_jobs_table( $jobs, true );
		Admin_UI::postbox_close();
	}

	/**
	 * Compact jobs list for side postboxes.
	 *
	 * @param string             $id       Postbox ID.
	 * @param string             $title    Title.
	 * @param array<int, object> $jobs     Jobs.
	 * @param string             $view_url View all URL.
	 */
	private static function render_compact_jobs_postbox( $id, $title, array $jobs, $view_url ) {
		Admin_UI::postbox_open( $id, $title );
		if ( empty( $jobs ) ) {
			echo '<p class="description">' . esc_html__( 'None', 'bulk-actions-manager' ) . '</p>';
		} else {
			self::render_jobs_table( $jobs, false );
		}
		printf(
			'<p><a href="%s">%s</a></p>',
			esc_url( $view_url ),
			esc_html__( 'View all', 'bulk-actions-manager' )
		);
		Admin_UI::postbox_close();
	}

	/**
	 * Render jobs widefat table.
	 *
	 * @param array<int, object> $jobs        Jobs.
	 * @param bool               $show_action Show action column.
	 */
	private static function render_jobs_table( array $jobs, $show_action ) {
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'bulk-actions-manager' ); ?></th>
					<?php if ( $show_action ) : ?>
						<th scope="col"><?php esc_html_e( 'Action', 'bulk-actions-manager' ); ?></th>
					<?php endif; ?>
					<th scope="col"><?php esc_html_e( 'Status', 'bulk-actions-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Undo', 'bulk-actions-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Date', 'bulk-actions-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $jobs as $job ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=bam-jobs&job_id=' . (int) $job->id ) ); ?>">
								<?php echo esc_html( $job->name ); ?>
							</a>
						</td>
						<?php if ( $show_action ) : ?>
							<td><?php echo esc_html( $job->action_type ); ?></td>
						<?php endif; ?>
						<td><?php echo Admin_UI::status_badge( $job->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td><?php echo Admin_UI::undo_label( $job ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td><?php echo esc_html( $job->created_at ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render system status form table.
	 *
	 * @param array<string, mixed> $status System status.
	 * @param array<string, int>   $counts Counts.
	 */
	private static function render_system_status( array $status, array $counts ) {
		$retention = (int) $status['snapshot_retention'];
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'PHP', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( $status['php_version'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'WordPress', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( $status['wordpress_version'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Memory', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( $status['memory_limit'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Max Execution', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( (string) $status['max_execution_time'] . 's' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Cron', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( $status['cron_status'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Queue', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( sprintf( /* translators: %d: queue count */ __( '%d queued', 'bulk-actions-manager' ), (int) $status['queue_count'] ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Snapshots', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( (string) $status['snapshot_count'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Total Jobs', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( (string) $counts['total'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Snapshot Retention', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( 0 === $retention ? __( 'Forever', 'bulk-actions-manager' ) : sprintf( /* translators: %d: days */ __( '%d days', 'bulk-actions-manager' ), $retention ) ); ?></td>
				</tr>
			</tbody>
		</table>
		<?php
	}
}

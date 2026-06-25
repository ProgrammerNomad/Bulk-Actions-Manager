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

		echo '<div id="bam-dashboard" class="metabox-holder">';

		self::render_kpi_summary( $data['counts'] );

		echo '<div class="bam-dashboard-main">';

		Admin_UI::postbox_open( 'bam-dashboard-recent', __( 'Recent Jobs', 'bulk-actions-manager' ) );
		self::render_jobs_table_section(
			$data['recent_jobs'],
			true,
			admin_url( 'admin.php?page=bam-jobs' ),
			__( 'Create your first job', 'bulk-actions-manager' ),
			admin_url( 'admin.php?page=bam-new-job' )
		);
		Admin_UI::postbox_close();

		Admin_UI::postbox_open( 'bam-dashboard-activity', __( 'Activity & Queue', 'bulk-actions-manager' ) );
		self::render_activity_panel( $data );
		Admin_UI::postbox_close();

		echo '</div>';

		Admin_UI::postbox_open( 'bam-dashboard-health', __( 'System Health', 'bulk-actions-manager' ) );
		self::render_system_health( $data['system_status'] );
		Admin_UI::postbox_close();

		echo '</div>';

		self::footer();
	}

	/**
	 * KPI summary row (WP-native boxes).
	 *
	 * @param array<string, int> $counts Counts.
	 */
	private static function render_kpi_summary( array $counts ) {
		$items = array(
			array(
				'label' => __( 'Total Jobs', 'bulk-actions-manager' ),
				'value' => $counts['total'],
				'url'   => admin_url( 'admin.php?page=bam-jobs' ),
			),
			array(
				'label' => __( 'Running', 'bulk-actions-manager' ),
				'value' => $counts['running'],
				'url'   => admin_url( 'admin.php?page=bam-jobs&status=running' ),
			),
			array(
				'label' => __( 'Completed', 'bulk-actions-manager' ),
				'value' => $counts['completed'],
				'url'   => admin_url( 'admin.php?page=bam-jobs&status=completed' ),
			),
			array(
				'label' => __( 'Failed', 'bulk-actions-manager' ),
				'value' => $counts['failed'],
				'url'   => admin_url( 'admin.php?page=bam-jobs&status=failed' ),
			),
			array(
				'label' => __( 'Undo Available', 'bulk-actions-manager' ),
				'value' => $counts['undo'],
				'url'   => admin_url( 'admin.php?page=bam-logs&undo_status=available' ),
			),
		);
		?>
		<div class="bam-dashboard-kpis">
			<?php foreach ( $items as $item ) : ?>
				<a class="bam-kpi-summary" href="<?php echo esc_url( $item['url'] ); ?>">
					<span class="bam-kpi-summary__value"><?php echo esc_html( number_format_i18n( (int) $item['value'] ) ); ?></span>
					<span class="bam-kpi-summary__label"><?php echo esc_html( $item['label'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Activity panel: running, undoable, queue/cron indicators.
	 *
	 * @param array<string, mixed> $data Dashboard data.
	 */
	private static function render_activity_panel( array $data ) {
		$counts = $data['counts'];
		$status = $data['system_status'];

		printf(
			'<p class="bam-dashboard-activity__headline"><strong>%1$s</strong> %2$s · <strong>%3$s</strong> %4$s</p>',
			esc_html( number_format_i18n( (int) $counts['running'] ) ),
			esc_html__( 'running', 'bulk-actions-manager' ),
			esc_html( number_format_i18n( (int) $counts['undo'] ) ),
			esc_html__( 'undo available', 'bulk-actions-manager' )
		);

		echo '<div class="bam-dashboard-activity__section">';
		echo '<h3 class="bam-dashboard-activity__title">' . esc_html__( 'Running Jobs', 'bulk-actions-manager' ) . '</h3>';
		self::render_compact_jobs_table(
			array_slice( $data['running_jobs'], 0, 3 ),
			admin_url( 'admin.php?page=bam-jobs&status=running' )
		);
		echo '</div>';

		echo '<div class="bam-dashboard-activity__section">';
		echo '<h3 class="bam-dashboard-activity__title">' . esc_html__( 'Undoable Jobs', 'bulk-actions-manager' ) . '</h3>';
		self::render_compact_jobs_table(
			array_slice( $data['undoable_jobs'], 0, 3 ),
			admin_url( 'admin.php?page=bam-jobs&status=completed' )
		);
		echo '</div>';

		$cron_active = ( __( 'Active', 'bulk-actions-manager' ) === $status['cron_status'] );
		?>
		<p class="bam-dashboard-activity__indicators description">
			<?php esc_html_e( 'Cron', 'bulk-actions-manager' ); ?>:
			<?php if ( $cron_active ) : ?>
				<span class="bam-status-badge bam-status-badge--completed"><?php echo esc_html( $status['cron_status'] ); ?></span>
			<?php else : ?>
				<span class="bam-status-badge bam-status-badge--failed"><?php echo esc_html( $status['cron_status'] ); ?></span>
			<?php endif; ?>
			&nbsp;·&nbsp;
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: queue count */
					__( 'Queue: %d queued', 'bulk-actions-manager' ),
					(int) $status['queue_count']
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Compact jobs table (name, status only).
	 *
	 * @param array<int, object> $jobs     Jobs.
	 * @param string             $view_url View-all URL.
	 */
	private static function render_compact_jobs_table( array $jobs, $view_url ) {
		if ( empty( $jobs ) ) {
			echo '<p class="description">' . esc_html__( 'None', 'bulk-actions-manager' ) . '</p>';
			printf(
				'<p><a href="%s">%s</a></p>',
				esc_url( $view_url ),
				esc_html__( 'View all', 'bulk-actions-manager' )
			);
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'bulk-actions-manager' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'bulk-actions-manager' ); ?></th>
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
						<td><?php echo Admin_UI::status_badge( $job->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p><a href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'View all', 'bulk-actions-manager' ); ?></a></p>
		<?php
	}

	/**
	 * Jobs table with optional empty state and view-all link.
	 *
	 * @param array<int, object> $jobs       Jobs.
	 * @param bool               $show_action Show action column.
	 * @param string             $view_url   View-all URL.
	 * @param string             $empty_cta  Empty-state link label (recent jobs only).
	 * @param string             $empty_link Empty-state CTA URL.
	 */
	private static function render_jobs_table_section( array $jobs, $show_action, $view_url, $empty_cta = '', $empty_link = '' ) {
		if ( empty( $jobs ) ) {
			if ( $empty_cta && $empty_link ) {
				printf(
					'<p class="description">%1$s <a href="%2$s">%3$s</a></p>',
					esc_html__( 'No jobs yet.', 'bulk-actions-manager' ),
					esc_url( $empty_link ),
					esc_html( $empty_cta )
				);
			} else {
				echo '<p class="description">' . esc_html__( 'No jobs yet.', 'bulk-actions-manager' ) . '</p>';
			}
			return;
		}

		self::render_jobs_table( $jobs, $show_action );

		printf(
			'<p><a href="%s">%s</a></p>',
			esc_url( $view_url ),
			esc_html__( 'View all jobs', 'bulk-actions-manager' )
		);
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
							<td><?php echo esc_html( Admin_UI::action_label( $job->action_type ) ); ?></td>
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
	 * Render system health inside postbox.
	 *
	 * @param array<string, mixed> $status System status.
	 */
	private static function render_system_health( array $status ) {
		$retention = (int) $status['snapshot_retention'];
		$cron_active = ( __( 'Active', 'bulk-actions-manager' ) === $status['cron_status'] );
		?>
		<table class="form-table bam-health-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'PHP', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( $status['php_version'] ); ?></td>
					<th scope="row"><?php esc_html_e( 'WordPress', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( $status['wordpress_version'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Memory', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( $status['memory_limit'] ); ?></td>
					<th scope="row"><?php esc_html_e( 'Max Execution', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( (string) $status['max_execution_time'] . 's' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Cron', 'bulk-actions-manager' ); ?></th>
					<td>
						<?php if ( $cron_active ) : ?>
							<span class="bam-status-badge bam-status-badge--completed"><?php echo esc_html( $status['cron_status'] ); ?></span>
						<?php else : ?>
							<span class="bam-status-badge bam-status-badge--failed"><?php echo esc_html( $status['cron_status'] ); ?></span>
						<?php endif; ?>
					</td>
					<th scope="row"><?php esc_html_e( 'Queue', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( sprintf( /* translators: %d: queue count */ __( '%d queued', 'bulk-actions-manager' ), (int) $status['queue_count'] ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Snapshots', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( (string) $status['snapshot_count'] ); ?></td>
					<th scope="row"><?php esc_html_e( 'Snapshot Retention', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( 0 === $retention ? __( 'Forever', 'bulk-actions-manager' ) : sprintf( /* translators: %d: days */ __( '%d days', 'bulk-actions-manager' ), $retention ) ); ?></td>
				</tr>
			</tbody>
		</table>
		<?php
	}
}

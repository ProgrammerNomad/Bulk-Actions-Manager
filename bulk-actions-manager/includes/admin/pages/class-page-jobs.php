<?php
/**
 * Jobs admin page (runs + schedules).
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

use BAM\Admin\Admin_UI;
use BAM\Admin\List_Tables\Jobs_List_Table;
use BAM\Cron\Schedule_Runner;
use BAM\Database\Repositories\Job_Repository;
use BAM\Database\Repositories\Schedule_Repository;
use BAM\Jobs\Job_Manager;
use BAM\Utils\Capabilities;
use BAM\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Class Page_Jobs
 */
class Page_Jobs extends Page_Base {

	/**
	 * Render jobs page.
	 */
	public static function render() {
		self::handle_actions();

		$job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $job_id ) {
			self::render_detail( $job_id );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type        = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'run';
		$is_schedule = ( 'schedule' === $type );

		self::header( __( 'Jobs', 'bulk-actions-manager' ) );

		if ( isset( $_GET['cancelled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Job cancelled.', 'bulk-actions-manager' ) . '</p></div>';
		}
		if ( isset( $_GET['deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Schedule deleted.', 'bulk-actions-manager' ) . '</p></div>';
		}
		if ( isset( $_GET['ran'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Schedule triggered.', 'bulk-actions-manager' ) . '</p></div>';
		}

		// Schedules are created/edited on the New Job page - show a button linking there.
		if ( $is_schedule ) {
			?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=bam-new-job' ) ); ?>">
					<?php esc_html_e( 'Add Schedule', 'bulk-actions-manager' ); ?>
				</a>
			</p>
			<?php
		}

		$list_table = new Jobs_List_Table();
		$list_table->prepare_items();
		$search_label = $is_schedule
			? __( 'Search Schedules', 'bulk-actions-manager' )
			: __( 'Search Jobs', 'bulk-actions-manager' );
		?>
		<form method="get">
			<input type="hidden" name="page" value="bam-jobs" />
			<?php if ( $is_schedule ) : ?>
				<input type="hidden" name="type" value="schedule" />
			<?php endif; ?>
			<?php
			$list_table->views();
			$list_table->search_box( $search_label, 'bam-job-search' );
			$list_table->display();
			?>
		</form>
		<?php
		self::footer();
	}

	/**
	 * Handle admin GET actions.
	 */
	private static function handle_actions() {
		if ( ! Capabilities::current_user_can() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['bam_action'] ) ? sanitize_key( wp_unslash( $_GET['bam_action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;

		if ( 'cancel_job' === $action && $job_id ) {
			check_admin_referer( 'bam_cancel_job_' . $job_id );
			( new Job_Manager() )->cancel( $job_id );
			wp_safe_redirect( admin_url( 'admin.php?page=bam-jobs&cancelled=1' ) );
			exit;
		}

		if ( 'pause_job' === $action && $job_id ) {
			check_admin_referer( 'bam_pause_job_' . $job_id );
			( new Job_Manager() )->pause( $job_id );
			wp_safe_redirect( admin_url( 'admin.php?page=bam-jobs&job_id=' . $job_id ) );
			exit;
		}

		if ( 'resume_job' === $action && $job_id ) {
			check_admin_referer( 'bam_resume_job_' . $job_id );
			( new Job_Manager() )->resume( $job_id );
			wp_safe_redirect( admin_url( 'admin.php?page=bam-jobs&job_id=' . $job_id ) );
			exit;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$schedule_id = isset( $_GET['schedule_id'] ) ? absint( $_GET['schedule_id'] ) : 0;

		if ( ! $action || ! $schedule_id ) {
			return;
		}

		if ( 'delete_schedule' === $action ) {
			check_admin_referer( 'bam_delete_schedule_' . $schedule_id );
			Schedule_Repository::delete( $schedule_id );
			wp_safe_redirect( admin_url( 'admin.php?page=bam-jobs&type=schedule&deleted=1' ) );
			exit;
		}

		if ( 'run_schedule' === $action ) {
			check_admin_referer( 'bam_run_schedule_' . $schedule_id );
			self::run_schedule( $schedule_id );
			wp_safe_redirect( admin_url( 'admin.php?page=bam-jobs&type=schedule&ran=1' ) );
			exit;
		}
	}

	/**
	 * Run a single schedule now.
	 *
	 * @param int $schedule_id Schedule ID.
	 */
	private static function run_schedule( $schedule_id ) {
		$schedule = Schedule_Repository::find( $schedule_id );
		if ( ! $schedule ) {
			return;
		}

		$manager = new Job_Manager();
		$result  = $manager->create(
			array(
				'name'            => $schedule->name,
				'filter'          => Sanitizer::json_decode( $schedule->filter_payload ),
				'action_type'     => $schedule->action_type,
				'action_payload'  => Sanitizer::json_decode( $schedule->action_payload ),
				'processing_mode' => 'background',
			)
		);

		$update = array(
			'last_run_at' => current_time( 'mysql' ),
			'next_run_at' => Schedule_Runner::calculate_next_run( $schedule->cron_expression ),
		);

		if ( ! is_wp_error( $result ) && ! empty( $result['job_id'] ) ) {
			$update['last_job_id'] = (int) $result['job_id'];
		}

		Schedule_Repository::update( $schedule_id, $update );
	}

	/**
	 * Render job detail view with debug info, edit/clone controls.
	 *
	 * @param int $job_id Job ID.
	 */
	private static function render_detail( $job_id ) {
		$job = Job_Repository::find( $job_id );
		if ( ! $job ) {
			self::header( __( 'Job Not Found', 'bulk-actions-manager' ) );
			echo '<p>' . esc_html__( 'Job not found.', 'bulk-actions-manager' ) . '</p>';
			self::footer();
			return;
		}

		$formatted = Job_Manager::format_job( $job );

		self::header(
			sprintf(
				/* translators: %d: job ID */
				__( 'Job #%d', 'bulk-actions-manager' ),
				$job_id
			)
		);
		?>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bam-jobs' ) ); ?>">&larr; <?php esc_html_e( 'Back to Jobs', 'bulk-actions-manager' ); ?></a>
			<?php if ( in_array( $job->status, array( 'queued', 'paused' ), true ) ) : ?>
				&nbsp;|&nbsp;
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=bam-new-job&job_id=' . $job_id ) ); ?>">
					<?php esc_html_e( 'Edit Job', 'bulk-actions-manager' ); ?>
				</a>
			<?php endif; ?>
			<?php if ( in_array( $job->status, array( 'completed', 'failed', 'cancelled' ), true ) ) : ?>
				&nbsp;|&nbsp;
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=bam-new-job&clone_job_id=' . $job_id ) ); ?>">
					<?php esc_html_e( 'Clone Job', 'bulk-actions-manager' ); ?>
				</a>
			<?php endif; ?>
		</p>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Name', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( $job->name ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'bulk-actions-manager' ); ?></th>
					<td><?php echo Admin_UI::status_badge( $job->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Action', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( $job->action_type ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Mode', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( $job->processing_mode ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Progress', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( (string) $formatted['processed_items'] . ' / ' . $formatted['total_items'] . ' (' . $formatted['percent'] . '%)' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Failed', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( (string) $formatted['failed_items'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Created', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( $job->created_at ?: '-' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Finished', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( $job->finished_at ?: '-' ); ?></td>
				</tr>
				<?php if ( ! empty( $job->error_message ) ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Last Error', 'bulk-actions-manager' ); ?></th>
					<td><span class="bam-error-message"><?php echo esc_html( $job->error_message ); ?></span></td>
				</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Undo Available', 'bulk-actions-manager' ); ?></th>
					<td><?php echo $formatted['undo_available'] ? esc_html__( 'Yes', 'bulk-actions-manager' ) : esc_html__( 'No', 'bulk-actions-manager' ); ?></td>
				</tr>
				<?php if ( ! empty( $formatted['export_download_url'] ) ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Export', 'bulk-actions-manager' ); ?></th>
					<td><a class="button" href="<?php echo esc_url( $formatted['export_download_url'] ); ?>"><?php esc_html_e( 'Download Export', 'bulk-actions-manager' ); ?></a></td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( in_array( $job->status, array( 'running', 'queued', 'paused' ), true ) ) : ?>
		<p>
			<?php if ( 'running' === $job->status ) : ?>
				<?php
				$pause_url = wp_nonce_url(
					admin_url( 'admin.php?page=bam-jobs&bam_action=pause_job&job_id=' . $job_id ),
					'bam_pause_job_' . $job_id
				);
				?>
				<a class="button" href="<?php echo esc_url( $pause_url ); ?>"><?php esc_html_e( 'Pause', 'bulk-actions-manager' ); ?></a>
			<?php endif; ?>
			<?php if ( 'paused' === $job->status ) : ?>
				<?php
				$resume_url = wp_nonce_url(
					admin_url( 'admin.php?page=bam-jobs&bam_action=resume_job&job_id=' . $job_id ),
					'bam_resume_job_' . $job_id
				);
				?>
				<a class="button" href="<?php echo esc_url( $resume_url ); ?>"><?php esc_html_e( 'Resume', 'bulk-actions-manager' ); ?></a>
			<?php endif; ?>
			<?php
			$cancel_url = wp_nonce_url(
				admin_url( 'admin.php?page=bam-jobs&bam_action=cancel_job&job_id=' . $job_id ),
				'bam_cancel_job_' . $job_id
			);
			?>
			<a class="button button-link-delete" href="<?php echo esc_url( $cancel_url ); ?>"><?php esc_html_e( 'Cancel', 'bulk-actions-manager' ); ?></a>
		</p>

		<div id="bam-job-detail" class="metabox-holder" data-job-id="<?php echo esc_attr( (string) $job_id ); ?>">
			<?php Admin_UI::postbox_open( 'bam-job-progress-detail', __( 'Job Progress', 'bulk-actions-manager' ) ); ?>
			<div class="bam-job-progress">
				<progress id="bam-progress-bar" max="100" value="<?php echo esc_attr( (string) $formatted['percent'] ); ?>"></progress>
				<p id="bam-progress-text" class="description"><?php echo esc_html( (string) $formatted['percent'] . '% - ' . $job->status ); ?></p>
				<p id="bam-progress-stats" class="description"></p>
				<div id="bam-job-errors"></div>
			</div>
			<?php Admin_UI::postbox_close(); ?>
		</div>
		<?php endif; ?>
		<?php
		self::footer();
	}
}

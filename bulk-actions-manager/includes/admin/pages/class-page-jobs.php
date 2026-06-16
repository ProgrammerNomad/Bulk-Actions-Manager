<?php
/**
 * Jobs admin page (runs + schedules).
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

use BAM\Actions\Action_Registry;
use BAM\Admin\Admin_UI;
use BAM\Admin\List_Tables\Jobs_List_Table;
use BAM\Cron\Schedule_Runner;
use BAM\Database\Repositories\Job_Repository;
use BAM\Database\Repositories\Schedule_Repository;
use BAM\Filters\Filter_Registry;
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

		if ( isset( $_POST['bam_save_schedule'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			self::save_schedule();
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type     = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'run';
		$is_schedule = ( 'schedule' === $type );
		$edit_id  = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$schedule = ( $is_schedule && $edit_id ) ? Schedule_Repository::find( $edit_id ) : null;

		self::header( __( 'Jobs', 'bulk-actions-manager' ) );

		if ( isset( $_GET['cancelled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Job cancelled.', 'bulk-actions-manager' ) . '</p></div>';
		}
		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Schedule saved.', 'bulk-actions-manager' ) . '</p></div>';
		}
		if ( isset( $_GET['deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Schedule deleted.', 'bulk-actions-manager' ) . '</p></div>';
		}
		if ( isset( $_GET['ran'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Schedule triggered.', 'bulk-actions-manager' ) . '</p></div>';
		}

		if ( $is_schedule ) {
			$show_form = $edit_id || isset( $_GET['add'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=bam-jobs&type=schedule&add=1' ) ); ?>">
					<?php esc_html_e( 'Add Schedule', 'bulk-actions-manager' ); ?>
				</a>
			</p>
			<?php
			if ( $show_form ) {
				self::render_schedule_form( $schedule );
			}
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
		if ( isset( $_GET['bam_action'] ) && 'cancel_job' === $_GET['bam_action'] && ! empty( $_GET['job_id'] ) ) {
			$job_id = absint( $_GET['job_id'] );
			check_admin_referer( 'bam_cancel_job_' . $job_id );
			( new Job_Manager() )->cancel( $job_id );
			wp_safe_redirect( admin_url( 'admin.php?page=bam-jobs&cancelled=1' ) );
			exit;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['bam_action'] ) || empty( $_GET['schedule_id'] ) ) {
			return;
		}

		$schedule_id = absint( $_GET['schedule_id'] );
		$action      = sanitize_key( wp_unslash( $_GET['bam_action'] ) );

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
	 * Save schedule from POST.
	 */
	private static function save_schedule() {
		check_admin_referer( 'bam_save_schedule' );

		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'Permission denied.', 'bulk-actions-manager' ) );
		}

		$id          = isset( $_POST['schedule_id'] ) ? absint( $_POST['schedule_id'] ) : 0;
		$name        = isset( $_POST['schedule_name'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_name'] ) ) : '';
		$cron        = isset( $_POST['cron_expression'] ) ? sanitize_key( wp_unslash( $_POST['cron_expression'] ) ) : 'daily';
		$post_type   = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : 'post';
		$post_status = isset( $_POST['post_status'] ) ? sanitize_key( wp_unslash( $_POST['post_status'] ) ) : 'publish';
		$action_type = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';
		$is_active   = ! empty( $_POST['is_active'] ) ? 1 : 0;

		$filter = array(
			'post_type'  => array( $post_type ),
			'logic'      => 'AND',
			'conditions' => array(
				array(
					'type'     => 'status',
					'operator' => 'in',
					'value'    => array( $post_status ),
				),
			),
		);

		$data = array(
			'name'            => $name,
			'cron_expression' => $cron,
			'is_active'       => $is_active,
			'action_type'     => $action_type,
			'action_payload'  => array(),
			'filter_payload'  => $filter,
			'next_run_at'     => Schedule_Runner::calculate_next_run( $cron ),
		);

		if ( $id ) {
			Schedule_Repository::update( $id, $data );
		} else {
			$id = Schedule_Repository::create( $data );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=bam-jobs&type=schedule&edit=' . (int) $id . '&saved=1' ) );
		exit;
	}

	/**
	 * Render add/edit schedule form.
	 *
	 * @param object|null $schedule Schedule row.
	 */
	private static function render_schedule_form( $schedule ) {
		$registry   = new Action_Registry();
		$post_types = Filter_Registry::get_post_types();
		$statuses   = get_post_stati();

		$post_type   = 'post';
		$post_status = 'publish';
		if ( $schedule ) {
			$filter = Sanitizer::json_decode( $schedule->filter_payload );
			if ( ! empty( $filter['post_type'][0] ) ) {
				$post_type = $filter['post_type'][0];
			}
			if ( ! empty( $filter['conditions'][0]['value'][0] ) ) {
				$post_status = $filter['conditions'][0]['value'][0];
			}
		}
		?>
		<h2><?php echo esc_html( $schedule ? __( 'Edit Schedule', 'bulk-actions-manager' ) : __( 'Add Schedule', 'bulk-actions-manager' ) ); ?></h2>
		<form method="post">
					<?php wp_nonce_field( 'bam_save_schedule' ); ?>
					<input type="hidden" name="bam_save_schedule" value="1" />
					<input type="hidden" name="schedule_id" value="<?php echo $schedule ? (int) $schedule->id : 0; ?>" />
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="schedule_name"><?php esc_html_e( 'Name', 'bulk-actions-manager' ); ?></label></th>
							<td><input type="text" name="schedule_name" id="schedule_name" class="regular-text" value="<?php echo $schedule ? esc_attr( $schedule->name ) : ''; ?>" required /></td>
						</tr>
						<tr>
							<th scope="row"><label for="cron_expression"><?php esc_html_e( 'Frequency', 'bulk-actions-manager' ); ?></label></th>
							<td>
								<select name="cron_expression" id="cron_expression">
									<?php
									$cron = $schedule ? $schedule->cron_expression : 'daily';
									foreach ( array( 'hourly', 'daily', 'weekly', 'monthly' ) as $freq ) :
										?>
										<option value="<?php echo esc_attr( $freq ); ?>" <?php selected( $cron, $freq ); ?>><?php echo esc_html( ucfirst( $freq ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="post_type"><?php esc_html_e( 'Content Type', 'bulk-actions-manager' ); ?></label></th>
							<td>
								<select name="post_type" id="post_type">
									<?php foreach ( $post_types as $slug => $label ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $post_type, $slug ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="post_status"><?php esc_html_e( 'Post Status', 'bulk-actions-manager' ); ?></label></th>
							<td>
								<select name="post_status" id="post_status">
									<?php foreach ( $statuses as $slug => $label ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $post_status, $slug ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="action_type"><?php esc_html_e( 'Action', 'bulk-actions-manager' ); ?></label></th>
							<td>
								<select name="action_type" id="action_type" required>
									<?php
									$selected_action = $schedule ? $schedule->action_type : 'status.draft';
									foreach ( $registry->get_grouped() as $group => $actions ) :
										?>
										<optgroup label="<?php echo esc_attr( $group ); ?>">
											<?php foreach ( $actions as $action ) : ?>
												<option value="<?php echo esc_attr( $action['id'] ); ?>" <?php selected( $selected_action, $action['id'] ); ?>>
													<?php echo esc_html( $action['label'] ); ?>
												</option>
											<?php endforeach; ?>
										</optgroup>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Active', 'bulk-actions-manager' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="is_active" value="1" <?php checked( ! $schedule || (int) $schedule->is_active ); ?> />
									<?php esc_html_e( 'Run this schedule automatically', 'bulk-actions-manager' ); ?>
								</label>
							</td>
						</tr>
					</table>
					<p>
						<?php submit_button( __( 'Save Schedule', 'bulk-actions-manager' ), 'primary', 'submit', false ); ?>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bam-jobs&type=schedule' ) ); ?>"><?php esc_html_e( 'Cancel', 'bulk-actions-manager' ); ?></a>
					</p>
				</form>
		<?php
	}

	/**
	 * Render job detail view.
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
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=bam-jobs' ) ); ?>">&larr; <?php esc_html_e( 'Back to Jobs', 'bulk-actions-manager' ); ?></a></p>

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
					<th scope="row"><?php esc_html_e( 'Progress', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( (string) $formatted['processed_items'] . ' / ' . $formatted['total_items'] . ' (' . $formatted['percent'] . '%)' ); ?></td>
				</tr>
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
		<div id="bam-job-detail" data-job-id="<?php echo esc_attr( (string) $job_id ); ?>">
			<?php Admin_UI::postbox_open( 'bam-job-progress-detail', __( 'Job Progress', 'bulk-actions-manager' ) ); ?>
			<div class="bam-job-progress">
				<progress id="bam-progress-bar" max="100" value="<?php echo esc_attr( (string) $formatted['percent'] ); ?>"></progress>
				<p id="bam-progress-text" class="description"><?php echo esc_html( (string) $formatted['percent'] . '% — ' . $job->status ); ?></p>
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

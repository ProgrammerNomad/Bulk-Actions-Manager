<?php
/**
 * Logs admin page.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

use BAM\Admin\Admin_UI;
use BAM\Admin\List_Tables\Logs_List_Table;
use BAM\Database\Repositories\Job_Item_Repository;
use BAM\Database\Repositories\Log_Repository;
use BAM\Undo\Undo_Manager;
use BAM\Utils\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class Page_Logs
 */
class Page_Logs extends Page_Base {

	/**
	 * Render logs page.
	 */
	public static function render() {
		self::handle_actions();

		$log_id = isset( $_GET['log_id'] ) ? absint( $_GET['log_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $log_id ) {
			self::render_detail( $log_id );
			return;
		}

		self::header( __( 'Logs', 'bulk-actions-manager' ) );

		if ( isset( $_GET['undo_started'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Undo job started.', 'bulk-actions-manager' ) . '</p></div>';
		}

		$list_table = new Logs_List_Table();
		$list_table->prepare_items();
		?>
		<form method="get">
			<input type="hidden" name="page" value="bam-logs" />
			<?php
			$list_table->views();
			$list_table->search_box( __( 'Search Logs', 'bulk-actions-manager' ), 'bam-log-search' );
			$list_table->display();
			?>
		</form>
		<?php
		self::footer();
	}

	/**
	 * Handle undo action.
	 */
	private static function handle_actions() {
		if ( ! Capabilities::current_user_can() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['bam_action'] ) && 'undo_log' === $_GET['bam_action'] && ! empty( $_GET['log_id'] ) ) {
			$log_id = absint( $_GET['log_id'] );
			check_admin_referer( 'bam_undo_log_' . $log_id );

			$result = ( new Undo_Manager() )->create_undo_job( $log_id );
			if ( is_wp_error( $result ) ) {
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'       => 'bam-logs',
							'log_id'     => $log_id,
							'undo_error' => rawurlencode( $result->get_error_message() ),
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
			}

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'   => 'bam-jobs',
						'job_id' => (int) $result['job_id'],
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
	}

	/**
	 * Render log detail.
	 *
	 * @param int $log_id Log ID.
	 */
	private static function render_detail( $log_id ) {
		$log = Log_Repository::find( $log_id );
		if ( ! $log ) {
			self::header( __( 'Log Not Found', 'bulk-actions-manager' ) );
			echo '<p>' . esc_html__( 'Log not found.', 'bulk-actions-manager' ) . '</p>';
			self::footer();
			return;
		}

		self::header(
			sprintf(
				/* translators: %d: log ID */
				__( 'Log #%d', 'bulk-actions-manager' ),
				$log_id
			)
		);

		if ( isset( $_GET['undo_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error"><p>' . esc_html( wp_unslash( $_GET['undo_error'] ) ) . '</p></div>';
		}

		$user = get_userdata( (int) $log->user_id );
		?>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=bam-logs' ) ); ?>">&larr; <?php esc_html_e( 'Back to Logs', 'bulk-actions-manager' ); ?></a></p>

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Job ID', 'bulk-actions-manager' ); ?></th>
					<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=bam-jobs&job_id=' . (int) $log->job_id ) ); ?>"><?php echo (int) $log->job_id; ?></a></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'User', 'bulk-actions-manager' ); ?></th>
					<td><?php echo $user ? esc_html( $user->display_name ) : '-'; ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Action', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( Admin_UI::action_label( $log->action_type ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Affected Records', 'bulk-actions-manager' ); ?></th>
					<td><?php echo (int) $log->affected_count; ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Failed Records', 'bulk-actions-manager' ); ?></th>
					<td><?php echo (int) $log->failed_count; ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Undo Status', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( $log->undo_status ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Date', 'bulk-actions-manager' ); ?></th>
					<td><?php echo esc_html( $log->created_at ); ?></td>
				</tr>
			</tbody>
		</table>

		<?php if ( 'available' === $log->undo_status ) : ?>
			<p>
				<a class="button button-primary bam-undo-log-link" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=bam-logs&bam_action=undo_log&log_id=' . $log_id ), 'bam_undo_log_' . $log_id ) ); ?>">
					<?php esc_html_e( 'Undo Job', 'bulk-actions-manager' ); ?>
				</a>
			</p>
		<?php endif; ?>

		<?php
		if ( ! empty( $log->job_id ) ) {
			self::render_job_item_section(
				Job_Item_Repository::list_by_status( (int) $log->job_id, 'failed', 50 ),
				'bam-job-errors-list',
				__( 'Errors', 'bulk-actions-manager' )
			);
			self::render_job_item_section(
				Job_Item_Repository::list_by_status( (int) $log->job_id, 'skipped', 50 ),
				'bam-job-skipped-list',
				__( 'Skipped', 'bulk-actions-manager' )
			);
		}
		?>
		<?php
		self::footer();
	}

	/**
	 * Render a list of job item rows for log detail.
	 *
	 * @param array<int, object> $items Job item rows.
	 * @param string             $class Wrapper CSS class.
	 * @param string             $title Section title.
	 */
	private static function render_job_item_section( array $items, $class, $title ) {
		if ( empty( $items ) ) {
			return;
		}

		echo '<div class="' . esc_attr( $class ) . '">';
		echo '<p class="bam-item-list-heading"><strong>' . esc_html( $title ) . '</strong></p>';
		echo '<ul>';
		foreach ( $items as $item ) {
			printf(
				'<li>#%d: %s</li>',
				(int) $item->object_id,
				esc_html( $item->error_message ?: '' )
			);
		}
		echo '</ul></div>';
	}
}

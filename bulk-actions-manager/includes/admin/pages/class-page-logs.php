<?php
/**
 * Logs admin page.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

defined( 'ABSPATH' ) || exit;

/**
 * Class Page_Logs
 */
class Page_Logs extends Page_Base {

	/**
	 * Render logs page.
	 */
	public static function render() {
		$log_id = isset( $_GET['log_id'] ) ? absint( $_GET['log_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $log_id ) {
			self::render_detail( $log_id );
			return;
		}

		self::header( __( 'Logs', 'bulk-actions-manager' ) );
		?>
		<div id="bam-logs-list">
			<table class="widefat striped" id="bam-logs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Log ID', 'bulk-actions-manager' ); ?></th>
						<th><?php esc_html_e( 'Job ID', 'bulk-actions-manager' ); ?></th>
						<th><?php esc_html_e( 'User', 'bulk-actions-manager' ); ?></th>
						<th><?php esc_html_e( 'Action', 'bulk-actions-manager' ); ?></th>
						<th><?php esc_html_e( 'Affected', 'bulk-actions-manager' ); ?></th>
						<th><?php esc_html_e( 'Date', 'bulk-actions-manager' ); ?></th>
						<th><?php esc_html_e( 'Undo Status', 'bulk-actions-manager' ); ?></th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			if ( typeof bamApi === 'undefined' ) return;
			bamApi.get('logs').then(function(data) {
				var tbody = document.querySelector('#bam-logs-table tbody');
				if ( !tbody || !data.items ) return;
				tbody.innerHTML = data.items.map(function(log) {
					return '<tr><td>' + log.id + '</td><td><a href="admin.php?page=bam-jobs&job_id=' + log.job_id + '">' + log.job_id + '</a></td><td>' + log.user + '</td><td>' + log.action_type + '</td><td>' + log.affected_count + '</td><td>' + log.created_at + '</td><td>' + log.undo_status + '</td></tr>';
				}).join('');
			});
		});
		</script>
		<?php
		self::footer();
	}

	/**
	 * Render log detail.
	 *
	 * @param int $log_id Log ID.
	 */
	private static function render_detail( $log_id ) {
		self::header( sprintf(
			/* translators: %d: log ID */
			__( 'Log #%d', 'bulk-actions-manager' ),
			$log_id
		) );
		?>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=bam-logs' ) ); ?>">&larr; <?php esc_html_e( 'Back to Logs', 'bulk-actions-manager' ); ?></a></p>
		<div id="bam-log-detail" data-log-id="<?php echo esc_attr( (string) $log_id ); ?>">
			<div class="bam-loading"><?php esc_html_e( 'Loading...', 'bulk-actions-manager' ); ?></div>
		</div>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			if ( typeof bamApi === 'undefined' ) return;
			var logId = <?php echo (int) $log_id; ?>;
			bamApi.get('logs/' + logId).then(function(log) {
				var el = document.getElementById('bam-log-detail');
				if ( !el ) return;
				var html = '<table class="form-table"><tbody>';
				html += '<tr><th><?php echo esc_js( __( 'Action', 'bulk-actions-manager' ) ); ?></th><td>' + log.action_type + '</td></tr>';
				html += '<tr><th><?php echo esc_js( __( 'Affected Records', 'bulk-actions-manager' ) ); ?></th><td>' + log.affected_count + '</td></tr>';
				html += '<tr><th><?php echo esc_js( __( 'Undo Status', 'bulk-actions-manager' ) ); ?></th><td>' + log.undo_status + '</td></tr>';
				html += '</tbody></table>';
				if ( log.undo_status === 'available' ) {
					html += '<p><button type="button" class="button button-primary" id="bam-undo-job" data-log-id="' + log.id + '"><?php echo esc_js( __( 'Undo Job', 'bulk-actions-manager' ) ); ?></button></p>';
				}
				el.innerHTML = html;
				var undoBtn = document.getElementById('bam-undo-job');
				if ( undoBtn ) {
					undoBtn.addEventListener('click', function() {
						if ( !confirm(bamAdmin.i18n.confirm) ) return;
						bamApi.post('logs/' + logId + '/undo', {}).then(function(res) {
							alert('<?php echo esc_js( __( 'Undo job started.', 'bulk-actions-manager' ) ); ?> #' + res.job_id);
							window.location.href = 'admin.php?page=bam-jobs&job_id=' + res.job_id;
						});
					});
				}
			});
		});
		</script>
		<?php
		self::footer();
	}
}

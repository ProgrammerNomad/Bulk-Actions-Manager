<?php
/**
 * Scheduled jobs admin page.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

defined( 'ABSPATH' ) || exit;

/**
 * Class Page_Scheduled
 */
class Page_Scheduled extends Page_Base {

	/**
	 * Render scheduled jobs page.
	 */
	public static function render() {
		self::header( __( 'Scheduled Jobs', 'bulk-actions-manager' ) );
		?>
		<div id="bam-scheduled">
			<p>
				<button type="button" class="button button-primary" id="bam-add-schedule"><?php esc_html_e( 'Add Schedule', 'bulk-actions-manager' ); ?></button>
			</p>
			<table class="widefat striped" id="bam-schedules-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'bulk-actions-manager' ); ?></th>
						<th><?php esc_html_e( 'Name', 'bulk-actions-manager' ); ?></th>
						<th><?php esc_html_e( 'Action', 'bulk-actions-manager' ); ?></th>
						<th><?php esc_html_e( 'Frequency', 'bulk-actions-manager' ); ?></th>
						<th><?php esc_html_e( 'Next Run', 'bulk-actions-manager' ); ?></th>
						<th><?php esc_html_e( 'Active', 'bulk-actions-manager' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bulk-actions-manager' ); ?></th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			if ( typeof bamApi === 'undefined' ) return;
			bamApi.get('schedules').then(function(data) {
				var tbody = document.querySelector('#bam-schedules-table tbody');
				if ( !tbody ) return;
				if ( !data.items || !data.items.length ) {
					tbody.innerHTML = '<tr><td colspan="7"><?php echo esc_js( __( 'No scheduled jobs yet.', 'bulk-actions-manager' ) ); ?></td></tr>';
					return;
				}
				tbody.innerHTML = data.items.map(function(s) {
					return '<tr><td>' + s.id + '</td><td>' + s.name + '</td><td>' + s.action_type + '</td><td>' + s.cron_expression + '</td><td>' + (s.next_run_at || '-') + '</td><td>' + (s.is_active ? 'Yes' : 'No') + '</td><td><button class="button bam-delete-schedule" data-id="' + s.id + '">Delete</button></td></tr>';
				}).join('');
			});
		});
		</script>
		<?php
		self::footer();
	}
}

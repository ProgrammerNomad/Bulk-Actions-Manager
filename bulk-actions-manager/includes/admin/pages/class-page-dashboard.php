<?php
/**
 * Dashboard admin page.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

defined( 'ABSPATH' ) || exit;

/**
 * Class Page_Dashboard
 */
class Page_Dashboard extends Page_Base {

	/**
	 * Render dashboard page.
	 */
	public static function render() {
		self::header( __( 'Bulk Actions Manager - Dashboard', 'bulk-actions-manager' ) );
		?>
		<div id="bam-dashboard" class="bam-dashboard">
			<div class="bam-widgets bam-widgets--grid">
				<div class="bam-widget" id="bam-widget-stats">
					<h2><?php esc_html_e( 'Statistics', 'bulk-actions-manager' ); ?></h2>
					<div class="bam-widget__body bam-loading"><?php esc_html_e( 'Loading...', 'bulk-actions-manager' ); ?></div>
				</div>
				<div class="bam-widget" id="bam-widget-recent-jobs">
					<h2><?php esc_html_e( 'Recent Jobs', 'bulk-actions-manager' ); ?></h2>
					<div class="bam-widget__body bam-loading"><?php esc_html_e( 'Loading...', 'bulk-actions-manager' ); ?></div>
				</div>
				<div class="bam-widget" id="bam-widget-health">
					<h2><?php esc_html_e( 'System Health', 'bulk-actions-manager' ); ?></h2>
					<div class="bam-widget__body bam-loading"><?php esc_html_e( 'Loading...', 'bulk-actions-manager' ); ?></div>
				</div>
				<div class="bam-widget" id="bam-widget-undo">
					<h2><?php esc_html_e( 'Undo Summary', 'bulk-actions-manager' ); ?></h2>
					<div class="bam-widget__body bam-loading"><?php esc_html_e( 'Loading...', 'bulk-actions-manager' ); ?></div>
				</div>
			</div>
		</div>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			if ( typeof bamApi === 'undefined' ) return;
			bamApi.get('dashboard').then(function(data) {
				bamDashboard.render(data);
			}).catch(function() {
				document.querySelectorAll('.bam-loading').forEach(function(el) {
					el.textContent = '<?php echo esc_js( __( 'Failed to load.', 'bulk-actions-manager' ) ); ?>';
				});
			});
		});
		</script>
		<?php
		self::footer();
	}
}

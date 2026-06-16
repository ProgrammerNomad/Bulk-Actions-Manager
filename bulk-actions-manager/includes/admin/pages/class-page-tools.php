<?php
/**
 * Tools admin page.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

defined( 'ABSPATH' ) || exit;

/**
 * Class Page_Tools
 */
class Page_Tools extends Page_Base {

	/**
	 * Available tools.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function get_tools() {
		return array(
			'remove_revisions'      => array(
				'label'       => __( 'Remove Revisions', 'bulk-actions-manager' ),
				'description' => __( 'Delete post revisions to free database space.', 'bulk-actions-manager' ),
				'group'       => 'cleanup',
			),
			'remove_auto_drafts'    => array(
				'label'       => __( 'Remove Auto Drafts', 'bulk-actions-manager' ),
				'description' => __( 'Delete auto-draft posts.', 'bulk-actions-manager' ),
				'group'       => 'cleanup',
			),
			'empty_trash'           => array(
				'label'       => __( 'Empty Trash', 'bulk-actions-manager' ),
				'description' => __( 'Permanently delete all trashed posts.', 'bulk-actions-manager' ),
				'group'       => 'cleanup',
			),
			'orphan_attachments'    => array(
				'label'       => __( 'Orphan Attachments', 'bulk-actions-manager' ),
				'description' => __( 'Find and remove attachments not attached to any post.', 'bulk-actions-manager' ),
				'group'       => 'orphan',
			),
			'orphan_metadata'       => array(
				'label'       => __( 'Orphan Metadata', 'bulk-actions-manager' ),
				'description' => __( 'Remove post meta for deleted posts.', 'bulk-actions-manager' ),
				'group'       => 'orphan',
			),
			'export_jobs'           => array(
				'label'       => __( 'Export Jobs', 'bulk-actions-manager' ),
				'description' => __( 'Download all jobs as JSON.', 'bulk-actions-manager' ),
				'group'       => 'export',
			),
			'export_logs'           => array(
				'label'       => __( 'Export Logs', 'bulk-actions-manager' ),
				'description' => __( 'Download all logs as JSON.', 'bulk-actions-manager' ),
				'group'       => 'export',
			),
		);
	}

	/**
	 * Render tools page.
	 */
	public static function render() {
		self::header( __( 'Tools', 'bulk-actions-manager' ) );

		$groups = array(
			'cleanup' => __( 'Cleanup Tools', 'bulk-actions-manager' ),
			'orphan'  => __( 'Orphan Cleanup', 'bulk-actions-manager' ),
			'export'  => __( 'Export Tools', 'bulk-actions-manager' ),
		);

		foreach ( $groups as $group_key => $group_label ) {
			echo '<h2>' . esc_html( $group_label ) . '</h2>';
			echo '<div class="bam-tools-grid">';
			foreach ( self::get_tools() as $tool_id => $tool ) {
				if ( $tool['group'] !== $group_key ) {
					continue;
				}
				?>
				<div class="bam-tool-card">
					<h3><?php echo esc_html( $tool['label'] ); ?></h3>
					<p><?php echo esc_html( $tool['description'] ); ?></p>
					<button type="button" class="button bam-run-tool" data-tool="<?php echo esc_attr( $tool_id ); ?>">
						<?php esc_html_e( 'Run', 'bulk-actions-manager' ); ?>
					</button>
					<span class="bam-tool-result" id="bam-tool-result-<?php echo esc_attr( $tool_id ); ?>"></span>
				</div>
				<?php
			}
			echo '</div>';
		}
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('.bam-run-tool').forEach(function(btn) {
				btn.addEventListener('click', function() {
					if ( !confirm(bamAdmin.i18n.confirm) ) return;
					var tool = btn.dataset.tool;
					var resultEl = document.getElementById('bam-tool-result-' + tool);
					btn.disabled = true;
					bamApi.post('tools/' + tool, {}).then(function(res) {
						if ( resultEl ) resultEl.textContent = res.message || bamAdmin.i18n.completed;
					}).catch(function() {
						if ( resultEl ) resultEl.textContent = bamAdmin.i18n.error;
					}).finally(function() {
						btn.disabled = false;
					});
				});
			});
		});
		</script>
		<?php
		self::footer();
	}
}

<?php
/**
 * Settings admin page.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

use BAM\Admin\Settings_Register;

defined( 'ABSPATH' ) || exit;

/**
 * Class Page_Settings
 */
class Page_Settings extends Page_Base {

	/**
	 * Render settings page.
	 */
	public static function render() {
		self::header( __( 'Settings', 'bulk-actions-manager' ) );

		echo '<p class="description">';
		esc_html_e( 'Configure default batch processing, undo retention, and audit logging for Bulk Actions Manager.', 'bulk-actions-manager' );
		echo '</p>';

		settings_errors( Settings_Register::OPTION );
		settings_errors( Settings_Register::UNINSTALL_OPTION );
		?>
		<form method="post" action="options.php" id="bam-settings-form">
			<?php
			settings_fields( Settings_Register::GROUP );
			do_settings_sections( Settings_Register::PAGE );
			submit_button();
			?>
		</form>
		<?php
		self::footer();
	}
}

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

		settings_errors( Settings_Register::OPTION );
		?>
		<form method="post" action="options.php">
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

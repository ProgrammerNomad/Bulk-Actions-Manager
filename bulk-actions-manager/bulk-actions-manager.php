<?php
/**
 * Plugin Name:       Bulk Actions Manager
 * Plugin URI:        https://github.com/ProgrammerNomad/Bulk-Actions-Manager
 * Description:       Filter, preview, modify, export, schedule, and manage large amounts of WordPress content safely using batch processing.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            NomadProgrammer
 * Author URI:        https://github.com/ProgrammerNomad
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bulk-actions-manager
 * Domain Path:       /languages
 *
 * @package BulkActionsManager
 */

defined( 'ABSPATH' ) || exit;

define( 'BAM_VERSION', '1.0.1' );
define( 'BAM_PLUGIN_FILE', __FILE__ );
define( 'BAM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BAM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BAM_DB_VERSION', '1' );
define( 'BAM_REST_NAMESPACE', 'bam/v1' );
define( 'BAM_CAPABILITY', 'manage_bulk_actions_manager' );

require_once BAM_PLUGIN_DIR . 'includes/class-autoloader.php';

BAM\Autoloader::register( BAM_PLUGIN_DIR . 'includes/', 'BAM\\' );

register_activation_hook( __FILE__, array( 'BAM\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BAM\\Deactivator', 'deactivate' ) );

/**
 * Returns the main plugin instance.
 *
 * @return BAM\Plugin
 */
function bam() {
	return BAM\Plugin::instance();
}

bam();

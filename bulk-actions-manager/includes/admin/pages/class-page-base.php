<?php
/**
 * Base admin page helper.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

defined( 'ABSPATH' ) || exit;

/**
 * Class Page_Base
 */
abstract class Page_Base {

	/**
	 * Render page wrapper start.
	 *
	 * @param string $title Page title.
	 */
	protected static function header( $title ) {
		echo '<div class="wrap bam-wrap">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
	}

	/**
	 * Render page wrapper end.
	 */
	protected static function footer() {
		echo '</div>';
	}
}

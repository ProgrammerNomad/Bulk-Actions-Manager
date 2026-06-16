<?php
/**
 * Base WP_List_Table for Bulk Actions Manager.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\List_Tables;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class List_Table_Base
 */
abstract class List_Table_Base extends \WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $args List table args.
	 */
	public function __construct( array $args = array() ) {
		$defaults = array(
			'singular' => 'item',
			'plural'   => 'items',
			'ajax'     => false,
		);
		parent::__construct( \wp_parse_args( $args, $defaults ) );
	}

	/**
	 * Default column output.
	 *
	 * @param object $item        Item row.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? \esc_html( (string) $item->$column_name ) : '';
	}

	/**
	 * Message when no items found.
	 */
	public function no_items() {
		\esc_html_e( 'No items found.', 'bulk-actions-manager' );
	}

	/**
	 * Get per-page option name for this screen.
	 *
	 * @return string
	 */
	protected function get_per_page_option() {
		return 'bam_items_per_page';
	}

	/**
	 * Items per page.
	 *
	 * @return int
	 */
	protected function get_items_per_page_value() {
		return (int) $this->get_items_per_page( $this->get_per_page_option(), 20 );
	}

	/**
	 * Build admin URL for this list page.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return string
	 */
	abstract protected function page_url( array $args = array() );

	/**
	 * Render subsubsub view link.
	 *
	 * @param string $label   Label.
	 * @param string $slug    View slug (empty = all).
	 * @param int    $count   Count.
	 * @param string $current Current view.
	 * @param string $param   Query param name.
	 * @return string
	 */
	protected function view_link( $label, $slug, $count, $current, $param = 'status' ) {
		$args = array( 'page' => $this->get_page_slug() );
		if ( $slug ) {
			$args[ $param ] = $slug;
		}
		$url   = \add_query_arg( $args, \admin_url( 'admin.php' ) );
		$class = ( (string) $current === (string) $slug ) ? ' class="current"' : '';
		return '<a href="' . \esc_url( $url ) . '"' . $class . '>' . \esc_html( $label ) . ' <span class="count">(' . (int) $count . ')</span></a>';
	}

	/**
	 * Admin page slug.
	 *
	 * @return string
	 */
	abstract protected function get_page_slug();
}

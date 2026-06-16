<?php
/**
 * Posts preview list table for New Job page.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\List_Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Class Posts_Preview_List_Table
 */
class Posts_Preview_List_Table extends List_Table_Base {

	/**
	 * Total matching posts.
	 *
	 * @var int
	 */
	private $total_items_count = 0;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'post',
				'plural'   => 'posts',
				'ajax'     => false,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'title'      => \__( 'Title', 'bulk-actions-manager' ),
			'author'     => \__( 'Author', 'bulk-actions-manager' ),
			'categories' => \__( 'Categories', 'bulk-actions-manager' ),
			'tags'       => \__( 'Tags', 'bulk-actions-manager' ),
			'date'       => \__( 'Date', 'bulk-actions-manager' ),
			'status'     => \__( 'Status', 'bulk-actions-manager' ),
		);
	}

	/**
	 * Set preview data.
	 *
	 * @param array<int, int> $ids   Post IDs for current page.
	 * @param int             $total Total matching count.
	 */
	public function set_preview_data( array $ids, $total ) {
		$this->total_items_count = $total;
		$this->items             = array();

		foreach ( $ids as $id ) {
			$post = \get_post( $id );
			if ( ! $post ) {
				continue;
			}
			$this->items[] = $post;
		}

		$per_page = $this->get_items_per_page_value();

		$this->_column_headers = array( $this->get_columns(), array(), array() );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) \ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepare_items() {
		// Data set via set_preview_data().
	}

	/**
	 * {@inheritDoc}
	 */
	public function no_items() {
		\esc_html_e( 'No posts found matching your filters.', 'bulk-actions-manager' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function column_cb( $item ) {
		return \sprintf( '<input type="checkbox" name="post[]" value="%d" disabled />', (int) $item->ID );
	}

	/**
	 * Title column.
	 *
	 * @param \WP_Post $item Post.
	 * @return string
	 */
	protected function column_title( $item ) {
		$edit_link = \get_edit_post_link( $item->ID );
		$title     = $item->post_title ? $item->post_title : \__( '(no title)', 'bulk-actions-manager' );

		if ( $edit_link ) {
			return \sprintf(
				'<strong><a class="row-title" href="%s" aria-label="%s">%s</a></strong>',
				\esc_url( $edit_link ),
				\esc_attr( $title ),
				\esc_html( $title )
			);
		}

		return '<strong>' . \esc_html( $title ) . '</strong>';
	}

	/**
	 * Author column.
	 *
	 * @param \WP_Post $item Post.
	 * @return string
	 */
	protected function column_author( $item ) {
		$user = \get_userdata( (int) $item->post_author );
		return $user ? \esc_html( $user->display_name ) : '-';
	}

	/**
	 * Categories column.
	 *
	 * @param \WP_Post $item Post.
	 * @return string
	 */
	protected function column_categories( $item ) {
		$terms = \get_the_category( $item->ID );
		if ( empty( $terms ) ) {
			return '—';
		}
		return \esc_html( \implode( ', ', \wp_list_pluck( $terms, 'name' ) ) );
	}

	/**
	 * Tags column.
	 *
	 * @param \WP_Post $item Post.
	 * @return string
	 */
	protected function column_tags( $item ) {
		$terms = \get_the_tags( $item->ID );
		if ( empty( $terms ) || \is_wp_error( $terms ) ) {
			return '—';
		}
		return \esc_html( \implode( ', ', \wp_list_pluck( $terms, 'name' ) ) );
	}

	/**
	 * Date column.
	 *
	 * @param \WP_Post $item Post.
	 * @return string
	 */
	protected function column_date( $item ) {
		return \esc_html( \get_the_date( '', $item ) );
	}

	/**
	 * Status column.
	 *
	 * @param \WP_Post $item Post.
	 * @return string
	 */
	protected function column_status( $item ) {
		$status_obj = \get_post_status_object( $item->post_status );
		return $status_obj ? \esc_html( $status_obj->label ) : \esc_html( $item->post_status );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_page_slug() {
		return 'bam-new-job';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function page_url( array $args = array() ) {
		return \add_query_arg( \wp_parse_args( $args, array( 'page' => 'bam-new-job' ) ), \admin_url( 'admin.php' ) );
	}
}

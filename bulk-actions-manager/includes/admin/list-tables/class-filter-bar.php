<?php
/**
 * Edit.php-style filter bar for New Job preview.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\List_Tables;

use BAM\Filters\Filter_Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Class Filter_Bar
 */
class Filter_Bar {

	/**
	 * Load WordPress admin template helpers when needed.
	 */
	private static function load_admin_deps() {
		if ( ! \function_exists( 'wp_dropdown_categories' ) ) {
			require_once ABSPATH . 'wp-admin/includes/template.php';
		}

		if ( ! \function_exists( 'wp_dropdown_users' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
	}

	/**
	 * Parse admin request to filter payload.
	 *
	 * @param array<string, mixed> $request Request args.
	 * @return array<string, mixed>
	 */
	public static function parse_request_to_payload( array $request ) {
		return \BAM\Filters\Filter_Compiler::from_admin_request( $request );
	}

	/**
	 * Resolve content type from a BAM admin request (avoids core post_type query var).
	 *
	 * @param array<string, mixed> $request Request args.
	 * @return string
	 */
	public static function get_request_post_type( array $request ) {
		if ( ! empty( $request['bam_post_type'] ) ) {
			return \sanitize_key( (string) $request['bam_post_type'] );
		}
		if ( ! empty( $request['post_type'] ) ) {
			return \sanitize_key( (string) $request['post_type'] );
		}
		return 'post';
	}

	/**
	 * Render filter controls.
	 *
	 * @param array<string, mixed> $request Current GET args.
	 */
	public static function render( array $request ) {
		self::load_admin_deps();

		$post_type       = self::get_request_post_type( $request );
		$post_status     = ! empty( $request['post_status'] ) ? \sanitize_key( $request['post_status'] ) : 'all';
		$selected_m      = ! empty( $request['m'] ) ? \sanitize_text_field( (string) $request['m'] ) : 0;
		$selected_cat    = ! empty( $request['cat'] ) ? \absint( $request['cat'] ) : 0;
		$selected_author = ! empty( $request['author'] ) ? \absint( $request['author'] ) : 0;
		$search          = ! empty( $request['s'] ) ? \sanitize_text_field( (string) $request['s'] ) : '';
		$seo_filter      = ! empty( $request['seo-filter'] ) ? \sanitize_key( (string) $request['seo-filter'] ) : '';
		$rm_filter       = ! empty( $request['rankmath-filter'] ) ? \sanitize_key( (string) $request['rankmath-filter'] ) : '';

		self::render_status_views( $post_type, $post_status, $request );
		?>
		<p class="search-box">
			<label class="screen-reader-text" for="post-search-input"><?php \esc_html_e( 'Search Posts', 'bulk-actions-manager' ); ?></label>
			<input type="search" id="post-search-input" name="s" value="<?php echo \esc_attr( $search ); ?>" />
			<input type="submit" id="search-submit" class="button" value="<?php \esc_attr_e( 'Search Posts', 'bulk-actions-manager' ); ?>" />
		</p>

		<div class="tablenav top">
			<div class="alignleft actions">
				<label for="filter-by-date" class="screen-reader-text"><?php \esc_html_e( 'Filter by date', 'bulk-actions-manager' ); ?></label>
				<select name="m" id="filter-by-date">
					<option value="0"<?php \selected( $selected_m, 0 ); ?>><?php \esc_html_e( 'All dates', 'bulk-actions-manager' ); ?></option>
					<?php self::render_month_options( $post_type, $selected_m ); ?>
				</select>

				<?php
				\wp_dropdown_categories(
					array(
						'show_option_all' => \__( 'All Categories', 'bulk-actions-manager' ),
						'hide_empty'      => 0,
						'name'            => 'cat',
						'id'              => 'cat',
						'selected'        => $selected_cat,
						'hierarchical'    => true,
						'taxonomy'        => 'category',
						'value_field'     => 'term_id',
					)
				);
				?>

				<?php
				\wp_dropdown_users(
					array(
						'show_option_all' => \__( 'All Authors', 'bulk-actions-manager' ),
						'name'            => 'author',
						'selected'        => $selected_author,
					)
				);
				?>

				<?php self::render_post_type_dropdown( $post_type ); ?>

				<?php if ( \defined( 'WPSEO_VERSION' ) ) : ?>
					<label for="seo-filter" class="screen-reader-text"><?php \esc_html_e( 'Yoast SEO filter', 'bulk-actions-manager' ); ?></label>
					<select name="seo-filter" id="seo-filter">
						<option value=""><?php \esc_html_e( 'All SEO Scores', 'bulk-actions-manager' ); ?></option>
						<option value="empty-fk"<?php \selected( $seo_filter, 'empty-fk' ); ?>><?php \esc_html_e( 'Without focus keyword', 'bulk-actions-manager' ); ?></option>
						<option value="missing-seo-title"<?php \selected( $seo_filter, 'missing-seo-title' ); ?>><?php \esc_html_e( 'Without SEO title', 'bulk-actions-manager' ); ?></option>
						<option value="missing-metadesc"<?php \selected( $seo_filter, 'missing-metadesc' ); ?>><?php \esc_html_e( 'Without meta description', 'bulk-actions-manager' ); ?></option>
					</select>
				<?php endif; ?>

				<?php if ( \defined( 'RANK_MATH_VERSION' ) ) : ?>
					<label for="rankmath-filter" class="screen-reader-text"><?php \esc_html_e( 'Rank Math filter', 'bulk-actions-manager' ); ?></label>
					<select name="rankmath-filter" id="rankmath-filter">
						<option value=""><?php \esc_html_e( 'All Rank Math', 'bulk-actions-manager' ); ?></option>
						<option value="missing-focus-keyword"<?php \selected( $rm_filter, 'missing-focus-keyword' ); ?>><?php \esc_html_e( 'Without focus keyword', 'bulk-actions-manager' ); ?></option>
						<option value="missing-description"<?php \selected( $rm_filter, 'missing-description' ); ?>><?php \esc_html_e( 'Without meta description', 'bulk-actions-manager' ); ?></option>
					</select>
				<?php endif; ?>

				<input type="submit" name="bam_refresh" id="post-query-submit" class="button" value="<?php \esc_attr_e( 'Refresh Results', 'bulk-actions-manager' ); ?>" />
			</div>
		</div>

		<?php self::render_advanced_filters( $request ); ?>
		<?php
	}

	/**
	 * Advanced filters accordion (native details/summary).
	 *
	 * @param array<string, mixed> $request Request args.
	 */
	private static function render_advanced_filters( array $request ) {
		$meta_key         = ! empty( $request['bam_meta_key'] ) ? \sanitize_text_field( (string) $request['bam_meta_key'] ) : '';
		$meta_op          = ! empty( $request['bam_meta_op'] ) ? \sanitize_key( (string) $request['bam_meta_op'] ) : 'exists';
		$meta_value_key   = ! empty( $request['bam_meta_value_key'] ) ? \sanitize_text_field( (string) $request['bam_meta_value_key'] ) : '';
		$meta_value       = isset( $request['bam_meta_value'] ) ? \sanitize_text_field( (string) $request['bam_meta_value'] ) : '';
		$meta_value_op    = ! empty( $request['bam_meta_value_op'] ) ? \sanitize_key( (string) $request['bam_meta_value_op'] ) : 'equals';
		$featured         = ! empty( $request['bam_featured'] ) ? \sanitize_key( (string) $request['bam_featured'] ) : '';
		$title_contains   = ! empty( $request['bam_title'] ) ? \sanitize_text_field( (string) $request['bam_title'] ) : '';
		$content_contains = ! empty( $request['bam_content'] ) ? \sanitize_text_field( (string) $request['bam_content'] ) : '';
		$advanced_open    = $meta_key || $meta_value_key || $featured || $title_contains || $content_contains;
		?>
		<details class="bam-advanced-filters"<?php echo $advanced_open ? ' open' : ''; ?>>
			<summary><?php \esc_html_e( 'Advanced Filters', 'bulk-actions-manager' ); ?></summary>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bam-meta-key"><?php \esc_html_e( 'Meta Key', 'bulk-actions-manager' ); ?></label></th>
					<td>
						<input type="text" name="bam_meta_key" id="bam-meta-key" class="regular-text" value="<?php echo \esc_attr( $meta_key ); ?>" />
						<select name="bam_meta_op" id="bam-meta-op">
							<option value="exists"<?php \selected( $meta_op, 'exists' ); ?>><?php \esc_html_e( 'Exists', 'bulk-actions-manager' ); ?></option>
							<option value="missing"<?php \selected( $meta_op, 'missing' ); ?>><?php \esc_html_e( 'Missing', 'bulk-actions-manager' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bam-meta-value-key"><?php \esc_html_e( 'Meta Value', 'bulk-actions-manager' ); ?></label></th>
					<td>
						<input type="text" name="bam_meta_value_key" id="bam-meta-value-key" class="regular-text" placeholder="<?php \esc_attr_e( 'Meta key', 'bulk-actions-manager' ); ?>" value="<?php echo \esc_attr( $meta_value_key ); ?>" />
						<input type="text" name="bam_meta_value" id="bam-meta-value" class="regular-text" placeholder="<?php \esc_attr_e( 'Value', 'bulk-actions-manager' ); ?>" value="<?php echo \esc_attr( $meta_value ); ?>" />
						<select name="bam_meta_value_op" id="bam-meta-value-op">
							<option value="equals"<?php \selected( $meta_value_op, 'equals' ); ?>><?php \esc_html_e( 'Equals', 'bulk-actions-manager' ); ?></option>
							<option value="contains"<?php \selected( $meta_value_op, 'contains' ); ?>><?php \esc_html_e( 'Contains', 'bulk-actions-manager' ); ?></option>
							<option value="empty"<?php \selected( $meta_value_op, 'empty' ); ?>><?php \esc_html_e( 'Empty', 'bulk-actions-manager' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bam-featured"><?php \esc_html_e( 'Featured Image', 'bulk-actions-manager' ); ?></label></th>
					<td>
						<select name="bam_featured" id="bam-featured">
							<option value=""><?php \esc_html_e( 'Any', 'bulk-actions-manager' ); ?></option>
							<option value="has"<?php \selected( $featured, 'has' ); ?>><?php \esc_html_e( 'Has featured image', 'bulk-actions-manager' ); ?></option>
							<option value="missing"<?php \selected( $featured, 'missing' ); ?>><?php \esc_html_e( 'Missing featured image', 'bulk-actions-manager' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bam-title"><?php \esc_html_e( 'Title Contains', 'bulk-actions-manager' ); ?></label></th>
					<td><input type="text" name="bam_title" id="bam-title" class="regular-text" value="<?php echo \esc_attr( $title_contains ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="bam-content"><?php \esc_html_e( 'Content Contains', 'bulk-actions-manager' ); ?></label></th>
					<td><input type="text" name="bam_content" id="bam-content" class="regular-text" value="<?php echo \esc_attr( $content_contains ); ?>" /></td>
				</tr>
			</table>
		</details>
		<?php
	}

	/**
	 * Post type dropdown.
	 *
	 * @param string $selected Selected post type.
	 */
	private static function render_post_type_dropdown( $selected ) {
		$post_types = Filter_Registry::get_post_types();
		if ( \count( $post_types ) <= 1 ) {
			echo '<input type="hidden" name="bam_post_type" value="' . \esc_attr( $selected ) . '" />';
			return;
		}
		?>
		<label for="filter-post-type" class="screen-reader-text"><?php \esc_html_e( 'Content type', 'bulk-actions-manager' ); ?></label>
		<select name="bam_post_type" id="filter-post-type">
			<?php foreach ( $post_types as $slug => $label ) : ?>
				<option value="<?php echo \esc_attr( $slug ); ?>"<?php \selected( $selected, $slug ); ?>><?php echo \esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render post status subsubsub views.
	 *
	 * @param string               $post_type   Post type.
	 * @param string               $post_status Current status view.
	 * @param array<string, mixed> $request     Request args.
	 */
	private static function render_status_views( $post_type, $post_status, array $request ) {
		$base_args = array(
			'page'          => 'bam-new-job',
			'bam_post_type' => $post_type,
		);

		$counts = \wp_count_posts( $post_type );
		$links  = array();

		$all_count = 0;
		foreach ( \get_post_stati( array( 'show_in_admin_all_list' => true ), 'names' ) as $status ) {
			if ( isset( $counts->$status ) ) {
				$all_count += (int) $counts->$status;
			}
		}

		$links[] = self::status_link(
			\__( 'All', 'bulk-actions-manager' ),
			'all',
			$all_count,
			$post_status,
			\array_merge( $base_args, self::preserve_filters( $request, array( 'post_status' ) ) )
		);

		$statuses = \get_post_stati( array( 'show_in_admin_status_list' => true ), 'objects' );
		foreach ( $statuses as $status => $obj ) {
			if ( empty( $counts->$status ) ) {
				continue;
			}
			$links[] = self::status_link(
				$obj->label,
				$status,
				(int) $counts->$status,
				$post_status,
				\array_merge(
					$base_args,
					array( 'post_status' => $status ),
					self::preserve_filters( $request, array( 'post_status' ) )
				)
			);
		}

		echo '<ul class="subsubsub">';
		echo \implode( ' | ', $links );
		echo '</ul>';
	}

	/**
	 * Preserve non-status filter args for view links.
	 *
	 * @param array<string, mixed> $request Request.
	 * @param array<int, string>   $exclude Keys to exclude.
	 * @return array<string, mixed>
	 */
	private static function preserve_filters( array $request, array $exclude = array() ) {
		$keys = array(
			'bam_post_type',
			'm',
			'cat',
			'author',
			's',
			'seo-filter',
			'rankmath-filter',
			'tag_id',
			'bam_meta_key',
			'bam_meta_op',
			'bam_meta_value_key',
			'bam_meta_value',
			'bam_meta_value_op',
			'bam_featured',
			'bam_title',
			'bam_content',
		);
		$out  = array();
		foreach ( $keys as $key ) {
			if ( \in_array( $key, $exclude, true ) || empty( $request[ $key ] ) ) {
				continue;
			}
			$out[ $key ] = $request[ $key ];
		}
		return $out;
	}

	/**
	 * Build a status view link.
	 *
	 * @param string               $label   Label.
	 * @param string               $status  Status slug.
	 * @param int                  $count   Count.
	 * @param string               $current Current status.
	 * @param array<string, mixed> $args    URL args.
	 * @return string
	 */
	private static function status_link( $label, $status, $count, $current, array $args ) {
		if ( 'all' !== $status ) {
			$args['post_status'] = $status;
		} else {
			$args['post_status'] = 'all';
		}

		$url   = \add_query_arg( $args, \admin_url( 'admin.php' ) );
		$class = ( $current === $status ) ? ' class="current"' : '';

		return '<li><a href="' . \esc_url( $url ) . '"' . $class . '>' . \esc_html( $label ) . ' <span class="count">(' . (int) $count . ')</span></a></li>';
	}

	/**
	 * Render month filter options (replaces removed wp_month_dropdown in WP 6.9+).
	 *
	 * @param string     $post_type  Post type slug.
	 * @param int|string $selected_m Selected YYYYMM value.
	 */
	private static function render_month_options( $post_type, $selected_m ) {
		global $wpdb, $wp_locale;

		$months = \apply_filters( 'pre_months_dropdown_query', false, $post_type );

		if ( ! \is_array( $months ) ) {
			$extra_checks = "AND post_status != 'auto-draft' AND post_status != 'trash'";

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$months = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
					FROM $wpdb->posts
					WHERE post_type = %s
					$extra_checks
					ORDER BY post_date DESC",
					$post_type
				)
			);
		}

		$months = \apply_filters( 'months_dropdown_results', $months, $post_type );

		if ( empty( $months ) ) {
			return;
		}

		$selected_month = $selected_m ? (int) $selected_m : 0;

		foreach ( $months as $arc_row ) {
			if ( 0 === (int) $arc_row->year ) {
				continue;
			}

			$month = \zeroise( $arc_row->month, 2 );
			$year  = $arc_row->year;
			$value = $year . $month;

			printf(
				'<option %1$s value="%2$s">%3$s</option>',
				\selected( $selected_month, (int) $value, false ),
				\esc_attr( $value ),
				\esc_html(
					\sprintf(
						/* translators: 1: Month name, 2: 4-digit year. */
						\__( '%1$s %2$d', 'default' ),
						$wp_locale->get_month( $month ),
						$year
					)
				)
			);
		}
	}
}

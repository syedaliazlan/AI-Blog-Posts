<?php
/**
 * Topics queue page template
 *
 * @link       https://devonicweb.co.uk/
 * @since      1.0.0
 *
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/admin/partials
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$topics_table = $wpdb->prefix . 'ai_blog_posts_topics';
$categories = get_categories( array( 'hide_empty' => false ) );

// Get topics with pagination
$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = 20;
$offset = ( $page - 1 ) * $per_page;

$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
$where_clause = '';
if ( $status_filter ) {
	$where_clause = $wpdb->prepare( ' WHERE status = %s', $status_filter );
}

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_clause is already prepared above
$total_topics = $wpdb->get_var( "SELECT COUNT(*) FROM $topics_table" . $where_clause );

// Build query without double-preparing the WHERE clause
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_clause is already prepared
$query = "SELECT * FROM $topics_table $where_clause ORDER BY priority DESC, created_at DESC LIMIT %d OFFSET %d";
$topics = $wpdb->get_results( 
	$wpdb->prepare( $query, $per_page, $offset ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
);

$total_pages = ceil( $total_topics / $per_page );

// Get counts by status
$status_counts = array(
	'pending'   => $wpdb->get_var( "SELECT COUNT(*) FROM $topics_table WHERE status = 'pending'" ),
	'completed' => $wpdb->get_var( "SELECT COUNT(*) FROM $topics_table WHERE status = 'completed'" ),
	'failed'    => $wpdb->get_var( "SELECT COUNT(*) FROM $topics_table WHERE status = 'failed'" ),
);
?>

<div class="wrap ai-blog-posts-wrap">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-list-view"></span>
		<?php esc_html_e( 'Topic Queue', 'ai-blog-posts' ); ?>
	</h1>

	<div class="ai-blog-posts-topics">
		<!-- Add Topic Form -->
		<div class="add-topic-section">
			<h2><?php esc_html_e( 'Add New Topic', 'ai-blog-posts' ); ?></h2>
			<form id="add-topic-form" class="ai-blog-posts-form inline-form">
				<?php wp_nonce_field( 'ai_blog_posts_nonce', 'ai_blog_posts_nonce' ); ?>
				
				<div class="form-row">
					<div class="form-field">
						<input type="text" id="new-topic" name="topic" placeholder="<?php esc_attr_e( 'Enter topic...', 'ai-blog-posts' ); ?>" required>
					</div>
					<div class="form-field">
						<input type="text" id="new-keywords" name="keywords" placeholder="<?php esc_attr_e( 'Keywords (optional)', 'ai-blog-posts' ); ?>">
					</div>
					<div class="form-field">
						<select id="new-category" name="category_id">
							<option value=""><?php esc_html_e( 'Category', 'ai-blog-posts' ); ?></option>
							<?php foreach ( $categories as $category ) : ?>
								<option value="<?php echo esc_attr( $category->term_id ); ?>">
									<?php echo esc_html( $category->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="form-field">
						<input type="number" id="new-priority" name="priority" placeholder="<?php esc_attr_e( 'Priority', 'ai-blog-posts' ); ?>" min="0" max="100" value="0">
					</div>
					<div class="form-field">
						<button type="submit" class="button button-primary">
							<span class="dashicons dashicons-plus"></span>
							<?php esc_html_e( 'Add Topic', 'ai-blog-posts' ); ?>
						</button>
					</div>
				</div>
			</form>
		</div>

		<!-- Bulk Actions -->
		<div class="topics-actions">
			<div class="actions-left">
				<button type="button" id="fetch-trending" class="button">
					<span class="dashicons dashicons-trending-up"></span>
					<?php esc_html_e( 'Fetch Trending Topics', 'ai-blog-posts' ); ?>
				</button>
				<button type="button" id="bulk-import" class="button">
					<span class="dashicons dashicons-upload"></span>
					<?php esc_html_e( 'Import from CSV', 'ai-blog-posts' ); ?>
				</button>
			</div>
			<div class="actions-right">
				<select id="bulk-action">
					<option value=""><?php esc_html_e( 'Bulk Actions', 'ai-blog-posts' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete', 'ai-blog-posts' ); ?></option>
					<option value="generate"><?php esc_html_e( 'Generate Now', 'ai-blog-posts' ); ?></option>
				</select>
				<button type="button" id="apply-bulk" class="button">
					<?php esc_html_e( 'Apply', 'ai-blog-posts' ); ?>
				</button>
			</div>
		</div>

		<!-- Status Filter -->
		<ul class="subsubsub">
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-blog-posts-topics' ) ); ?>" 
				   class="<?php echo empty( $status_filter ) ? 'current' : ''; ?>">
					<?php esc_html_e( 'All', 'ai-blog-posts' ); ?>
					<span class="count">(<?php echo esc_html( $total_topics ); ?>)</span>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-blog-posts-topics&status=pending' ) ); ?>" 
				   class="<?php echo 'pending' === $status_filter ? 'current' : ''; ?>">
					<?php esc_html_e( 'Pending', 'ai-blog-posts' ); ?>
					<span class="count">(<?php echo esc_html( $status_counts['pending'] ); ?>)</span>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-blog-posts-topics&status=completed' ) ); ?>" 
				   class="<?php echo 'completed' === $status_filter ? 'current' : ''; ?>">
					<?php esc_html_e( 'Completed', 'ai-blog-posts' ); ?>
					<span class="count">(<?php echo esc_html( $status_counts['completed'] ); ?>)</span>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-blog-posts-topics&status=failed' ) ); ?>" 
				   class="<?php echo 'failed' === $status_filter ? 'current' : ''; ?>">
					<?php esc_html_e( 'Failed', 'ai-blog-posts' ); ?>
					<span class="count">(<?php echo esc_html( $status_counts['failed'] ); ?>)</span>
				</a>
			</li>
		</ul>

		<!-- Topics Table -->
		<table class="wp-list-table widefat fixed striped topics-table">
			<thead>
				<tr>
					<th class="manage-column column-cb check-column">
						<input type="checkbox" id="select-all-topics" class="topic-checkbox">
					</th>
					<th class="manage-column column-topic"><?php esc_html_e( 'Topic', 'ai-blog-posts' ); ?></th>
					<th class="manage-column column-keywords"><?php esc_html_e( 'Keywords', 'ai-blog-posts' ); ?></th>
					<th class="manage-column column-category"><?php esc_html_e( 'Category', 'ai-blog-posts' ); ?></th>
					<th class="manage-column column-source"><?php esc_html_e( 'Source', 'ai-blog-posts' ); ?></th>
					<th class="manage-column column-priority"><?php esc_html_e( 'Priority', 'ai-blog-posts' ); ?></th>
					<th class="manage-column column-status"><?php esc_html_e( 'Status', 'ai-blog-posts' ); ?></th>
					<th class="manage-column column-date"><?php esc_html_e( 'Date', 'ai-blog-posts' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $topics ) ) : ?>
					<tr>
						<td colspan="8" class="no-topics">
							<div class="ai-blog-posts-empty-state">
								<span class="dashicons dashicons-list-view"></span>
								<p><?php esc_html_e( 'No topics in the queue. Add some topics to get started!', 'ai-blog-posts' ); ?></p>
							</div>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $topics as $topic ) : ?>
						<tr data-topic-id="<?php echo esc_attr( $topic->id ); ?>">
							<th class="check-column">
								<input type="checkbox" class="topic-checkbox" value="<?php echo esc_attr( $topic->id ); ?>">
							</th>
							<td class="column-topic">
								<strong><?php echo esc_html( $topic->topic ); ?></strong>
								<div class="row-actions">
									<?php if ( 'pending' === $topic->status ) : ?>
										<span class="generate">
											<a href="#" class="generate-topic" data-id="<?php echo esc_attr( $topic->id ); ?>">
												<?php esc_html_e( 'Generate', 'ai-blog-posts' ); ?>
											</a> |
										</span>
									<?php elseif ( 'failed' === $topic->status ) : ?>
										<span class="retry">
											<a href="#" class="retry-topic" data-id="<?php echo esc_attr( $topic->id ); ?>">
												<?php esc_html_e( 'Retry', 'ai-blog-posts' ); ?>
											</a> |
										</span>
									<?php elseif ( 'generating' === $topic->status ) : ?>
										<span class="generating-text">
											<span class="spinner is-active" style="float: none; margin: 0;"></span>
											<?php esc_html_e( 'Generating...', 'ai-blog-posts' ); ?>
										</span>
									<?php endif; ?>
									<?php if ( $topic->post_id ) : ?>
										<span class="view">
											<a href="<?php echo esc_url( get_permalink( $topic->post_id ) ); ?>" target="_blank">
												<?php esc_html_e( 'View Post', 'ai-blog-posts' ); ?>
											</a> |
										</span>
										<span class="edit">
											<a href="<?php echo esc_url( get_edit_post_link( $topic->post_id ) ); ?>">
												<?php esc_html_e( 'Edit', 'ai-blog-posts' ); ?>
											</a> |
										</span>
									<?php endif; ?>
									<?php if ( 'generating' !== $topic->status ) : ?>
									<span class="delete">
										<a href="#" class="delete-topic" data-id="<?php echo esc_attr( $topic->id ); ?>">
											<?php esc_html_e( 'Delete', 'ai-blog-posts' ); ?>
										</a>
									</span>
									<?php endif; ?>
								</div>
							</td>
							<td class="column-keywords">
								<?php if ( $topic->keywords ) : ?>
									<?php 
									$keywords = explode( ',', $topic->keywords );
									foreach ( $keywords as $keyword ) : ?>
										<span class="keyword-tag"><?php echo esc_html( trim( $keyword ) ); ?></span>
									<?php endforeach; ?>
								<?php else : ?>
									<em>—</em>
								<?php endif; ?>
							</td>
							<td class="column-category">
								<?php if ( $topic->category_id ) : ?>
									<?php $cat = get_category( $topic->category_id ); ?>
									<?php echo $cat ? esc_html( $cat->name ) : '—'; ?>
								<?php else : ?>
									<em>—</em>
								<?php endif; ?>
							</td>
							<td class="column-source">
								<span class="source-badge source-<?php echo esc_attr( $topic->source ); ?>">
									<?php echo esc_html( ucfirst( $topic->source ) ); ?>
								</span>
							</td>
							<td class="column-priority">
								<span class="priority-badge priority-<?php echo $topic->priority > 50 ? 'high' : ( $topic->priority > 0 ? 'medium' : 'low' ); ?>">
									<?php echo esc_html( $topic->priority ); ?>
								</span>
							</td>
							<td class="column-status">
								<span class="status-badge <?php echo esc_attr( $topic->status ); ?>">
									<?php echo esc_html( ucfirst( $topic->status ) ); ?>
								</span>
								<?php if ( 'failed' === $topic->status && $topic->last_error ) : ?>
									<span class="error-tooltip" title="<?php echo esc_attr( $topic->last_error ); ?>">
										<span class="dashicons dashicons-info"></span>
									</span>
								<?php endif; ?>
							</td>
							<td class="column-date">
								<span title="<?php echo esc_attr( $topic->created_at ); ?>">
									<?php echo esc_html( human_time_diff( strtotime( $topic->created_at ), current_time( 'timestamp' ) ) ); ?> ago
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<span class="displaying-num">
						<?php 
						printf(
							/* translators: %s: number of items */
							esc_html( _n( '%s item', '%s items', $total_topics, 'ai-blog-posts' ) ),
							number_format_i18n( $total_topics )
						);
						?>
					</span>
					<span class="pagination-links">
						<?php
						echo paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $total_pages,
							'current'   => $page,
						) );
						?>
					</span>
				</div>
			</div>
		<?php endif; ?>

		<!-- CSV Import Modal -->
		<div id="csv-import-modal" class="ai-blog-posts-modal" style="display: none;">
			<div class="modal-content">
				<div class="modal-header">
					<h2><?php esc_html_e( 'Import Topics from CSV', 'ai-blog-posts' ); ?></h2>
					<button type="button" class="modal-close">&times;</button>
				</div>
				<div class="modal-body">
					<p><?php esc_html_e( 'Upload a CSV file with topics. Expected columns:', 'ai-blog-posts' ); ?></p>
					<ul style="margin: 10px 0 10px 20px; list-style: disc;">
						<li><strong>Topic</strong> <?php esc_html_e( '(required)', 'ai-blog-posts' ); ?></li>
						<li><strong>Keywords</strong> <?php esc_html_e( '(optional - comma-separated)', 'ai-blog-posts' ); ?></li>
						<li><strong>Category</strong> <?php esc_html_e( '(optional - name or slug, will be auto-created if not found)', 'ai-blog-posts' ); ?></li>
						<li><strong>Priority</strong> <?php esc_html_e( '(optional - 0-100)', 'ai-blog-posts' ); ?></li>
					</ul>
					<form id="csv-import-form" enctype="multipart/form-data">
						<input type="file" id="csv-file" name="csv_file" accept=".csv" required>
						<p class="description">
							<?php esc_html_e( 'Maximum file size: 2MB', 'ai-blog-posts' ); ?>
						</p>
					</form>
				</div>
				<div class="modal-footer">
					<button type="button" class="button modal-cancel"><?php esc_html_e( 'Cancel', 'ai-blog-posts' ); ?></button>
					<button type="button" id="do-csv-import" class="button button-primary"><?php esc_html_e( 'Import', 'ai-blog-posts' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Trending Topics Modal -->
		<div id="trending-modal" class="ai-blog-posts-modal" style="display: none;">
			<div class="modal-content">
				<div class="modal-header">
					<h2><?php esc_html_e( 'Trending Topics', 'ai-blog-posts' ); ?></h2>
					<button type="button" class="modal-close">&times;</button>
				</div>
				<div class="modal-body">
					<div id="trending-loading" class="loading-spinner">
						<span class="spinner is-active"></span>
						<p><?php esc_html_e( 'Fetching trending topics...', 'ai-blog-posts' ); ?></p>
					</div>
					<div id="trending-list" style="display: none;"></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="button modal-cancel"><?php esc_html_e( 'Cancel', 'ai-blog-posts' ); ?></button>
					<button type="button" id="add-selected-trends" class="button button-primary"><?php esc_html_e( 'Add Selected', 'ai-blog-posts' ); ?></button>
				</div>
			</div>
		</div>
	</div>
</div>


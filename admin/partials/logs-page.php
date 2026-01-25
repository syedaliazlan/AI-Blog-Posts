<?php
/**
 * Generation logs page template
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

$cost_tracker = new Ai_Blog_Posts_Cost_Tracker();
$stats = $cost_tracker->get_stats();

// Get logs with pagination
$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = 25;
$logs_data = $cost_tracker->get_logs( $page, $per_page );
$logs = $logs_data['logs'];
$total_logs = $logs_data['total'];
$total_pages = ceil( $total_logs / $per_page );
?>

<div class="wrap ai-blog-posts-wrap">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-chart-line"></span>
		<?php esc_html_e( 'Generation Logs & Costs', 'ai-blog-posts' ); ?>
	</h1>

	<div class="ai-blog-posts-logs">
		<!-- Stats Cards -->
		<div class="logs-stats">
			<div class="stat-card">
				<div class="stat-icon">
					<span class="dashicons dashicons-admin-post"></span>
				</div>
				<div class="stat-content">
					<h3><?php esc_html_e( 'Total Posts', 'ai-blog-posts' ); ?></h3>
					<p class="stat-value"><?php echo esc_html( number_format( $stats['total_posts'] ?? 0 ) ); ?></p>
				</div>
			</div>

			<div class="stat-card">
				<div class="stat-icon">
					<span class="dashicons dashicons-editor-code"></span>
				</div>
				<div class="stat-content">
					<h3><?php esc_html_e( 'Total Tokens', 'ai-blog-posts' ); ?></h3>
					<p class="stat-value"><?php echo esc_html( number_format( $stats['total_tokens'] ?? 0 ) ); ?></p>
				</div>
			</div>

			<div class="stat-card">
				<div class="stat-icon">
					<span class="dashicons dashicons-money-alt"></span>
				</div>
				<div class="stat-content">
					<h3><?php esc_html_e( 'Total Cost', 'ai-blog-posts' ); ?></h3>
					<p class="stat-value">$<?php echo esc_html( number_format( $stats['total_cost'] ?? 0, 2 ) ); ?></p>
				</div>
			</div>

			<div class="stat-card">
				<div class="stat-icon">
					<span class="dashicons dashicons-calculator"></span>
				</div>
				<div class="stat-content">
					<h3><?php esc_html_e( 'Avg Cost/Post', 'ai-blog-posts' ); ?></h3>
					<p class="stat-value">$<?php echo esc_html( number_format( $stats['avg_cost'] ?? 0, 4 ) ); ?></p>
				</div>
			</div>
		</div>

		<!-- Period Stats -->
		<div class="period-stats">
			<div class="period-card">
				<h4><?php esc_html_e( 'This Month', 'ai-blog-posts' ); ?></h4>
				<div class="period-data">
					<span class="period-posts"><?php echo esc_html( $stats['posts_this_month'] ?? 0 ); ?> <?php esc_html_e( 'posts', 'ai-blog-posts' ); ?></span>
					<span class="period-cost">$<?php echo esc_html( number_format( $stats['cost_this_month'] ?? 0, 2 ) ); ?></span>
				</div>
			</div>
			<div class="period-card">
				<h4><?php esc_html_e( 'This Week', 'ai-blog-posts' ); ?></h4>
				<div class="period-data">
					<span class="period-posts"><?php echo esc_html( $stats['posts_this_week'] ?? 0 ); ?> <?php esc_html_e( 'posts', 'ai-blog-posts' ); ?></span>
					<span class="period-cost">$<?php echo esc_html( number_format( $stats['cost_this_week'] ?? 0, 2 ) ); ?></span>
				</div>
			</div>
			<div class="period-card">
				<h4><?php esc_html_e( 'Today', 'ai-blog-posts' ); ?></h4>
				<div class="period-data">
					<span class="period-posts"><?php echo esc_html( $stats['posts_today'] ?? 0 ); ?> <?php esc_html_e( 'posts', 'ai-blog-posts' ); ?></span>
					<span class="period-cost">$<?php echo esc_html( number_format( $stats['cost_today'] ?? 0, 2 ) ); ?></span>
				</div>
			</div>
		</div>

		<!-- Actions -->
		<div class="logs-actions">
			<button type="button" id="export-csv" class="button">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export to CSV', 'ai-blog-posts' ); ?>
			</button>
			<button type="button" id="clear-logs" class="button button-link-delete">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Clear All Logs', 'ai-blog-posts' ); ?>
			</button>
		</div>

		<!-- Logs Table -->
		<table class="wp-list-table widefat fixed striped logs-table">
			<thead>
				<tr>
					<th class="manage-column column-date"><?php esc_html_e( 'Date', 'ai-blog-posts' ); ?></th>
					<th class="manage-column column-post"><?php esc_html_e( 'Post', 'ai-blog-posts' ); ?></th>
					<th class="manage-column column-model"><?php esc_html_e( 'Model', 'ai-blog-posts' ); ?></th>
					<th class="manage-column column-tokens"><?php esc_html_e( 'Tokens (In/Out)', 'ai-blog-posts' ); ?></th>
					<th class="manage-column column-cost"><?php esc_html_e( 'Text Cost', 'ai-blog-posts' ); ?></th>
					<th class="manage-column column-image-cost"><?php esc_html_e( 'Image Cost', 'ai-blog-posts' ); ?></th>
					<th class="manage-column column-total"><?php esc_html_e( 'Total', 'ai-blog-posts' ); ?></th>
					<th class="manage-column column-time"><?php esc_html_e( 'Time', 'ai-blog-posts' ); ?></th>
					<th class="manage-column column-status"><?php esc_html_e( 'Status', 'ai-blog-posts' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr>
						<td colspan="9" class="no-logs">
							<div class="ai-blog-posts-empty-state">
								<span class="dashicons dashicons-chart-line"></span>
								<p><?php esc_html_e( 'No generation logs yet. Start generating posts to see your usage statistics.', 'ai-blog-posts' ); ?></p>
							</div>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td class="column-date">
								<span title="<?php echo esc_attr( $log->created_at ); ?>">
									<?php echo esc_html( date_i18n( 'M j, Y', strtotime( $log->created_at ) ) ); ?>
									<br>
									<small><?php echo esc_html( date_i18n( 'g:i a', strtotime( $log->created_at ) ) ); ?></small>
								</span>
							</td>
							<td class="column-post">
								<?php if ( $log->post_id && get_post( $log->post_id ) ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $log->post_id ) ); ?>">
										<?php echo esc_html( wp_trim_words( get_the_title( $log->post_id ), 8 ) ); ?>
									</a>
								<?php else : ?>
									<em><?php esc_html_e( 'Deleted or N/A', 'ai-blog-posts' ); ?></em>
								<?php endif; ?>
							</td>
							<td class="column-model">
								<code><?php echo esc_html( $log->model_used ); ?></code>
							</td>
							<td class="column-tokens">
								<span class="token-in" title="<?php esc_attr_e( 'Input tokens', 'ai-blog-posts' ); ?>">
									↑ <?php echo esc_html( number_format( $log->prompt_tokens ) ); ?>
								</span>
								<br>
								<span class="token-out" title="<?php esc_attr_e( 'Output tokens', 'ai-blog-posts' ); ?>">
									↓ <?php echo esc_html( number_format( $log->completion_tokens ) ); ?>
								</span>
							</td>
							<td class="column-cost">
								$<?php echo esc_html( number_format( $log->cost_usd, 4 ) ); ?>
							</td>
							<td class="column-image-cost">
								<?php if ( $log->image_cost_usd > 0 ) : ?>
									$<?php echo esc_html( number_format( $log->image_cost_usd, 4 ) ); ?>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td class="column-total">
								<strong>$<?php echo esc_html( number_format( $log->cost_usd + $log->image_cost_usd, 4 ) ); ?></strong>
							</td>
							<td class="column-time">
								<?php echo esc_html( number_format( $log->generation_time, 1 ) ); ?>s
							</td>
							<td class="column-status">
								<span class="status-badge <?php echo esc_attr( $log->status ); ?>">
									<?php echo esc_html( ucfirst( $log->status ) ); ?>
								</span>
								<?php if ( 'failed' === $log->status && $log->error_message ) : ?>
									<span class="error-tooltip" title="<?php echo esc_attr( $log->error_message ); ?>">
										<span class="dashicons dashicons-info"></span>
									</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
			<?php if ( ! empty( $logs ) ) : ?>
				<tfoot>
					<tr class="totals-row">
						<td colspan="3"><strong><?php esc_html_e( 'Page Totals', 'ai-blog-posts' ); ?></strong></td>
						<td>
							<?php 
							$page_prompt = array_sum( array_column( $logs, 'prompt_tokens' ) );
							$page_completion = array_sum( array_column( $logs, 'completion_tokens' ) );
							?>
							↑ <?php echo esc_html( number_format( $page_prompt ) ); ?><br>
							↓ <?php echo esc_html( number_format( $page_completion ) ); ?>
						</td>
						<td>
							$<?php echo esc_html( number_format( array_sum( array_column( $logs, 'cost_usd' ) ), 4 ) ); ?>
						</td>
						<td>
							$<?php echo esc_html( number_format( array_sum( array_column( $logs, 'image_cost_usd' ) ), 4 ) ); ?>
						</td>
						<td>
							<strong>
								$<?php 
								$page_total = array_sum( array_column( $logs, 'cost_usd' ) ) + array_sum( array_column( $logs, 'image_cost_usd' ) );
								echo esc_html( number_format( $page_total, 4 ) ); 
								?>
							</strong>
						</td>
						<td colspan="2"></td>
					</tr>
				</tfoot>
			<?php endif; ?>
		</table>

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<span class="displaying-num">
						<?php 
						printf(
							/* translators: %s: number of items */
							esc_html( _n( '%s log entry', '%s log entries', $total_logs, 'ai-blog-posts' ) ),
							number_format_i18n( $total_logs )
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
	</div>
</div>


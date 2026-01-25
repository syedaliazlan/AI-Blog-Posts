<?php
/**
 * Dashboard page template
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

// Get stats
$cost_tracker = new Ai_Blog_Posts_Cost_Tracker();
$stats = $cost_tracker->get_stats();
$recent_logs = $cost_tracker->get_logs( 1, 5 );

// Get queue count
global $wpdb;
$topics_table = $wpdb->prefix . 'ai_blog_posts_topics';
$pending_count = $wpdb->get_var( "SELECT COUNT(*) FROM $topics_table WHERE status = 'pending'" );

// Check configuration status
$is_configured = Ai_Blog_Posts_Settings::is_configured();
$is_verified = Ai_Blog_Posts_Settings::is_verified();
$schedule_enabled = Ai_Blog_Posts_Settings::get( 'schedule_enabled' );
?>

<div class="wrap ai-blog-posts-wrap">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-edit-page"></span>
		<?php esc_html_e( 'AI Blog Posts Dashboard', 'ai-blog-posts' ); ?>
	</h1>

	<div class="ai-blog-posts-dashboard">
		<!-- Status Cards -->
		<div class="ai-blog-posts-cards">
			<div class="ai-blog-posts-card <?php echo $is_verified ? 'status-success' : 'status-warning'; ?>">
				<div class="card-icon">
					<span class="dashicons dashicons-admin-network"></span>
				</div>
				<div class="card-content">
					<h3><?php esc_html_e( 'API Status', 'ai-blog-posts' ); ?></h3>
					<p class="card-value">
						<?php if ( $is_verified ) : ?>
							<span class="status-badge success"><?php esc_html_e( 'Connected', 'ai-blog-posts' ); ?></span>
						<?php elseif ( $is_configured ) : ?>
							<span class="status-badge warning"><?php esc_html_e( 'Not Verified', 'ai-blog-posts' ); ?></span>
						<?php else : ?>
							<span class="status-badge error"><?php esc_html_e( 'Not Configured', 'ai-blog-posts' ); ?></span>
						<?php endif; ?>
					</p>
				</div>
			</div>

			<div class="ai-blog-posts-card">
				<div class="card-icon">
					<span class="dashicons dashicons-chart-bar"></span>
				</div>
				<div class="card-content">
					<h3><?php esc_html_e( 'Posts Generated', 'ai-blog-posts' ); ?></h3>
					<p class="card-value"><?php echo esc_html( number_format( $stats['total_posts'] ?? 0 ) ); ?></p>
					<p class="card-subtitle">
						<?php 
						printf(
							/* translators: %d: number of posts this month */
							esc_html__( '%d this month', 'ai-blog-posts' ),
							$stats['posts_this_month'] ?? 0
						);
						?>
					</p>
				</div>
			</div>

			<div class="ai-blog-posts-card">
				<div class="card-icon">
					<span class="dashicons dashicons-money-alt"></span>
				</div>
				<div class="card-content">
					<h3><?php esc_html_e( 'Total Spent', 'ai-blog-posts' ); ?></h3>
					<p class="card-value">$<?php echo esc_html( number_format( $stats['total_cost'] ?? 0, 2 ) ); ?></p>
					<p class="card-subtitle">
						<?php 
						printf(
							/* translators: %s: amount spent this month */
							esc_html__( '$%s this month', 'ai-blog-posts' ),
							number_format( $stats['cost_this_month'] ?? 0, 2 )
						);
						?>
					</p>
				</div>
			</div>

			<div class="ai-blog-posts-card">
				<div class="card-icon">
					<span class="dashicons dashicons-list-view"></span>
				</div>
				<div class="card-content">
					<h3><?php esc_html_e( 'Topics in Queue', 'ai-blog-posts' ); ?></h3>
					<p class="card-value"><?php echo esc_html( number_format( $pending_count ) ); ?></p>
					<p class="card-subtitle">
						<?php if ( $schedule_enabled ) : ?>
							<span class="status-badge success"><?php esc_html_e( 'Auto-posting ON', 'ai-blog-posts' ); ?></span>
						<?php else : ?>
							<span class="status-badge warning"><?php esc_html_e( 'Auto-posting OFF', 'ai-blog-posts' ); ?></span>
						<?php endif; ?>
					</p>
				</div>
			</div>
		</div>

		<!-- Quick Actions -->
		<div class="ai-blog-posts-section">
			<h2><?php esc_html_e( 'Quick Actions', 'ai-blog-posts' ); ?></h2>
			<div class="ai-blog-posts-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-blog-posts-generate' ) ); ?>" class="button button-primary button-hero">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'Generate New Post', 'ai-blog-posts' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-blog-posts-topics' ) ); ?>" class="button button-secondary button-hero">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'Manage Topics', 'ai-blog-posts' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-blog-posts-settings' ) ); ?>" class="button button-secondary button-hero">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Settings', 'ai-blog-posts' ); ?>
				</a>
			</div>
		</div>

		<!-- Recent Activity -->
		<div class="ai-blog-posts-section">
			<h2><?php esc_html_e( 'Recent Activity', 'ai-blog-posts' ); ?></h2>
			<?php if ( ! empty( $recent_logs['logs'] ) ) : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'ai-blog-posts' ); ?></th>
							<th><?php esc_html_e( 'Post', 'ai-blog-posts' ); ?></th>
							<th><?php esc_html_e( 'Model', 'ai-blog-posts' ); ?></th>
							<th><?php esc_html_e( 'Tokens', 'ai-blog-posts' ); ?></th>
							<th><?php esc_html_e( 'Cost', 'ai-blog-posts' ); ?></th>
							<th><?php esc_html_e( 'Status', 'ai-blog-posts' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_logs['logs'] as $log ) : ?>
							<tr>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->created_at ) ) ); ?></td>
								<td>
									<?php if ( $log->post_id ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( $log->post_id ) ); ?>">
											<?php echo esc_html( get_the_title( $log->post_id ) ?: __( 'Untitled', 'ai-blog-posts' ) ); ?>
										</a>
									<?php else : ?>
										<em><?php esc_html_e( 'N/A', 'ai-blog-posts' ); ?></em>
									<?php endif; ?>
								</td>
								<td><code><?php echo esc_html( $log->model_used ); ?></code></td>
								<td><?php echo esc_html( number_format( $log->total_tokens ) ); ?></td>
								<td>$<?php echo esc_html( number_format( $log->cost_usd + $log->image_cost_usd, 4 ) ); ?></td>
								<td>
									<span class="status-badge <?php echo 'success' === $log->status ? 'success' : 'error'; ?>">
										<?php echo esc_html( ucfirst( $log->status ) ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-blog-posts-logs' ) ); ?>">
						<?php esc_html_e( 'View all logs →', 'ai-blog-posts' ); ?>
					</a>
				</p>
			<?php else : ?>
				<div class="ai-blog-posts-empty-state">
					<span class="dashicons dashicons-format-status"></span>
					<p><?php esc_html_e( 'No posts generated yet. Start by generating your first post!', 'ai-blog-posts' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-blog-posts-generate' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Generate First Post', 'ai-blog-posts' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>

		<!-- Getting Started Guide (show if not configured) -->
		<?php if ( ! $is_verified ) : ?>
		<div class="ai-blog-posts-section ai-blog-posts-guide">
			<h2><?php esc_html_e( 'Getting Started', 'ai-blog-posts' ); ?></h2>
			<div class="guide-steps">
				<div class="guide-step <?php echo $is_configured ? 'completed' : 'active'; ?>">
					<div class="step-number">1</div>
					<div class="step-content">
						<h4><?php esc_html_e( 'Configure API Key', 'ai-blog-posts' ); ?></h4>
						<p><?php esc_html_e( 'Add your OpenAI API key in the settings page.', 'ai-blog-posts' ); ?></p>
					</div>
				</div>
				<div class="guide-step <?php echo $is_verified ? 'completed' : ( $is_configured ? 'active' : '' ); ?>">
					<div class="step-number">2</div>
					<div class="step-content">
						<h4><?php esc_html_e( 'Verify Connection', 'ai-blog-posts' ); ?></h4>
						<p><?php esc_html_e( 'Test your API key to ensure it works correctly.', 'ai-blog-posts' ); ?></p>
					</div>
				</div>
				<div class="guide-step">
					<div class="step-number">3</div>
					<div class="step-content">
						<h4><?php esc_html_e( 'Generate Content', 'ai-blog-posts' ); ?></h4>
						<p><?php esc_html_e( 'Start generating AI-powered blog posts!', 'ai-blog-posts' ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>


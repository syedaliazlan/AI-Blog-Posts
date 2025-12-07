<?php
/**
 * Generate post page template
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

$categories = get_categories( array( 'hide_empty' => false ) );
$is_configured = Ai_Blog_Posts_Settings::is_configured();
$is_verified = Ai_Blog_Posts_Settings::is_verified();
$models = Ai_Blog_Posts_Settings::get_models();
$current_model = Ai_Blog_Posts_Settings::get( 'model' );

// Get pre-filled values from URL
$prefill_topic = isset( $_GET['topic'] ) ? sanitize_text_field( wp_unslash( $_GET['topic'] ) ) : '';
$prefill_keywords = isset( $_GET['keywords'] ) ? sanitize_text_field( wp_unslash( $_GET['keywords'] ) ) : '';
$prefill_topic_id = isset( $_GET['topic_id'] ) ? absint( $_GET['topic_id'] ) : 0;
?>

<div class="wrap ai-blog-posts-wrap">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-plus-alt"></span>
		<?php esc_html_e( 'Generate New Post', 'ai-blog-posts' ); ?>
	</h1>

	<?php if ( ! $is_verified ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'Please configure and verify your OpenAI API key before generating posts.', 'ai-blog-posts' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-blog-posts-settings' ) ); ?>">
					<?php esc_html_e( 'Go to Settings', 'ai-blog-posts' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<div class="ai-blog-posts-generate">
		<div class="generate-form-container">
			<form id="generate-post-form" class="ai-blog-posts-form" <?php echo ! $is_verified ? 'disabled' : ''; ?>>
				<?php wp_nonce_field( 'ai_blog_posts_nonce', 'ai_blog_posts_nonce' ); ?>
				<?php if ( $prefill_topic_id ) : ?>
					<input type="hidden" id="queue_topic_id" name="queue_topic_id" value="<?php echo esc_attr( $prefill_topic_id ); ?>">
				<?php endif; ?>

				<div class="form-section">
					<h2><?php esc_html_e( 'Topic & Content', 'ai-blog-posts' ); ?></h2>
					
					<div class="form-field">
						<label for="topic"><?php esc_html_e( 'Topic', 'ai-blog-posts' ); ?> <span class="required">*</span></label>
						<input type="text" 
							   id="topic" 
							   name="topic" 
							   class="large-text" 
							   placeholder="<?php esc_attr_e( 'e.g., 10 Tips for Better SEO in 2024', 'ai-blog-posts' ); ?>"
							   value="<?php echo esc_attr( $prefill_topic ); ?>"
							   required
							   <?php echo ! $is_verified ? 'disabled' : ''; ?>>
						<p class="description"><?php esc_html_e( 'Enter the topic or title for your blog post.', 'ai-blog-posts' ); ?></p>
					</div>

					<div class="form-field">
						<label for="keywords"><?php esc_html_e( 'Focus Keywords', 'ai-blog-posts' ); ?></label>
						<input type="text" 
							   id="keywords" 
							   name="keywords" 
							   class="large-text" 
							   placeholder="<?php esc_attr_e( 'e.g., SEO tips, search ranking, Google optimization', 'ai-blog-posts' ); ?>"
							   value="<?php echo esc_attr( $prefill_keywords ); ?>"
							   <?php echo ! $is_verified ? 'disabled' : ''; ?>>
						<p class="description"><?php esc_html_e( 'Comma-separated keywords to focus on (optional).', 'ai-blog-posts' ); ?></p>
					</div>

					<div class="form-field">
						<label for="additional_instructions"><?php esc_html_e( 'Additional Instructions', 'ai-blog-posts' ); ?></label>
						<textarea id="additional_instructions" 
								  name="additional_instructions" 
								  class="large-text" 
								  rows="3"
								  placeholder="<?php esc_attr_e( 'Any specific requirements, tone preferences, or points to include...', 'ai-blog-posts' ); ?>"
								  <?php echo ! $is_verified ? 'disabled' : ''; ?>></textarea>
					</div>
				</div>

				<div class="form-section">
					<h2><?php esc_html_e( 'Post Settings', 'ai-blog-posts' ); ?></h2>
					
					<div class="form-row">
						<div class="form-field half">
							<label for="category_id"><?php esc_html_e( 'Category', 'ai-blog-posts' ); ?></label>
							<select id="category_id" name="category_id" <?php echo ! $is_verified ? 'disabled' : ''; ?>>
								<option value=""><?php esc_html_e( '— Select Category —', 'ai-blog-posts' ); ?></option>
								<?php foreach ( $categories as $category ) : ?>
									<option value="<?php echo esc_attr( $category->term_id ); ?>">
										<?php echo esc_html( $category->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="form-field half">
							<label for="model"><?php esc_html_e( 'AI Model', 'ai-blog-posts' ); ?></label>
							<select id="model" name="model" <?php echo ! $is_verified ? 'disabled' : ''; ?>>
								<?php foreach ( $models as $model_id => $model_info ) : ?>
									<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $current_model, $model_id ); ?>>
										<?php echo esc_html( $model_info['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div class="form-row">
						<div class="form-field half">
							<label for="post_status"><?php esc_html_e( 'Post Status', 'ai-blog-posts' ); ?></label>
							<select id="post_status" name="post_status" <?php echo ! $is_verified ? 'disabled' : ''; ?>>
								<option value="draft"><?php esc_html_e( 'Draft', 'ai-blog-posts' ); ?></option>
								<option value="pending"><?php esc_html_e( 'Pending Review', 'ai-blog-posts' ); ?></option>
								<option value="publish"><?php esc_html_e( 'Publish Immediately', 'ai-blog-posts' ); ?></option>
							</select>
						</div>

						<div class="form-field half">
							<label for="generate_image">
								<input type="checkbox" id="generate_image" name="generate_image" value="1" 
									   <?php checked( Ai_Blog_Posts_Settings::get( 'image_enabled' ) ); ?>
									   <?php echo ! $is_verified ? 'disabled' : ''; ?>>
								<?php esc_html_e( 'Generate Featured Image', 'ai-blog-posts' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Create an AI-generated featured image using DALL-E.', 'ai-blog-posts' ); ?></p>
						</div>
					</div>
				</div>

				<div class="form-actions">
					<button type="submit" id="generate-btn" class="button button-primary button-hero" <?php echo ! $is_verified ? 'disabled' : ''; ?>>
						<span class="dashicons dashicons-superhero-alt"></span>
						<?php esc_html_e( 'Generate Post', 'ai-blog-posts' ); ?>
					</button>
					<button type="button" id="add-to-queue-btn" class="button button-secondary button-hero" <?php echo ! $is_verified ? 'disabled' : ''; ?>>
						<span class="dashicons dashicons-plus"></span>
						<?php esc_html_e( 'Add to Queue', 'ai-blog-posts' ); ?>
					</button>
				</div>
			</form>
		</div>

		<!-- Generation Progress & Preview -->
		<div class="generate-preview-container" id="preview-container" style="display: none;">
			<div class="preview-header">
				<h2><?php esc_html_e( 'Generation Progress', 'ai-blog-posts' ); ?></h2>
				<button type="button" id="close-preview" class="button">
					<span class="dashicons dashicons-no"></span>
				</button>
			</div>

			<div class="generation-progress" id="generation-progress">
				<div class="progress-steps">
					<div class="progress-step" data-step="outline">
						<span class="step-icon"><span class="dashicons dashicons-list-view"></span></span>
						<span class="step-label"><?php esc_html_e( 'Creating Outline', 'ai-blog-posts' ); ?></span>
					</div>
					<div class="progress-step" data-step="content">
						<span class="step-icon"><span class="dashicons dashicons-edit"></span></span>
						<span class="step-label"><?php esc_html_e( 'Writing Content', 'ai-blog-posts' ); ?></span>
					</div>
					<div class="progress-step" data-step="humanize">
						<span class="step-icon"><span class="dashicons dashicons-admin-users"></span></span>
						<span class="step-label"><?php esc_html_e( 'Humanizing', 'ai-blog-posts' ); ?></span>
					</div>
					<div class="progress-step" data-step="seo">
						<span class="step-icon"><span class="dashicons dashicons-search"></span></span>
						<span class="step-label"><?php esc_html_e( 'SEO Optimization', 'ai-blog-posts' ); ?></span>
					</div>
					<div class="progress-step" data-step="image">
						<span class="step-icon"><span class="dashicons dashicons-format-image"></span></span>
						<span class="step-label"><?php esc_html_e( 'Generating Image', 'ai-blog-posts' ); ?></span>
					</div>
					<div class="progress-step" data-step="complete">
						<span class="step-icon"><span class="dashicons dashicons-yes-alt"></span></span>
						<span class="step-label"><?php esc_html_e( 'Complete', 'ai-blog-posts' ); ?></span>
					</div>
				</div>
				<div class="progress-bar">
					<div class="progress-fill" id="progress-fill"></div>
				</div>
				<p class="progress-status" id="progress-status"><?php esc_html_e( 'Starting generation...', 'ai-blog-posts' ); ?></p>
			</div>

			<div class="preview-content" id="preview-content" style="display: none;">
				<div class="preview-meta">
					<span class="preview-model"><span class="dashicons dashicons-admin-generic"></span> <span id="result-model"></span></span>
					<span class="preview-tokens"><span class="dashicons dashicons-editor-code"></span> <span id="result-tokens"></span> tokens</span>
					<span class="preview-cost"><span class="dashicons dashicons-money-alt"></span> $<span id="result-cost"></span></span>
					<span class="preview-time"><span class="dashicons dashicons-clock"></span> <span id="result-time"></span>s</span>
				</div>

				<div class="preview-title">
					<h3 id="preview-title-text"></h3>
				</div>

				<div class="preview-body" id="preview-body"></div>

				<div class="preview-actions">
					<a href="#" id="edit-post-btn" class="button button-primary" target="_blank">
						<span class="dashicons dashicons-edit"></span>
						<?php esc_html_e( 'Edit Post', 'ai-blog-posts' ); ?>
					</a>
					<a href="#" id="view-post-btn" class="button button-secondary" target="_blank">
						<span class="dashicons dashicons-visibility"></span>
						<?php esc_html_e( 'View Post', 'ai-blog-posts' ); ?>
					</a>
					<button type="button" id="generate-another-btn" class="button">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Generate Another', 'ai-blog-posts' ); ?>
					</button>
				</div>
			</div>

			<div class="preview-error" id="preview-error" style="display: none;">
				<span class="dashicons dashicons-warning"></span>
				<p id="error-message"></p>
				<button type="button" id="retry-btn" class="button button-primary">
					<?php esc_html_e( 'Try Again', 'ai-blog-posts' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>


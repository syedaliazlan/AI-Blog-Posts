<?php
/**
 * Settings page template
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

// Get current settings
$settings = Ai_Blog_Posts_Settings::get_all( true );
$models = Ai_Blog_Posts_Settings::get_models();
$categories = get_categories( array( 'hide_empty' => false ) );
$authors = get_users( array( 'role__in' => array( 'administrator', 'editor', 'author' ) ) );

// Get current tab
$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'api';

// Check for Yoast/RankMath
$yoast_active = defined( 'WPSEO_VERSION' );
$rankmath_active = class_exists( 'RankMath' );
?>

<div class="wrap ai-blog-posts-wrap">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'AI Blog Posts Settings', 'ai-blog-posts' ); ?>
	</h1>

	<nav class="nav-tab-wrapper">
		<a href="?page=ai-blog-posts-settings&tab=api" class="nav-tab <?php echo 'api' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-admin-network"></span>
			<?php esc_html_e( 'API Configuration', 'ai-blog-posts' ); ?>
		</a>
		<a href="?page=ai-blog-posts-settings&tab=content" class="nav-tab <?php echo 'content' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-edit"></span>
			<?php esc_html_e( 'Content Settings', 'ai-blog-posts' ); ?>
		</a>
		<a href="?page=ai-blog-posts-settings&tab=schedule" class="nav-tab <?php echo 'schedule' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-calendar-alt"></span>
			<?php esc_html_e( 'Scheduling', 'ai-blog-posts' ); ?>
		</a>
		<a href="?page=ai-blog-posts-settings&tab=seo" class="nav-tab <?php echo 'seo' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-search"></span>
			<?php esc_html_e( 'SEO Integration', 'ai-blog-posts' ); ?>
		</a>
	</nav>

	<div class="ai-blog-posts-settings-content">
		<form id="ai-blog-posts-settings-form" class="ai-blog-posts-form">
			<?php wp_nonce_field( 'ai_blog_posts_nonce', 'ai_blog_posts_nonce' ); ?>

			<!-- API Configuration Tab -->
			<div class="settings-tab <?php echo 'api' === $current_tab ? 'active' : ''; ?>" data-tab="api">
				<div class="settings-section">
					<h2><?php esc_html_e( 'OpenAI API Configuration', 'ai-blog-posts' ); ?></h2>
					<p class="description">
						<?php 
						printf(
							/* translators: %s: OpenAI platform URL */
							esc_html__( 'Get your API key from the %s.', 'ai-blog-posts' ),
							'<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>'
						);
						?>
					</p>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="api_key"><?php esc_html_e( 'API Key', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<div class="api-key-wrapper">
									<input type="password" 
										   id="api_key" 
										   name="api_key" 
										   class="regular-text" 
										   placeholder="sk-..."
										   value="<?php echo esc_attr( $settings['api_key'] ); ?>"
										   autocomplete="off">
									<button type="button" id="toggle-api-key" class="button" title="<?php esc_attr_e( 'Show/Hide API Key', 'ai-blog-posts' ); ?>">
										<span class="dashicons dashicons-visibility"></span>
									</button>
									<button type="button" id="verify-api-key" class="button button-secondary">
										<?php esc_html_e( 'Verify Key', 'ai-blog-posts' ); ?>
									</button>
								</div>
								<p class="description" id="api-key-status">
									<?php if ( Ai_Blog_Posts_Settings::is_verified() ) : ?>
										<span class="status-success"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'API key verified and working.', 'ai-blog-posts' ); ?></span>
									<?php elseif ( Ai_Blog_Posts_Settings::is_configured() ) : ?>
										<span class="status-success"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'API key configured. You can generate posts now.', 'ai-blog-posts' ); ?></span>
										<br><small><?php esc_html_e( 'Verification is optional - if it fails, your API key may still work for post generation.', 'ai-blog-posts' ); ?></small>
									<?php else : ?>
										<?php esc_html_e( 'Enter your OpenAI API key to get started.', 'ai-blog-posts' ); ?>
									<?php endif; ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="org_id"><?php esc_html_e( 'Organization ID', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<input type="text" 
									   id="org_id" 
									   name="org_id" 
									   class="regular-text" 
									   placeholder="org-..."
									   value="<?php echo esc_attr( $settings['org_id'] ); ?>">
								<p class="description"><?php esc_html_e( 'Optional. Only required if you belong to multiple organizations.', 'ai-blog-posts' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="model"><?php esc_html_e( 'Default Model', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<select id="model" name="model" class="regular-text">
									<?php foreach ( $models as $model_id => $model_info ) : ?>
										<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $settings['model'], $model_id ); ?>>
											<?php echo esc_html( $model_info['name'] ); ?><?php echo ! empty( $model_info['recommended'] ) ? ' ⭐' : ''; ?> - <?php echo esc_html( $model_info['description'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( '⭐ = Recommended for blog writing. GPT-4o Mini offers the best value.', 'ai-blog-posts' ); ?></p>
								<div class="model-pricing">
									<?php foreach ( $models as $model_id => $model_info ) : ?>
										<p class="model-price" data-model="<?php echo esc_attr( $model_id ); ?>" style="display: <?php echo $model_id === $settings['model'] ? 'block' : 'none'; ?>;">
											<strong><?php esc_html_e( 'Pricing:', 'ai-blog-posts' ); ?></strong>
											$<?php echo esc_html( $model_info['input_cost'] ); ?>/1M input tokens, 
											$<?php echo esc_html( $model_info['output_cost'] ); ?>/1M output tokens
										</p>
									<?php endforeach; ?>
								</div>
							</td>
						</tr>
					</table>
				</div>

				<div class="settings-section">
					<h2><?php esc_html_e( 'Pexels Image Integration', 'ai-blog-posts' ); ?></h2>
					<p class="description">
						<?php 
						printf(
							/* translators: %s: Pexels API URL */
							esc_html__( 'Get your free API key from %s. Pexels provides high-quality stock photos that will be automatically embedded in your blog posts.', 'ai-blog-posts' ),
							'<a href="https://www.pexels.com/api/" target="_blank">Pexels API</a>'
						);
						?>
					</p>
					
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="pexels_api_key"><?php esc_html_e( 'Pexels API Key', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<div class="api-key-wrapper">
									<input type="password" 
										   id="pexels_api_key" 
										   name="pexels_api_key" 
										   class="regular-text" 
										   placeholder="Your Pexels API key"
										   value="<?php echo esc_attr( $settings['pexels_api_key'] ); ?>"
										   autocomplete="off">
									<button type="button" id="toggle-pexels-api-key" class="button" title="<?php esc_attr_e( 'Show/Hide API Key', 'ai-blog-posts' ); ?>">
										<span class="dashicons dashicons-visibility"></span>
									</button>
									<button type="button" id="verify-pexels-api-key" class="button button-secondary">
										<?php esc_html_e( 'Verify Key', 'ai-blog-posts' ); ?>
									</button>
								</div>
								<p class="description" id="pexels-api-key-status">
									<?php if ( ! empty( $settings['pexels_api_key'] ) ) : ?>
										<span class="status-info"><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'API key configured. Click "Verify Key" to test.', 'ai-blog-posts' ); ?></span>
									<?php else : ?>
										<span class="status-warning"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Pexels API key is required for image generation.', 'ai-blog-posts' ); ?></span>
									<?php endif; ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="image_enabled"><?php esc_html_e( 'Enable Images', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<label class="switch">
									<input type="checkbox" id="image_enabled" name="image_enabled" value="1" <?php checked( $settings['image_enabled'] ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Automatically search and embed relevant Pexels images in your blog posts. GPT-5.2 will suggest images based on content context.', 'ai-blog-posts' ); ?></p>
							</td>
						</tr>
						<tr class="image-settings" style="<?php echo $settings['image_enabled'] ? '' : 'display:none;'; ?>">
							<th scope="row">
								<label for="pexels_orientation"><?php esc_html_e( 'Image Orientation', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<select id="pexels_orientation" name="pexels_orientation">
									<option value="landscape" <?php selected( $settings['pexels_orientation'] ?? 'landscape', 'landscape' ); ?>><?php esc_html_e( 'Landscape', 'ai-blog-posts' ); ?></option>
									<option value="portrait" <?php selected( $settings['pexels_orientation'] ?? 'landscape', 'portrait' ); ?>><?php esc_html_e( 'Portrait', 'ai-blog-posts' ); ?></option>
									<option value="square" <?php selected( $settings['pexels_orientation'] ?? 'landscape', 'square' ); ?>><?php esc_html_e( 'Square', 'ai-blog-posts' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Preferred orientation for Pexels images.', 'ai-blog-posts' ); ?></p>
							</td>
						</tr>
						<tr class="image-settings" style="<?php echo $settings['image_enabled'] ? '' : 'display:none;'; ?>">
							<th scope="row">
								<label for="pexels_inline_images"><?php esc_html_e( 'Inline Images', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<select id="pexels_inline_images" name="pexels_inline_images">
									<option value="0" <?php selected( $settings['pexels_inline_images'] ?? 3, 0 ); ?>><?php esc_html_e( 'None (featured image only)', 'ai-blog-posts' ); ?></option>
									<option value="1" <?php selected( $settings['pexels_inline_images'] ?? 3, 1 ); ?>>1</option>
									<option value="2" <?php selected( $settings['pexels_inline_images'] ?? 3, 2 ); ?>>2</option>
									<option value="3" <?php selected( $settings['pexels_inline_images'] ?? 3, 3 ); ?>>3</option>
									<option value="4" <?php selected( $settings['pexels_inline_images'] ?? 3, 4 ); ?>>4</option>
									<option value="5" <?php selected( $settings['pexels_inline_images'] ?? 3, 5 ); ?>>5</option>
								</select>
								<p class="description"><?php esc_html_e( 'Number of images to embed within the post content. Images are distributed across different sections.', 'ai-blog-posts' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- Content Settings Tab -->
			<div class="settings-tab <?php echo 'content' === $current_tab ? 'active' : ''; ?>" data-tab="content">
				<div class="settings-section">
					<h2><?php esc_html_e( 'Post Settings', 'ai-blog-posts' ); ?></h2>
					
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="post_status"><?php esc_html_e( 'Default Post Status', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<select id="post_status" name="post_status">
									<option value="draft" <?php selected( $settings['post_status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'ai-blog-posts' ); ?></option>
									<option value="pending" <?php selected( $settings['post_status'], 'pending' ); ?>><?php esc_html_e( 'Pending Review', 'ai-blog-posts' ); ?></option>
									<option value="publish" <?php selected( $settings['post_status'], 'publish' ); ?>><?php esc_html_e( 'Published', 'ai-blog-posts' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'The status assigned to newly generated posts.', 'ai-blog-posts' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="default_author"><?php esc_html_e( 'Default Author', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<select id="default_author" name="default_author">
									<?php foreach ( $authors as $author ) : ?>
										<option value="<?php echo esc_attr( $author->ID ); ?>" <?php selected( $settings['default_author'], $author->ID ); ?>>
											<?php echo esc_html( $author->display_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="categories"><?php esc_html_e( 'Default Categories', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<select id="categories" name="categories[]" multiple class="regular-text" style="height: 150px;">
									<?php foreach ( $categories as $category ) : ?>
										<option value="<?php echo esc_attr( $category->term_id ); ?>" 
											<?php echo in_array( $category->term_id, (array) $settings['categories'], true ) ? 'selected' : ''; ?>>
											<?php echo esc_html( $category->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple categories.', 'ai-blog-posts' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="use_topic_as_title"><?php esc_html_e( 'Title Source', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<label class="switch">
									<input type="checkbox" id="use_topic_as_title" name="use_topic_as_title" value="1" <?php checked( $settings['use_topic_as_title'] ?? false ); ?>>
									<span class="slider"></span>
								</label>
								<span style="margin-left: 10px;"><?php esc_html_e( 'Use topic from queue as post title', 'ai-blog-posts' ); ?></span>
								<p class="description"><?php esc_html_e( 'When enabled, the exact topic text will be used as the post title. When disabled, GPT-5.2 will generate an optimized title.', 'ai-blog-posts' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="settings-section">
					<h2><?php esc_html_e( 'Content Quality', 'ai-blog-posts' ); ?></h2>
					
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="word_count_min"><?php esc_html_e( 'Word Count Range', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<div class="range-inputs">
									<input type="number" id="word_count_min" name="word_count_min" 
										   value="<?php echo esc_attr( $settings['word_count_min'] ); ?>" 
										   min="300" max="5000" step="100">
									<span><?php esc_html_e( 'to', 'ai-blog-posts' ); ?></span>
									<input type="number" id="word_count_max" name="word_count_max" 
										   value="<?php echo esc_attr( $settings['word_count_max'] ); ?>" 
										   min="500" max="10000" step="100">
									<span><?php esc_html_e( 'words', 'ai-blog-posts' ); ?></span>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="humanize_level"><?php esc_html_e( 'Humanization Level', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<div class="humanize-slider">
									<input type="range" id="humanize_level" name="humanize_level" 
										   value="<?php echo esc_attr( $settings['humanize_level'] ); ?>" 
										   min="1" max="5" step="1">
									<div class="slider-labels">
										<span><?php esc_html_e( 'Standard', 'ai-blog-posts' ); ?></span>
										<span><?php esc_html_e( 'Balanced', 'ai-blog-posts' ); ?></span>
										<span><?php esc_html_e( 'Human-like', 'ai-blog-posts' ); ?></span>
									</div>
								</div>
								<p class="description"><?php esc_html_e( 'Higher levels add more variety and personality to reduce AI-detectable patterns.', 'ai-blog-posts' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="settings-section">
					<h2><?php esc_html_e( 'Website Context', 'ai-blog-posts' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Analyze your existing content to help the AI match your writing style.', 'ai-blog-posts' ); ?></p>
					
					<div class="analysis-actions">
						<button type="button" id="analyze-website" class="button button-secondary" data-use-ai="false">
							<span class="dashicons dashicons-search"></span>
							<?php esc_html_e( 'Quick Analysis', 'ai-blog-posts' ); ?>
						</button>
						<button type="button" id="analyze-website-ai" class="button button-primary" data-use-ai="true">
							<span class="dashicons dashicons-superhero-alt"></span>
							<?php esc_html_e( 'AI-Powered Analysis', 'ai-blog-posts' ); ?>
							<span class="cost-badge">~$0.01</span>
						</button>
						<span id="analysis-status">
							<?php if ( ! empty( $settings['last_analysis'] ) ) : ?>
								<span class="status-success">
									<?php 
									printf(
										/* translators: %s: date of last analysis */
										esc_html__( 'Last analyzed: %s', 'ai-blog-posts' ),
										esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $settings['last_analysis'] ) ) )
									);
									?>
								</span>
							<?php endif; ?>
						</span>
					</div>

					<?php
					// Get cached analysis
					$analyzer = new Ai_Blog_Posts_Analyzer();
					$cached_analysis = $analyzer->get_cached_analysis();
					?>

					<div id="analysis-result" class="analysis-result-full" <?php echo empty( $cached_analysis ) ? 'style="display:none;"' : ''; ?>>
						<?php if ( ! empty( $cached_analysis ) ) : ?>
							<div class="analysis-grid">
								<!-- Content Stats -->
								<div class="analysis-card">
									<h4><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'Content Statistics', 'ai-blog-posts' ); ?></h4>
									<div class="analysis-data">
										<div class="data-row">
											<span class="data-label"><?php esc_html_e( 'Average Word Count', 'ai-blog-posts' ); ?></span>
											<span class="data-value"><?php echo esc_html( $cached_analysis['content_stats']['avg_word_count'] ?? 'N/A' ); ?></span>
										</div>
										<div class="data-row">
											<span class="data-label"><?php esc_html_e( 'Word Count Range', 'ai-blog-posts' ); ?></span>
											<span class="data-value">
												<?php 
												echo esc_html( 
													( $cached_analysis['content_stats']['min_word_count'] ?? 0 ) . ' - ' . 
													( $cached_analysis['content_stats']['max_word_count'] ?? 0 ) 
												); 
												?>
											</span>
										</div>
										<div class="data-row">
											<span class="data-label"><?php esc_html_e( 'Avg Paragraphs', 'ai-blog-posts' ); ?></span>
											<span class="data-value"><?php echo esc_html( $cached_analysis['content_stats']['avg_paragraphs'] ?? 'N/A' ); ?></span>
										</div>
										<div class="data-row">
											<span class="data-label"><?php esc_html_e( 'Avg Headings', 'ai-blog-posts' ); ?></span>
											<span class="data-value"><?php echo esc_html( $cached_analysis['content_stats']['avg_headings'] ?? 'N/A' ); ?></span>
										</div>
									</div>
								</div>

								<!-- Writing Style -->
								<div class="analysis-card">
									<h4><span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Writing Style', 'ai-blog-posts' ); ?></h4>
									<div class="analysis-data">
										<div class="data-row">
											<span class="data-label"><?php esc_html_e( 'Tone', 'ai-blog-posts' ); ?></span>
											<span class="data-value style-badge tone-<?php echo esc_attr( $cached_analysis['writing_style']['tone'] ?? '' ); ?>">
												<?php echo esc_html( ucfirst( $cached_analysis['writing_style']['tone'] ?? 'N/A' ) ); ?>
											</span>
										</div>
										<div class="data-row">
											<span class="data-label"><?php esc_html_e( 'Voice', 'ai-blog-posts' ); ?></span>
											<span class="data-value style-badge">
												<?php 
												$voice = $cached_analysis['writing_style']['voice'] ?? '';
												$voice_labels = array(
													'first_person' => __( 'First Person (we, our)', 'ai-blog-posts' ),
													'second_person' => __( 'Second Person (you)', 'ai-blog-posts' ),
													'third_person' => __( 'Third Person', 'ai-blog-posts' ),
												);
												echo esc_html( $voice_labels[ $voice ] ?? ucfirst( str_replace( '_', ' ', $voice ) ) );
												?>
											</span>
										</div>
										<div class="data-row">
											<span class="data-label"><?php esc_html_e( 'Avg Sentence Length', 'ai-blog-posts' ); ?></span>
											<span class="data-value"><?php echo esc_html( $cached_analysis['writing_style']['avg_sentence_length'] ?? 'N/A' ); ?> <?php esc_html_e( 'words', 'ai-blog-posts' ); ?></span>
										</div>
										<div class="data-row">
											<span class="data-label"><?php esc_html_e( 'Uses Questions', 'ai-blog-posts' ); ?></span>
											<span class="data-value">
												<?php if ( ! empty( $cached_analysis['writing_style']['uses_questions'] ) ) : ?>
													<span class="dashicons dashicons-yes-alt" style="color: var(--aibp-success);"></span> <?php esc_html_e( 'Yes', 'ai-blog-posts' ); ?>
												<?php else : ?>
													<span class="dashicons dashicons-minus" style="color: var(--aibp-gray-400);"></span> <?php esc_html_e( 'Rarely', 'ai-blog-posts' ); ?>
												<?php endif; ?>
											</span>
										</div>
									</div>
								</div>

								<!-- Structure -->
								<div class="analysis-card">
									<h4><span class="dashicons dashicons-layout"></span> <?php esc_html_e( 'Content Structure', 'ai-blog-posts' ); ?></h4>
									<div class="analysis-data">
										<div class="data-row">
											<span class="data-label"><?php esc_html_e( 'Has Introduction', 'ai-blog-posts' ); ?></span>
											<span class="data-value">
												<?php echo ! empty( $cached_analysis['structure']['typically_has_intro'] ) ? '<span class="dashicons dashicons-yes-alt" style="color: var(--aibp-success);"></span>' : '<span class="dashicons dashicons-minus" style="color: var(--aibp-gray-400);"></span>'; ?>
											</span>
										</div>
										<div class="data-row">
											<span class="data-label"><?php esc_html_e( 'Has Conclusion', 'ai-blog-posts' ); ?></span>
											<span class="data-value">
												<?php echo ! empty( $cached_analysis['structure']['typically_has_conclusion'] ) ? '<span class="dashicons dashicons-yes-alt" style="color: var(--aibp-success);"></span>' : '<span class="dashicons dashicons-minus" style="color: var(--aibp-gray-400);"></span>'; ?>
											</span>
										</div>
										<div class="data-row">
											<span class="data-label"><?php esc_html_e( 'Uses Lists', 'ai-blog-posts' ); ?></span>
											<span class="data-value">
												<?php echo ! empty( $cached_analysis['structure']['uses_lists_frequently'] ) ? '<span class="dashicons dashicons-yes-alt" style="color: var(--aibp-success);"></span>' : '<span class="dashicons dashicons-minus" style="color: var(--aibp-gray-400);"></span>'; ?>
											</span>
										</div>
										<div class="data-row">
											<span class="data-label"><?php esc_html_e( 'Uses Images', 'ai-blog-posts' ); ?></span>
											<span class="data-value">
												<?php echo ! empty( $cached_analysis['structure']['uses_images'] ) ? '<span class="dashicons dashicons-yes-alt" style="color: var(--aibp-success);"></span>' : '<span class="dashicons dashicons-minus" style="color: var(--aibp-gray-400);"></span>'; ?>
											</span>
										</div>
									</div>
								</div>

								<!-- Topics & Keywords -->
								<div class="analysis-card">
									<h4><span class="dashicons dashicons-tag"></span> <?php esc_html_e( 'Common Keywords', 'ai-blog-posts' ); ?></h4>
									<div class="keyword-cloud">
										<?php 
										$keywords = $cached_analysis['topics']['common_keywords'] ?? array();
										$keywords = array_slice( $keywords, 0, 15 );
										foreach ( $keywords as $keyword ) : ?>
											<span class="keyword-tag"><?php echo esc_html( $keyword ); ?></span>
										<?php endforeach; ?>
										<?php if ( empty( $keywords ) ) : ?>
											<em><?php esc_html_e( 'No keywords extracted', 'ai-blog-posts' ); ?></em>
										<?php endif; ?>
									</div>
								</div>
							</div>

							<!-- AI Insights (if available) -->
							<?php if ( ! empty( $cached_analysis['ai_insights']['style_guide'] ) ) : ?>
								<div class="analysis-card ai-insights-card">
									<h4><span class="dashicons dashicons-superhero-alt"></span> <?php esc_html_e( 'AI Style Guide', 'ai-blog-posts' ); ?></h4>
									<div class="ai-style-guide">
										<?php echo wp_kses_post( nl2br( esc_html( $cached_analysis['ai_insights']['style_guide'] ) ) ); ?>
									</div>
									<p class="tokens-used">
										<small><?php printf( esc_html__( 'Tokens used: %d', 'ai-blog-posts' ), $cached_analysis['ai_insights']['tokens_used'] ?? 0 ); ?></small>
									</p>
								</div>
							<?php endif; ?>

							<!-- Generated Style Prompt -->
							<div class="analysis-card style-prompt-card">
								<h4><span class="dashicons dashicons-format-quote"></span> <?php esc_html_e( 'Generated Style Prompt', 'ai-blog-posts' ); ?></h4>
								<p class="description"><?php esc_html_e( 'This prompt is automatically added when generating content:', 'ai-blog-posts' ); ?></p>
								<div class="style-prompt-preview">
									<code><?php echo esc_html( $analyzer->get_style_prompt() ); ?></code>
								</div>
							</div>

							<p class="analysis-meta">
								<span class="dashicons dashicons-clock"></span>
								<?php 
								printf(
									esc_html__( 'Based on %d posts analyzed on %s', 'ai-blog-posts' ),
									$cached_analysis['posts_analyzed'] ?? 0,
									date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $cached_analysis['analyzed_at'] ?? '' ) )
								);
								?>
							</p>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- Scheduling Tab -->
			<div class="settings-tab <?php echo 'schedule' === $current_tab ? 'active' : ''; ?>" data-tab="schedule">
				<div class="settings-section">
					<h2><?php esc_html_e( 'Automatic Posting', 'ai-blog-posts' ); ?></h2>
					
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="schedule_enabled"><?php esc_html_e( 'Enable Auto-Posting', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<label class="switch">
									<input type="checkbox" id="schedule_enabled" name="schedule_enabled" value="1" <?php checked( $settings['schedule_enabled'] ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Automatically generate and publish posts from the topic queue.', 'ai-blog-posts' ); ?></p>
							</td>
						</tr>
						<tr class="schedule-settings" style="<?php echo $settings['schedule_enabled'] ? '' : 'display:none;'; ?>">
							<th scope="row">
								<label for="schedule_frequency"><?php esc_html_e( 'Frequency', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<select id="schedule_frequency" name="schedule_frequency">
									<option value="hourly" <?php selected( $settings['schedule_frequency'], 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'ai-blog-posts' ); ?></option>
									<option value="twicedaily" <?php selected( $settings['schedule_frequency'], 'twicedaily' ); ?>><?php esc_html_e( 'Twice Daily', 'ai-blog-posts' ); ?></option>
									<option value="daily" <?php selected( $settings['schedule_frequency'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'ai-blog-posts' ); ?></option>
									<option value="weekly" <?php selected( $settings['schedule_frequency'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'ai-blog-posts' ); ?></option>
								</select>
							</td>
						</tr>
						<tr class="schedule-settings schedule-day-row" style="<?php 
							echo ( $settings['schedule_enabled'] && $settings['schedule_frequency'] === 'weekly' ) ? '' : 'display:none;'; 
						?>">
							<th scope="row">
								<label for="schedule_day"><?php esc_html_e( 'Day of Week', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<?php $schedule_day = $settings['schedule_day'] ?? 1; ?>
								<select id="schedule_day" name="schedule_day">
									<option value="1" <?php selected( $schedule_day, 1 ); ?>><?php esc_html_e( 'Monday', 'ai-blog-posts' ); ?></option>
									<option value="2" <?php selected( $schedule_day, 2 ); ?>><?php esc_html_e( 'Tuesday', 'ai-blog-posts' ); ?></option>
									<option value="3" <?php selected( $schedule_day, 3 ); ?>><?php esc_html_e( 'Wednesday', 'ai-blog-posts' ); ?></option>
									<option value="4" <?php selected( $schedule_day, 4 ); ?>><?php esc_html_e( 'Thursday', 'ai-blog-posts' ); ?></option>
									<option value="5" <?php selected( $schedule_day, 5 ); ?>><?php esc_html_e( 'Friday', 'ai-blog-posts' ); ?></option>
									<option value="6" <?php selected( $schedule_day, 6 ); ?>><?php esc_html_e( 'Saturday', 'ai-blog-posts' ); ?></option>
									<option value="0" <?php selected( $schedule_day, 0 ); ?>><?php esc_html_e( 'Sunday', 'ai-blog-posts' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Select which day of the week to run scheduled posts.', 'ai-blog-posts' ); ?></p>
							</td>
						</tr>
						<tr class="schedule-settings schedule-time-row" style="<?php 
							echo ( $settings['schedule_enabled'] && $settings['schedule_frequency'] !== 'hourly' ) ? '' : 'display:none;'; 
						?>">
							<th scope="row">
								<label for="schedule_time"><?php 
									echo $settings['schedule_frequency'] === 'twicedaily' 
										? esc_html__( 'First Time Slot', 'ai-blog-posts' ) 
										: esc_html__( 'Preferred Time', 'ai-blog-posts' ); 
								?></label>
							</th>
							<td>
								<input type="time" id="schedule_time" name="schedule_time" 
									   value="<?php echo esc_attr( $settings['schedule_time'] ); ?>">
								<p class="description schedule-time-desc" data-timezone="<?php echo esc_attr( wp_timezone_string() ); ?>">
									<?php 
									$frequency = $settings['schedule_frequency'];
									$timezone = wp_timezone_string();
									if ( $frequency === 'twicedaily' ) {
										/* translators: %s: WordPress timezone string */
										printf( esc_html__( 'First posting time (WordPress timezone: %s).', 'ai-blog-posts' ), esc_html( $timezone ) );
									} elseif ( $frequency === 'weekly' ) {
										/* translators: %s: WordPress timezone string */
										printf( esc_html__( 'Posts will run at this time on the selected day (WordPress timezone: %s).', 'ai-blog-posts' ), esc_html( $timezone ) );
									} else {
										/* translators: %s: WordPress timezone string */
										printf( esc_html__( 'Posts will run once daily at this time (WordPress timezone: %s).', 'ai-blog-posts' ), esc_html( $timezone ) );
									}
									?>
								</p>
							</td>
						</tr>
						<tr class="schedule-settings schedule-time-2-row" style="<?php 
							echo ( $settings['schedule_enabled'] && $settings['schedule_frequency'] === 'twicedaily' ) ? '' : 'display:none;'; 
						?>">
							<th scope="row">
								<label for="schedule_time_2"><?php esc_html_e( 'Second Time Slot', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<input type="time" id="schedule_time_2" name="schedule_time_2" 
									   value="<?php echo esc_attr( $settings['schedule_time_2'] ?? '21:00' ); ?>">
								<p class="description">
									<?php 
									/* translators: %s: WordPress timezone string */
									printf( esc_html__( 'Second posting time (WordPress timezone: %s).', 'ai-blog-posts' ), esc_html( wp_timezone_string() ) ); 
									?>
								</p>
							</td>
						</tr>
						<tr class="schedule-settings" style="<?php echo $settings['schedule_enabled'] ? '' : 'display:none;'; ?>">
							<th scope="row">
								<label for="max_posts_per_day"><?php esc_html_e( 'Max Posts Per Day', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<input type="number" id="max_posts_per_day" name="max_posts_per_day" 
									   value="<?php echo esc_attr( $settings['max_posts_per_day'] ); ?>" 
									   min="1" max="10">
								<p class="description"><?php esc_html_e( 'Limit the number of posts generated per day.', 'ai-blog-posts' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- External Cron Configuration -->
				<div class="settings-section schedule-settings" style="<?php echo $settings['schedule_enabled'] ? '' : 'display:none;'; ?>">
					<h2><?php esc_html_e( 'Reliable Scheduling (Recommended)', 'ai-blog-posts' ); ?></h2>
					<p class="section-description">
						<?php esc_html_e( 'WordPress cron only runs when someone visits your site. For reliable scheduled posting, use cron-job.org (free) for automatic triggering.', 'ai-blog-posts' ); ?>
					</p>
					
					<!-- cron-job.org Automatic Integration -->
					<div class="cronjob-org-integration">
						<h3><span class="dashicons dashicons-cloud"></span> <?php esc_html_e( 'Automatic Setup with cron-job.org', 'ai-blog-posts' ); ?></h3>
						
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="cronjob_org_api_key"><?php esc_html_e( 'cron-job.org API Key', 'ai-blog-posts' ); ?></label>
								</th>
								<td>
									<div class="api-key-row">
										<input type="password" id="cronjob_org_api_key" name="cronjob_org_api_key" 
											   class="regular-text" 
											   value="<?php echo esc_attr( $settings['cronjob_org_api_key'] ?? '' ); ?>"
											   placeholder="<?php esc_attr_e( 'Enter your cron-job.org API key', 'ai-blog-posts' ); ?>">
										<button type="button" class="button" id="toggle-cronjob-api-key">
											<span class="dashicons dashicons-visibility"></span>
										</button>
										<button type="button" class="button button-primary" id="test-cronjob-org-api">
											<?php esc_html_e( 'Verify & Save', 'ai-blog-posts' ); ?>
										</button>
									</div>
									<p class="description">
										<?php echo wp_kses_post( sprintf(
											/* translators: %s: link to cron-job.org settings */
											__( 'Get your API key from <a href="%s" target="_blank" rel="noopener">cron-job.org Settings</a> (free account required).', 'ai-blog-posts' ),
											'https://console.cron-job.org/settings'
										) ); ?>
									</p>
								</td>
							</tr>
						</table>
						
						<!-- Status Panel (shown when API is configured) -->
						<div id="cronjob-org-status-panel" class="cronjob-status-panel" style="display: none;">
							<div class="status-header">
								<h4><?php esc_html_e( 'cron-job.org Status', 'ai-blog-posts' ); ?></h4>
								<button type="button" class="button button-small" id="refresh-cronjob-status">
									<span class="dashicons dashicons-update"></span>
								</button>
							</div>
							<div class="status-content">
								<div class="status-row">
									<span class="status-label"><?php esc_html_e( 'Status:', 'ai-blog-posts' ); ?></span>
									<span class="status-value" id="cronjob-status-badge">-</span>
								</div>
								<div class="status-row">
									<span class="status-label"><?php esc_html_e( 'Last Run:', 'ai-blog-posts' ); ?></span>
									<span class="status-value" id="cronjob-last-run">-</span>
								</div>
								<div class="status-row">
									<span class="status-label"><?php esc_html_e( 'Next Run:', 'ai-blog-posts' ); ?></span>
									<span class="status-value" id="cronjob-next-run">-</span>
								</div>
								<div class="status-row">
									<span class="status-label"><?php esc_html_e( 'Last Status:', 'ai-blog-posts' ); ?></span>
									<span class="status-value" id="cronjob-last-status">-</span>
								</div>
							</div>
							<div class="status-actions">
								<button type="button" class="button" id="toggle-cronjob-enabled">
									<?php esc_html_e( 'Enable/Disable', 'ai-blog-posts' ); ?>
								</button>
								<button type="button" class="button" id="sync-cronjob-url">
									<?php esc_html_e( 'Sync URL', 'ai-blog-posts' ); ?>
								</button>
								<button type="button" class="button button-link-delete" id="delete-cronjob">
									<?php esc_html_e( 'Delete Job', 'ai-blog-posts' ); ?>
								</button>
							</div>
						</div>
						
						<!-- Create Job Panel (shown when no job exists) -->
						<div id="cronjob-org-create-panel" class="cronjob-create-panel" style="display: none;">
							<p><?php esc_html_e( 'API key verified! Create a cron job to automatically trigger your scheduled posts.', 'ai-blog-posts' ); ?></p>
							<div class="create-options">
								<label for="cronjob-interval"><?php esc_html_e( 'Check every:', 'ai-blog-posts' ); ?></label>
								<select id="cronjob-interval">
									<option value="5"><?php esc_html_e( '5 minutes (recommended)', 'ai-blog-posts' ); ?></option>
									<option value="10"><?php esc_html_e( '10 minutes', 'ai-blog-posts' ); ?></option>
									<option value="15"><?php esc_html_e( '15 minutes', 'ai-blog-posts' ); ?></option>
									<option value="30"><?php esc_html_e( '30 minutes', 'ai-blog-posts' ); ?></option>
								</select>
								<button type="button" class="button button-primary" id="create-cronjob">
									<span class="dashicons dashicons-yes"></span>
									<?php esc_html_e( 'Create Cron Job', 'ai-blog-posts' ); ?>
								</button>
							</div>
						</div>
					</div>
					
					<!-- Manual Setup (Fallback) -->
					<details class="manual-cron-setup">
						<summary><?php esc_html_e( 'Manual Setup (Alternative)', 'ai-blog-posts' ); ?></summary>
						
						<table class="form-table">
							<tr>
								<th scope="row">
									<?php esc_html_e( 'Your Cron URL', 'ai-blog-posts' ); ?>
								</th>
								<td>
									<?php $cron_url = Ai_Blog_Posts_Settings::get_cron_url(); ?>
									<div class="cron-url-container">
										<input type="text" id="cron_url" class="large-text code" 
											   value="<?php echo esc_url( $cron_url ); ?>" 
											   readonly onclick="this.select();">
										<button type="button" class="button button-secondary" id="copy-cron-url" 
												data-url="<?php echo esc_url( $cron_url ); ?>">
											<?php esc_html_e( 'Copy', 'ai-blog-posts' ); ?>
										</button>
									</div>
									<p class="description">
										<?php esc_html_e( 'This URL includes a secret key for security. Do not share it publicly.', 'ai-blog-posts' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<?php esc_html_e( 'Server Crontab', 'ai-blog-posts' ); ?>
								</th>
								<td>
									<p><?php esc_html_e( 'Add this to your server crontab (runs every 5 minutes):', 'ai-blog-posts' ); ?></p>
									<code class="cron-command">*/5 * * * * curl -s "<?php echo esc_url( $cron_url ); ?>" > /dev/null 2>&1</code>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<?php esc_html_e( 'Regenerate Secret', 'ai-blog-posts' ); ?>
								</th>
								<td>
									<button type="button" class="button button-secondary" id="regenerate-cron-secret">
										<?php esc_html_e( 'Generate New Secret Key', 'ai-blog-posts' ); ?>
									</button>
									<p class="description">
										<?php esc_html_e( 'Use this if your cron URL has been compromised.', 'ai-blog-posts' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</details>
				</div>

				<div class="settings-section">
					<h2><?php esc_html_e( 'Trending Topics', 'ai-blog-posts' ); ?></h2>
					
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="trending_enabled"><?php esc_html_e( 'Enable Trending Topics', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<label class="switch">
									<input type="checkbox" id="trending_enabled" name="trending_enabled" value="1" <?php checked( $settings['trending_enabled'] ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Automatically fetch and add trending topics to your queue.', 'ai-blog-posts' ); ?></p>
							</td>
						</tr>
						<tr class="trending-settings" style="<?php echo $settings['trending_enabled'] ? '' : 'display:none;'; ?>">
							<th scope="row">
								<label for="trending_country"><?php esc_html_e( 'Country/Region', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<select id="trending_country" name="trending_country">
									<option value="US" <?php selected( $settings['trending_country'], 'US' ); ?>>United States</option>
									<option value="GB" <?php selected( $settings['trending_country'], 'GB' ); ?>>United Kingdom</option>
									<option value="CA" <?php selected( $settings['trending_country'], 'CA' ); ?>>Canada</option>
									<option value="AU" <?php selected( $settings['trending_country'], 'AU' ); ?>>Australia</option>
									<option value="DE" <?php selected( $settings['trending_country'], 'DE' ); ?>>Germany</option>
									<option value="FR" <?php selected( $settings['trending_country'], 'FR' ); ?>>France</option>
									<option value="IN" <?php selected( $settings['trending_country'], 'IN' ); ?>>India</option>
								</select>
							</td>
						</tr>
					</table>
				</div>

				<div class="settings-section">
					<h2><?php esc_html_e( 'Budget Control', 'ai-blog-posts' ); ?></h2>
					
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="budget_limit"><?php esc_html_e( 'Monthly Budget Limit', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<div class="budget-input">
									<span class="currency">$</span>
									<input type="number" id="budget_limit" name="budget_limit" 
										   value="<?php echo esc_attr( $settings['budget_limit'] ); ?>" 
										   min="0" step="1" placeholder="0">
								</div>
								<p class="description"><?php esc_html_e( 'Set to 0 for unlimited. Auto-posting pauses when limit is reached.', 'ai-blog-posts' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="budget_alert_email"><?php esc_html_e( 'Alert Email', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<input type="email" id="budget_alert_email" name="budget_alert_email" 
									   class="regular-text"
									   value="<?php echo esc_attr( $settings['budget_alert_email'] ); ?>" 
									   placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
								<p class="description"><?php esc_html_e( 'Receive alerts when approaching budget limit.', 'ai-blog-posts' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- SEO Tab -->
			<div class="settings-tab <?php echo 'seo' === $current_tab ? 'active' : ''; ?>" data-tab="seo">
				<div class="settings-section">
					<h2><?php esc_html_e( 'SEO Integration', 'ai-blog-posts' ); ?></h2>
					
					<div class="seo-plugin-status">
						<?php if ( $yoast_active ) : ?>
							<div class="plugin-detected success">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Yoast SEO detected', 'ai-blog-posts' ); ?>
							</div>
						<?php endif; ?>
						<?php if ( $rankmath_active ) : ?>
							<div class="plugin-detected success">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'RankMath SEO detected', 'ai-blog-posts' ); ?>
							</div>
						<?php endif; ?>
						<?php if ( ! $yoast_active && ! $rankmath_active ) : ?>
							<div class="plugin-detected warning">
								<span class="dashicons dashicons-warning"></span>
								<?php esc_html_e( 'No supported SEO plugin detected. Basic SEO meta will still be generated.', 'ai-blog-posts' ); ?>
							</div>
						<?php endif; ?>
					</div>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="seo_enabled"><?php esc_html_e( 'Enable SEO Optimization', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<label class="switch">
									<input type="checkbox" id="seo_enabled" name="seo_enabled" value="1" <?php checked( $settings['seo_enabled'] ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Generate SEO meta descriptions and focus keywords for each post.', 'ai-blog-posts' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="settings-section">
					<h2><?php esc_html_e( 'SEO Features', 'ai-blog-posts' ); ?></h2>
					<ul class="seo-features-list">
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Auto-generate meta descriptions', 'ai-blog-posts' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Suggest focus keywords', 'ai-blog-posts' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Optimize heading structure (H1, H2, H3)', 'ai-blog-posts' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Natural keyword placement', 'ai-blog-posts' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Readable paragraph length', 'ai-blog-posts' ); ?></li>
						<?php if ( $yoast_active || $rankmath_active ) : ?>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Direct integration with your SEO plugin', 'ai-blog-posts' ); ?></li>
						<?php endif; ?>
					</ul>
				</div>
			</div>

			<div class="settings-footer">
				<button type="submit" id="save-settings" class="button button-primary button-large">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Save Settings', 'ai-blog-posts' ); ?>
				</button>
				<span class="save-status"></span>
			</div>
		</form>
	</div>
</div>


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
$image_models = Ai_Blog_Posts_Settings::get_image_models();
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
										   value="<?php echo esc_attr( Ai_Blog_Posts_Settings::is_configured() ? '••••••••••••••••••••' : '' ); ?>"
										   autocomplete="off">
									<button type="button" id="toggle-api-key" class="button">
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
										<span class="status-warning"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'API key set but not verified.', 'ai-blog-posts' ); ?></span>
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
											<?php echo esc_html( $model_info['name'] ); ?> - <?php echo esc_html( $model_info['description'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
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
					<h2><?php esc_html_e( 'Image Generation (DALL-E)', 'ai-blog-posts' ); ?></h2>
					
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="image_enabled"><?php esc_html_e( 'Enable Featured Images', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<label class="switch">
									<input type="checkbox" id="image_enabled" name="image_enabled" value="1" <?php checked( $settings['image_enabled'] ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Automatically generate featured images using DALL-E.', 'ai-blog-posts' ); ?></p>
							</td>
						</tr>
						<tr class="image-settings" style="<?php echo $settings['image_enabled'] ? '' : 'display:none;'; ?>">
							<th scope="row">
								<label for="image_model"><?php esc_html_e( 'Image Model', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<select id="image_model" name="image_model">
									<?php foreach ( $image_models as $model_id => $model_info ) : ?>
										<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $settings['image_model'], $model_id ); ?>>
											<?php echo esc_html( $model_info['name'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr class="image-settings" style="<?php echo $settings['image_enabled'] ? '' : 'display:none;'; ?>">
							<th scope="row">
								<label for="image_size"><?php esc_html_e( 'Image Size', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<select id="image_size" name="image_size">
									<option value="1024x1024" <?php selected( $settings['image_size'], '1024x1024' ); ?>>1024×1024 (Square) - $0.04</option>
									<option value="1792x1024" <?php selected( $settings['image_size'], '1792x1024' ); ?>>1792×1024 (Landscape) - $0.08</option>
									<option value="1024x1792" <?php selected( $settings['image_size'], '1024x1792' ); ?>>1024×1792 (Portrait) - $0.08</option>
								</select>
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
					
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Content Analysis', 'ai-blog-posts' ); ?></th>
							<td>
								<button type="button" id="analyze-website" class="button button-secondary">
									<span class="dashicons dashicons-search"></span>
									<?php esc_html_e( 'Analyze Website', 'ai-blog-posts' ); ?>
								</button>
								<span id="analysis-status">
									<?php if ( ! empty( $settings['last_analysis'] ) ) : ?>
										<span class="status-success">
											<?php 
											printf(
												/* translators: %s: date of last analysis */
												esc_html__( 'Last analyzed: %s', 'ai-blog-posts' ),
												esc_html( date_i18n( get_option( 'date_format' ), strtotime( $settings['last_analysis'] ) ) )
											);
											?>
										</span>
									<?php endif; ?>
								</span>
								<div id="analysis-result" class="analysis-result" style="display:none;"></div>
							</td>
						</tr>
					</table>
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
						<tr class="schedule-settings" style="<?php echo $settings['schedule_enabled'] ? '' : 'display:none;'; ?>">
							<th scope="row">
								<label for="schedule_time"><?php esc_html_e( 'Preferred Time', 'ai-blog-posts' ); ?></label>
							</th>
							<td>
								<input type="time" id="schedule_time" name="schedule_time" 
									   value="<?php echo esc_attr( $settings['schedule_time'] ); ?>">
								<p class="description"><?php esc_html_e( 'The time to run scheduled posts (server timezone).', 'ai-blog-posts' ); ?></p>
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


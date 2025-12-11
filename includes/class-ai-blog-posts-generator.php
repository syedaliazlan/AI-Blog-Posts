<?php

/**
 * Content generation engine
 *
 * @link       https://devonicweb.co.uk/
 * @since      1.0.0
 *
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 */

/**
 * Handles AI-powered blog post generation.
 *
 * Multi-step generation process with humanization and SEO optimization.
 *
 * @since      1.0.0
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 * @author     Ali Azlan <contact@devonicweb.co.uk>
 */
class Ai_Blog_Posts_Generator {

	/**
	 * OpenAI API instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Ai_Blog_Posts_OpenAI
	 */
	private $openai;

	/**
	 * Website analyzer instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Ai_Blog_Posts_Analyzer
	 */
	private $analyzer;

	/**
	 * Cost tracker instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Ai_Blog_Posts_Cost_Tracker
	 */
	private $cost_tracker;

	/**
	 * SEO handler instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Ai_Blog_Posts_SEO
	 */
	private $seo;

	/**
	 * Total tokens used in this generation.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array
	 */
	private $token_usage = array(
		'prompt_tokens'     => 0,
		'completion_tokens' => 0,
		'total_tokens'      => 0,
		'cost_usd'          => 0,
	);

	/**
	 * Initialize the class.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->openai = new Ai_Blog_Posts_OpenAI();
		$this->analyzer = new Ai_Blog_Posts_Analyzer();
		$this->cost_tracker = new Ai_Blog_Posts_Cost_Tracker();
		$this->seo = new Ai_Blog_Posts_SEO();
	}

	/**
	 * Create a new generation job for step-by-step processing.
	 *
	 * @since    1.0.0
	 * @param    string $topic      The topic to write about.
	 * @param    array  $options    Generation options.
	 * @return   string|WP_Error    Job ID or error.
	 */
	public function create_job( $topic, $options = array() ) {
		// Check if API is configured
		if ( ! Ai_Blog_Posts_Settings::is_configured() ) {
			return new WP_Error( 
				'api_not_configured', 
				__( 'OpenAI API key is not configured. Please add your API key in Settings.', 'ai-blog-posts' ) 
			);
		}

		// Parse options
		$defaults = array(
			'keywords'       => '',
			'category_id'    => 0,
			'publish'        => false,
			'source'         => 'manual',
			'instructions'   => '',
			'model'          => Ai_Blog_Posts_Settings::get( 'model' ),
			'generate_image' => Ai_Blog_Posts_Settings::get( 'image_enabled' ),
			'queue_topic_id' => 0,
		);
		$options = wp_parse_args( $options, $defaults );

		// Create unique job ID
		$job_id = 'aibp_' . wp_generate_uuid4();

		// Initialize job state
		$job_state = array(
			'job_id'         => $job_id,
			'topic'          => $topic,
			'options'        => $options,
			'status'         => 'pending',
			'current_step'   => 'outline',
			'steps_completed'=> array(),
			'start_time'     => microtime( true ),
			'token_usage'    => array(
				'prompt_tokens'     => 0,
				'completion_tokens' => 0,
				'total_tokens'      => 0,
				'cost_usd'          => 0,
			),
			'data'           => array(),  // Stores outline, content, etc.
			'error'          => null,
			'post_id'        => null,
		);

		// Store job state (1 hour expiry)
		set_transient( $job_id, $job_state, HOUR_IN_SECONDS );

		return $job_id;
	}

	/**
	 * Get job state.
	 *
	 * @since    1.0.0
	 * @param    string $job_id    Job ID.
	 * @return   array|false       Job state or false if not found.
	 */
	public function get_job( $job_id ) {
		return get_transient( $job_id );
	}

	/**
	 * Update job state.
	 *
	 * @since    1.0.0
	 * @param    string $job_id    Job ID.
	 * @param    array  $updates   Updates to merge into job state.
	 * @return   bool              Success.
	 */
	private function update_job( $job_id, $updates ) {
		$job = $this->get_job( $job_id );
		if ( ! $job ) {
			return false;
		}
		$job = array_merge( $job, $updates );
		return set_transient( $job_id, $job, HOUR_IN_SECONDS );
	}

	/**
	 * Process a single step of the generation.
	 *
	 * @since    1.0.0
	 * @param    string $job_id    Job ID.
	 * @param    string $step      Step to process (outline, content, humanize, seo, image, finalize).
	 * @return   array|WP_Error    Result with next_step or error.
	 */
	public function process_step( $job_id, $step ) {
		$job = $this->get_job( $job_id );
		
		if ( ! $job ) {
			return new WP_Error( 'job_not_found', __( 'Generation job not found or expired.', 'ai-blog-posts' ) );
		}

		// Update status
		$this->update_job( $job_id, array( 
			'status' => 'processing',
			'current_step' => $step,
		) );

		// Initialize token tracking for this step
		$this->token_usage = $job['token_usage'];

		$result = null;
		$next_step = null;
		$data_key = null;

		try {
			switch ( $step ) {
				case 'outline':
					$result = $this->generate_outline( $job['topic'], $job['options'] );
					$data_key = 'outline';
					$next_step = 'content';
					break;

				case 'content':
					if ( empty( $job['data']['outline'] ) ) {
						return new WP_Error( 'missing_outline', __( 'Outline not generated yet.', 'ai-blog-posts' ) );
					}
					$result = $this->generate_content( $job['topic'], $job['data']['outline'], $job['options'] );
					$data_key = 'content';
					$next_step = 'humanize';
					break;

				case 'humanize':
					if ( empty( $job['data']['content'] ) ) {
						return new WP_Error( 'missing_content', __( 'Content not generated yet.', 'ai-blog-posts' ) );
					}
					$result = $this->humanize_content( $job['data']['content'], $job['options'] );
					// If humanization fails, use original content
					if ( is_wp_error( $result ) ) {
						$result = $job['data']['content'];
					}
					$data_key = 'humanized';
					$next_step = Ai_Blog_Posts_Settings::get( 'seo_enabled' ) ? 'seo' : 'finalize';
					break;

				case 'seo':
					$content = $job['data']['humanized'] ?? $job['data']['content'];
					if ( empty( $content ) ) {
						return new WP_Error( 'missing_content', __( 'Content not generated yet.', 'ai-blog-posts' ) );
					}
					$result = $this->generate_seo_meta( $job['topic'], $content, $job['options'] );
					// SEO is optional, don't fail if it errors
					if ( is_wp_error( $result ) ) {
						$result = array();
					}
					$data_key = 'seo_data';
					$next_step = 'finalize';
					break;

				case 'finalize':
					return $this->finalize_job( $job_id );

				case 'image':
					// Image is processed after post creation
					if ( empty( $job['post_id'] ) ) {
						return new WP_Error( 'missing_post', __( 'Post not created yet.', 'ai-blog-posts' ) );
					}
					$content = $job['data']['humanized'] ?? $job['data']['content'];
					$title = $this->extract_title( $job['topic'], $job['data']['outline'] );
					$result = $this->generate_featured_image( $job['post_id'], $job['topic'], $title );
					$data_key = 'image_result';
					$next_step = 'complete';
					break;

				default:
					return new WP_Error( 'invalid_step', __( 'Invalid generation step.', 'ai-blog-posts' ) );
			}
		} catch ( Exception $e ) {
			$this->update_job( $job_id, array( 
				'status' => 'error',
				'error' => $e->getMessage(),
			) );
			return new WP_Error( 'generation_error', $e->getMessage() );
		}

		if ( is_wp_error( $result ) ) {
			$this->update_job( $job_id, array( 
				'status' => 'error',
				'error' => $result->get_error_message(),
			) );
			return $result;
		}

		// Update job with results
		$job = $this->get_job( $job_id );
		if ( ! $job ) {
			return new WP_Error( 'job_expired', __( 'Generation job expired during processing. Please try again.', 'ai-blog-posts' ) );
		}
		$job['data'][ $data_key ] = $result;
		$job['steps_completed'][] = $step;
		$job['token_usage'] = $this->token_usage;
		$job['current_step'] = $next_step;
		$job['status'] = 'in_progress';
		set_transient( $job_id, $job, HOUR_IN_SECONDS );

		return array(
			'success'    => true,
			'step'       => $step,
			'next_step'  => $next_step,
			'job_status' => 'in_progress',
		);
	}

	/**
	 * Finalize the job - create the post and set metadata.
	 *
	 * @since    1.0.0
	 * @param    string $job_id    Job ID.
	 * @return   array|WP_Error    Final result or error.
	 */
	private function finalize_job( $job_id ) {
		$job = $this->get_job( $job_id );
		
		if ( ! $job ) {
			return new WP_Error( 'job_not_found', __( 'Generation job not found.', 'ai-blog-posts' ) );
		}

		$content = $job['data']['humanized'] ?? $job['data']['content'];
		if ( empty( $content ) ) {
			return new WP_Error( 'missing_content', __( 'No content to publish.', 'ai-blog-posts' ) );
		}

		// Convert to Gutenberg blocks
		$gutenberg_content = $this->convert_to_gutenberg( $content );
		$outline = $job['data']['outline'] ?? '';
		$title = $this->extract_title( $job['topic'], $outline );

		// Create the post
		$post_data = array(
			'post_title'   => $title,
			'post_content' => $gutenberg_content,
			'post_status'  => $job['options']['publish'] ? 'publish' : Ai_Blog_Posts_Settings::get( 'post_status' ),
			'post_author'  => Ai_Blog_Posts_Settings::get( 'default_author' ),
			'post_type'    => 'post',
		);

		// Add category
		if ( $job['options']['category_id'] ) {
			$post_data['post_category'] = array( $job['options']['category_id'] );
		} elseif ( ! empty( Ai_Blog_Posts_Settings::get( 'categories' ) ) ) {
			$post_data['post_category'] = Ai_Blog_Posts_Settings::get( 'categories' );
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			$this->update_job( $job_id, array( 
				'status' => 'error',
				'error' => $post_id->get_error_message(),
			) );
			return $post_id;
		}

		// Set SEO meta
		$seo_data = $job['data']['seo_data'] ?? array();
		if ( ! empty( $seo_data ) ) {
			$this->seo->set_post_meta( $post_id, $seo_data );
		}

		// Generate and set tags
		$tags = $this->generate_tags( $job['topic'], $job['options']['keywords'], $content );
		if ( ! empty( $tags ) ) {
			wp_set_post_tags( $post_id, $tags, false );
		}

		// Add post meta
		update_post_meta( $post_id, '_ai_blog_posts_generated', true );
		update_post_meta( $post_id, '_ai_blog_posts_topic', $job['topic'] );
		update_post_meta( $post_id, '_ai_blog_posts_model', $job['options']['model'] );
		update_post_meta( $post_id, '_ai_blog_posts_tokens', $job['token_usage']['total_tokens'] );
		update_post_meta( $post_id, '_ai_blog_posts_cost', $job['token_usage']['cost_usd'] );

		// Update job with post ID
		$job['post_id'] = $post_id;
		$job['steps_completed'][] = 'finalize';
		
		// Determine next step
		$next_step = $job['options']['generate_image'] ? 'image' : 'complete';
		$job['current_step'] = $next_step;
		$job['status'] = $next_step === 'complete' ? 'completed' : 'in_progress';
		
		set_transient( $job_id, $job, HOUR_IN_SECONDS );

		$generation_time = microtime( true ) - $job['start_time'];

		// If no image needed, log and return final result
		if ( $next_step === 'complete' ) {
			$this->log_job_completion( $job, $generation_time );

			// Update queue topic if applicable
			if ( $job['options']['queue_topic_id'] ) {
				$this->update_queue_topic( $job['options']['queue_topic_id'], $post_id );
			}
		}

		return array(
			'success'         => true,
			'step'            => 'finalize',
			'next_step'       => $next_step,
			'job_status'      => $job['status'],
			'post_id'         => $post_id,
			'title'           => $title,
			'edit_url'        => get_edit_post_link( $post_id, 'raw' ),
			'view_url'        => get_permalink( $post_id ),
			'model'           => $job['options']['model'],
			'tokens'          => $job['token_usage']['total_tokens'],
			'cost_usd'        => $job['token_usage']['cost_usd'],
			'generation_time' => round( $generation_time, 2 ),
			'content_preview' => wp_trim_words( wp_strip_all_tags( $content ), 100 ),
		);
	}

	/**
	 * Complete image step and finalize everything.
	 *
	 * @since    1.0.0
	 * @param    string $job_id    Job ID.
	 * @return   array|WP_Error    Final result.
	 */
	public function complete_with_image( $job_id ) {
		$job = $this->get_job( $job_id );
		
		if ( ! $job || empty( $job['post_id'] ) ) {
			return new WP_Error( 'job_not_found', __( 'Generation job not found.', 'ai-blog-posts' ) );
		}

		$generation_time = microtime( true ) - $job['start_time'];
		$image_cost = 0;

		// Get image cost if generated
		if ( isset( $job['data']['image_result'] ) && ! is_wp_error( $job['data']['image_result'] ) ) {
			$image_cost = $job['data']['image_result']['cost_usd'] ?? 0;
		}

		// Update post meta with image cost
		$total_cost = $job['token_usage']['cost_usd'] + $image_cost;
		update_post_meta( $job['post_id'], '_ai_blog_posts_cost', $total_cost );

		// Log completion
		$job['token_usage']['image_cost_usd'] = $image_cost;
		$this->log_job_completion( $job, $generation_time );

		// Update queue topic if applicable
		if ( $job['options']['queue_topic_id'] ) {
			$this->update_queue_topic( $job['options']['queue_topic_id'], $job['post_id'] );
		}

		// Mark job complete
		$job['status'] = 'completed';
		$job['steps_completed'][] = 'image';
		$job['current_step'] = 'complete';
		set_transient( $job_id, $job, HOUR_IN_SECONDS );

		$content = $job['data']['humanized'] ?? $job['data']['content'] ?? '';
		$outline = $job['data']['outline'] ?? '';
		$title = $this->extract_title( $job['topic'], $outline );

		return array(
			'success'         => true,
			'step'            => 'complete',
			'next_step'       => null,
			'job_status'      => 'completed',
			'post_id'         => $job['post_id'],
			'title'           => $title,
			'edit_url'        => get_edit_post_link( $job['post_id'], 'raw' ),
			'view_url'        => get_permalink( $job['post_id'] ),
			'model'           => $job['options']['model'],
			'tokens'          => $job['token_usage']['total_tokens'],
			'cost_usd'        => $total_cost,
			'generation_time' => round( $generation_time, 2 ),
			'content_preview' => wp_trim_words( wp_strip_all_tags( $content ), 100 ),
		);
	}

	/**
	 * Log job completion to cost tracker.
	 *
	 * @since    1.0.0
	 * @param    array $job              Job state.
	 * @param    float $generation_time  Total generation time.
	 */
	private function log_job_completion( $job, $generation_time ) {
		$image_cost = $job['token_usage']['image_cost_usd'] ?? 0;
		
		$this->cost_tracker->log( array(
			'post_id'           => $job['post_id'],
			'model_used'        => $job['options']['model'],
			'prompt_tokens'     => $job['token_usage']['prompt_tokens'],
			'completion_tokens' => $job['token_usage']['completion_tokens'],
			'total_tokens'      => $job['token_usage']['total_tokens'],
			'cost_usd'          => $job['token_usage']['cost_usd'],
			'image_cost_usd'    => $image_cost,
			'generation_time'   => $generation_time,
			'topic_source'      => $job['options']['source'],
			'status'            => 'success',
		) );
	}

	/**
	 * Update queue topic status after generation.
	 *
	 * @since    1.0.0
	 * @param    int $topic_id    Topic ID.
	 * @param    int $post_id     Created post ID.
	 */
	private function update_queue_topic( $topic_id, $post_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ai_blog_posts_topics';
		$wpdb->update(
			$table,
			array(
				'status'       => 'completed',
				'post_id'      => $post_id,
				'processed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $topic_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Generate a complete blog post.
	 *
	 * @since    1.0.0
	 * @param    string $topic      The topic to write about.
	 * @param    array  $options    Generation options.
	 * @return   array|WP_Error     Result array or error.
	 */
	public function generate_post( $topic, $options = array() ) {
		$start_time = microtime( true );

		// Check if API is configured
		if ( ! Ai_Blog_Posts_Settings::is_configured() ) {
			return new WP_Error( 
				'api_not_configured', 
				__( 'OpenAI API key is not configured. Please add your API key in Settings.', 'ai-blog-posts' ) 
			);
		}

		// Verify API key is working
		$api_key = Ai_Blog_Posts_Settings::get( 'api_key' );
		if ( empty( $api_key ) ) {
			return new WP_Error( 
				'api_key_empty', 
				__( 'API key could not be retrieved. Please re-enter your API key in Settings.', 'ai-blog-posts' ) 
			);
		}

		// Reset token tracking
		$this->token_usage = array(
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
			'total_tokens'      => 0,
			'cost_usd'          => 0,
		);

		// Parse options
		$defaults = array(
			'keywords'      => '',
			'category_id'   => 0,
			'publish'       => false,
			'source'        => 'manual',
			'instructions'  => '',
			'model'         => Ai_Blog_Posts_Settings::get( 'model' ),
			'generate_image'=> Ai_Blog_Posts_Settings::get( 'image_enabled' ),
		);
		$options = wp_parse_args( $options, $defaults );

		// Check if we're within limits
		if ( 'scheduled' === $options['source'] ) {
			if ( ! $this->cost_tracker->can_generate_today() ) {
				return new WP_Error( 'daily_limit', __( 'Daily post limit reached.', 'ai-blog-posts' ) );
			}
			if ( ! $this->cost_tracker->within_budget() ) {
				return new WP_Error( 'budget_exceeded', __( 'Monthly budget limit exceeded.', 'ai-blog-posts' ) );
			}
		}

		// Step 1: Generate outline
		$outline = $this->generate_outline( $topic, $options );
		if ( is_wp_error( $outline ) ) {
			$this->log_failure( $topic, $outline, $options, $start_time );
			return $outline;
		}

		// Step 2: Generate content
		$content = $this->generate_content( $topic, $outline, $options );
		if ( is_wp_error( $content ) ) {
			$this->log_failure( $topic, $content, $options, $start_time );
			return $content;
		}

		// Step 3: Humanize content
		$humanized = $this->humanize_content( $content, $options );
		if ( is_wp_error( $humanized ) ) {
			// Use original content if humanization fails
			$humanized = $content;
		}

		// Step 4: Generate SEO meta
		$seo_data = array();
		if ( Ai_Blog_Posts_Settings::get( 'seo_enabled' ) ) {
			$seo_data = $this->generate_seo_meta( $topic, $humanized, $options );
		}

		// Step 5: Convert to Gutenberg blocks
		$gutenberg_content = $this->convert_to_gutenberg( $humanized );

		// Step 6: Create the post
		$post_data = array(
			'post_title'   => $this->extract_title( $topic, $outline ),
			'post_content' => $gutenberg_content,
			'post_status'  => $options['publish'] ? 'publish' : Ai_Blog_Posts_Settings::get( 'post_status' ),
			'post_author'  => Ai_Blog_Posts_Settings::get( 'default_author' ),
			'post_type'    => 'post',
		);

		// Add category
		if ( $options['category_id'] ) {
			$post_data['post_category'] = array( $options['category_id'] );
		} elseif ( ! empty( Ai_Blog_Posts_Settings::get( 'categories' ) ) ) {
			$post_data['post_category'] = Ai_Blog_Posts_Settings::get( 'categories' );
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			$this->log_failure( $topic, $post_id, $options, $start_time );
			return $post_id;
		}

		// Step 7: Set SEO meta
		if ( ! empty( $seo_data ) ) {
			$this->seo->set_post_meta( $post_id, $seo_data );
		}

		// Step 8: Generate featured image (with error isolation)
		$image_cost = 0;
		if ( $options['generate_image'] ) {
			try {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'AI Blog Posts: Starting image generation for post %d', $post_id ) );
				}
				
				$image_result = $this->generate_featured_image( $post_id, $topic, $post_data['post_title'] );
				
				if ( is_wp_error( $image_result ) ) {
					// Log error but don't fail the whole generation
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( sprintf( 'AI Blog Posts: Image generation failed: %s', $image_result->get_error_message() ) );
					}
				} else {
					$image_cost = $image_result['cost_usd'] ?? 0;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( sprintf( 'AI Blog Posts: Image generation completed for post %d', $post_id ) );
					}
				}
			} catch ( Exception $e ) {
				// Catch any unexpected errors - don't let image failure stop post creation
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'AI Blog Posts: Image generation exception: %s', $e->getMessage() ) );
				}
			}
		}

		// Step 9: Generate and set tags
		$tags = $this->generate_tags( $topic, $options['keywords'], $humanized );
		if ( ! empty( $tags ) ) {
			wp_set_post_tags( $post_id, $tags, false );
		}

		// Add post meta
		update_post_meta( $post_id, '_ai_blog_posts_generated', true );
		update_post_meta( $post_id, '_ai_blog_posts_topic', $topic );
		update_post_meta( $post_id, '_ai_blog_posts_model', $options['model'] );
		update_post_meta( $post_id, '_ai_blog_posts_tokens', $this->token_usage['total_tokens'] );
		update_post_meta( $post_id, '_ai_blog_posts_cost', $this->token_usage['cost_usd'] + $image_cost );

		$generation_time = microtime( true ) - $start_time;

		// Log success
		$log_id = $this->cost_tracker->log( array(
			'post_id'           => $post_id,
			'model_used'        => $options['model'],
			'prompt_tokens'     => $this->token_usage['prompt_tokens'],
			'completion_tokens' => $this->token_usage['completion_tokens'],
			'total_tokens'      => $this->token_usage['total_tokens'],
			'cost_usd'          => $this->token_usage['cost_usd'],
			'image_cost_usd'    => $image_cost,
			'generation_time'   => $generation_time,
			'topic_source'      => $options['source'],
			'status'            => 'success',
		) );

		return array(
			'success'           => true,
			'post_id'           => $post_id,
			'title'             => $post_data['post_title'],
			'edit_url'          => get_edit_post_link( $post_id, 'raw' ),
			'view_url'          => get_permalink( $post_id ),
			'model'             => $options['model'],
			'tokens'            => $this->token_usage['total_tokens'],
			'cost_usd'          => $this->token_usage['cost_usd'] + $image_cost,
			'generation_time'   => round( $generation_time, 2 ),
			'content_preview'   => wp_trim_words( wp_strip_all_tags( $humanized ), 100 ),
		);
	}

	/**
	 * Generate an outline for the post.
	 *
	 * @since    1.0.0
	 * @param    string $topic      The topic.
	 * @param    array  $options    Options.
	 * @return   string|WP_Error    Outline or error.
	 */
	private function generate_outline( $topic, $options ) {
		$system_prompt = $this->get_system_prompt( 'outline' );
		
		$prompt = sprintf(
			"Create a detailed outline for a blog post about: %s\n\n" .
			"Include:\n" .
			"- A compelling title suggestion\n" .
			"- 4-6 main sections with H2 headings\n" .
			"- 2-3 key points under each section\n" .
			"- A brief intro and conclusion plan\n\n" .
			"Target word count: %d-%d words\n",
			$topic,
			Ai_Blog_Posts_Settings::get( 'word_count_min' ),
			Ai_Blog_Posts_Settings::get( 'word_count_max' )
		);

		if ( $options['keywords'] ) {
			$prompt .= sprintf( "\nFocus keywords to include: %s", $options['keywords'] );
		}

		if ( $options['instructions'] ) {
			$prompt .= sprintf( "\n\nAdditional instructions: %s", $options['instructions'] );
		}

		// Log model being used for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: Generating outline using model: %s', $options['model'] ) );
		}

		$result = $this->openai->generate_text( $prompt, $system_prompt, array(
			'model'       => $options['model'],
			'max_tokens'  => 1000,
			'temperature' => 0.7,
		) );

		if ( is_wp_error( $result ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'AI Blog Posts: Outline generation error: %s', $result->get_error_message() ) );
			}
			return $result;
		}

		$this->track_tokens( $result );

		// Validate we got actual content
		$content = $result['content'] ?? '';
		if ( empty( trim( $content ) ) ) {
			// Log debug info for empty response
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 
					'AI Blog Posts: Empty outline response. Model: %s, Tokens: %d/%d, Finish reason: %s', 
					$result['model'] ?? 'unknown',
					$result['prompt_tokens'] ?? 0,
					$result['completion_tokens'] ?? 0,
					$result['finish_reason'] ?? 'unknown'
				) );
			}
			return new WP_Error( 
				'empty_outline', 
				sprintf(
					/* translators: %s: model name */
					__( 'The AI returned an empty outline using model "%s". This can happen if: 1) The model does not exist, 2) Your API key lacks permissions, or 3) You have no API credits. Please check your Settings page.', 'ai-blog-posts' ),
					$options['model']
				)
			);
		}

		return $content;
	}

	/**
	 * Generate the main content.
	 *
	 * @since    1.0.0
	 * @param    string $topic      The topic.
	 * @param    string $outline    The outline.
	 * @param    array  $options    Options.
	 * @return   string|WP_Error    Content or error.
	 */
	private function generate_content( $topic, $outline, $options ) {
		$system_prompt = $this->get_system_prompt( 'content' );
		
		// Get website style prompt if available
		$style_prompt = $this->analyzer->get_style_prompt();
		if ( $style_prompt ) {
			$system_prompt .= "\n\nWebsite Writing Style:\n" . $style_prompt;
		}

		$word_target = floor( ( Ai_Blog_Posts_Settings::get( 'word_count_min' ) + Ai_Blog_Posts_Settings::get( 'word_count_max' ) ) / 2 );

		$prompt = sprintf(
			"Write a complete blog post based on this outline:\n\n%s\n\n" .
			"Requirements:\n" .
			"- Write approximately %d words\n" .
			"- Use proper H2 and H3 heading hierarchy\n" .
			"- Write engaging, informative content\n" .
			"- Include a compelling introduction that hooks the reader\n" .
			"- End with a strong conclusion or call-to-action\n" .
			"- Use short paragraphs (2-4 sentences each)\n" .
			"- Include bullet points or numbered lists where appropriate\n" .
			"- Make it SEO-friendly but natural\n\n" .
			"Format: Use HTML with <h2>, <h3>, <p>, <ul>, <li> tags. Do NOT include <h1> as WordPress adds the title automatically.",
			$outline,
			$word_target
		);

		if ( $options['keywords'] ) {
			$prompt .= sprintf( "\n\nNaturally incorporate these keywords: %s", $options['keywords'] );
		}

		// Calculate max tokens based on word count
		$max_tokens = min( 4000, $word_target * 2 );

		$result = $this->openai->generate_text( $prompt, $system_prompt, array(
			'model'       => $options['model'],
			'max_tokens'  => $max_tokens,
			'temperature' => 0.7,
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->track_tokens( $result );

		// Validate we got actual content
		$content = $result['content'] ?? '';
		if ( empty( trim( $content ) ) ) {
			return new WP_Error( 
				'empty_content', 
				__( 'The AI returned empty content. Please check your API key has sufficient credits and try again.', 'ai-blog-posts' ) 
			);
		}

		return $content;
	}

	/**
	 * Humanize the content to reduce AI detection.
	 *
	 * @since    1.0.0
	 * @param    string $content    Original content.
	 * @param    array  $options    Options.
	 * @return   string|WP_Error    Humanized content or error.
	 */
	private function humanize_content( $content, $options ) {
		$level = Ai_Blog_Posts_Settings::get( 'humanize_level' );
		
		// Lower levels skip this step
		if ( $level < 3 ) {
			return $content;
		}

		// Skip if content is too short (likely an error)
		if ( strlen( wp_strip_all_tags( $content ) ) < 200 ) {
			return $content;
		}

		$intensity_map = array(
			3 => 'moderate',
			4 => 'substantial',
			5 => 'extensive',
		);

		$intensity = $intensity_map[ $level ] ?? 'moderate';

		$system_prompt = "You are an expert editor. Your task is to rewrite the provided HTML blog content to sound more natural and human-written. " .
			"You MUST output the complete rewritten content with all HTML tags preserved. Do not ask questions or request clarification - just rewrite the content provided below.";

		$prompt = sprintf(
			"TASK: Rewrite this blog post to sound more natural and human-written.\n\n" .
			"INTENSITY: %s rewriting\n\n" .
			"RULES:\n" .
			"1. Output ONLY the rewritten HTML content - no explanations or questions\n" .
			"2. Vary sentence structures and lengths\n" .
			"3. Add conversational elements where appropriate\n" .
			"4. Use more varied vocabulary\n" .
			"5. Remove robotic or formulaic patterns\n" .
			"6. NEVER use: 'dive into', 'delve into', 'it's important to note', 'in today's world', 'in conclusion', 'firstly/secondly/thirdly', 'game-changer', 'leverage', 'unlock', 'landscape', 'tapestry'\n" .
			"7. PRESERVE all HTML tags (<h2>, <h3>, <p>, <ul>, <li>, etc.)\n" .
			"8. Keep approximately the same length\n\n" .
			"CONTENT TO REWRITE:\n\n%s",
			$intensity,
			$content
		);

		$result = $this->openai->generate_text( $prompt, $system_prompt, array(
			'model'       => $options['model'],
			'max_tokens'  => 4000,
			'temperature' => 0.8,
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->track_tokens( $result );
		
		// Verify the result looks like HTML content
		$result_content = $result['content'];
		if ( strpos( $result_content, '<' ) === false || strlen( $result_content ) < 200 ) {
			// AI didn't return proper content, use original
			return $content;
		}

		return $result_content;
	}

	/**
	 * Generate SEO meta data.
	 *
	 * @since    1.0.0
	 * @param    string $topic      The topic.
	 * @param    string $content    The content.
	 * @param    array  $options    Options.
	 * @return   array              SEO data.
	 */
	private function generate_seo_meta( $topic, $content, $options ) {
		$system_prompt = "You are an SEO specialist. Generate SEO metadata that is compelling and optimized.";

		// Truncate content for efficiency
		$content_sample = wp_trim_words( wp_strip_all_tags( $content ), 300 );

		$prompt = sprintf(
			"Based on this blog post about '%s', generate:\n\n" .
			"1. Meta description (150-160 characters, compelling and includes main keyword)\n" .
			"2. Focus keyword (single phrase, 2-4 words)\n" .
			"3. SEO title (if different from post title, 50-60 characters)\n\n" .
			"Content summary:\n%s\n\n" .
			"Format your response as JSON: {\"meta_description\": \"...\", \"focus_keyword\": \"...\", \"seo_title\": \"...\"}",
			$topic,
			$content_sample
		);

		$result = $this->openai->generate_text( $prompt, $system_prompt, array(
			'model'       => $options['model'],
			'max_tokens'  => 1500, // Increased for GPT-5 reasoning overhead
			'temperature' => 0.5,
		) );

		if ( is_wp_error( $result ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'AI Blog Posts: SEO generation failed: %s', $result->get_error_message() ) );
			}
			return array();
		}

		$this->track_tokens( $result );

		// Parse JSON response
		$json_content = $result['content'] ?? '';
		
		if ( empty( $json_content ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'AI Blog Posts: SEO generation returned empty content' );
			}
			return array();
		}

		// Extract JSON from response - handle nested structures properly
		$start = strpos( $json_content, '{' );
		$end = strrpos( $json_content, '}' );
		
		if ( $start !== false && $end !== false && $end > $start ) {
			$json_content = substr( $json_content, $start, $end - $start + 1 );
		}

		$seo_data = json_decode( $json_content, true );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: SEO data generated: %s', wp_json_encode( $seo_data ) ) );
		}

		return is_array( $seo_data ) ? $seo_data : array();
	}

	/**
	 * Convert content to Gutenberg blocks.
	 *
	 * @since    1.0.0
	 * @param    string $content    HTML content.
	 * @return   string             Gutenberg block content.
	 */
	private function convert_to_gutenberg( $content ) {
		// Clean up the content
		$content = trim( $content );
		
		// Remove any markdown code fences
		$content = preg_replace( '/```html?\s*/', '', $content );
		$content = preg_replace( '/```\s*/', '', $content );

		// Convert paragraphs to Gutenberg blocks
		$content = preg_replace(
			'/<p([^>]*)>(.*?)<\/p>/s',
			"<!-- wp:paragraph -->\n<p$1>$2</p>\n<!-- /wp:paragraph -->\n",
			$content
		);

		// Convert H2 headings
		$content = preg_replace(
			'/<h2([^>]*)>(.*?)<\/h2>/s',
			"<!-- wp:heading -->\n<h2$1>$2</h2>\n<!-- /wp:heading -->\n",
			$content
		);

		// Convert H3 headings
		$content = preg_replace(
			'/<h3([^>]*)>(.*?)<\/h3>/s',
			"<!-- wp:heading {\"level\":3} -->\n<h3$1>$2</h3>\n<!-- /wp:heading -->\n",
			$content
		);

		// Convert H4 headings
		$content = preg_replace(
			'/<h4([^>]*)>(.*?)<\/h4>/s',
			"<!-- wp:heading {\"level\":4} -->\n<h4$1>$2</h4>\n<!-- /wp:heading -->\n",
			$content
		);

		// Convert unordered lists
		$content = preg_replace(
			'/<ul([^>]*)>(.*?)<\/ul>/s',
			"<!-- wp:list -->\n<ul$1>$2</ul>\n<!-- /wp:list -->\n",
			$content
		);

		// Convert ordered lists
		$content = preg_replace(
			'/<ol([^>]*)>(.*?)<\/ol>/s',
			"<!-- wp:list {\"ordered\":true} -->\n<ol$1>$2</ol>\n<!-- /wp:list -->\n",
			$content
		);

		// Convert blockquotes
		$content = preg_replace(
			'/<blockquote([^>]*)>(.*?)<\/blockquote>/s',
			"<!-- wp:quote -->\n<blockquote$1>$2</blockquote>\n<!-- /wp:quote -->\n",
			$content
		);

		// Clean up multiple newlines
		$content = preg_replace( '/\n{3,}/', "\n\n", $content );

		return trim( $content );
	}

	/**
	 * Generate and set featured image.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id    Post ID.
	 * @param    string $topic      Topic.
	 * @param    string $title      Post title.
	 * @return   array|WP_Error     Result or error.
	 */
	private function generate_featured_image( $post_id, $topic, $title ) {
		// Create a visual concept prompt - NO text allowed
		$image_prompt = $this->create_image_prompt( $title, $topic );

		$result = $this->openai->generate_image( $image_prompt, array(
			'model'   => Ai_Blog_Posts_Settings::get( 'image_model' ),
			'size'    => Ai_Blog_Posts_Settings::get( 'image_size' ),
			'style'   => 'natural', // More photorealistic
			'quality' => 'hd',      // Higher quality
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Download and attach the image
		$filename = sanitize_title( $title ) . '-featured';
		$attachment_id = $this->openai->download_image_to_media( $result['url'], $filename, $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Set as featured image
		set_post_thumbnail( $post_id, $attachment_id );

		// Set alt text
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title );

		return array(
			'attachment_id' => $attachment_id,
			'cost_usd'      => $result['cost_usd'],
		);
	}

	/**
	 * Create an optimized image prompt for DALL-E.
	 *
	 * @since    1.0.0
	 * @param    string $title    Post title.
	 * @param    string $topic    Original topic.
	 * @return   string           Optimized prompt.
	 */
	private function create_image_prompt( $title, $topic ) {
		// Extract key concepts from the title for visual representation
		$visual_subject = $this->extract_visual_concept( $title );
		
		$prompt = sprintf(
			"Professional photorealistic image representing: %s. " .
			"CRITICAL REQUIREMENTS: " .
			"1. ABSOLUTELY NO TEXT, words, letters, numbers, labels, captions, watermarks, or any written content in the image. " .
			"2. NO signs, banners, screens, or surfaces with text. " .
			"3. Style: Clean, modern, high-quality stock photo aesthetic. " .
			"4. Lighting: Professional, well-lit, natural lighting. " .
			"5. Composition: Rule of thirds, visually balanced, suitable as a blog header. " .
			"6. Subject: Real objects, environments, or scenes that visually represent the topic. " .
			"7. Quality: Sharp focus, high resolution, professional photography style. " .
			"Generate a visually compelling image that tells the story through imagery alone, not through any text or words.",
			$visual_subject
		);

		return $prompt;
	}

	/**
	 * Extract visual concepts from a title for image generation.
	 *
	 * @since    1.0.0
	 * @param    string $title    Post title.
	 * @return   string           Visual concept description.
	 */
	private function extract_visual_concept( $title ) {
		// Universal topic-to-visual mappings (works for any website globally)
		$visual_mappings = array(
			// Technology
			'technology'   => 'modern technology workspace with laptop and devices',
			'ai'           => 'futuristic abstract digital network visualization',
			'software'     => 'clean modern computer workspace',
			'digital'      => 'modern digital devices showing connectivity',
			'app'          => 'smartphone with abstract colorful interface elements',
			'coding'       => 'developer workspace with code on multiple screens',
			'computer'     => 'sleek modern computer setup',
			'internet'     => 'global connectivity network visualization',
			'cyber'        => 'digital security concept with abstract elements',
			'robot'        => 'modern robotics and automation',
			
			// Business/Finance
			'business'     => 'professional modern office environment',
			'finance'      => 'financial growth charts and professional workspace',
			'investment'   => 'growth concept with ascending visual elements',
			'money'        => 'financial success and prosperity concept',
			'market'       => 'dynamic trading or marketplace environment',
			'economy'      => 'modern cityscape representing commerce',
			'startup'      => 'innovative modern workspace with creative energy',
			'entrepreneur' => 'confident professional in modern setting',
			'career'       => 'professional advancement and success concept',
			'job'          => 'professional workplace environment',
			
			// Health/Wellness
			'health'       => 'healthy lifestyle with fresh food and active living',
			'fitness'      => 'athletic movement and exercise environment',
			'wellness'     => 'peaceful zen environment with natural elements',
			'medical'      => 'clean healthcare environment',
			'mental'       => 'peaceful meditation and mindfulness scene',
			'nutrition'    => 'colorful fresh healthy food arrangement',
			'exercise'     => 'dynamic fitness and movement',
			'yoga'         => 'serene yoga practice in peaceful setting',
			
			// Education
			'university'   => 'impressive academic campus architecture',
			'education'    => 'inspiring learning environment with books',
			'learning'     => 'bright study space with educational materials',
			'school'       => 'modern educational facility',
			'student'      => 'young person engaged in learning',
			'course'       => 'organized educational materials and resources',
			'training'     => 'professional development environment',
			'skill'        => 'hands-on learning and practice',
			
			// Travel/Lifestyle
			'travel'       => 'stunning scenic destination landscape',
			'food'         => 'beautifully styled gourmet cuisine',
			'home'         => 'cozy modern interior living space',
			'fashion'      => 'stylish clothing and accessories arrangement',
			'garden'       => 'lush flourishing garden landscape',
			'cooking'      => 'inviting kitchen with fresh ingredients',
			'restaurant'   => 'elegant dining atmosphere',
			'vacation'     => 'relaxing paradise destination',
			
			// Environment/Nature
			'climate'      => 'dramatic environmental landscape',
			'sustainable'  => 'eco-friendly green living concept',
			'energy'       => 'renewable energy like solar panels or wind turbines',
			'nature'       => 'beautiful natural wilderness landscape',
			'environment'  => 'pristine natural ecosystem',
			'green'        => 'lush vegetation and sustainable living',
			'ocean'        => 'stunning seascape or marine scene',
			'forest'       => 'serene woodland environment',
			
			// Real Estate/Property
			'property'     => 'attractive modern real estate',
			'house'        => 'beautiful residential home exterior',
			'apartment'    => 'stylish modern apartment interior',
			'real estate'  => 'impressive property and architecture',
			'rent'         => 'welcoming living space',
			
			// Relationships/Family
			'family'       => 'warm family togetherness moment',
			'relationship' => 'meaningful human connection',
			'parenting'    => 'nurturing parent-child interaction',
			'wedding'      => 'romantic celebration atmosphere',
			
			// Productivity/Work
			'productivity' => 'organized efficient workspace',
			'tips'         => 'helpful tools and resources arranged neatly',
			'guide'        => 'clear pathway and direction concept',
			'how to'       => 'step-by-step process visualization',
			'best'         => 'excellence and quality concept',
			'top'          => 'achievement and success pinnacle',
		);

		$title_lower = strtolower( $title );
		$visual_elements = array();

		// Find matching visual concepts
		foreach ( $visual_mappings as $keyword => $visual ) {
			if ( strpos( $title_lower, $keyword ) !== false ) {
				$visual_elements[] = $visual;
			}
		}

		// If we found specific mappings, use them
		if ( ! empty( $visual_elements ) ) {
			return implode( ', combined with ', array_slice( $visual_elements, 0, 2 ) );
		}

		// Fallback: use the title itself but emphasize visual representation
		return "the concept of: " . $title . " - shown through relevant objects, environments, and visual metaphors";
	}

	/**
	 * Extract title from outline.
	 *
	 * @since    1.0.0
	 * @param    string $topic      Original topic.
	 * @param    string $outline    Generated outline.
	 * @return   string             Title.
	 */
	private function extract_title( $topic, $outline ) {
		// Words that indicate a title label (not the actual title)
		$title_labels = array( 'title', 'suggested title', 'title suggestion', 'blog title', 'post title', 'article title' );
		
		// Pattern 1: **Title:** Actual Title Here or **Suggested Title:** Actual Title
		// Captures what comes AFTER the colon
		if ( preg_match( '/\*\*(?:suggested\s+)?title(?:\s+suggestion)?[:\s]*\*\*[:\s]*["\'"]?([^"\'\n]+)["\'"]?/i', $outline, $matches ) ) {
			$title = $this->clean_title( $matches[1] );
			if ( $this->is_valid_title( $title, $title_labels ) ) {
				return $title;
			}
		}

		// Pattern 2: Title: "Actual Title Here" (with quotes)
		if ( preg_match( '/(?:suggested\s+)?title(?:\s+suggestion)?[:\s]+["\']([^"\']+)["\']/i', $outline, $matches ) ) {
			$title = $this->clean_title( $matches[1] );
			if ( $this->is_valid_title( $title, $title_labels ) ) {
				return $title;
			}
		}

		// Pattern 3: Title: Actual Title Here (without quotes, same line)
		if ( preg_match( '/^(?:\*\*)?(?:suggested\s+)?title(?:\s+suggestion)?(?:\*\*)?[:\s]+(.+)$/mi', $outline, $matches ) ) {
			$title = $this->clean_title( $matches[1] );
			if ( $this->is_valid_title( $title, $title_labels ) ) {
				return $title;
			}
		}

		// Pattern 4: Title label on one line, actual title on next line (bold or plain)
		if ( preg_match( '/(?:suggested\s+)?title(?:\s+suggestion)?[:\s]*\n+\s*\*?\*?["\'"]?([^\n\*"\']+)["\'"]?\*?\*?/i', $outline, $matches ) ) {
			$title = $this->clean_title( $matches[1] );
			if ( $this->is_valid_title( $title, $title_labels ) ) {
				return $title;
			}
		}

		// Pattern 5: First bold text that looks like a title (not a section heading like "Introduction")
		if ( preg_match_all( '/\*\*([^*\n]+)\*\*/m', $outline, $matches ) ) {
			foreach ( $matches[1] as $match ) {
				$title = $this->clean_title( $match );
				// Skip common section headings and title labels
				$skip_words = array_merge( $title_labels, array( 'introduction', 'conclusion', 'overview', 'summary', 'outline', 'section', 'chapter' ) );
				if ( $this->is_valid_title( $title, $skip_words ) && strlen( $title ) > 15 ) {
					return $title;
				}
			}
		}

		// Pattern 6: First H1 heading that's not a label
		if ( preg_match( '/^#\s+([^#\n]+)/m', $outline, $matches ) ) {
			$title = $this->clean_title( $matches[1] );
			if ( $this->is_valid_title( $title, $title_labels ) ) {
				return $title;
			}
		}

		// Fall back to the topic (properly formatted)
		return ucwords( strtolower( $topic ) );
	}

	/**
	 * Clean up a title string.
	 *
	 * @param    string $title    Raw title.
	 * @return   string           Cleaned title.
	 */
	private function clean_title( $title ) {
		// Remove markdown formatting
		$title = preg_replace( '/^\*\*|\*\*$/', '', $title );
		$title = preg_replace( '/^#+\s*/', '', $title );
		$title = preg_replace( '/^\*|\*$/', '', $title );
		// Remove quotes and extra punctuation
		$title = trim( $title, ':*#"\'\- ' );
		// Remove "Title:" prefix if it somehow got included
		$title = preg_replace( '/^(?:suggested\s+)?title(?:\s+suggestion)?[:\s]+/i', '', $title );
		return trim( $title );
	}

	/**
	 * Check if a title is valid (not just a label).
	 *
	 * @param    string $title        The title to check.
	 * @param    array  $skip_words   Words that indicate this is a label, not a title.
	 * @return   bool                 True if valid.
	 */
	private function is_valid_title( $title, $skip_words ) {
		if ( empty( $title ) || strlen( $title ) < 5 ) {
			return false;
		}
		
		$title_lower = strtolower( trim( $title ) );
		
		// Check if the title is just a label word
		foreach ( $skip_words as $word ) {
			if ( $title_lower === $word || $title_lower === $word . ':' ) {
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Generate tags for the post based on topic and content.
	 *
	 * @since    1.0.0
	 * @param    string $topic      The topic.
	 * @param    string $keywords   Keywords from the topic queue.
	 * @param    string $content    The generated content.
	 * @return   array              Array of tags.
	 */
	private function generate_tags( $topic, $keywords, $content ) {
		$tags = array();

		// Extract tags from provided keywords
		if ( ! empty( $keywords ) ) {
			$keyword_tags = array_map( 'trim', explode( ',', $keywords ) );
			$tags = array_merge( $tags, $keyword_tags );
		}

		// Extract important words from topic
		$topic_words = $this->extract_keywords_from_text( $topic );
		$tags = array_merge( $tags, $topic_words );

		// Extract key phrases from content (looking for bold text and headings)
		if ( preg_match_all( '/<strong>([^<]+)<\/strong>/', $content, $matches ) ) {
			foreach ( array_slice( $matches[1], 0, 5 ) as $match ) {
				$clean = trim( wp_strip_all_tags( $match ) );
				if ( strlen( $clean ) > 2 && strlen( $clean ) < 30 ) {
					$tags[] = $clean;
				}
			}
		}

		// Clean and deduplicate tags
		$tags = array_map( 'sanitize_text_field', $tags );
		$tags = array_map( 'strtolower', $tags );
		$tags = array_unique( $tags );
		$tags = array_filter( $tags, function( $tag ) {
			return strlen( $tag ) > 2 && strlen( $tag ) < 50;
		} );

		// Limit to 10 tags
		return array_slice( array_values( $tags ), 0, 10 );
	}

	/**
	 * Extract keywords from text (for tags).
	 *
	 * @since    1.0.0
	 * @param    string $text    The text to extract from.
	 * @return   array           Array of keywords.
	 */
	private function extract_keywords_from_text( $text ) {
		// Common stop words to exclude
		$stop_words = array(
			'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
			'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been',
			'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
			'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those',
			'it', 'its', 'you', 'your', 'we', 'our', 'they', 'their', 'how', 'what',
			'when', 'where', 'why', 'which', 'who', 'whom',
		);

		// Clean and split text
		$words = preg_split( '/[\s\-_:,;.!?]+/', strtolower( $text ) );
		$words = array_filter( $words, function( $word ) use ( $stop_words ) {
			return strlen( $word ) > 3 && ! in_array( $word, $stop_words, true );
		} );

		return array_unique( $words );
	}

	/**
	 * Get system prompt for different generation stages.
	 *
	 * @since    1.0.0
	 * @param    string $stage    Generation stage.
	 * @return   string           System prompt.
	 */
	private function get_system_prompt( $stage ) {
		$base = "You are an expert blog content writer with years of experience in creating engaging, well-researched articles. ";
		
		$prompts = array(
			'outline' => $base . 
				"You excel at creating comprehensive outlines that lead to well-structured, valuable content. " .
				"Your outlines are detailed enough to guide writing but flexible enough to allow creativity.",
			
			'content' => $base .
				"You write in a natural, engaging style that connects with readers. " .
				"Your content is informative, accurate, and easy to read. " .
				"You always provide value and actionable insights. " .
				"You write content that doesn't sound AI-generated - it's authentic and personable. " .
				"Avoid clichés, filler phrases, and overly formal language. " .
				"IMPORTANT: Never use these phrases: 'dive into', 'delve into', 'let's explore', " .
				"'in today's fast-paced world', 'it's important to note', 'at the end of the day', " .
				"'game-changer', 'leverage', 'synergy', 'unlock the power of'.",
		);

		return $prompts[ $stage ] ?? $base;
	}

	/**
	 * Track token usage.
	 *
	 * @since    1.0.0
	 * @param    array $result    API result.
	 */
	private function track_tokens( $result ) {
		$this->token_usage['prompt_tokens'] += $result['prompt_tokens'] ?? 0;
		$this->token_usage['completion_tokens'] += $result['completion_tokens'] ?? 0;
		$this->token_usage['total_tokens'] += $result['total_tokens'] ?? 0;
		$this->token_usage['cost_usd'] += $result['cost_usd'] ?? 0;
	}

	/**
	 * Log a failed generation attempt.
	 *
	 * @since    1.0.0
	 * @param    string   $topic       Topic.
	 * @param    WP_Error $error       Error object.
	 * @param    array    $options     Options.
	 * @param    float    $start_time  Start timestamp.
	 */
	private function log_failure( $topic, $error, $options, $start_time ) {
		$this->cost_tracker->log( array(
			'post_id'           => null,
			'model_used'        => $options['model'],
			'prompt_tokens'     => $this->token_usage['prompt_tokens'],
			'completion_tokens' => $this->token_usage['completion_tokens'],
			'total_tokens'      => $this->token_usage['total_tokens'],
			'cost_usd'          => $this->token_usage['cost_usd'],
			'image_cost_usd'    => 0,
			'generation_time'   => microtime( true ) - $start_time,
			'topic_source'      => $options['source'],
			'status'            => 'failed',
			'error_message'     => $error->get_error_message(),
		) );
	}
}


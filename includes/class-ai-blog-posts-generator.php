<?php

/**
 * Content generation engine - Simplified version
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
 * Simplified synchronous generation - no step-based processing or complex retry logic.
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
	 * Pexels API instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Ai_Blog_Posts_Pexels
	 */
	private $pexels;

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
	 * Token usage tracking.
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
		$this->pexels = new Ai_Blog_Posts_Pexels();
		$this->cost_tracker = new Ai_Blog_Posts_Cost_Tracker();
		$this->seo = new Ai_Blog_Posts_SEO();
	}

	/**
	 * Start a new generation job (step-by-step mode).
	 *
	 * @since    1.0.0
	 * @param    string $topic      The topic to write about.
	 * @param    array  $options    Generation options.
	 * @return   string             Job ID.
	 */
	public function start_job( $topic, $options = array() ) {
		$job_id = 'aibp_' . wp_generate_uuid4();
		
		// Parse options
		$defaults = array(
			'keywords'       => '',
			'category_id'    => Ai_Blog_Posts_Settings::get( 'category' ) ?: 0,
			'publish'        => Ai_Blog_Posts_Settings::get( 'post_status' ) === 'publish',
			'generate_image' => Ai_Blog_Posts_Settings::get( 'image_enabled' ),
			'model'          => Ai_Blog_Posts_Settings::get( 'model' ),
		);
		$options = wp_parse_args( $options, $defaults );

		// Store job data
		$job_data = array(
			'topic'            => $topic,
			'options'          => $options,
			'status'           => 'pending',
			'step'             => 'outline',
			'progress'         => 0,
			'outline'          => '',
			'content'          => '',
			'post_id'          => 0,
			'error'            => '',
			'created_at'       => time(),
			'prompt_tokens'    => 0,
			'completion_tokens' => 0,
			'cost_usd'         => 0,
		);

		set_transient( $job_id, $job_data, HOUR_IN_SECONDS );
		
		return $job_id;
	}

	/**
	 * Process a generation step.
	 *
	 * @since    1.0.0
	 * @param    string $job_id    Job ID.
	 * @return   array|WP_Error    Step result.
	 */
	public function process_step( $job_id ) {
		@set_time_limit( 120 );

		$job = get_transient( $job_id );
		if ( ! $job ) {
			return new WP_Error( 'job_not_found', __( 'Job not found or expired.', 'ai-blog-posts' ) );
		}

		$job['status'] = 'processing';
		$step = $job['step'];

		try {
			switch ( $step ) {
				case 'outline':
					$result = $this->step_outline( $job );
					break;
				case 'content':
					$result = $this->step_content( $job );
					break;
				case 'post':
					$result = $this->step_create_post( $job );
					break;
				case 'images':
					$result = $this->step_images( $job );
					break;
				case 'seo':
					$result = $this->step_seo( $job );
					break;
				default:
					$result = new WP_Error( 'invalid_step', __( 'Invalid step.', 'ai-blog-posts' ) );
			}

			if ( is_wp_error( $result ) ) {
				$job['status'] = 'failed';
				$job['error'] = $result->get_error_message();
				set_transient( $job_id, $job, HOUR_IN_SECONDS );
				return $result;
			}

			// Update job with result
			$job = array_merge( $job, $result );
			set_transient( $job_id, $job, HOUR_IN_SECONDS );

			return array(
				'status'    => $job['status'],
				'step'      => $job['step'],
				'next_step' => $job['step'] === 'complete' ? null : $job['step'],
				'progress'  => $job['progress'],
				'post_id'   => $job['post_id'],
				'message'   => $this->get_step_message( $job['step'] ),
			);

		} catch ( Exception $e ) {
			$job['status'] = 'failed';
			$job['error'] = $e->getMessage();
			set_transient( $job_id, $job, HOUR_IN_SECONDS );
			return new WP_Error( 'step_failed', $e->getMessage() );
		}
	}

	/**
	 * Get job status.
	 *
	 * @since    1.0.0
	 * @param    string $job_id    Job ID.
	 * @return   array|null        Job data or null.
	 */
	public function get_job( $job_id ) {
		return get_transient( $job_id );
	}

	private function step_outline( $job ) {
		$this->log( 'Step 1: Generating outline...' );
		$result = $this->generate_outline( $job['topic'], $job['options'] );
		
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Track tokens
		$prompt_tokens = ( $job['prompt_tokens'] ?? 0 ) + ( $result['prompt_tokens'] ?? 0 );
		$completion_tokens = ( $job['completion_tokens'] ?? 0 ) + ( $result['completion_tokens'] ?? 0 );
		$cost_usd = ( $job['cost_usd'] ?? 0 ) + ( $result['cost_usd'] ?? 0 );

		$this->log( sprintf( 'Outline tokens - API returned: prompt=%d, completion=%d, cost=$%.6f', 
			$result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, $result['cost_usd'] ?? 0 ) );

		return array(
			'outline'           => $result['content'],
			'step'              => 'content',
			'progress'          => 20,
			'status'            => 'processing',
			'prompt_tokens'     => $prompt_tokens,
			'completion_tokens' => $completion_tokens,
			'cost_usd'          => $cost_usd,
		);
	}

	private function step_content( $job ) {
		$this->log( 'Step 2: Generating content...' );
		$result = $this->generate_content( $job['topic'], $job['outline'], $job['options'] );
		
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$content_data = $this->parse_content_result( $result );
		
		if ( is_wp_error( $content_data ) ) {
			return $content_data;
		}

		// Track tokens
		$prompt_tokens = ( $job['prompt_tokens'] ?? 0 ) + ( $result['prompt_tokens'] ?? 0 );
		$completion_tokens = ( $job['completion_tokens'] ?? 0 ) + ( $result['completion_tokens'] ?? 0 );
		$cost_usd = ( $job['cost_usd'] ?? 0 ) + ( $result['cost_usd'] ?? 0 );

		$this->log( sprintf( 'Content tokens - API returned: prompt=%d, completion=%d, cost=$%.6f', 
			$result['prompt_tokens'] ?? 0, $result['completion_tokens'] ?? 0, $result['cost_usd'] ?? 0 ) );
		$this->log( sprintf( 'Running totals - prompt=%d, completion=%d, cost=$%.6f', 
			$prompt_tokens, $completion_tokens, $cost_usd ) );

		return array(
			'content'           => $content_data,
			'step'              => 'post',
			'progress'          => 50,
			'status'            => 'processing',
			'prompt_tokens'     => $prompt_tokens,
			'completion_tokens' => $completion_tokens,
			'cost_usd'          => $cost_usd,
		);
	}

	private function step_create_post( $job ) {
		$this->log( 'Step 3: Creating WordPress post...' );
		$post_id = $this->create_wordpress_post( $job['topic'], $job['content'], $job['options'] );
		
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$next_step = $job['options']['generate_image'] ? 'images' : 'seo';

		return array(
			'post_id'  => $post_id,
			'step'     => $next_step,
			'progress' => 60,
			'status'   => 'processing',
		);
	}

	private function step_images( $job ) {
		$this->log( 'Step 4: Adding images from Pexels...' );
		
		// Featured image
		$image_result = $this->add_featured_image( $job['post_id'], $job['topic'], $job['content']['title'] ?? '' );
		if ( is_wp_error( $image_result ) ) {
			$this->log( 'Featured image failed: ' . $image_result->get_error_message() );
		}

		// Inline images
		if ( ! empty( $job['content']['images'] ) ) {
			$this->add_inline_images( $job['post_id'], $job['content'] );
		}

		return array(
			'step'     => 'seo',
			'progress' => 85,
			'status'   => 'processing',
		);
	}

	private function step_seo( $job ) {
		$this->log( 'Step 5: Setting SEO metadata...' );
		
		// Reset token tracking for this step
		$this->token_usage = array(
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
			'total_tokens'      => 0,
			'cost_usd'          => 0,
		);
		
		$this->set_seo_metadata( $job['post_id'], $job['topic'], $job['content'] );

		// Add SEO tokens to job totals
		$prompt_tokens = ( $job['prompt_tokens'] ?? 0 ) + $this->token_usage['prompt_tokens'];
		$completion_tokens = ( $job['completion_tokens'] ?? 0 ) + $this->token_usage['completion_tokens'];
		$cost_usd = ( $job['cost_usd'] ?? 0 ) + $this->token_usage['cost_usd'];

		// Debug log the token values
		$this->log( sprintf( 'Token tracking - Prompt: %d, Completion: %d, Cost: $%.6f', $prompt_tokens, $completion_tokens, $cost_usd ) );

		$log_result = $this->cost_tracker->log( array(
			'post_id'           => $job['post_id'],
			'model_used'        => $job['options']['model'],
			'prompt_tokens'     => $prompt_tokens,
			'completion_tokens' => $completion_tokens,
			'total_tokens'      => $prompt_tokens + $completion_tokens,
			'cost_usd'          => $cost_usd,
			'image_cost_usd'    => 0,
			'status'            => 'success',
		) );

		$this->log( sprintf( 'Generation complete! Post ID: %d, Cost: $%.4f, Log ID: %s', $job['post_id'], $cost_usd, $log_result ? $log_result : 'failed' ) );

		return array(
			'step'              => 'complete',
			'progress'          => 100,
			'status'            => 'completed',
			'prompt_tokens'     => $prompt_tokens,
			'completion_tokens' => $completion_tokens,
			'cost_usd'          => $cost_usd,
			'edit_url'          => get_edit_post_link( $job['post_id'], 'raw' ),
			'view_url'          => get_permalink( $job['post_id'] ),
		);
	}

	private function get_step_message( $step ) {
		$messages = array(
			'outline' => __( 'Generating outline...', 'ai-blog-posts' ),
			'content' => __( 'Writing content...', 'ai-blog-posts' ),
			'post'    => __( 'Creating post...', 'ai-blog-posts' ),
			'images'  => __( 'Adding images...', 'ai-blog-posts' ),
			'seo'     => __( 'Optimizing SEO...', 'ai-blog-posts' ),
			'complete' => __( 'Complete!', 'ai-blog-posts' ),
		);
		return $messages[ $step ] ?? '';
	}

	/**
	 * Generate a complete blog post (synchronous mode).
	 *
	 * Synchronous generation: outline -> content -> create post -> images -> SEO
	 *
	 * @since    1.0.0
	 * @param    string $topic      The topic to write about.
	 * @param    array  $options    Generation options.
	 * @return   array|WP_Error     Result with post_id or error.
	 */
	public function generate_post( $topic, $options = array() ) {
		// Extend execution time
		@set_time_limit( 300 ); // 5 minutes

		// Parse options - get defaults from settings
		$defaults = array(
			'keywords'       => '',
			'category_id'    => Ai_Blog_Posts_Settings::get( 'category' ) ?: 0,
			'publish'        => Ai_Blog_Posts_Settings::get( 'post_status' ) === 'publish',
			'generate_image' => Ai_Blog_Posts_Settings::get( 'image_enabled' ),
			'model'          => Ai_Blog_Posts_Settings::get( 'model' ),
		);
		$options = wp_parse_args( $options, $defaults );

		// Check API configuration
		if ( ! Ai_Blog_Posts_Settings::is_configured() ) {
			return new WP_Error( 'api_not_configured', __( 'OpenAI API key is not configured.', 'ai-blog-posts' ) );
		}

		$this->log( sprintf( 'Starting generation for topic: %s', $topic ) );

		// Step 1: Generate outline
		$this->log( 'Step 1: Generating outline...' );
		$outline_result = $this->generate_outline( $topic, $options );
		if ( is_wp_error( $outline_result ) ) {
			return $outline_result;
		}
		$this->add_token_usage( $outline_result );
		$this->log( sprintf( 'Outline generated: %d chars', strlen( $outline_result['content'] ) ) );

		// Brief delay between API calls
		sleep( 1 );

		// Step 2: Generate content
		$this->log( 'Step 2: Generating content...' );
		$content_result = $this->generate_content( $topic, $outline_result['content'], $options );
		if ( is_wp_error( $content_result ) ) {
			return $content_result;
		}
		$this->add_token_usage( $content_result );
		$this->log( sprintf( 'Content generated: %d chars', strlen( $content_result['content'] ?? '' ) ) );

		// Parse content result
		$content_data = $this->parse_content_result( $content_result );
		if ( is_wp_error( $content_data ) ) {
			return $content_data;
		}

		// Step 3: Create WordPress post
		$this->log( 'Step 3: Creating WordPress post...' );
		$post_id = $this->create_wordpress_post( $topic, $content_data, $options );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$this->log( sprintf( 'Post created with ID: %d', $post_id ) );

		// Step 4: Add featured image (optional)
		if ( $options['generate_image'] ) {
			$this->log( 'Step 4: Adding featured image from Pexels...' );
			$image_result = $this->add_featured_image( $post_id, $topic, $content_data['title'] );
			if ( is_wp_error( $image_result ) ) {
				$this->log( sprintf( 'Featured image failed: %s (continuing anyway)', $image_result->get_error_message() ) );
			} else {
				$this->log( sprintf( 'Featured image added: attachment ID %d', $image_result ) );
			}
		}

		// Step 5: Add inline images to content (optional)
		if ( $options['generate_image'] && ! empty( $content_data['images'] ) ) {
			$this->log( 'Step 5: Adding inline images from Pexels...' );
			$this->add_inline_images( $post_id, $content_data );
		}

		// Step 6: Set SEO metadata
		$this->log( 'Step 6: Setting SEO metadata...' );
		$this->set_seo_metadata( $post_id, $topic, $content_data );

		// Log completion
		$this->cost_tracker->log( array(
			'post_id'           => $post_id,
			'model_used'        => $options['model'],
			'prompt_tokens'     => $this->token_usage['prompt_tokens'],
			'completion_tokens' => $this->token_usage['completion_tokens'],
			'total_tokens'      => $this->token_usage['prompt_tokens'] + $this->token_usage['completion_tokens'],
			'cost_usd'          => $this->token_usage['cost_usd'],
			'image_cost_usd'    => 0,
			'status'            => 'success',
		) );

		$this->log( sprintf( 'Generation complete! Post ID: %d, Cost: $%.4f', $post_id, $this->token_usage['cost_usd'] ) );

		return array(
			'post_id'    => $post_id,
			'title'      => $content_data['title'],
			'cost_usd'   => $this->token_usage['cost_usd'],
			'edit_url'   => get_edit_post_link( $post_id, 'raw' ),
			'view_url'   => get_permalink( $post_id ),
		);
	}

	/**
	 * Generate content outline.
	 *
	 * @since    1.0.0
	 * @param    string $topic    Topic to outline.
	 * @param    array  $options  Options.
	 * @return   array|WP_Error   Result or error.
	 */
	private function generate_outline( $topic, $options ) {
		$model = $options['model'] ?? Ai_Blog_Posts_Settings::get( 'model' );
		$keywords = $options['keywords'] ?? '';

		$system_prompt = 'You are an expert content strategist who creates clear, actionable blog outlines.';
		
		$prompt = sprintf(
			"Create a detailed outline for a blog post about: %s\n\n" .
			"Target keywords: %s\n\n" .
			"Requirements:\n" .
			"1. Create an attention-grabbing title (no generic titles)\n" .
			"2. Structure with 4-6 main sections, each with:\n" .
			"   - Clear H2 heading\n" .
			"   - 2-3 specific subpoints to cover\n" .
			"   - Practical examples or tips to include\n" .
			"3. Opening section should hook the reader immediately\n" .
			"4. Closing section should summarize actionable takeaways\n" .
			"5. Focus on practical, useful information (not filler content)\n\n" .
			"Format the outline clearly with headings and bullet points.",
			$topic,
			$keywords ?: 'related to the topic'
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: Generating outline using model: %s', $model ) );
		}

		// Use generous token limit to avoid reasoning token issues with GPT-5.2
		return $this->openai->generate_text( $prompt, $system_prompt, array(
			'model'      => $model,
			'max_tokens' => 8000,
		) );
	}

	/**
	 * Generate full blog content.
	 *
	 * @since    1.0.0
	 * @param    string $topic    Topic.
	 * @param    string $outline  Generated outline.
	 * @param    array  $options  Options.
	 * @return   array|WP_Error   Result or error.
	 */
	private function generate_content( $topic, $outline, $options ) {
		$model = $options['model'] ?? Ai_Blog_Posts_Settings::get( 'model' );
		$word_count = Ai_Blog_Posts_Settings::get( 'word_count' ) ?: 1000;
		$generate_images = $options['generate_image'] ?? false;

		// Extend PHP execution time for content generation (this is the longest API call)
		@set_time_limit( 300 );

		$system_prompt = "You are a skilled content writer who creates engaging, natural-sounding blog posts. " .
			"Write like a knowledgeable human expert, not like AI. Use conversational language, " .
			"vary sentence structure, and include practical insights. Never use em dashes. " .
			"Use commas, semicolons, or separate sentences instead.";

		$prompt = sprintf(
			"Write a complete, publish-ready blog post based on this outline:\n\n%s\n\n" .
			"STRICT REQUIREMENTS:\n" .
			"1. Word count: approximately %d words\n" .
			"2. Format: Clean HTML using h2, h3, p, ul, li tags only\n" .
			"3. Writing style:\n" .
			"   - Write naturally like an experienced professional, not robotic AI\n" .
			"   - Use active voice and direct language\n" .
			"   - NEVER use em dashes (—). Use commas or periods instead\n" .
			"   - Vary paragraph lengths (2-4 sentences each)\n" .
			"   - Include specific examples and actionable advice\n" .
			"4. Structure:\n" .
			"   - Start with a compelling hook (no generic openings)\n" .
			"   - Use clear section headings (h2 for main sections, h3 for subsections)\n" .
			"   - End with a strong conclusion that summarizes key takeaways\n" .
			"   - DO NOT include any template text, CTAs, or placeholder content\n" .
			"5. Quality:\n" .
			"   - Every sentence should add value\n" .
			"   - Be specific and practical, not vague or generic\n",
			$outline,
			$word_count
		);

		if ( $generate_images ) {
			$prompt .= "\n6. Images: Suggest exactly 3 images that would enhance the content.\n" .
				"   For each image, provide:\n" .
				"   - searchQuery: A specific 2-4 word phrase for finding a relevant stock photo (e.g., 'business meeting laptop', 'marketing team brainstorm')\n" .
				"   - altText: Descriptive alt text for accessibility\n" .
				"   - placement: Where to insert (use 'section_1', 'section_2', 'section_3' to distribute throughout)\n" .
				"   Choose images that directly illustrate the section content, not generic stock photos.\n";
		}

		$prompt .= "\nReturn your response as valid JSON:\n" .
			'{"title": "Compelling Post Title", "content": "<html content>", "excerpt": "One sentence summary", "images": []}';

		$this->log( sprintf( 'Content generation: model=%s, word_count=%d, images=%s', $model, $word_count, $generate_images ? 'yes' : 'no' ) );

		$start_time = microtime( true );
		// Use generous token limit (16000) to avoid reasoning token issues with GPT-5.2
		$result = $this->openai->generate_text( $prompt, $system_prompt, array(
			'model'           => $model,
			'max_tokens'      => 16000,
			'temperature'     => 0.7,
			'response_format' => array( 'type' => 'json_object' ),
		) );
		$duration = microtime( true ) - $start_time;

		if ( is_wp_error( $result ) ) {
			$this->log( sprintf( 'Content generation FAILED after %.1fs: %s', $duration, $result->get_error_message() ) );
		} else {
			$this->log( sprintf( 'Content generation completed in %.1fs, tokens: %d', $duration, $result['total_tokens'] ?? 0 ) );
		}

		return $result;
	}

	/**
	 * Parse content result from API.
	 *
	 * @since    1.0.0
	 * @param    array $result    API result.
	 * @return   array|WP_Error   Parsed data or error.
	 */
	private function parse_content_result( $result ) {
		$content = $result['content'] ?? '';
		
		if ( empty( $content ) ) {
			return new WP_Error( 'empty_content', __( 'No content generated.', 'ai-blog-posts' ) );
		}

		// Try to parse as JSON
		$data = json_decode( $content, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// Not JSON, treat as raw content
			return array(
				'title'   => '',
				'content' => $content,
				'excerpt' => '',
				'images'  => array(),
			);
		}

		return array(
			'title'   => $data['title'] ?? '',
			'content' => $data['content'] ?? '',
			'excerpt' => $data['excerpt'] ?? '',
			'images'  => $data['images'] ?? array(),
		);
	}

	/**
	 * Create WordPress post.
	 *
	 * @since    1.0.0
	 * @param    string $topic         Topic.
	 * @param    array  $content_data  Parsed content data.
	 * @param    array  $options       Options.
	 * @return   int|WP_Error          Post ID or error.
	 */
	private function create_wordpress_post( $topic, $content_data, $options ) {
		// Use topic as title if setting is enabled, otherwise use AI-generated title
		$use_topic_as_title = Ai_Blog_Posts_Settings::get( 'use_topic_as_title' );
		if ( $use_topic_as_title ) {
			$title = $topic;
		} else {
			$title = ! empty( $content_data['title'] ) ? $content_data['title'] : $topic;
		}
		$content = $content_data['content'];
		$excerpt = $content_data['excerpt'] ?? '';

		$post_data = array(
			'post_title'   => sanitize_text_field( $title ),
			'post_content' => wp_kses_post( $content ),
			'post_excerpt' => sanitize_text_field( $excerpt ),
			'post_status'  => $options['publish'] ? 'publish' : 'draft',
			'post_type'    => 'post',
			'post_author'  => get_current_user_id() ?: 1,
		);

		// Handle category assignment
		$category_id = isset( $options['category_id'] ) ? (int) $options['category_id'] : 0;
		
		if ( $category_id > 0 ) {
			// Verify the category exists
			$category = get_term( $category_id, 'category' );
			if ( $category && ! is_wp_error( $category ) ) {
				$post_data['post_category'] = array( $category_id );
				$this->log( sprintf( 'Assigning post to category ID %d (%s)', $category_id, $category->name ) );
			} else {
				$this->log( sprintf( 'Category ID %d not found, post will be uncategorized', $category_id ) );
			}
		} else {
			$this->log( 'No category_id provided in options, post will be uncategorized' );
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Store metadata
		update_post_meta( $post_id, '_ai_blog_posts_generated', true );
		update_post_meta( $post_id, '_ai_blog_posts_topic', $topic );
		update_post_meta( $post_id, '_ai_blog_posts_generated_at', current_time( 'mysql' ) );

		return $post_id;
	}

	/**
	 * Add featured image from Pexels.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id  Post ID.
	 * @param    string $topic    Topic.
	 * @param    string $title    Post title.
	 * @return   int|WP_Error     Attachment ID or error.
	 */
	private function add_featured_image( $post_id, $topic, $title ) {
		$search_query = ! empty( $title ) ? $title : $topic;
		$orientation = Ai_Blog_Posts_Settings::get( 'pexels_orientation' ) ?: 'landscape';
		
		// Search Pexels for image
		$images = $this->pexels->search_images( $search_query, array(
			'orientation' => $orientation,
			'per_page'    => 5,
		) );

		if ( is_wp_error( $images ) ) {
			return $images;
		}

		// Pexels returns { photos: [...] }
		$photos = $images['photos'] ?? array();
		if ( empty( $photos ) ) {
			return new WP_Error( 'no_images', __( 'No images found on Pexels.', 'ai-blog-posts' ) );
		}

		// Use first image
		$image = $photos[0];
		$image_url = $image['src']['large'] ?? $image['src']['original'] ?? '';

		if ( empty( $image_url ) ) {
			return new WP_Error( 'no_image_url', __( 'No image URL in Pexels response.', 'ai-blog-posts' ) );
		}

		// Download and attach
		$attachment_id = $this->pexels->download_image(
			$image_url,
			sanitize_title( $title ) . '-featured',
			$post_id,
			$image['alt'] ?? $title,
			$image['photographer'] ?? ''
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Set as featured image
		set_post_thumbnail( $post_id, $attachment_id );

		return $attachment_id;
	}

	/**
	 * Add inline images to content.
	 *
	 * @since    1.0.0
	 * @param    int   $post_id       Post ID.
	 * @param    array $content_data  Content data with images array.
	 */
	private function add_inline_images( $post_id, $content_data ) {
		if ( empty( $content_data['images'] ) || ! is_array( $content_data['images'] ) ) {
			return;
		}

		$post = get_post( $post_id );
		$content = $post->post_content;

		// Get settings
		$orientation = Ai_Blog_Posts_Settings::get( 'pexels_orientation' ) ?: 'landscape';
		$max_images = Ai_Blog_Posts_Settings::get( 'pexels_inline_images' );
		if ( $max_images === null ) {
			$max_images = 3;
		}

		// If inline images disabled, return
		if ( $max_images <= 0 ) {
			return;
		}

		// Find all h2 positions to distribute images
		preg_match_all( '/<\/h2>/i', $content, $matches, PREG_OFFSET_CAPTURE );
		$h2_positions = array();
		foreach ( $matches[0] as $match ) {
			$h2_positions[] = $match[1] + strlen( $match[0] );
		}

		// Limit to max images setting, distributed across sections
		$images_to_add = array_slice( $content_data['images'], 0, $max_images );
		$images_added = 0;

		foreach ( $images_to_add as $index => $image_suggestion ) {
			$search_query = $image_suggestion['searchQuery'] ?? '';
			$alt_text = $image_suggestion['altText'] ?? '';

			if ( empty( $search_query ) ) {
				continue;
			}

			// Search Pexels
			$images = $this->pexels->search_images( $search_query, array(
				'orientation' => $orientation,
				'per_page'    => 3,
			) );

			// Pexels returns { photos: [...] }
			$photos = $images['photos'] ?? array();
			if ( is_wp_error( $images ) || empty( $photos ) ) {
				continue;
			}

			$image = $photos[0];
			$image_url = $image['src']['large'] ?? $image['src']['original'] ?? '';

			if ( empty( $image_url ) ) {
				continue;
			}

			// Download image
			$attachment_id = $this->pexels->download_image(
				$image_url,
				sanitize_title( $search_query ),
				$post_id,
				$alt_text ?: $search_query,
				$image['photographer'] ?? ''
			);

			if ( is_wp_error( $attachment_id ) ) {
				continue;
			}

			// Create image HTML
			$img_html = wp_get_attachment_image( $attachment_id, 'large', false, array(
				'class' => 'ai-blog-posts-inline-image',
				'alt'   => esc_attr( $alt_text ?: $search_query ),
			) );
			$figure_html = "\n<figure class=\"wp-block-image aligncenter\">" . $img_html . "</figure>\n";

			// Determine which h2 to insert after (distribute evenly)
			// Image 0 after h2[1], Image 1 after h2[3], Image 2 after h2[5], etc.
			$target_h2_index = ( $images_added * 2 ) + 1;
			
			if ( isset( $h2_positions[ $target_h2_index ] ) ) {
				// Insert after this h2
				$insert_pos = $h2_positions[ $target_h2_index ];
				$content = substr( $content, 0, $insert_pos ) . $figure_html . substr( $content, $insert_pos );
				
				// Adjust all future positions
				$offset = strlen( $figure_html );
				for ( $i = $target_h2_index + 1; $i < count( $h2_positions ); $i++ ) {
					$h2_positions[ $i ] += $offset;
				}
			} elseif ( ! empty( $h2_positions ) ) {
				// Fallback: insert after a later h2 if available
				$fallback_index = min( $images_added, count( $h2_positions ) - 1 );
				$insert_pos = $h2_positions[ $fallback_index ];
				$content = substr( $content, 0, $insert_pos ) . $figure_html . substr( $content, $insert_pos );
			}

			$images_added++;
		}

		// Update post content
		wp_update_post( array(
			'ID'           => $post_id,
			'post_content' => $content,
		) );
	}

	/**
	 * Set SEO metadata for post.
	 *
	 * Uses AI to generate optimized SEO title and meta description that follow
	 * best practices for character length and keyword optimization.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id       Post ID.
	 * @param    string $topic         Topic.
	 * @param    array  $content_data  Content data.
	 */
	private function set_seo_metadata( $post_id, $topic, $content_data ) {
		$title = $content_data['title'] ?: $topic;
		$content = $content_data['content'] ?: '';
		$keywords = $content_data['keywords'] ?? $topic;

		// Generate optimized SEO metadata using AI
		$seo_data = $this->generate_optimized_seo( $title, $content, $keywords );
		
		if ( is_wp_error( $seo_data ) ) {
			$this->log( 'SEO optimization failed: ' . $seo_data->get_error_message() . ' - using fallback' );
			// Fallback to basic SEO
			$seo_title = $title;
			$meta_description = wp_trim_words( wp_strip_all_tags( $content ), 25 );
		} else {
			$seo_title = $seo_data['seo_title'] ?? $title;
			$meta_description = $seo_data['meta_description'] ?? wp_trim_words( wp_strip_all_tags( $content ), 25 );
			$this->log( sprintf( 'SEO optimized - Title: %d chars, Description: %d chars', strlen( $seo_title ), strlen( $meta_description ) ) );
		}

		// Apply to active SEO plugin
		$this->seo->set_post_meta( $post_id, array(
			'seo_title'        => $seo_title,
			'meta_description' => $meta_description,
			'focus_keyword'    => sanitize_text_field( $topic ),
		) );

		// Generate and set tags
		$tags = $this->generate_tags( $topic, $content );
		if ( ! empty( $tags ) ) {
			wp_set_post_tags( $post_id, $tags, false );
		}
	}

	/**
	 * Generate optimized SEO title and meta description using AI.
	 *
	 * @since    1.0.0
	 * @param    string $title     Post title.
	 * @param    string $content   Post content.
	 * @param    string $keywords  Focus keywords.
	 * @return   array|WP_Error    SEO data or error.
	 */
	private function generate_optimized_seo( $title, $content, $keywords ) {
		$model = Ai_Blog_Posts_Settings::get( 'model' );
		
		// Get first 1500 chars of content for context (enough for SEO but not too much)
		$content_excerpt = wp_trim_words( wp_strip_all_tags( $content ), 200 );

		$system_prompt = 'You are an SEO expert who creates optimized meta titles and descriptions that rank well in search engines.';
		
		$prompt = sprintf(
			"Create an SEO-optimized title and meta description for this blog post.\n\n" .
			"Original Title: %s\n" .
			"Focus Keywords: %s\n" .
			"Content Preview: %s\n\n" .
			"STRICT REQUIREMENTS:\n" .
			"1. SEO Title:\n" .
			"   - MUST be 50-60 characters (this is critical for Google display)\n" .
			"   - Include the primary keyword naturally\n" .
			"   - Make it compelling and click-worthy\n" .
			"   - Use power words if appropriate (e.g., Ultimate, Complete, Essential)\n" .
			"   - Can include year or numbers if relevant\n\n" .
			"2. Meta Description:\n" .
			"   - MUST be 150-160 characters (this is critical for Google display)\n" .
			"   - Include a clear value proposition\n" .
			"   - Include the focus keyword naturally\n" .
			"   - End with a subtle call-to-action or benefit\n" .
			"   - Make it readable and engaging\n\n" .
			"Return ONLY valid JSON:\n" .
			'{"seo_title": "Your 50-60 char title", "meta_description": "Your 150-160 char description"}',
			$title,
			$keywords,
			$content_excerpt
		);

		$result = $this->openai->generate_text( $prompt, $system_prompt, array(
			'model'           => $model,
			'max_tokens'      => 500,
			'temperature'     => 0.7,
			'response_format' => array( 'type' => 'json_object' ),
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Track tokens
		$this->add_token_usage( $result );

		// Parse response
		$response_content = $result['content'] ?? '';
		$data = json_decode( $response_content, true );

		if ( json_last_error() !== JSON_ERROR_NONE || empty( $data ) ) {
			return new WP_Error( 'seo_parse_error', 'Failed to parse SEO response' );
		}

		return array(
			'seo_title'        => $data['seo_title'] ?? $title,
			'meta_description' => $data['meta_description'] ?? '',
		);
	}

	/**
	 * Generate tags from content.
	 *
	 * @since    1.0.0
	 * @param    string $topic    Topic.
	 * @param    string $content  Content.
	 * @return   array            Tags.
	 */
	private function generate_tags( $topic, $content ) {
		// Extract key words from topic
		$words = preg_split( '/\s+/', strtolower( $topic ) );
		$stop_words = array( 'the', 'a', 'an', 'and', 'or', 'but', 'for', 'to', 'of', 'in', 'on', 'with', 'how', 'what', 'why', 'when', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'shall' );
		
		$tags = array();
		foreach ( $words as $word ) {
			$word = trim( $word, '.,!?;:' );
			if ( strlen( $word ) > 3 && ! in_array( $word, $stop_words, true ) ) {
				$tags[] = $word;
			}
		}

		return array_slice( array_unique( $tags ), 0, 5 );
	}

	/**
	 * Add token usage from a result.
	 *
	 * @since    1.0.0
	 * @param    array $result    API result with token counts.
	 */
	private function add_token_usage( $result ) {
		$this->token_usage['prompt_tokens'] += $result['prompt_tokens'] ?? 0;
		$this->token_usage['completion_tokens'] += $result['completion_tokens'] ?? 0;
		$this->token_usage['total_tokens'] += $result['total_tokens'] ?? 0;
		$this->token_usage['cost_usd'] += $result['cost_usd'] ?? 0;
	}

	/**
	 * Log a message.
	 *
	 * @since    1.0.0
	 * @param    string $message    Message to log.
	 */
	private function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[AI Blog Posts] %s', $message ) );
		}
	}
}

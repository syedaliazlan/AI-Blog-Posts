<?php

/**
 * OpenAI API wrapper class
 *
 * @link       https://devonicweb.co.uk/
 * @since      1.0.0
 *
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 */

/**
 * Handles all OpenAI API interactions.
 *
 * Provides methods for text generation (GPT-5.2) with built-in rate limiting, 
 * retry logic, and cost tracking. Image generation has been replaced with Pexels API.
 *
 * @since      1.0.0
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 * @author     Ali Azlan <contact@devonicweb.co.uk>
 */
class Ai_Blog_Posts_OpenAI {

	/**
	 * OpenAI API base URL.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	private const API_BASE = 'https://api.openai.com/v1';

	/**
	 * Maximum retry attempts for failed requests.
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	private const MAX_RETRIES = 3;

	/**
	 * Timeout for API requests in seconds.
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	private const TIMEOUT = 300; // 5 minutes for longer generations

	/**
	 * Current request timeout (used by cURL filter).
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	private $current_request_timeout = 180;

	/**
	 * The API key.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private $api_key;

	/**
	 * The organization ID.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private $org_id;

	/**
	 * Last API response.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array
	 */
	private $last_response;

	/**
	 * Last error message.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private $last_error;

	/**
	 * API call counter for this session.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      int
	 */
	private $api_call_count = 0;

	/**
	 * Global API call counter (stored in transient for logging).
	 *
	 * @since    1.1.0
	 * @access   private
	 * @static
	 * @var      int
	 */
	private static $global_call_count = 0;

	/**
	 * Initialize the class.
	 *
	 * @since    1.0.0
	 * @param    string $api_key    Optional API key override.
	 * @param    string $org_id     Optional organization ID override.
	 */
	public function __construct( $api_key = null, $org_id = null ) {
		$this->api_key = $api_key ?? Ai_Blog_Posts_Settings::get( 'api_key' );
		$this->org_id = $org_id ?? Ai_Blog_Posts_Settings::get( 'org_id' );
		
		// Load global call count from option (persists across requests)
		self::$global_call_count = (int) get_option( 'ai_blog_posts_total_api_calls', 0 );
	}
	
	/**
	 * Get the number of API calls made in this session.
	 *
	 * @since    1.1.0
	 * @return   int    Number of API calls.
	 */
	public function get_call_count() {
		return $this->api_call_count;
	}
	
	/**
	 * Get the total number of API calls made (all time).
	 *
	 * @since    1.1.0
	 * @return   int    Total number of API calls.
	 */
	public static function get_total_call_count() {
		return (int) get_option( 'ai_blog_posts_total_api_calls', 0 );
	}
	
	/**
	 * Increment the API call counter.
	 *
	 * @since    1.1.0
	 * @param    string $endpoint    The endpoint being called.
	 */
	private function increment_call_count( $endpoint ) {
		$this->api_call_count++;
		self::$global_call_count++;
		
		// Update total in database (batched - only update every 10 calls to reduce DB writes)
		// This reduces the chance of "Commands out of sync" errors on shared hosting
		if ( self::$global_call_count % 10 === 0 ) {
			$this->safe_update_call_count();
		}
		
		// Log API call for debugging (less verbose)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 
				'[AI Blog Posts] API Call #%d to %s', 
				$this->api_call_count,
				$endpoint
			) );
		}
	}

	/**
	 * Safely update the API call count in the database.
	 * Uses a try-catch to avoid "Commands out of sync" errors.
	 *
	 * @since    1.1.0
	 */
	private function safe_update_call_count() {
		global $wpdb;
		
		// Check if we're in a valid state to update
		if ( ! $wpdb || ! $wpdb->ready ) {
			return;
		}
		
		// Use direct query to avoid triggering hooks
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s",
			self::$global_call_count,
			'ai_blog_posts_total_api_calls'
		) );
	}
	
	/**
	 * Ensure total call count is saved to database.
	 * Called at the end of generation (not on shutdown to avoid DB conflicts).
	 *
	 * @since    1.1.0
	 */
	public function save_call_count() {
		// Use safe update to avoid "Commands out of sync" errors
		$this->safe_update_call_count();
	}

	/**
	 * Verify the API key by making a test request.
	 *
	 * @since    1.0.0
	 * @param    string $api_key    Optional API key to test.
	 * @return   array              Result with 'success' and 'message' keys.
	 */
	public function verify_api_key( $api_key = null ) {
		$key = $api_key ?? $this->api_key;

		if ( empty( $key ) ) {
			return array(
				'success' => false,
				'message' => __( 'API key is empty.', 'ai-blog-posts' ),
			);
		}

		// Test with a minimal models list request
		$response = $this->make_request( 'GET', '/models', array(), $key );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		if ( isset( $response['data'] ) ) {
			// Update verified status
			Ai_Blog_Posts_Settings::set( 'api_verified', true );
			
			return array(
				'success' => true,
				'message' => __( 'API key verified successfully!', 'ai-blog-posts' ),
				'models'  => $this->filter_relevant_models( $response['data'] ),
			);
		}

		return array(
			'success' => false,
			'message' => $response['error']['message'] ?? __( 'Unknown error occurred.', 'ai-blog-posts' ),
		);
	}

	/**
	 * Generate text using GPT models.
	 *
	 * @since    1.0.0
	 * @param    string $prompt         The user prompt.
	 * @param    string $system_prompt  Optional system prompt.
	 * @param    array  $options        Additional options.
	 * @return   array|WP_Error         Response array or error.
	 */
	public function generate_text( $prompt, $system_prompt = '', $options = array() ) {
		$model = $options['model'] ?? Ai_Blog_Posts_Settings::get( 'model' );
		$max_tokens = $options['max_tokens'] ?? 4000;
		$temperature = $options['temperature'] ?? 0.7;

		$messages = array();

		// Check if model supports system messages (o1/reasoning models don't)
		$is_reasoning_model = $this->is_reasoning_model( $model );
		// GPT-5.2 and newer GPT-5 models use "developer" role instead of "system" role
		// According to OpenAI API documentation: https://platform.openai.com/docs/api-reference/chat/create
		$is_gpt5_model = strpos( $model, 'gpt-5' ) === 0;
		
		if ( ! empty( $system_prompt ) ) {
			if ( $is_reasoning_model ) {
				// For reasoning models (o1, o3, o4), prepend system prompt to user message
				// Reasoning models don't support system messages
				$prompt = "Instructions: " . $system_prompt . "\n\n" . $prompt;
			} elseif ( $is_gpt5_model ) {
				// GPT-5.2 and newer GPT-5 models use "developer" role instead of "system"
				// This is the correct role according to OpenAI API documentation
				$messages[] = array(
					'role'    => 'developer',
					'content' => $system_prompt,
				);
			} else {
				// Legacy models (GPT-4, GPT-3.5) use "system" role
				$messages[] = array(
					'role'    => 'system',
					'content' => $system_prompt,
				);
			}
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => $prompt,
		);

		// Build request body based on model capabilities
		$body = array(
			'model'    => $model,
			'messages' => $messages,
		);

		// GPT-5.2 uses max_completion_tokens (not max_tokens)
		// Default reasoning_effort is "none", so we use the requested tokens directly
		if ( $is_gpt5_model || $this->uses_max_completion_tokens( $model ) ) {
			$body['max_completion_tokens'] = (int) $max_tokens;
		} else {
			// Legacy models use max_tokens
			$body['max_tokens'] = (int) $max_tokens;
		}

		// Only add temperature for models that support it
		if ( $this->supports_temperature( $model ) ) {
			$body['temperature'] = $temperature;
		}

		// Add response format if specified (for JSON mode)
		if ( isset( $options['response_format'] ) && is_array( $options['response_format'] ) ) {
			$body['response_format'] = $options['response_format'];
		}

		// Log request for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: [OpenAI] Making request to /chat/completions with model: %s', $model ) );
			error_log( sprintf( 'AI Blog Posts: [OpenAI] Request body keys: %s', implode( ', ', array_keys( $body ) ) ) );
			if ( isset( $body['response_format'] ) ) {
				error_log( sprintf( 'AI Blog Posts: [OpenAI] Response format: %s', wp_json_encode( $body['response_format'] ) ) );
			}
		}

		$start_time = microtime( true );
		$response = $this->make_request( 'POST', '/chat/completions', $body );
		$generation_time = microtime( true ) - $start_time;
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: [OpenAI] Request completed in %.2f seconds', $generation_time ) );
		}

		if ( is_wp_error( $response ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'AI Blog Posts: [OpenAI] Request returned WP_Error: %s - %s', $response->get_error_code(), $response->get_error_message() ) );
			}
			return $response;
		}

		if ( isset( $response['error'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'AI Blog Posts: [OpenAI] API error: %s', wp_json_encode( $response['error'] ) ) );
			}
			return new WP_Error(
				'openai_error',
				$response['error']['message'] ?? __( 'Unknown API error.', 'ai-blog-posts' )
			);
		}

		$usage = $response['usage'] ?? array();
		$content = $response['choices'][0]['message']['content'] ?? '';
		$finish_reason = $response['choices'][0]['finish_reason'] ?? 'unknown';
		
		// Check for reasoning tokens usage (GPT-5.2 specific)
		$completion_details = $usage['completion_tokens_details'] ?? array();
		$reasoning_tokens = $completion_details['reasoning_tokens'] ?? 0;
		$completion_tokens = $usage['completion_tokens'] ?? 0;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: [OpenAI] Response received' ) );
			error_log( sprintf( 'AI Blog Posts: [OpenAI] Finish reason: %s', $finish_reason ) );
			error_log( sprintf( 'AI Blog Posts: [OpenAI] Content length: %d', strlen( $content ) ) );
			error_log( sprintf( 'AI Blog Posts: [OpenAI] Usage - prompt: %d, completion: %d, total: %d', 
				$usage['prompt_tokens'] ?? 0,
				$completion_tokens,
				$usage['total_tokens'] ?? 0
			) );
			if ( $reasoning_tokens > 0 ) {
				error_log( sprintf( 'AI Blog Posts: [OpenAI] Reasoning tokens: %d (%.1f%% of completion tokens)', 
					$reasoning_tokens,
					( $reasoning_tokens / max( $completion_tokens, 1 ) ) * 100
				) );
			}
		}

		// Log response details for debugging empty responses
		if ( empty( $content ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 
				'AI Blog Posts: [OpenAI] WARNING: Empty content from API. Model: %s, Finish reason: %s, Reasoning tokens: %d, Full response: %s',
				$model,
				$finish_reason,
				$reasoning_tokens,
				wp_json_encode( $response )
			) );
		}

		// Calculate cost
		$cost = $this->calculate_text_cost(
			$model,
			$usage['prompt_tokens'] ?? 0,
			$usage['completion_tokens'] ?? 0
		);

		return array(
			'content'           => $content,
			'model'             => $model,
			'prompt_tokens'     => $usage['prompt_tokens'] ?? 0,
			'completion_tokens' => $usage['completion_tokens'] ?? 0,
			'total_tokens'      => $usage['total_tokens'] ?? 0,
			'cost_usd'          => $cost,
			'generation_time'   => $generation_time,
			'finish_reason'     => $finish_reason,
		);
	}

	/**
	 * Check if a model uses max_completion_tokens parameter.
	 *
	 * @since    1.0.0
	 * @param    string $model    The model ID.
	 * @return   bool             True if uses max_completion_tokens.
	 */
	private function uses_max_completion_tokens( $model ) {
		// GPT-5.x, GPT-4.1.x, GPT-4o, and reasoning models use max_completion_tokens
		$new_model_prefixes = array(
			'gpt-5',    // GPT-5, GPT-5.1, GPT-5-mini, GPT-5-nano, GPT-5-pro
			'gpt-4.1',  // GPT-4.1, GPT-4.1-mini
			'gpt-4o',   // GPT-4o, GPT-4o-mini (legacy)
			'o1',       // o1, o1-mini, o1-pro
			'o3',       // o3, o3-mini
		);

		foreach ( $new_model_prefixes as $prefix ) {
			if ( strpos( $model, $prefix ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a model is a reasoning model (o1, o3, o4 series).
	 *
	 * @since    1.0.0
	 * @param    string $model    The model ID.
	 * @return   bool             True if reasoning model.
	 */
	private function is_reasoning_model( $model ) {
		// Reasoning models don't support temperature or system messages
		$reasoning_prefixes = array( 'o1', 'o3', 'o4' );

		foreach ( $reasoning_prefixes as $prefix ) {
			if ( strpos( $model, $prefix ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a model supports custom temperature values.
	 *
	 * According to OpenAI API documentation, GPT-5.2 and newer GPT-5 models
	 * do not support the temperature parameter. Temperature is fixed for these models.
	 *
	 * @since    1.0.0
	 * @param    string $model    The model ID.
	 * @return   bool             True if supports custom temperature.
	 */
	private function supports_temperature( $model ) {
		// Reasoning models (o1, o3, o4) don't support temperature
		// GPT-5.2 DOES support temperature (default reasoning_effort is "none")
		$no_temp_prefixes = array( 'o1', 'o3', 'o4' );

		foreach ( $no_temp_prefixes as $prefix ) {
			if ( strpos( $model, $prefix ) === 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * REMOVED: Image generation methods removed - using Pexels API instead.
	 * 
	 * @deprecated This method has been removed. Use Pexels API for images.
	 */
	private function _removed_generate_image( $prompt, $options = array() ) {
		// SIMPLIFIED: Use only DALL-E 3 with reliable defaults
		// This is the most stable and well-tested image model
		$model = 'dall-e-3';
		$size = '1792x1024';      // Landscape - perfect for blog featured images
		$quality = 'standard';    // Faster, cheaper, good quality
		$style = 'natural';       // More realistic images

		// Extend execution time for image generation
		@set_time_limit( 120 ); // 2 minutes max
		
		// Increase memory limit for image processing
		$original_memory_limit = ini_get( 'memory_limit' );
		@ini_set( 'memory_limit', '256M' );

		// Build request body - simple and reliable
		$body = array(
			'model'           => $model,
			'prompt'          => $prompt,
			'n'               => 1,
			'size'            => $size,
			'quality'         => $quality,
			'style'           => $style,
			'response_format' => 'url',
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: Starting image generation API call with model: %s, size: %s', $model, $size ) );
		}

		// Enforce maximum execution time for image generation (2 minutes max)
		// This ensures the request doesn't hang indefinitely on shared hosting
		$max_execution_time = 120; // 2 minutes maximum (matches API timeout + buffer)
		@set_time_limit( $max_execution_time );
		
		$start_time = microtime( true );
		
		// Make the request with enforced timeout (90 seconds)
		// Note: On some shared hosts, wp_remote_request may not respect timeouts
		// The watchdog mechanism will detect and recover if this hangs
		$response = $this->make_request( 'POST', '/images/generations', $body );
		
		$generation_time = microtime( true ) - $start_time;
		
		// Check if request took longer than expected (indicates potential hang or timeout)
		if ( $generation_time > 100 ) { // More than 100 seconds (timeout + buffer)
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'AI Blog Posts: WARNING - Image generation request took %.2f seconds, may have timed out or hung', $generation_time ) );
			}
			
			// If we got an error and it took too long, it's likely a timeout
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				
				// Check if it's a timeout-related error
				if ( strpos( strtolower( $error_message ), 'timeout' ) === false && 
				     strpos( strtolower( $error_message ), 'timed out' ) === false &&
				     strpos( strtolower( $error_message ), 'connection' ) === false ) {
					// Not explicitly a timeout, but took too long - treat as timeout
					return new WP_Error(
						'image_generation_timeout',
						sprintf( __( 'Image generation request timed out after %.1f seconds. The server may be slow or the connection may have been interrupted. The watchdog will automatically retry this topic.', 'ai-blog-posts' ), $generation_time ),
						array( 'duration' => $generation_time )
					);
				}
			}
		}

		// Restore memory limit
		if ( $original_memory_limit ) {
			@ini_set( 'memory_limit', $original_memory_limit );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: Image generation API call completed in %.2f seconds', $generation_time ) );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( isset( $response['error'] ) ) {
			return new WP_Error(
				'openai_error',
				$response['error']['message'] ?? __( 'Unknown API error.', 'ai-blog-posts' )
			);
		}

		// Image API returns URLs (or b64_json based on response_format)
		$image_url = $response['data'][0]['url'] ?? '';
		$revised_prompt = $response['data'][0]['revised_prompt'] ?? $prompt;

		if ( empty( $image_url ) ) {
			return new WP_Error(
				'no_image_url',
				__( 'No image URL received from API.', 'ai-blog-posts' )
			);
		}

		// Calculate cost - DALL-E 3, 1792x1024 landscape, standard = $0.08/image
		$cost = 0.08;

		return array(
			'url'             => $image_url,
			'revised_prompt'  => $revised_prompt,
			'model'           => $model,
			'size'            => $size,
			'cost_usd'        => $cost,
			'generation_time' => $generation_time,
		);
	}

	/**
	 * Validate and fix image size based on model type.
	 * 
	 * GPT Image models support: 1024x1024, 1536x1024, 1024x1536, auto
	 * DALL-E 3 supports: 1024x1024, 1024x1792, 1792x1024
	 *
	 * @since    1.1.0
	 * @param    string $model    Image model.
	 * @param    string $size     Requested size.
	 * @return   string           Valid size for the model.
	 */
	private function validate_image_size( $model, $size ) {
		$is_gpt_image = strpos( $model, 'gpt-image' ) === 0;
		
		if ( $is_gpt_image ) {
			// GPT Image supported sizes
			$supported_sizes = array( '1024x1024', '1536x1024', '1024x1536', 'auto' );
			
			if ( in_array( $size, $supported_sizes, true ) ) {
				return $size;
			}
			
			// Map DALL-E 3 sizes to GPT Image equivalents
			$size_map = array(
				'1792x1024' => '1536x1024', // Landscape
				'1024x1792' => '1024x1536', // Portrait
			);
		} else {
			// DALL-E 3 supported sizes
			$supported_sizes = array( '1024x1024', '1024x1792', '1792x1024' );
			
			if ( in_array( $size, $supported_sizes, true ) ) {
				return $size;
			}
			
			// Map GPT Image sizes to DALL-E 3 equivalents
			$size_map = array(
				'1536x1024' => '1792x1024', // Landscape
				'1024x1536' => '1024x1792', // Portrait
				'auto'      => '1024x1024', // Auto not supported in DALL-E 3
			);
		}
		
		// Common fallbacks
		$size_map['512x512'] = '1024x1024';
		$size_map['256x256'] = '1024x1024';

		if ( isset( $size_map[ $size ] ) ) {
			$fallback_size = $size_map[ $size ];
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 
					'AI Blog Posts: Size %s mapped to %s equivalent: %s', 
					$size, 
					$is_gpt_image ? 'GPT Image' : 'DALL-E 3',
					$fallback_size
				) );
			}
			
			return $fallback_size;
		}

		// Default to square
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 
				'AI Blog Posts: Size %s not supported for %s, using default: 1024x1024', 
				$size,
				$model
			) );
		}

		return '1024x1024';
	}
	
	/**
	 * Validate and fix image quality based on model type.
	 * 
	 * GPT Image models support: auto, low, medium, high
	 * DALL-E 3 supports: standard, hd
	 *
	 * @since    1.1.1
	 * @param    string $model    Image model.
	 * @param    string $quality  Requested quality.
	 * @return   string           Valid quality for the model.
	 */
	private function validate_image_quality( $model, $quality ) {
		$is_gpt_image = strpos( $model, 'gpt-image' ) === 0;
		
		if ( $is_gpt_image ) {
			// GPT Image supported qualities
			$supported_qualities = array( 'auto', 'low', 'medium', 'high' );
			
			if ( in_array( $quality, $supported_qualities, true ) ) {
				return $quality;
			}
			
			// Map DALL-E 3 qualities to GPT Image equivalents
			$quality_map = array(
				'standard' => 'medium',
				'hd'       => 'high',
			);
			
			if ( isset( $quality_map[ $quality ] ) ) {
				return $quality_map[ $quality ];
			}
			
			return 'auto'; // Default for GPT Image
		} else {
			// DALL-E 3 supported qualities
			$supported_qualities = array( 'standard', 'hd' );
			
			if ( in_array( $quality, $supported_qualities, true ) ) {
				return $quality;
			}
			
			// Map GPT Image qualities to DALL-E 3 equivalents
			$quality_map = array(
				'auto'   => 'standard',
				'low'    => 'standard',
				'medium' => 'standard',
				'high'   => 'hd',
			);
			
			if ( isset( $quality_map[ $quality ] ) ) {
				return $quality_map[ $quality ];
			}
			
			return 'standard'; // Default for DALL-E 3
		}
	}

	/**
	 * Download an image and add it to the media library.
	 *
	 * OpenAI image models return image URLs directly, which we download and add to WordPress.
	 *
	 * @since    1.0.0
	 * @param    string $image_url   The image URL from the API.
	 * @param    string $filename    The desired filename.
	 * @param    int    $post_id     Optional post ID to attach to.
	 * @return   int|WP_Error        Attachment ID or error.
	 */
	public function download_image_to_media( $image_url, $filename, $post_id = 0 ) {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: Downloading image from URL: %s', $image_url ) );
		}

		// Extend execution time for download
		@set_time_limit( 180 ); // 3 minutes for download

		// Download file to temp location with extended timeout for large images
		$temp_file = download_url( $image_url, 120 ); // 120 second timeout

		if ( is_wp_error( $temp_file ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'AI Blog Posts: Image download failed: %s', $temp_file->get_error_message() ) );
			}
			return $temp_file;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: Image downloaded to temp file: %s', $temp_file ) );
		}

		// Prepare file array
		$file_array = array(
			'name'     => sanitize_file_name( $filename . '.png' ),
			'tmp_name' => $temp_file,
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: Uploading image to media library for post %d', $post_id ) );
			error_log( sprintf( 'AI Blog Posts: Temp file size: %d bytes', filesize( $temp_file ) ) );
		}

		// Increase memory limit for image processing
		$original_memory_limit = ini_get( 'memory_limit' );
		@ini_set( 'memory_limit', '256M' );

		// Use fallback method directly - media_handle_sideload() hangs on shared hosting
		// The fallback method is more reliable and skips problematic thumbnail generation
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: Using fallback attachment method (media_handle_sideload hangs on shared hosting)' ) );
		}
		
		$attachment_id = $this->create_attachment_fallback( $temp_file, $filename, $post_id );
		
		// Restore memory limit
		if ( $original_memory_limit ) {
			@ini_set( 'memory_limit', $original_memory_limit );
		}

		// Clean up temp file
		if ( file_exists( $temp_file ) ) {
			wp_delete_file( $temp_file );
		}

		if ( is_wp_error( $attachment_id ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'AI Blog Posts: Fallback attachment method failed: %s', $attachment_id->get_error_message() ) );
			}
			return $attachment_id;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: Image uploaded successfully, attachment ID: %d', $attachment_id ) );
		}

		// Set as featured image immediately (before metadata generation which might hang)
		if ( $post_id > 0 ) {
			$thumbnail_set = set_post_thumbnail( $post_id, $attachment_id );
			if ( ! $thumbnail_set ) {
				// Fallback: Set directly in database
				update_post_meta( $post_id, '_thumbnail_id', $attachment_id );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'AI Blog Posts: Featured image set via direct database update for post %d', $post_id ) );
				}
			} else {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'AI Blog Posts: Featured image set successfully for post %d', $post_id ) );
				}
			}
		}

		// Add meta to track AI-generated images
		update_post_meta( $attachment_id, '_ai_blog_posts_generated', true );
		update_post_meta( $attachment_id, '_ai_blog_posts_generated_at', current_time( 'mysql' ) );

		return $attachment_id;
	}

	/**
	 * Fallback method to create attachment if media_handle_sideload fails.
	 * 
	 * @since    1.1.0
	 * @param    string $temp_file    Temporary file path.
	 * @param    string $filename     Desired filename.
	 * @param    int    $post_id      Post ID to attach to.
	 * @return   int|WP_Error         Attachment ID or error.
	 */
	private function create_attachment_fallback( $temp_file, $filename, $post_id = 0 ) {
		if ( ! file_exists( $temp_file ) ) {
			return new WP_Error( 'file_not_found', 'Temporary file not found' );
		}

		// Get upload directory
		$upload_dir = wp_upload_dir();
		if ( $upload_dir['error'] ) {
			return new WP_Error( 'upload_dir_error', $upload_dir['error'] );
		}

		// Generate unique filename
		$file_info = pathinfo( sanitize_file_name( $filename . '.png' ) );
		$filename = $file_info['filename'];
		$ext = isset( $file_info['extension'] ) ? $file_info['extension'] : 'png';
		
		$unique_filename = wp_unique_filename( $upload_dir['path'], $filename . '.' . $ext );
		$new_file = $upload_dir['path'] . '/' . $unique_filename;

		// Copy file to uploads directory
		if ( ! @copy( $temp_file, $new_file ) ) {
			return new WP_Error( 'file_copy_failed', 'Failed to copy file to uploads directory' );
		}

		// Set proper file permissions
		$stat = stat( dirname( $new_file ) );
		$perms = $stat['mode'] & 0000666;
		@chmod( $new_file, $perms );

		// Prepare attachment data
		$attachment = array(
			'post_mime_type' => 'image/png',
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Create attachment
		$attachment_id = wp_insert_attachment( $attachment, $new_file, $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $new_file );
			return $attachment_id;
		}

		// Generate attachment metadata (this might still be slow, but we'll try)
		require_once ABSPATH . 'wp-admin/includes/image.php';
		
		// Skip thumbnail generation entirely - it hangs on shared hosting
		// The attachment will work fine without thumbnails, WordPress will generate them on-demand
		// This prevents the process from hanging indefinitely
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: Skipping thumbnail generation to prevent hangs on shared hosting (attachment ID: %d)', $attachment_id ) );
		}
		
		// Set basic metadata without generating thumbnails
		// Get relative path from uploads directory
		$upload_dir = wp_upload_dir();
		$relative_path = str_replace( $upload_dir['basedir'] . '/', '', $new_file );
		
		$basic_metadata = array(
			'width'  => 0,
			'height' => 0,
			'file'   => $relative_path,
		);
		wp_update_attachment_metadata( $attachment_id, $basic_metadata );

		return $attachment_id;
	}

	/**
	 * Get available models from the API.
	 *
	 * @since    1.0.0
	 * @return   array|WP_Error    List of models or error.
	 */
	public function get_available_models() {
		$cached = get_transient( 'ai_blog_posts_models_list' );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->make_request( 'GET', '/models' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( isset( $response['data'] ) ) {
			$models = $this->filter_relevant_models( $response['data'] );
			set_transient( 'ai_blog_posts_models_list', $models, DAY_IN_SECONDS );
			return $models;
		}

		return array();
	}

	/**
	 * Configure cURL options for long-running API requests.
	 *
	 * This callback is attached to the 'http_api_curl' filter to ensure
	 * proper timeout settings are applied at the cURL level.
	 *
	 * @since    1.0.0
	 * @param    resource $handle     cURL handle.
	 * @param    array    $parsed_args Parsed request arguments.
	 * @param    string   $url         Request URL.
	 * @return   void
	 */
	public function configure_curl_for_openai( $handle, $parsed_args, $url ) {
		// Only apply to OpenAI API requests
		if ( strpos( $url, 'api.openai.com' ) === false ) {
			return;
		}

		$timeout = $this->current_request_timeout;

		// Set cURL timeout options explicitly
		curl_setopt( $handle, CURLOPT_TIMEOUT, $timeout );
		curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, 30 ); // 30 seconds to connect
		
		// Disable cURL's internal timeout for slow responses
		curl_setopt( $handle, CURLOPT_LOW_SPEED_LIMIT, 1 ); // 1 byte per second minimum
		curl_setopt( $handle, CURLOPT_LOW_SPEED_TIME, $timeout ); // For the duration of timeout
		
		// Ensure we get the full response
		curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: [cURL] Configured timeout=%ds, connect_timeout=30s for OpenAI request', $timeout ) );
		}
	}

	/**
	 * Make an API request.
	 *
	 * @since    1.0.0
	 * @param    string $method     HTTP method.
	 * @param    string $endpoint   API endpoint.
	 * @param    array  $body       Request body.
	 * @param    string $api_key    Optional API key override.
	 * @return   array|WP_Error     Response or error.
	 */
	private function make_request( $method, $endpoint, $body = array(), $api_key = null ) {
		$key = $api_key ?? $this->api_key;

		if ( empty( $key ) ) {
			return new WP_Error(
				'missing_api_key',
				__( 'OpenAI API key is not configured.', 'ai-blog-posts' )
			);
		}

		$headers = array(
			'Authorization' => 'Bearer ' . $key,
			'Content-Type'  => 'application/json',
		);

		if ( ! empty( $this->org_id ) ) {
			$headers['OpenAI-Organization'] = $this->org_id;
		}

		// Set appropriate timeout based on endpoint
		$timeout = self::TIMEOUT; // Default 300 seconds
		if ( '/chat/completions' === $endpoint ) {
			// GPT-5.2 can take longer, especially with reasoning tokens
			// Use 180 seconds (3 minutes) to ensure complete responses
			$timeout = 180;
		}

		// Store timeout for cURL callback and add the filter
		$this->current_request_timeout = $timeout;
		add_action( 'http_api_curl', array( $this, 'configure_curl_for_openai' ), 10, 3 );

		$args = array(
			'method'      => $method,
			'headers'     => $headers,
			'timeout'     => $timeout,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking'    => true,
			'sslverify'   => true,
			// Add user agent to help with connection issues
			'user-agent'  => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
			// Increase stream reading timeout for large responses
			'stream'      => false,
		);

		if ( ! empty( $body ) && 'GET' !== $method ) {
			$args['body'] = wp_json_encode( $body );
		}

		$url = self::API_BASE . $endpoint;

		// Retry logic
		$attempts = 0;
		$last_error = null;
		$last_http_code = null;
		$last_response_body = null;

		while ( $attempts < self::MAX_RETRIES ) {
			$attempts++;
			
			// Increment API call counter on first attempt only
			if ( 1 === $attempts ) {
				$this->increment_call_count( $endpoint );
			}
			
			// Log attempt for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'AI Blog Posts: API request attempt %d to %s (timeout: %ds, session calls: %d)', $attempts, $endpoint, $timeout, $this->api_call_count ) );
			}
			
			$request_start = microtime( true );
			
			// Enforce timeout for chat completions on shared hosting
			// wp_remote_request() may not respect timeouts on some shared hosts
			if ( '/chat/completions' === $endpoint ) {
				// Set max execution time to timeout + buffer
				$max_execution_time = $timeout + 10; // Add 10 second buffer
				@set_time_limit( $max_execution_time );
				
				// Store initial execution time limit for restoration
				$initial_time_limit = ini_get( 'max_execution_time' );
				
				// Make the request
				$response = wp_remote_request( $url, $args );
				$request_duration = microtime( true ) - $request_start;
				
				// Restore original time limit if it was set
				if ( $initial_time_limit ) {
					@set_time_limit( $initial_time_limit );
				}
				
				// Check if request took longer than timeout (indicates potential hang)
				// For chat completions, if it takes more than timeout + 10s buffer, treat as timeout
				$max_allowed_time = $timeout + 10;
				
				if ( $request_duration > $max_allowed_time ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( sprintf( 
							'AI Blog Posts: WARNING - Request to %s took %.2f seconds (timeout: %ds, max allowed: %ds), treating as timeout', 
							$endpoint, 
							$request_duration, 
							$timeout,
							$max_allowed_time
						) );
					}
					
					// Treat as timeout - return error immediately
					$last_error = new WP_Error( 
						'request_timeout', 
						sprintf( 
							__( 'Request to %s exceeded maximum allowed time (%.1f seconds). This may indicate a network issue or server overload. Please try again.', 'ai-blog-posts' ),
							$endpoint,
							$request_duration
						),
						array( 'duration' => $request_duration, 'timeout' => $timeout )
					);
					
					// Return error immediately
					remove_action( 'http_api_curl', array( $this, 'configure_curl_for_openai' ), 10 );
					return $last_error;
				}
				
				// If we got an error and it took too long, treat it as a timeout
				if ( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();
					if ( strpos( strtolower( $error_message ), 'timeout' ) === false && 
					     strpos( strtolower( $error_message ), 'timed out' ) === false ) {
						// If it took a long time, treat as timeout
						if ( $request_duration > ( $timeout * 0.8 ) ) {
							$last_error = new WP_Error( 
								'request_timeout', 
								sprintf( 
									__( 'Request to %s failed after %.1f seconds (likely timeout). Please try again.', 'ai-blog-posts' ),
									$endpoint,
									$request_duration
								)
							);
							if ( $attempts < self::MAX_RETRIES ) {
								sleep( pow( 2, $attempts ) );
								continue;
							}
							remove_action( 'http_api_curl', array( $this, 'configure_curl_for_openai' ), 10 );
							return $last_error;
						}
					}
				}
			} else {
				// For other endpoints, use standard request
				// Set execution time limit for the request
				@set_time_limit( $timeout + 10 );
				$response = wp_remote_request( $url, $args );
				$request_duration = microtime( true ) - $request_start;
			}
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'AI Blog Posts: API request completed in %.2f seconds', $request_duration ) );
			}

			if ( is_wp_error( $response ) ) {
				$last_error = $response;
				
				// Log the error
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'AI Blog Posts: WP_Error - ' . $response->get_error_message() );
				}
				
				// Don't retry on connection errors - break but keep the error
				if ( strpos( $response->get_error_message(), 'cURL' ) !== false ) {
					break;
				}
				
				// Wait before retry
				if ( $attempts < self::MAX_RETRIES ) {
					sleep( pow( 2, $attempts ) ); // Exponential backoff
				}
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			
			// Check response headers for content length (using array access, not get() method)
			$headers = wp_remote_retrieve_headers( $response );
			$content_length = isset( $headers['content-length'] ) ? $headers['content-length'] : null;
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'AI Blog Posts: [make_request] HTTP %d response from %s', $code, $endpoint ) );
				error_log( sprintf( 'AI Blog Posts: [make_request] Response body length: %d', strlen( $body ) ) );
				if ( $content_length ) {
					$expected_length = (int) $content_length;
					$actual_length = strlen( $body );
					if ( $actual_length < $expected_length ) {
						error_log( sprintf( 'AI Blog Posts: [make_request] WARNING - Response truncated! Expected: %d bytes, Got: %d bytes', $expected_length, $actual_length ) );
					}
				}
			}
			
			// Check if body is empty
			if ( empty( $body ) ) {
				$last_error = new WP_Error(
					'empty_response',
					__( 'Empty response body received from OpenAI API.', 'ai-blog-posts' ),
					array( 'http_code' => $code )
				);
				if ( $attempts < self::MAX_RETRIES ) {
					sleep( pow( 2, $attempts ) );
					continue;
				}
				return $last_error;
			}
			
			// Try to decode JSON
			$data = json_decode( $body, true );
			$json_error = json_last_error();
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				if ( $json_error !== JSON_ERROR_NONE ) {
					error_log( sprintf( 'AI Blog Posts: [make_request] JSON decode error: %d - %s', $json_error, json_last_error_msg() ) );
					error_log( sprintf( 'AI Blog Posts: [make_request] Response body length: %d', strlen( $body ) ) );
					error_log( sprintf( 'AI Blog Posts: [make_request] Response body (first 2000 chars): %s', substr( $body, 0, 2000 ) ) );
					if ( strlen( $body ) > 2000 ) {
						error_log( sprintf( 'AI Blog Posts: [make_request] Response body (last 500 chars): %s', substr( $body, -500 ) ) );
					}
				} else {
					error_log( sprintf( 'AI Blog Posts: [make_request] JSON decoded successfully, data keys: %s', implode( ', ', array_keys( $data ?? array() ) ) ) );
				}
			}

			$this->last_response = $data;
			$last_http_code = $code;
			$last_response_body = $body;

			// Log response for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'AI Blog Posts: HTTP %d response from %s', $code, $endpoint ) );
				if ( $code >= 400 ) {
					error_log( 'AI Blog Posts: Error response - ' . substr( $body, 0, 500 ) );
				}
			}
			
			// If JSON decode failed, return error with more details
			if ( $json_error !== JSON_ERROR_NONE ) {
				// Check if response might be truncated (common issue with large responses)
				$is_truncated = false;
				if ( strlen( $body ) > 0 ) {
					// Check if body ends abruptly (not with closing brace/bracket)
					$trimmed = trim( $body );
					$last_char = substr( $trimmed, -1 );
					if ( ! in_array( $last_char, array( '}', ']', '"' ), true ) ) {
						$is_truncated = true;
					}
				}
				
				$error_message = sprintf( 
					__( 'Response could not be parsed. JSON error: %s', 'ai-blog-posts' ), 
					json_last_error_msg() 
				);
				
				if ( $is_truncated ) {
					$error_message .= ' ' . __( 'Response appears to be truncated.', 'ai-blog-posts' );
				}
				
				$last_error = new WP_Error(
					'json_parse_error',
					$error_message,
					array( 
						'http_code'    => $code, 
						'body_length'  => strlen( $body ),
						'body_preview' => substr( $body, 0, 1000 ),
						'is_truncated' => $is_truncated,
					)
				);
				
				// For JSON parse errors, check if it's a timeout/truncation issue
				// If the request took close to the timeout, it might be truncated
				if ( $request_duration > ( $timeout * 0.9 ) && $attempts < self::MAX_RETRIES ) {
					// Likely a timeout/truncation - retry with fresh request
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( sprintf( 'AI Blog Posts: [make_request] JSON parse error after %.1fs (close to timeout %ds) - retrying...', $request_duration, $timeout ) );
					}
					sleep( pow( 2, $attempts ) );
					continue;
				}
				
				// Don't retry other JSON parse errors - they're usually permanent (invalid format)
				remove_action( 'http_api_curl', array( $this, 'configure_curl_for_openai' ), 10 );
				return $last_error;
			}

			// Success
			if ( $code >= 200 && $code < 300 ) {
				// Remove cURL filter before returning
				remove_action( 'http_api_curl', array( $this, 'configure_curl_for_openai' ), 10 );
				return $data;
			}

			// Rate limited or quota exceeded
			if ( 429 === $code ) {
				// Check if it's a quota issue (don't retry those)
				$error_type = $data['error']['type'] ?? '';
				$error_code = $data['error']['code'] ?? '';
				
				if ( 'insufficient_quota' === $error_code || 'insufficient_quota' === $error_type ) {
					remove_action( 'http_api_curl', array( $this, 'configure_curl_for_openai' ), 10 );
					return new WP_Error(
						'quota_exceeded',
						__( 'Your OpenAI API quota has been exceeded. Please add credits to your OpenAI account at https://platform.openai.com/account/billing', 'ai-blog-posts' )
					);
				}
				
				// Regular rate limiting - retry with backoff
				$retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
				$wait = $retry_after ? (int) $retry_after : pow( 2, $attempts );
				
				if ( $attempts < self::MAX_RETRIES ) {
					sleep( min( $wait, 60 ) ); // Max 60 second wait
				}
				continue;
			}

			// Server error - retry
			if ( $code >= 500 ) {
				$last_error = new WP_Error( 'server_error', sprintf( __( 'OpenAI server error (HTTP %d)', 'ai-blog-posts' ), $code ) );
				if ( $attempts < self::MAX_RETRIES ) {
					sleep( pow( 2, $attempts ) );
				}
				continue;
			}

			// Client error - don't retry, return specific error message
			$error_message = $data['error']['message'] ?? __( 'API request failed.', 'ai-blog-posts' );
			$this->last_error = $error_message;
			
			// Check for specific error types
			if ( 401 === $code ) {
				remove_action( 'http_api_curl', array( $this, 'configure_curl_for_openai' ), 10 );
				return new WP_Error( 'invalid_api_key', __( 'Invalid API key. Please check your API key in Settings.', 'ai-blog-posts' ) );
			}
			if ( 403 === $code ) {
				// Check if it's a GPT Image verification error
				$error_message = $data['error']['message'] ?? '';
				remove_action( 'http_api_curl', array( $this, 'configure_curl_for_openai' ), 10 );
				if ( strpos( $error_message, 'organization must be verified' ) !== false || strpos( $error_message, 'gpt-image' ) !== false ) {
					return new WP_Error( 
						'organization_not_verified', 
						__( 'GPT Image models require organization verification. Please verify your organization at https://platform.openai.com/settings/organization/general or try a different image model in Settings.', 'ai-blog-posts' ),
						array( 'original_message' => $error_message )
					);
				}
				return new WP_Error( 'access_denied', __( 'Access denied. Your API key may not have permission for this model.', 'ai-blog-posts' ) );
			}
			if ( 404 === $code ) {
				remove_action( 'http_api_curl', array( $this, 'configure_curl_for_openai' ), 10 );
				return new WP_Error( 'model_not_found', __( 'The selected model was not found. Please choose a different model.', 'ai-blog-posts' ) );
			}
			
			remove_action( 'http_api_curl', array( $this, 'configure_curl_for_openai' ), 10 );
			return new WP_Error( 'api_error', $error_message, array( 'status' => $code ) );
		}

		// Remove cURL filter now that requests are done
		remove_action( 'http_api_curl', array( $this, 'configure_curl_for_openai' ), 10 );

		// All retries exhausted
		if ( $last_error ) {
			$error_msg = $last_error->get_error_message();
			// Make error more helpful
			if ( strpos( $error_msg, 'cURL error 6' ) !== false ) {
				return new WP_Error( 'connection_error', __( 'Cannot connect to OpenAI. Please check your internet connection or server DNS settings.', 'ai-blog-posts' ) );
			}
			if ( strpos( $error_msg, 'cURL error 28' ) !== false || 
			     strpos( strtolower( $error_msg ), 'timed out' ) !== false ||
			     strpos( strtolower( $error_msg ), 'timeout' ) !== false ) {
				$timeout_message = __( 'Connection to OpenAI timed out. The servers may be busy or your server connection is slow. Please try again.', 'ai-blog-posts' );
				return new WP_Error( 'timeout_error', $timeout_message );
			}
			if ( strpos( $error_msg, 'cURL error 7' ) !== false ) {
				return new WP_Error( 'connection_refused', __( 'Connection to OpenAI was refused. Your server firewall may be blocking outbound HTTPS requests.', 'ai-blog-posts' ) );
			}
			if ( strpos( $error_msg, 'cURL error 35' ) !== false || strpos( $error_msg, 'cURL error 60' ) !== false ) {
				return new WP_Error( 'ssl_error', __( 'SSL certificate error connecting to OpenAI. Please contact your hosting provider.', 'ai-blog-posts' ) );
			}
			if ( strpos( $error_msg, 'cURL' ) !== false ) {
				return new WP_Error( 'curl_error', sprintf( __( 'Connection error: %s', 'ai-blog-posts' ), $error_msg ) );
			}
			return $last_error;
		}

		// No specific error - provide HTTP code if available
		if ( $last_http_code ) {
			return new WP_Error(
				'http_error',
				sprintf( __( 'OpenAI returned HTTP %d. Please try again or contact support.', 'ai-blog-posts' ), $last_http_code )
			);
		}

		return new WP_Error(
			'max_retries',
			__( 'API request failed after multiple attempts. Please check the debug log for details.', 'ai-blog-posts' )
		);
	}

	/**
	 * Calculate the cost of a text generation request.
	 *
	 * @since    1.0.0
	 * @param    string $model              The model used.
	 * @param    int    $prompt_tokens      Number of input tokens.
	 * @param    int    $completion_tokens  Number of output tokens.
	 * @return   float                      Cost in USD.
	 */
	public function calculate_text_cost( $model, $prompt_tokens, $completion_tokens ) {
		$models = Ai_Blog_Posts_Settings::get_models();

		if ( ! isset( $models[ $model ] ) ) {
			return 0.0;
		}

		$pricing = $models[ $model ];
		$input_cost = ( $prompt_tokens / 1000000 ) * $pricing['input_cost'];
		$output_cost = ( $completion_tokens / 1000000 ) * $pricing['output_cost'];

		return round( $input_cost + $output_cost, 6 );
	}

	/**
	 * REMOVED: Image generation cost calculation - using Pexels (free) instead.
	 * 
	 * @deprecated This method has been removed. Pexels images are free.
	 * @since    1.0.0
	 * @param    string $model    The image model used (deprecated).
	 * @param    string $size     The image size (deprecated).
	 * @param    string $quality  The image quality (deprecated).
	 * @return   float            Always returns 0 (Pexels is free).
	 */
	public function calculate_image_cost( $model, $size, $quality = 'auto' ) {
		// Pexels images are free
		return 0.0;
	}

	/**
	 * Filter API models to only relevant ones.
	 *
	 * GPT-5.2 model names may include version suffixes (e.g., "gpt-5.2-2025-12-11")
	 * We filter for any model that starts with "gpt-5.2"
	 *
	 * @since    1.0.0
	 * @param    array $models    All models from API.
	 * @return   array            Filtered models.
	 */
	private function filter_relevant_models( $models ) {
		// Only include GPT-5.2 (including versioned variants like gpt-5.2-2025-12-11)
		$filtered = array();

		foreach ( $models as $model ) {
			$id = $model['id'] ?? '';
			// Match gpt-5.2 and any versioned variants (e.g., gpt-5.2-2025-12-11)
			if ( strpos( $id, 'gpt-5.2' ) === 0 ) {
				$filtered[] = $id;
			}
		}

		return array_unique( $filtered );
	}

	/**
	 * Get the last error message.
	 *
	 * @since    1.0.0
	 * @return   string    The last error message.
	 */
	public function get_last_error() {
		return $this->last_error ?? '';
	}

	/**
	 * Get the last API response.
	 *
	 * @since    1.0.0
	 * @return   array    The last response.
	 */
	public function get_last_response() {
		return $this->last_response ?? array();
	}
}


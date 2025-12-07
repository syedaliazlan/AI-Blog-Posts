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
 * Provides methods for text generation (GPT) and image generation (DALL-E)
 * with built-in rate limiting, retry logic, and cost tracking.
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
	 * Initialize the class.
	 *
	 * @since    1.0.0
	 * @param    string $api_key    Optional API key override.
	 * @param    string $org_id     Optional organization ID override.
	 */
	public function __construct( $api_key = null, $org_id = null ) {
		$this->api_key = $api_key ?? Ai_Blog_Posts_Settings::get( 'api_key' );
		$this->org_id = $org_id ?? Ai_Blog_Posts_Settings::get( 'org_id' );
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
		
		if ( ! empty( $system_prompt ) ) {
			if ( $is_reasoning_model ) {
				// For reasoning models, prepend system prompt to user message
				$prompt = "Instructions: " . $system_prompt . "\n\n" . $prompt;
			} else {
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

		// Use max_completion_tokens for newer models, max_tokens for legacy
		if ( $this->uses_max_completion_tokens( $model ) ) {
			$body['max_completion_tokens'] = $max_tokens;
		} else {
			$body['max_tokens'] = $max_tokens;
		}

		// Only add temperature for models that support it
		if ( $this->supports_temperature( $model ) ) {
			$body['temperature'] = $temperature;
		}

		$start_time = microtime( true );
		$response = $this->make_request( 'POST', '/chat/completions', $body );
		$generation_time = microtime( true ) - $start_time;

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( isset( $response['error'] ) ) {
			return new WP_Error(
				'openai_error',
				$response['error']['message'] ?? __( 'Unknown API error.', 'ai-blog-posts' )
			);
		}

		$usage = $response['usage'] ?? array();
		$content = $response['choices'][0]['message']['content'] ?? '';

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
			'finish_reason'     => $response['choices'][0]['finish_reason'] ?? 'unknown',
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
		// All GPT-5.x, GPT-4.1.x, GPT-4o, o1, o3, o4 models use max_completion_tokens
		$new_model_prefixes = array(
			'gpt-5',    // GPT-5, GPT-5.1, GPT-5-mini, GPT-5-nano, GPT-5-pro
			'gpt-4.1',  // GPT-4.1, GPT-4.1-mini
			'gpt-4o',   // GPT-4o, GPT-4o-mini
			'o1',       // o1, o1-mini, o1-pro
			'o3',       // o3, o3-mini, o3-pro
			'o4',       // o4-mini
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
	 * @since    1.0.0
	 * @param    string $model    The model ID.
	 * @return   bool             True if supports custom temperature.
	 */
	private function supports_temperature( $model ) {
		// GPT-5 series and reasoning models only support default temperature (1)
		$no_temp_prefixes = array( 'gpt-5', 'o1', 'o3', 'o4' );

		foreach ( $no_temp_prefixes as $prefix ) {
			if ( strpos( $model, $prefix ) === 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Generate an image using DALL-E.
	 *
	 * @since    1.0.0
	 * @param    string $prompt    The image description prompt.
	 * @param    array  $options   Additional options.
	 * @return   array|WP_Error    Response array or error.
	 */
	public function generate_image( $prompt, $options = array() ) {
		$model = $options['model'] ?? Ai_Blog_Posts_Settings::get( 'image_model' );
		$size = $options['size'] ?? Ai_Blog_Posts_Settings::get( 'image_size' );
		$quality = $options['quality'] ?? 'standard';
		$style = $options['style'] ?? 'natural';

		$body = array(
			'model'           => $model,
			'prompt'          => $prompt,
			'n'               => 1,
			'size'            => $size,
			'response_format' => 'url',
		);

		// DALL-E 3 specific options
		if ( 'dall-e-3' === $model ) {
			$body['quality'] = $quality;
			$body['style'] = $style;
		}

		$start_time = microtime( true );
		$response = $this->make_request( 'POST', '/images/generations', $body );
		$generation_time = microtime( true ) - $start_time;

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( isset( $response['error'] ) ) {
			return new WP_Error(
				'openai_error',
				$response['error']['message'] ?? __( 'Unknown API error.', 'ai-blog-posts' )
			);
		}

		$image_url = $response['data'][0]['url'] ?? '';
		$revised_prompt = $response['data'][0]['revised_prompt'] ?? $prompt;

		// Calculate cost
		$cost = $this->calculate_image_cost( $model, $size, $quality );

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
	 * Download an image and add it to the media library.
	 *
	 * @since    1.0.0
	 * @param    string $url         The image URL.
	 * @param    string $filename    The desired filename.
	 * @param    int    $post_id     Optional post ID to attach to.
	 * @return   int|WP_Error        Attachment ID or error.
	 */
	public function download_image_to_media( $url, $filename, $post_id = 0 ) {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// Download file to temp location
		$temp_file = download_url( $url );

		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}

		// Prepare file array
		$file_array = array(
			'name'     => sanitize_file_name( $filename . '.png' ),
			'tmp_name' => $temp_file,
		);

		// Do the upload
		$attachment_id = media_handle_sideload( $file_array, $post_id );

		// Clean up temp file
		if ( file_exists( $temp_file ) ) {
			wp_delete_file( $temp_file );
		}

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Add meta to track AI-generated images
		update_post_meta( $attachment_id, '_ai_blog_posts_generated', true );
		update_post_meta( $attachment_id, '_ai_blog_posts_generated_at', current_time( 'mysql' ) );

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

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => self::TIMEOUT,
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
			
			// Log attempt for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'AI Blog Posts: API request attempt %d to %s', $attempts, $endpoint ) );
			}
			
			$response = wp_remote_request( $url, $args );

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
			$data = json_decode( $body, true );

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

			// Success
			if ( $code >= 200 && $code < 300 ) {
				return $data;
			}

			// Rate limited or quota exceeded
			if ( 429 === $code ) {
				// Check if it's a quota issue (don't retry those)
				$error_type = $data['error']['type'] ?? '';
				$error_code = $data['error']['code'] ?? '';
				
				if ( 'insufficient_quota' === $error_code || 'insufficient_quota' === $error_type ) {
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
				return new WP_Error( 'invalid_api_key', __( 'Invalid API key. Please check your API key in Settings.', 'ai-blog-posts' ) );
			}
			if ( 403 === $code ) {
				return new WP_Error( 'access_denied', __( 'Access denied. Your API key may not have permission for this model.', 'ai-blog-posts' ) );
			}
			if ( 404 === $code ) {
				return new WP_Error( 'model_not_found', __( 'The selected model was not found. Please choose a different model.', 'ai-blog-posts' ) );
			}
			
			return new WP_Error( 'api_error', $error_message, array( 'status' => $code ) );
		}

		// All retries exhausted
		if ( $last_error ) {
			$error_msg = $last_error->get_error_message();
			// Make error more helpful
			if ( strpos( $error_msg, 'cURL error 6' ) !== false ) {
				return new WP_Error( 'connection_error', __( 'Cannot connect to OpenAI. Please check your internet connection or server DNS settings.', 'ai-blog-posts' ) );
			}
			if ( strpos( $error_msg, 'cURL error 28' ) !== false ) {
				return new WP_Error( 'timeout_error', __( 'Connection to OpenAI timed out. The servers may be busy, please try again.', 'ai-blog-posts' ) );
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
	 * Calculate the cost of an image generation request.
	 *
	 * @since    1.0.0
	 * @param    string $model    The model used.
	 * @param    string $size     The image size.
	 * @param    string $quality  The image quality.
	 * @return   float            Cost in USD.
	 */
	public function calculate_image_cost( $model, $size, $quality = 'standard' ) {
		$models = Ai_Blog_Posts_Settings::get_image_models();

		if ( ! isset( $models[ $model ] ) ) {
			return 0.0;
		}

		$pricing = $models[ $model ]['pricing'];

		// DALL-E 3 HD quality doubles the price
		$multiplier = ( 'dall-e-3' === $model && 'hd' === $quality ) ? 2 : 1;

		return isset( $pricing[ $size ] ) ? $pricing[ $size ] * $multiplier : 0.0;
	}

	/**
	 * Filter API models to only relevant ones.
	 *
	 * @since    1.0.0
	 * @param    array $models    All models from API.
	 * @return   array            Filtered models.
	 */
	private function filter_relevant_models( $models ) {
		// Include all GPT-5, GPT-4, o-series and image models
		$relevant_prefixes = array(
			'gpt-5',      // GPT-5.x series
			'gpt-4',      // GPT-4.x series (including 4o, 4.1, 4-turbo)
			'gpt-3.5',    // Legacy
			'o1',         // o1 reasoning models
			'o3',         // o3 reasoning models
			'o4',         // o4 reasoning models
			'dall-e',     // Image models
		);
		$filtered = array();

		foreach ( $models as $model ) {
			$id = $model['id'] ?? '';
			foreach ( $relevant_prefixes as $prefix ) {
				if ( strpos( $id, $prefix ) === 0 ) {
					$filtered[] = $id;
					break;
				}
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


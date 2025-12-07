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
	private const TIMEOUT = 120;

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

		if ( ! empty( $system_prompt ) ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $system_prompt,
			);
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => $prompt,
		);

		$body = array(
			'model'       => $model,
			'messages'    => $messages,
			'max_tokens'  => $max_tokens,
			'temperature' => $temperature,
		);

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

		while ( $attempts < self::MAX_RETRIES ) {
			$attempts++;
			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				$last_error = $response;
				
				// Don't retry on connection errors
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

			// Success
			if ( $code >= 200 && $code < 300 ) {
				return $data;
			}

			// Rate limited - retry with backoff
			if ( 429 === $code ) {
				$retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
				$wait = $retry_after ? (int) $retry_after : pow( 2, $attempts );
				
				if ( $attempts < self::MAX_RETRIES ) {
					sleep( min( $wait, 60 ) ); // Max 60 second wait
				}
				continue;
			}

			// Server error - retry
			if ( $code >= 500 ) {
				if ( $attempts < self::MAX_RETRIES ) {
					sleep( pow( 2, $attempts ) );
				}
				continue;
			}

			// Client error - don't retry
			$error_message = $data['error']['message'] ?? __( 'API request failed.', 'ai-blog-posts' );
			$this->last_error = $error_message;
			
			return new WP_Error( 'api_error', $error_message, array( 'status' => $code ) );
		}

		// All retries exhausted
		if ( $last_error ) {
			return $last_error;
		}

		return new WP_Error(
			'max_retries',
			__( 'Maximum retry attempts reached.', 'ai-blog-posts' )
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
		$relevant = array( 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo', 'dall-e-3', 'dall-e-2' );
		$filtered = array();

		foreach ( $models as $model ) {
			$id = $model['id'] ?? '';
			foreach ( $relevant as $r ) {
				if ( strpos( $id, $r ) === 0 ) {
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


<?php
/**
 * Pexels API wrapper class
 *
 * @link       https://devonicweb.co.uk/
 * @since      1.0.0
 *
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 */

/**
 * Handles all Pexels API interactions.
 *
 * Provides methods for image searching, downloading, and scoring.
 *
 * @since      1.0.0
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 * @author     Ali Azlan <contact@devonicweb.co.uk>
 */
class Ai_Blog_Posts_Pexels {

	/**
	 * Pexels API base URL.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	private const API_BASE = 'https://api.pexels.com/v1';

	/**
	 * The Pexels API key.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private $api_key;

	/**
	 * Initialize the class.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->api_key = Ai_Blog_Posts_Settings::get( 'pexels_api_key' );
	}

	/**
	 * Verify the Pexels API key by making a test request.
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
				'message' => __( 'Pexels API key is empty.', 'ai-blog-posts' ),
			);
		}

		// Temporarily set the API key for verification
		$original_key = $this->api_key;
		$this->api_key = $key;

		// Test with a simple search request
		$result = $this->search_images( 'test', array( 'per_page' => 1 ) );

		// Restore original key
		$this->api_key = $original_key;

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Pexels API key verified successfully!', 'ai-blog-posts' ),
		);
	}

	/**
	 * Search for images on Pexels.
	 *
	 * @since    1.0.0
	 * @param    string $query      The search query.
	 * @param    array  $options    Search options (orientation, per_page).
	 * @return   array|WP_Error     Response array or error.
	 */
	public function search_images( $query, $options = array() ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error(
				'pexels_api_key_missing',
				__( 'Pexels API key is not configured.', 'ai-blog-posts' )
			);
		}

		$args = array(
			'query'       => urlencode( $query ),
			'orientation' => $options['orientation'] ?? 'landscape',
			'per_page'    => $options['per_page'] ?? 15, // Reduced from 80 to minimize API usage
		);

		$url = add_query_arg( $args, self::API_BASE . '/search' );

		$headers = array(
			'Authorization' => $this->api_key,
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: [Pexels] Searching for: %s (orientation: %s, per_page: %d)', $query, $args['orientation'], $args['per_page'] ) );
		}

		$start_time = microtime( true );
		$response = wp_remote_get( $url, array( 'headers' => $headers, 'timeout' => 30 ) );
		$duration = microtime( true ) - $start_time;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: [Pexels] Search completed in %.2f seconds', $duration ) );
		}

		if ( is_wp_error( $response ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'AI Blog Posts: [Pexels] Search error: %s', $response->get_error_message() ) );
			}
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 ) {
			// Extract error message from response
			$error_msg = __( 'Unknown error.', 'ai-blog-posts' );
			if ( is_array( $data ) ) {
				$error_msg = $data['error'] ?? ( isset( $data['message'] ) ? $data['message'] : $error_msg );
			} elseif ( is_string( $body ) && ! empty( $body ) ) {
				$error_msg = $body;
			}
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'AI Blog Posts: [Pexels] HTTP %d error: %s. Response: %s', $code, $error_msg, substr( $body, 0, 500 ) ) );
			}
			return new WP_Error( 'pexels_api_error', sprintf( __( 'Pexels API error (HTTP %d): %s', 'ai-blog-posts' ), $code, $error_msg ) );
		}

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'AI Blog Posts: [Pexels] JSON decode error: %s', json_last_error_msg() ) );
			}
			return new WP_Error( 'pexels_json_error', __( 'Failed to parse Pexels API response.', 'ai-blog-posts' ) );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: [Pexels] Found %d images', count( $data['photos'] ?? array() ) ) );
		}

		return $data;
	}

	/**
	 * Download an image from Pexels and add it to the WordPress media library.
	 *
	 * @since    1.0.0
	 * @param    string $image_url     The URL of the image to download (large size).
	 * @param    string $filename      The desired filename for the image.
	 * @param    int    $post_id       Optional. The post ID to attach the image to.
	 * @param    string $alt_text      Optional. The alt text for the image.
	 * @param    string $photographer  Optional. The photographer's name for attribution.
	 * @return   int|WP_Error          Attachment ID on success, WP_Error on failure.
	 */
	public function download_image( $image_url, $filename, $post_id = 0, $alt_text = '', $photographer = '' ) {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: [Pexels] Downloading image: %s', $image_url ) );
		}

		// Download file to temp location
		$temp_file = download_url( $image_url, 60 ); // 60 second timeout

		if ( is_wp_error( $temp_file ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'AI Blog Posts: [Pexels] Download failed: %s', $temp_file->get_error_message() ) );
			}
			return $temp_file;
		}

		// Get file type from Content-Type or URL
		$file_type = wp_check_filetype( basename( $image_url ), null );
		$mime_type = $file_type['type'];
		$extension = $file_type['ext'] ?: 'jpg';

		// Prepare file array
		$file_array = array(
			'name'     => sanitize_file_name( $filename . '.' . $extension ),
			'tmp_name' => $temp_file,
		);

		// Upload to media library
		$attachment_id = media_handle_sideload( $file_array, $post_id, $alt_text );

		// Clean up temp file
		@unlink( $temp_file );

		if ( is_wp_error( $attachment_id ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'AI Blog Posts: [Pexels] Media upload failed: %s', $attachment_id->get_error_message() ) );
			}
			return $attachment_id;
		}

		// Set alt text
		if ( ! empty( $alt_text ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		}

		// Store Pexels attribution
		if ( ! empty( $photographer ) ) {
			update_post_meta( $attachment_id, '_ai_blog_posts_pexels_photographer', $photographer );
		}
		update_post_meta( $attachment_id, '_ai_blog_posts_pexels_url', $image_url );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: [Pexels] Image uploaded successfully, attachment ID: %d', $attachment_id ) );
		}

		return $attachment_id;
	}

	/**
	 * Selects a featured image based on the topic and title.
	 *
	 * @since    1.0.0
	 * @param    string $topic    The post topic.
	 * @param    string $title    The post title.
	 * @return   array|WP_Error   Selected image data or error.
	 */
	public function select_featured_image( $topic, $title ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AI Blog Posts: [Pexels] Selecting featured image for topic: %s, title: %s', $topic, $title ) );
		}

		$search_queries = array();
		$search_queries[] = $title; // Primary search query
		
		// Extract keywords from topic and title for additional searches
		$keywords = array_unique( array_merge(
			$this->extract_keywords_from_text( $topic ),
			$this->extract_keywords_from_text( $title )
		) );

		// Add keyword combinations as search queries
		foreach ( $keywords as $keyword ) {
			$search_queries[] = $keyword;
		}
		if ( count( $keywords ) >= 2 ) {
			$search_queries[] = implode( ' ', array_slice( $keywords, 0, 2 ) );
		}

		$all_images = array();
		$used_queries = array();

		foreach ( array_unique( $search_queries ) as $query ) {
			if ( empty( $query ) || in_array( strtolower( $query ), $used_queries, true ) ) {
				continue;
			}
			$used_queries[] = strtolower( $query );

			// Use per_page 15 for featured image searches to reduce API calls while still getting good results
			$result = $this->search_images( $query, array( 'orientation' => 'landscape', 'per_page' => 15 ) );
			if ( ! is_wp_error( $result ) && ! empty( $result['photos'] ) ) {
				$all_images = array_merge( $all_images, $result['photos'] );
			}
		}

		if ( empty( $all_images ) ) {
			return new WP_Error( 'no_featured_images', __( 'Could not find suitable featured images on Pexels.', 'ai-blog-posts' ) );
		}

		// Score images
		$scored_images = $this->score_images( $all_images, $keywords, $topic . ' ' . $title );

		// Select from top 5
		if ( ! empty( $scored_images ) ) {
			$selected = $scored_images[0]['image'];
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'AI Blog Posts: [Pexels] Selected featured image with score: %d', $scored_images[0]['score'] ) );
			}
			return $selected; // Return the highest-scoring image
		}

		return new WP_Error( 'no_featured_images', __( 'Could not find suitable featured images on Pexels after scoring.', 'ai-blog-posts' ) );
	}

	/**
	 * Score images based on keyword and context matching.
	 *
	 * @since    1.0.0
	 * @param    array  $images     Array of image data from Pexels.
	 * @param    array  $keywords   Keywords to match.
	 * @param    string $context    Additional context for scoring.
	 * @return   array              Sorted array of images with scores.
	 */
	public function score_images( $images, $keywords, $context ) {
		$scored = array();
		$context_lower = strtolower( $context );

		foreach ( $images as $image ) {
			$score = 0;
			$image_alt_lower = strtolower( $image['alt'] ?? '' );
			$image_photographer_lower = strtolower( $image['photographer'] ?? '' );

			foreach ( $keywords as $keyword ) {
				// Exact keyword match in alt text (highest)
				if ( strpos( $image_alt_lower, $keyword ) !== false ) {
					$score += 5;
				}
				// Keyword in context (medium)
				if ( strpos( $context_lower, $keyword ) !== false ) {
					$score += 2;
				}
			}
			// General descriptive alt text
			if ( ! empty( $image_alt_lower ) && strlen( $image_alt_lower ) > 10 ) {
				$score += 1;
			}

			$scored[] = array( 'image' => $image, 'score' => $score );
		}

		// Sort by score descending
		usort( $scored, function( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );

		return $scored;
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
}

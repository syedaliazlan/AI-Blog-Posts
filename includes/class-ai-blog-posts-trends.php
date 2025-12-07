<?php

/**
 * Trending topics fetcher
 *
 * @link       https://devonicweb.co.uk/
 * @since      1.0.0
 *
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 */

/**
 * Fetches trending topics from Google Trends.
 *
 * @since      1.0.0
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 * @author     Ali Azlan <contact@devonicweb.co.uk>
 */
class Ai_Blog_Posts_Trends {

	/**
	 * Google Trends RSS URL.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	private const TRENDS_RSS_URL = 'https://trends.google.com/trends/trendingsearches/daily/rss';

	/**
	 * Cache expiration in seconds.
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	private const CACHE_EXPIRATION = 6 * HOUR_IN_SECONDS;

	/**
	 * Fetch trending topics.
	 *
	 * @since    1.0.0
	 * @param    bool $force_refresh    Skip cache.
	 * @return   array|WP_Error         Topics array or error.
	 */
	public function fetch_trending( $force_refresh = false ) {
		// Check cache first
		if ( ! $force_refresh ) {
			$cached = get_transient( 'ai_blog_posts_trending_topics' );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$country = Ai_Blog_Posts_Settings::get( 'trending_country' );
		$url = add_query_arg( 'geo', $country, self::TRENDS_RSS_URL );

		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array(
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'fetch_failed',
				sprintf( __( 'Failed to fetch trends. HTTP code: %d', 'ai-blog-posts' ), $code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$topics = $this->parse_rss( $body );

		if ( empty( $topics ) ) {
			return new WP_Error(
				'parse_failed',
				__( 'No trending topics found or failed to parse response.', 'ai-blog-posts' )
			);
		}

		// Cache the results
		set_transient( 'ai_blog_posts_trending_topics', $topics, self::CACHE_EXPIRATION );

		return $topics;
	}

	/**
	 * Parse RSS feed.
	 *
	 * @since    1.0.0
	 * @param    string $xml    XML content.
	 * @return   array          Parsed topics.
	 */
	private function parse_rss( $xml ) {
		$topics = array();

		// Suppress XML errors
		libxml_use_internal_errors( true );
		
		$doc = simplexml_load_string( $xml );
		
		if ( false === $doc ) {
			return $topics;
		}

		// Register namespace
		$namespaces = $doc->getNamespaces( true );
		
		foreach ( $doc->channel->item as $item ) {
			$title = (string) $item->title;
			
			if ( empty( $title ) ) {
				continue;
			}

			// Get traffic volume if available
			$traffic = '';
			if ( isset( $namespaces['ht'] ) ) {
				$ht = $item->children( $namespaces['ht'] );
				$traffic = isset( $ht->approx_traffic ) ? (string) $ht->approx_traffic : '';
			}

			// Get news items if available
			$news_items = array();
			if ( isset( $namespaces['ht'] ) ) {
				$ht = $item->children( $namespaces['ht'] );
				if ( isset( $ht->news_item ) ) {
					foreach ( $ht->news_item as $news ) {
						$news_items[] = array(
							'title'  => (string) $news->news_item_title,
							'url'    => (string) $news->news_item_url,
							'source' => (string) $news->news_item_source,
						);
					}
				}
			}

			$topics[] = array(
				'title'       => $title,
				'traffic'     => $traffic,
				'link'        => (string) $item->link,
				'pub_date'    => (string) $item->pubDate,
				'news_items'  => $news_items,
			);
		}

		return $topics;
	}

	/**
	 * Add trending topics to the queue.
	 *
	 * @since    1.0.0
	 * @param    array $topics      Topics to add.
	 * @param    int   $category_id Optional category ID.
	 * @return   int                Number of topics added.
	 */
	public function add_to_queue( $topics, $category_id = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ai_blog_posts_topics';

		$added = 0;

		foreach ( $topics as $topic ) {
			$title = is_array( $topic ) ? $topic['title'] : $topic;

			// Check if topic already exists in queue
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $table WHERE topic = %s AND status = 'pending'",
					$title
				)
			);

			if ( $exists > 0 ) {
				continue;
			}

			$inserted = $wpdb->insert(
				$table,
				array(
					'topic'       => $title,
					'keywords'    => '',
					'category_id' => $category_id,
					'source'      => 'trending',
					'status'      => 'pending',
					'priority'    => 50, // Medium priority for trending
					'created_at'  => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%d', '%s', '%s', '%d', '%s' )
			);

			if ( $inserted ) {
				$added++;
			}
		}

		return $added;
	}

	/**
	 * Filter topics by category relevance.
	 *
	 * Uses AI to determine which trending topics are relevant to selected categories.
	 *
	 * @since    1.0.0
	 * @param    array $topics        Trending topics.
	 * @param    array $category_ids  Category IDs to match against.
	 * @return   array                Filtered topics.
	 */
	public function filter_by_category( $topics, $category_ids ) {
		if ( empty( $category_ids ) || empty( $topics ) ) {
			return $topics;
		}

		// Get category names
		$category_names = array();
		foreach ( $category_ids as $id ) {
			$cat = get_category( $id );
			if ( $cat ) {
				$category_names[] = $cat->name;
			}
		}

		if ( empty( $category_names ) ) {
			return $topics;
		}

		// Use AI to filter
		$openai = new Ai_Blog_Posts_OpenAI();
		
		if ( ! Ai_Blog_Posts_Settings::is_verified() ) {
			return $topics;
		}

		$topic_titles = array_column( $topics, 'title' );
		
		$prompt = sprintf(
			"Given these blog categories: %s\n\n" .
			"Filter the following trending topics and return ONLY the ones that could be relevant to write about in these categories.\n\n" .
			"Topics:\n%s\n\n" .
			"Return the relevant topics as a JSON array of strings. Only include topics that have a clear connection to the categories.",
			implode( ', ', $category_names ),
			implode( "\n", array_map( function( $i, $t ) { return ($i + 1) . ". " . $t; }, array_keys( $topic_titles ), $topic_titles ) )
		);

		$result = $openai->generate_text( $prompt, 'You are a content strategist. Return only valid JSON.', array(
			'max_tokens'  => 500,
			'temperature' => 0.3,
		) );

		if ( is_wp_error( $result ) ) {
			return $topics; // Return all if filtering fails
		}

		// Parse response
		$content = $result['content'];
		if ( preg_match( '/\[.*\]/s', $content, $matches ) ) {
			$relevant = json_decode( $matches[0], true );
			if ( is_array( $relevant ) ) {
				// Filter original topics
				return array_filter( $topics, function( $topic ) use ( $relevant ) {
					$title = is_array( $topic ) ? $topic['title'] : $topic;
					return in_array( $title, $relevant, true );
				} );
			}
		}

		return $topics;
	}

	/**
	 * Refresh trending topics via cron.
	 *
	 * @since    1.0.0
	 */
	public function cron_refresh() {
		if ( ! Ai_Blog_Posts_Settings::get( 'trending_enabled' ) ) {
			return;
		}

		$topics = $this->fetch_trending( true );

		if ( is_wp_error( $topics ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[AI Blog Posts] Trending refresh failed: ' . $topics->get_error_message() );
			}
			return;
		}

		// If category filtering is needed
		$categories = Ai_Blog_Posts_Settings::get( 'categories' );
		if ( ! empty( $categories ) ) {
			$topics = $this->filter_by_category( $topics, $categories );
		}

		// Limit to top 5 trending topics
		$topics = array_slice( $topics, 0, 5 );

		// Add to queue
		$added = $this->add_to_queue( $topics );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[AI Blog Posts] Added ' . $added . ' trending topics to queue.' );
		}
	}

	/**
	 * Get cached trending topics.
	 *
	 * @since    1.0.0
	 * @return   array|false    Cached topics or false.
	 */
	public function get_cached() {
		return get_transient( 'ai_blog_posts_trending_topics' );
	}

	/**
	 * Clear cached trending topics.
	 *
	 * @since    1.0.0
	 */
	public function clear_cache() {
		delete_transient( 'ai_blog_posts_trending_topics' );
	}

	/**
	 * Get available countries.
	 *
	 * @since    1.0.0
	 * @return   array    Country code => name.
	 */
	public static function get_countries() {
		return array(
			'US' => 'United States',
			'GB' => 'United Kingdom',
			'CA' => 'Canada',
			'AU' => 'Australia',
			'DE' => 'Germany',
			'FR' => 'France',
			'IN' => 'India',
			'JP' => 'Japan',
			'BR' => 'Brazil',
			'MX' => 'Mexico',
			'ES' => 'Spain',
			'IT' => 'Italy',
			'NL' => 'Netherlands',
			'PL' => 'Poland',
			'SE' => 'Sweden',
			'NO' => 'Norway',
			'DK' => 'Denmark',
			'FI' => 'Finland',
			'BE' => 'Belgium',
			'AT' => 'Austria',
			'CH' => 'Switzerland',
			'IE' => 'Ireland',
			'NZ' => 'New Zealand',
			'SG' => 'Singapore',
			'HK' => 'Hong Kong',
			'KR' => 'South Korea',
			'TW' => 'Taiwan',
			'ID' => 'Indonesia',
			'TH' => 'Thailand',
			'MY' => 'Malaysia',
			'PH' => 'Philippines',
			'VN' => 'Vietnam',
			'ZA' => 'South Africa',
			'AE' => 'United Arab Emirates',
			'SA' => 'Saudi Arabia',
			'EG' => 'Egypt',
			'NG' => 'Nigeria',
			'KE' => 'Kenya',
			'AR' => 'Argentina',
			'CL' => 'Chile',
			'CO' => 'Colombia',
			'PE' => 'Peru',
		);
	}
}


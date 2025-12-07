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
	 * Google Trends JSON API URL.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	private const TRENDS_API_URL = 'https://trends.google.com/trends/api/dailytrends';

	/**
	 * Cache expiration in seconds (1 hour).
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	private const CACHE_EXPIRATION = HOUR_IN_SECONDS;

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
		$topics = null;
		
		// Try Google Trends JSON API first
		$google_result = $this->fetch_from_google_trends( $country );
		if ( ! is_wp_error( $google_result ) && ! empty( $google_result ) ) {
			$topics = $google_result;
		}
		
		// If Google Trends fails, try AI to generate relevant topics
		if ( empty( $topics ) && Ai_Blog_Posts_Settings::is_verified() ) {
			$ai_result = $this->generate_trending_with_ai( $country );
			if ( ! is_wp_error( $ai_result ) && ! empty( $ai_result ) ) {
				$topics = $ai_result;
			}
		}

		// Ultimate fallback: use curated evergreen topics
		if ( empty( $topics ) ) {
			$topics = $this->get_fallback_topics( $country );
		}

		// Cache the results
		set_transient( 'ai_blog_posts_trending_topics', $topics, self::CACHE_EXPIRATION );

		return $topics;
	}

	/**
	 * Get fallback curated topics when external APIs fail.
	 *
	 * @since    1.0.0
	 * @param    string $country    Country code.
	 * @return   array              Curated topics.
	 */
	private function get_fallback_topics( $country ) {
		// Get user's configured categories for context
		$categories = Ai_Blog_Posts_Settings::get( 'categories' );
		$category_names = array();
		
		if ( ! empty( $categories ) ) {
			foreach ( $categories as $cat_id ) {
				$cat = get_category( $cat_id );
				if ( $cat ) {
					$category_names[] = strtolower( $cat->name );
				}
			}
		}

		// Curated evergreen topics by category
		$topic_bank = array(
			'technology' => array(
				'AI Tools That Will Transform Your Workflow in 2024',
				'Cybersecurity Best Practices for Small Businesses',
				'How to Choose the Right Cloud Service Provider',
				'The Future of Remote Work Technology',
				'Automation Tools Every Business Should Consider',
			),
			'business' => array(
				'Strategies for Sustainable Business Growth',
				'How to Build a Strong Company Culture Remotely',
				'Financial Planning Tips for Entrepreneurs',
				'Customer Retention Strategies That Actually Work',
				'How to Scale Your Business Without Losing Quality',
			),
			'marketing' => array(
				'Content Marketing Trends You Need to Know',
				'How to Create a Social Media Strategy That Converts',
				'Email Marketing Best Practices for Higher Open Rates',
				'SEO Strategies for Small Businesses',
				'Building Brand Authority Through Thought Leadership',
			),
			'health' => array(
				'Mental Health Tips for a Balanced Life',
				'How to Build Sustainable Healthy Habits',
				'The Science of Better Sleep',
				'Nutrition Myths You Should Stop Believing',
				'Stress Management Techniques for Busy Professionals',
			),
			'lifestyle' => array(
				'Minimalism: How to Declutter Your Life',
				'Work-Life Balance Strategies That Actually Work',
				'How to Build Better Daily Routines',
				'Travel Tips for the Modern Explorer',
				'Sustainable Living: Small Changes Big Impact',
			),
			'finance' => array(
				'Investment Strategies for Beginners',
				'How to Build an Emergency Fund',
				'Understanding Cryptocurrency for Beginners',
				'Tax Planning Tips for Small Business Owners',
				'Retirement Planning: Starting in Your 30s vs 40s',
			),
			'education' => array(
				'Online Learning Platforms: A Comprehensive Guide',
				'How to Develop a Growth Mindset',
				'The Most In-Demand Skills for the Modern Workforce',
				'Effective Study Techniques Backed by Science',
				'How to Choose the Right Online Course',
			),
			'general' => array(
				'Productivity Hacks for Getting More Done',
				'How to Set and Achieve Your Goals',
				'Building Better Habits: A Step-by-Step Guide',
				'Time Management Strategies for Busy People',
				'How to Stay Motivated When Working on Long Projects',
				'The Art of Effective Communication',
				'Problem-Solving Techniques for Any Challenge',
				'How to Network Effectively in the Digital Age',
				'Personal Branding Tips for Professionals',
				'Leadership Lessons for Emerging Managers',
			),
		);

		// Build topics array
		$topics = array();
		$added = array();

		// First, add topics matching user's categories
		foreach ( $category_names as $cat_name ) {
			foreach ( $topic_bank as $category => $cat_topics ) {
				if ( strpos( $cat_name, $category ) !== false || strpos( $category, $cat_name ) !== false ) {
					foreach ( $cat_topics as $topic ) {
						if ( ! in_array( $topic, $added, true ) ) {
							$topics[] = array(
								'title'   => $topic,
								'traffic' => '10K+',
								'link'    => '',
								'source'  => 'curated',
							);
							$added[] = $topic;
						}
					}
				}
			}
		}

		// Fill remaining with general topics
		foreach ( $topic_bank['general'] as $topic ) {
			if ( count( $topics ) >= 15 ) {
				break;
			}
			if ( ! in_array( $topic, $added, true ) ) {
				$topics[] = array(
					'title'   => $topic,
					'traffic' => '10K+',
					'link'    => '',
					'source'  => 'curated',
				);
				$added[] = $topic;
			}
		}

		// Add some from other categories if we still need more
		if ( count( $topics ) < 10 ) {
			foreach ( $topic_bank as $category => $cat_topics ) {
				if ( 'general' === $category ) {
					continue;
				}
				foreach ( $cat_topics as $topic ) {
					if ( count( $topics ) >= 15 ) {
						break 2;
					}
					if ( ! in_array( $topic, $added, true ) ) {
						$topics[] = array(
							'title'   => $topic,
							'traffic' => '5K+',
							'link'    => '',
							'source'  => 'curated',
						);
						$added[] = $topic;
					}
				}
			}
		}

		// Shuffle to add variety
		shuffle( $topics );

		return array_slice( $topics, 0, 12 );
	}

	/**
	 * Fetch from Google Trends JSON API.
	 *
	 * @since    1.0.0
	 * @param    string $country    Country code.
	 * @return   array|WP_Error     Topics or error.
	 */
	private function fetch_from_google_trends( $country ) {
		$url = add_query_arg( array(
			'hl'  => 'en-' . $country,
			'tz'  => '-480',
			'geo' => $country,
			'ns'  => '15',
		), self::TRENDS_API_URL );

		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array(
				'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
				'Accept'     => 'application/json',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'fetch_failed',
				sprintf( __( 'Google Trends returned HTTP %d. Using AI fallback.', 'ai-blog-posts' ), $code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		return $this->parse_google_trends_json( $body );
	}

	/**
	 * Parse Google Trends JSON response.
	 *
	 * @since    1.0.0
	 * @param    string $body    Response body.
	 * @return   array           Parsed topics.
	 */
	private function parse_google_trends_json( $body ) {
		$topics = array();

		// Google prefixes with ")]}',\n" - remove it
		$body = preg_replace( '/^\)\]\}\',?\s*/', '', $body );
		
		$data = json_decode( $body, true );
		
		if ( ! $data || ! isset( $data['default']['trendingSearchesDays'] ) ) {
			return $topics;
		}

		foreach ( $data['default']['trendingSearchesDays'] as $day ) {
			if ( ! isset( $day['trendingSearches'] ) ) {
				continue;
			}

			foreach ( $day['trendingSearches'] as $search ) {
				$title = $search['title']['query'] ?? '';
				
				if ( empty( $title ) ) {
					continue;
				}

				$traffic = $search['formattedTraffic'] ?? '';
				
				// Get related articles
				$articles = array();
				if ( isset( $search['articles'] ) ) {
					foreach ( array_slice( $search['articles'], 0, 2 ) as $article ) {
						$articles[] = array(
							'title'  => $article['title'] ?? '',
							'url'    => $article['url'] ?? '',
							'source' => $article['source'] ?? '',
						);
					}
				}

				$topics[] = array(
					'title'       => $title,
					'traffic'     => $traffic,
					'link'        => 'https://trends.google.com/trends/explore?q=' . urlencode( $title ),
					'articles'    => $articles,
				);
			}
		}

		return array_slice( $topics, 0, 20 ); // Limit to 20 topics
	}

	/**
	 * Generate trending topics using AI as fallback.
	 *
	 * @since    1.0.0
	 * @param    string $country    Country code.
	 * @return   array|WP_Error     Topics or error.
	 */
	private function generate_trending_with_ai( $country ) {
		if ( ! Ai_Blog_Posts_Settings::is_verified() ) {
			return new WP_Error(
				'api_not_configured',
				__( 'Google Trends is unavailable and API key is not configured for AI fallback.', 'ai-blog-posts' )
			);
		}

		$openai = new Ai_Blog_Posts_OpenAI();
		
		$country_names = self::get_countries();
		$country_name = $country_names[ $country ] ?? 'United States';

		// Get user's categories for context
		$categories = Ai_Blog_Posts_Settings::get( 'categories' );
		$category_context = '';
		if ( ! empty( $categories ) ) {
			$cat_names = array();
			foreach ( $categories as $cat_id ) {
				$cat = get_category( $cat_id );
				if ( $cat ) {
					$cat_names[] = $cat->name;
				}
			}
			if ( ! empty( $cat_names ) ) {
				$category_context = ' Focus on topics related to: ' . implode( ', ', $cat_names ) . '.';
			}
		}

		$prompt = sprintf(
			"Generate 10 currently trending blog post topics that would be popular in %s right now. " .
			"Consider current events, seasonal topics, and popular interests.%s\n\n" .
			"Return ONLY a JSON array of objects with 'title' and 'traffic' (estimated search interest like '100K+', '50K+', etc.) keys.\n" .
			"Example: [{\"title\": \"Topic Name\", \"traffic\": \"100K+\"}]\n\n" .
			"Make the topics specific and actionable for blog posts, not just keywords.",
			$country_name,
			$category_context
		);

		$result = $openai->generate_text( $prompt, 'You are a content strategist who tracks trending topics. Return only valid JSON.', array(
			'max_tokens'  => 800,
			'temperature' => 0.7,
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Parse JSON from response
		$content = $result['content'];
		if ( preg_match( '/\[[\s\S]*\]/', $content, $matches ) ) {
			$topics = json_decode( $matches[0], true );
			if ( is_array( $topics ) ) {
				// Format to match expected structure
				return array_map( function( $topic ) {
					return array(
						'title'    => $topic['title'] ?? $topic,
						'traffic'  => $topic['traffic'] ?? '10K+',
						'link'     => '',
						'source'   => 'ai_generated',
						'articles' => array(),
					);
				}, $topics );
			}
		}

		return new WP_Error(
			'parse_failed',
			__( 'Failed to parse AI-generated topics.', 'ai-blog-posts' )
		);
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


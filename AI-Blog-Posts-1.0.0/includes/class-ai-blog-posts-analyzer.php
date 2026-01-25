<?php

/**
 * Website content analyzer
 *
 * @link       https://devonicweb.co.uk/
 * @since      1.0.0
 *
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 */

/**
 * Analyzes existing website content to extract writing style and patterns.
 *
 * Helps the AI match the website's tone and style when generating content.
 *
 * @since      1.0.0
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 * @author     Ali Azlan <contact@devonicweb.co.uk>
 */
class Ai_Blog_Posts_Analyzer {

	/**
	 * Number of posts to analyze.
	 *
	 * @since    1.0.0
	 * @var      int
	 */
	private const SAMPLE_SIZE = 10;

	/**
	 * Analyze the website's existing content.
	 *
	 * @since    1.0.0
	 * @param    bool $use_ai    Whether to use AI for deeper analysis.
	 * @return   array|WP_Error  Analysis results or error.
	 */
	public function analyze( $use_ai = false ) {
		// Get recent published posts
		$posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => self::SAMPLE_SIZE,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		if ( empty( $posts ) ) {
			return new WP_Error(
				'no_content',
				__( 'No published posts found to analyze.', 'ai-blog-posts' )
			);
		}

		$analysis = array(
			'site_info'        => $this->get_site_info(),
			'content_stats'    => $this->analyze_content_stats( $posts ),
			'writing_style'    => $this->analyze_writing_style( $posts ),
			'topics'           => $this->extract_topics( $posts ),
			'structure'        => $this->analyze_structure( $posts ),
			'analyzed_at'      => current_time( 'mysql' ),
			'posts_analyzed'   => count( $posts ),
		);

		// Use AI for deeper style analysis if requested and API is available
		if ( $use_ai && Ai_Blog_Posts_Settings::is_verified() ) {
			$ai_analysis = $this->ai_style_analysis( $posts );
			if ( ! is_wp_error( $ai_analysis ) ) {
				$analysis['ai_insights'] = $ai_analysis;
			}
		}

		// Cache the analysis
		Ai_Blog_Posts_Settings::set( 'website_context', wp_json_encode( $analysis ) );
		Ai_Blog_Posts_Settings::set( 'last_analysis', current_time( 'mysql' ) );

		return $analysis;
	}

	/**
	 * Get basic site information.
	 *
	 * @since    1.0.0
	 * @return   array    Site info.
	 */
	private function get_site_info() {
		return array(
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'url'         => home_url(),
			'language'    => get_bloginfo( 'language' ),
			'categories'  => $this->get_category_summary(),
		);
	}

	/**
	 * Get category summary.
	 *
	 * @since    1.0.0
	 * @return   array    Categories with post counts.
	 */
	private function get_category_summary() {
		$categories = get_categories( array(
			'orderby' => 'count',
			'order'   => 'DESC',
			'number'  => 10,
		) );

		$summary = array();
		foreach ( $categories as $cat ) {
			$summary[] = array(
				'name'  => $cat->name,
				'slug'  => $cat->slug,
				'count' => $cat->count,
			);
		}

		return $summary;
	}

	/**
	 * Analyze content statistics.
	 *
	 * @since    1.0.0
	 * @param    array $posts    Posts to analyze.
	 * @return   array           Content stats.
	 */
	private function analyze_content_stats( $posts ) {
		$word_counts = array();
		$paragraph_counts = array();
		$heading_counts = array();

		foreach ( $posts as $post ) {
			$content = wp_strip_all_tags( $post->post_content );
			
			// Word count
			$word_counts[] = str_word_count( $content );

			// Paragraph count
			$paragraphs = preg_split( '/\n\s*\n/', $post->post_content );
			$paragraph_counts[] = count( array_filter( $paragraphs ) );

			// Heading count
			preg_match_all( '/<h[1-6][^>]*>/i', $post->post_content, $headings );
			$heading_counts[] = count( $headings[0] );
		}

		return array(
			'avg_word_count'      => round( array_sum( $word_counts ) / count( $word_counts ) ),
			'min_word_count'      => min( $word_counts ),
			'max_word_count'      => max( $word_counts ),
			'avg_paragraphs'      => round( array_sum( $paragraph_counts ) / count( $paragraph_counts ) ),
			'avg_headings'        => round( array_sum( $heading_counts ) / count( $heading_counts ) ),
		);
	}

	/**
	 * Analyze writing style patterns.
	 *
	 * @since    1.0.0
	 * @param    array $posts    Posts to analyze.
	 * @return   array           Style analysis.
	 */
	private function analyze_writing_style( $posts ) {
		$all_content = '';
		$sentence_lengths = array();
		$question_count = 0;
		$exclamation_count = 0;

		foreach ( $posts as $post ) {
			$content = wp_strip_all_tags( $post->post_content );
			$all_content .= ' ' . $content;

			// Sentence analysis
			$sentences = preg_split( '/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY );
			foreach ( $sentences as $sentence ) {
				$sentence_lengths[] = str_word_count( trim( $sentence ) );
			}

			// Count questions and exclamations
			$question_count += substr_count( $content, '?' );
			$exclamation_count += substr_count( $content, '!' );
		}

		// Calculate formality indicators
		$formal_words = array( 'therefore', 'however', 'consequently', 'furthermore', 'moreover', 'nevertheless' );
		$informal_words = array( 'gonna', 'wanna', 'kinda', 'gotta', 'awesome', 'cool', 'amazing' );
		
		$formal_count = 0;
		$informal_count = 0;
		$lower_content = strtolower( $all_content );

		foreach ( $formal_words as $word ) {
			$formal_count += substr_count( $lower_content, $word );
		}
		foreach ( $informal_words as $word ) {
			$informal_count += substr_count( $lower_content, $word );
		}

		// Determine tone
		$tone = 'neutral';
		if ( $formal_count > $informal_count * 2 ) {
			$tone = 'formal';
		} elseif ( $informal_count > $formal_count * 2 ) {
			$tone = 'casual';
		}

		// Check for first person usage
		$first_person = preg_match_all( '/\b(I|we|our|my|us)\b/i', $all_content );
		$second_person = preg_match_all( '/\b(you|your|yours)\b/i', $all_content );

		$voice = 'third_person';
		if ( $first_person > $second_person * 2 ) {
			$voice = 'first_person';
		} elseif ( $second_person > $first_person ) {
			$voice = 'second_person';
		}

		return array(
			'avg_sentence_length' => count( $sentence_lengths ) > 0 ? round( array_sum( $sentence_lengths ) / count( $sentence_lengths ) ) : 0,
			'tone'                => $tone,
			'voice'               => $voice,
			'uses_questions'      => $question_count > count( $posts ),
			'uses_exclamations'   => $exclamation_count > count( $posts ) * 2,
			'question_frequency'  => round( $question_count / count( $posts ), 1 ),
		);
	}

	/**
	 * Extract common topics and themes.
	 *
	 * @since    1.0.0
	 * @param    array $posts    Posts to analyze.
	 * @return   array           Topics and keywords.
	 */
	private function extract_topics( $posts ) {
		$all_words = array();
		$stop_words = array( 'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'shall', 'can', 'need', 'dare', 'ought', 'used', 'it', 'its', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'we', 'they', 'what', 'which', 'who', 'when', 'where', 'why', 'how', 'all', 'each', 'every', 'both', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 'just' );

		foreach ( $posts as $post ) {
			$content = strtolower( wp_strip_all_tags( $post->post_content . ' ' . $post->post_title ) );
			$words = preg_split( '/\W+/', $content, -1, PREG_SPLIT_NO_EMPTY );

			foreach ( $words as $word ) {
				if ( strlen( $word ) > 3 && ! in_array( $word, $stop_words, true ) ) {
					if ( ! isset( $all_words[ $word ] ) ) {
						$all_words[ $word ] = 0;
					}
					$all_words[ $word ]++;
				}
			}
		}

		arsort( $all_words );
		$top_words = array_slice( $all_words, 0, 20, true );

		// Get categories used
		$categories = array();
		foreach ( $posts as $post ) {
			$cats = get_the_category( $post->ID );
			foreach ( $cats as $cat ) {
				if ( ! isset( $categories[ $cat->name ] ) ) {
					$categories[ $cat->name ] = 0;
				}
				$categories[ $cat->name ]++;
			}
		}

		return array(
			'common_keywords' => array_keys( $top_words ),
			'categories_used' => $categories,
		);
	}

	/**
	 * Analyze content structure patterns.
	 *
	 * @since    1.0.0
	 * @param    array $posts    Posts to analyze.
	 * @return   array           Structure analysis.
	 */
	private function analyze_structure( $posts ) {
		$has_intro = 0;
		$has_conclusion = 0;
		$uses_lists = 0;
		$uses_blockquotes = 0;
		$uses_images = 0;
		$heading_patterns = array();

		foreach ( $posts as $post ) {
			$content = $post->post_content;

			// Check for intro (first paragraph before any heading)
			if ( preg_match( '/^<p>.*?<\/p>/s', $content ) ) {
				$has_intro++;
			}

			// Check for conclusion keywords in last paragraph
			if ( preg_match( '/(conclusion|summary|final thoughts|in summary|to conclude)/i', $content ) ) {
				$has_conclusion++;
			}

			// Check for lists
			if ( preg_match( '/<[ou]l[^>]*>/i', $content ) ) {
				$uses_lists++;
			}

			// Check for blockquotes
			if ( preg_match( '/<blockquote/i', $content ) ) {
				$uses_blockquotes++;
			}

			// Check for images
			if ( preg_match( '/<img/i', $content ) || has_post_thumbnail( $post->ID ) ) {
				$uses_images++;
			}

			// Analyze heading hierarchy
			preg_match_all( '/<h([1-6])[^>]*>/i', $content, $headings );
			if ( ! empty( $headings[1] ) ) {
				$pattern = implode( '-', $headings[1] );
				if ( ! isset( $heading_patterns[ $pattern ] ) ) {
					$heading_patterns[ $pattern ] = 0;
				}
				$heading_patterns[ $pattern ]++;
			}
		}

		$total = count( $posts );

		return array(
			'typically_has_intro'       => ( $has_intro / $total ) > 0.5,
			'typically_has_conclusion'  => ( $has_conclusion / $total ) > 0.3,
			'uses_lists_frequently'     => ( $uses_lists / $total ) > 0.5,
			'uses_blockquotes'          => ( $uses_blockquotes / $total ) > 0.2,
			'uses_images'               => ( $uses_images / $total ) > 0.5,
			'common_heading_patterns'   => array_slice( $heading_patterns, 0, 3, true ),
		);
	}

	/**
	 * Use AI for deeper style analysis.
	 *
	 * @since    1.0.0
	 * @param    array $posts    Posts to analyze.
	 * @return   array|WP_Error  AI insights or error.
	 */
	private function ai_style_analysis( $posts ) {
		// Get sample content (limit to avoid high token usage)
		$sample_content = '';
		$sample_count = min( 3, count( $posts ) );
		
		for ( $i = 0; $i < $sample_count; $i++ ) {
			$content = wp_strip_all_tags( $posts[ $i ]->post_content );
			// Limit each post to ~500 words
			$words = explode( ' ', $content );
			$sample_content .= implode( ' ', array_slice( $words, 0, 500 ) ) . "\n\n---\n\n";
		}

		$openai = new Ai_Blog_Posts_OpenAI();
		
		$system_prompt = 'You are a writing style analyst. Analyze the provided blog post samples and describe the writing style in a concise format suitable for instructing an AI to replicate this style.';
		
		$prompt = "Analyze these blog post samples and provide:
1. Overall tone (formal, casual, professional, friendly, etc.)
2. Writing perspective (first person, second person, third person)
3. Sentence structure preferences (short/punchy, long/detailed, varied)
4. Common phrases or expressions used
5. How the author engages readers
6. Any distinctive stylistic elements

Samples:
$sample_content

Provide a brief, actionable style guide (max 200 words).";

		$result = $openai->generate_text( $prompt, $system_prompt, array(
			'max_tokens'  => 500,
			'temperature' => 0.3,
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'style_guide' => $result['content'],
			'tokens_used' => $result['total_tokens'],
		);
	}

	/**
	 * Get cached analysis.
	 *
	 * @since    1.0.0
	 * @return   array|null    Cached analysis or null.
	 */
	public function get_cached_analysis() {
		$cached = Ai_Blog_Posts_Settings::get( 'website_context' );
		
		if ( empty( $cached ) ) {
			return null;
		}

		return json_decode( $cached, true );
	}

	/**
	 * Generate a style prompt from analysis.
	 *
	 * @since    1.0.0
	 * @return   string    Style prompt for AI.
	 */
	public function get_style_prompt() {
		$analysis = $this->get_cached_analysis();
		
		if ( empty( $analysis ) ) {
			return '';
		}

		$style = $analysis['writing_style'] ?? array();
		$structure = $analysis['structure'] ?? array();
		$stats = $analysis['content_stats'] ?? array();

		$prompt_parts = array();

		// Tone
		if ( ! empty( $style['tone'] ) ) {
			$prompt_parts[] = sprintf( 'Write in a %s tone.', $style['tone'] );
		}

		// Voice
		if ( ! empty( $style['voice'] ) ) {
			$voice_map = array(
				'first_person'  => 'Use first person (we, our) perspective.',
				'second_person' => 'Address the reader directly using "you".',
				'third_person'  => 'Use third person perspective.',
			);
			$prompt_parts[] = $voice_map[ $style['voice'] ] ?? '';
		}

		// Sentence length
		if ( ! empty( $style['avg_sentence_length'] ) ) {
			if ( $style['avg_sentence_length'] < 15 ) {
				$prompt_parts[] = 'Use concise, punchy sentences.';
			} elseif ( $style['avg_sentence_length'] > 25 ) {
				$prompt_parts[] = 'Use detailed, flowing sentences.';
			}
		}

		// Questions
		if ( ! empty( $style['uses_questions'] ) ) {
			$prompt_parts[] = 'Include rhetorical questions to engage readers.';
		}

		// Structure
		if ( ! empty( $structure['typically_has_intro'] ) ) {
			$prompt_parts[] = 'Start with an engaging introduction.';
		}

		if ( ! empty( $structure['uses_lists_frequently'] ) ) {
			$prompt_parts[] = 'Use bullet points or numbered lists where appropriate.';
		}

		// Word count
		if ( ! empty( $stats['avg_word_count'] ) ) {
			$prompt_parts[] = sprintf( 'Target approximately %d words.', $stats['avg_word_count'] );
		}

		// AI insights
		if ( ! empty( $analysis['ai_insights']['style_guide'] ) ) {
			$prompt_parts[] = "\n" . $analysis['ai_insights']['style_guide'];
		}

		return implode( ' ', array_filter( $prompt_parts ) );
	}
}


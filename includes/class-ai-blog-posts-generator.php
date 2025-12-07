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
	 * Generate a complete blog post.
	 *
	 * @since    1.0.0
	 * @param    string $topic      The topic to write about.
	 * @param    array  $options    Generation options.
	 * @return   array|WP_Error     Result array or error.
	 */
	public function generate_post( $topic, $options = array() ) {
		$start_time = microtime( true );

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

		// Step 8: Generate featured image
		$image_cost = 0;
		if ( $options['generate_image'] ) {
			$image_result = $this->generate_featured_image( $post_id, $topic, $post_data['post_title'] );
			if ( ! is_wp_error( $image_result ) ) {
				$image_cost = $image_result['cost_usd'] ?? 0;
			}
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

		$result = $this->openai->generate_text( $prompt, $system_prompt, array(
			'model'       => $options['model'],
			'max_tokens'  => 1000,
			'temperature' => 0.7,
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->track_tokens( $result );

		return $result['content'];
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

		return $result['content'];
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

		$intensity_map = array(
			3 => 'moderate',
			4 => 'substantial',
			5 => 'extensive',
		);

		$intensity = $intensity_map[ $level ] ?? 'moderate';

		$system_prompt = "You are an expert editor who makes AI-generated content sound more natural and human-written. " .
			"Maintain the original meaning, structure, and HTML formatting while making the writing feel more authentic.";

		$prompt = sprintf(
			"Revise the following blog post to sound more natural and human-written. Apply %s humanization:\n\n" .
			"Guidelines:\n" .
			"- Vary sentence structures and lengths\n" .
			"- Add conversational elements where appropriate\n" .
			"- Use more varied vocabulary\n" .
			"- Remove any robotic or formulaic patterns\n" .
			"- AVOID these AI clichés: 'dive into', 'delve into', 'it's important to note', 'in today's world', 'in conclusion', 'firstly/secondly/thirdly', 'game-changer', 'leverage', 'unlock'\n" .
			"- Keep the HTML structure intact (<h2>, <h3>, <p>, <ul>, etc.)\n" .
			"- Maintain the same approximate length\n\n" .
			"Content to humanize:\n%s",
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

		return $result['content'];
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
			'max_tokens'  => 300,
			'temperature' => 0.5,
		) );

		if ( is_wp_error( $result ) ) {
			return array();
		}

		$this->track_tokens( $result );

		// Parse JSON response
		$json_content = $result['content'];
		
		// Extract JSON from response (in case there's extra text)
		if ( preg_match( '/\{[^}]+\}/', $json_content, $matches ) ) {
			$json_content = $matches[0];
		}

		$seo_data = json_decode( $json_content, true );

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
		$image_prompt = sprintf(
			"Create a professional, high-quality blog header image for an article titled '%s'. " .
			"The image should be clean, modern, and suitable for a professional blog. " .
			"Use appropriate imagery that represents the topic visually. " .
			"Avoid text in the image. Make it visually appealing with good composition.",
			$title
		);

		$result = $this->openai->generate_image( $image_prompt, array(
			'model' => Ai_Blog_Posts_Settings::get( 'image_model' ),
			'size'  => Ai_Blog_Posts_Settings::get( 'image_size' ),
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
	 * Extract title from outline.
	 *
	 * @since    1.0.0
	 * @param    string $topic      Original topic.
	 * @param    string $outline    Generated outline.
	 * @return   string             Title.
	 */
	private function extract_title( $topic, $outline ) {
		// Try to find a suggested title in the outline
		if ( preg_match( '/title[:\s]+["\'"]?([^"\'\n]+)["\'"]?/i', $outline, $matches ) ) {
			return trim( $matches[1] );
		}

		// Fall back to the topic
		return ucwords( $topic );
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


<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://devonicweb.co.uk/
 * @since      1.0.0
 *
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for the admin area
 * including menu pages, settings, and AJAX handlers.
 *
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/admin
 * @author     Ali Azlan <contact@devonicweb.co.uk>
 */
class Ai_Blog_Posts_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string $plugin_name    The name of this plugin.
	 * @param    string $version        The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
		
		// Ensure database tables exist
		$this->maybe_create_tables();
	}

	/**
	 * Ensure database tables exist (creates them if missing).
	 *
	 * @since    1.0.0
	 */
	private function maybe_create_tables() {
		global $wpdb;
		
		$topics_table = $wpdb->prefix . 'ai_blog_posts_topics';
		$logs_table = $wpdb->prefix . 'ai_blog_posts_logs';
		
		// Check if tables exist
		$topics_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $topics_table ) ) === $topics_table;
		$logs_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $logs_table ) ) === $logs_table;
		
		// Create tables if they don't exist
		if ( ! $topics_exists || ! $logs_exists ) {
			$plugin_dir = plugin_dir_path( dirname( __FILE__ ) );
			require_once $plugin_dir . 'includes/class-ai-blog-posts-activator.php';
			Ai_Blog_Posts_Activator::create_database_tables();
		}
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		$screen = get_current_screen();
		
		// Only load on our plugin pages
		if ( $screen && strpos( $screen->id, 'ai-blog-posts' ) !== false ) {
			wp_enqueue_style(
				$this->plugin_name,
				plugin_dir_url( __FILE__ ) . 'css/ai-blog-posts-admin.css',
				array(),
				$this->version,
				'all'
			);
		}
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		
		// Only load on our plugin pages
		if ( $screen && strpos( $screen->id, 'ai-blog-posts' ) !== false ) {
			wp_enqueue_script(
				$this->plugin_name,
				plugin_dir_url( __FILE__ ) . 'js/ai-blog-posts-admin.js',
				array( 'jquery' ),
				$this->version,
				true
			);

			// Localize script with AJAX URL and nonce
			wp_localize_script(
				$this->plugin_name,
				'aiBlogPosts',
				array(
					'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
					'nonce'     => wp_create_nonce( 'ai_blog_posts_nonce' ),
					'strings'   => array(
						'verifying'     => __( 'Verifying...', 'ai-blog-posts' ),
						'generating'    => __( 'Generating...', 'ai-blog-posts' ),
						'saving'        => __( 'Saving...', 'ai-blog-posts' ),
						'success'       => __( 'Success!', 'ai-blog-posts' ),
						'error'         => __( 'Error', 'ai-blog-posts' ),
						'confirmDelete' => __( 'Are you sure you want to delete this?', 'ai-blog-posts' ),
					),
				)
			);
		}
	}

	/**
	 * Register the admin menu pages.
	 *
	 * @since    1.0.0
	 */
	public function add_admin_menu() {
		// Main menu
		add_menu_page(
			__( 'AI Blog Posts', 'ai-blog-posts' ),
			__( 'AI Blog Posts', 'ai-blog-posts' ),
			'manage_options',
			'ai-blog-posts',
			array( $this, 'render_dashboard_page' ),
			'dashicons-edit-page',
			30
		);

		// Dashboard submenu (same as main)
		add_submenu_page(
			'ai-blog-posts',
			__( 'Dashboard', 'ai-blog-posts' ),
			__( 'Dashboard', 'ai-blog-posts' ),
			'manage_options',
			'ai-blog-posts',
			array( $this, 'render_dashboard_page' )
		);

		// Generate Post
		add_submenu_page(
			'ai-blog-posts',
			__( 'Generate Post', 'ai-blog-posts' ),
			__( 'Generate Post', 'ai-blog-posts' ),
			'manage_options',
			'ai-blog-posts-generate',
			array( $this, 'render_generate_page' )
		);

		// Topic Queue
		add_submenu_page(
			'ai-blog-posts',
			__( 'Topic Queue', 'ai-blog-posts' ),
			__( 'Topic Queue', 'ai-blog-posts' ),
			'manage_options',
			'ai-blog-posts-topics',
			array( $this, 'render_topics_page' )
		);

		// Generation Logs
		add_submenu_page(
			'ai-blog-posts',
			__( 'Generation Logs', 'ai-blog-posts' ),
			__( 'Logs & Costs', 'ai-blog-posts' ),
			'manage_options',
			'ai-blog-posts-logs',
			array( $this, 'render_logs_page' )
		);

		// Settings
		add_submenu_page(
			'ai-blog-posts',
			__( 'Settings', 'ai-blog-posts' ),
			__( 'Settings', 'ai-blog-posts' ),
			'manage_options',
			'ai-blog-posts-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render the dashboard page.
	 *
	 * @since    1.0.0
	 */
	public function render_dashboard_page() {
		include plugin_dir_path( __FILE__ ) . 'partials/dashboard-page.php';
	}

	/**
	 * Render the generate post page.
	 *
	 * @since    1.0.0
	 */
	public function render_generate_page() {
		include plugin_dir_path( __FILE__ ) . 'partials/generate-page.php';
	}

	/**
	 * Render the topics queue page.
	 *
	 * @since    1.0.0
	 */
	public function render_topics_page() {
		include plugin_dir_path( __FILE__ ) . 'partials/topics-page.php';
	}

	/**
	 * Render the logs page.
	 *
	 * @since    1.0.0
	 */
	public function render_logs_page() {
		include plugin_dir_path( __FILE__ ) . 'partials/logs-page.php';
	}

	/**
	 * Render the settings page.
	 *
	 * @since    1.0.0
	 */
	public function render_settings_page() {
		include plugin_dir_path( __FILE__ ) . 'partials/settings-page.php';
	}

	/**
	 * AJAX handler: Verify API key.
	 *
	 * @since    1.0.0
	 */
	public function ajax_verify_api_key() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-blog-posts' ) ) );
		}

		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => __( 'API key is required.', 'ai-blog-posts' ) ) );
		}

		$openai = new Ai_Blog_Posts_OpenAI( $api_key );
		$result = $openai->verify_api_key( $api_key );

		if ( $result['success'] ) {
			// Save the API key
			Ai_Blog_Posts_Settings::set( 'api_key', $api_key );
			Ai_Blog_Posts_Settings::set( 'api_verified', true );
			wp_send_json_success( $result );
		} else {
			Ai_Blog_Posts_Settings::set( 'api_verified', false );
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler: Save settings.
	 *
	 * @since    1.0.0
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-blog-posts' ) ) );
		}

		$settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();

		if ( empty( $settings ) || ! is_array( $settings ) ) {
			wp_send_json_error( array( 'message' => __( 'No settings provided.', 'ai-blog-posts' ) ) );
		}

		// Boolean settings that need special handling
		$boolean_settings = array(
			'schedule_enabled',
			'trending_enabled',
			'image_enabled',
			'seo_enabled',
			'api_verified',
		);

		$saved = array();
		foreach ( $settings as $key => $value ) {
			// Skip API key if empty (don't overwrite existing)
			if ( 'api_key' === $key && empty( $value ) ) {
				continue;
			}

			// Handle boolean values - convert string "true"/"false" to actual booleans
			if ( in_array( $key, $boolean_settings, true ) ) {
				// Handle various truthy/falsy values from JavaScript
				if ( is_string( $value ) ) {
					$value = in_array( strtolower( $value ), array( 'true', '1', 'yes', 'on' ), true );
				} else {
					$value = (bool) $value;
				}
			} elseif ( is_array( $value ) ) {
				// Handle arrays
				$value = array_map( 'sanitize_text_field', $value );
			} else {
				$value = sanitize_text_field( $value );
			}

			if ( Ai_Blog_Posts_Settings::set( $key, $value ) ) {
				$saved[] = $key;
			}
		}

		wp_send_json_success( array(
			'message' => __( 'Settings saved successfully.', 'ai-blog-posts' ),
			'saved'   => $saved,
		) );
	}

	/**
	 * AJAX handler: Generate post.
	 *
	 * @since    1.0.0
	 */
	public function ajax_generate_post() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-blog-posts' ) ) );
		}

		$topic = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
		$keywords = isset( $_POST['keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['keywords'] ) ) : '';
		$category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
		$publish = isset( $_POST['publish'] ) && 'true' === $_POST['publish'];
		$queue_topic_id = isset( $_POST['queue_topic_id'] ) ? absint( $_POST['queue_topic_id'] ) : 0;

		if ( empty( $topic ) ) {
			wp_send_json_error( array( 'message' => __( 'Topic is required.', 'ai-blog-posts' ) ) );
		}

		// If generating from queue, update status to processing
		if ( $queue_topic_id ) {
			global $wpdb;
			$table = $wpdb->prefix . 'ai_blog_posts_topics';
			$wpdb->update(
				$table,
				array( 'status' => 'processing' ),
				array( 'id' => $queue_topic_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		$generator = new Ai_Blog_Posts_Generator();
		$result = $generator->generate_post( $topic, array(
			'keywords'    => $keywords,
			'category_id' => $category_id,
			'publish'     => $publish,
			'source'      => $queue_topic_id ? 'queue' : 'manual',
		) );

		if ( is_wp_error( $result ) ) {
			// If from queue, update status to failed
			if ( $queue_topic_id ) {
				global $wpdb;
				$table = $wpdb->prefix . 'ai_blog_posts_topics';
				$wpdb->update(
					$table,
					array(
						'status'     => 'failed',
						'last_error' => $result->get_error_message(),
					),
					array( 'id' => $queue_topic_id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// If from queue, update status to completed
		if ( $queue_topic_id ) {
			global $wpdb;
			$table = $wpdb->prefix . 'ai_blog_posts_topics';
			$wpdb->update(
				$table,
				array(
					'status'       => 'completed',
					'post_id'      => $result['post_id'],
					'processed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $queue_topic_id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler: Add topic to queue.
	 *
	 * @since    1.0.0
	 */
	public function ajax_add_topic() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-blog-posts' ) ) );
		}

		$topic = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
		$keywords = isset( $_POST['keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['keywords'] ) ) : '';
		$category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
		$priority = isset( $_POST['priority'] ) ? absint( $_POST['priority'] ) : 0;

		if ( empty( $topic ) ) {
			wp_send_json_error( array( 'message' => __( 'Topic is required.', 'ai-blog-posts' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ai_blog_posts_topics';

		$inserted = $wpdb->insert(
			$table,
			array(
				'topic'       => $topic,
				'keywords'    => $keywords,
				'category_id' => $category_id,
				'source'      => 'manual',
				'status'      => 'pending',
				'priority'    => $priority,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%d', '%s' )
		);

		if ( $inserted ) {
			wp_send_json_success( array(
				'message' => __( 'Topic added to queue.', 'ai-blog-posts' ),
				'id'      => $wpdb->insert_id,
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to add topic.', 'ai-blog-posts' ) ) );
		}
	}

	/**
	 * AJAX handler: Delete topic from queue.
	 *
	 * @since    1.0.0
	 */
	public function ajax_delete_topic() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-blog-posts' ) ) );
		}

		$topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;

		if ( ! $topic_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid topic ID.', 'ai-blog-posts' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ai_blog_posts_topics';

		$deleted = $wpdb->delete( $table, array( 'id' => $topic_id ), array( '%d' ) );

		if ( $deleted ) {
			wp_send_json_success( array( 'message' => __( 'Topic deleted.', 'ai-blog-posts' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete topic.', 'ai-blog-posts' ) ) );
		}
	}

	/**
	 * AJAX handler: Bulk delete topics.
	 *
	 * @since    1.0.0
	 */
	public function ajax_bulk_delete_topics() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-blog-posts' ) ) );
		}

		$topic_ids = isset( $_POST['topic_ids'] ) ? array_map( 'absint', (array) $_POST['topic_ids'] ) : array();

		if ( empty( $topic_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No topics selected.', 'ai-blog-posts' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ai_blog_posts_topics';

		$placeholders = implode( ', ', array_fill( 0, count( $topic_ids ), '%d' ) );
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table WHERE id IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$topic_ids
			)
		);

		if ( false !== $deleted ) {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %d: number of topics deleted */
					__( '%d topic(s) deleted successfully.', 'ai-blog-posts' ),
					$deleted
				),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete topics.', 'ai-blog-posts' ) ) );
		}
	}

	/**
	 * AJAX handler: Generate post from queue.
	 *
	 * @since    1.0.0
	 */
	public function ajax_generate_from_queue() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-blog-posts' ) ) );
		}

		$topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;

		if ( ! $topic_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid topic ID.', 'ai-blog-posts' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ai_blog_posts_topics';

		// Get the topic
		$topic = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $topic_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( ! $topic ) {
			wp_send_json_error( array( 'message' => __( 'Topic not found.', 'ai-blog-posts' ) ) );
		}

		// Update status to processing
		$wpdb->update(
			$table,
			array( 'status' => 'processing' ),
			array( 'id' => $topic_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Generate the post
		$generator = new Ai_Blog_Posts_Generator();
		$result = $generator->generate_post( $topic->topic, array(
			'keywords'    => $topic->keywords,
			'category_id' => $topic->category_id,
			'publish'     => false,
			'source'      => 'queue',
		) );

		if ( is_wp_error( $result ) ) {
			// Update topic status to failed
			$wpdb->update(
				$table,
				array(
					'status'     => 'failed',
					'attempts'   => $topic->attempts + 1,
					'last_error' => $result->get_error_message(),
				),
				array( 'id' => $topic_id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);

			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Update topic status to completed
		$wpdb->update(
			$table,
			array(
				'status'       => 'completed',
				'post_id'      => $result['post_id'],
				'processed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $topic_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		wp_send_json_success( array(
			'message'  => __( 'Post generated successfully.', 'ai-blog-posts' ),
			'post_id'  => $result['post_id'],
			'title'    => get_the_title( $result['post_id'] ),
			'edit_url' => get_edit_post_link( $result['post_id'], 'raw' ),
			'post_url' => get_permalink( $result['post_id'] ),
		) );
	}

	/**
	 * AJAX handler: Get generation logs.
	 *
	 * @since    1.0.0
	 */
	public function ajax_get_logs() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-blog-posts' ) ) );
		}

		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;

		$cost_tracker = new Ai_Blog_Posts_Cost_Tracker();
		$logs = $cost_tracker->get_logs( $page, $per_page );
		$stats = $cost_tracker->get_stats();

		wp_send_json_success( array(
			'logs'  => $logs,
			'stats' => $stats,
		) );
	}

	/**
	 * AJAX handler: Analyze website.
	 *
	 * @since    1.0.0
	 */
	public function ajax_analyze_website() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-blog-posts' ) ) );
		}

		// Check if AI-powered analysis is requested
		$use_ai = isset( $_POST['use_ai'] ) && ( 'true' === $_POST['use_ai'] || true === $_POST['use_ai'] );

		// Verify API is configured if using AI
		if ( $use_ai && ! Ai_Blog_Posts_Settings::is_verified() ) {
			wp_send_json_error( array( 'message' => __( 'Please configure and verify your API key first to use AI-powered analysis.', 'ai-blog-posts' ) ) );
		}

		$analyzer = new Ai_Blog_Posts_Analyzer();
		$result = $analyzer->analyze( $use_ai );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Generate the style prompt for preview
		$style_prompt = $analyzer->get_style_prompt();

		wp_send_json_success( array(
			'message'      => $use_ai 
				? __( 'AI-powered analysis complete!', 'ai-blog-posts' ) 
				: __( 'Quick analysis complete!', 'ai-blog-posts' ),
			'analysis'     => $result,
			'style_prompt' => $style_prompt,
			'used_ai'      => $use_ai,
		) );
	}

	/**
	 * AJAX handler: Fetch trending topics.
	 *
	 * @since    1.0.0
	 */
	public function ajax_fetch_trending() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-blog-posts' ) ) );
		}

		// Check if force refresh is requested
		$force_refresh = isset( $_POST['force_refresh'] ) && '1' === $_POST['force_refresh'];

		$trends = new Ai_Blog_Posts_Trends();
		$result = $trends->fetch_trending( $force_refresh );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Trending topics fetched.', 'ai-blog-posts' ),
			'topics'  => $result,
		) );
	}

	/**
	 * AJAX handler: Export logs to CSV.
	 *
	 * @since    1.0.0
	 */
	public function ajax_export_logs() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Permission denied.', 'ai-blog-posts' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ai_blog_posts_logs';

		$logs = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Set headers for CSV download
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=ai-blog-posts-logs-' . date( 'Y-m-d' ) . '.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// Add CSV header
		fputcsv( $output, array(
			'ID',
			'Post ID',
			'Post Title',
			'Model',
			'Prompt Tokens',
			'Completion Tokens',
			'Total Tokens',
			'Text Cost (USD)',
			'Image Cost (USD)',
			'Total Cost (USD)',
			'Generation Time (s)',
			'Topic Source',
			'Status',
			'Error Message',
			'Date',
		) );

		// Add data rows
		foreach ( $logs as $log ) {
			$post_title = $log['post_id'] ? get_the_title( $log['post_id'] ) : 'N/A';
			$total_cost = floatval( $log['cost_usd'] ) + floatval( $log['image_cost_usd'] );

			fputcsv( $output, array(
				$log['id'],
				$log['post_id'] ?? 'N/A',
				$post_title,
				$log['model_used'],
				$log['prompt_tokens'],
				$log['completion_tokens'],
				$log['total_tokens'],
				number_format( $log['cost_usd'], 6 ),
				number_format( $log['image_cost_usd'], 6 ),
				number_format( $total_cost, 6 ),
				$log['generation_time'],
				$log['topic_source'],
				$log['status'],
				$log['error_message'] ?? '',
				$log['created_at'],
			) );
		}

		fclose( $output );
		exit;
	}

	/**
	 * AJAX handler: Clear all logs.
	 *
	 * @since    1.0.0
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-blog-posts' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ai_blog_posts_logs';

		$result = $wpdb->query( "TRUNCATE TABLE $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( false !== $result ) {
			wp_send_json_success( array( 'message' => __( 'All logs have been cleared.', 'ai-blog-posts' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to clear logs.', 'ai-blog-posts' ) ) );
		}
	}

	/**
	 * Add settings link to plugins page.
	 *
	 * @since    1.0.0
	 * @param    array $links    Existing links.
	 * @return   array           Modified links.
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=ai-blog-posts-settings' ),
			__( 'Settings', 'ai-blog-posts' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Display admin notices.
	 *
	 * @since    1.0.0
	 */
	public function admin_notices() {
		$screen = get_current_screen();
		
		// Only show on our plugin pages
		if ( ! $screen || strpos( $screen->id, 'ai-blog-posts' ) === false ) {
			return;
		}

		// Check if API is configured
		if ( ! Ai_Blog_Posts_Settings::is_configured() ) {
			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'AI Blog Posts requires an OpenAI API key to function.', 'ai-blog-posts' ),
				esc_url( admin_url( 'admin.php?page=ai-blog-posts-settings' ) ),
				esc_html__( 'Configure now', 'ai-blog-posts' )
			);
		} elseif ( ! Ai_Blog_Posts_Settings::is_verified() ) {
			printf(
				'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'Your OpenAI API key has not been verified yet.', 'ai-blog-posts' ),
				esc_url( admin_url( 'admin.php?page=ai-blog-posts-settings' ) ),
				esc_html__( 'Verify now', 'ai-blog-posts' )
			);
		}
	}
}

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
		
		// Check if tables exist (use direct query - table names are safe as they use $wpdb->prefix)
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$topics_exists = $wpdb->get_var( "SHOW TABLES LIKE '$topics_table'" ) === $topics_table;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$logs_exists = $wpdb->get_var( "SHOW TABLES LIKE '$logs_table'" ) === $logs_table;
		
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
		// Main menu (position 4 to appear above Posts which is 5)
		add_menu_page(
			__( 'AI Blog Posts', 'ai-blog-posts' ),
			__( 'AI Blog Posts', 'ai-blog-posts' ),
			'manage_options',
			'ai-blog-posts',
			array( $this, 'render_dashboard_page' ),
			'dashicons-edit-page',
			4
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
		$schedule_changed = false;
		
		foreach ( $settings as $key => $value ) {
			// Skip API key if empty (don't overwrite existing)
			if ( 'api_key' === $key && empty( $value ) ) {
				continue;
			}

			// Track if schedule settings changed
			if ( in_array( $key, array( 'schedule_enabled', 'schedule_frequency', 'schedule_time' ), true ) ) {
				$old_value = Ai_Blog_Posts_Settings::get( $key );
				$schedule_changed = $schedule_changed || ( $old_value !== $value );
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

		// Reschedule cron if schedule settings changed
		if ( $schedule_changed ) {
			// Set cooldown to prevent immediate generation when settings are saved
			$cooldown_end = time() + ( 5 * MINUTE_IN_SECONDS );
			set_transient( 'ai_blog_posts_schedule_cooldown', $cooldown_end, 10 * MINUTE_IN_SECONDS );
			
			$scheduler = new Ai_Blog_Posts_Scheduler();
			$scheduler->reschedule();
		}

		wp_send_json_success( array(
			'message' => __( 'Settings saved successfully.', 'ai-blog-posts' ),
			'saved'   => $saved,
		) );
	}

	/**
	 * AJAX handler: Start step-by-step generation (create job).
	 *
	 * @since    1.0.0
	 */
	public function ajax_start_generation() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-blog-posts' ) ) );
		}

		$topic = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
		$keywords = isset( $_POST['keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['keywords'] ) ) : '';
		$category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
		$publish = isset( $_POST['publish'] ) && 'true' === $_POST['publish'];
		$queue_topic_id = isset( $_POST['queue_topic_id'] ) ? absint( $_POST['queue_topic_id'] ) : 0;
		$generate_image = isset( $_POST['generate_image'] ) ? filter_var( $_POST['generate_image'], FILTER_VALIDATE_BOOLEAN ) : Ai_Blog_Posts_Settings::get( 'image_enabled' );

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
		$job_id = $generator->create_job( $topic, array(
			'keywords'       => $keywords,
			'category_id'    => $category_id,
			'publish'        => $publish,
			'source'         => $queue_topic_id ? 'queue' : 'manual',
			'generate_image' => $generate_image,
			'queue_topic_id' => $queue_topic_id,
		) );

		if ( is_wp_error( $job_id ) ) {
			wp_send_json_error( array( 'message' => $job_id->get_error_message() ) );
		}

		wp_send_json_success( array(
			'job_id'     => $job_id,
			'next_step'  => 'outline',
			'message'    => __( 'Generation started.', 'ai-blog-posts' ),
		) );
	}

	/**
	 * AJAX handler: Process a generation step.
	 *
	 * @since    1.0.0
	 */
	public function ajax_process_step() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-blog-posts' ) ) );
		}

		$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
		$step = isset( $_POST['step'] ) ? sanitize_text_field( wp_unslash( $_POST['step'] ) ) : '';

		if ( empty( $job_id ) || empty( $step ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing job ID or step.', 'ai-blog-posts' ) ) );
		}

		// Extend execution time for this request
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // 2 minutes per step
		}

		$generator = new Ai_Blog_Posts_Generator();
		$result = $generator->process_step( $job_id, $step );

		if ( is_wp_error( $result ) ) {
			// Update queue topic if failed
			$job = $generator->get_job( $job_id );
			if ( $job && ! empty( $job['options']['queue_topic_id'] ) ) {
				global $wpdb;
				$table = $wpdb->prefix . 'ai_blog_posts_topics';
				$wpdb->update(
					$table,
					array(
						'status'     => 'failed',
						'last_error' => $result->get_error_message(),
					),
					array( 'id' => $job['options']['queue_topic_id'] ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// If step is image and completed, call complete_with_image
		if ( $step === 'image' && isset( $result['next_step'] ) && $result['next_step'] === 'complete' ) {
			$result = $generator->complete_with_image( $job_id );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler: Get job status.
	 *
	 * @since    1.0.0
	 */
	public function ajax_get_job_status() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-blog-posts' ) ) );
		}

		$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';

		if ( empty( $job_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing job ID.', 'ai-blog-posts' ) ) );
		}

		$generator = new Ai_Blog_Posts_Generator();
		$job = $generator->get_job( $job_id );

		if ( ! $job ) {
			wp_send_json_error( array( 'message' => __( 'Job not found or expired.', 'ai-blog-posts' ) ) );
		}

		wp_send_json_success( array(
			'status'          => $job['status'],
			'current_step'    => $job['current_step'],
			'steps_completed' => $job['steps_completed'],
			'error'           => $job['error'],
			'post_id'         => $job['post_id'],
		) );
	}

	/**
	 * AJAX handler: Generate post (legacy single-request mode, kept for backward compatibility).
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

		// Extend execution time
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // 5 minutes
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
	 * AJAX handler: Update topic in queue.
	 *
	 * @since    1.0.0
	 */
	public function ajax_update_topic() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-blog-posts' ) ) );
		}

		$topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
		$topic = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
		$keywords = isset( $_POST['keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['keywords'] ) ) : '';
		$category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
		$priority = isset( $_POST['priority'] ) ? absint( $_POST['priority'] ) : 0;

		if ( ! $topic_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid topic ID.', 'ai-blog-posts' ) ) );
		}

		if ( empty( $topic ) ) {
			wp_send_json_error( array( 'message' => __( 'Topic is required.', 'ai-blog-posts' ) ) );
		}

		// Ensure priority is within valid range
		$priority = max( 0, min( 100, $priority ) );

		global $wpdb;
		$table = $wpdb->prefix . 'ai_blog_posts_topics';

		// Check if topic exists
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $topic_id ) );
		if ( ! $existing ) {
			wp_send_json_error( array( 'message' => __( 'Topic not found.', 'ai-blog-posts' ) ) );
		}

		// Only allow editing if topic is pending or failed (not processing or completed)
		if ( ! in_array( $existing->status, array( 'pending', 'failed' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Only pending or failed topics can be edited.', 'ai-blog-posts' ) ) );
		}

		$updated = $wpdb->update(
			$table,
			array(
				'topic'       => $topic,
				'keywords'    => $keywords,
				'category_id' => $category_id,
				'priority'    => $priority,
			),
			array( 'id' => $topic_id ),
			array( '%s', '%s', '%d', '%d' ),
			array( '%d' )
		);

		if ( false !== $updated ) {
			wp_send_json_success( array( 'message' => __( 'Topic updated successfully.', 'ai-blog-posts' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update topic.', 'ai-blog-posts' ) ) );
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
				...$topic_ids // Spread operator to expand array into individual arguments
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
	 * Supports both step-by-step mode (use_steps=true) and legacy single-request mode.
	 *
	 * @since    1.0.0
	 */
	public function ajax_generate_from_queue() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-blog-posts' ) ) );
		}

		$topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
		$use_steps = isset( $_POST['use_steps'] ) && filter_var( $_POST['use_steps'], FILTER_VALIDATE_BOOLEAN );

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

		$generator = new Ai_Blog_Posts_Generator();

		// Step-by-step mode: Create a job and return the job ID
		if ( $use_steps ) {
			$job_id = $generator->create_job( $topic->topic, array(
				'keywords'       => $topic->keywords,
				'category_id'    => $topic->category_id,
				'publish'        => false,
				'source'         => 'queue',
				'generate_image' => Ai_Blog_Posts_Settings::get( 'image_enabled' ),
				'queue_topic_id' => $topic_id,
			) );

			if ( is_wp_error( $job_id ) ) {
				$wpdb->update(
					$table,
					array(
						'status'     => 'failed',
						'attempts'   => $topic->attempts + 1,
						'last_error' => $job_id->get_error_message(),
					),
					array( 'id' => $topic_id ),
					array( '%s', '%d', '%s' ),
					array( '%d' )
				);
				wp_send_json_error( array( 'message' => $job_id->get_error_message() ) );
			}

			wp_send_json_success( array(
				'job_id'    => $job_id,
				'next_step' => 'outline',
				'message'   => __( 'Generation started.', 'ai-blog-posts' ),
			) );
			return;
		}

		// Legacy single-request mode (for backward compatibility and scheduled tasks)
		// Extend execution time
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // 5 minutes
		}

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
			'view_url' => get_permalink( $result['post_id'] ),
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
	 * AJAX handler: Get server diagnostics.
	 *
	 * @since    1.0.0
	 */
	public function ajax_server_diagnostics() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-blog-posts' ) ) );
		}

		$diagnostics = array();
		$warnings = array();
		$recommendations = array();

		// PHP Version
		$php_version = phpversion();
		$diagnostics['php_version'] = $php_version;
		if ( version_compare( $php_version, '7.4', '<' ) ) {
			$warnings[] = __( 'PHP version is below 7.4. Consider upgrading for better performance.', 'ai-blog-posts' );
		}

		// PHP max_execution_time
		$max_exec_time = ini_get( 'max_execution_time' );
		$diagnostics['max_execution_time'] = $max_exec_time;
		if ( $max_exec_time > 0 && $max_exec_time < 60 ) {
			$warnings[] = sprintf(
				/* translators: %d: seconds */
				__( 'PHP max_execution_time is %d seconds. This may cause timeout issues with longer content generation.', 'ai-blog-posts' ),
				$max_exec_time
			);
			$recommendations[] = __( 'The plugin uses step-by-step generation to work around this limit, but increasing it to 120+ seconds would improve reliability.', 'ai-blog-posts' );
		}

		// PHP memory_limit
		$memory_limit = ini_get( 'memory_limit' );
		$diagnostics['memory_limit'] = $memory_limit;
		$memory_bytes = wp_convert_hr_to_bytes( $memory_limit );
		if ( $memory_bytes < 128 * 1024 * 1024 ) {
			$warnings[] = sprintf(
				/* translators: %s: memory limit */
				__( 'PHP memory_limit is %s. Consider increasing to at least 256M for optimal performance.', 'ai-blog-posts' ),
				$memory_limit
			);
		}

		// WordPress memory limit
		$wp_memory_limit = WP_MEMORY_LIMIT;
		$diagnostics['wp_memory_limit'] = $wp_memory_limit;

		// cURL availability
		$diagnostics['curl_enabled'] = function_exists( 'curl_version' );
		if ( ! function_exists( 'curl_version' ) ) {
			$warnings[] = __( 'cURL is not available. This may cause connection issues with the OpenAI API.', 'ai-blog-posts' );
		}

		// OpenSSL availability
		$diagnostics['openssl_enabled'] = extension_loaded( 'openssl' );
		if ( ! extension_loaded( 'openssl' ) ) {
			$warnings[] = __( 'OpenSSL extension is not loaded. HTTPS connections may fail.', 'ai-blog-posts' );
		}

		// Test outbound connection to OpenAI
		$diagnostics['openai_reachable'] = false;
		$test_response = wp_remote_get( 'https://api.openai.com/v1/models', array(
			'timeout' => 10,
			'headers' => array( 'Authorization' => 'Bearer test' ),
		) );
		if ( ! is_wp_error( $test_response ) ) {
			$diagnostics['openai_reachable'] = true;
		} else {
			$warnings[] = sprintf(
				/* translators: %s: error message */
				__( 'Cannot reach OpenAI API: %s', 'ai-blog-posts' ),
				$test_response->get_error_message()
			);
		}

		// WP Cron status
		$diagnostics['wp_cron_disabled'] = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		if ( $diagnostics['wp_cron_disabled'] ) {
			$recommendations[] = __( 'WP-Cron is disabled. Scheduled post generation may not work unless you have a server cron configured.', 'ai-blog-posts' );
		}

		// Server software
		$diagnostics['server_software'] = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown';

		// Check if loopback connections work
		// A successful loopback means the server can reach itself - any HTTP response indicates connectivity
		$diagnostics['loopback_working'] = false;
		$loopback_test = wp_remote_get( admin_url( 'admin-ajax.php' ), array( 'timeout' => 10 ) );
		if ( ! is_wp_error( $loopback_test ) ) {
			$response_code = wp_remote_retrieve_response_code( $loopback_test );
			// Any valid HTTP response code indicates the loopback connection works
			// admin-ajax.php returns 400 when called without action param, which is expected
			if ( $response_code >= 200 && $response_code < 500 ) {
				$diagnostics['loopback_working'] = true;
			}
		}
		if ( ! $diagnostics['loopback_working'] ) {
			$recommendations[] = __( 'Loopback connections may not be working. This could affect some background processing features.', 'ai-blog-posts' );
		}

		// Overall status
		$status = empty( $warnings ) ? 'good' : ( count( $warnings ) > 2 ? 'poor' : 'fair' );

		wp_send_json_success( array(
			'status'          => $status,
			'diagnostics'     => $diagnostics,
			'warnings'        => $warnings,
			'recommendations' => $recommendations,
		) );
	}

	/**
	 * AJAX handler: Import topics from CSV.
	 *
	 * @since    1.0.0
	 */
	public function ajax_import_csv() {
		check_ajax_referer( 'ai_blog_posts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-blog-posts' ) ) );
		}

		$topics_json = isset( $_POST['topics'] ) ? wp_unslash( $_POST['topics'] ) : '';
		$topics = json_decode( $topics_json, true );

		if ( empty( $topics ) || ! is_array( $topics ) ) {
			wp_send_json_error( array( 'message' => __( 'No valid topics data received.', 'ai-blog-posts' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ai_blog_posts_topics';

		$imported = 0;
		$skipped = 0;

		foreach ( $topics as $topic_data ) {
			$topic = isset( $topic_data['topic'] ) ? sanitize_text_field( $topic_data['topic'] ) : '';
			if ( empty( $topic ) ) {
				$skipped++;
				continue;
			}

			$keywords = isset( $topic_data['keywords'] ) ? sanitize_text_field( $topic_data['keywords'] ) : '';
			$priority = isset( $topic_data['priority'] ) ? absint( $topic_data['priority'] ) : 0;
			$category = isset( $topic_data['category'] ) ? sanitize_text_field( $topic_data['category'] ) : '';

			// Resolve category by name or slug
			$category_id = 0;
			if ( ! empty( $category ) ) {
				// First try to find by name (case-insensitive)
				$cat = get_term_by( 'name', $category, 'category' );
				
				// If not found by name, try by slug
				if ( ! $cat ) {
					$cat = get_term_by( 'slug', sanitize_title( $category ), 'category' );
				}

				// If still not found, create the category
				if ( ! $cat ) {
					$new_cat = wp_insert_term( $category, 'category' );
					if ( ! is_wp_error( $new_cat ) ) {
						$category_id = $new_cat['term_id'];
					}
				} else {
					$category_id = $cat->term_id;
				}
			}

			$inserted = $wpdb->insert(
				$table,
				array(
					'topic'       => $topic,
					'keywords'    => $keywords,
					'category_id' => $category_id,
					'source'      => 'csv',
					'status'      => 'pending',
					'priority'    => $priority,
					'created_at'  => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%d', '%s', '%s', '%d', '%s' )
			);

			if ( $inserted ) {
				$imported++;
			} else {
				$skipped++;
			}
		}

		$message = sprintf(
			/* translators: 1: number of imported topics, 2: number of skipped topics */
			__( '%1$d topic(s) imported successfully.', 'ai-blog-posts' ),
			$imported
		);

		if ( $skipped > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of skipped topics */
				__( '%d skipped.', 'ai-blog-posts' ),
				$skipped
			);
		}

		wp_send_json_success( array( 'message' => $message, 'imported' => $imported, 'skipped' => $skipped ) );
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

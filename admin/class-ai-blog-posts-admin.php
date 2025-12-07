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

		$saved = array();
		foreach ( $settings as $key => $value ) {
			// Skip API key if empty (don't overwrite existing)
			if ( 'api_key' === $key && empty( $value ) ) {
				continue;
			}

			// Handle arrays
			if ( is_array( $value ) ) {
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
		$category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
		$publish = isset( $_POST['publish'] ) && 'true' === $_POST['publish'];

		if ( empty( $topic ) ) {
			wp_send_json_error( array( 'message' => __( 'Topic is required.', 'ai-blog-posts' ) ) );
		}

		$generator = new Ai_Blog_Posts_Generator();
		$result = $generator->generate_post( $topic, array(
			'category_id' => $category_id,
			'publish'     => $publish,
			'source'      => 'manual',
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
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

		$analyzer = new Ai_Blog_Posts_Analyzer();
		$result = $analyzer->analyze();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'  => __( 'Website analyzed successfully.', 'ai-blog-posts' ),
			'analysis' => $result,
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

		$trends = new Ai_Blog_Posts_Trends();
		$result = $trends->fetch_trending();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Trending topics fetched.', 'ai-blog-posts' ),
			'topics'  => $result,
		) );
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

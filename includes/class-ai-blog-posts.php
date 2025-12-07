<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://devonicweb.co.uk/
 * @since      1.0.0
 *
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 * @author     Ali Azlan <contact@devonicweb.co.uk>
 */
class Ai_Blog_Posts {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Ai_Blog_Posts_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'AI_BLOG_POSTS_VERSION' ) ) {
			$this->version = AI_BLOG_POSTS_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'ai-blog-posts';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_cron_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Ai_Blog_Posts_Loader. Orchestrates the hooks of the plugin.
	 * - Ai_Blog_Posts_i18n. Defines internationalization functionality.
	 * - Ai_Blog_Posts_Admin. Defines all hooks for the admin area.
	 * - Ai_Blog_Posts_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-blog-posts-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-blog-posts-i18n.php';

		/**
		 * Encryption helper for secure API key storage.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-blog-posts-encryption.php';

		/**
		 * Settings management class.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-blog-posts-settings.php';

		/**
		 * OpenAI API wrapper class.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-blog-posts-openai.php';

		/**
		 * Cost tracking and logging class.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-blog-posts-cost-tracker.php';

		/**
		 * Website content analyzer class.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-blog-posts-analyzer.php';

		/**
		 * SEO plugin integration class.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-blog-posts-seo.php';

		/**
		 * Content generator class.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-blog-posts-generator.php';

		/**
		 * Scheduler class for automated posting.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-blog-posts-scheduler.php';

		/**
		 * Trending topics fetcher class.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-blog-posts-trends.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-ai-blog-posts-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-ai-blog-posts-public.php';

		$this->loader = new Ai_Blog_Posts_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Ai_Blog_Posts_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Ai_Blog_Posts_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Ai_Blog_Posts_Admin( $this->get_plugin_name(), $this->get_version() );

		// Styles and scripts
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// Admin menu
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );

		// Admin notices
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'admin_notices' );

		// Settings link on plugins page
		$this->loader->add_filter( 'plugin_action_links_ai-blog-posts/ai-blog-posts.php', $plugin_admin, 'add_settings_link' );

		// AJAX handlers
		$this->loader->add_action( 'wp_ajax_ai_blog_posts_verify_api', $plugin_admin, 'ajax_verify_api_key' );
		$this->loader->add_action( 'wp_ajax_ai_blog_posts_save_settings', $plugin_admin, 'ajax_save_settings' );
		$this->loader->add_action( 'wp_ajax_ai_blog_posts_generate_post', $plugin_admin, 'ajax_generate_post' );
		$this->loader->add_action( 'wp_ajax_ai_blog_posts_add_topic', $plugin_admin, 'ajax_add_topic' );
		$this->loader->add_action( 'wp_ajax_ai_blog_posts_delete_topic', $plugin_admin, 'ajax_delete_topic' );
		$this->loader->add_action( 'wp_ajax_ai_blog_posts_get_logs', $plugin_admin, 'ajax_get_logs' );
		$this->loader->add_action( 'wp_ajax_ai_blog_posts_analyze_website', $plugin_admin, 'ajax_analyze_website' );
		$this->loader->add_action( 'wp_ajax_ai_blog_posts_fetch_trending', $plugin_admin, 'ajax_fetch_trending' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Ai_Blog_Posts_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Add meta description if no SEO plugin is active
		$seo = new Ai_Blog_Posts_SEO();
		$this->loader->add_action( 'wp_head', $seo, 'maybe_add_meta_description', 1 );

	}

	/**
	 * Register cron-related hooks.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_cron_hooks() {

		$scheduler = new Ai_Blog_Posts_Scheduler();
		$trends = new Ai_Blog_Posts_Trends();

		// Register custom cron schedules
		$this->loader->add_filter( 'cron_schedules', $scheduler, 'add_cron_schedules' );

		// Scheduled generation hook
		$this->loader->add_action( 'ai_blog_posts_scheduled_generation', $scheduler, 'run_scheduled_generation' );

		// Trending topics refresh hook
		$this->loader->add_action( 'ai_blog_posts_trending_refresh', $trends, 'cron_refresh' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Ai_Blog_Posts_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}

<?php

/**
 * Fired during plugin activation
 *
 * @link       https://devonicweb.co.uk/
 * @since      1.0.0
 *
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation,
 * including database table creation and default options setup.
 *
 * @since      1.0.0
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 * @author     Ali Azlan <contact@devonicweb.co.uk>
 */
class Ai_Blog_Posts_Activator {

	/**
	 * Plugin activation handler.
	 *
	 * Creates necessary database tables and sets up default options.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		self::create_database_tables();
		self::set_default_options();
		self::schedule_cron_events();
		
		// Store the plugin version for future upgrades
		update_option( 'ai_blog_posts_version', AI_BLOG_POSTS_VERSION );
	}

	/**
	 * Create custom database tables for logs and topic queue.
	 *
	 * @since    1.0.0
	 */
	private static function create_database_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Generation logs table
		$logs_table = $wpdb->prefix . 'ai_blog_posts_logs';
		$logs_sql = "CREATE TABLE $logs_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned DEFAULT NULL,
			model_used varchar(50) NOT NULL,
			prompt_tokens int(11) NOT NULL DEFAULT 0,
			completion_tokens int(11) NOT NULL DEFAULT 0,
			total_tokens int(11) NOT NULL DEFAULT 0,
			cost_usd decimal(10,6) NOT NULL DEFAULT 0.000000,
			image_cost_usd decimal(10,6) NOT NULL DEFAULT 0.000000,
			generation_time float NOT NULL DEFAULT 0,
			topic_source varchar(50) NOT NULL DEFAULT 'manual',
			status varchar(20) NOT NULL DEFAULT 'success',
			error_message text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY created_at (created_at),
			KEY status (status)
		) $charset_collate;";

		// Topic queue table
		$topics_table = $wpdb->prefix . 'ai_blog_posts_topics';
		$topics_sql = "CREATE TABLE $topics_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			topic varchar(500) NOT NULL,
			keywords text DEFAULT NULL,
			category_id bigint(20) unsigned DEFAULT NULL,
			source varchar(50) NOT NULL DEFAULT 'manual',
			status varchar(20) NOT NULL DEFAULT 'pending',
			priority int(11) NOT NULL DEFAULT 0,
			attempts int(11) NOT NULL DEFAULT 0,
			last_error text DEFAULT NULL,
			post_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			processed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY priority (priority),
			KEY source (source),
			KEY category_id (category_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $logs_sql );
		dbDelta( $topics_sql );
	}

	/**
	 * Set default plugin options.
	 *
	 * @since    1.0.0
	 */
	private static function set_default_options() {
		$defaults = array(
			'ai_blog_posts_api_key'            => '',
			'ai_blog_posts_org_id'             => '',
			'ai_blog_posts_model'              => 'gpt-4o-mini',
			'ai_blog_posts_image_enabled'      => false,
			'ai_blog_posts_image_model'        => 'dall-e-3',
			'ai_blog_posts_image_size'         => '1792x1024',
			'ai_blog_posts_schedule_enabled'   => false,
			'ai_blog_posts_schedule_frequency' => 'daily',
			'ai_blog_posts_schedule_time'      => '09:00',
			'ai_blog_posts_max_posts_per_day'  => 1,
			'ai_blog_posts_post_status'        => 'draft',
			'ai_blog_posts_default_author'     => get_current_user_id(),
			'ai_blog_posts_categories'         => array(),
			'ai_blog_posts_humanize_level'     => 3,
			'ai_blog_posts_word_count_min'     => 800,
			'ai_blog_posts_word_count_max'     => 1500,
			'ai_blog_posts_website_context'    => '',
			'ai_blog_posts_seo_enabled'        => true,
			'ai_blog_posts_trending_enabled'   => false,
			'ai_blog_posts_trending_country'   => 'US',
			'ai_blog_posts_budget_limit'       => 0,
			'ai_blog_posts_budget_alert_email' => get_option( 'admin_email' ),
		);

		foreach ( $defaults as $option => $value ) {
			if ( false === get_option( $option ) ) {
				add_option( $option, $value );
			}
		}
	}

	/**
	 * Schedule cron events for automated posting.
	 *
	 * @since    1.0.0
	 */
	private static function schedule_cron_events() {
		if ( ! wp_next_scheduled( 'ai_blog_posts_scheduled_generation' ) ) {
			wp_schedule_event( time(), 'hourly', 'ai_blog_posts_scheduled_generation' );
		}

		if ( ! wp_next_scheduled( 'ai_blog_posts_trending_refresh' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'ai_blog_posts_trending_refresh' );
		}
	}
}

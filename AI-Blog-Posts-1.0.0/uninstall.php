<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * This file handles complete cleanup of all plugin data including:
 * - Custom database tables
 * - Plugin options
 * - Scheduled cron events
 * - Transient data
 *
 * @link       https://devonicweb.co.uk/
 * @since      1.0.0
 *
 * @package    Ai_Blog_Posts
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Perform complete plugin uninstallation cleanup.
 */
function ai_blog_posts_uninstall() {
	global $wpdb;

	// Delete custom database tables
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ai_blog_posts_logs" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ai_blog_posts_topics" );

	// Delete all plugin options
	$options = array(
		'ai_blog_posts_version',
		'ai_blog_posts_api_key',
		'ai_blog_posts_org_id',
		'ai_blog_posts_model',
		'ai_blog_posts_image_enabled',
		'ai_blog_posts_image_model',
		'ai_blog_posts_image_size',
		'ai_blog_posts_schedule_enabled',
		'ai_blog_posts_schedule_frequency',
		'ai_blog_posts_schedule_time',
		'ai_blog_posts_max_posts_per_day',
		'ai_blog_posts_post_status',
		'ai_blog_posts_default_author',
		'ai_blog_posts_categories',
		'ai_blog_posts_humanize_level',
		'ai_blog_posts_word_count_min',
		'ai_blog_posts_word_count_max',
		'ai_blog_posts_website_context',
		'ai_blog_posts_seo_enabled',
		'ai_blog_posts_trending_enabled',
		'ai_blog_posts_trending_country',
		'ai_blog_posts_budget_limit',
		'ai_blog_posts_budget_alert_email',
		'ai_blog_posts_api_verified',
		'ai_blog_posts_last_analysis',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Clear scheduled hooks
	wp_clear_scheduled_hook( 'ai_blog_posts_scheduled_generation' );
	wp_clear_scheduled_hook( 'ai_blog_posts_trending_refresh' );

	// Delete transients
	delete_transient( 'ai_blog_posts_trending_topics' );
	delete_transient( 'ai_blog_posts_api_status' );
	delete_transient( 'ai_blog_posts_models_list' );

	// Clean up any post meta created by this plugin
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_ai_blog_posts_%'" );
}

ai_blog_posts_uninstall();

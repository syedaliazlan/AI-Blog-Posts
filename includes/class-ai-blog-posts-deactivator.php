<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://devonicweb.co.uk/
 * @since      1.0.0
 *
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation,
 * primarily clearing scheduled cron events.
 *
 * @since      1.0.0
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 * @author     Ali Azlan <contact@devonicweb.co.uk>
 */
class Ai_Blog_Posts_Deactivator {

	/**
	 * Plugin deactivation handler.
	 *
	 * Clears all scheduled cron events but preserves data and settings.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		self::clear_scheduled_hooks();
	}

	/**
	 * Clear all scheduled cron hooks.
	 *
	 * @since    1.0.0
	 */
	private static function clear_scheduled_hooks() {
		$timestamp = wp_next_scheduled( 'ai_blog_posts_scheduled_generation' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ai_blog_posts_scheduled_generation' );
		}

		$timestamp = wp_next_scheduled( 'ai_blog_posts_trending_refresh' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ai_blog_posts_trending_refresh' );
		}

		// Clear all instances of our hooks
		wp_clear_scheduled_hook( 'ai_blog_posts_scheduled_generation' );
		wp_clear_scheduled_hook( 'ai_blog_posts_trending_refresh' );
	}
}

<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://devonicweb.co.uk/
 * @since             1.0.0
 * @package           Ai_Blog_Posts
 *
 * @wordpress-plugin
 * Plugin Name:       AI Blog Posts
 * Plugin URI:        https://github.com/syedaliazlan/AI-Blog-Posts
 * Description:       Automatically generate and publish high-quality, SEO-optimized blog posts using OpenAI's latest GPT-5 models. Features include GPT Image 1 generation, Yoast/RankMath/AIOSEO integration, scheduled posting, trending topics, CSV import, and comprehensive cost tracking.
 * Version:           1.0.0
 * Author:            Ali Azlan
 * Author URI:        https://devonicweb.co.uk/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ai-blog-posts
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 */
define( 'AI_BLOG_POSTS_VERSION', '1.0.0' );

/**
 * Plugin base path.
 */
define( 'AI_BLOG_POSTS_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin base URL.
 */
define( 'AI_BLOG_POSTS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'AI_BLOG_POSTS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-ai-blog-posts-activator.php
 */
function activate_ai_blog_posts() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-blog-posts-activator.php';
	Ai_Blog_Posts_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-ai-blog-posts-deactivator.php
 */
function deactivate_ai_blog_posts() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-blog-posts-deactivator.php';
	Ai_Blog_Posts_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_ai_blog_posts' );
register_deactivation_hook( __FILE__, 'deactivate_ai_blog_posts' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-ai-blog-posts.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_ai_blog_posts() {

	$plugin = new Ai_Blog_Posts();
	$plugin->run();

}
run_ai_blog_posts();

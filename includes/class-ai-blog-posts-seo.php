<?php

/**
 * SEO plugin integration
 *
 * @link       https://devonicweb.co.uk/
 * @since      1.0.0
 *
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 */

/**
 * Handles integration with Yoast SEO and RankMath.
 *
 * @since      1.0.0
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 * @author     Ali Azlan <contact@devonicweb.co.uk>
 */
class Ai_Blog_Posts_SEO {

	/**
	 * Check if Yoast SEO is active.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function is_yoast_active() {
		return defined( 'WPSEO_VERSION' );
	}

	/**
	 * Check if RankMath is active.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function is_rankmath_active() {
		return class_exists( 'RankMath' );
	}

	/**
	 * Get the active SEO plugin.
	 *
	 * @since    1.0.0
	 * @return   string|null    'yoast', 'rankmath', or null.
	 */
	public function get_active_plugin() {
		if ( $this->is_yoast_active() ) {
			return 'yoast';
		}
		if ( $this->is_rankmath_active() ) {
			return 'rankmath';
		}
		return null;
	}

	/**
	 * Set SEO meta for a post.
	 *
	 * @since    1.0.0
	 * @param    int   $post_id    Post ID.
	 * @param    array $seo_data   SEO data array.
	 * @return   bool              Success status.
	 */
	public function set_post_meta( $post_id, $seo_data ) {
		if ( empty( $seo_data ) || ! Ai_Blog_Posts_Settings::get( 'seo_enabled' ) ) {
			return false;
		}

		$meta_description = $seo_data['meta_description'] ?? '';
		$focus_keyword = $seo_data['focus_keyword'] ?? '';
		$seo_title = $seo_data['seo_title'] ?? '';

		$plugin = $this->get_active_plugin();

		switch ( $plugin ) {
			case 'yoast':
				return $this->set_yoast_meta( $post_id, $meta_description, $focus_keyword, $seo_title );
			
			case 'rankmath':
				return $this->set_rankmath_meta( $post_id, $meta_description, $focus_keyword, $seo_title );
			
			default:
				// Store as custom post meta for themes that might use it
				return $this->set_generic_meta( $post_id, $meta_description, $focus_keyword, $seo_title );
		}
	}

	/**
	 * Set Yoast SEO meta.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id          Post ID.
	 * @param    string $meta_description Meta description.
	 * @param    string $focus_keyword    Focus keyword.
	 * @param    string $seo_title        SEO title.
	 * @return   bool                     Success status.
	 */
	private function set_yoast_meta( $post_id, $meta_description, $focus_keyword, $seo_title ) {
		$success = true;

		if ( $meta_description ) {
			$success = $success && update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_description );
		}

		if ( $focus_keyword ) {
			$success = $success && update_post_meta( $post_id, '_yoast_wpseo_focuskw', $focus_keyword );
		}

		if ( $seo_title ) {
			$success = $success && update_post_meta( $post_id, '_yoast_wpseo_title', $seo_title );
		}

		// Set additional Yoast meta for better integration
		update_post_meta( $post_id, '_yoast_wpseo_content_score', 0 ); // Will be recalculated by Yoast
		update_post_meta( $post_id, '_yoast_wpseo_estimated-reading-time-minutes', 0 );

		return $success;
	}

	/**
	 * Set RankMath SEO meta.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id          Post ID.
	 * @param    string $meta_description Meta description.
	 * @param    string $focus_keyword    Focus keyword.
	 * @param    string $seo_title        SEO title.
	 * @return   bool                     Success status.
	 */
	private function set_rankmath_meta( $post_id, $meta_description, $focus_keyword, $seo_title ) {
		$success = true;

		if ( $meta_description ) {
			$success = $success && update_post_meta( $post_id, 'rank_math_description', $meta_description );
		}

		if ( $focus_keyword ) {
			$success = $success && update_post_meta( $post_id, 'rank_math_focus_keyword', $focus_keyword );
		}

		if ( $seo_title ) {
			$success = $success && update_post_meta( $post_id, 'rank_math_title', $seo_title );
		}

		// Set additional RankMath meta
		update_post_meta( $post_id, 'rank_math_seo_score', 0 ); // Will be recalculated
		update_post_meta( $post_id, 'rank_math_robots', array( 'index' ) );

		return $success;
	}

	/**
	 * Set generic SEO meta for themes without SEO plugins.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id          Post ID.
	 * @param    string $meta_description Meta description.
	 * @param    string $focus_keyword    Focus keyword.
	 * @param    string $seo_title        SEO title.
	 * @return   bool                     Success status.
	 */
	private function set_generic_meta( $post_id, $meta_description, $focus_keyword, $seo_title ) {
		$success = true;

		if ( $meta_description ) {
			$success = $success && update_post_meta( $post_id, '_ai_blog_posts_meta_description', $meta_description );
		}

		if ( $focus_keyword ) {
			$success = $success && update_post_meta( $post_id, '_ai_blog_posts_focus_keyword', $focus_keyword );
		}

		if ( $seo_title ) {
			$success = $success && update_post_meta( $post_id, '_ai_blog_posts_seo_title', $seo_title );
		}

		return $success;
	}

	/**
	 * Get SEO meta for a post.
	 *
	 * @since    1.0.0
	 * @param    int $post_id    Post ID.
	 * @return   array           SEO data.
	 */
	public function get_post_meta( $post_id ) {
		$plugin = $this->get_active_plugin();

		switch ( $plugin ) {
			case 'yoast':
				return array(
					'meta_description' => get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
					'focus_keyword'    => get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ),
					'seo_title'        => get_post_meta( $post_id, '_yoast_wpseo_title', true ),
				);
			
			case 'rankmath':
				return array(
					'meta_description' => get_post_meta( $post_id, 'rank_math_description', true ),
					'focus_keyword'    => get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
					'seo_title'        => get_post_meta( $post_id, 'rank_math_title', true ),
				);
			
			default:
				return array(
					'meta_description' => get_post_meta( $post_id, '_ai_blog_posts_meta_description', true ),
					'focus_keyword'    => get_post_meta( $post_id, '_ai_blog_posts_focus_keyword', true ),
					'seo_title'        => get_post_meta( $post_id, '_ai_blog_posts_seo_title', true ),
				);
		}
	}

	/**
	 * Add meta description to head if no SEO plugin is active.
	 *
	 * @since    1.0.0
	 */
	public function maybe_add_meta_description() {
		if ( $this->get_active_plugin() !== null ) {
			return; // Let the SEO plugin handle it
		}

		if ( ! is_singular( 'post' ) ) {
			return;
		}

		$post_id = get_the_ID();
		$meta_description = get_post_meta( $post_id, '_ai_blog_posts_meta_description', true );

		if ( $meta_description ) {
			echo '<meta name="description" content="' . esc_attr( $meta_description ) . '">' . "\n";
		}
	}

	/**
	 * Get SEO status information.
	 *
	 * @since    1.0.0
	 * @return   array    Status info.
	 */
	public function get_status() {
		$plugin = $this->get_active_plugin();

		return array(
			'plugin_active'   => $plugin !== null,
			'plugin_name'     => $plugin,
			'plugin_version'  => $this->get_plugin_version( $plugin ),
			'enabled'         => Ai_Blog_Posts_Settings::get( 'seo_enabled' ),
		);
	}

	/**
	 * Get SEO plugin version.
	 *
	 * @since    1.0.0
	 * @param    string|null $plugin    Plugin identifier.
	 * @return   string                 Version string.
	 */
	private function get_plugin_version( $plugin ) {
		switch ( $plugin ) {
			case 'yoast':
				return defined( 'WPSEO_VERSION' ) ? WPSEO_VERSION : '';
			case 'rankmath':
				return defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : '';
			default:
				return '';
		}
	}
}


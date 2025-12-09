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
	 * Check if All In One SEO is active.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function is_aioseo_active() {
		return defined( 'AIOSEO_VERSION' ) || class_exists( 'AIOSEO\\Plugin\\AIOSEO' );
	}

	/**
	 * Get the active SEO plugin.
	 *
	 * @since    1.0.0
	 * @return   string|null    'yoast', 'rankmath', 'aioseo', or null.
	 */
	public function get_active_plugin() {
		if ( $this->is_yoast_active() ) {
			return 'yoast';
		}
		if ( $this->is_rankmath_active() ) {
			return 'rankmath';
		}
		if ( $this->is_aioseo_active() ) {
			return 'aioseo';
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
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'AI Blog Posts SEO: Skipped for post %d - data_empty=%s, seo_enabled=%s',
					$post_id,
					empty( $seo_data ) ? 'yes' : 'no',
					Ai_Blog_Posts_Settings::get( 'seo_enabled' ) ? 'yes' : 'no'
				) );
			}
			return false;
		}

		$meta_description = $seo_data['meta_description'] ?? '';
		$focus_keyword = $seo_data['focus_keyword'] ?? '';
		$seo_title = $seo_data['seo_title'] ?? '';

		$plugin = $this->get_active_plugin();

		// Log for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'AI Blog Posts SEO: Post %d - Plugin: %s, Data: %s',
				$post_id,
				$plugin ?? 'none',
				wp_json_encode( $seo_data )
			) );
		}

		switch ( $plugin ) {
			case 'yoast':
				return $this->set_yoast_meta( $post_id, $meta_description, $focus_keyword, $seo_title );
			
			case 'rankmath':
				return $this->set_rankmath_meta( $post_id, $meta_description, $focus_keyword, $seo_title );
			
			case 'aioseo':
				return $this->set_aioseo_meta( $post_id, $meta_description, $focus_keyword, $seo_title );
			
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
		$updated = false;

		if ( ! empty( $meta_description ) ) {
			// Delete first to ensure fresh value, then add
			delete_post_meta( $post_id, '_yoast_wpseo_metadesc' );
			$result = update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_text_field( $meta_description ) );
			if ( $result !== false ) {
				$updated = true;
			}
		}

		if ( ! empty( $focus_keyword ) ) {
			delete_post_meta( $post_id, '_yoast_wpseo_focuskw' );
			$result = update_post_meta( $post_id, '_yoast_wpseo_focuskw', sanitize_text_field( $focus_keyword ) );
			if ( $result !== false ) {
				$updated = true;
			}
		}

		if ( ! empty( $seo_title ) ) {
			delete_post_meta( $post_id, '_yoast_wpseo_title' );
			$result = update_post_meta( $post_id, '_yoast_wpseo_title', sanitize_text_field( $seo_title ) );
			if ( $result !== false ) {
				$updated = true;
			}
		}

		// Set additional Yoast meta for better integration
		update_post_meta( $post_id, '_yoast_wpseo_content_score', 0 ); // Will be recalculated by Yoast
		update_post_meta( $post_id, '_yoast_wpseo_estimated-reading-time-minutes', 0 );

		// Log for debugging if WP_DEBUG is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'AI Blog Posts SEO: Post %d - Yoast meta set: desc=%s, keyword=%s, title=%s',
				$post_id,
				! empty( $meta_description ) ? 'yes' : 'no',
				! empty( $focus_keyword ) ? 'yes' : 'no',
				! empty( $seo_title ) ? 'yes' : 'no'
			) );
		}

		return $updated;
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
		$updated = false;

		if ( ! empty( $meta_description ) ) {
			delete_post_meta( $post_id, 'rank_math_description' );
			$result = update_post_meta( $post_id, 'rank_math_description', sanitize_text_field( $meta_description ) );
			if ( $result !== false ) {
				$updated = true;
			}
		}

		if ( ! empty( $focus_keyword ) ) {
			delete_post_meta( $post_id, 'rank_math_focus_keyword' );
			$result = update_post_meta( $post_id, 'rank_math_focus_keyword', sanitize_text_field( $focus_keyword ) );
			if ( $result !== false ) {
				$updated = true;
			}
		}

		if ( ! empty( $seo_title ) ) {
			delete_post_meta( $post_id, 'rank_math_title' );
			$result = update_post_meta( $post_id, 'rank_math_title', sanitize_text_field( $seo_title ) );
			if ( $result !== false ) {
				$updated = true;
			}
		}

		// Set additional RankMath meta
		update_post_meta( $post_id, 'rank_math_seo_score', 0 ); // Will be recalculated
		update_post_meta( $post_id, 'rank_math_robots', array( 'index' ) );

		return $updated;
	}

	/**
	 * Set All In One SEO meta.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id          Post ID.
	 * @param    string $meta_description Meta description.
	 * @param    string $focus_keyword    Focus keyword.
	 * @param    string $seo_title        SEO title.
	 * @return   bool                     Success status.
	 */
	private function set_aioseo_meta( $post_id, $meta_description, $focus_keyword, $seo_title ) {
		$success = true;

		// AIOSEO stores data in a custom table, but also supports post meta for compatibility
		// The post meta approach works for both AIOSEO Lite and Pro
		
		if ( $meta_description ) {
			$success = $success && update_post_meta( $post_id, '_aioseo_description', $meta_description );
		}

		if ( $focus_keyword ) {
			// AIOSEO stores focus keyphrase in a JSON format within _aioseo_keywords
			$keyphrases = array(
				'focus' => array(
					'keyphrase' => $focus_keyword,
				),
			);
			$success = $success && update_post_meta( $post_id, '_aioseo_keywords', wp_json_encode( $keyphrases ) );
		}

		if ( $seo_title ) {
			$success = $success && update_post_meta( $post_id, '_aioseo_title', $seo_title );
		}

		// Try to use AIOSEO's API if available (for proper database storage)
		if ( function_exists( 'aioseo' ) && method_exists( aioseo()->meta, 'savePostMeta' ) ) {
			$aioseo_data = array();
			if ( $seo_title ) {
				$aioseo_data['title'] = $seo_title;
			}
			if ( $meta_description ) {
				$aioseo_data['description'] = $meta_description;
			}
			if ( $focus_keyword ) {
				$aioseo_data['keyphrases'] = array(
					'focus' => array( 'keyphrase' => $focus_keyword ),
				);
			}
			
			if ( ! empty( $aioseo_data ) ) {
				try {
					aioseo()->meta->savePostMeta( $post_id, $aioseo_data );
				} catch ( Exception $e ) {
					// Fallback to post meta already set above
				}
			}
		}

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
			
			case 'aioseo':
				$focus_keyword = '';
				$keywords_json = get_post_meta( $post_id, '_aioseo_keywords', true );
				if ( $keywords_json ) {
					$keywords = json_decode( $keywords_json, true );
					$focus_keyword = $keywords['focus']['keyphrase'] ?? '';
				}
				return array(
					'meta_description' => get_post_meta( $post_id, '_aioseo_description', true ),
					'focus_keyword'    => $focus_keyword,
					'seo_title'        => get_post_meta( $post_id, '_aioseo_title', true ),
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
			case 'aioseo':
				return defined( 'AIOSEO_VERSION' ) ? AIOSEO_VERSION : '';
			default:
				return '';
		}
	}
}



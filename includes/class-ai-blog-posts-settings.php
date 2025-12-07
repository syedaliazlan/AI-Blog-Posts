<?php

/**
 * Settings management for the plugin
 *
 * @link       https://devonicweb.co.uk/
 * @since      1.0.0
 *
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 */

/**
 * Manages plugin settings with type-safe getters and setters.
 *
 * @since      1.0.0
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 * @author     Ali Azlan <contact@devonicweb.co.uk>
 */
class Ai_Blog_Posts_Settings {

	/**
	 * Option prefix for all settings.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	const PREFIX = 'ai_blog_posts_';

	/**
	 * Settings definitions with types and defaults.
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	private static $settings = array(
		'api_key' => array(
			'type'      => 'string',
			'default'   => '',
			'sanitize'  => 'sanitize_text_field',
		),
		'org_id' => array(
			'type'      => 'string',
			'default'   => '',
			'sanitize'  => 'sanitize_text_field',
		),
		'model' => array(
			'type'      => 'string',
			'default'   => 'gpt-5-mini',
			'sanitize'  => 'sanitize_text_field',
			'options'   => array( 'gpt-5.1', 'gpt-5', 'gpt-5-mini', 'gpt-5-nano', 'gpt-5-pro', 'gpt-4.1', 'gpt-4.1-mini', 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo' ),
		),
		'image_enabled' => array(
			'type'      => 'bool',
			'default'   => false,
		),
		'image_model' => array(
			'type'      => 'string',
			'default'   => 'dall-e-3',
			'sanitize'  => 'sanitize_text_field',
			'options'   => array( 'dall-e-3', 'dall-e-2' ),
		),
		'image_size' => array(
			'type'      => 'string',
			'default'   => '1792x1024',
			'sanitize'  => 'sanitize_text_field',
			'options'   => array( '1024x1024', '1792x1024', '1024x1792' ),
		),
		'schedule_enabled' => array(
			'type'      => 'bool',
			'default'   => false,
		),
		'schedule_frequency' => array(
			'type'      => 'string',
			'default'   => 'daily',
			'sanitize'  => 'sanitize_text_field',
			'options'   => array( 'hourly', 'twicedaily', 'daily', 'weekly' ),
		),
		'schedule_time' => array(
			'type'      => 'string',
			'default'   => '09:00',
			'sanitize'  => 'sanitize_text_field',
		),
		'max_posts_per_day' => array(
			'type'      => 'int',
			'default'   => 1,
			'sanitize'  => 'absint',
			'min'       => 1,
			'max'       => 10,
		),
		'post_status' => array(
			'type'      => 'string',
			'default'   => 'draft',
			'sanitize'  => 'sanitize_text_field',
			'options'   => array( 'publish', 'draft', 'pending' ),
		),
		'default_author' => array(
			'type'      => 'int',
			'default'   => 1,
			'sanitize'  => 'absint',
		),
		'categories' => array(
			'type'      => 'array',
			'default'   => array(),
		),
		'humanize_level' => array(
			'type'      => 'int',
			'default'   => 3,
			'sanitize'  => 'absint',
			'min'       => 1,
			'max'       => 5,
		),
		'word_count_min' => array(
			'type'      => 'int',
			'default'   => 800,
			'sanitize'  => 'absint',
			'min'       => 300,
			'max'       => 5000,
		),
		'word_count_max' => array(
			'type'      => 'int',
			'default'   => 1500,
			'sanitize'  => 'absint',
			'min'       => 500,
			'max'       => 10000,
		),
		'website_context' => array(
			'type'      => 'string',
			'default'   => '',
			'sanitize'  => 'wp_kses_post',
		),
		'seo_enabled' => array(
			'type'      => 'bool',
			'default'   => true,
		),
		'trending_enabled' => array(
			'type'      => 'bool',
			'default'   => false,
		),
		'trending_country' => array(
			'type'      => 'string',
			'default'   => 'US',
			'sanitize'  => 'sanitize_text_field',
		),
		'budget_limit' => array(
			'type'      => 'float',
			'default'   => 0,
			'min'       => 0,
		),
		'budget_alert_email' => array(
			'type'      => 'string',
			'default'   => '',
			'sanitize'  => 'sanitize_email',
		),
		'api_verified' => array(
			'type'      => 'bool',
			'default'   => false,
		),
		'last_analysis' => array(
			'type'      => 'string',
			'default'   => '',
		),
	);

	/**
	 * Get a setting value.
	 *
	 * @since    1.0.0
	 * @param    string $key    The setting key (without prefix).
	 * @return   mixed          The setting value.
	 */
	public static function get( $key ) {
		if ( ! isset( self::$settings[ $key ] ) ) {
			return null;
		}

		$setting = self::$settings[ $key ];
		$value = get_option( self::PREFIX . $key, $setting['default'] );

		// Handle encrypted values
		if ( 'encrypted' === $setting['type'] && ! empty( $value ) ) {
			$value = Ai_Blog_Posts_Encryption::decrypt( $value );
		}

		// Type casting
		switch ( $setting['type'] ) {
			case 'bool':
				return (bool) $value;
			case 'int':
				$value = (int) $value;
				if ( isset( $setting['min'] ) ) {
					$value = max( $setting['min'], $value );
				}
				if ( isset( $setting['max'] ) ) {
					$value = min( $setting['max'], $value );
				}
				return $value;
			case 'float':
				return (float) $value;
			case 'array':
				return is_array( $value ) ? $value : array();
			default:
				return $value;
		}
	}

	/**
	 * Set a setting value.
	 *
	 * @since    1.0.0
	 * @param    string $key      The setting key (without prefix).
	 * @param    mixed  $value    The value to set.
	 * @return   bool             True on success, false on failure.
	 */
	public static function set( $key, $value ) {
		if ( ! isset( self::$settings[ $key ] ) ) {
			return false;
		}

		$setting = self::$settings[ $key ];

		// Sanitize
		if ( isset( $setting['sanitize'] ) && is_callable( $setting['sanitize'] ) ) {
			$value = call_user_func( $setting['sanitize'], $value );
		}

		// Validate options
		if ( isset( $setting['options'] ) && ! in_array( $value, $setting['options'], true ) ) {
			$value = $setting['default'];
		}

		// Handle encrypted values
		if ( 'encrypted' === $setting['type'] && ! empty( $value ) ) {
			$value = Ai_Blog_Posts_Encryption::encrypt( $value );
		}

		// Handle boolean - properly convert string values
		if ( 'bool' === $setting['type'] ) {
			if ( is_string( $value ) ) {
				// Convert string "true"/"false"/"1"/"0" properly
				$value = in_array( strtolower( $value ), array( 'true', '1', 'yes', 'on' ), true );
			} else {
				$value = (bool) $value;
			}
		}

		// Handle integer with bounds
		if ( 'int' === $setting['type'] ) {
			$value = (int) $value;
			if ( isset( $setting['min'] ) ) {
				$value = max( $setting['min'], $value );
			}
			if ( isset( $setting['max'] ) ) {
				$value = min( $setting['max'], $value );
			}
		}

		return update_option( self::PREFIX . $key, $value );
	}

	/**
	 * Get all settings as an array.
	 *
	 * @since    1.0.0
	 * @param    bool $mask_sensitive    Whether to mask sensitive data.
	 * @return   array                   All settings.
	 */
	public static function get_all( $mask_sensitive = false ) {
		$all = array();

		foreach ( array_keys( self::$settings ) as $key ) {
			$all[ $key ] = self::get( $key );
		}

		return $all;
	}

	/**
	 * Get setting definition.
	 *
	 * @since    1.0.0
	 * @param    string $key    The setting key.
	 * @return   array|null     The setting definition or null.
	 */
	public static function get_definition( $key ) {
		return isset( self::$settings[ $key ] ) ? self::$settings[ $key ] : null;
	}

	/**
	 * Get all setting definitions.
	 *
	 * @since    1.0.0
	 * @return   array    All setting definitions.
	 */
	public static function get_definitions() {
		return self::$settings;
	}

	/**
	 * Get available models with pricing info.
	 *
	 * @since    1.0.0
	 * @return   array    Models with pricing.
	 */
	public static function get_models() {
		return array(
			// GPT-5 Series (Latest)
			'gpt-5.1' => array(
				'name'             => 'GPT-5.1',
				'description'      => 'Latest flagship - best for coding and agentic tasks',
				'input_cost'       => 1.25,  // per 1M tokens
				'output_cost'      => 10.00, // per 1M tokens
				'context_window'   => 1000000,
				'recommended'      => true,
			),
			'gpt-5' => array(
				'name'             => 'GPT-5',
				'description'      => 'Main GPT-5 model - excellent for all tasks',
				'input_cost'       => 1.25,
				'output_cost'      => 10.00,
				'context_window'   => 1000000,
				'recommended'      => true,
			),
			'gpt-5-mini' => array(
				'name'             => 'GPT-5 Mini',
				'description'      => 'Fast & affordable - great for most content',
				'input_cost'       => 0.25,
				'output_cost'      => 2.00,
				'context_window'   => 1000000,
				'recommended'      => true,
			),
			'gpt-5-nano' => array(
				'name'             => 'GPT-5 Nano',
				'description'      => 'Ultra-fast, cheapest - good for simple tasks',
				'input_cost'       => 0.05,
				'output_cost'      => 0.40,
				'context_window'   => 1000000,
				'recommended'      => false,
			),
			'gpt-5-pro' => array(
				'name'             => 'GPT-5 Pro',
				'description'      => 'Premium reasoning - best for complex analysis',
				'input_cost'       => 15.00,
				'output_cost'      => 120.00,
				'context_window'   => 1000000,
				'recommended'      => false,
			),
			// GPT-4.1 Series
			'gpt-4.1' => array(
				'name'             => 'GPT-4.1',
				'description'      => 'Updated GPT-4 - excellent quality',
				'input_cost'       => 2.00,
				'output_cost'      => 8.00,
				'context_window'   => 128000,
				'recommended'      => false,
			),
			'gpt-4.1-mini' => array(
				'name'             => 'GPT-4.1 Mini',
				'description'      => 'Efficient GPT-4.1 - good balance',
				'input_cost'       => 0.40,
				'output_cost'      => 1.60,
				'context_window'   => 128000,
				'recommended'      => false,
			),
			// Legacy Models (still available)
			'gpt-4o' => array(
				'name'             => 'GPT-4o (Legacy)',
				'description'      => 'Previous flagship - still excellent',
				'input_cost'       => 2.50,
				'output_cost'      => 10.00,
				'context_window'   => 128000,
				'recommended'      => false,
			),
			'gpt-4o-mini' => array(
				'name'             => 'GPT-4o Mini (Legacy)',
				'description'      => 'Previous best value model',
				'input_cost'       => 0.15,
				'output_cost'      => 0.60,
				'context_window'   => 128000,
				'recommended'      => false,
			),
			'gpt-4-turbo' => array(
				'name'             => 'GPT-4 Turbo (Legacy)',
				'description'      => 'Older GPT-4 variant',
				'input_cost'       => 10.00,
				'output_cost'      => 30.00,
				'context_window'   => 128000,
				'recommended'      => false,
			),
		);
	}

	/**
	 * Get image model pricing.
	 *
	 * @since    1.0.0
	 * @return   array    Image models with pricing.
	 */
	public static function get_image_models() {
		return array(
			'dall-e-3' => array(
				'name'        => 'DALL-E 3',
				'description' => 'Latest image model with best quality',
				'pricing'     => array(
					'1024x1024'  => 0.040,
					'1792x1024'  => 0.080,
					'1024x1792'  => 0.080,
				),
			),
			'dall-e-2' => array(
				'name'        => 'DALL-E 2',
				'description' => 'Previous model, more affordable',
				'pricing'     => array(
					'1024x1024' => 0.020,
					'512x512'   => 0.018,
					'256x256'   => 0.016,
				),
			),
		);
	}

	/**
	 * Check if the API is configured.
	 *
	 * @since    1.0.0
	 * @return   bool    True if API key is set.
	 */
	public static function is_configured() {
		$api_key = self::get( 'api_key' );
		return ! empty( $api_key );
	}

	/**
	 * Check if the API is verified.
	 *
	 * @since    1.0.0
	 * @return   bool    True if API key is verified.
	 */
	public static function is_verified() {
		return self::is_configured() && self::get( 'api_verified' );
	}
}


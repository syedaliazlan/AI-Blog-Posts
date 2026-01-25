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
			'default'   => 'gpt-5.2',
			'sanitize'  => 'sanitize_text_field',
			'options'   => array( 'gpt-5.2' ),
		),
		'image_enabled' => array(
			'type'      => 'bool',
			'default'   => false,
		),
		'pexels_api_key' => array(
			'type'      => 'string',
			'default'   => '',
			'sanitize'  => 'sanitize_text_field',
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
		'schedule_time_2' => array(
			'type'      => 'string',
			'default'   => '21:00',
			'sanitize'  => 'sanitize_text_field',
		),
		'schedule_day' => array(
			'type'      => 'int',
			'default'   => 1, // Monday
			'sanitize'  => 'absint',
			'min'       => 0,
			'max'       => 6,
		),
		'cron_secret' => array(
			'type'      => 'string',
			'default'   => '',
			'sanitize'  => 'sanitize_text_field',
		),
		'external_cron_enabled' => array(
			'type'      => 'bool',
			'default'   => false,
		),
		'cronjob_org_api_key' => array(
			'type'      => 'string',
			'default'   => '',
			'sanitize'  => 'sanitize_text_field',
		),
		'cronjob_org_job_id' => array(
			'type'      => 'int',
			'default'   => 0,
			'sanitize'  => 'absint',
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
		// Pexels settings
		'pexels_orientation' => array(
			'type'      => 'string',
			'default'   => 'landscape',
			'sanitize'  => 'sanitize_text_field',
			'options'   => array( 'landscape', 'portrait', 'square' ),
		),
		'pexels_inline_images' => array(
			'type'      => 'int',
			'default'   => 3,
			'sanitize'  => 'absint',
			'min'       => 0,
			'max'       => 5,
		),
		'use_topic_as_title' => array(
			'type'      => 'bool',
			'default'   => false,
		),
		'word_count' => array(
			'type'      => 'int',
			'default'   => 1000,
			'sanitize'  => 'absint',
			'min'       => 500,
			'max'       => 5000,
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

		// Validate model setting - auto-correct invalid models
		if ( 'model' === $key && ! empty( $value ) ) {
			$valid_models = self::get_models();
			if ( ! isset( $valid_models[ $value ] ) ) {
				// Invalid model stored, reset to default (gpt-5.2)
				$value = $setting['default'];
				update_option( self::PREFIX . $key, $value );
			}
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
	 * Only GPT-5.2 is supported.
	 *
	 * @since    1.0.0
	 * @return   array    Models with pricing.
	 */
	public static function get_models() {
		return array(
			'gpt-5.2' => array(
				'name'             => 'GPT-5.2',
				'description'      => 'Latest flagship model for coding and agentic tasks',
				'input_cost'       => 2.00,  // per 1M tokens (estimated)
				'output_cost'      => 10.00, // per 1M tokens (estimated)
				'context_window'   => 400000,
				'recommended'      => true,
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

	/**
	 * Get or generate the cron secret key.
	 * The secret is used to authenticate external cron requests.
	 *
	 * @since    1.2.0
	 * @param    bool $regenerate    Whether to regenerate the secret.
	 * @return   string              The cron secret key.
	 */
	public static function get_cron_secret( $regenerate = false ) {
		$secret = self::get( 'cron_secret' );
		
		if ( empty( $secret ) || $regenerate ) {
			// Generate a secure random secret
			$secret = wp_generate_password( 32, false, false );
			self::set( 'cron_secret', $secret );
		}
		
		return $secret;
	}

	/**
	 * Get the external cron URL for use with cron-job.org or similar services.
	 *
	 * @since    1.2.0
	 * @return   string    The full cron URL with secret.
	 */
	public static function get_cron_url() {
		$secret = self::get_cron_secret();
		return add_query_arg(
			array(
				'ai_blog_posts_cron' => $secret,
			),
			home_url( '/' )
		);
	}

	/**
	 * Verify a cron secret from a request.
	 *
	 * @since    1.2.0
	 * @param    string $provided_secret    The secret provided in the request.
	 * @return   bool                       True if valid, false otherwise.
	 */
	public static function verify_cron_secret( $provided_secret ) {
		if ( empty( $provided_secret ) ) {
			return false;
		}
		
		$stored_secret = self::get( 'cron_secret' );
		
		if ( empty( $stored_secret ) ) {
			return false;
		}
		
		return hash_equals( $stored_secret, $provided_secret );
	}
}


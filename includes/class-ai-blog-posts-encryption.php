<?php

/**
 * Encryption helper for secure API key storage
 *
 * @link       https://devonicweb.co.uk/
 * @since      1.0.0
 *
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 */

/**
 * Handles encryption and decryption of sensitive data.
 *
 * Uses OpenSSL with WordPress salts for secure API key storage.
 *
 * @since      1.0.0
 * @package    Ai_Blog_Posts
 * @subpackage Ai_Blog_Posts/includes
 * @author     Ali Azlan <contact@devonicweb.co.uk>
 */
class Ai_Blog_Posts_Encryption {

	/**
	 * Encryption method to use.
	 *
	 * @since    1.0.0
	 * @var      string
	 */
	private const METHOD = 'AES-256-CBC';

	/**
	 * Get the encryption key derived from WordPress salts.
	 *
	 * @since    1.0.0
	 * @return   string    The encryption key.
	 */
	private static function get_key() {
		$salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'ai-blog-posts-default-key';
		return hash( 'sha256', $salt, true );
	}

	/**
	 * Get the initialization vector derived from WordPress salts.
	 *
	 * @since    1.0.0
	 * @return   string    The IV for encryption.
	 */
	private static function get_iv() {
		$salt = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'ai-blog-posts-default-iv';
		return substr( hash( 'sha256', $salt ), 0, 16 );
	}

	/**
	 * Encrypt a string.
	 *
	 * @since    1.0.0
	 * @param    string $data    The data to encrypt.
	 * @return   string          The encrypted data (base64 encoded).
	 */
	public static function encrypt( $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			// Fallback: base64 encode if OpenSSL not available
			return base64_encode( $data );
		}

		$encrypted = openssl_encrypt(
			$data,
			self::METHOD,
			self::get_key(),
			0,
			self::get_iv()
		);

		return $encrypted ? $encrypted : '';
	}

	/**
	 * Decrypt a string.
	 *
	 * @since    1.0.0
	 * @param    string $data    The encrypted data (base64 encoded).
	 * @return   string          The decrypted data.
	 */
	public static function decrypt( $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			// Fallback: base64 decode if OpenSSL not available
			return base64_decode( $data );
		}

		$decrypted = openssl_decrypt(
			$data,
			self::METHOD,
			self::get_key(),
			0,
			self::get_iv()
		);

		return $decrypted ? $decrypted : '';
	}

	/**
	 * Check if encryption is available.
	 *
	 * @since    1.0.0
	 * @return   bool    True if OpenSSL is available.
	 */
	public static function is_available() {
		return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' );
	}

	/**
	 * Mask a string for display (show only last 4 characters).
	 *
	 * @since    1.0.0
	 * @param    string $data    The data to mask.
	 * @return   string          The masked data.
	 */
	public static function mask( $data ) {
		if ( empty( $data ) || strlen( $data ) < 8 ) {
			return str_repeat( '•', 20 );
		}

		$visible = substr( $data, -4 );
		return str_repeat( '•', 20 ) . $visible;
	}
}


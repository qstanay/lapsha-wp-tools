<?php
/**
 * Cleanup exceptions declared by a compatibility integration.
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Typed bag of rule keys. Scanner and cleaner never switch on plugin names;
 * they ask the registry to interpret these keys.
 *
 * Known keys today:
 * - exclude_post_types: CPT slugs skipped for auto-draft and trash cleanup.
 *
 * Add a new key here, interpret it in Lapsha_Database_Compat, then ship it
 * from any integration. Do not add plugin-specific branches in the cleaner.
 */
class Lapsha_Database_Compat_Rules {

	const EXCLUDE_POST_TYPES = 'exclude_post_types';

	/**
	 * @var array<string, mixed>
	 */
	private $data = array();

	/**
	 * @param array<string, mixed> $data Rule map.
	 */
	private function __construct( $data ) {
		$this->data = $data;
	}

	/**
	 * @param array<string, mixed> $data Rule map.
	 * @return self
	 */
	public static function from_array( $data ) {
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		return new self( $data );
	}

	/**
	 * @return self
	 */
	public static function none() {
		return new self( array() );
	}

	/**
	 * @param string $key     Rule key.
	 * @param mixed  $default Default when the key is absent.
	 * @return mixed
	 */
	public function get( $key, $default = array() ) {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $default;
	}

	/**
	 * @return string[]
	 */
	public function exclude_post_types() {
		return $this->sanitize_key_list( $this->get( self::EXCLUDE_POST_TYPES, array() ) );
	}

	/**
	 * @param mixed $values Raw list.
	 * @return string[]
	 */
	private function sanitize_key_list( $values ) {
		if ( ! is_array( $values ) ) {
			return array();
		}

		$out = array();
		foreach ( $values as $value ) {
			$key = sanitize_key( (string) $value );
			if ( '' !== $key && ! in_array( $key, $out, true ) ) {
				$out[] = $key;
			}
		}

		return $out;
	}
}

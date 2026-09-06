<?php
/**
 * Shared probes for “is this plugin or theme running?”.
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Integrations should call these helpers instead of copying admin includes.
 */
class Lapsha_Database_Compat_Detect {

	/**
	 * @param string $name Constant name.
	 * @return bool
	 */
	public static function constant( $name ) {
		return defined( $name );
	}

	/**
	 * @param string[] $names Constant names.
	 * @return bool
	 */
	public static function any_constant( $names ) {
		foreach ( $names as $name ) {
			if ( self::constant( $name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $class Class name.
	 * @return bool
	 */
	public static function class_name( $class ) {
		return class_exists( $class, false );
	}

	/**
	 * @param string $name Function name.
	 * @return bool
	 */
	public static function func( $name ) {
		return function_exists( $name );
	}

	/**
	 * @param string[] $names Function names.
	 * @return bool
	 */
	public static function any_func( $names ) {
		foreach ( $names as $name ) {
			if ( self::func( $name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $hook Action name.
	 * @return bool
	 */
	public static function hook_fired( $hook ) {
		return (bool) did_action( $hook );
	}

	/**
	 * @param string[] $templates Parent theme directories (e.g. Divi).
	 * @return bool
	 */
	public static function parent_theme( $templates ) {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return false;
		}

		$theme = wp_get_theme();
		if ( ! $theme instanceof WP_Theme ) {
			return false;
		}

		return in_array( $theme->get_template(), $templates, true );
	}

	/**
	 * @param string $plugin_file Plugin basename, e.g. elementor/elementor.php.
	 * @return bool
	 */
	public static function plugin( $plugin_file ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			$plugin_php = ABSPATH . 'wp-admin/includes/plugin.php';
			if ( ! is_readable( $plugin_php ) ) {
				return false;
			}
			require_once $plugin_php;
		}

		return function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin_file );
	}
}

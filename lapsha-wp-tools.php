<?php
/**
 * Plugin Name:       Lapsha WP Tools
 * Plugin URI:        https://github.com/qstanay/lapsha-wp-tools
 * Description:       Free and open-source toolkit for WordPress — database, debugging, performance and administration tools.
 * Version:           0.1.1
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            qstanay
 * Author URI:        https://github.com/qstanay
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lapsha-wp-tools
 * Domain Path:       /languages
 *
 * @package Lapsha_WP_Tools
 */

defined( 'ABSPATH' ) || exit;

define( 'LAPSHA_WP_TOOLS_VERSION', '0.1.1' );
define( 'LAPSHA_WP_TOOLS_FILE', __FILE__ );
define( 'LAPSHA_WP_TOOLS_DIR', plugin_dir_path( __FILE__ ) );
define( 'LAPSHA_WP_TOOLS_URL', plugin_dir_url( __FILE__ ) );
define( 'LAPSHA_WP_TOOLS_BASENAME', plugin_basename( __FILE__ ) );

require_once LAPSHA_WP_TOOLS_DIR . 'includes/helpers.php';
require_once LAPSHA_WP_TOOLS_DIR . 'includes/class-lapsha-module.php';
require_once LAPSHA_WP_TOOLS_DIR . 'includes/class-lapsha-security.php';
require_once LAPSHA_WP_TOOLS_DIR . 'includes/class-lapsha-core.php';

/**
 * Retrieve the core singleton and boot the plugin.
 *
 * @return Lapsha_Core
 */
function lapsha_wp_tools() {
	return Lapsha_Core::instance();
}

add_action( 'plugins_loaded', 'lapsha_wp_tools' );

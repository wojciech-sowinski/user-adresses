<?php

/**
 * Plugin Name: User Addresses
 * Plugin URI:
 * Description: Wtyczka do zarządzania adresami użytkowników.
 * Version: 1.3.0
 * Author:
 * Author URI:
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: user-adresses
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('USER_ADRESSES_VERSION', '1.3.9');
define('USER_ADRESSES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('USER_ADRESSES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('USER_ADRESSES_OPTION_ALLOWED_ROLES', 'user_addresses_allowed_roles');

require_once USER_ADRESSES_PLUGIN_DIR . 'src/UserAddressRepository.php';
require_once USER_ADRESSES_PLUGIN_DIR . 'src/front/Shortcodes.php';

new UserAddresses_Shortcodes();

add_action('wp_enqueue_scripts', 'user_addresses_enqueue_assets');
function user_addresses_enqueue_assets(): void {
    if (is_admin()) {
        return;
    }
    wp_enqueue_script(
        'user-addresses-main',
        USER_ADRESSES_PLUGIN_URL . 'js/main.js',
        [],
        USER_ADRESSES_VERSION,
        true
    );
    wp_enqueue_style(
        'user-addresses-main',
        USER_ADRESSES_PLUGIN_URL . 'css/main.min.css',
        [],
        USER_ADRESSES_VERSION
    );
}

if (is_admin()) {
    require_once USER_ADRESSES_PLUGIN_DIR . 'src/admin/AdminPage.php';
    new UserAddresses_AdminPage();
}


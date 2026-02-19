<?php

/**
 * Plugin Name: User Addresses
 * Plugin URI:
 * Description: Wtyczka do zarządzania adresami użytkowników.
 * Version: 1.0.0
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

define('USER_ADRESSES_VERSION', '1.0.0');
define('USER_ADRESSES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('USER_ADRESSES_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once USER_ADRESSES_PLUGIN_DIR . 'src/UserAddressRepository.php';

if (is_admin()) {
    require_once USER_ADRESSES_PLUGIN_DIR . 'src/admin/AdminPage.php';
    new UserAddresses_AdminPage();
}

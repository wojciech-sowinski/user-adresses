<?php

/**
 * Strona konfiguracyjna wtyczki User Addresses (Narzędzia).
 *
 * @package UserAddresses
 */

if (!defined('ABSPATH')) {
    exit;
}

class UserAddresses_AdminPage {

    /**
     * Slug strony w menu.
     *
     * @var string
     */
    const PAGE_SLUG = 'user-addresses-settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    /**
     * Rejestruje pozycję w menu Narzędzia.
     */
    public function register_menu(): void {
        add_submenu_page(
            'tools.php',
            __('User Addresses', 'user-adresses'),
            __('User Addresses', 'user-adresses'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    /**
     * Renderuje zawartość strony.
     */
    public function render_page(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        </div>
        <?php
    }
}

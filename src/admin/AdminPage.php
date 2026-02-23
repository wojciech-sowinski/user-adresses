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
        $manage_shortcode = '[' . \UserAddresses_Shortcodes::SHORTCODE_MANAGE . ']';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="user-addresses-settings-info" style="max-width: 640px; margin-top: 1em;">
                <h2 class="title"><?php esc_html_e('Shortcode w panelu zarządzania', 'user-adresses'); ?></h2>
                <p><?php esc_html_e('Aby umożliwić użytkownikom dodawanie i usuwanie adresów dodatkowych, osadź poniższy shortcode w panelu zarządzania (np. na stronie „Moje konto” lub w edytorze bloków strony z kontem).', 'user-adresses'); ?></p>
                <p>
                    <label for="user-addresses-manage-shortcode" class="screen-reader-text"><?php esc_html_e('Shortcode do zarządzania adresami', 'user-adresses'); ?></label>
                    <input type="text" id="user-addresses-manage-shortcode" class="regular-text code" value="<?php echo esc_attr($manage_shortcode); ?>" readonly style="width: 100%;" />
                </p>
                <p class="description">
                    <?php esc_html_e('Opcje shortcode (atrybuty):', 'user-adresses'); ?>
                    <code>title</code> — <?php esc_html_e('nagłówek sekcji (domyślnie: „Dodatkowe adresy”)', 'user-adresses'); ?>,
                    <code>class</code> — <?php esc_html_e('dodatkowa klasa CSS', 'user-adresses'); ?>
                </p>

                <h2 class="title" style="margin-top: 2em;"><?php esc_html_e('Shortcode na stronie checkout', 'user-adresses'); ?></h2>
                <p><?php esc_html_e('Shortcode do osadzenia na stronie checkout (koszyk / realizacja zamówienia), aby wyświetlić listę zapisanych adresów do wyboru:', 'user-adresses'); ?></p>
                <p>
                    <input type="text" class="regular-text code" value="[<?php echo esc_attr(\UserAddresses_Shortcodes::SHORTCODE_CHECKOUT); ?>]" readonly style="width: 100%;" />
                </p>
                <p class="description">
                    <?php esc_html_e('Atrybuty:', 'user-adresses'); ?>
                    <code>type</code> — billing | shipping | both,
                    <code>class</code> — <?php esc_html_e('dodatkowa klasa CSS', 'user-adresses'); ?>
                </p>
            </div>
        </div>
        <?php
    }
}

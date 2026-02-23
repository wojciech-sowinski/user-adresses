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

    /**
     * Klucz opcji: role z dostępem do „Moje adresy”.
     *
     * @var string
     */
    const OPTION_ALLOWED_ROLES = USER_ADRESSES_OPTION_ALLOWED_ROLES;

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Rejestruje ustawienia (Settings API).
     */
    public function register_settings(): void {
        register_setting(
            'user_addresses_settings',
            self::OPTION_ALLOWED_ROLES,
            [
                'type'              => 'array',
                'default'           => ['b2b_', 'administrator'],
                'sanitize_callback' => [$this, 'sanitize_allowed_roles'],
            ]
        );

        add_settings_section(
            'user_addresses_roles',
            __('Role z dostępem do adresów', 'user-adresses'),
            function () {
                echo '<p class="description">' . esc_html__('Wybierz role, które mogą widzieć i zarządzać dodatkowymi adresami (shortcode „Moje adresy” oraz wybór na checkout).', 'user-adresses') . '</p>';
            },
            self::PAGE_SLUG
        );

        add_settings_field(
            'user_addresses_allowed_roles_field',
            __('Dozwolone role', 'user-adresses'),
            [$this, 'render_allowed_roles_field'],
            self::PAGE_SLUG,
            'user_addresses_roles',
            ['label_for' => 'user_addresses_allowed_roles']
        );
    }

    /**
     * Sanityzuje zapisane role (tylko istniejące slugi ról).
     *
     * @param array<int, string>|mixed $value Wysłane wartości.
     * @return array<int, string>
     */
    public function sanitize_allowed_roles($value): array {
        $roles = wp_roles()->get_names();
        if (!is_array($value)) {
            $value = [];
        }
        $out = [];
        foreach ($value as $slug) {
            $slug = is_string($slug) ? $slug : '';
            if ($slug !== '' && isset($roles[$slug])) {
                $out[] = $slug;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Renderuje pole multiselect z rolami.
     */
    public function render_allowed_roles_field(): void {
        $saved = get_option(self::OPTION_ALLOWED_ROLES, ['b2b_', 'administrator']);
        if (!is_array($saved)) {
            $saved = [];
        }
        $roles = wp_roles()->get_names();
        $id    = 'user_addresses_allowed_roles';
        ?>
        <select id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr(self::OPTION_ALLOWED_ROLES); ?>[]"
                multiple="multiple"
                size="<?php echo esc_attr(min(12, max(4, count($roles)))); ?>"
                style="min-width: 220px;">
            <?php foreach ($roles as $slug => $label) : ?>
                <option value="<?php echo esc_attr($slug); ?>" <?php selected(in_array($slug, $saved, true)); ?>>
                    <?php echo esc_html(translate_user_role($label)); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('Przytrzymaj Ctrl (Cmd na Mac), aby wybrać wiele ról.', 'user-adresses'); ?>
        </p>
        <?php
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

            <form action="options.php" method="post" style="max-width: 640px; margin-top: 1em;">
                <?php
                settings_fields('user_addresses_settings');
                do_settings_sections(self::PAGE_SLUG);
                submit_button(__('Zapisz zmiany', 'user-adresses'));
                ?>
            </form>

            <div class="user-addresses-settings-info" style="max-width: 640px; margin-top: 2em;">
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

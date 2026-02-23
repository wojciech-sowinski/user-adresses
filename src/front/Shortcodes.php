<?php

/**
 * Shortcode’y wtyczki User Addresses.
 *
 * - user_addresses_manage: panel zarządzania (Moje konto) – dodawanie i usuwanie adresów dodatkowych.
 * - user_addresses_checkout: do osadzenia na stronie checkout – wybór adresu dodatkowego.
 *
 * @package UserAddresses
 */

if (!defined('ABSPATH')) {
    exit;
}

class UserAddresses_Shortcodes {

    /**
     * Shortcode: panel zarządzania adresami (np. w Moim koncie).
     */
    const SHORTCODE_MANAGE = 'user_addresses_manage';

    /**
     * Shortcode: wybór adresu na checkout.
     */
    const SHORTCODE_CHECKOUT = 'user_addresses_checkout';

    /**
     * @var UserAddressRepository
     */
    private $repository;

    /**
     * Role, które mają dostęp do przycisków „Moje adresy” (shortcode manage + checkout).
     * Użytkownik musi mieć przynajmniej jedną z tych ról, żeby zobaczyć funkcjonalność.
     *
     * @var array<int, string>
     */
    private $allowed_roles = ['b2b_', 'administrator_'];

    const SAVE_ACTION = 'user_addresses_save_address';

    public function __construct() {
        $this->repository = new UserAddressRepository();
        add_shortcode(self::SHORTCODE_MANAGE, [$this, 'render_manage']);
        add_shortcode(self::SHORTCODE_CHECKOUT, [$this, 'render_checkout']);
        add_action('admin_post_' . self::SAVE_ACTION, [$this, 'handle_save_address']);
        add_action('admin_post_nopriv_' . self::SAVE_ACTION, [$this, 'handle_save_address_nopriv']);
    }

    /**
     * Renderuje shortcode zarządzania adresami.
     * Atrybuty: title, class.
     *
     * @param array<string, string> $atts Atrybuty shortcode.
     * @return string HTML.
     */
    public function render_manage(array $atts): string {
        $atts = shortcode_atts([
            'title' => __('Dodatkowe adresy', 'user-adresses'),
            'class' => '',
        ], $atts, self::SHORTCODE_MANAGE);

        if (!is_user_logged_in() || !$this->user_can_see_addresses()) {
            return '';
        }

        $user_id   = get_current_user_id();
        $addresses = $this->repository->get_additional_addresses($user_id);
        $this->ensure_default_addresses($user_id, $addresses);
        $addresses = $this->repository->get_additional_addresses($user_id);

        $saved = isset($_GET['user_addresses_saved']) && $_GET['user_addresses_saved'] === '1';

        ob_start();
        ?>
        <div style="margin-top: 1rem;margin-bottom: 1rem;" class="user-addresses-manage <?php echo esc_attr($atts['class']); ?>">
            <?php if ($saved) : ?>
                <p class="user-addresses-manage__notice user-addresses-manage__notice--success"><?php esc_html_e('Adres zapisany.', 'user-adresses'); ?></p>
            <?php endif; ?>
            <button type="button" class="user-addresses-manage__toggle btn--orange" aria-controls="user-addresses-panel" aria-expanded="false">
                <?php esc_html_e('Moje adresy', 'user-adresses'); ?>
            </button>
            <?php $this->render_addresses_panel($addresses, $user_id); ?>
            <?php $this->render_add_address_modal(); ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Jeśli lista adresów jest pusta, dopisuje adres rozliczeniowy i adres wysyłki z WooCommerce.
     *
     * @param int   $user_id   ID użytkownika.
     * @param array $addresses Aktualna lista adresów (przed uzupełnieniem).
     */
    protected function ensure_default_addresses(int $user_id, array $addresses): void {
        if (!empty($addresses)) {
            return;
        }
        $billing  = $this->repository->get_woocommerce_billing($user_id);
        $shipping = $this->repository->get_woocommerce_shipping($user_id);
        $billing['label'] = __('Adres rozliczeniowy (domyślny)', 'user-adresses');
        $billing['type']  = 'billing';
        $shipping['label'] = __('Adres wysyłki (domyślny)', 'user-adresses');
        $shipping['type'] = 'shipping';
        $this->repository->add_additional_address($user_id, $billing);
        $this->repository->add_additional_address($user_id, $shipping);
    }

    /**
     * Funkcja pomocnicza: renderuje sekcję ukrytą (rozsuwaną) z listą zapisanych adresów i przyciskiem „Dodaj adres”.
     *
     * @param array<int, array<string, mixed>> $addresses Tablica adresów z repozytorium.
     * @param int                              $user_id   ID użytkownika (do porównania z adresami WC).
     */
    protected function render_addresses_panel(array $addresses, int $user_id): void {
        $wc_billing  = $this->repository->get_woocommerce_billing($user_id);
        $wc_shipping = $this->repository->get_woocommerce_shipping($user_id);
        $billing_assigned  = false;
        $shipping_assigned = false;
        ?>
        <div id="user-addresses-panel" class="user-addresses-manage__panel" aria-hidden="true" style="display: none;">
            <ul class="user-addresses-manage__list">
                <?php
                foreach ($addresses as $address) {
                    $matches_billing  = $this->address_matches($address, $wc_billing);
                    $matches_shipping = $this->address_matches($address, $wc_shipping);
                    $is_current_billing  = $matches_billing && !$billing_assigned;
                    $is_current_shipping = $matches_shipping && !$shipping_assigned;
                    if ($is_current_billing) $billing_assigned = true;
                    if ($is_current_shipping) $shipping_assigned = true;
                    $this->render_address_list_item($address, $is_current_billing, $is_current_shipping);
                }
                ?>
            </ul>
            <p class="user-addresses-manage__actions">
                <button type="button" class="user-addresses-modal__add btn--orange">
                    <?php esc_html_e('Dodaj adres', 'user-adresses'); ?>
                </button>
            </p>
        </div>
        <?php
    }

    /**
     * Sprawdza, czy dwa adresy są uznawane za ten sam (porównanie kluczowych pól).
     *
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return bool
     */
    protected function address_matches(array $a, array $b): bool {
        $keys = ['first_name', 'last_name', 'address_1', 'postcode', 'city', 'country'];
        foreach ($keys as $key) {
            $va = isset($a[$key]) ? trim((string) $a[$key]) : '';
            $vb = isset($b[$key]) ? trim((string) $b[$key]) : '';
            if ($va !== $vb) {
                return false;
            }
        }
        return true;
    }

    /**
     * Dane adresu do przekazania do JS (do przycisków „Ustaw rozliczeniowy” / „Ustaw wysyłki”).
     *
     * @param array<string, mixed> $address
     * @return array<string, string>
     */
    protected function address_for_js(array $address): array {
        $out = [];
        foreach (UserAddressRepository::ADDRESS_FIELDS as $key) {
            $out[$key] = isset($address[$key]) ? (string) $address[$key] : '';
        }
        $out['billing_nip'] = isset($address['billing_nip']) ? (string) $address['billing_nip'] : '';
        $out['id']    = isset($address['id']) ? (string) $address['id'] : '';
        $out['label'] = isset($address['label']) ? (string) $address['label'] : '';
        $out['type']  = isset($address['type']) ? (string) $address['type'] : 'both';
        return $out;
    }

    /**
     * Jedna pozycja na liście: panel z danymi adresu oraz przyciskami „Ustaw rozliczeniowy” / „Ustaw wysyłki” / „Edytuj”.
     * is_current_billing / is_current_shipping: czy ten adres jest aktualnie domyślnym w WC (do zaznaczenia checkboxów przy edycji – tylko jeden może być każdym).
     *
     * @param array<string, mixed> $address
     * @param bool                 $is_current_billing
     * @param bool                 $is_current_shipping
     */
    protected function render_address_list_item(array $address, bool $is_current_billing = false, bool $is_current_shipping = false): void {
        $label = $address['label'] ?? '';
        $title = $label !== '' ? $label : $this->format_address_full($address);
        $address_json = wp_json_encode($this->address_for_js($address));
        ?>
        <li class="user-addresses-manage__item">
            <div class="user-addresses-manage__card" data-address="<?php echo esc_attr($address_json); ?>" data-is-current-billing="<?php echo $is_current_billing ? '1' : '0'; ?>" data-is-current-shipping="<?php echo $is_current_shipping ? '1' : '0'; ?>">
                <div class="user-addresses-manage__card-heading"><?php echo esc_html($title); ?></div>
                <div class="user-addresses-manage__card-body user-addresses-card"><?php echo $this->get_address_card_body_html($address); ?></div>
                <div class="user-addresses-manage__card-meta">
                    <div class="user-addresses-manage__card-actions">
                        <button type="button" class="user-addresses-edit button--orange"><?php esc_html_e('Edytuj', 'user-adresses'); ?></button>
                    </div>
                </div>
            </div>
        </li>
        <?php
    }

    /**
     * Pełny adres sformatowany wielolinijkowo (bez etykiet).
     *
     * @param array<string, mixed> $address
     * @return string
     */
    protected function format_address_full(array $address): string {
        $parts = array_filter([
            trim(($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? '')),
            $address['company'] ?? '',
            $address['address_1'] ?? '',
            $address['address_2'] ?? '',
            trim(($address['postcode'] ?? '') . ' ' . ($address['city'] ?? '')),
            $address['country'] ?? '',
            $address['phone'] ?? '',
            !empty($address['billing_nip']) ? __('NIP:', 'user-adresses') . ' ' . ($address['billing_nip']) : '',
        ]);
        return implode("\n", $parts) ?: '—';
    }

    /**
     * Pełny adres z etykietami pól (do wyświetlania w panelach).
     * Zwraca wielolinijkowy tekst w formie "Etykieta: wartość".
     *
     * @param array<string, mixed> $address
     * @return string
     */
    protected function format_address_full_with_labels(array $address): string {
        $labels = $this->get_address_form_labels();
        $lines  = [];

        $single = [
            'first_name'  => $address['first_name'] ?? '',
            'last_name'   => $address['last_name'] ?? '',
            'company'     => $address['company'] ?? '',
            'address_1'   => $address['address_1'] ?? '',
            'address_2'   => $address['address_2'] ?? '',
            'postcode'    => $address['postcode'] ?? '',
            'city'        => $address['city'] ?? '',
            'country'     => $address['country'] ?? '',
            'phone'       => $address['phone'] ?? '',
        ];
        foreach ($single as $key => $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $lines[] = ($labels[ $key ] ?? $key) . ': ' . $value;
            }
        }
        if (!empty($address['billing_nip'])) {
            $lines[] = __('NIP', 'user-adresses') . ': ' . $address['billing_nip'];
        }

        return $lines !== [] ? implode("\n", $lines) : '—';
    }

    /**
     * Adres jako lista HTML: każda para to etykieta (na górze) i wartość (pod spodem).
     * Używane w kartach w panelu zarządzania i w modalu checkout.
     *
     * @param array<string, mixed> $address
     * @return string HTML (bez <br>, tylko divy ze spanami).
     */
    protected function get_address_card_body_html(array $address): string {
        $labels = $this->get_address_form_labels();
        $order  = [ 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'postcode', 'city', 'country', 'phone' ];
        $out    = [];
        foreach ($order as $key) {
            $value = trim((string) ($address[ $key ] ?? ''));
            if ($value === '') {
                continue;
            }
            $label = $labels[ $key ] ?? $key;
            $out[] = '<div class="user-addresses-card__row">'
                . '<span class="user-addresses-card__label">' . esc_html($label) . '</span>'
                . '<span class="user-addresses-card__value">' . esc_html($value) . '</span>'
                . '</div>';
        }
        if (!empty($address['billing_nip'])) {
            $out[] = '<div class="user-addresses-card__row">'
                . '<span class="user-addresses-card__label">' . esc_html(__('NIP', 'user-adresses')) . '</span>'
                . '<span class="user-addresses-card__value">' . esc_html($address['billing_nip']) . '</span>'
                . '</div>';
        }
        if ($out === []) {
            return '<div class="user-addresses-card__row"><span class="user-addresses-card__value">—</span></div>';
        }
        return implode('', $out);
    }

    /**
     * Modal z formularzem dodawania adresu (pola tożsame z WooCommerce).
     */
    protected function render_add_address_modal(): void {
        $fields = $this->get_address_form_labels();
        $countries = $this->get_countries_options();
        $form_action = admin_url('admin-post.php?action=' . self::SAVE_ACTION);
        $redirect_to = home_url(isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/');
        ?>
        <div id="user-addresses-add-modal" class="user-addresses-add-modal" role="dialog" aria-modal="true" aria-labelledby="user-addresses-add-modal-title" aria-hidden="true" hidden>
            <div class="user-addresses-add-modal__backdrop" data-close></div>
            <div class="user-addresses-add-modal__box">
                <div class="user-addresses-add-modal__header">
                    <h3 id="user-addresses-add-modal-title" class="user-addresses-add-modal__title"><?php esc_html_e('Dodaj adres', 'user-adresses'); ?></h3>
                    <button type="button" class="user-addresses-add-modal__close" data-close aria-label="<?php esc_attr_e('Zamknij', 'user-adresses'); ?>">&times;</button>
                </div>
                <form id="user-addresses-add-form" class="user-addresses-add-modal__form" method="post" action="<?php echo esc_url($form_action); ?>" data-title-add="<?php echo esc_attr(__('Dodaj adres', 'user-adresses')); ?>" data-title-edit="<?php echo esc_attr(__('Edytuj adres', 'user-adresses')); ?>">
                    <?php wp_nonce_field(self::SAVE_ACTION, 'user_addresses_save_nonce'); ?>
                    <input type="hidden" name="address_id" id="ua-address_id" value="" />
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
                    <div class="user-addresses-add-modal__body">
                        <p class="form-row-address form-row-address-wide">
                            <label for="ua-label"><?php esc_html_e('Nazwa adresu', 'user-adresses'); ?></label>
                            <input type="text" id="ua-label" name="label" class="input-text" placeholder="<?php esc_attr_e('np. Dom, Praca', 'user-adresses'); ?>" />
                        </p>
                        <p class="form-row-address form-row-address-first">
                            <label for="ua-first_name"><?php echo esc_html($fields['first_name']); ?> <abbr class="required">*</abbr></label>
                            <input type="text" id="ua-first_name" name="first_name" class="input-text" required />
                        </p>
                        <p class="form-row-address form-row-address-last">
                            <label for="ua-last_name"><?php echo esc_html($fields['last_name']); ?> <abbr class="required">*</abbr></label>
                            <input type="text" id="ua-last_name" name="last_name" class="input-text" required />
                        </p>
                        <p class="form-row-address form-row-address-wide">
                            <label for="ua-company"><?php echo esc_html($fields['company']); ?></label>
                            <input type="text" id="ua-company" name="company" class="input-text" />
                        </p>
                        <p class="form-row-address form-row-address-wide">
                            <label for="ua-address_1"><?php echo esc_html($fields['address_1']); ?> <abbr class="required">*</abbr></label>
                            <input type="text" id="ua-address_1" name="address_1" class="input-text" required />
                        </p>
                        <p class="form-row-address form-row-address-wide">
                            <label for="ua-address_2"><?php echo esc_html($fields['address_2']); ?></label>
                            <input type="text" id="ua-address_2" name="address_2" class="input-text" />
                        </p>
                        <p class="form-row-address form-row-address-first">
                            <label for="ua-postcode"><?php echo esc_html($fields['postcode']); ?> <abbr class="required">*</abbr></label>
                            <input type="text" id="ua-postcode" name="postcode" class="input-text" required />
                        </p>
                        <p class="form-row-address form-row-address-last">
                            <label for="ua-city"><?php echo esc_html($fields['city']); ?> <abbr class="required">*</abbr></label>
                            <input type="text" id="ua-city" name="city" class="input-text" required />
                        </p>
                        <p class="form-row-address form-row-address-wide">
                            <label for="ua-country"><?php echo esc_html($fields['country']); ?> <abbr class="required">*</abbr></label>
                            <?php if (!empty($countries)) : ?>
                                <select id="ua-country" name="country" class="select" required>
                                    <option value=""><?php esc_attr_e('Wybierz kraj', 'user-adresses'); ?></option>
                                    <?php foreach ($countries as $code => $name) : ?>
                                        <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else : ?>
                                <input type="text" id="ua-country" name="country" class="input-text" required />
                            <?php endif; ?>
                        </p>
                        <p class="form-row-address form-row-address-wide">
                            <label for="ua-phone"><?php echo esc_html($fields['phone']); ?></label>
                            <input type="tel" id="ua-phone" name="phone" class="input-text" />
                        </p>
                        <p class="form-row-address form-row-address-wide">
                            <label for="ua-billing_nip"><?php esc_html_e('NIP', 'user-adresses'); ?></label>
                            <input type="text" id="ua-billing_nip" name="billing_nip" class="input-text" />
                        </p>
                        <p class="form-row-address form-row-address-wide user-addresses-add-modal__type">
                            <span class="label"><?php esc_html_e('Typ adresu', 'user-adresses'); ?></span>
                            <label class="checkbox">
                                <input type="checkbox" name="type_billing" value="1" />
                                <?php esc_html_e('Rozliczeniowy', 'user-adresses'); ?>
                            </label>
                            <label class="checkbox">
                                <input type="checkbox" name="type_shipping" value="1" />
                                <?php esc_html_e('Wysyłka', 'user-adresses'); ?>
                            </label>
                        </p>
                    </div>
                    <div class="user-addresses-add-modal__footer">
                        <button type="submit" class="user-addresses-add-modal__submit btn--orange" data-text-add="<?php esc_attr_e('Zapisz adres', 'user-adresses'); ?>" data-text-edit="<?php esc_attr_e('Zapisz zmiany', 'user-adresses'); ?>"><?php esc_html_e('Zapisz adres', 'user-adresses'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Etykiety pól adresowych (zgodne z WooCommerce).
     *
     * @return array<string, string>
     */
    protected function get_address_form_labels(): array {
        if (function_exists('WC') && isset(WC()->countries)) {
            $defaults = method_exists(WC()->countries, 'get_default_address_fields')
                ? WC()->countries->get_default_address_fields()
                : [];
            $labels = [
                'first_name' => $defaults['first_name']['label'] ?? __('Imię', 'user-adresses'),
                'last_name'  => $defaults['last_name']['label'] ?? __('Nazwisko', 'user-adresses'),
                'company'    => $defaults['company']['label'] ?? __('Firma', 'user-adresses'),
                'address_1'  => $defaults['address_1']['label'] ?? __('Adres', 'user-adresses'),
                'address_2'  => $defaults['address_2']['label'] ?? __('Adres (cd.)', 'user-adresses'),
                'city'       => $defaults['city']['label'] ?? __('Miejscowość', 'user-adresses'),
                'state'      => $defaults['state']['label'] ?? __('Województwo', 'user-adresses'),
                'postcode'   => $defaults['postcode']['label'] ?? __('Kod pocztowy', 'user-adresses'),
                'country'    => $defaults['country']['label'] ?? __('Kraj', 'user-adresses'),
                'phone'      => __('Telefon', 'user-adresses'),
            ];
            return $labels;
        }
        return [
            'first_name' => __('Imię', 'user-adresses'),
            'last_name'  => __('Nazwisko', 'user-adresses'),
            'company'    => __('Firma', 'user-adresses'),
            'address_1'  => __('Adres', 'user-adresses'),
            'address_2'  => __('Adres (cd.)', 'user-adresses'),
            'city'       => __('Miejscowość', 'user-adresses'),
            'state'      => __('Województwo', 'user-adresses'),
            'postcode'   => __('Kod pocztowy', 'user-adresses'),
            'country'    => __('Kraj', 'user-adresses'),
            'phone'      => __('Telefon', 'user-adresses'),
        ];
    }

    /**
     * Lista krajów (WooCommerce lub pusta).
     *
     * @return array<string, string>
     */
    protected function get_countries_options(): array {
        if (function_exists('WC') && isset(WC()->countries)) {
            $countries = WC()->countries->get_countries();
            return is_array($countries) ? $countries : [];
        }
        return [];
    }

    /**
     * Określa typ adresu na podstawie checkboxów type_billing i type_shipping.
     *
     * @return string 'both'|'billing'|'shipping'
     */
    protected function resolve_address_type_from_post(): string {
        $billing  = !empty($_POST['type_billing']);
        $shipping = !empty($_POST['type_shipping']);
        if ($billing && $shipping) {
            return 'both';
        }
        if ($billing) {
            return 'billing';
        }
        if ($shipping) {
            return 'shipping';
        }
        return 'both';
    }

    /**
     * Niezalogowani: przekierowanie na logowanie.
     */
    public function handle_save_address_nopriv(): void {
        wp_safe_redirect(wp_login_url(wp_get_referer() ?: admin_url()));
        exit;
    }

    /**
     * Obsługa zapisu adresu (POST): dodanie lub aktualizacja, potem przekierowanie z odświeżeniem.
     */
    public function handle_save_address(): void {
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(wp_get_referer() ?: admin_url()));
            exit;
        }
        if (!isset($_POST['user_addresses_save_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['user_addresses_save_nonce'])), self::SAVE_ACTION)) {
            wp_safe_redirect(wp_get_referer() ?: home_url('/'));
            exit;
        }
        if (!$this->user_can_see_addresses()) {
            wp_safe_redirect(wp_get_referer() ?: home_url('/'));
            exit;
        }
        $user_id = get_current_user_id();
        $data = [
            'first_name'  => isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '',
            'last_name'   => isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '',
            'company'     => isset($_POST['company']) ? sanitize_text_field(wp_unslash($_POST['company'])) : '',
            'address_1'   => isset($_POST['address_1']) ? sanitize_text_field(wp_unslash($_POST['address_1'])) : '',
            'address_2'   => isset($_POST['address_2']) ? sanitize_text_field(wp_unslash($_POST['address_2'])) : '',
            'city'        => isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '',
            'state'       => isset($_POST['state']) ? sanitize_text_field(wp_unslash($_POST['state'])) : '',
            'postcode'    => isset($_POST['postcode']) ? sanitize_text_field(wp_unslash($_POST['postcode'])) : '',
            'country'     => isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '',
            'phone'       => isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '',
            'billing_nip' => isset($_POST['billing_nip']) ? sanitize_text_field(wp_unslash($_POST['billing_nip'])) : '',
            'label'       => isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '',
            'type'        => $this->resolve_address_type_from_post(),
        ];
        $address_id = isset($_POST['address_id']) ? sanitize_text_field(wp_unslash($_POST['address_id'])) : '';
        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : wp_get_referer();
        if (empty($redirect_to)) {
            $redirect_to = home_url('/');
        }
        if ($address_id !== '') {
            $ok = $this->repository->update_additional_address($user_id, $address_id, $data);
        } else {
            $ok = $this->repository->add_additional_address($user_id, $data) !== false;
        }
        $wants_billing  = !empty($_POST['type_billing']);
        $wants_shipping = !empty($_POST['type_shipping']);
        if ($wants_billing) {
            $this->repository->set_woocommerce_billing($user_id, $data);
        }
        if ($wants_shipping) {
            $this->repository->set_woocommerce_shipping($user_id, $data);
        }
        $redirect_to = add_query_arg('user_addresses_saved', '1', $redirect_to);
        wp_safe_redirect($redirect_to);
        exit;
    }

    /**
     * Jednolinijkowy opis adresu (address_1, city, postcode).
     *
     * @param array<string, mixed> $address
     * @return string
     */
    protected function format_address_line(array $address): string {
        $parts = array_filter([
            $address['address_1'] ?? '',
            $address['city'] ?? '',
            $address['postcode'] ?? '',
        ]);
        return implode(', ', $parts) ?: '—';
    }

    /**
     * URL do strony Moje konto (WooCommerce).
     *
     * @return string
     */
    protected function get_myaccount_url(): string {
        if (function_exists('wc_get_page_permalink')) {
            $url = wc_get_page_permalink('myaccount');
            return $url ?: home_url('/');
        }
        $page_id = get_option('woocommerce_myaccount_page_id');
        return $page_id ? get_permalink($page_id) : home_url('/');
    }

    /**
     * URL do zarządzania adresami (edit-address z parametrem tab_open=true).
     *
     * @return string
     */
    protected function get_manage_addresses_url(): string {
        $base = $this->get_myaccount_url();
        if (function_exists('wc_get_endpoint_url')) {
            $url = wc_get_endpoint_url('edit-address', '', $base);
        } else {
            $url = rtrim($base, '/') . '/edit-address/';
        }
        return add_query_arg('tab_open', 'true', $url);
    }

    /**
     * Czy bieżący użytkownik ma prawo do widoku „Moje adresy” (ma przynajmniej jedną z ról w $allowed_roles).
     *
     * @return bool
     */
    protected function user_can_see_addresses(): bool {
        $user = wp_get_current_user();
        if (empty($user->ID)) {
            return false;
        }
        return (bool) array_intersect((array) $user->roles, $this->allowed_roles);
    }

    /**
     * Renderuje shortcode na checkout.
     * Przycisk „Moje adresy” otwiera modal z listą; każdy adres ma „Wybierz jako adres rozliczenia” / „Wybierz jako adres dostawy”.
     * Atrybuty: title, class.
     *
     * @param array<string, string> $atts Atrybuty shortcode.
     * @return string HTML.
     */
    public function render_checkout(array $atts): string {
        $atts = shortcode_atts([
            'title' => __('Moje adresy', 'user-adresses'),
            'class' => '',
        ], $atts, self::SHORTCODE_CHECKOUT);

        if (!is_user_logged_in() || !$this->user_can_see_addresses()) {
            return '';
        }

        $user_id   = get_current_user_id();
        $addresses = $this->repository->get_additional_addresses($user_id);
        $this->ensure_default_addresses($user_id, $addresses);
        $addresses = $this->repository->get_additional_addresses($user_id);
        $manage_url = $this->get_manage_addresses_url();

        ob_start();
        ?>
        <div class="user-addresses-checkout <?php echo esc_attr($atts['class']); ?>">
            <button type="button" class="user-addresses-checkout__toggle btn--orange" aria-controls="user-addresses-checkout-modal" aria-expanded="false">
                <?php echo esc_html($atts['title']); ?>
            </button>
            <?php $this->render_checkout_modal($addresses, $manage_url); ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Modal na checkout: nagłówek z linkiem „Zarządzaj adresami”, lista adresów z przyciskami wyboru.
     *
     * @param array<int, array<string, mixed>> $addresses
     * @param string                             $manage_url URL do strony zarządzania adresami (edit-address?tab_open=true).
     */
    protected function render_checkout_modal(array $addresses, string $manage_url): void {
        ?>
        <div id="user-addresses-checkout-modal" class="user-addresses-checkout-modal" role="dialog" aria-modal="true" aria-labelledby="user-addresses-checkout-modal-title" aria-hidden="true" hidden>
            <div class="user-addresses-checkout-modal__backdrop" data-close></div>
            <div class="user-addresses-checkout-modal__box">
                <div class="user-addresses-checkout-modal__header">
                    <h3 id="user-addresses-checkout-modal-title" class="user-addresses-checkout-modal__title"><?php esc_html_e('Wybierz adres', 'user-adresses'); ?></h3>
                    <a href="<?php echo esc_url($manage_url); ?>" class="user-addresses-checkout-modal__manage  button--orange"><?php esc_html_e('Zarządzaj adresami', 'user-adresses'); ?></a>
                    <button type="button" class="user-addresses-checkout-modal__close" data-close aria-label="<?php esc_attr_e('Zamknij', 'user-adresses'); ?>">&times;</button>
                </div>
                <div class="user-addresses-checkout-modal__body">
                    <ul class="user-addresses-checkout-modal__list">
                        <?php
                        foreach ($addresses as $address) {
                            $this->render_checkout_address_item($address);
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Jedna karta adresu w modalu checkout (bez Edytuj, z przyciskami „Wybierz jako rozliczenie” / „Wybierz jako dostawa”).
     *
     * @param array<string, mixed> $address
     */
    protected function render_checkout_address_item(array $address): void {
        $label = $address['label'] ?? '';
        $title = $label !== '' ? $label : $this->format_address_full($address);
        $address_json = wp_json_encode($this->address_for_js($address));
        ?>
        <li class="user-addresses-checkout-modal__item">
            <div class="user-addresses-checkout-modal__card" data-address="<?php echo esc_attr($address_json); ?>">
                <div class="user-addresses-checkout-modal__card-heading"><?php echo esc_html($title); ?></div>
                <div class="user-addresses-checkout-modal__card-body user-addresses-card"><?php echo $this->get_address_card_body_html($address); ?></div>
                <div class="user-addresses-checkout-modal__card-actions">
                    <button type="button" class="user-addresses-select-billing  button--orange"><?php esc_html_e('Wybierz jako adres rozliczenia', 'user-adresses'); ?></button>
                    <button type="button" class="user-addresses-select-shipping  button--orange"><?php esc_html_e('Wybierz jako adres dostawy', 'user-adresses'); ?></button>
                </div>
            </div>
        </li>
        <?php
    }
}

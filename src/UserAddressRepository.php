<?php

/**
 * Repozytorium adresów dodatkowych użytkownika (user meta).
 *
 * Klucz meta: additional_adresses
 * Format: tablica elementów, każdy z polami tożsamymi z WooCommerce billing/shipping
 * oraz: id, default, type, label (patrz get_default_address_fields()).
 *
 * @package UserAddresses
 */

if (!defined('ABSPATH')) {
    exit;
}

class UserAddressRepository {

    /**
     * Klucz user meta z tablicą adresów.
     *
     * @var string
     */
    const META_KEY = 'additional_adresses';

    /**
     * Klucze pól adresowych zgodne z WooCommerce (billing i shipping).
     * Bez prefiksu billing_/shipping_; phone jest używany w billing.
     *
     * @var array<int, string>
     */
    const ADDRESS_FIELDS = [
        'first_name',
        'last_name',
        'company',
        'address_1',
        'address_2',
        'city',
        'state',
        'postcode',
        'country',
        'phone',
    ];

    /**
     * Zwraca pustą strukturę adresu z kluczami WC (pola adresowe puste, type='both', default=false).
     *
     * @return array<string, mixed>
     */
    public static function get_default_address_fields(): array {
        $fields = array_fill_keys(self::ADDRESS_FIELDS, '');
        $fields['id']          = '';
        $fields['label']       = '';
        $fields['type']        = 'both';
        $fields['default']     = false;
        $fields['billing_nip'] = '';
        return $fields;
    }

    /**
     * Zwraca tablicę dodatkowych adresów użytkownika.
     *
     * @param int $user_id ID użytkownika.
     * @return array<int, array<string, mixed>> Zawsze tablica (pusta jeśli brak meta).
     */
    public function get_additional_addresses(int $user_id): array {
        $raw = get_user_meta($user_id, self::META_KEY, true);
        if (!is_array($raw)) {
            return [];
        }
        return $raw;
    }

    /**
     * Zapisuje tablicę dodatkowych adresów użytkownika.
     *
     * @param int   $user_id   ID użytkownika.
     * @param array $addresses Tablica adresów (każdy element to tablica asocjacyjna).
     * @return bool Wynik update_user_meta.
     */
    public function save_additional_addresses(int $user_id, array $addresses): bool {
        return (bool) update_user_meta($user_id, self::META_KEY, $addresses);
    }

    /**
     * Normalizuje dane adresu do pól WooCommerce + id, label, type, default.
     *
     * @param array<string, mixed> $address Dane wejściowe.
     * @return array<string, mixed>
     */
    public function normalize_address(array $address): array {
        $out = self::get_default_address_fields();
        foreach (self::ADDRESS_FIELDS as $key) {
            if (array_key_exists($key, $address)) {
                $out[$key] = is_string($address[$key]) ? $address[$key] : (string) $address[$key];
            }
        }
        if (array_key_exists('label', $address)) {
            $out['label'] = is_string($address['label']) ? $address['label'] : (string) $address['label'];
        }
        if (array_key_exists('type', $address)) {
            $t = $address['type'];
            $out['type'] = in_array($t, ['billing', 'shipping', 'both'], true) ? $t : 'both';
        }
        if (array_key_exists('default', $address)) {
            $out['default'] = (bool) $address['default'];
        }
        if (!empty($address['id']) && is_string($address['id'])) {
            $out['id'] = $address['id'];
        }
        if (array_key_exists('billing_nip', $address)) {
            $out['billing_nip'] = is_string($address['billing_nip']) ? $address['billing_nip'] : (string) $address['billing_nip'];
        }
        return $out;
    }

    /**
     * Dodaje dodatkowy adres użytkownika.
     * Pola adresowe muszą być tożsame z WooCommerce (billing/shipping).
     *
     * @param int                  $user_id ID użytkownika.
     * @param array<string, mixed> $address Dane adresu (first_name, last_name, company, address_1, address_2, city, state, postcode, country, phone oraz opcjonalnie label, type, default).
     * @return string|false Id dodanego adresu lub false przy błędzie.
     */
    public function add_additional_address(int $user_id, array $address) {
        $addresses = $this->get_additional_addresses($user_id);
        $normalized = $this->normalize_address($address);
        $normalized['id'] = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('addr_', true);

        if (!empty($normalized['default'])) {
            foreach ($addresses as &$a) {
                $a['default'] = false;
            }
            unset($a);
        }

        $addresses[] = $normalized;
        return $this->save_additional_addresses($user_id, $addresses) ? $normalized['id'] : false;
    }

    /**
     * Aktualizuje istniejący adres dodatkowy po id.
     *
     * @param int                  $user_id    ID użytkownika.
     * @param string               $address_id Id adresu do aktualizacji.
     * @param array<string, mixed> $address    Nowe dane adresu (pola jak przy dodawaniu).
     * @return bool True jeśli zaktualizowano, false jeśli nie znaleziono adresu lub błąd zapisu.
     */
    public function update_additional_address(int $user_id, string $address_id, array $address): bool {
        $addresses = $this->get_additional_addresses($user_id);
        $found = false;
        foreach ($addresses as $idx => $item) {
            if (($item['id'] ?? '') === $address_id) {
                $normalized = $this->normalize_address($address);
                $normalized['id'] = $address_id;
                $addresses[$idx] = $normalized;
                $found = true;
                break;
            }
        }
        if (!$found) {
            return false;
        }
        return $this->save_additional_addresses($user_id, $addresses);
    }

    /**
     * Usuwa dodatkowy adres użytkownika po id.
     *
     * @param int    $user_id    ID użytkownika.
     * @param string $address_id Id adresu do usunięcia.
     * @return bool True jeśli usunięto (lub adres nie istniał), false przy błędzie zapisu.
     */
    public function delete_additional_address(int $user_id, string $address_id): bool {
        $addresses = $this->get_additional_addresses($user_id);
        $before = count($addresses);
        $addresses = array_values(array_filter($addresses, function ($a) use ($address_id) {
            return ($a['id'] ?? '') !== $address_id;
        }));
        if (count($addresses) === $before) {
            return true;
        }
        return $this->save_additional_addresses($user_id, $addresses);
    }

    /**
     * Zwraca adres oznaczony jako domyślny.
     *
     * @param int $user_id ID użytkownika.
     * @return array<string, mixed>|null Adres z kluczem 'default' === true lub null.
     */
    public function get_default_address(int $user_id): ?array {
        $addresses = $this->get_additional_addresses($user_id);
        foreach ($addresses as $address) {
            if (!empty($address['default'])) {
                return $address;
            }
        }
        return null;
    }

    /**
     * Zwraca adresy przypisane do danego typu (billing / shipping / both).
     *
     * @param int    $user_id ID użytkownika.
     * @param string $type    'billing', 'shipping' lub 'both'.
     * @return array<int, array<string, mixed>>
     */
    public function get_by_type(int $user_id, string $type): array {
        $addresses = $this->get_additional_addresses($user_id);
        $out = [];
        foreach ($addresses as $idx => $address) {
            $addr_type = $address['type'] ?? '';
            if ($addr_type === $type || $addr_type === 'both') {
                $out[$idx] = $address;
            }
        }
        return array_values($out);
    }

    /**
     * Pobiera domyślny adres rozliczeniowy WooCommerce (user meta billing_*).
     *
     * @param int $user_id ID użytkownika.
     * @return array<string, mixed> Tablica z kluczami jak ADDRESS_FIELDS + id (wc_billing), label.
     */
    public function get_woocommerce_billing(int $user_id): array {
        $out = self::get_default_address_fields();
        $out['id']    = 'wc_billing';
        $out['label'] = __('Adres rozliczeniowy (domyślny)', 'user-adresses');
        $out['type']  = 'billing';
        foreach (self::ADDRESS_FIELDS as $key) {
            $val = get_user_meta($user_id, 'billing_' . $key, true);
            $out[$key] = is_string($val) ? $val : '';
        }
        $nip = get_user_meta($user_id, 'billing_nip', true);
        $out['billing_nip'] = is_string($nip) ? $nip : '';
        return $out;
    }

    /**
     * Pobiera domyślny adres wysyłki WooCommerce (user meta shipping_*).
     *
     * @param int $user_id ID użytkownika.
     * @return array<string, mixed> Tablica z kluczami jak ADDRESS_FIELDS + id (wc_shipping), label.
     */
    public function get_woocommerce_shipping(int $user_id): array {
        $out = self::get_default_address_fields();
        $out['id']    = 'wc_shipping';
        $out['label'] = __('Adres wysyłki (domyślny)', 'user-adresses');
        $out['type']  = 'shipping';
        foreach (self::ADDRESS_FIELDS as $key) {
            $val = get_user_meta($user_id, 'shipping_' . $key, true);
            $out[$key] = is_string($val) ? $val : '';
        }
        return $out;
    }

    /**
     * Zapisuje adres jako domyślny rozliczeniowy WooCommerce (billing_*).
     *
     * @param int                  $user_id ID użytkownika.
     * @param array<string, mixed> $address Tablica z kluczami jak ADDRESS_FIELDS (bez prefiksu).
     * @return bool True jeśli zapisano.
     */
    public function set_woocommerce_billing(int $user_id, array $address): bool {
        $address = $this->normalize_address($address);
        foreach (self::ADDRESS_FIELDS as $key) {
            $value = $address[$key] ?? '';
            update_user_meta($user_id, 'billing_' . $key, $value);
        }
        return true;
    }

    /**
     * Zapisuje adres jako domyślny wysyłki WooCommerce (shipping_*).
     *
     * @param int                  $user_id ID użytkownika.
     * @param array<string, mixed> $address Tablica z kluczami jak ADDRESS_FIELDS (bez prefiksu).
     * @return bool True jeśli zapisano.
     */
    public function set_woocommerce_shipping(int $user_id, array $address): bool {
        $address = $this->normalize_address($address);
        foreach (self::ADDRESS_FIELDS as $key) {
            $value = $address[$key] ?? '';
            update_user_meta($user_id, 'shipping_' . $key, $value);
        }
        return true;
    }
}

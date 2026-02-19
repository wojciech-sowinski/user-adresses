<?php

/**
 * Repozytorium adresów dodatkowych użytkownika (user meta).
 *
 * Klucz meta: additional_adresses
 * Format: tablica elementów, każdy np.:
 *   - id (string) – identyfikator
 *   - default (bool) – czy adres domyślny
 *   - type (string) – 'billing' | 'shipping' | 'both'
 *   - label (string) – nazwa adresu
 *   - oraz pola adresowe (address_1, address_2, city, postcode, country, itd.)
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
}

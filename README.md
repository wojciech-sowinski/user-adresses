# User Addresses

Wtyczka WordPress do przechowywania i zarządzania **dodatkowymi adresami** użytkowników, zintegrowana z **WooCommerce** (rozliczeniowy, wysyłka, NIP).

## Wymagania

- WordPress 5.0+
- WooCommerce (adresy bazowe, checkout, Moje konto)
- PHP 7.4+

## Instalacja

1. Skopiuj katalog wtyczki do `wp-content/plugins/user-adresses/`.
2. Aktywuj wtyczkę w panelu **Wtyczki**.
3. W **Narzędzia → User Addresses** wybierz role z dostępem do funkcji adresów.

## Konfiguracja ról

Opcja `user_addresses_allowed_roles` (domyślnie m.in. `b2b_`, `administrator`). Tylko użytkownicy z wybranymi rolami widzą shortcode’y i mogą zapisywać adresy.

## Shortcode’y

| Shortcode | Opis |
|-----------|------|
| `[user_addresses_manage]` | Panel w „Moim koncie”: lista adresów, dodawanie, edycja, usuwanie. Atrybuty: `title`, `class`. |
| `[user_addresses_checkout]` | Na stronie checkout: modal wyboru zapisanego adresu (rozliczenie / dostawa). Atrybuty: `title`, `class`. |

Dane trzymane w user meta (`additional_adresses`). Pola zgodne z polami rozliczeniowymi WooCommerce; przy pierwszym wejściu mogą zostać uzupełnione adresy domyślne z WC.

## Struktura repozytorium

```
user-adresses.php          — bootstrap wtyczki
src/UserAddressRepository.php
src/front/Shortcodes.php
src/admin/AdminPage.php
css/, js/                  — front (modal, checkout)
```

## Licencja

GPL v2 lub nowsza.

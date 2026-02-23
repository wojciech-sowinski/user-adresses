(function ($) {
    'use strict';

    var panelId = 'user-addresses-panel';
    var toggleSelector = '.user-addresses-manage__toggle';
    var addModalId = 'user-addresses-add-modal';
    var addFormId = 'user-addresses-add-form';
    var checkoutModalId = 'user-addresses-checkout-modal';

    var modalTitleId = 'user-addresses-add-modal-title';

    var addressFieldKeys = ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'postcode', 'country', 'phone'];

    function openAddModal() {
        var $form = $('#' + addFormId);
        if ($form.length && $form[0]) {
            $form.find('#ua-address_id').val('');
            var titleEl = document.getElementById(modalTitleId);
            if (titleEl) titleEl.textContent = $form.attr('data-title-add') || 'Dodaj adres';
            var $submit = $form.find('.user-addresses-add-modal__submit');
            if ($submit.length) $submit.text($submit.attr('data-text-add') || 'Zapisz adres');
            $form[0].reset();
            $form.find('#ua-address_id').val('');
        }
        var modal = document.getElementById(addModalId);
        if (modal) {
            modal.removeAttribute('hidden');
            modal.setAttribute('aria-hidden', 'false');
        }
    }

    function openEditModal(addressData, isCurrentBilling, isCurrentShipping) {
        var $form = $('#' + addFormId);
        var a = addressData;
        if ($form.length && $form[0]) {
            $form.find('#ua-address_id').val(a.id || '');
            $form.find('#ua-label').val(a.label || '');
            $form.find('#ua-first_name').val(a.first_name || '');
            $form.find('#ua-last_name').val(a.last_name || '');
            $form.find('#ua-company').val(a.company || '');
            $form.find('#ua-address_1').val(a.address_1 || '');
            $form.find('#ua-address_2').val(a.address_2 || '');
            $form.find('#ua-postcode').val(a.postcode || '');
            $form.find('#ua-city').val(a.city || '');
            $form.find('#ua-country').val(a.country || '');
            $form.find('#ua-phone').val(a.phone || '');
            $form.find('#ua-billing_nip').val(a.billing_nip || '');
            var titleEl = document.getElementById(modalTitleId);
            if (titleEl) titleEl.textContent = $form.attr('data-title-edit') || 'Edytuj adres';
            $form.find('[name="type_billing"]').prop('checked', !!isCurrentBilling);
            $form.find('[name="type_shipping"]').prop('checked', !!isCurrentShipping);
            var $submit = $form.find('.user-addresses-add-modal__submit');
            if ($submit.length) $submit.text($submit.attr('data-text-edit') || 'Zapisz zmiany');
        }
        var modal = document.getElementById(addModalId);
        if (modal) {
            modal.removeAttribute('hidden');
            modal.setAttribute('aria-hidden', 'false');
        }
    }

    function closeAddModal() {
        var modal = document.getElementById(addModalId);
        if (modal) {
            modal.setAttribute('hidden', '');
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    function openCheckoutModal() {
        var modal = document.getElementById(checkoutModalId);
        if (modal) {
            modal.removeAttribute('hidden');
            modal.setAttribute('aria-hidden', 'false');
        }
        $('.user-addresses-checkout__toggle').attr('aria-expanded', 'true');
    }

    function closeCheckoutModal() {
        var modal = document.getElementById(checkoutModalId);
        if (modal) {
            modal.setAttribute('hidden', '');
            modal.setAttribute('aria-hidden', 'true');
        }
        $('.user-addresses-checkout__toggle').attr('aria-expanded', 'false');
    }

    function fillCheckoutAddress(prefix, address) {
        addressFieldKeys.forEach(function (key) {
            var id = prefix + '_' + key;
            var $el = $('#' + id);
            if ($el.length) {
                var val = address[key] || '';
                $el.val(val);
                if ($el.is('select')) {
                    $el.trigger('change');
                }
            }
        });
        var $country = $('#' + prefix + '_country');
        if ($country.length) {
            $country.trigger('change');
        }
        if (address.billing_nip) {
            var $nip = $('#billing_nip');
            if ($nip.length) $nip.val(address.billing_nip);
        }
    }

    $(document).ready(function () {
        // Panel rozsuwany (Moje adresy)
        $(document).on('click', toggleSelector, function () {
            var $btn = $(this);
            var $panel = $('#' + panelId);
            if (!$panel.length) return;
            $panel.slideToggle(function () {
                var isExpanded = $panel.is(':visible');
                $btn.attr('aria-expanded', isExpanded);
                $panel.attr('aria-hidden', !isExpanded);
            });
        });

        // Otwórz modal „Dodaj adres”
        $(document).on('click', '.user-addresses-modal__add', openAddModal);

        // Zamknij modal (backdrop, przycisk ×)
        $(document).on('click', '.user-addresses-add-modal__close, .user-addresses-add-modal__backdrop', closeAddModal);

        // Escape zamyka modal
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                var modalAdd = document.getElementById(addModalId);
                var modalCheckout = document.getElementById(checkoutModalId);
                if (modalCheckout && !modalCheckout.hasAttribute('hidden')) {
                    closeCheckoutModal();
                } else if (modalAdd && !modalAdd.hasAttribute('hidden')) {
                    closeAddModal();
                }
            }
        });

        // Checkout: otwórz modal „Moje adresy”
        $(document).on('click', '.user-addresses-checkout__toggle', openCheckoutModal);

        // Checkout: zamknij modal (backdrop, ×)
        $(document).on('click', '.user-addresses-checkout-modal__close, .user-addresses-checkout-modal__backdrop', closeCheckoutModal);

        // Checkout: wybierz adres rozliczeniowy / dostawy – wypełnij formularz i zamknij modal
        $(document).on('click', '.user-addresses-select-billing, .user-addresses-select-shipping', function () {
            var $btn = $(this);
            var $card = $btn.closest('.user-addresses-checkout-modal__card');
            var addressData = $card.attr('data-address');
            if (!addressData) return;
            var address;
            try {
                address = typeof addressData === 'string' ? JSON.parse(addressData) : addressData;
            } catch (e) {
                return;
            }
            var prefix = $btn.hasClass('user-addresses-select-billing') ? 'billing' : 'shipping';
            fillCheckoutAddress(prefix, address);
            closeCheckoutModal();
        });

        // Formularz dodawania/edycji adresu wysyła zwykły POST (action admin-post.php) – po zapisie PHP przekierowuje i odświeża stronę.

        // Edytuj adres – otwórz modal z uzupełnionym formularzem (checkboxy z karty: czy ten adres jest domyślnym w WC)
        $(document).on('click', '.user-addresses-edit', function () {
            var $card = $(this).closest('.user-addresses-manage__card');
            var addressData = $card.attr('data-address');
            if (!addressData) return;
            var address;
            try {
                address = typeof addressData === 'string' ? JSON.parse(addressData) : addressData;
            } catch (e) {
                return;
            }
            var isCurrentBilling = $card.attr('data-is-current-billing') === '1';
            var isCurrentShipping = $card.attr('data-is-current-shipping') === '1';
            openEditModal(address, isCurrentBilling, isCurrentShipping);
        });

    });
})(jQuery);

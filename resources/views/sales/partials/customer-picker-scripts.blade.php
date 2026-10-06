<script type="text/javascript">
    const registeredCustomers = @json($customers ?? []);

    function formatPhoneForDisplay(phone) {
        if (!phone) return '';
        let value = String(phone).replace(/\s/g, '');
        if (value.startsWith('+255')) value = value.slice(4);
        else if (value.startsWith('255')) value = value.slice(3);
        return value.replace(/^0+/, '');
    }

    function initCustomerPicker(config) {
        const $select = $(config.select);
        const $name = $(config.nameInput);
        const $phone = $(config.phoneInput);
        const $phoneHidden = config.phoneHidden ? $(config.phoneHidden) : null;
        const $manualWrap = config.manualWrap ? $(config.manualWrap) : null;

        const hasCountryPicker = $phone.closest('[data-phone-country]').length > 0 && window.phoneCountryInput;

        function setPhone(value) {
            if (hasCountryPicker) {
                window.phoneCountryInput.set($phone, value || '');
            } else {
                $phone.val(formatPhoneForDisplay(value));
            }
        }

        function syncPhoneHidden() {
            if (!$phoneHidden || !$phoneHidden.length) return;
            if (hasCountryPicker) {
                $phoneHidden.val(window.phoneCountryInput.get($phone));
                return;
            }
            const digits = String($phone.val() || '').replace(/\D/g, '').replace(/^0+/, '');
            $phoneHidden.val(digits ? '+255' + digits : '');
        }

        function setManualMode(isManual) {
            $name.prop('readonly', !isManual && $select.val() !== '');
            if ($phone.length) {
                $phone.prop('readonly', !isManual && $select.val() !== '');
            }
        }

        function applyCustomer(id) {
            if (!id) {
                $name.val('').prop('readonly', false);
                if ($phone.length) {
                    setPhone('');
                    $phone.prop('readonly', false);
                }
                syncPhoneHidden();
                setManualMode(true);
                return;
            }

            const customer = registeredCustomers.find(c => String(c.id) === String(id));
            if (!customer) {
                setManualMode(true);
                return;
            }

            $name.val(customer.name);
            if ($phone.length) {
                setPhone(customer.phone);
            }
            syncPhoneHidden();
            setManualMode(false);
        }

        $select.on('change', function() {
            applyCustomer($(this).val());
        });

        if ($phone.length) {
            $phone.on('input', syncPhoneHidden);
        }

        applyCustomer($select.val());
    }
</script>

<script type="text/javascript">
    let currentBalance = 0;
    let currentTotal = 0;
    let currentPaid = 0;
    let currentPaymentMode = '';
    let payLineItems = [];
    let invoiceCustomer = { id: '', name: '', phone: '' };

    function formatMoney(amount) {
        return Math.round(parseFloat(amount) || 0).toLocaleString();
    }

    function formatMoneyLabel(amount) {
        return 'TZS ' + formatMoney(amount);
    }

    function formatPhoneForDisplay(phone) {
        if (!phone) {
            return '';
        }
        let value = String(phone).replace(/\s/g, '');
        if (value.startsWith('+255')) {
            value = value.slice(4);
        } else if (value.startsWith('255')) {
            value = value.slice(3);
        }
        return value.replace(/^0+/, '');
    }

    function formatPhoneForSave(local) {
        const digits = String(local || '').replace(/\D/g, '').replace(/^0+/, '');
        return digits ? '+255' + digits : '';
    }

    function syncCustomerPhoneField() {
        $('#payCustomerPhone').val(formatPhoneForSave($('#payCustomerPhoneLocal').val()));
    }

    function setProviderFieldRequirements(requiresRef) {
        $('#paymentProvider, #transactionReference').prop('required', requiresRef);
        $('#paymentProviderCustom').prop('required', false);
    }

    function resolvedPaymentProvider() {
        const custom = ($('#paymentProviderCustom').val() || '').trim();
        if (custom) {
            return custom;
        }

        return ($('#paymentProvider').val() || '').trim();
    }

    function syncPaymentProviderValue() {
        $('#paymentProviderValue').val(resolvedPaymentProvider());
    }

    function setCustomerFieldRequirements(mode) {
        const needsCustomer = mode === 'partial' || mode === 'debt';
        $('#payCustomerName').prop('required', needsCustomer);
        $('#payCustomerPhoneLocal').prop('required', mode === 'partial');
        $('#payNotes').prop('required', mode === 'partial');
        $('#payDueDate').prop('required', needsCustomer);
    }

    function hasInvoiceCustomer() {
        return !!(invoiceCustomer.id || invoiceCustomer.name);
    }

    function renderInvoiceCustomerSummary() {
        if (!hasInvoiceCustomer()) {
            $('#customerOnRecord').hide();
            return;
        }

        $('#customerOnRecord').show();
        const displayName = invoiceCustomer.name
            || $('#payCustomerName').val()
            || ($('#payCustomerSelect option:selected').text() || '').trim()
            || 'Registered customer';
        const displayPhone = invoiceCustomer.phone || $('#payCustomerPhone').val() || '';

        $('#payCustomerDisplayName').text(displayName);
        if (displayPhone) {
            $('#payCustomerDisplayPhone').show().text(displayPhone);
        } else {
            $('#payCustomerDisplayPhone').hide();
        }
    }

    function syncInvoiceCustomerToForm() {
        if (invoiceCustomer.id) {
            $('#payCustomerSelect').val(invoiceCustomer.id).trigger('change');
        } else {
            $('#payCustomerSelect').val('').trigger('change');
            $('#payCustomerName').val(invoiceCustomer.name || '');
            $('#payCustomerPhoneLocal').val(formatPhoneForDisplay(invoiceCustomer.phone));
            syncCustomerPhoneField();
        }
    }

    function updateCustomerInfoVisibility() {
        const $selected = $('#paymentMethod option:selected');
        const method = $('#paymentMethod').val();
        const methodType = $selected.data('type');
        const paying = parseFloat($('#amountPaid').val()) || 0;
        const applied = Math.min(Math.max(paying, 0), currentBalance);
        const isPartialAmount = applied > 0 && applied < currentBalance;
        const onInvoice = hasInvoiceCustomer();

        $('#customerInfoFields, #partialPaymentAlert, #debtPaymentAlert, #repayDateSection').hide();
        $('#customerInfoPlaceholder').hide();
        currentPaymentMode = '';

        renderInvoiceCustomerSummary();

        if (onInvoice) {
            syncInvoiceCustomerToForm();
            renderInvoiceCustomerSummary();
        }

        if (methodType === 'credit') {
            $('#customerInfoFields, #debtPaymentAlert, #repayDateSection').show();
            currentPaymentMode = 'debt';
        } else if (method && isPartialAmount) {
            $('#customerInfoFields, #partialPaymentAlert, #repayDateSection').show();
            currentPaymentMode = 'partial';
        } else if (!onInvoice && method && !isPartialAmount && methodType !== 'credit') {
            $('#customerInfoPlaceholder').show();
        }

        setCustomerFieldRequirements(currentPaymentMode);
    }

    function computeLineSubtotal(item) {
        const qty = parseFloat(item.qty) || 0;
        const listPrice = parseFloat(item.unit_price) || 0;
        const gross = qty * listPrice;

        if (item.adjustment_mode === 'discount') {
            const discVal = parseFloat(item.discount_value) || 0;
            if (item.discount_type === 'percent') {
                return Math.max(0, gross - (gross * (discVal / 100)));
            }
            if (item.discount_type === 'fixed') {
                return Math.max(0, gross - discVal);
            }
            return gross;
        }

        const customPrice = parseFloat(item.custom_price);
        if (!isNaN(customPrice) && customPrice >= 0) {
            return Math.max(0, qty * customPrice);
        }

        return gross;
    }

    function syncPayLineItemsFromDom() {
        payLineItems = payLineItems.map(function(item, index) {
            const row = $(`#payItemsBody tr[data-index="${index}"]`);
            item.adjustment_mode = row.find('.pay-adjust-mode').val() || 'price';
            item.custom_price = parseFloat(row.find('.pay-custom-price').val());
            item.discount_type = row.find('.pay-discount-type').val() || '';
            item.discount_value = parseFloat(row.find('.pay-discount-value').val()) || 0;
            return item;
        });
    }

    function renderPayLineItemRow(item, index) {
        const listPrice = parseFloat(item.unit_price) || 0;
        const customPrice = !isNaN(parseFloat(item.custom_price)) ? parseFloat(item.custom_price) : listPrice;
        const lineTotal = computeLineSubtotal(item);

        return `
            <tr data-index="${index}">
                <td>${item.name}</td>
                <td class="text-center">${item.qty}</td>
                <td class="text-right">${formatMoneyLabel(listPrice)}</td>
                <td>
                    <select class="form-control form-control-sm pay-adjust-mode">
                        <option value="price" ${item.adjustment_mode === 'price' ? 'selected' : ''}>Custom price</option>
                        <option value="discount" ${item.adjustment_mode === 'discount' ? 'selected' : ''}>Discount</option>
                    </select>
                </td>
                <td>
                    <div class="pay-price-fields" style="${item.adjustment_mode === 'discount' ? 'display:none;' : ''}">
                        <input type="number" class="form-control form-control-sm pay-custom-price" value="${customPrice}" min="0" step="1" placeholder="Unit price">
                    </div>
                    <div class="pay-discount-fields" style="${item.adjustment_mode === 'price' ? 'display:none;' : ''}">
                        <div class="d-flex" style="gap:6px;">
                            <select class="form-control form-control-sm pay-discount-type" style="max-width:110px;">
                                <option value="fixed" ${item.discount_type === 'fixed' ? 'selected' : ''}>TZS off</option>
                                <option value="percent" ${item.discount_type === 'percent' ? 'selected' : ''}>% off</option>
                            </select>
                            <input type="number" class="form-control form-control-sm pay-discount-value" value="${item.discount_value || 0}" min="0" step="1">
                        </div>
                    </div>
                </td>
                <td class="text-right font-weight-bold pay-line-total">${formatMoneyLabel(lineTotal)}</td>
            </tr>
        `;
    }

    function renderPayLineItems() {
        const body = $('#payItemsBody');
        body.empty();

        if (!payLineItems.length) {
            body.append('<tr><td colspan="6" class="text-center text-muted py-3">No items on this order.</td></tr>');
            updateOrderTotalsFromItems();
            return;
        }

        payLineItems.forEach(function(item, index) {
            body.append(renderPayLineItemRow(item, index));
        });

        updateOrderTotalsFromItems();
    }

    function updateOrderTotalsFromItems() {
        syncPayLineItemsFromDom();

        let revisedTotal = 0;
        payLineItems.forEach(function(item, index) {
            const subtotal = computeLineSubtotal(item);
            revisedTotal += subtotal;
            $(`#payItemsBody tr[data-index="${index}"] .pay-line-total`).text(formatMoneyLabel(subtotal));
        });

        currentTotal = revisedTotal;
        currentBalance = Math.max(0, currentTotal - currentPaid);

        $('#payOrderTotal').text(formatMoneyLabel(currentTotal));
        $('#payRevisedTotal').text(formatMoneyLabel(currentTotal));
        $('#payBalance').text(formatMoneyLabel(currentBalance));

        const amountField = $('#amountPaid');
        if ($('#paymentAmountSection').is(':visible')) {
            const currentVal = parseFloat(amountField.val()) || 0;
            if (currentVal > currentBalance || !amountField.data('user-edited')) {
                amountField.val(Math.round(currentBalance)).attr('max', Math.ceil(currentBalance));
            }
        } else {
            amountField.val(Math.round(currentBalance)).attr('max', Math.ceil(currentBalance));
        }

        syncPayLineItemHiddenInputs();
        updatePaymentPreview();
    }

    function syncPayLineItemHiddenInputs() {
        const container = $('#payLineItemsHidden');
        container.empty();

        payLineItems.forEach(function(item, index) {
            container.append(`
                <input type="hidden" name="line_items[${index}][id]" value="${item.id}">
                <input type="hidden" name="line_items[${index}][adjustment_mode]" value="${item.adjustment_mode}">
                <input type="hidden" name="line_items[${index}][unit_price]" value="${item.adjustment_mode === 'price' ? (parseFloat(item.custom_price) || item.unit_price) : item.unit_price}">
                <input type="hidden" name="line_items[${index}][discount_type]" value="${item.discount_type || ''}">
                <input type="hidden" name="line_items[${index}][discount_value]" value="${item.discount_value || 0}">
            `);
        });
    }

    function updatePaymentPreview() {
        const method = $('#paymentMethod').val();
        const paying = parseFloat($('#amountPaid').val()) || 0;
        const applied = Math.min(Math.max(paying, 0), currentBalance);
        const remaining = Math.max(0, currentBalance - applied);

        $('#remainingAfterPay').val(formatMoneyLabel(remaining));

        updateCustomerInfoVisibility();
    }

    function openPaymentModal(saleId, ref, totalAmount, amountPaid, customerId, customerName, customerPhone, dueDate, items) {
        currentTotal = parseFloat(totalAmount) || 0;
        currentPaid = parseFloat(amountPaid) || 0;
        currentBalance = Math.max(0, currentTotal - currentPaid);

        payLineItems = (items || []).map(function(item) {
            return {
                id: item.id,
                name: item.name,
                qty: item.qty,
                unit_price: item.unit_price,
                adjustment_mode: 'price',
                custom_price: item.unit_price,
                discount_type: 'fixed',
                discount_value: 0,
            };
        });

        $('#payRef').text(ref);
        $('#payOrderTotal').text(formatMoneyLabel(currentTotal));
        $('#payAmountPaid').text(formatMoneyLabel(currentPaid));
        $('#payBalance').text(formatMoneyLabel(currentBalance));

        $('#paymentForm').attr('action', `/sales/${saleId}/pay`);

        invoiceCustomer = {
            id: customerId ? String(customerId) : '',
            name: customerName || '',
            phone: customerPhone || '',
        };

        $('#paymentMethod').val('');
        $('#amountPaid').val(Math.round(currentBalance)).attr('max', Math.ceil(currentBalance)).removeData('user-edited');
        $('#payNotes').val('');
        $('#payDueDate').val(dueDate || '');
        $('#payCustomerSelect').val('').trigger('change');
        $('#payCustomerName').val('');
        $('#payCustomerPhoneLocal').val('');
        syncCustomerPhoneField();

        renderInvoiceCustomerSummary();
        if (hasInvoiceCustomer()) {
            syncInvoiceCustomerToForm();
        }

        renderPayLineItems();
        $('#paymentSubmitBtn').prop('disabled', false);
        $('#paymentMethod').trigger('change');
        updatePaymentPreview();

        $('#paymentModal').modal('show');
    }

    $(document).on('click', '.open-payment-modal-btn', function() {
        const $btn = $(this);
        let items = [];
        try {
            items = JSON.parse($btn.attr('data-items') || '[]');
        } catch (e) {
            items = [];
        }

        openPaymentModal(
            $btn.data('sale-id'),
            $btn.attr('data-ref') || '',
            $btn.data('total'),
            $btn.data('paid'),
            $btn.data('customer-id') || '',
            $btn.attr('data-customer-name') || '',
            $btn.attr('data-customer-phone') || '',
            $btn.attr('data-due-date') || null,
            items
        );
    });

    $(document).on('change', '.pay-adjust-mode', function() {
        const row = $(this).closest('tr');
        const isDiscount = $(this).val() === 'discount';
        row.find('.pay-price-fields').toggle(!isDiscount);
        row.find('.pay-discount-fields').toggle(isDiscount);
        updateOrderTotalsFromItems();
    });

    $(document).on('input change', '.pay-custom-price, .pay-discount-type, .pay-discount-value', function() {
        updateOrderTotalsFromItems();
    });

    $('#paymentMethod').on('change', function() {
        const $selected = $(this).find(':selected');
        const method = $(this).val();
        const methodType = $selected.data('type');
        const requiresRef = String($selected.data('requires-reference')) === '1';
        const providerAccounts = $selected.data('providerAccounts') || [];

        $('#paymentAmountSection, #providerFields').hide();
        $('#payReceiveDetailsBox').hide();
        $('#paymentProvider').empty().append('<option value="">-- Select Provider --</option>');
        $('#paymentProviderCustom').val('');
        $('#paymentProviderValue').val('');
        $('#transactionReference').val('');
        $('#amountPaid').prop('required', false);
        setProviderFieldRequirements(false);
        $('#paymentSubmitBtn').html('<i class="fa fa-check"></i> Save Payment');

        if (method && methodType !== 'credit') {
            $('#paymentAmountSection').show();
            $('#amountPaid').prop('required', true).val(Math.round(currentBalance)).removeData('user-edited');

            if (requiresRef) {
                $('#providerFields').show();
                setProviderFieldRequirements(true);
                providerAccounts.forEach(function (account) {
                    if (!account.name) return;
                    $('#paymentProvider').append(
                        $('<option></option>').val(account.name).text(account.name)
                            .attr('data-pay-number', account.pay_number || '')
                            .attr('data-account-name', account.account_name || '')
                    );
                });
            }
        } else if (methodType === 'credit') {
            $('#paymentSubmitBtn').html('<i class="fa fa-clock-o"></i> Save as Pay Later');
        }

        updatePaymentPreview();
    });

    function updatePayReceiveDetails() {
        syncPaymentProviderValue();

        const method = $('#paymentMethod').val();
        const customProvider = ($('#paymentProviderCustom').val() || '').trim();
        const $providerOpt = $('#paymentProvider option:selected');
        const payNumber = customProvider ? '' : ($providerOpt.attr('data-pay-number') || '');
        const accountName = customProvider ? '' : ($providerOpt.attr('data-account-name') || '');

        if (!payNumber && !accountName) {
            $('#payReceiveDetailsBox').hide();
            return;
        }

        let text = '';
        if (method === 'mobile_money') {
            if (payNumber) text += 'Lipa No: <strong>' + payNumber + '</strong>';
            if (accountName) text += (text ? ' · ' : '') + 'Name: <strong>' + accountName + '</strong>';
        } else if (method === 'bank') {
            if (payNumber) text += 'Account: <strong>' + payNumber + '</strong>';
            if (accountName) text += (text ? ' · ' : '') + 'Name: <strong>' + accountName + '</strong>';
        }

        if (text) {
            $('#payReceiveDetailsText').html(text);
            $('#payReceiveDetailsBox').show();
        }
    }

    $('#paymentProvider, #paymentProviderCustom').on('input change', updatePayReceiveDetails);

    $('#amountPaid').on('input', function() {
        $(this).data('user-edited', true);
        updatePaymentPreview();
    });
    $('#payCustomerPhoneLocal').on('input', syncCustomerPhoneField);

    $('#paymentForm').on('submit', function(e) {
        syncCustomerPhoneField();
        syncPayLineItemsFromDom();
        syncPayLineItemHiddenInputs();
        updateCustomerInfoVisibility();

        if (currentPaymentMode === 'partial' || currentPaymentMode === 'debt') {
            if (!$('#payDueDate').val()) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Repayment date required',
                    text: 'Please select when the customer will pay the remaining balance.',
                    confirmButtonColor: '#940000'
                });
                $('#payDueDate').focus();
                return false;
            }
        }

        if ($('#providerFields').is(':visible')) {
            syncPaymentProviderValue();
            const provider = resolvedPaymentProvider();

            if (!provider) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Provider required',
                    text: 'Select a provider from the list or type a custom provider name.',
                    confirmButtonColor: '#940000'
                });
                if (!$('#paymentProvider').val()) {
                    $('#paymentProviderCustom').focus();
                } else {
                    $('#paymentProvider').focus();
                }
                return false;
            }

            if (!$('#transactionReference').val().trim()) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Reference required',
                    text: 'Please enter the transaction reference number from the payment.',
                    confirmButtonColor: '#940000'
                });
                $('#transactionReference').focus();
                return false;
            }
        }

        const $btn = $('#paymentSubmitBtn');
        if ($btn.prop('disabled')) {
            e.preventDefault();
            return false;
        }
    });

    $(function() {
        if ($('#payCustomerSelect').length && typeof initCustomerPicker === 'function') {
            $('#payCustomerSelect').select2({
                width: '100%',
                placeholder: 'Search registered customer...',
                allowClear: true,
                dropdownParent: $('#paymentModal'),
            });

            initCustomerPicker({
                select: '#payCustomerSelect',
                nameInput: '#payCustomerName',
                phoneInput: '#payCustomerPhoneLocal',
                phoneHidden: '#payCustomerPhone',
            });
        }
    });
</script>

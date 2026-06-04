<script>
(function () {
  function toggleBillingFields() {
    var model = document.getElementById('billing_model').value;
    var isProfit = model === 'profit_share';
    document.querySelectorAll('.billing-profit-field').forEach(function (el) {
      el.style.display = isProfit ? '' : 'none';
    });
    document.querySelectorAll('.billing-fixed-field').forEach(function (el) {
      el.style.display = isProfit ? 'none' : '';
    });
  }

  var billingSelect = document.getElementById('billing_model');
  if (billingSelect) {
    billingSelect.addEventListener('change', toggleBillingFields);
    toggleBillingFields();
  }

  document.querySelectorAll('.plan-storage-unit').forEach(function (unitSelect) {
    var group = unitSelect.closest('.input-group');
    var input = group ? group.querySelector('input[name="max_storage_value"]') : null;
    if (! input) {
      return;
    }

    unitSelect.addEventListener('change', function () {
      input.step = unitSelect.value === 'mb' ? '1' : '0.1';
    });
  });

  function toggleSmsLimitFields() {
    var allowSms = document.getElementById('allow_sms_sending');
    var allowEmail = document.getElementById('allow_email_sms');
    var maxSms = document.getElementById('max_sms');
    var maxEmail = document.getElementById('max_email_sms');
    var smsGroup = document.getElementById('max_sms_group');
    var emailGroup = document.getElementById('max_email_sms_group');

    if (allowSms && maxSms && smsGroup) {
      var smsOn = allowSms.checked;
      maxSms.disabled = ! smsOn;
      smsGroup.style.opacity = smsOn ? '1' : '0.5';
      if (! smsOn) {
        maxSms.value = '0';
      }
    }

    if (allowEmail && maxEmail && emailGroup) {
      var emailOn = allowEmail.checked;
      maxEmail.disabled = ! emailOn;
      emailGroup.style.opacity = emailOn ? '1' : '0.5';
      if (! emailOn) {
        maxEmail.value = '0';
      }
    }
  }

  ['allow_sms_sending', 'allow_email_sms'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) {
      el.addEventListener('change', toggleSmsLimitFields);
    }
  });
  toggleSmsLimitFields();
})();
</script>

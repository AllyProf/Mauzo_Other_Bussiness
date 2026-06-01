@php
  $business = $invoice->business;
  $platform = platform_settings('platform_name', 'SP-POS');
  $instructions = platform_settings('payment_instructions');
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Subscription Invoice</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.5;">
  <h2 style="color: #940000;">{{ $platform }} — Monthly Subscription Invoice</h2>

  <p>Hello {{ $business->contact_person ?: $business->name }},</p>

  <p>Your platform subscription invoice for <strong>{{ $invoice->billingMonthLabel() }}</strong> is ready.</p>

  <table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #ddd; min-width: 320px;">
    <tr><td><strong>Invoice No.</strong></td><td>{{ $invoice->invoice_number }}</td></tr>
    <tr><td><strong>Business</strong></td><td>{{ $business->name }}</td></tr>
    <tr><td><strong>Plan</strong></td><td>{{ $invoice->plan?->name ?? '—' }}</td></tr>
    <tr><td><strong>Billing</strong></td><td>{{ $invoice->plan?->billingModelLabel() ?? '—' }}</td></tr>
    @if($invoice->billing_model === 'profit_share')
    <tr><td><strong>Profit Basis</strong></td><td>{{ $invoice->profit_basis === 'gross_profit' ? 'Gross profit' : 'Net profit' }}</td></tr>
    <tr><td><strong>Business Profit</strong></td><td>TZS {{ number_format((float) $invoice->profit_amount, 0) }}</td></tr>
    <tr><td><strong>Your Share</strong></td><td>{{ number_format((float) $invoice->share_percent, 1) }}%</td></tr>
    @endif
    <tr><td><strong>Amount Due</strong></td><td><strong>TZS {{ number_format((float) $invoice->amount, 0) }}</strong></td></tr>
  </table>

  @if($instructions)
  <h3 style="margin-top: 24px;">How to Pay</h3>
  <div style="white-space: pre-wrap;">{{ $instructions }}</div>
  @endif

  <p style="margin-top: 24px;">
    Support: {{ platform_settings('support_email', 'admin@sp-pos.com') }}
    @if(platform_settings('support_phone'))
      · {{ platform_settings('support_phone') }}
    @endif
  </p>

  <p style="color: #666; font-size: 12px;">Please pay promptly to keep your POS subscription active.</p>
</body>
</html>

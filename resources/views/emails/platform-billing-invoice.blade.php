@php
  $business = $invoice->business;
  $platform = platform_settings('platform_name', 'SP-POS');
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

  <p>Your platform subscription invoice for <strong>{{ $invoice->billingMonthLabel() }}</strong> is ready. The full invoice is attached as a PDF.</p>
</body>
</html>

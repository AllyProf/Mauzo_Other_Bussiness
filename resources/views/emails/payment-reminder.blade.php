@php $platform = platform_settings('platform_name', 'Mauzo Link'); @endphp
<p>Hello {{ $business->contact_person ?: $business->name }},</p>
<p>{{ $bodyMessage }}</p>
@if($invoice)
<p><strong>Invoice:</strong> {{ $invoice->invoice_number }} — <strong>TZS {{ number_format((float) $invoice->amount, 0) }}</strong></p>
@endif
@php $instructions = platform_settings('payment_instructions'); @endphp
@if($instructions)
<h3>How to Pay</h3>
<div style="white-space:pre-wrap">{{ $instructions }}</div>
@endif
<p>Support: {{ platform_settings('support_email') }}</p>
<p style="color:#666;font-size:12px">{{ $platform }}</p>

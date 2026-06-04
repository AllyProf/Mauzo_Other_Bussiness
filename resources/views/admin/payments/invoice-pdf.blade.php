<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $invoice->invoice_number }}</title>
<style>body{font-family:Arial,sans-serif;color:#333;line-height:1.5;padding:24px}h2{color:#940000}table{border-collapse:collapse;width:100%;max-width:520px}td,th{border:1px solid #ddd;padding:8px;text-align:left}</style>
</head><body>
@include('emails.platform-billing-invoice', ['invoice' => $invoice])
</body></html>

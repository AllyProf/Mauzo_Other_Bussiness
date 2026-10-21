@php
  $platformPaymentMethods = platform_invoice_payment_methods();
  $paymentIntro = trim((string) platform_settings('invoice_payment_intro'));
  $paymentInstructions = trim((string) platform_settings('payment_instructions'));
  $hasPaymentInfo = $platformPaymentMethods !== [] || $paymentInstructions !== '';
@endphp
@if($hasPaymentInfo)
<div class="alert alert-light border text-left mb-4 subscription-platform-payment">
  <strong><i class="fa fa-credit-card"></i> Payment methods</strong>
  @if($paymentIntro !== '')
    <p class="mb-2 mt-2 small text-muted">{{ $paymentIntro }}</p>
  @endif
  @if($platformPaymentMethods !== [])
    <div class="table-responsive mt-2">
      <table class="table table-sm table-bordered mb-0 {{ $paymentMethodsTableClass ?? '' }}">
        <thead class="thead-light">
          <tr>
            <th>Method</th>
            <th>Account name</th>
            <th>Pay number / account</th>
          </tr>
        </thead>
        <tbody>
          @foreach($platformPaymentMethods as $method)
          <tr>
            <td data-label="Method">{{ $method['provider'] !== '' ? $method['provider'] : '—' }}</td>
            <td data-label="Account name">{{ $method['account_name'] !== '' ? $method['account_name'] : '—' }}</td>
            <td data-label="Pay number / account">
              @if($method['number'] !== '')
                <strong>{{ $method['number_label'] }}:</strong> {{ $method['number'] }}
              @else
                —
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
  @if($paymentInstructions !== '')
    <div class="mt-3 mb-0" style="white-space:pre-wrap;">{{ $paymentInstructions }}</div>
  @endif
  @if(platform_settings('support_phone') || platform_settings('support_email'))
    <p class="mb-0 mt-3 small text-muted">
      After paying, contact support to confirm:
      @if(platform_settings('support_phone'))
        <i class="fa fa-phone"></i> {{ platform_settings('support_phone') }}
      @endif
      @if(platform_settings('support_email'))
        @if(platform_settings('support_phone')) · @endif
        <i class="fa fa-envelope"></i> {{ platform_settings('support_email') }}
      @endif
    </p>
  @endif
</div>
@endif

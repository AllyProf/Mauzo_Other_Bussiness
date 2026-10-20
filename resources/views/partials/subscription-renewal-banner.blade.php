@php
  $subscriptionRenewalNotice = $subscriptionRenewalNotice
    ?? (auth()->user()?->role === 'owner'
      ? subscription_renewal_notice_for(active_business() ?? auth()->user()?->business)
      : null);
@endphp
@if(!empty($subscriptionRenewalNotice))
@php
  $notice = $subscriptionRenewalNotice;
  $daysLeft = (int) ($notice['days_left'] ?? 0);
  $expiryLabel = $notice['expiry_date']?->format('d M Y') ?? '—';
  $isFree = !empty($notice['is_free_renewal']);
@endphp
<div class="alert alert-danger border-danger d-flex flex-wrap justify-content-between align-items-center mb-4 subscription-renewal-banner" role="alert">
  <img
    src="{{ asset('gp-assets/img/Alert Icon Exclamation.gif') }}"
    alt=""
    width="52"
    height="52"
    aria-hidden="true"
    class="subscription-renewal-banner__icon"
  >
  <div class="mb-2 mb-md-0 pr-md-3 flex-grow-1 subscription-renewal-banner__body">
    <strong class="subscription-renewal-banner__title">Subscription renewal</strong>
    @if($daysLeft === 0)
      <span class="d-block mt-1">Your <strong>{{ $notice['plan_name'] }}</strong> plan expires <strong>today</strong> ({{ $expiryLabel }}).</span>
    @elseif($daysLeft === 1)
      <span class="d-block mt-1">Your <strong>{{ $notice['plan_name'] }}</strong> plan expires <strong>tomorrow</strong> ({{ $expiryLabel }}).</span>
    @else
      <span class="d-block mt-1">Your <strong>{{ $notice['plan_name'] }}</strong> plan expires in <strong>{{ $daysLeft }} days</strong> ({{ $expiryLabel }}).</span>
    @endif
    @if($isFree)
      <small class="d-block text-muted mt-1">This is a free plan — ask the platform administrator to extend your period (no payment). Use the upgrade section below if you want a paid plan.</small>
    @else
      <small class="d-block text-muted mt-1">Renew before expiry to avoid interruption. @if(isset($notice['renewal_amount'])) Renewal fee: <strong>TZS {{ number_format((float) $notice['renewal_amount'], 0) }}</strong>. @endif</small>
    @endif
  </div>
  @if(request()->routeIs('subscription.upgrade'))
    @if($isFree && (platform_settings('support_email') || platform_settings('support_phone')))
      <div class="small text-muted mb-0">
        @if(platform_settings('support_email'))
          <i class="fa fa-envelope"></i> {{ platform_settings('support_email') }}
        @endif
        @if(platform_settings('support_phone'))
          <br><i class="fa fa-phone"></i> {{ platform_settings('support_phone') }}
        @endif
      </div>
    @endif
  @else
    <a href="{{ route('subscription.upgrade') }}" class="btn btn-sm btn-danger font-weight-bold subscription-renewal-banner__cta">
      <i class="fa fa-refresh"></i> View plan &amp; renew
    </a>
  @endif
</div>
@endif

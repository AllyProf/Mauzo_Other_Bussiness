@if(($whatsappDigits ?? false) && $website->sectionEnabled('whatsapp_float'))
  @php
    $waFloatMsg = urlencode(($sections['order']['whatsapp_message'] ?? null) ?: __('public_site.order.wa_message', ['name' => $business->name]));
  @endphp
  <a href="https://wa.me/{{ $whatsappDigits }}?text={{ $waFloatMsg }}"
     class="site-wa-float"
     target="_blank"
     rel="noopener"
     aria-label="{{ __('public_site.actions.chat_whatsapp') }}"
     title="{{ __('public_site.actions.chat_whatsapp') }}">
    <i class="bi bi-whatsapp"></i>
  </a>
@endif

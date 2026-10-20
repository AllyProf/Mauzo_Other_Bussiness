@if(tenant_system_guide_visible())
<li class="breadcrumb-item breadcrumb-manual-item">
  <a href="#"
     role="button"
     class="breadcrumb-manual-link js-page-manual-open @if(request()->routeIs('help.getting-started')) is-active @endif"
     data-toggle="modal"
     data-target="#pageManualModal"
     title="{{ __('menu.system_guide') }}"
     aria-label="{{ __('menu.system_guide') }}">
    <span class="breadcrumb-manual-icon"><i class="fa fa-book"></i></span>
  </a>
</li>
@endif

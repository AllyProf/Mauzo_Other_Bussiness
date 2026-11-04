@extends('layouts.app')

@section('title', 'Business Website')

@section('content')
@php
  $sections = $siteConfig['sections'] ?? [];
  $payment = $siteConfig['payment'] ?? [];
  $sectionMeta = [
    'promo' => ['label' => 'Promo / specials', 'edit' => 'home', 'section' => 'promo', 'hint' => 'Featured deals strip'],
    'products' => ['label' => 'Products', 'edit' => 'products', 'section' => null, 'hint' => 'Live catalog from Mauzo'],
    'order' => ['label' => 'Order', 'edit' => 'order', 'section' => null, 'hint' => 'How to order + delivery/payment notes'],
    'gallery' => ['label' => 'Gallery', 'edit' => 'home', 'section' => 'gallery', 'hint' => 'Shop photos'],
    'contact' => ['label' => 'Contact', 'edit' => 'contact', 'section' => null, 'hint' => 'Map, phone, intro'],
    'share' => ['label' => 'Share / QR scan', 'edit' => null, 'section' => null, 'hint' => 'Share link + QR animation'],
    'open_badge' => ['label' => 'Open now badge', 'edit' => 'contact', 'section' => 'hours', 'hint' => 'Uses open/close times'],
    'whatsapp_float' => ['label' => 'Floating WhatsApp', 'edit' => null, 'section' => null, 'hint' => 'Green chat button'],
  ];
@endphp

<div class="app-title">
  <div>
    <h1><i class="fa fa-globe"></i> Business Website</h1>
    <p>Customize your public storefront — sections, content, and printable payment QR.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    @include('layouts.partials._breadcrumb_manual')
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Business Website</li>
  </ul>
</div>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row">
  <div class="col-lg-5">
    <div class="tile">
      <h3 class="tile-title">Site settings</h3>
      <div class="tile-body">
        <form method="POST" action="{{ route('business-website.settings.update') }}">
          @csrf
          @method('PUT')
          <div class="form-group">
            <label class="font-weight-bold">Web address</label>
            <div class="input-group">
              <div class="input-group-prepend"><span class="input-group-text">{{ url('/site') }}/</span></div>
              <input type="text" name="slug" class="form-control" value="{{ old('slug', $website->slug) }}" required pattern="[a-z0-9]+(-[a-z0-9]+)*">
            </div>
            @error('slug')<small class="text-danger">{{ $message }}</small>@enderror
            <small class="text-muted">Lowercase letters, numbers, and hyphens only.</small>
          </div>
          <div class="form-group">
            <label class="font-weight-bold">Tagline</label>
            <input type="text" name="tagline" class="form-control" maxlength="255" value="{{ old('tagline', $website->tagline) }}" placeholder="Short line under your business name">
          </div>
          <div class="form-group">
            <label class="font-weight-bold">Brand color</label>
            <input type="color" name="primary_color" class="form-control" style="max-width:80px;height:42px;padding:4px;" value="{{ old('primary_color', $website->primary_color ?? '#940000') }}">
          </div>
          <div class="form-group">
            <label class="font-weight-bold">WhatsApp number</label>
            <input type="text" name="whatsapp" class="form-control" value="{{ old('whatsapp', $website->social_links['whatsapp'] ?? $business->phone) }}" placeholder="+255...">
          </div>
          <div class="form-group">
            <label class="font-weight-bold">Facebook URL</label>
            <input type="url" name="facebook" class="form-control" value="{{ old('facebook', $website->social_links['facebook'] ?? '') }}">
          </div>
          <div class="form-group">
            <label class="font-weight-bold">Instagram URL</label>
            <input type="url" name="instagram" class="form-control" value="{{ old('instagram', $website->social_links['instagram'] ?? '') }}">
          </div>
          <div class="form-group">
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="is_published" name="is_published" value="1" {{ old('is_published', $website->is_published) ? 'checked' : '' }}>
              <label class="custom-control-label font-weight-bold" for="is_published">Publish website (visible to the public)</label>
            </div>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save settings</button>
        </form>
      </div>
    </div>

    <div class="tile">
      <h3 class="tile-title">Website URL</h3>
      <div class="tile-body">
        @php $publicSiteUrl = $website->publicUrl(); @endphp
        <div class="input-group mb-2">
          <input type="text"
                 id="businessWebsiteUrl"
                 class="form-control"
                 value="{{ $publicSiteUrl }}"
                 readonly
                 aria-label="Public website URL"
                 onclick="this.select()">
          <div class="input-group-append">
            <button type="button"
                    class="btn btn-outline-secondary"
                    id="copyBusinessWebsiteUrl"
                    title="Copy URL"
                    data-url="{{ $publicSiteUrl }}">
              <i class="fa fa-copy"></i> Copy
            </button>
            <a href="{{ $publicSiteUrl }}"
               target="_blank"
               rel="noopener"
               class="btn btn-{{ $website->is_published ? 'success' : 'outline-primary' }}"
               title="Open website">
              <i class="fa fa-external-link"></i> Open
            </a>
          </div>
        </div>
        @if($website->is_published)
          <p class="text-muted small mb-0">Published — share this link with customers.</p>
        @else
          <p class="text-muted small mb-0">Not published yet. Turn on <strong>Publish website</strong>.</p>
        @endif
        <p class="text-muted small mt-3 mb-0">Logo, phone, email, and address come from <a href="{{ route('settings.index') }}">Business settings</a>.</p>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="tile">
      <h3 class="tile-title">Sections — enable / disable</h3>
      <div class="tile-body">
        <form method="POST" action="{{ route('business-website.sections.update') }}">
          @csrf
          @method('PUT')
          <div class="list-group mb-3">
            @foreach($sectionMeta as $key => $meta)
              <div class="list-group-item d-flex align-items-center justify-content-between">
                <div class="pr-3">
                  <div class="custom-control custom-switch">
                    <input type="checkbox"
                           class="custom-control-input"
                           id="section_{{ $key }}"
                           name="section_{{ $key }}"
                           value="1"
                           {{ !empty($sections[$key]) ? 'checked' : '' }}>
                    <label class="custom-control-label font-weight-bold" for="section_{{ $key }}">{{ $meta['label'] }}</label>
                  </div>
                  <div class="small text-muted ml-4 pl-1">{{ $meta['hint'] }}</div>
                </div>
                @if($meta['edit'])
                  <a href="{{ route('business-website.edit-page', array_filter(['page' => $meta['edit'], 'section' => $meta['section'] ?? null])) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                @endif
              </div>
            @endforeach
          </div>
          <div class="d-flex align-items-center justify-content-between flex-wrap">
            <a href="{{ route('business-website.edit-page', ['page' => 'home', 'section' => 'hero']) }}" class="btn btn-sm btn-outline-secondary mb-2"><i class="fa fa-home"></i> Edit Hero</a>
            <button type="submit" class="btn btn-primary mb-2"><i class="fa fa-save"></i> Save sections</button>
          </div>
        </form>
      </div>
    </div>

    <div class="tile">
      <h3 class="tile-title">Shop website QR (print standee)</h3>
      <div class="tile-body">
        <p class="text-muted small mb-2">
          The QR opens your <strong>website URL</strong>
          <code class="small">{{ $website->publicUrl() }}</code>.
          Print a counter standee so customers can scan and open your shop site.
        </p>
        <div class="mb-3 d-flex align-items-center p-2 border rounded bg-light">
          <img src="{{ $website->qrCodeUrl(120) }}" alt="Website QR" width="96" height="96" class="mr-3" style="background:#fff;padding:6px;border-radius:6px;">
          <div class="small text-muted">Preview of the website QR that will be printed.</div>
        </div>
        <form method="POST" action="{{ route('business-website.payment-qr.update') }}">
          @csrf
          @method('PUT')
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="font-weight-bold">Shop name on standee</label>
              <input type="text" name="merchant_name" class="form-control text-uppercase" value="{{ old('merchant_name', $payment['merchant_name'] ?? $business->name) }}" placeholder="ALLY ENGINEER">
            </div>
            <div class="col-md-6 form-group">
              <label class="font-weight-bold">Code / phone (optional)</label>
              <input type="text" name="merchant_id" class="form-control" value="{{ old('merchant_id', $payment['merchant_id'] ?? '') }}" placeholder="e.g. phone or short code">
            </div>
          </div>
          @php
            $savedTitle = old('payment_title', $payment['title'] ?? '');
            $savedFooter = old('payment_footer_text', $payment['footer_text'] ?? '');
            if ($savedTitle === '' || str_contains(strtolower($savedTitle), 'lipa')) {
                $savedTitle = 'Visit our website';
            }
            if ($savedFooter === '' || str_contains(strtolower((string) $savedFooter), 'lipa') || str_contains(strtolower((string) $savedFooter), 'benki') || str_contains(strtolower((string) $savedFooter), 'products')) {
                $savedFooter = 'Scan to open';
            }
          @endphp
          <div class="form-group">
            <label class="font-weight-bold">Headline</label>
            <input type="text" name="payment_title" class="form-control" value="{{ $savedTitle }}" placeholder="Visit our website">
            <small class="text-muted">Top line under the circle (like “SCAN AND ORDER”).</small>
          </div>
          <div class="form-group">
            <label class="font-weight-bold">Big line</label>
            <input type="text" name="payment_footer_text" class="form-control" value="{{ $savedFooter }}" placeholder="Scan to open">
            <small class="text-muted">Large bottom line (like “OUR MENU”).</small>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary mr-2 mb-2"><i class="fa fa-save"></i> Save standee text</button>
            <a href="{{ route('business-website.payment-qr.print') }}" target="_blank" rel="noopener" class="btn btn-success mb-2">
              <i class="fa fa-print"></i> Preview &amp; print standee
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
  var btn = document.getElementById('copyBusinessWebsiteUrl');
  var input = document.getElementById('businessWebsiteUrl');
  if (!btn || !input) return;

  function flashCopied() {
    var original = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-check"></i> Copied';
    btn.classList.add('btn-success');
    btn.classList.remove('btn-outline-secondary');
    setTimeout(function () {
      btn.innerHTML = original;
      btn.classList.remove('btn-success');
      btn.classList.add('btn-outline-secondary');
    }, 1600);
  }

  btn.addEventListener('click', function () {
    var url = btn.getAttribute('data-url') || input.value;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(flashCopied).catch(function () {
        input.select();
        document.execCommand('copy');
        flashCopied();
      });
      return;
    }
    input.select();
    document.execCommand('copy');
    flashCopied();
  });
})();
</script>
@endsection

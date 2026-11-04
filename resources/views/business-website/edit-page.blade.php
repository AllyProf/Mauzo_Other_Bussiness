@extends('layouts.app')

@section('title', 'Edit '.$pageLabel.' — Business Website')

@section('content')
@php
  $section = $section ?? ($page === 'home' ? 'hero' : null);
  $showHero = $page === 'home' && in_array($section, ['hero', 'home'], true);
  $showPromo = $page === 'home' && in_array($section, ['promo', 'home'], true);
  $showGallery = $page === 'home' && in_array($section, ['gallery', 'home'], true);
  $showContactFull = $page === 'contact' && $section !== 'hours';
  $showHours = $page === 'contact' && ($section === 'hours' || $section === null);
@endphp
<div class="app-title">
  <div>
    <h1><i class="fa fa-pencil"></i> {{ $pageLabel }}</h1>
    <p>Edit only this part of your public website.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    @include('layouts.partials._breadcrumb_manual')
    <li class="breadcrumb-item"><a href="{{ route('business-website.index') }}">Business Website</a></li>
    <li class="breadcrumb-item active">{{ $pageLabel }}</li>
  </ul>
</div>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="tile">
  <div class="tile-body">
    <form method="POST" action="{{ route('business-website.update-page', $page) }}" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      @if($section)
        <input type="hidden" name="section" value="{{ $section }}">
      @endif

      @if($showHero)
        <div class="form-group"><label class="font-weight-bold">Hero title</label>
          <input type="text" name="hero_title" class="form-control" value="{{ old('hero_title', $content['hero_title'] ?? '') }}" required></div>
        <div class="form-group"><label class="font-weight-bold">Hero subtitle</label>
          <textarea name="hero_subtitle" class="form-control" rows="2">{{ old('hero_subtitle', $content['hero_subtitle'] ?? '') }}</textarea></div>
        <div class="form-group">
          <label class="font-weight-bold">Hero image</label>
          @php
            $heroPath = $content['hero_image_path'] ?? null;
            $heroPreview = $website->uploadedMediaUrl(is_string($heroPath) ? $heroPath : null);
          @endphp
          @if($heroPreview)
            <div class="mb-2 site-upload-preview site-upload-preview--hero">
              <img src="{{ $heroPreview }}" alt="Current hero">
            </div>
            <div class="custom-control custom-checkbox mb-2">
              <input type="checkbox" class="custom-control-input" id="remove_hero_image" name="remove_hero_image" value="1">
              <label class="custom-control-label" for="remove_hero_image">Remove current image (use default)</label>
            </div>
          @endif
          <input type="file" name="hero_image" class="form-control-file" accept="image/*">
          <small class="text-muted d-block mt-1">JPG, PNG or WebP · max 4MB. Shown behind the welcome text.</small>
          @error('hero_image')<small class="text-danger d-block">{{ $message }}</small>@enderror
        </div>
        <div class="row">
          <div class="col-md-6 form-group"><label class="font-weight-bold">Button label</label>
            <input type="text" name="hero_button_label" class="form-control" value="{{ old('hero_button_label', $content['hero_button_label'] ?? '') }}"></div>
          <div class="col-md-6 form-group"><label class="font-weight-bold">Button link</label>
            <input type="text" name="hero_button_url" class="form-control" value="{{ old('hero_button_url', $content['hero_button_url'] ?? 'order') }}" placeholder="order or #products">
            <small class="text-muted">Use <code>order</code> or <code>products</code> for on-page sections.</small></div>
        </div>
        <div class="form-group"><label class="font-weight-bold">Products section title (optional fallback)</label>
          <input type="text" name="intro_title" class="form-control" value="{{ old('intro_title', $content['intro_title'] ?? '') }}"></div>
        <div class="form-group"><label class="font-weight-bold">Products section caption (optional fallback)</label>
          <textarea name="intro_body" class="form-control" rows="2">{{ old('intro_body', $content['intro_body'] ?? '') }}</textarea></div>
      @endif

      @if($showPromo)
        <div class="form-group"><label class="font-weight-bold">Promo title</label>
          <input type="text" name="promo_title" class="form-control" value="{{ old('promo_title', $content['promo_title'] ?? '') }}" placeholder="Today’s specials"></div>
        <div class="form-group"><label class="font-weight-bold">Promo items (one per line, max 5)</label>
          <textarea name="promo_items" class="form-control" rows="5" placeholder="Fresh bread — TZS 1,500&#10;Cooking oil 1L — special price">{{ old('promo_items', implode("\n", $content['promo_items'] ?? [])) }}</textarea>
          <small class="text-muted">Shown above the product list on the public site.</small>
        </div>
      @endif

      @if($showGallery)
        @php
          $galleryPaths = collect($content['gallery_paths'] ?? [])->filter()->values();
        @endphp
        @if($galleryPaths->isNotEmpty())
          <div class="row mb-3">
            @foreach($galleryPaths as $gPath)
              @php
                $gUrl = $website->uploadedMediaUrl(is_string($gPath) ? $gPath : null);
              @endphp
              @if($gUrl)
                <div class="col-6 col-md-3 mb-2">
                  <div class="border rounded p-1">
                    <div class="site-upload-preview site-upload-preview--gallery">
                      <img src="{{ $gUrl }}" alt="">
                    </div>
                    <div class="custom-control custom-checkbox mt-1">
                      <input type="checkbox" class="custom-control-input" id="remove_gallery_{{ $loop->index }}" name="remove_gallery[]" value="{{ $gPath }}">
                      <label class="custom-control-label small" for="remove_gallery_{{ $loop->index }}">Remove</label>
                    </div>
                  </div>
                </div>
              @endif
            @endforeach
          </div>
        @endif
        <div class="form-group">
          <label class="font-weight-bold">Add photos</label>
          <input type="file" name="gallery_images[]" class="form-control-file" accept="image/*" multiple>
          <small class="text-muted d-block mt-1">Up to 8 photos total · JPG/PNG/WebP · max 4MB each. Photos are cropped to fit square gallery tiles.</small>
          @error('gallery_images.*')<small class="text-danger d-block">{{ $message }}</small>@enderror
        </div>
      @endif

      @if($page === 'products')
        @php
          $hiddenCategoryIds = collect(old('hidden_category_ids', $content['hidden_category_ids'] ?? []))->map(fn ($id) => (int) $id)->all();
          $hiddenItemIds = collect(old('hidden_item_ids', $content['hidden_item_ids'] ?? []))->map(fn ($id) => (int) $id)->all();
          $catalogCategories = $catalogCategories ?? collect();
          $uncategorizedItems = $uncategorizedItems ?? collect();
        @endphp
        <div class="form-group"><label class="font-weight-bold">Page title</label>
          <input type="text" name="title" class="form-control" value="{{ old('title', $content['title'] ?? '') }}" required></div>
        <div class="form-group"><label class="font-weight-bold">Intro text</label>
          <textarea name="body" class="form-control" rows="3">{{ old('body', $content['body'] ?? '') }}</textarea></div>
        <div class="custom-control custom-checkbox mb-2">
          <input type="checkbox" class="custom-control-input" id="show_prices" name="show_prices" value="1" {{ old('show_prices', $content['show_prices'] ?? true) ? 'checked' : '' }}>
          <label class="custom-control-label" for="show_prices">Show selling prices</label>
        </div>
        <div class="custom-control custom-checkbox mb-3">
          <input type="checkbox" class="custom-control-input" id="show_out_of_stock" name="show_out_of_stock" value="1" {{ old('show_out_of_stock', $content['show_out_of_stock'] ?? false) ? 'checked' : '' }}>
          <label class="custom-control-label" for="show_out_of_stock">Show out-of-stock items</label>
        </div>
        <p class="text-muted small mb-3">Products are loaded automatically from Items in Mauzo Link. Tick below to hide categories or specific items from the public website.</p>

        <hr>
        <h5 class="mb-2">Hide categories</h5>
        <p class="text-muted small">Checked categories will not appear on the public shop site.</p>
        @if($catalogCategories->isEmpty())
          <p class="text-muted small">No categories found yet.</p>
        @else
          <div class="site-restrict-box mb-4">
            @foreach($catalogCategories as $category)
              <div class="custom-control custom-checkbox mb-2">
                <input type="checkbox"
                       class="custom-control-input site-hide-category"
                       id="hide_cat_{{ $category->id }}"
                       name="hidden_category_ids[]"
                       value="{{ $category->id }}"
                       data-category-id="{{ $category->id }}"
                       {{ in_array((int) $category->id, $hiddenCategoryIds, true) ? 'checked' : '' }}>
                <label class="custom-control-label" for="hide_cat_{{ $category->id }}">
                  {{ $category->name }}
                  <span class="text-muted small">({{ $category->items->count() }} items)</span>
                </label>
              </div>
            @endforeach
          </div>
        @endif

        <h5 class="mb-2">Hide specific items</h5>
        <p class="text-muted small">Use this when you want to hide only some items inside a visible category.</p>
        <div class="form-group">
          <input type="search" id="siteItemRestrictFilter" class="form-control" placeholder="Search items…" autocomplete="off">
        </div>
        @if($catalogCategories->isEmpty() && $uncategorizedItems->isEmpty())
          <p class="text-muted small">No items found yet.</p>
        @else
          <div class="site-restrict-box site-restrict-box--tall mb-2" id="siteItemRestrictList">
            @foreach($catalogCategories as $category)
              <div class="site-restrict-group" data-category-id="{{ $category->id }}">
                <div class="font-weight-bold small text-uppercase text-muted mb-2">{{ $category->name }}</div>
                @forelse($category->items as $item)
                  <div class="custom-control custom-checkbox mb-1 site-restrict-item"
                       data-search="{{ strtolower($item->name.' '.($item->brand ?? '').' '.$category->name) }}">
                    <input type="checkbox"
                           class="custom-control-input"
                           id="hide_item_{{ $item->id }}"
                           name="hidden_item_ids[]"
                           value="{{ $item->id }}"
                           {{ in_array((int) $item->id, $hiddenItemIds, true) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="hide_item_{{ $item->id }}">
                      {{ $item->name }}
                      @if($item->brand)<span class="text-muted small">· {{ $item->brand }}</span>@endif
                    </label>
                  </div>
                @empty
                  <p class="text-muted small mb-2">No items in this category.</p>
                @endforelse
              </div>
            @endforeach
            @if($uncategorizedItems->isNotEmpty())
              <div class="site-restrict-group">
                <div class="font-weight-bold small text-uppercase text-muted mb-2">Uncategorized</div>
                @foreach($uncategorizedItems as $item)
                  <div class="custom-control custom-checkbox mb-1 site-restrict-item"
                       data-search="{{ strtolower($item->name.' '.($item->brand ?? '').' uncategorized') }}">
                    <input type="checkbox"
                           class="custom-control-input"
                           id="hide_item_{{ $item->id }}"
                           name="hidden_item_ids[]"
                           value="{{ $item->id }}"
                           {{ in_array((int) $item->id, $hiddenItemIds, true) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="hide_item_{{ $item->id }}">
                      {{ $item->name }}
                      @if($item->brand)<span class="text-muted small">· {{ $item->brand }}</span>@endif
                    </label>
                  </div>
                @endforeach
              </div>
            @endif
          </div>
          <small class="text-muted d-block">Items in a hidden category are already excluded — you do not need to tick them separately.</small>
        @endif
      @endif

      @if($page === 'about')
        <div class="form-group"><label class="font-weight-bold">Page title</label>
          <input type="text" name="title" class="form-control" value="{{ old('title', $content['title'] ?? '') }}" required></div>
        <div class="form-group"><label class="font-weight-bold">Main text</label>
          <textarea name="body" class="form-control" rows="5">{{ old('body', $content['body'] ?? '') }}</textarea></div>
        @for($i = 1; $i <= 3; $i++)
          <hr><h5>Highlight {{ $i }}</h5>
          <div class="form-group"><label>Title</label>
            <input type="text" name="highlight_{{ $i }}_title" class="form-control" value="{{ old('highlight_'.$i.'_title', $content['highlight_'.$i.'_title'] ?? '') }}"></div>
          <div class="form-group"><label>Text</label>
            <textarea name="highlight_{{ $i }}_body" class="form-control" rows="2">{{ old('highlight_'.$i.'_body', $content['highlight_'.$i.'_body'] ?? '') }}</textarea></div>
        @endfor
      @endif

      @if($showContactFull)
        <div class="form-group"><label class="font-weight-bold">Page title</label>
          <input type="text" name="title" class="form-control" value="{{ old('title', $content['title'] ?? '') }}" required></div>
        <div class="form-group"><label class="font-weight-bold">Intro text</label>
          <textarea name="body" class="form-control" rows="3">{{ old('body', $content['body'] ?? '') }}</textarea></div>
        <p class="text-muted small">Contact details (phone, WhatsApp, email, address) come from Business settings.</p>
        <input type="hidden" name="show_contact_form" value="0">

        <hr class="my-4">
        <h5 class="mb-3">Map</h5>
        <div class="custom-control custom-checkbox mb-3">
          <input type="checkbox" class="custom-control-input" id="show_map" name="show_map" value="1" {{ old('show_map', $content['show_map'] ?? true) ? 'checked' : '' }}>
          <label class="custom-control-label font-weight-bold" for="show_map">Show map on the public contact section</label>
        </div>
        <div class="form-group">
          <label class="font-weight-bold">Map location</label>
          <input type="text"
                 name="map_query"
                 class="form-control"
                 value="{{ old('map_query', $content['map_query'] ?? '') }}"
                 placeholder="{{ $business->address ?: ($business->name.' Tanzania') }}">
          <small class="text-muted">Search text for Google Maps. Leave blank to use the business address from settings.</small>
        </div>
        <div class="form-group mb-0">
          <label class="font-weight-bold">Google Maps embed URL <span class="text-muted font-weight-normal">(optional)</span></label>
          <textarea name="map_embed_url"
                    class="form-control"
                    rows="3"
                    placeholder="https://maps.google.com/maps?q=...&output=embed">{{ old('map_embed_url', $content['map_embed_url'] ?? '') }}</textarea>
          <small class="text-muted d-block mt-1">
            Paste a Google Maps embed link, or the whole iframe code from Google Maps → Share → Embed a map.
            If set, this overrides the location text above.
          </small>
          @error('map_embed_url')<small class="text-danger d-block">{{ $message }}</small>@enderror
        </div>
      @endif

      @if($showHours)
        @if($showContactFull)<hr class="my-4">@endif
        <div class="form-group"><label class="font-weight-bold">Opening hours (display text)</label>
          <input type="text" name="hours" class="form-control" value="{{ old('hours', $content['hours'] ?? '') }}" placeholder="Mon – Sat: 8:00 – 20:00"></div>
        <div class="row">
          <div class="col-md-6 form-group">
            <label class="font-weight-bold">Opens at</label>
            <input type="time" name="open_time" class="form-control" value="{{ old('open_time', $content['open_time'] ?? '08:00') }}">
          </div>
          <div class="col-md-6 form-group">
            <label class="font-weight-bold">Closes at</label>
            <input type="time" name="close_time" class="form-control" value="{{ old('close_time', $content['close_time'] ?? '20:00') }}">
          </div>
        </div>
        <p class="text-muted small">Used for the Open now / Closed badge (Tanzania time). Leave blank to hide the badge.</p>
      @endif

      @if($page === 'order')
        <div class="form-group"><label class="font-weight-bold">Page title</label>
          <input type="text" name="title" class="form-control" value="{{ old('title', $content['title'] ?? '') }}" required></div>
        <div class="form-group"><label class="font-weight-bold">Intro text</label>
          <textarea name="body" class="form-control" rows="3">{{ old('body', $content['body'] ?? '') }}</textarea></div>
        <div class="form-group"><label class="font-weight-bold">How to order (steps)</label>
          <textarea name="steps" class="form-control" rows="5">{{ old('steps', $content['steps'] ?? '') }}</textarea></div>
        <div class="form-group"><label class="font-weight-bold">Default WhatsApp message</label>
          <input type="text" name="whatsapp_message" class="form-control" value="{{ old('whatsapp_message', $content['whatsapp_message'] ?? '') }}"></div>
        <div class="form-group"><label class="font-weight-bold">Delivery notes</label>
          <textarea name="delivery_notes" class="form-control" rows="2" placeholder="Delivery in town same day. Outside town on request.">{{ old('delivery_notes', $content['delivery_notes'] ?? '') }}</textarea></div>
        <div class="form-group"><label class="font-weight-bold">Payment notes</label>
          <textarea name="payment_notes" class="form-control" rows="2" placeholder="M-Pesa, cash on collection.">{{ old('payment_notes', $content['payment_notes'] ?? '') }}</textarea></div>
      @endif

      <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save</button>
      <a href="{{ route('business-website.index') }}" class="btn btn-secondary">Back</a>
    </form>
  </div>
</div>
@endsection

@section('styles')
<style>
  .site-upload-preview {
    width: 100%;
    overflow: hidden;
    border-radius: 6px;
    background: #f1f1f1;
    position: relative;
  }
  .site-upload-preview img {
    position: absolute;
    inset: 0;
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    object-position: center center !important;
    display: block;
  }
  .site-upload-preview--hero {
    aspect-ratio: 16 / 7;
    max-height: 200px;
  }
  .site-upload-preview--gallery {
    aspect-ratio: 1 / 1;
  }
  .site-restrict-box {
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 12px 14px;
    background: #fafafa;
    max-height: 220px;
    overflow: auto;
  }
  .site-restrict-box--tall { max-height: 360px; }
  .site-restrict-group + .site-restrict-group {
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid #e9ecef;
  }
  .site-restrict-group.is-category-hidden { opacity: .55; }
</style>
@endsection

@section('scripts')
<script>
(function () {
  var filter = document.getElementById('siteItemRestrictFilter');
  var list = document.getElementById('siteItemRestrictList');
  if (filter && list) {
    filter.addEventListener('input', function () {
      var q = (filter.value || '').toLowerCase().trim();
      list.querySelectorAll('.site-restrict-item').forEach(function (row) {
        var hay = row.getAttribute('data-search') || '';
        row.style.display = (!q || hay.indexOf(q) !== -1) ? '' : 'none';
      });
      list.querySelectorAll('.site-restrict-group').forEach(function (group) {
        var visible = Array.prototype.some.call(group.querySelectorAll('.site-restrict-item'), function (row) {
          return row.style.display !== 'none';
        });
        group.style.display = visible ? '' : 'none';
      });
    });
  }

  function syncHiddenCategories() {
    document.querySelectorAll('.site-hide-category').forEach(function (cb) {
      var group = document.querySelector('.site-restrict-group[data-category-id="' + cb.getAttribute('data-category-id') + '"]');
      if (group) group.classList.toggle('is-category-hidden', cb.checked);
    });
  }
  document.querySelectorAll('.site-hide-category').forEach(function (cb) {
    cb.addEventListener('change', syncHiddenCategories);
  });
  syncHiddenCategories();
})();
</script>
@endsection

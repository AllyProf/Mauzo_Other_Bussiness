@extends('layouts.app')

@section('title', 'Business Website')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-globe"></i> Business Website</h1>
    <p>Configure your public site — Home, About, Contact, and Order pages.</p>
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
  </div>

  <div class="col-lg-7">
    <div class="tile">
      <h3 class="tile-title">Pages</h3>
      <div class="tile-body">
        @php
          $pages = [
            'home' => ['label' => 'Home', 'icon' => 'fa-home', 'desc' => 'Hero welcome and introduction'],
            'products' => ['label' => 'Products', 'icon' => 'fa-tags', 'desc' => 'Catalog from your Mauzo Link items'],
            'about' => ['label' => 'About', 'icon' => 'fa-info-circle', 'desc' => 'Story and highlights'],
            'contact' => ['label' => 'Contact', 'icon' => 'fa-envelope', 'desc' => 'Location, hours, contact form'],
            'order' => ['label' => 'Order', 'icon' => 'fa-shopping-bag', 'desc' => 'How customers place orders'],
          ];
        @endphp
        <div class="list-group">
          @foreach($pages as $key => $meta)
            <div class="list-group-item d-flex align-items-center justify-content-between">
              <div>
                <i class="fa {{ $meta['icon'] }} text-danger mr-2"></i>
                <strong>{{ $meta['label'] }}</strong>
                <div class="small text-muted">{{ $meta['desc'] }}</div>
              </div>
              <a href="{{ route('business-website.edit-page', $key) }}" class="btn btn-sm btn-outline-primary">Edit</a>
            </div>
          @endforeach
        </div>

        <hr>
        <p class="mb-2 font-weight-bold">Live link</p>
        @if($website->is_published)
          <a href="{{ $website->publicUrl() }}" target="_blank" rel="noopener" class="btn btn-success btn-sm">
            <i class="fa fa-external-link"></i> {{ $website->publicUrl() }}
          </a>
        @else
          <p class="text-muted small mb-0">Turn on <strong>Publish website</strong> to make {{ url('/site/'.$website->slug) }} public.</p>
        @endif
        <p class="text-muted small mt-3 mb-0">Logo, phone, email, and address come from <a href="{{ route('settings.index') }}">Business settings</a>.</p>
      </div>
    </div>
  </div>
</div>
@endsection

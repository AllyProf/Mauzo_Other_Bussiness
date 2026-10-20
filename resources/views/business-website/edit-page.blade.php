@extends('layouts.app')

@section('title', 'Edit '.$pageLabel.' — Business Website')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-globe"></i> {{ $pageLabel }} page</h1>
    <p>Content shown on your public site.</p>
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
    <form method="POST" action="{{ route('business-website.update-page', $page) }}">
      @csrf
      @method('PUT')

      @if($page === 'home')
        <div class="form-group"><label class="font-weight-bold">Hero title</label>
          <input type="text" name="hero_title" class="form-control" value="{{ old('hero_title', $content['hero_title'] ?? '') }}" required></div>
        <div class="form-group"><label class="font-weight-bold">Hero subtitle</label>
          <textarea name="hero_subtitle" class="form-control" rows="2">{{ old('hero_subtitle', $content['hero_subtitle'] ?? '') }}</textarea></div>
        <div class="row">
          <div class="col-md-6 form-group"><label class="font-weight-bold">Button label</label>
            <input type="text" name="hero_button_label" class="form-control" value="{{ old('hero_button_label', $content['hero_button_label'] ?? '') }}"></div>
          <div class="col-md-6 form-group"><label class="font-weight-bold">Button link</label>
            <input type="text" name="hero_button_url" class="form-control" value="{{ old('hero_button_url', $content['hero_button_url'] ?? 'order') }}" placeholder="order or full URL">
            <small class="text-muted">Use <code>order</code> for your Order page.</small></div>
        </div>
        <div class="form-group"><label class="font-weight-bold">Section title</label>
          <input type="text" name="intro_title" class="form-control" value="{{ old('intro_title', $content['intro_title'] ?? '') }}"></div>
        <div class="form-group"><label class="font-weight-bold">Section body</label>
          <textarea name="intro_body" class="form-control" rows="4">{{ old('intro_body', $content['intro_body'] ?? '') }}</textarea></div>
      @endif

      @if($page === 'products')
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
        <p class="text-muted small">Products are loaded automatically from Items in Mauzo Link (with prices on sale units).</p>
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

      @if($page === 'contact')
        <div class="form-group"><label class="font-weight-bold">Page title</label>
          <input type="text" name="title" class="form-control" value="{{ old('title', $content['title'] ?? '') }}" required></div>
        <div class="form-group"><label class="font-weight-bold">Intro text</label>
          <textarea name="body" class="form-control" rows="3">{{ old('body', $content['body'] ?? '') }}</textarea></div>
        <div class="form-group"><label class="font-weight-bold">Opening hours</label>
          <input type="text" name="hours" class="form-control" value="{{ old('hours', $content['hours'] ?? '') }}"></div>
        <div class="custom-control custom-checkbox mb-3">
          <input type="checkbox" class="custom-control-input" id="show_contact_form" name="show_contact_form" value="1" {{ old('show_contact_form', $content['show_contact_form'] ?? true) ? 'checked' : '' }}>
          <label class="custom-control-label" for="show_contact_form">Show contact form</label>
        </div>
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
      @endif

      <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save page</button>
      <a href="{{ route('business-website.index') }}" class="btn btn-secondary">Back</a>
    </form>
  </div>
</div>
@endsection

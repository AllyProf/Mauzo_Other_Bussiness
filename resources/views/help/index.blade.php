@extends('layouts.app')

@section('title', 'Help Center - Mauzo Link')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-question-circle-o"></i> Help Center</h1>
    <p>Knowledge base, tutorials, and system answers at your fingertips.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item active"><a href="#">Help Center</a></li>
  </ul>
</div>

<div class="row">
  <div class="col-md-12">
    {{-- Search/Jumbotron Header --}}
    <div class="tile text-white p-5 text-center mb-4" style="background: linear-gradient(135deg, #940000 0%, #d32f2f 100%); border-radius: 8px; box-shadow: 0 4px 15px rgba(148,0,0,0.2);">
      <h2 class="mb-2 font-weight-bold text-white"><i class="fa fa-graduation-cap mr-2"></i> How can we help you today?</h2>
      <p class="lead mb-4 text-white-50">Browse our comprehensive list of guides, tutorials, and operational FAQs.</p>
    </div>
  </div>
</div>

<div class="row">
  @forelse($grouped as $category => $articles)
    <div class="col-md-6 mb-4">
      <div class="tile h-100 p-4 border-0 shadow-sm" style="border-radius:8px;">
        <h4 class="tile-title text-danger mb-3" style="border-bottom: 2px solid #f5f5f5; padding-bottom: 10px;">
          <i class="fa fa-folder-open-o mr-2"></i> {{ $category }}
        </h4>
        <div class="tile-body">
          <ul class="list-unstyled mb-0" style="font-size: 1.02rem; line-height: 2;">
            @foreach($articles as $article)
              <li class="py-2 border-bottom-light">
                <a href="{{ route('help.show', $article->slug) }}" class="text-dark d-flex align-items-center justify-content-between" style="text-decoration:none; transition: color 0.15s ease-in-out;">
                  <span>
                    <i class="fa fa-file-text-o text-muted mr-2"></i>
                    <strong>{{ $article->title }}</strong>
                  </span>
                  <i class="fa fa-chevron-right text-muted small"></i>
                </a>
              </li>
            @endforeach
          </ul>
        </div>
      </div>
    </div>
  @empty
    <div class="col-md-12">
      <div class="tile text-center p-5">
        <i class="fa fa-book fa-3x text-muted mb-3"></i>
        <h4>No resources found</h4>
        <p class="text-muted">We are currently updating our documentation database. Please check back later or submit a support ticket.</p>
        <a href="{{ route('tickets.create') }}" class="btn btn-primary mt-2">
          <i class="fa fa-ticket"></i> Open Support Ticket
        </a>
      </div>
    </div>
  @endforelse
</div>
@endsection

@push('styles')
<style>
  .border-bottom-light {
    border-bottom: 1px solid #f9f9f9;
  }
  .border-bottom-light:last-child {
    border-bottom: none;
  }
  .list-unstyled a:hover {
    color: #940000 !important;
  }
</style>
@endpush

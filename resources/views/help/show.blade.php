@extends('layouts.app')

@section('title', $article->title . ' - Help Center')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-question-circle-o"></i> Help Article</h1>
    <p>{{ $article->category }} Guide</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('help.index') }}">Help Center</a></li>
    <li class="breadcrumb-item active">Article Details</li>
  </ul>
</div>

<div class="row">
  <div class="col-md-8">
    <div class="tile p-4 shadow-sm" style="border-radius: 8px;">
      <div class="d-flex justify-content-between align-items-center mb-3 pb-3" style="border-bottom: 1px solid #eeeeee;">
        <div>
          <span class="badge badge-danger mb-2" style="font-size:0.85rem;">{{ $article->category }}</span>
          <h2 class="font-weight-bold text-dark mb-0">{{ $article->title }}</h2>
        </div>
      </div>
      <div class="tile-body article-content" style="font-size: 1.05rem; line-height: 1.7; color: #333333;">
        {!! nl2br(e($article->content)) !!}
      </div>
      <div class="tile-footer border-top-light mt-4 pt-3 text-muted" style="font-size:0.85rem;">
        <i class="fa fa-clock-o mr-1"></i> Last updated {{ $article->updated_at->diffForHumans() }}
      </div>
    </div>
  </div>

  <div class="col-md-4">
    {{-- Related articles --}}
    <div class="tile p-4 border-0 shadow-sm" style="border-radius: 8px;">
      <h4 class="tile-title text-danger mb-3" style="border-bottom: 2px solid #f5f5f5; padding-bottom: 10px; font-size:1.15rem;">
        <i class="fa fa-folder-open-o mr-2"></i> Related in {{ $article->category }}
      </h4>
      <div class="tile-body">
        @if($related->isEmpty())
          <p class="text-muted small">No other articles in this category.</p>
        @else
          <ul class="list-unstyled mb-0" style="font-size: 0.95rem; line-height: 1.8;">
            @foreach($related as $rel)
              <li class="py-2 border-bottom-light">
                <a href="{{ route('help.show', $rel->slug) }}" class="text-dark d-flex align-items-center justify-content-between" style="text-decoration:none; transition: color 0.15s ease-in-out;">
                  <span>
                    <i class="fa fa-file-text-o text-muted mr-2"></i>
                    <strong>{{ $rel->title }}</strong>
                  </span>
                  <i class="fa fa-chevron-right text-muted small"></i>
                </a>
              </li>
            @endforeach
          </ul>
        @endif
      </div>
    </div>

    {{-- Need more help card --}}
    <div class="tile p-4 text-center text-white border-0 shadow-sm mt-3" style="background: linear-gradient(135deg, #343a40 0%, #212529 100%); border-radius: 8px;">
      <h5 class="font-weight-bold text-white mb-2"><i class="fa fa-life-ring mr-2 text-warning"></i> Still Need Help?</h5>
      <p class="small text-white-50 mb-3">If you couldn't find the answer you were looking for, please open a support ticket and our team will get back to you shortly.</p>
      <a href="{{ route('tickets.create') }}" class="btn btn-warning btn-sm btn-block">
        <i class="fa fa-ticket"></i> Open Support Ticket
      </a>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
  .border-top-light {
    border-top: 1px solid #f5f5f5;
  }
  .border-bottom-light {
    border-bottom: 1px solid #f9f9f9;
  }
  .border-bottom-light:last-child {
    border-bottom: none;
  }
  .list-unstyled a:hover {
    color: #940000 !important;
  }
  .article-content p {
    margin-bottom: 1rem;
  }
</style>
@endpush

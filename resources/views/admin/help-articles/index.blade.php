@extends('layouts.app')

@section('title', 'Help Articles - Software Owner')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-question-circle"></i> Help Desk & FAQ Articles</h1>
    <p>Manage tutorials, guides, and frequently asked questions for tenants.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Help Articles</li>
  </ul>
</div>

@if(session('success'))
<div class="row">
  <div class="col-md-12">
    <div class="alert alert-success alert-dismissible fade show">
      <i class="fa fa-check-circle"></i> {{ session('success') }}
      <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
  </div>
</div>
@endif

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-title-w-btn">
        <h3 class="title"><i class="fa fa-book"></i> Help Articles List</h3>
        <p><a class="btn btn-primary icon-btn" href="{{ route('admin.help-articles.create') }}"><i class="fa fa-plus"></i>Create Article</a></p>
      </div>
      <div class="tile-body">
        @if($articles->isEmpty())
          <div class="text-center py-5 text-muted">
            <i class="fa fa-question-circle-o fa-3x mb-3 d-block"></i>
            <p class="mb-3">No help articles created yet.</p>
            <a href="{{ route('admin.help-articles.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Create First Article</a>
          </div>
        @else
          @foreach($articles->groupBy('category') as $category => $groupedArticles)
            <div class="card mb-4 border-light shadow-sm">
              <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2" style="background-color:#343a40 !important;">
                <h5 class="mb-0 text-white" style="font-size: 1rem;"><i class="fa fa-folder-open mr-2"></i>{{ $category }}</h5>
                <span class="badge badge-light">{{ $groupedArticles->count() }} Article(s)</span>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-hover table-striped mb-0">
                    <thead>
                      <tr>
                        <th>Title</th>
                        <th>Slug</th>
                        <th style="width: 150px; text-align: center;">Status</th>
                        <th style="width: 250px; text-align: center;">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($groupedArticles as $article)
                        <tr>
                          <td><strong>{{ $article->title }}</strong></td>
                          <td><code>{{ $article->slug }}</code></td>
                          <td class="text-center">
                            @if($article->is_published)
                              <span class="badge badge-success"><i class="fa fa-check-circle mr-1"></i> Published</span>
                            @else
                              <span class="badge badge-warning"><i class="fa fa-pencil-square-o mr-1"></i> Draft</span>
                            @endif
                          </td>
                          <td class="text-center">
                            <div class="d-flex justify-content-center" style="gap: 6px;">
                              <form action="{{ route('admin.help-articles.toggle-publish', $article->id) }}" method="POST" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $article->is_published ? 'btn-outline-warning' : 'btn-outline-success' }}" style="padding: 2px 8px; font-size: 0.8rem;">
                                  <i class="fa {{ $article->is_published ? 'fa-eye-slash' : 'fa-eye' }} mr-1"></i> {{ $article->is_published ? 'Unpublish' : 'Publish' }}
                                </button>
                              </form>

                              <a href="{{ route('admin.help-articles.edit', $article->id) }}" class="btn btn-sm btn-info" style="padding: 2px 8px; font-size: 0.8rem;">
                                <i class="fa fa-edit mr-1"></i> Edit
                              </a>

                              <form action="{{ route('admin.help-articles.destroy', $article->id) }}" method="POST" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" style="padding: 2px 8px; font-size: 0.8rem;" onclick="confirmAction(event, 'Delete Help Article?', 'This will permanently delete this article.')">
                                  <i class="fa fa-trash mr-1"></i> Delete
                                </button>
                              </form>
                            </div>
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          @endforeach
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

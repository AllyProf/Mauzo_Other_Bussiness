@extends('layouts.app')

@section('title', 'Edit Help Article - Software Owner')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-edit"></i> Edit Help Article</h1>
    <p>Modify guide or tutorial details.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.help-articles.index') }}">Help Articles</a></li>
    <li class="breadcrumb-item active">Edit</li>
  </ul>
</div>

<div class="row justify-content-center">
  <div class="col-md-9">
    <div class="tile">
      <h3 class="tile-title">Edit Article Details</h3>
      <div class="tile-body">
        <form action="{{ route('admin.help-articles.update', $article->id) }}" method="POST">
          @csrf
          @method('PUT')
          
          <div class="form-group">
            <label class="control-label font-weight-bold">Article Title</label>
            <input class="form-control @error('title') is-invalid @enderror" type="text" name="title" value="{{ old('title', $article->title) }}" placeholder="e.g. How to manage multiple branches" required>
            @error('title')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="form-group">
            <label class="control-label font-weight-bold">Category</label>
            <input class="form-control @error('category') is-invalid @enderror" type="text" name="category" id="categoryInput" value="{{ old('category', $article->category) }}" placeholder="Select or type a category" required list="category-options">
            <datalist id="category-options">
              @foreach($existingCategories as $cat)
                <option value="{{ $cat }}"></option>
              @endforeach
            </datalist>
            <small class="text-muted">Choose an existing category or type a new one to group this article.</small>
            @error('category')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="form-group">
            <label class="control-label font-weight-bold">Content (Markdown / HTML Supported)</label>
            <textarea class="form-control @error('content') is-invalid @enderror" name="content" rows="12" placeholder="Write full article description here..." required>{{ old('content', $article->content) }}</textarea>
            @error('content')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="form-group">
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="is_published" name="is_published" value="1" {{ old('is_published', $article->is_published) ? 'checked' : '' }}>
              <label class="custom-control-label font-weight-bold" for="is_published">Published</label>
              <br><small class="text-muted">Unchecked articles will remain as Drafts and won't be visible to tenants.</small>
            </div>
          </div>

          <div class="tile-footer text-right">
            <button class="btn btn-primary" type="submit"><i class="fa fa-check-circle"></i> Save Changes</button>
            <a class="btn btn-secondary" href="{{ route('admin.help-articles.index') }}">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

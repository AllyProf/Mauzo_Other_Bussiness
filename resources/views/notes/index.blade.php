@extends('layouts.app')

@section('title', 'Notes & Reminders')

@section('styles')
<style>
  .note-modal-dialog {
    max-width: 640px;
  }
  .note-modal-header {
    background: #940000;
    color: #fff;
    align-items: center;
    padding: 1rem 1.25rem;
  }
  .note-modal-header .close {
    opacity: 1;
    text-shadow: none;
  }
  .note-preview {
    white-space: pre-wrap;
    word-break: break-word;
  }
  .note-stat-box {
    border-left: 4px solid #940000;
  }
</style>
@endsection

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-sticky-note"></i> Notes & Reminders</h1>
    <p>Write notes and set when you want to be reminded</p>
  </div>
  <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#noteModal">
    <i class="fa fa-plus"></i> New Note
  </button>
</div>

<div class="row mb-3">
  <div class="col-md-4">
    <div class="widget-small primary coloured-icon note-stat-box">
      <i class="icon fa fa-sticky-note fa-3x"></i>
      <div class="info">
        <h4>Active Notes</h4>
        <p><b>{{ $stats['active'] }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small danger coloured-icon">
      <i class="icon fa fa-bell fa-3x"></i>
      <div class="info">
        <h4>Due Now</h4>
        <p><b>{{ $stats['due'] }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small info coloured-icon">
      <i class="icon fa fa-clock-o fa-3x"></i>
      <div class="info">
        <h4>Upcoming</h4>
        <p><b>{{ $stats['upcoming'] }}</b></p>
      </div>
    </div>
  </div>
</div>

<div class="tile">
  <div class="tile-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
      <div class="btn-group btn-group-sm" role="group">
        <a href="{{ route('notes.index', ['filter' => 'active']) }}" class="btn btn-{{ $filter === 'active' ? 'primary' : 'outline-secondary' }}">Active</a>
        <a href="{{ route('notes.index', ['filter' => 'completed']) }}" class="btn btn-{{ $filter === 'completed' ? 'primary' : 'outline-secondary' }}">Completed</a>
        <a href="{{ route('notes.index', ['filter' => 'all']) }}" class="btn btn-{{ $filter === 'all' ? 'primary' : 'outline-secondary' }}">All</a>
      </div>
    </div>

    @if($notes->isEmpty())
      <div class="text-center text-muted py-5">
        <i class="fa fa-sticky-note-o fa-3x mb-3"></i>
        <p class="mb-2">No notes yet.</p>
        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#noteModal">
          <i class="fa fa-plus"></i> Create your first note
        </button>
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover table-bordered mb-0">
          <thead>
            <tr>
              <th>{{ __('tables.columns.title_note') }}</th>
              <th style="width: 180px;">Remind At</th>
              <th style="width: 120px;">Status</th>
              <th style="width: 160px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($notes as $note)
              <tr class="{{ $note->isDue() ? 'table-warning' : '' }}">
                <td>
                  <strong>{{ $note->displayTitle() }}</strong>
                  @if($note->title)
                    <div class="text-muted small note-preview">{{ \Illuminate\Support\Str::limit($note->body, 120) }}</div>
                  @endif
                </td>
                <td>
                  @if($note->remind_at)
                    {{ $note->remind_at->format('M j, Y g:i A') }}
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>
                  <span class="badge badge-{{ $note->statusBadgeClass() }}">{{ $note->statusLabel() }}</span>
                </td>
                <td>
                  <div class="btn-group btn-group-sm">
                    @if(! $note->isCompleted())
                      <a href="{{ route('notes.index', ['filter' => $filter, 'edit' => $note->id]) }}" class="btn btn-outline-primary" title="{{ __('tables.actions.edit') }}">
                        <i class="fa fa-pencil"></i>
                      </a>
                      <form action="{{ route('notes.complete', $note) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-success" title="Mark done">
                          <i class="fa fa-check"></i>
                        </button>
                      </form>
                    @endif
                    <form action="{{ route('notes.destroy', $note) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this note?');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-outline-danger" title="{{ __('tables.actions.delete') }}">
                        <i class="fa fa-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="mt-3">
        {{ $notes->links() }}
      </div>
    @endif
  </div>
</div>

@include('notes.partials.note-modal', ['editNote' => $editNote ?? null])
@endsection

@section('scripts')
@if($editNote || request('new'))
<script>
  $(function () {
    $('#noteModal').modal('show');
  });
</script>
@endif
@endsection

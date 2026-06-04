<div class="modal fade" id="noteModal" tabindex="-1" role="dialog" aria-labelledby="noteModalLabel" aria-hidden="true">
  <div class="modal-dialog note-modal-dialog" role="document">
    <div class="modal-content">
      <form action="{{ $editNote ? route('notes.update', $editNote) : route('notes.store') }}" method="POST">
        @csrf
        @if($editNote)
          @method('PUT')
        @endif
        <div class="modal-header note-modal-header">
          <h5 class="modal-title" id="noteModalLabel">
            <i class="fa fa-sticky-note mr-1"></i>
            {{ $editNote ? 'Edit Note' : 'New Note' }}
          </h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="control-label" for="note-title">Title</label>
            <input type="text" class="form-control" id="note-title" name="title" maxlength="255"
              value="{{ old('title', $editNote->title ?? '') }}" placeholder="Optional short title">
          </div>
          <div class="form-group">
            <label class="control-label" for="note-body">Note</label>
            <textarea class="form-control" id="note-body" name="body" rows="6" required maxlength="5000"
              placeholder="Write your note here...">{{ old('body', $editNote->body ?? '') }}</textarea>
          </div>
          <div class="form-group mb-0">
            <label class="control-label" for="note-remind-at">Remind me on</label>
            <input type="datetime-local" class="form-control" id="note-remind-at" name="remind_at"
              value="{{ old('remind_at', isset($editNote) && $editNote->remind_at ? $editNote->remind_at->format('Y-m-d\TH:i') : '') }}">
            <small class="form-text text-muted">When this time is reached, you get an in-app alert and an SMS (if enabled in Settings and your profile has a phone number).</small>
          </div>
        </div>
        <div class="modal-footer">
          @if($editNote)
            <a href="{{ route('notes.index', ['filter' => request('filter', 'active')]) }}" class="btn btn-secondary">Cancel</a>
          @else
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          @endif
          <button type="submit" class="btn btn-primary">
            <i class="fa fa-save"></i> Save Note
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

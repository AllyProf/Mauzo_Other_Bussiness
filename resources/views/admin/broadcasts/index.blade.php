@extends('layouts.app')

@section('title', 'System Broadcasts - Software Owner')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-bullhorn"></i> System Broadcasts</h1>
    <p>Send a global announcement to all business owners</p>
  </div>
</div>

<div class="row">
  <div class="col-md-6">
    <div class="tile">
      <h3 class="tile-title">Send New Announcement</h3>
      <div class="tile-body">
        <form action="{{ route('admin.broadcasts.store') }}" method="POST">
          @csrf
          <div class="form-group">
            <label class="control-label">Your Message</label>
            <textarea class="form-control" name="message" rows="4" placeholder="e.g. Scheduled maintenance at 11:00 PM tonight..." required></textarea>
            <small class="text-muted">This message will appear at the top of every user's dashboard.</small>
          </div>
          <div class="tile-footer">
            <button class="btn btn-primary" type="submit"><i class="fa fa-paper-plane"></i> Broadcast Message</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="tile">
      <h3 class="tile-title">Recent Announcements</h3>
      <div class="tile-body">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>{{ __('tables.columns.message') }}</th>
              <th>{{ __('tables.columns.status') }}</th>
              <th>{{ __('tables.columns.action') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($broadcasts as $broadcast)
                <tr>
                    <td>{{ Str::limit($broadcast->message, 50) }}</td>
                    <td>
                        @if($broadcast->is_active)
                            <span class="badge badge-success">{{ __('tables.status.active') }}</span>
                        @else
                            <span class="badge badge-secondary">Past</span>
                        @endif
                    </td>
                    <td>
                        <form action="{{ route('admin.broadcasts.destroy', $broadcast->id) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" onclick="confirmAction(event, 'Delete Broadcast?', 'This announcement will be removed forever.')">
                                <i class="fa fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

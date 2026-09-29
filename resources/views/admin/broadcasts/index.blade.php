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
  <div class="col-lg-7">
    <div class="tile">
      @php
        $form = [
          'message' => old('message', $editing->message ?? ''),
          'scroll_speed' => old('scroll_speed', $editing->scroll_speed ?? 'slow'),
          'text_color' => old('text_color', $editing?->textColor() ?? '#ffffff'),
          'font_family' => old('font_family', $editing->font_family ?? 'century_gothic'),
          'font_size' => (int) old('font_size', $editing?->fontSizePx() ?? 14),
        ];
      @endphp
      <h3 class="tile-title">
        @if($editing)
          <i class="fa fa-pencil"></i> Edit Announcement
        @else
          Send New Announcement
        @endif
      </h3>
      <div class="tile-body">
        <form action="{{ $editing ? route('admin.broadcasts.update', $editing) : route('admin.broadcasts.store') }}" method="POST" id="broadcastForm">
          @csrf
          @if($editing)
            @method('PUT')
          @endif
          <div class="form-group">
            <label class="control-label" for="broadcastMessage">Your Message</label>
            <textarea class="form-control @error('message') is-invalid @enderror" id="broadcastMessage" name="message" rows="3" maxlength="1000" placeholder="e.g. Scheduled maintenance at 11:00 PM tonight..." required>{{ $form['message'] }}</textarea>
            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <small class="text-muted">This message scrolls across the top of every page.</small>
          </div>

          <div class="form-row">
            <div class="form-group col-sm-6">
              <label class="control-label" for="broadcastSpeed">Scroll speed</label>
              <select class="form-control" id="broadcastSpeed" name="scroll_speed">
                @foreach(\App\Models\Broadcast::SPEEDS as $key => $speed)
                  <option value="{{ $key }}" data-speed="{{ $speed['px_per_second'] }}" {{ $form['scroll_speed'] === $key ? 'selected' : '' }}>{{ $speed['label'] }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-sm-6">
              <label class="control-label" for="broadcastColor">Text colour</label>
              <div class="d-flex align-items-center">
                <input type="color" class="form-control p-1 mr-2" id="broadcastColor" name="text_color" value="{{ $form['text_color'] }}" style="width: 56px; height: 38px;">
                <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Quick colours">
                  @foreach(['#ffffff' => 'White', '#ffd54f' => 'Yellow', '#000000' => 'Black', '#b9f6ca' => 'Mint'] as $hex => $name)
                  <button type="button" class="btn btn-light border js-broadcast-color" data-color="{{ $hex }}" title="{{ $name }}" style="width: 28px; background: {{ $hex }};">&nbsp;</button>
                  @endforeach
                </div>
              </div>
            </div>
            <div class="form-group col-sm-6">
              <label class="control-label" for="broadcastFont">Font family</label>
              <select class="form-control" id="broadcastFont" name="font_family">
                @foreach(\App\Models\Broadcast::FONTS as $key => $font)
                  <option value="{{ $key }}" data-css="{{ $font['css'] }}" style="font-family: {{ $font['css'] }};" {{ $form['font_family'] === $key ? 'selected' : '' }}>{{ $font['label'] }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group col-sm-6">
              <label class="control-label" for="broadcastSize">Font size</label>
              <select class="form-control" id="broadcastSize" name="font_size">
                @foreach(\App\Models\Broadcast::FONT_SIZES as $size)
                  <option value="{{ $size }}" {{ $form['font_size'] === $size ? 'selected' : '' }}>{{ $size }} px</option>
                @endforeach
              </select>
            </div>
          </div>

          <label class="control-label d-block">Preview</label>
          <div class="app-broadcast mb-3" id="broadcastPreview" style="margin: 0; height: 40px;">
            <div class="app-broadcast__label"><i class="fa fa-bullhorn"></i> <span>Announcement</span></div>
            <div class="app-broadcast__track">
              <div class="app-broadcast__text js-broadcast-text" id="broadcastPreviewText" data-speed="50">Type your message to see the preview…</div>
            </div>
          </div>

          <div class="tile-footer">
            @if($editing)
              <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Save Changes</button>
              <a href="{{ route('admin.broadcasts.index') }}" class="btn btn-secondary ml-1">Cancel</a>
            @else
              <button class="btn btn-primary" type="submit"><i class="fa fa-paper-plane"></i> Broadcast Message</button>
            @endif
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="tile">
      <h3 class="tile-title">Recent Announcements</h3>
      <div class="tile-body">
        <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>{{ __('tables.columns.message') }}</th>
              <th>{{ __('tables.columns.status') }}</th>
              <th>{{ __('tables.columns.action') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse($broadcasts as $broadcast)
                <tr class="{{ $editing && $editing->id === $broadcast->id ? 'table-warning' : '' }}">
                    <td>
                      {{ Str::limit($broadcast->message, 50) }}
                      <br><small class="text-muted">
                        {{ $broadcast->speedLabel() }} · {{ $broadcast->fontLabel() }} {{ $broadcast->fontSizePx() }}px ·
                        <span class="d-inline-block align-middle border" style="width: 10px; height: 10px; background: {{ $broadcast->textColor() }};"></span>
                      </small>
                    </td>
                    <td>
                        @if($broadcast->is_active)
                            <span class="badge badge-success">{{ __('tables.status.active') }}</span>
                        @else
                            <span class="badge badge-secondary">Past</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        <a href="{{ route('admin.broadcasts.index', ['edit' => $broadcast->id]) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                            <i class="fa fa-pencil"></i>
                        </a>
                        <form action="{{ route('admin.broadcasts.activate', $broadcast->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm {{ $broadcast->is_active ? 'btn-outline-secondary' : 'btn-outline-success' }}" title="{{ $broadcast->is_active ? 'Hide' : 'Show again' }}">
                                <i class="fa {{ $broadcast->is_active ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                            </button>
                        </form>
                        <form action="{{ route('admin.broadcasts.destroy', $broadcast->id) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" onclick="confirmAction(event, 'Delete Broadcast?', 'This announcement will be removed forever.')">
                                <i class="fa fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted">No announcements yet.</td></tr>
            @endforelse
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  (function () {
    var message = document.getElementById('broadcastMessage');
    var speed = document.getElementById('broadcastSpeed');
    var color = document.getElementById('broadcastColor');
    var font = document.getElementById('broadcastFont');
    var size = document.getElementById('broadcastSize');
    var preview = document.getElementById('broadcastPreview');
    var text = document.getElementById('broadcastPreviewText');

    function refresh() {
      var fontSize = parseInt(size.value, 10) || 14;
      text.textContent = message.value.trim() || 'Type your message to see the preview…';
      text.style.color = color.value;
      text.style.fontFamily = font.options[font.selectedIndex].getAttribute('data-css');
      text.style.fontSize = fontSize + 'px';
      text.setAttribute('data-speed', speed.options[speed.selectedIndex].getAttribute('data-speed'));
      preview.style.height = Math.max(40, fontSize + 20) + 'px';
      window.appBroadcastSpeed(text);
    }

    [message, speed, color, font, size].forEach(function (el) {
      el.addEventListener('input', refresh);
      el.addEventListener('change', refresh);
    });

    document.querySelectorAll('.js-broadcast-color').forEach(function (btn) {
      btn.addEventListener('click', function () {
        color.value = btn.getAttribute('data-color');
        refresh();
      });
    });

    refresh();
  })();
</script>
@endpush

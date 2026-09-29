@php
  $appearanceChoice = old('background', $appearance['background']);
  $appearanceStrength = old('strength', $appearance['strength']);
  $customBgUrl = $appearance['custom_path'] !== '' ? asset('storage/'.$appearance['custom_path']) : null;
@endphp
<form method="POST" action="{{ $action }}" class="settings-form appearance-picker" enctype="multipart/form-data">
  @csrf @method('PUT')

  <div class="row">
    <div class="col-6 col-md-4 mb-3">
      <label class="bg-choice {{ $appearanceChoice === 'none' ? 'is-selected' : '' }}">
        <input type="radio" name="background" value="none" {{ $appearanceChoice === 'none' ? 'checked' : '' }}>
        <span class="bg-choice__thumb bg-choice__thumb--none"><i class="fa fa-ban"></i></span>
        <span class="bg-choice__label">No wallpaper</span>
      </label>
    </div>
    @foreach(\App\Models\Business::BACKGROUND_PATTERNS as $key => $pattern)
    <div class="col-6 col-md-4 mb-3">
      <label class="bg-choice {{ $appearanceChoice === $key ? 'is-selected' : '' }}">
        <input type="radio" name="background" value="{{ $key }}" {{ $appearanceChoice === $key ? 'checked' : '' }}>
        <span class="bg-choice__thumb" style="background-image:url('{{ asset($pattern['path']) }}');"></span>
        <span class="bg-choice__label">{{ $pattern['label'] }}</span>
      </label>
    </div>
    @endforeach
    <div class="col-6 col-md-4 mb-3">
      <label class="bg-choice {{ $appearanceChoice === 'custom' ? 'is-selected' : '' }}">
        <input type="radio" name="background" value="custom" {{ $appearanceChoice === 'custom' ? 'checked' : '' }}>
        <span class="bg-choice__thumb js-custom-bg-thumb" @if($customBgUrl) style="background-image:url('{{ $customBgUrl }}');" @endif>
          @unless($customBgUrl)<i class="fa fa-upload"></i>@endunless
        </span>
        <span class="bg-choice__label">My own image</span>
      </label>
    </div>
  </div>

  <div class="form-group">
    <label class="control-label font-weight-bold">Upload wallpaper</label>
    <input type="file" name="background_file" class="form-control-file js-bg-file" accept="image/jpeg,image/png,image/webp">
    <small class="text-muted">JPG, PNG or WEBP, up to 3 MB. Light patterns work best. Selecting a file switches to "My own image".</small>
    @error('background_file')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
  </div>

  <div class="form-group">
    <label class="control-label font-weight-bold d-block">Wallpaper strength</label>
    <div class="btn-group btn-group-toggle" data-toggle="buttons">
      @foreach(['light' => 'Soft', 'medium' => 'Medium', 'strong' => 'Strong'] as $value => $label)
      <label class="btn btn-outline-secondary btn-sm {{ $appearanceStrength === $value ? 'active' : '' }}">
        <input type="radio" name="strength" value="{{ $value }}" {{ $appearanceStrength === $value ? 'checked' : '' }}> {{ $label }}
      </label>
      @endforeach
    </div>
  </div>

  <button type="submit" class="btn btn-primary settings-save-btn" style="background-color:#940000;border-color:#940000;">
    <i class="fa fa-save"></i> Save Appearance
  </button>
</form>

@once
<style>
  .bg-choice { display: block; cursor: pointer; margin: 0; }
  .bg-choice input { position: absolute; opacity: 0; pointer-events: none; }
  .bg-choice__thumb {
    display: flex; align-items: center; justify-content: center; height: 110px; border-radius: 6px;
    border: 2px solid #dee2e6; background: #f8f9fa center / cover repeat; color: #adb5bd; font-size: 26px;
    transition: border-color .15s, box-shadow .15s;
  }
  .bg-choice__label { display: block; text-align: center; font-size: 13px; font-weight: 600; margin-top: 6px; }
  .bg-choice:hover .bg-choice__thumb { border-color: #c9a0a0; }
  .bg-choice.is-selected .bg-choice__thumb { border-color: #940000; box-shadow: 0 0 0 3px rgba(148,0,0,.15); }
  .bg-choice.is-selected .bg-choice__label { color: #940000; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.appearance-picker').forEach(function (form) {
    function select(input) {
      form.querySelectorAll('.bg-choice').forEach(function (el) { el.classList.remove('is-selected'); });
      input.closest('.bg-choice').classList.add('is-selected');
    }
    form.querySelectorAll('input[name="background"]').forEach(function (input) {
      input.addEventListener('change', function () { select(input); });
    });
    var file = form.querySelector('.js-bg-file');
    if (file) {
      file.addEventListener('change', function () {
        var f = file.files && file.files[0];
        if (!f) return;
        var reader = new FileReader();
        reader.onload = function (ev) {
          var thumb = form.querySelector('.js-custom-bg-thumb');
          thumb.style.backgroundImage = 'url(' + ev.target.result + ')';
          thumb.innerHTML = '';
        };
        reader.readAsDataURL(f);
        var custom = form.querySelector('input[name="background"][value="custom"]');
        custom.checked = true;
        select(custom);
      });
    }
  });
});
</script>
@endonce

@if(Auth::check() && Auth::user()->role !== 'super_admin' && Auth::user()->business_id)
<button type="button" class="support-fab" id="supportFabBtn" title="{{ __('common.contact_support') }}" aria-label="{{ __('common.contact_support') }}">
  <img src="{{ asset('gp-assets/img/support-fab.png') }}" alt="" class="support-fab-img" aria-hidden="true">
</button>

<div class="modal fade" id="supportQuickModal" tabindex="-1" role="dialog" aria-labelledby="supportQuickModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title" id="supportQuickModalLabel"><i class="fa fa-life-ring text-primary"></i> {{ __('common.contact_support') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form id="supportQuickForm">
        @csrf
        <div class="modal-body pb-2">
          <p class="text-muted small mb-2">Describe your issue or question. Our team will get back to you.</p>
          <div class="form-group mb-0">
            <label class="control-label sr-only" for="supportQuickMessage">Description</label>
            <textarea id="supportQuickMessage" name="message" class="form-control" rows="4" maxlength="2000" required placeholder="Write your message here..."></textarea>
            <small class="text-muted">Minimum 10 characters.</small>
          </div>
          <div id="supportQuickError" class="alert alert-danger small py-2 mt-2 mb-0 d-none"></div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm" id="supportQuickSubmit">
            <i class="fa fa-paper-plane"></i> Send
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
  .support-fab {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 1040;
    width: 56px;
    height: 56px;
    padding: 0;
    border: 2px solid #fff;
    border-radius: 50%;
    background: #940000;
    color: #fff;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.22);
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    outline: none;
    animation: supportFabPulse 2.4s ease-out infinite;
  }
  .support-fab-img {
    display: block;
    width: 100%;
    height: 100%;
    pointer-events: none;
    user-select: none;
    animation: supportFabRing 6s ease-in-out 1.5s infinite;
  }
  .support-fab:hover,
  .support-fab:focus {
    background: #7a0000;
    transform: translateY(-3px) scale(1.06);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.28);
    animation-play-state: paused;
  }
  .support-fab:hover .support-fab-img,
  .support-fab:focus .support-fab-img {
    animation-play-state: paused;
  }
  @keyframes supportFabPulse {
    0%   { box-shadow: 0 4px 14px rgba(0, 0, 0, 0.22), 0 0 0 0 rgba(148, 0, 0, 0.55); }
    70%  { box-shadow: 0 4px 14px rgba(0, 0, 0, 0.22), 0 0 0 16px rgba(148, 0, 0, 0); }
    100% { box-shadow: 0 4px 14px rgba(0, 0, 0, 0.22), 0 0 0 0 rgba(148, 0, 0, 0); }
  }
  @keyframes supportFabRing {
    0%, 82%, 100% { transform: rotate(0); }
    84% { transform: rotate(-14deg); }
    86% { transform: rotate(12deg); }
    88% { transform: rotate(-10deg); }
    90% { transform: rotate(8deg); }
    92% { transform: rotate(-4deg); }
    94% { transform: rotate(0); }
  }
  @media (prefers-reduced-motion: reduce) {
    .support-fab, .support-fab-img { animation: none; }
  }
  @media (max-width: 576px) {
    .support-fab {
      bottom: 18px;
      right: 16px;
      width: 50px;
      height: 50px;
    }
  }
  @media print {
    .support-fab, #supportQuickModal { display: none !important; }
  }
  #supportQuickModal .modal-title {
    font-size: 1rem;
  }
  #supportQuickModal .text-primary {
    color: #940000 !important;
  }
</style>

@push('scripts')
<script>
(function () {
  var fab = document.getElementById('supportFabBtn');
  var form = document.getElementById('supportQuickForm');
  if (! fab || ! form) return;

  fab.addEventListener('click', function () {
    jQuery('#supportQuickModal').modal('show');
    setTimeout(function () {
      document.getElementById('supportQuickMessage').focus();
    }, 300);
  });

  jQuery('#supportQuickModal').on('hidden.bs.modal', function () {
    form.reset();
    document.getElementById('supportQuickError').classList.add('d-none');
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    var submitBtn = document.getElementById('supportQuickSubmit');
    var errorBox = document.getElementById('supportQuickError');
    var message = document.getElementById('supportQuickMessage').value.trim();
    errorBox.classList.add('d-none');

    if (message.length < 10) {
      errorBox.textContent = 'Please write at least 10 characters.';
      errorBox.classList.remove('d-none');
      return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending...';

    jQuery.ajax({
      url: @json(route('tickets.quick-store')),
      method: 'POST',
      data: {
        _token: @json(csrf_token()),
        message: message
      },
      headers: { 'Accept': 'application/json' },
      success: function (response) {
        jQuery('#supportQuickModal').modal('hide');
        Toast.fire({
          icon: 'success',
          title: response.message || 'Support request sent successfully.'
        });
      },
      error: function (xhr) {
        var msg = 'Could not send your request. Please try again.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        } else if (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.message) {
          msg = xhr.responseJSON.errors.message[0];
        }
        errorBox.textContent = msg;
        errorBox.classList.remove('d-none');
      },
      complete: function () {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa fa-paper-plane"></i> Send';
      }
    });
  });
})();
</script>
@endpush
@endif

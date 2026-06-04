@if(Auth::check() && Auth::user()->role !== 'super_admin' && Auth::user()->business_id)
<button type="button" class="support-fab" id="supportFabBtn" title="Contact Support" aria-label="Contact Support">
  <i class="fa fa-life-ring"></i>
</button>

<div class="modal fade" id="supportQuickModal" tabindex="-1" role="dialog" aria-labelledby="supportQuickModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title" id="supportQuickModalLabel"><i class="fa fa-life-ring text-primary"></i> Contact Support</h5>
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
    border-radius: 50%;
    background-color: #940000;
    color: #fff;
    border: none;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
  }
  .support-fab:hover,
  .support-fab:focus {
    color: #fff;
    transform: scale(1.05);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.3);
    outline: none;
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

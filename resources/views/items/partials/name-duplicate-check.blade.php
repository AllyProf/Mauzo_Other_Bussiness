@php
  $excludeId = $excludeId ?? null;
@endphp
<div id="itemNameDuplicateFeedback" class="mt-1" style="display:none;"></div>

@push('scripts')
<script>
(function () {
  var input = document.querySelector('input[name="name"]');
  var feedback = document.getElementById('itemNameDuplicateFeedback');
  var form = input ? input.closest('form') : null;
  var submitBtn = form ? form.querySelector('button[type="submit"]') : null;
  var checkUrl = @json(route('items.check-name'));
  var excludeId = @json($excludeId);
  var timer = null;
  var requestId = 0;
  var exactDuplicate = false;
  var checking = false;

  if (!input || !feedback) return;

  function setSubmitEnabled(enabled) {
    if (!submitBtn) return;
    submitBtn.disabled = !enabled;
    if (enabled) {
      submitBtn.classList.remove('disabled');
    } else {
      submitBtn.classList.add('disabled');
    }
  }

  function showLoading() {
    checking = true;
    exactDuplicate = false;
    feedback.style.display = 'block';
    feedback.innerHTML =
      '<div class="text-muted small py-1">' +
        '<i class="fa fa-spinner fa-spin"></i> Checking if this item already exists…' +
      '</div>';
    input.classList.remove('is-invalid');
  }

  function clearFeedback() {
    checking = false;
    exactDuplicate = false;
    feedback.style.display = 'none';
    feedback.innerHTML = '';
    input.classList.remove('is-invalid');
    setSubmitEnabled(true);
  }

  function render(data) {
    checking = false;
    var matches = data.matches || [];
    exactDuplicate = !!data.exact;

    if (!matches.length) {
      feedback.style.display = 'block';
      feedback.innerHTML =
        '<div class="text-success small py-1">' +
          '<i class="fa fa-check-circle"></i> Name looks available.' +
        '</div>';
      input.classList.remove('is-invalid');
      setSubmitEnabled(true);
      return;
    }

    feedback.style.display = 'block';

    if (exactDuplicate) {
      input.classList.add('is-invalid');
      setSubmitEnabled(false);
      var exact = data.exact_item || matches.find(function (m) { return m.exact; }) || matches[0];
      feedback.innerHTML =
        '<div class="alert alert-danger py-2 px-3 mb-0">' +
          '<strong><i class="fa fa-exclamation-triangle"></i> Already registered:</strong> ' +
          '<a href="' + exact.url + '" target="_blank" rel="noopener">' + escapeHtml(exact.name) + '</a>' +
          '. Choose a different name.' +
        '</div>';
      return;
    }

    input.classList.remove('is-invalid');
    setSubmitEnabled(true);
    var list = matches.slice(0, 5).map(function (m) {
      var meta = [];
      if (m.brand) meta.push(escapeHtml(m.brand));
      if (m.category) meta.push(escapeHtml(m.category));
      if (m.sku) meta.push(escapeHtml(m.sku));
      return '<li class="mb-1">' +
        '<a href="' + m.url + '" target="_blank" rel="noopener">' + escapeHtml(m.name) + '</a>' +
        (meta.length ? ' <small class="text-muted">(' + meta.join(' · ') + ')</small>' : '') +
      '</li>';
    }).join('');

    feedback.innerHTML =
      '<div class="alert alert-warning py-2 px-3 mb-0">' +
        '<strong><i class="fa fa-info-circle"></i> Similar items found:</strong>' +
        '<ul class="mb-0 mt-1 pl-3">' + list + '</ul>' +
      '</div>';
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function checkName() {
    var name = (input.value || '').trim();
    if (name.length < 2) {
      clearFeedback();
      return;
    }

    showLoading();
    var currentRequest = ++requestId;
    var params = new URLSearchParams({ name: name });
    if (excludeId) params.set('exclude_id', excludeId);

    fetch(checkUrl + '?' + params.toString(), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (res) { return res.ok ? res.json() : Promise.reject(); })
      .then(function (data) {
        if (currentRequest !== requestId) return;
        render(data);
      })
      .catch(function () {
        if (currentRequest !== requestId) return;
        checking = false;
        feedback.style.display = 'block';
        feedback.innerHTML =
          '<div class="text-warning small py-1">' +
            '<i class="fa fa-exclamation-circle"></i> Could not check name. Try again.' +
          '</div>';
        setSubmitEnabled(true);
      });
  }

  input.addEventListener('input', function () {
    clearTimeout(timer);
    var name = (input.value || '').trim();

    if (name.length < 2) {
      clearFeedback();
      return;
    }

    // Show loading immediately while typing (before debounce fires)
    showLoading();
    timer = setTimeout(checkName, 350);
  });

  input.addEventListener('blur', function () {
    clearTimeout(timer);
    checkName();
  });

  if (form) {
    form.addEventListener('submit', function (e) {
      if (checking || exactDuplicate) {
        e.preventDefault();
        e.stopPropagation();
        if (checking) {
          showLoading();
        }
        input.focus();
      }
    });
  }

  if ((input.value || '').trim().length >= 2) {
    checkName();
  }
})();
</script>
@endpush

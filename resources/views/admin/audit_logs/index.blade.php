@extends('layouts.app')

@section('title', 'Activity Logs - Software Owner')

@section('styles')
<style>
  @keyframes auditLivePulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.35; }
  }
  .audit-live-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #28a745;
    margin-right: 6px;
    animation: auditLivePulse 1.4s ease-in-out infinite;
  }
  tr.audit-log-new {
    animation: auditRowFlash 2s ease-out;
  }
  @keyframes auditRowFlash {
    from { background-color: rgba(40, 167, 69, 0.25); }
    to { background-color: transparent; }
  }
  #auditLogDetailModal .detail-label {
    font-weight: 600;
    color: #6c757d;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
  }
  #auditLogDetailModal .detail-value {
    margin-bottom: 12px;
  }
</style>
@endsection

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-history"></i> Business Activity Logs</h1>
    <p>Live feed — updates automatically every few seconds using your current filters.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('admin.audit-logs.export', request()->query()) }}"><i class="fa fa-download"></i> Export CSV</a></li>
  </ul>
</div>

<div class="tile mb-3">
  <h3 class="tile-title">Filters <small class="text-muted font-weight-normal">(apply instantly)</small></h3>
  <div class="tile-body">
    <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="row" id="auditLogFilterForm">
      <div class="col-md-3 form-group">
        <label class="control-label">Business</label>
        <select name="business_id" class="form-control audit-log-filter">
          <option value="">All businesses</option>
          @foreach($businesses as $business)
          <option value="{{ $business->id }}" {{ ($filters['business_id'] ?? '') == $business->id ? 'selected' : '' }}>{{ $business->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2 form-group">
        <label class="control-label">User</label>
        <select name="user_id" class="form-control audit-log-filter">
          <option value="">All users</option>
          @foreach($users as $staff)
          <option value="{{ $staff->id }}" {{ ($filters['user_id'] ?? '') == $staff->id ? 'selected' : '' }}>{{ $staff->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2 form-group">
        <label class="control-label">Activity Type</label>
        <select name="type" class="form-control audit-log-filter">
          <option value="">All activity</option>
          <option value="login" {{ ($filters['type'] ?? '') === 'login' ? 'selected' : '' }}>Logins & logouts only</option>
          <option value="actions" {{ ($filters['type'] ?? '') === 'actions' ? 'selected' : '' }}>Actions only</option>
        </select>
      </div>
      <div class="col-md-2 form-group">
        <label class="control-label">Action</label>
        <select name="action" class="form-control audit-log-filter">
          <option value="">Any action</option>
          @foreach($actions as $action)
          <option value="{{ $action }}" {{ ($filters['action'] ?? '') === $action ? 'selected' : '' }}>{{ str_replace('_', ' ', $action) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3 form-group">
        <label class="control-label">Search</label>
        <input type="text" name="search" class="form-control audit-log-filter" value="{{ $filters['search'] ?? '' }}" placeholder="Name, action, description">
      </div>
      <div class="col-md-2 form-group">
        <label class="control-label">From</label>
        <input type="date" name="date_from" class="form-control audit-log-filter" value="{{ $filters['date_from'] ?? '' }}">
      </div>
      <div class="col-md-2 form-group">
        <label class="control-label">To</label>
        <input type="date" name="date_to" class="form-control audit-log-filter" value="{{ $filters['date_to'] ?? '' }}">
      </div>
      <div class="col-md-8 form-group d-flex align-items-end">
        <button type="button" class="btn btn-primary mr-2" id="auditLogRefreshBtn"><i class="fa fa-refresh"></i> Refresh Now</button>
        <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="d-flex justify-content-between align-items-center px-3 pt-3">
        <h3 class="tile-title mb-0">Recent Activity</h3>
        <span class="badge badge-success" id="auditLiveBadge"><span class="audit-live-dot"></span>Live · <span id="auditLiveTime">—</span></span>
      </div>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover table-bordered table-sm mb-0">
            <thead style="background-color: #940000; color: white;">
              <tr>
                <th>{{ __('tables.columns.date_time') }}</th>
                <th>{{ __('tables.columns.business') }}</th>
                <th>{{ __('tables.columns.user') }}</th>
                <th>{{ __('tables.columns.action') }}</th>
                <th>{{ __('tables.columns.details') }}</th>
                <th>IP &amp; location</th>
              </tr>
            </thead>
            <tbody id="auditLogsTbody">
              @include('admin.audit_logs.partials.rows', ['logs' => $logs])
            </tbody>
          </table>
        </div>
        <small class="text-muted d-block mt-2">Showing latest 50 matching entries · auto-refreshes every 5 seconds</small>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="auditLogDetailModal" tabindex="-1" role="dialog" aria-labelledby="auditLogDetailModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="auditLogDetailModalTitle"><i class="fa fa-file-text-o"></i> Activity details</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body" id="auditLogDetailBody">
        <div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin"></i> Loading…</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var form = document.getElementById('auditLogFilterForm');
  var tbody = document.getElementById('auditLogsTbody');
  var liveTime = document.getElementById('auditLiveTime');
  var refreshBtn = document.getElementById('auditLogRefreshBtn');
  var feedUrl = @json(route('admin.audit-logs.feed'));
  var showUrlTemplate = @json(route('admin.audit-logs.show', ['auditLog' => '__ID__']));
  var pollMs = 5000;
  var pollTimer = null;
  var searchTimer = null;
  var fetching = false;
  var lastTopId = tbody.querySelector('tr[data-log-id]') ? parseInt(tbody.querySelector('tr[data-log-id]').getAttribute('data-log-id'), 10) : 0;

  function filterParams() {
    return new URLSearchParams(new FormData(form)).toString();
  }

  function updateUrl() {
    var qs = filterParams();
    var url = window.location.pathname + (qs ? '?' + qs : '');
    window.history.replaceState({}, '', url);
  }

  function flashNewRows(previousTopId) {
    tbody.querySelectorAll('tr[data-log-id]').forEach(function (row) {
      var id = parseInt(row.getAttribute('data-log-id'), 10);
      if (id > previousTopId) {
        row.classList.add('audit-log-new');
      }
    });
  }

  function refreshFeed() {
    if (fetching || document.hidden) {
      return;
    }

    fetching = true;
    if (refreshBtn) {
      refreshBtn.disabled = true;
    }

    jQuery.ajax({
      url: feedUrl + '?' + filterParams(),
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      success: function (response) {
        var previousTopId = lastTopId;
        if (response.latest_id) {
          lastTopId = response.latest_id;
        } else {
          lastTopId = 0;
        }

        tbody.innerHTML = response.html || '';
        if (previousTopId && response.latest_id && response.latest_id > previousTopId) {
          flashNewRows(previousTopId);
        }

        if (liveTime) {
          liveTime.textContent = 'updated ' + (response.updated_at || 'now');
        }

        updateUrl();
      },
      complete: function () {
        fetching = false;
        if (refreshBtn) {
          refreshBtn.disabled = false;
        }
      }
    });
  }

  function scheduleRefresh(delay) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(refreshFeed, delay || 0);
  }

  form.querySelectorAll('.audit-log-filter').forEach(function (el) {
    if (el.type === 'text') {
      el.addEventListener('input', function () {
        scheduleRefresh(400);
      });
    } else {
      el.addEventListener('change', function () {
        scheduleRefresh(0);
      });
    }
  });

  if (refreshBtn) {
    refreshBtn.addEventListener('click', refreshFeed);
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    refreshFeed();
  });

  pollTimer = setInterval(refreshFeed, pollMs);
  refreshFeed();

  document.addEventListener('visibilitychange', function () {
    if (! document.hidden) {
      refreshFeed();
    }
  });

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
  }

  function renderDetailModal(data) {
    var loc = data.ip_location || {};
    var locLines = [];
    if (loc.city) locLines.push('<strong>City:</strong> ' + escapeHtml(loc.city));
    if (loc.region) locLines.push('<strong>Region:</strong> ' + escapeHtml(loc.region));
    if (loc.country) locLines.push('<strong>Country:</strong> ' + escapeHtml(loc.country));
    if (loc.isp) locLines.push('<strong>ISP:</strong> ' + escapeHtml(loc.isp));
    var locHtml = locLines.length
      ? '<div class="detail-value">' + locLines.join('<br>') + '</div><div class="text-muted small">' + escapeHtml(loc.label || '') + '</div>'
      : '<div class="detail-value">' + escapeHtml(loc.label || '—') + '</div>';

    document.getElementById('auditLogDetailModalTitle').innerHTML =
      '<i class="fa fa-file-text-o"></i> #' + escapeHtml(data.id) + ' · <span class="badge ' + escapeHtml(data.badge_class) + '">' + escapeHtml(data.action_label) + '</span>';

    document.getElementById('auditLogDetailBody').innerHTML =
      '<div class="row">' +
        '<div class="col-md-6"><div class="detail-label">Date & time</div><div class="detail-value">' + escapeHtml(data.created_at) + '</div></div>' +
        '<div class="col-md-6"><div class="detail-label">Business</div><div class="detail-value">' + escapeHtml(data.business) + '</div></div>' +
        '<div class="col-md-6"><div class="detail-label">User</div><div class="detail-value">' + escapeHtml(data.user) + ' <span class="text-muted">(' + escapeHtml(data.user_role) + ')</span></div></div>' +
        '<div class="col-md-6"><div class="detail-label">IP address</div><div class="detail-value"><code>' + escapeHtml(data.ip_address) + '</code></div></div>' +
        '<div class="col-md-12"><div class="detail-label">Location</div>' + locHtml + '</div>' +
        '<div class="col-md-12"><div class="detail-label">Details</div><div class="detail-value" style="white-space: pre-wrap; word-break: break-word;">' + escapeHtml(data.description) + '</div></div>' +
        '<div class="col-md-12"><div class="detail-label">Browser / device</div><div class="detail-value small text-muted" style="word-break: break-all;">' + escapeHtml(data.user_agent) + '</div></div>' +
      '</div>';
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.audit-log-view-btn');
    if (!btn) return;

    var logId = btn.getAttribute('data-log-id');
    if (!logId) return;

    var url = showUrlTemplate.replace('__ID__', logId);
    document.getElementById('auditLogDetailBody').innerHTML = '<div class="text-center text-muted py-4"><i class="fa fa-spinner fa-spin"></i> Loading…</div>';
    jQuery('#auditLogDetailModal').modal('show');

    jQuery.ajax({
      url: url,
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      success: function (data) {
        renderDetailModal(data);
      },
      error: function () {
        document.getElementById('auditLogDetailBody').innerHTML = '<div class="alert alert-danger mb-0">Could not load activity details.</div>';
      }
    });
  });
})();
</script>
@endpush

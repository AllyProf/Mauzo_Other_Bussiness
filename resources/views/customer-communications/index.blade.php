@extends('layouts.app')

@section('title', __('communications.title'))

@section('styles')
<style>
  .comms-page .comms-stat-tile .table th {
    width: 38%;
    white-space: nowrap;
    vertical-align: top;
    padding-right: 12px;
  }
  .comms-page .comms-stat-tile .table td {
    word-break: break-word;
  }
  .comms-page .comms-channel-group .custom-control-inline {
    display: inline-flex;
    margin-right: 1rem;
    margin-bottom: 0.35rem;
  }
  .comms-page .comms-customer-list {
    max-height: 220px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 10px;
    -webkit-overflow-scrolling: touch;
  }
  .comms-page .comms-customer-list .custom-control-label {
    line-height: 1.45;
  }
  .comms-page .comms-mobile-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 10px;
    background: #fff;
  }
  .comms-page .comms-mobile-card:last-child {
    margin-bottom: 0;
  }
  .comms-page .comms-mobile-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
  }
  .comms-page .comms-mobile-card-title {
    font-weight: 700;
    color: #212529;
    line-height: 1.35;
  }
  .comms-page .comms-mobile-meta {
    font-size: 0.82rem;
    color: #6c757d;
    line-height: 1.5;
  }
  .comms-page .comms-mobile-meta strong {
    color: #495057;
    font-weight: 600;
  }
  .comms-page .comms-mobile-message {
    font-size: 0.88rem;
    color: #343a40;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed #e9ecef;
    word-break: break-word;
  }
  .comms-page .comms-compose-actions .btn {
    min-height: 44px;
  }

  @media (max-width: 991.98px) {
    .comms-page .app-title h1 {
      font-size: 1.35rem;
      line-height: 1.35;
    }
    .comms-page .app-title p {
      font-size: 0.88rem;
    }
    .comms-page .tile {
      padding: 14px;
    }
    .comms-page .comms-compose-col {
      margin-bottom: 1rem;
    }
  }

  @media (max-width: 767.98px) {
    .comms-page .app-title {
      margin-bottom: 16px;
    }
    .comms-page .app-title h1 {
      font-size: 1.15rem;
    }
    .comms-page .app-title p {
      font-size: 0.82rem;
    }
    .comms-page .tile-title {
      font-size: 1rem;
    }
    .comms-page .comms-channel-group .custom-control-inline {
      display: flex;
      width: 100%;
      margin-right: 0;
      margin-bottom: 0.5rem;
    }
    .comms-page .comms-customer-list {
      max-height: 280px;
      padding: 12px;
    }
    .comms-page .comms-customer-list .custom-control {
      min-height: 36px;
      padding-left: 1.75rem;
    }
    .comms-page .comms-compose-actions .btn {
      width: 100%;
    }
    .comms-page input[type="datetime-local"].form-control {
      font-size: 16px;
    }
    .comms-page .form-control,
    .comms-page select.form-control,
    .comms-page textarea.form-control {
      font-size: 16px;
    }
  }

  @media (max-width: 575.98px) {
    .comms-page .comms-stat-tile .table th,
    .comms-page .comms-stat-tile .table td {
      display: block;
      width: 100%;
      padding: 0.15rem 0;
    }
    .comms-page .comms-stat-tile .table tr + tr th {
      padding-top: 0.65rem;
    }
    .comms-page .comms-mobile-card {
      padding: 11px 12px;
    }
  }
</style>
@endsection

@section('content')
<div class="comms-page">

<div class="app-title">
  <div>
    <h1><i class="fa fa-commenting"></i> {{ __('communications.title') }}</h1>
    <p>{{ __('communications.subtitle') }}</p>
  </div>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

@php
  $smsQuotaExhausted = ($quota['sms']['enabled'] ?? false) && $quota['sms']['remaining'] === 0;
  $emailQuotaExhausted = ($quota['email']['enabled'] ?? false) && $quota['email']['remaining'] === 0;
  $defaultChannels = old('channels');
  if ($defaultChannels === null) {
      $defaultChannels = $smsQuotaExhausted
          ? ($emailQuotaExhausted ? [] : ['email'])
          : ['sms'];
  }
@endphp

@if($smsQuotaExhausted)
<div class="alert alert-warning">{{ __('communications.sms_quota_reached') }}</div>
@endif
@if($emailQuotaExhausted)
<div class="alert alert-warning">{{ __('communications.email_quota_reached') }}</div>
@endif

<div class="row mb-3">
  <div class="col-sm-6 col-12 mb-3 mb-sm-0">
    <div class="tile comms-stat-tile h-100 mb-0">
      <h3 class="tile-title">{{ __('communications.monthly_quota') }}</h3>
      <div class="tile-body">
        <table class="table table-sm table-borderless mb-0">
          <tr>
            <th>{{ __('communications.sms') }}</th>
            <td>
              @if($quota['sms']['enabled'])
                {{ __('communications.used', ['count' => number_format($quota['sms']['used'])]) }}
                @if($quota['sms']['limit'])
                  / {{ number_format($quota['sms']['limit']) }}
                  ({{ __('communications.left', ['count' => $quota['sms']['remaining']]) }})
                @else
                  / {{ __('communications.unlimited') }}
                @endif
              @else
                <span class="badge badge-secondary">{{ __('communications.disabled_on_plan') }}</span>
              @endif
            </td>
          </tr>
          <tr>
            <th>{{ __('communications.email') }}</th>
            <td>
              @if($quota['email']['enabled'])
                {{ __('communications.used', ['count' => number_format($quota['email']['used'])]) }}
                @if($quota['email']['limit'])
                  / {{ number_format($quota['email']['limit']) }}
                  ({{ __('communications.left', ['count' => $quota['email']['remaining']]) }})
                @else
                  / {{ __('communications.unlimited') }}
                @endif
              @else
                <span class="badge badge-secondary">{{ __('communications.disabled_on_plan') }}</span>
              @endif
            </td>
          </tr>
        </table>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-12">
    <div class="tile h-100 mb-0">
      <h3 class="tile-title">{{ __('communications.recipients') }}</h3>
      <div class="tile-body d-flex align-items-center h-100">
        <p class="mb-0">{{ __('communications.recipients_count', ['count' => $customers->count()]) }}</p>
      </div>
    </div>
  </div>
</div>

@if($scheduledCampaigns->isNotEmpty())
<div class="row mb-3">
  <div class="col-12">
    <div class="tile mb-0">
      <h3 class="tile-title">{{ __('communications.scheduled_messages') }}</h3>
      <div class="tile-body">
        <div class="d-md-none">
          @foreach($scheduledCampaigns as $campaign)
          <div class="comms-mobile-card">
            <div class="comms-mobile-card-head">
              <div>
                <div class="comms-mobile-card-title">{{ $campaign->scheduled_at->format('M d, Y H:i') }}</div>
                <div class="comms-mobile-meta mt-1">
                  {{ $campaign->channelsLabel() }} · {{ $campaign->purposeLabel() }}
                </div>
              </div>
              <form method="POST" action="{{ route('customer-communications.cancel', $campaign) }}" onsubmit="return confirm(@json(__('communications.cancel_confirm')));">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('communications.cancel') }}</button>
              </form>
            </div>
            <div class="comms-mobile-meta">
              <strong>{{ __('communications.recipients') }}:</strong> {{ count($campaign->customer_ids ?? []) }}<br>
              <strong>{{ __('communications.created_by') }}:</strong> {{ $campaign->user->name ?? '—' }}
            </div>
            <div class="comms-mobile-message">{{ Str::limit($campaign->message, 120) }}</div>
          </div>
          @endforeach
        </div>

        <div class="table-responsive d-none d-md-block">
          <table class="table table-sm table-bordered mb-0">
            <thead>
              <tr>
                <th>{{ __('communications.scheduled_for') }}</th>
                <th>{{ __('communications.channels') }}</th>
                <th>{{ __('communications.purpose') }}</th>
                <th>{{ __('communications.recipients') }}</th>
                <th>{{ __('tables.columns.message') }}</th>
                <th>{{ __('communications.created_by') }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @foreach($scheduledCampaigns as $campaign)
              <tr>
                <td class="text-nowrap">{{ $campaign->scheduled_at->format('M d, Y H:i') }}</td>
                <td>{{ $campaign->channelsLabel() }}</td>
                <td>{{ $campaign->purposeLabel() }}</td>
                <td>{{ count($campaign->customer_ids ?? []) }}</td>
                <td><small>{{ Str::limit($campaign->message, 60) }}</small></td>
                <td>{{ $campaign->user->name ?? '—' }}</td>
                <td class="text-nowrap">
                  <form method="POST" action="{{ route('customer-communications.cancel', $campaign) }}" class="d-inline" onsubmit="return confirm(@json(__('communications.cancel_confirm')));">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('communications.cancel') }}</button>
                  </form>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <small class="text-muted d-block mt-2">{{ __('communications.scheduler_hint') }}</small>
      </div>
    </div>
  </div>
</div>
@endif

<div class="row">
  <div class="col-12 col-lg-5 comms-compose-col">
    <div class="tile mb-0">
      <h3 class="tile-title">{{ __('communications.compose') }}</h3>
      <div class="tile-body">
        <form method="POST" action="{{ route('customer-communications.send') }}" id="communication-form">
          @csrf
          <div class="form-group">
            <label class="control-label">{{ __('communications.delivery_channels') }}</label>
            <div class="comms-channel-group">
              @if($quota['sms']['enabled'])
              <div class="custom-control custom-checkbox custom-control-inline">
                <input type="checkbox" class="custom-control-input channel-checkbox" id="channel_sms" name="channels[]" value="sms" {{ in_array('sms', $defaultChannels, true) ? 'checked' : '' }} {{ $smsQuotaExhausted ? 'disabled' : '' }}>
                <label class="custom-control-label {{ $smsQuotaExhausted ? 'text-muted' : '' }}" for="channel_sms">
                  {{ __('communications.sms') }}
                  @if($smsQuotaExhausted)
                    <small class="text-danger d-block">{{ __('communications.quota_reached', ['channel' => 'SMS']) }}</small>
                  @endif
                </label>
              </div>
              @endif
              @if($quota['email']['enabled'])
              <div class="custom-control custom-checkbox custom-control-inline">
                <input type="checkbox" class="custom-control-input channel-checkbox" id="channel_email" name="channels[]" value="email" {{ in_array('email', $defaultChannels, true) ? 'checked' : '' }} {{ $emailQuotaExhausted ? 'disabled' : '' }}>
                <label class="custom-control-label {{ $emailQuotaExhausted ? 'text-muted' : '' }}" for="channel_email">
                  {{ __('communications.email') }}
                  @if($emailQuotaExhausted)
                    <small class="text-danger d-block">{{ __('communications.quota_reached', ['channel' => 'Email']) }}</small>
                  @endif
                </label>
              </div>
              @endif
              @if(! $quota['sms']['enabled'] && ! $quota['email']['enabled'])
              <p class="text-muted mb-0">{{ __('communications.no_channels') }}</p>
              @endif
            </div>
          </div>
          <div class="form-group">
            <label class="control-label">{{ __('communications.purpose') }}</label>
            <select name="purpose" class="form-control" required>
              <option value="new_product" {{ old('purpose') === 'new_product' ? 'selected' : '' }}>{{ __('communications.purposes.new_product') }}</option>
              <option value="promotion" {{ old('purpose') === 'promotion' ? 'selected' : '' }}>{{ __('communications.purposes.promotion') }}</option>
              <option value="debt_reminder" {{ old('purpose') === 'debt_reminder' ? 'selected' : '' }}>{{ __('communications.purposes.debt_reminder') }}</option>
              <option value="general" {{ old('purpose', 'general') === 'general' ? 'selected' : '' }}>{{ __('communications.purposes.general') }}</option>
            </select>
          </div>
          <div class="form-group" id="subject-group" style="display:none;">
            <label class="control-label">{{ __('communications.email_subject') }}</label>
            <input type="text" name="subject" class="form-control" maxlength="255" value="{{ old('subject') }}" placeholder="{{ __('communications.email_subject_placeholder') }}">
          </div>
          <div class="form-group">
            <label class="control-label">{{ __('communications.message') }}</label>
            <textarea name="message" class="form-control" rows="5" maxlength="480" required placeholder="{{ __('communications.message_placeholder') }}">{{ old('message') }}</textarea>
            <small class="text-muted">{{ __('communications.message_hint') }}</small>
          </div>
          <div class="form-group">
            <label class="control-label">{{ __('communications.when_to_send') }}</label>
            <div class="custom-control custom-radio">
              <input type="radio" id="send_now" name="send_mode" value="now" class="custom-control-input" {{ old('send_mode', 'now') === 'now' ? 'checked' : '' }}>
              <label class="custom-control-label" for="send_now">{{ __('communications.send_now') }}</label>
            </div>
            <div class="custom-control custom-radio">
              <input type="radio" id="send_scheduled" name="send_mode" value="scheduled" class="custom-control-input" {{ old('send_mode') === 'scheduled' ? 'checked' : '' }}>
              <label class="custom-control-label" for="send_scheduled">{{ __('communications.schedule_later') }}</label>
            </div>
          </div>
          <div class="form-group" id="schedule-group" style="display:none;">
            <label class="control-label">{{ __('communications.scheduled_datetime') }}</label>
            <input type="datetime-local" name="scheduled_at" class="form-control" value="{{ old('scheduled_at') }}">
          </div>
          <div class="form-group mb-0">
            <label class="control-label">{{ __('communications.select_customers') }}</label>
            <div class="comms-customer-list">
              <div class="custom-control custom-checkbox mb-2">
                <input type="checkbox" class="custom-control-input" id="select_all_customers">
                <label class="custom-control-label font-weight-bold" for="select_all_customers">{{ __('communications.select_all') }}</label>
              </div>
              @forelse($customers as $customer)
              <div class="custom-control custom-checkbox mb-1">
                <input type="checkbox" class="custom-control-input customer-checkbox" id="customer_{{ $customer->id }}" name="customer_ids[]" value="{{ $customer->id }}" {{ in_array($customer->id, old('customer_ids', [])) ? 'checked' : '' }}>
                <label class="custom-control-label" for="customer_{{ $customer->id }}">
                  {{ $customer->name }}
                  <small class="text-muted d-block d-sm-inline">
                    @if($customer->phone)({{ $customer->displayPhone() }})@endif
                    @if($customer->phone && $customer->email) · @endif
                    @if($customer->email){{ $customer->email }}@endif
                  </small>
                </label>
              </div>
              @empty
              <p class="text-muted mb-0">{{ __('communications.no_customers') }}</p>
              @endforelse
            </div>
          </div>
          <div class="comms-compose-actions mt-3">
            <button type="submit" class="btn btn-primary" id="send-button" {{ $customers->isEmpty() ? 'disabled' : '' }}><i class="fa fa-send"></i> {{ __('communications.send_message') }}</button>
            <small id="send-button-hint" class="text-muted d-block mt-2" style="display:none;"></small>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="tile mb-0">
      <h3 class="tile-title">{{ __('communications.recent_messages') }}</h3>
      <div class="tile-body">
        <div class="d-md-none">
          @forelse($logs as $log)
          <div class="comms-mobile-card">
            <div class="comms-mobile-card-head">
              <div>
                <div class="comms-mobile-card-title">{{ $log->recipient_name ?? '—' }}</div>
                <div class="comms-mobile-meta mt-1">{{ $log->created_at->format('M d, H:i') }} · {{ $log->channelLabel() }}</div>
              </div>
              <span class="badge badge-{{ $log->status === 'sent' ? 'success' : ($log->status === 'failed' ? 'danger' : 'secondary') }}">{{ $log->statusLabel() }}</span>
            </div>
            <div class="comms-mobile-meta">
              <strong>{{ __('communications.purpose') }}:</strong> {{ $log->purposeLabel() }}<br>
              {{ $log->recipientContact() }}
            </div>
            <div class="comms-mobile-message">{{ Str::limit($log->message, 140) }}</div>
          </div>
          @empty
          <p class="text-center text-muted mb-0 py-3">{{ __('communications.no_messages_yet') }}</p>
          @endforelse
        </div>

        <div class="table-responsive d-none d-md-block">
          <table class="table table-hover table-sm table-bordered mb-0">
            <thead>
              <tr>
                <th>{{ __('tables.columns.date') }}</th>
                <th>{{ __('communications.channel') }}</th>
                <th>{{ __('tables.columns.customer') }}</th>
                <th>{{ __('communications.purpose') }}</th>
                <th>{{ __('tables.columns.status') }}</th>
                <th>{{ __('tables.columns.message') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($logs as $log)
              <tr>
                <td class="text-nowrap">{{ $log->created_at->format('M d, H:i') }}</td>
                <td>{{ $log->channelLabel() }}</td>
                <td>{{ $log->recipient_name ?? '—' }}<br><small class="text-muted">{{ $log->recipientContact() }}</small></td>
                <td>{{ $log->purposeLabel() }}</td>
                <td><span class="badge badge-{{ $log->status === 'sent' ? 'success' : ($log->status === 'failed' ? 'danger' : 'secondary') }}">{{ $log->statusLabel() }}</span></td>
                <td><small>{{ Str::limit($log->message, 80) }}</small></td>
              </tr>
              @empty
              <tr><td colspan="6" class="text-center text-muted">{{ __('communications.no_messages_yet') }}</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

</div>
@endsection

@section('scripts')
@php
    $commI18n = [
        'send_message' => __('communications.send_message'),
        'schedule_message' => __('communications.schedule_message'),
        'sms_quota_reached' => __('communications.sms_quota_reached'),
        'email_quota_reached' => __('communications.email_quota_reached'),
        'select_channel' => __('communications.delivery_channels'),
    ];
    $commQuota = [
        'smsExhausted' => $smsQuotaExhausted,
        'emailExhausted' => $emailQuotaExhausted,
    ];
@endphp
<script>
(function () {
  var commI18n = @json($commI18n);
  var commQuota = @json($commQuota);
  var selectAll = document.getElementById('select_all_customers');
  var boxes = document.querySelectorAll('.customer-checkbox');
  var channelBoxes = document.querySelectorAll('.channel-checkbox');
  var subjectGroup = document.getElementById('subject-group');
  var scheduleGroup = document.getElementById('schedule-group');
  var sendNow = document.getElementById('send_now');
  var sendScheduled = document.getElementById('send_scheduled');
  var sendButton = document.getElementById('send-button');
  var sendButtonHint = document.getElementById('send-button-hint');

  function channelIsUsable(channel) {
    if (channel === 'sms') {
      return !commQuota.smsExhausted;
    }
    if (channel === 'email') {
      return !commQuota.emailExhausted;
    }
    return false;
  }

  function hasSelectedUsableChannel() {
    var usable = false;
    channelBoxes.forEach(function (box) {
      if (box.checked && !box.disabled && channelIsUsable(box.value)) {
        usable = true;
      }
    });
    return usable;
  }

  function updateSubmitState() {
    if (!sendButton) {
      return;
    }

    var hasCustomers = boxes.length > 0;
    var canSend = hasCustomers && hasSelectedUsableChannel();

    sendButton.disabled = !canSend;

    if (sendButtonHint) {
      if (!hasCustomers) {
        sendButtonHint.style.display = 'none';
        sendButtonHint.textContent = '';
      } else if (!canSend) {
        sendButtonHint.style.display = 'block';
        if (commQuota.smsExhausted && commQuota.emailExhausted) {
          sendButtonHint.textContent = commI18n.sms_quota_reached;
        } else if (commQuota.smsExhausted) {
          sendButtonHint.textContent = commI18n.sms_quota_reached;
        } else if (commQuota.emailExhausted) {
          sendButtonHint.textContent = commI18n.email_quota_reached;
        } else {
          sendButtonHint.textContent = commI18n.select_channel;
        }
      } else {
        sendButtonHint.style.display = 'none';
        sendButtonHint.textContent = '';
      }
    }
  }

  function updateSubjectVisibility() {
    var emailSelected = document.getElementById('channel_email') && document.getElementById('channel_email').checked;
    subjectGroup.style.display = emailSelected ? 'block' : 'none';
  }

  function updateScheduleVisibility() {
    var scheduled = sendScheduled && sendScheduled.checked;
    scheduleGroup.style.display = scheduled ? 'block' : 'none';
    if (sendButton && sendButton.disabled === false) {
      sendButton.innerHTML = scheduled
        ? '<i class="fa fa-clock-o"></i> ' + commI18n.schedule_message
        : '<i class="fa fa-send"></i> ' + commI18n.send_message;
    }
  }

  if (selectAll) {
    selectAll.addEventListener('change', function () {
      boxes.forEach(function (box) { box.checked = selectAll.checked; });
    });
  }

  channelBoxes.forEach(function (box) {
    box.addEventListener('change', function () {
      updateSubjectVisibility();
      updateSubmitState();
      updateScheduleVisibility();
    });
  });

  if (sendNow) sendNow.addEventListener('change', updateScheduleVisibility);
  if (sendScheduled) sendScheduled.addEventListener('change', updateScheduleVisibility);

  updateSubjectVisibility();
  updateScheduleVisibility();
  updateSubmitState();
})();
</script>
@endsection

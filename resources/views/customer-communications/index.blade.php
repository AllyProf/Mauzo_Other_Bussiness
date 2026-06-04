@extends('layouts.app')

@section('title', 'Customer Communications')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-commenting"></i> Customer Communications</h1>
    <p>Send SMS, email, or both to customers. Schedule messages to go out automatically.</p>
  </div>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row mb-3">
  <div class="col-md-6">
    <div class="tile">
      <h3 class="tile-title">Monthly Quota</h3>
      <div class="tile-body">
        <table class="table table-sm table-borderless mb-0">
          <tr>
            <th>SMS</th>
            <td>
              @if($quota['sms']['enabled'])
                {{ number_format($quota['sms']['used']) }} used
                @if($quota['sms']['limit'])
                  / {{ number_format($quota['sms']['limit']) }}
                  ({{ $quota['sms']['remaining'] }} left)
                @else
                  / Unlimited
                @endif
              @else
                <span class="badge badge-secondary">Disabled on plan</span>
              @endif
            </td>
          </tr>
          <tr>
            <th>Email</th>
            <td>
              @if($quota['email']['enabled'])
                {{ number_format($quota['email']['used']) }} used
                @if($quota['email']['limit'])
                  / {{ number_format($quota['email']['limit']) }}
                  ({{ $quota['email']['remaining'] }} left)
                @else
                  / Unlimited
                @endif
              @else
                <span class="badge badge-secondary">Disabled on plan</span>
              @endif
            </td>
          </tr>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="tile">
      <h3 class="tile-title">Recipients</h3>
      <div class="tile-body">
        <p class="mb-0"><strong>{{ $customers->count() }}</strong> active customers with a phone number and/or email address.</p>
      </div>
    </div>
  </div>
</div>

@if($scheduledCampaigns->isNotEmpty())
<div class="row mb-3">
  <div class="col-12">
    <div class="tile">
      <h3 class="tile-title">Scheduled Messages</h3>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-sm table-bordered mb-0">
            <thead>
              <tr>
                <th>Scheduled For</th>
                <th>Channels</th>
                <th>Purpose</th>
                <th>Recipients</th>
                <th>Message</th>
                <th>Created By</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @foreach($scheduledCampaigns as $campaign)
              <tr>
                <td>{{ $campaign->scheduled_at->format('M d, Y H:i') }}</td>
                <td>{{ $campaign->channelsLabel() }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $campaign->purpose)) }}</td>
                <td>{{ count($campaign->customer_ids ?? []) }}</td>
                <td><small>{{ Str::limit($campaign->message, 60) }}</small></td>
                <td>{{ $campaign->user->name ?? '—' }}</td>
                <td class="text-nowrap">
                  <form method="POST" action="{{ route('customer-communications.cancel', $campaign) }}" class="d-inline" onsubmit="return confirm('Cancel this scheduled message?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                  </form>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <small class="text-muted d-block mt-2">Scheduled messages are sent automatically every minute once due. Keep the scheduler running on your server.</small>
      </div>
    </div>
  </div>
</div>
@endif

<div class="row">
  <div class="col-lg-5">
    <div class="tile">
      <h3 class="tile-title">Compose Message</h3>
      <div class="tile-body">
        <form method="POST" action="{{ route('customer-communications.send') }}" id="communication-form">
          @csrf
          <div class="form-group">
            <label class="control-label">Delivery Channels</label>
            <div>
              @if($quota['sms']['enabled'])
              <div class="custom-control custom-checkbox custom-control-inline">
                <input type="checkbox" class="custom-control-input channel-checkbox" id="channel_sms" name="channels[]" value="sms" {{ in_array('sms', old('channels', ['sms'])) ? 'checked' : '' }}>
                <label class="custom-control-label" for="channel_sms">SMS</label>
              </div>
              @endif
              @if($quota['email']['enabled'])
              <div class="custom-control custom-checkbox custom-control-inline">
                <input type="checkbox" class="custom-control-input channel-checkbox" id="channel_email" name="channels[]" value="email" {{ in_array('email', old('channels', [])) ? 'checked' : '' }}>
                <label class="custom-control-label" for="channel_email">Email</label>
              </div>
              @endif
              @if(! $quota['sms']['enabled'] && ! $quota['email']['enabled'])
              <p class="text-muted mb-0">No messaging channels are enabled on your plan.</p>
              @endif
            </div>
          </div>
          <div class="form-group">
            <label class="control-label">Purpose</label>
            <select name="purpose" class="form-control" required>
              <option value="new_product" {{ old('purpose') === 'new_product' ? 'selected' : '' }}>New Product Announcement</option>
              <option value="promotion" {{ old('purpose') === 'promotion' ? 'selected' : '' }}>Promotion / Offer</option>
              <option value="debt_reminder" {{ old('purpose') === 'debt_reminder' ? 'selected' : '' }}>Debt Reminder</option>
              <option value="general" {{ old('purpose', 'general') === 'general' ? 'selected' : '' }}>General Message</option>
            </select>
          </div>
          <div class="form-group" id="subject-group" style="display:none;">
            <label class="control-label">Email Subject</label>
            <input type="text" name="subject" class="form-control" maxlength="255" value="{{ old('subject') }}" placeholder="Subject line for email recipients">
          </div>
          <div class="form-group">
            <label class="control-label">Message</label>
            <textarea name="message" class="form-control" rows="5" maxlength="480" required placeholder="Write your message here...">{{ old('message') }}</textarea>
            <small class="text-muted">Max 480 characters. Used for SMS and email body.</small>
          </div>
          <div class="form-group">
            <label class="control-label">When to Send</label>
            <div class="custom-control custom-radio">
              <input type="radio" id="send_now" name="send_mode" value="now" class="custom-control-input" {{ old('send_mode', 'now') === 'now' ? 'checked' : '' }}>
              <label class="custom-control-label" for="send_now">Send now</label>
            </div>
            <div class="custom-control custom-radio">
              <input type="radio" id="send_scheduled" name="send_mode" value="scheduled" class="custom-control-input" {{ old('send_mode') === 'scheduled' ? 'checked' : '' }}>
              <label class="custom-control-label" for="send_scheduled">Schedule for later</label>
            </div>
          </div>
          <div class="form-group" id="schedule-group" style="display:none;">
            <label class="control-label">Scheduled Date &amp; Time</label>
            <input type="datetime-local" name="scheduled_at" class="form-control" value="{{ old('scheduled_at') }}">
          </div>
          <div class="form-group">
            <label class="control-label">Select Customers</label>
            <div style="max-height:220px;overflow-y:auto;border:1px solid #dee2e6;border-radius:4px;padding:10px;">
              <div class="custom-control custom-checkbox mb-2">
                <input type="checkbox" class="custom-control-input" id="select_all_customers">
                <label class="custom-control-label font-weight-bold" for="select_all_customers">Select all</label>
              </div>
              @forelse($customers as $customer)
              <div class="custom-control custom-checkbox mb-1">
                <input type="checkbox" class="custom-control-input customer-checkbox" id="customer_{{ $customer->id }}" name="customer_ids[]" value="{{ $customer->id }}" {{ in_array($customer->id, old('customer_ids', [])) ? 'checked' : '' }}>
                <label class="custom-control-label" for="customer_{{ $customer->id }}">
                  {{ $customer->name }}
                  <small class="text-muted">
                    @if($customer->phone)({{ $customer->displayPhone() }})@endif
                    @if($customer->phone && $customer->email) · @endif
                    @if($customer->email){{ $customer->email }}@endif
                  </small>
                </label>
              </div>
              @empty
              <p class="text-muted mb-0">No customers with contact details found. Add phone or email under Customers first.</p>
              @endforelse
            </div>
          </div>
          <button type="submit" class="btn btn-primary" id="send-button" {{ $customers->isEmpty() ? 'disabled' : '' }}><i class="fa fa-send"></i> Send Message</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="tile">
      <h3 class="tile-title">Recent Messages</h3>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover table-sm table-bordered mb-0">
            <thead>
              <tr>
                <th>Date</th>
                <th>Channel</th>
                <th>Customer</th>
                <th>Purpose</th>
                <th>Status</th>
                <th>Message</th>
              </tr>
            </thead>
            <tbody>
              @forelse($logs as $log)
              <tr>
                <td>{{ $log->created_at->format('M d, H:i') }}</td>
                <td>{{ $log->channelLabel() }}</td>
                <td>{{ $log->recipient_name ?? '—' }}<br><small class="text-muted">{{ $log->recipientContact() }}</small></td>
                <td>{{ $log->purposeLabel() }}</td>
                <td><span class="badge badge-{{ $log->status === 'sent' ? 'success' : ($log->status === 'failed' ? 'danger' : 'secondary') }}">{{ $log->statusLabel() }}</span></td>
                <td><small>{{ Str::limit($log->message, 80) }}</small></td>
              </tr>
              @empty
              <tr><td colspan="6" class="text-center text-muted">No messages sent yet.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
  var selectAll = document.getElementById('select_all_customers');
  var boxes = document.querySelectorAll('.customer-checkbox');
  var channelBoxes = document.querySelectorAll('.channel-checkbox');
  var subjectGroup = document.getElementById('subject-group');
  var scheduleGroup = document.getElementById('schedule-group');
  var sendNow = document.getElementById('send_now');
  var sendScheduled = document.getElementById('send_scheduled');
  var sendButton = document.getElementById('send-button');

  function updateSubjectVisibility() {
    var emailSelected = document.getElementById('channel_email') && document.getElementById('channel_email').checked;
    subjectGroup.style.display = emailSelected ? 'block' : 'none';
  }

  function updateScheduleVisibility() {
    var scheduled = sendScheduled && sendScheduled.checked;
    scheduleGroup.style.display = scheduled ? 'block' : 'none';
    if (sendButton) {
      sendButton.innerHTML = scheduled
        ? '<i class="fa fa-clock-o"></i> Schedule Message'
        : '<i class="fa fa-send"></i> Send Message';
    }
  }

  if (selectAll) {
    selectAll.addEventListener('change', function () {
      boxes.forEach(function (box) { box.checked = selectAll.checked; });
    });
  }

  channelBoxes.forEach(function (box) {
    box.addEventListener('change', updateSubjectVisibility);
  });

  if (sendNow) sendNow.addEventListener('change', updateScheduleVisibility);
  if (sendScheduled) sendScheduled.addEventListener('change', updateScheduleVisibility);

  updateSubjectVisibility();
  updateScheduleVisibility();
})();
</script>
@endsection

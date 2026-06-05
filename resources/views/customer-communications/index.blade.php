@extends('layouts.app')



@section('title', __('communications.title'))



@section('content')

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



<div class="row mb-3">

  <div class="col-md-6">

    <div class="tile">

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

  <div class="col-md-6">

    <div class="tile">

      <h3 class="tile-title">{{ __('communications.recipients') }}</h3>

      <div class="tile-body">

        <p class="mb-0">{{ __('communications.recipients_count', ['count' => $customers->count()]) }}</p>

      </div>

    </div>

  </div>

</div>



@if($scheduledCampaigns->isNotEmpty())

<div class="row mb-3">

  <div class="col-12">

    <div class="tile">

      <h3 class="tile-title">{{ __('communications.scheduled_messages') }}</h3>

      <div class="tile-body">

        <div class="table-responsive">

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

                <td>{{ $campaign->scheduled_at->format('M d, Y H:i') }}</td>

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

  <div class="col-lg-5">

    <div class="tile">

      <h3 class="tile-title">{{ __('communications.compose') }}</h3>

      <div class="tile-body">

        <form method="POST" action="{{ route('customer-communications.send') }}" id="communication-form">

          @csrf

          <div class="form-group">

            <label class="control-label">{{ __('communications.delivery_channels') }}</label>

            <div>

              @if($quota['sms']['enabled'])

              <div class="custom-control custom-checkbox custom-control-inline">

                <input type="checkbox" class="custom-control-input channel-checkbox" id="channel_sms" name="channels[]" value="sms" {{ in_array('sms', old('channels', ['sms'])) ? 'checked' : '' }}>

                <label class="custom-control-label" for="channel_sms">{{ __('communications.sms') }}</label>

              </div>

              @endif

              @if($quota['email']['enabled'])

              <div class="custom-control custom-checkbox custom-control-inline">

                <input type="checkbox" class="custom-control-input channel-checkbox" id="channel_email" name="channels[]" value="email" {{ in_array('email', old('channels', [])) ? 'checked' : '' }}>

                <label class="custom-control-label" for="channel_email">{{ __('communications.email') }}</label>

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

          <div class="form-group">

            <label class="control-label">{{ __('communications.select_customers') }}</label>

            <div style="max-height:220px;overflow-y:auto;border:1px solid #dee2e6;border-radius:4px;padding:10px;">

              <div class="custom-control custom-checkbox mb-2">

                <input type="checkbox" class="custom-control-input" id="select_all_customers">

                <label class="custom-control-label font-weight-bold" for="select_all_customers">{{ __('communications.select_all') }}</label>

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

              <p class="text-muted mb-0">{{ __('communications.no_customers') }}</p>

              @endforelse

            </div>

          </div>

          <button type="submit" class="btn btn-primary" id="send-button" {{ $customers->isEmpty() ? 'disabled' : '' }}><i class="fa fa-send"></i> {{ __('communications.send_message') }}</button>

        </form>

      </div>

    </div>

  </div>



  <div class="col-lg-7">

    <div class="tile">

      <h3 class="tile-title">{{ __('communications.recent_messages') }}</h3>

      <div class="tile-body">

        <div class="table-responsive">

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

                <td>{{ $log->created_at->format('M d, H:i') }}</td>

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

@endsection



@section('scripts')
@php
    $commI18n = [
        'send_message' => __('communications.send_message'),
        'schedule_message' => __('communications.schedule_message'),
    ];
@endphp
<script>
(function () {
  var commI18n = @json($commI18n);
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

    box.addEventListener('change', updateSubjectVisibility);

  });



  if (sendNow) sendNow.addEventListener('change', updateScheduleVisibility);

  if (sendScheduled) sendScheduled.addEventListener('change', updateScheduleVisibility);



  updateSubjectVisibility();

  updateScheduleVisibility();

})();

</script>

@endsection



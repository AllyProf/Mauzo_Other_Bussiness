@extends('layouts.app')

@section('title', 'Communication Room - Software Owner')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
  .nav-tabs .nav-link {
    font-size: 1.05rem;
    padding: 12px 20px;
    border: none;
    border-bottom: 3px solid transparent;
    color: #6c757d;
    transition: all 0.3s ease;
  }
  .nav-tabs .nav-link:hover {
    color: #940000;
    border-bottom: 3px solid #ffcdd2;
  }
  .nav-tabs .nav-link.active {
    color: #940000 !important;
    border-bottom: 3px solid #940000 !important;
    background: transparent !important;
    font-weight: bold;
  }
  .placeholder-btn {
    display: inline-block;
    margin: 3px 2px;
    font-size: 0.8rem;
    font-family: monospace;
    border: 1px dashed #940000;
    color: #940000;
    background-color: #fff8f8;
    border-radius: 4px;
    padding: 3px 8px;
    cursor: pointer;
    transition: all 0.2s ease;
  }
  .placeholder-btn:hover {
    background-color: #940000;
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(148,0,0,0.15);
  }
  .char-counter-badge {
    font-size: 0.85rem;
    padding: 5px 10px;
    border-radius: 50px;
    font-weight: 600;
  }
  .bg-sms-count {
    background-color: #e3f2fd;
    color: #0d47a1;
  }
  .badge-sent { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
  .badge-failed { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
  .badge-pending { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
  .select2-container--default .select2-selection--multiple {
    border: 1px solid #ced4da;
    border-radius: 4px;
    padding: 5px;
  }
  .tile-header-premium {
    border-bottom: 1px solid #f5f5f5;
    padding-bottom: 15px;
    margin-bottom: 20px;
  }
</style>
@endpush

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-comments"></i> Communication Room</h1>
    <p>Manage bulk SMS communication and edit automated notification templates</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="#">Communication Room</a></li>
  </ul>
</div>

<div class="row">
  <div class="col-md-12">

    <div class="tile">
      <ul class="nav nav-tabs" id="commRoomTabs" role="tablist" style="border-bottom: 2px solid #dee2e6; margin-bottom: 25px;">
        <li class="nav-item">
          <a class="nav-link {{ $statusFilter === 'all' ? 'active' : '' }}" id="broadcast-tab" data-toggle="tab" href="#broadcast" role="tab" aria-controls="broadcast" aria-selected="true">
            <i class="fa fa-paper-plane mr-2"></i> SMS Broadcast
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="templates-tab" data-toggle="tab" href="#templates" role="tab" aria-controls="templates" aria-selected="false">
            <i class="fa fa-sliders mr-2"></i> Automated SMS Templates
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link {{ $statusFilter !== 'all' ? 'active' : '' }}" id="logs-tab" data-toggle="tab" href="#logs" role="tab" aria-controls="logs" aria-selected="false">
            <i class="fa fa-history mr-2"></i> Dispatch Logs
            @if($counts['scheduled'] > 0)
              <span class="badge badge-warning ml-1" style="font-size: 0.75rem;">{{ $counts['scheduled'] }} scheduled</span>
            @endif
          </a>
        </li>
      </ul>

      <div class="tab-content" id="commRoomTabsContent">
        <!-- Tab 1: SMS Broadcast -->
        <div class="tab-pane fade {{ $statusFilter === 'all' ? 'show active' : '' }}" id="broadcast" role="tabpanel" aria-labelledby="broadcast-tab">
          <div class="row">
            <div class="col-lg-7">
              <div class="tile-header-premium">
                <h4 class="text-danger"><i class="fa fa-bullhorn mr-2"></i> Compose SMS Broadcast</h4>
                <p class="text-muted">Draft a general message or notice. Target businesses, platform staff, or business employees.</p>
              </div>

              <form action="{{ route('admin.communication.send-broadcast') }}" method="POST" id="broadcastForm">
                @csrf

                <div class="form-group">
                  <label class="control-label" style="font-weight: 600;">Recipient Group</label>
                  <select name="recipient_group" id="recipientGroup" class="form-control" required>
                    <optgroup label="Businesses">
                      <option value="all">All Registered Businesses</option>
                      <option value="active">Active Subscription Businesses</option>
                      <option value="suspended">Suspended Businesses</option>
                      <option value="expired">Expired Subscription Businesses</option>
                      <option value="selected">Select Specific Businesses</option>
                    </optgroup>
                    <optgroup label="Staff">
                      <option value="platform_staff">Platform Staff Only</option>
                      <option value="business_staff">Business Staff Only</option>
                    </optgroup>
                  </select>
                  <small class="form-text text-muted" id="recipientGroupHint">
                    Business options send to each business phone number on file.
                  </small>
                </div>

                <div class="form-group d-none" id="selectedBusinessesWrapper">
                  <label class="control-label" style="font-weight: 600;" id="selectedBusinessesLabel">Select Businesses</label>
                  <select name="selected_businesses[]" id="selectedBusinesses" class="form-control" multiple style="width: 100%;">
                    @foreach($businesses as $business)
                      <option value="{{ $business->id }}">
                        {{ $business->name }} ({{ $business->phone ?? 'No Phone' }} - {{ $business->owner_name ?? 'No Owner' }})
                      </option>
                    @endforeach
                  </select>
                  <small class="form-text text-muted d-none" id="businessStaffFilterHint">
                    Optional: leave empty to message staff from all businesses, or pick businesses to limit the list.
                  </small>
                </div>

                <div class="form-group mb-3">
                  <label class="control-label" style="font-weight: 600; display: block; margin-bottom: 8px;">Delivery Schedule</label>
                  <div class="btn-group" role="group">
                    <button type="button" id="btnSendImmediate" class="btn btn-sm btn-danger active"
                      onclick="setSendType('immediate')">
                      <i class="fa fa-paper-plane mr-1"></i> Send Immediately
                    </button>
                    <button type="button" id="btnSendScheduled" class="btn btn-sm btn-outline-secondary"
                      onclick="setSendType('scheduled')">
                      <i class="fa fa-calendar mr-1"></i> Schedule for Later
                    </button>
                  </div>
                  <input type="hidden" name="send_type" id="sendTypeInput" value="immediate">
                </div>

                <div class="form-group d-none mb-3" id="schedulingWrapper">
                  <label class="control-label" style="font-weight: 600;">Schedule Date & Time</label>
                  <input type="datetime-local" name="scheduled_at" id="scheduledAt" class="form-control" min="{{ now()->timezone('Africa/Dar_es_Salaam')->format('Y-m-d\TH:i') }}">
                  <small class="form-text text-muted">Select when the broadcast should be sent out automatically.</small>
                </div>

                <div class="form-group">
                  <div class="d-flex justify-content-between align-items-center">
                    <label class="control-label" style="font-weight: 600;">Message Content</label>
                    <div class="d-flex align-items-center">
                      <span id="charCount" class="badge badge-secondary mr-2">0 chars</span>
                      <span id="smsCount" class="badge bg-sms-count">1 SMS</span>
                    </div>
                  </div>
                  <textarea name="message" id="broadcastMessage" class="form-control" rows="6" placeholder="Write your announcement or notice here..." required></textarea>
                  
                  <div class="mt-2">
                    <span class="text-muted small mr-2">Click to insert placeholder:</span>
                    <button type="button" class="placeholder-btn" data-target="broadcastMessage" data-variable="{business_name}">{business_name}</button>
                    <button type="button" class="placeholder-btn" data-target="broadcastMessage" data-variable="{contact_person}">{contact_person}</button>
                    <button type="button" class="placeholder-btn" data-target="broadcastMessage" data-variable="{staff_name}">{staff_name}</button>
                  </div>
                </div>

                <div class="form-group">
                  <button type="submit" id="broadcastSubmitBtn" class="btn btn-primary btn-block btn-lg"
                    style="background-color: #940000; border-color: #940000;"
                    onclick="confirmBroadcast(event)">
                    <i class="fa fa-paper-plane mr-2"></i> <span id="submitBtnLabel">Send SMS Broadcast</span>
                  </button>
                </div>
              </form>
            </div>

            <div class="col-lg-5" style="border-left: 1px solid #eee;">
              <div class="p-3">
                <h5 class="text-secondary"><i class="fa fa-info-circle mr-2"></i> SMS Broadcasting Guidelines</h5>
                <hr>
                <ul>
                  <li class="mb-2"><strong>Character Limits:</strong> A standard single SMS contains up to <strong>160 characters</strong>. If your message exceeds this, it will be split into multiple parts (costing more credits).</li>
                  <li class="mb-2"><strong>Placeholders:</strong>
                    <ul>
                      <li><code>{business_name}</code> - Business name (business or business-staff sends).</li>
                      <li><code>{contact_person}</code> - Owner/contact name for business sends.</li>
                      <li><code>{staff_name}</code> - Individual staff member name (staff sends).</li>
                    </ul>
                  </li>
                  <li class="mb-2"><strong>Staff targeting:</strong>
                    <ul>
                      <li><strong>Platform Staff Only</strong> — Mauzo Link admin staff with a phone number.</li>
                      <li><strong>Business Staff Only</strong> — tenant employees (not owners). Optionally filter by business.</li>
                    </ul>
                  </li>
                  <li class="mb-2"><strong>Real-time Filtering:</strong> You can choose "Select Specific Businesses" to target one or a few selected businesses directly.</li>
                  <li class="mb-2"><strong>Delivery Notice:</strong> Messages are sent using the configured gateway details (MauzoLink sender ID).</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab 2: Automated SMS Templates -->
        <div class="tab-pane fade" id="templates" role="tabpanel" aria-labelledby="templates-tab">
          <form action="{{ route('admin.communication.update-templates') }}" method="POST">
            @csrf

            <div class="tile-header-premium d-flex justify-content-between align-items-center">
              <div>
                <h4 class="text-danger"><i class="fa fa-sliders mr-2"></i> Automated SMS Settings & Templates</h4>
                <p class="text-muted">Edit the automated notification text messages dispatched by the system on events.</p>
              </div>
              <button type="submit" class="btn btn-primary" style="background-color: #940000; border-color: #940000;">
                <i class="fa fa-save mr-2"></i> Save All Templates
              </button>
            </div>

            <div class="row">
              <!-- Column 1 -->
              <div class="col-md-6">
                <div class="tile p-3 mb-4" style="background: #fafafa; border: 1px solid #eaeaea;">
                  <h5 class="text-dark font-weight-bold"><i class="fa fa-user-plus text-danger mr-2"></i> Verification & Pending</h5>
                  <hr class="my-2">
                  
                  <div class="form-group">
                    <label class="font-weight-bold">Registration Verification Code</label>
                    <textarea name="sms_template_registration_verification" id="tpl_verification" class="form-control form-control-sm" rows="3" required>{{ old('sms_template_registration_verification', $settings['sms_template_registration_verification'] ?? '') }}</textarea>
                    <div class="mt-1">
                      <button type="button" class="placeholder-btn" data-target="tpl_verification" data-variable="{platform_name}">{platform_name}</button>
                      <button type="button" class="placeholder-btn" data-target="tpl_verification" data-variable="{code}">{code}</button>
                    </div>
                  </div>

                  <div class="form-group mt-3">
                    <label class="font-weight-bold">Registration Received (Pending Approval)</label>
                    <textarea name="sms_template_registration_pending" id="tpl_pending" class="form-control form-control-sm" rows="3" required>{{ old('sms_template_registration_pending', $settings['sms_template_registration_pending'] ?? '') }}</textarea>
                    <div class="mt-1">
                      <button type="button" class="placeholder-btn" data-target="tpl_pending" data-variable="{platform_name}">{platform_name}</button>
                      <button type="button" class="placeholder-btn" data-target="tpl_pending" data-variable="{business_name}">{business_name}</button>
                    </div>
                  </div>
                </div>

                <div class="tile p-3 mb-4" style="background: #fafafa; border: 1px solid #eaeaea;">
                  <h5 class="text-dark font-weight-bold"><i class="fa fa-check-circle text-danger mr-2"></i> Approvals & Rejections</h5>
                  <hr class="my-2">

                  <div class="form-group">
                    <label class="font-weight-bold">Registration Approved (Standard)</label>
                    <textarea name="sms_template_registration_approved" id="tpl_approved" class="form-control form-control-sm" rows="3" required>{{ old('sms_template_registration_approved', $settings['sms_template_registration_approved'] ?? '') }}</textarea>
                    <div class="mt-1">
                      <button type="button" class="placeholder-btn" data-target="tpl_approved" data-variable="{business_name}">{business_name}</button>
                      <button type="button" class="placeholder-btn" data-target="tpl_approved" data-variable="{login_email}">{login_email}</button>
                      <button type="button" class="placeholder-btn" data-target="tpl_approved" data-variable="{password}">{password}</button>
                      <button type="button" class="placeholder-btn" data-target="tpl_approved" data-variable="{support_phone}">{support_phone}</button>
                    </div>
                  </div>

                  <div class="form-group mt-3">
                    <label class="font-weight-bold">Registration Rejected</label>
                    <textarea name="sms_template_registration_rejected" id="tpl_rejected" class="form-control form-control-sm" rows="3" required>{{ old('sms_template_registration_rejected', $settings['sms_template_registration_rejected'] ?? '') }}</textarea>
                    <div class="mt-1">
                      <button type="button" class="placeholder-btn" data-target="tpl_rejected" data-variable="{platform_name}">{platform_name}</button>
                      <button type="button" class="placeholder-btn" data-target="tpl_rejected" data-variable="{business_name}">{business_name}</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Column 2 -->
              <div class="col-md-6">
                <div class="tile p-3 mb-4" style="background: #fafafa; border: 1px solid #eaeaea;">
                  <h5 class="text-dark font-weight-bold"><i class="fa fa-lock text-danger mr-2"></i> Security & Suspension</h5>
                  <hr class="my-2">

                  <div class="form-group">
                    <label class="font-weight-bold">Password Reset Notification</label>
                    <textarea name="sms_template_password_reset" id="tpl_password" class="form-control form-control-sm" rows="3" required>{{ old('sms_template_password_reset', $settings['sms_template_password_reset'] ?? '') }}</textarea>
                    <div class="mt-1">
                      <button type="button" class="placeholder-btn" data-target="tpl_password" data-variable="{platform_name}">{platform_name}</button>
                      <button type="button" class="placeholder-btn" data-target="tpl_password" data-variable="{login_email}">{login_email}</button>
                      <button type="button" class="placeholder-btn" data-target="tpl_password" data-variable="{password}">{password}</button>
                      <button type="button" class="placeholder-btn" data-target="tpl_password" data-variable="{support_phone}">{support_phone}</button>
                    </div>
                  </div>

                  <div class="form-group mt-3">
                    <label class="font-weight-bold">Account Suspended Notice</label>
                    <textarea name="sms_template_account_suspended" id="tpl_suspended" class="form-control form-control-sm" rows="3" required>{{ old('sms_template_account_suspended', $settings['sms_template_account_suspended'] ?? '') }}</textarea>
                    <div class="mt-1">
                      <button type="button" class="placeholder-btn" data-target="tpl_suspended" data-variable="{platform_name}">{platform_name}</button>
                      <button type="button" class="placeholder-btn" data-target="tpl_suspended" data-variable="{reason}">{reason}</button>
                    </div>
                  </div>

                  <div class="form-group mt-3">
                    <label class="font-weight-bold">Account Reactivated Notice</label>
                    <textarea name="sms_template_account_reactivated" id="tpl_reactivated" class="form-control form-control-sm" rows="3" required>{{ old('sms_template_account_reactivated', $settings['sms_template_account_reactivated'] ?? '') }}</textarea>
                    <div class="mt-1">
                      <button type="button" class="placeholder-btn" data-target="tpl_reactivated" data-variable="{platform_name}">{platform_name}</button>
                    </div>
                  </div>
                </div>

                <div class="tile p-3 mb-4" style="background: #fafafa; border: 1px solid #eaeaea;">
                  <h5 class="text-dark font-weight-bold"><i class="fa fa-link text-danger mr-2"></i> Account Registration & Linking</h5>
                  <hr class="my-2">

                  <div class="form-group">
                    <label class="font-weight-bold">Business Owner Account Created (Directly Registered)</label>
                    <textarea name="sms_template_business_registered" id="tpl_biz_reg" class="form-control form-control-sm" rows="3" required>{{ old('sms_template_business_registered', $settings['sms_template_business_registered'] ?? '') }}</textarea>
                    <div class="mt-1">
                      <button type="button" class="placeholder-btn" data-target="tpl_biz_reg" data-variable="{business_name}">{business_name}</button>
                      <button type="button" class="placeholder-btn" data-target="tpl_biz_reg" data-variable="{login_email}">{login_email}</button>
                      <button type="button" class="placeholder-btn" data-target="tpl_biz_reg" data-variable="{password}">{password}</button>
                      <button type="button" class="placeholder-btn" data-target="tpl_biz_reg" data-variable="{support_phone}">{support_phone}</button>
                    </div>
                  </div>

                  <div class="form-group mt-3">
                    <label class="font-weight-bold">New Branch/Business Linked to Existing Owner</label>
                    <textarea name="sms_template_business_linked" id="tpl_biz_linked" class="form-control form-control-sm" rows="3" required>{{ old('sms_template_business_linked', $settings['sms_template_business_linked'] ?? '') }}</textarea>
                    <div class="mt-1">
                      <button type="button" class="placeholder-btn" data-target="tpl_biz_linked" data-variable="{business_name}">{business_name}</button>
                      <button type="button" class="placeholder-btn" data-target="tpl_biz_linked" data-variable="{login_email}">{login_email}</button>
                      <button type="button" class="placeholder-btn" data-target="tpl_biz_linked" data-variable="{support_phone}">{support_phone}</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="tile-footer d-flex justify-content-end">
              <button type="submit" class="btn btn-primary btn-lg" style="background-color: #940000; border-color: #940000;">
                <i class="fa fa-save mr-2"></i> Save All Templates
              </button>
            </div>
          </form>
        </div>

        <!-- Tab 3: Dispatch Logs -->
        <div class="tab-pane fade {{ $statusFilter !== 'all' ? 'show active' : '' }}" id="logs" role="tabpanel" aria-labelledby="logs-tab">
          <div class="tile-header-premium">
            <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:10px;">
              <div>
                <h4 class="text-danger"><i class="fa fa-history mr-2"></i> SMS Dispatch Logs</h4>
                <p class="text-muted mb-0">View all system notifications and custom broadcasts sent via SMS.</p>
              </div>
              {{-- Filter Pills --}}
              <div class="d-flex flex-wrap" style="gap: 6px;">
                <a href="{{ route('admin.communication.index', ['status' => 'all']) }}#logs"
                   class="btn btn-sm {{ $statusFilter === 'all' ? 'btn-dark' : 'btn-outline-secondary' }}">
                  All <span class="badge badge-light ml-1">{{ $counts['all'] }}</span>
                </a>
                <a href="{{ route('admin.communication.index', ['status' => 'scheduled']) }}#logs"
                   class="btn btn-sm {{ $statusFilter === 'scheduled' ? 'btn-warning' : 'btn-outline-warning' }}">
                  <i class="fa fa-calendar mr-1"></i> Scheduled <span class="badge badge-warning ml-1" style="background:#e65100;color:#fff;">{{ $counts['scheduled'] }}</span>
                </a>
                <a href="{{ route('admin.communication.index', ['status' => 'sent']) }}#logs"
                   class="btn btn-sm {{ $statusFilter === 'sent' ? 'btn-success' : 'btn-outline-success' }}">
                  <i class="fa fa-check mr-1"></i> Sent <span class="badge badge-light ml-1">{{ $counts['sent'] }}</span>
                </a>
                <a href="{{ route('admin.communication.index', ['status' => 'failed']) }}#logs"
                   class="btn btn-sm {{ $statusFilter === 'failed' ? 'btn-danger' : 'btn-outline-danger' }}">
                  <i class="fa fa-times mr-1"></i> Failed <span class="badge badge-light ml-1">{{ $counts['failed'] }}</span>
                </a>
              </div>
            </div>
          </div>

          {{-- Scheduled Queue Alert --}}
          @if($counts['scheduled'] > 0)
          <div class="alert d-flex align-items-center justify-content-between flex-wrap mb-3"
               style="background: #fff8e1; border: 1px solid #ffe082; border-left: 5px solid #e65100; border-radius: 6px; gap: 10px;">
            <div>
              <i class="fa fa-clock-o mr-2" style="color:#e65100;"></i>
              <strong style="color:#e65100;">{{ $counts['scheduled'] }} message(s) queued for future delivery.</strong>
              <span class="text-muted ml-2">They will be sent automatically when their scheduled time arrives.</span>
            </div>
            <div class="d-flex" style="gap:8px;">
              <a href="{{ route('admin.communication.index', ['status' => 'scheduled']) }}#logs"
                 class="btn btn-sm btn-warning" style="white-space:nowrap;">
                <i class="fa fa-eye mr-1"></i> View Scheduled
              </a>
              <form action="{{ route('admin.communication.cancel-all-scheduled') }}" method="POST" style="display:inline;">
                @csrf
                <button type="button" class="btn btn-sm btn-outline-danger" style="white-space:nowrap;"
                  onclick="Swal.fire({title:'Cancel ALL Scheduled SMS?',text:'This will stop all {{ $counts['scheduled'] }} queued message(s) from being sent.',icon:'warning',showCancelButton:true,confirmButtonColor:\'#dc3545\',confirmButtonText:\'Yes, Cancel All\'}).then(r=>{ if(r.isConfirmed) this.closest(\'form\').submit(); })">
                  <i class="fa fa-ban mr-1"></i> Cancel All
                </button>
              </form>
            </div>
          </div>
          @endif

          <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0" style="font-size:0.91rem;">
              <thead style="background:#f8f9fa;">
                <tr>
                  <th>Recipient / Business</th>
                  <th>Phone Number</th>
                  <th>Purpose</th>
                  <th style="max-width:260px;">Message</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th class="text-center" style="width:110px;">Action</th>
                </tr>
              </thead>
              <tbody>
                @forelse($smsLogs as $log)
                  <tr style="{{ $log->status === 'scheduled' ? 'background-color: #fffde7;' : '' }}">
                    <td>
                      @if($log->recipient_name)
                        <strong>{{ $log->recipient_name }}</strong>
                      @else
                        <span class="text-muted">Unknown</span>
                      @endif
                      @if($log->business)
                        <div class="small text-secondary">{{ $log->business->name }}</div>
                      @endif
                    </td>
                    <td><code>{{ $log->phone }}</code></td>
                    <td>
                      <span class="badge badge-light" style="font-size:0.82rem;">{{ $log->purposeLabel() }}</span>
                    </td>
                    <td style="max-width:260px; word-wrap:break-word; white-space:normal; font-size:0.88rem;">
                      {{ $log->message }}
                    </td>
                    <td>
                      @if($log->status === 'sent')
                        <span class="badge badge-sent"><i class="fa fa-check mr-1"></i> Sent</span>
                      @elseif($log->status === 'failed')
                        <span class="badge badge-failed" title="{{ $log->provider_response }}">
                          <i class="fa fa-times mr-1"></i>
                          {{ $log->provider_response === 'Cancelled by Administrator' ? 'Cancelled' : 'Failed' }}
                        </span>
                      @elseif($log->status === 'scheduled')
                        <span class="badge" style="background:#fff3e0;color:#e65100;border:1px solid #ffe0b2;font-size:0.82rem;">
                          <i class="fa fa-clock-o mr-1"></i> Scheduled
                        </span>
                      @else
                        <span class="badge badge-pending"><i class="fa fa-clock-o mr-1"></i> Pending</span>
                      @endif
                    </td>
                    <td style="white-space:nowrap;">
                      @if($log->scheduled_at && $log->status === 'scheduled')
                        <div class="font-weight-bold" style="color:#e65100; font-size:0.85rem;">
                          <i class="fa fa-calendar-o mr-1"></i>{{ $log->scheduled_at->timezone('Africa/Dar_es_Salaam')->format('M d, Y h:i A') }}
                        </div>
                        <div class="small text-muted live-countdown" data-timestamp="{{ $log->scheduled_at->timestamp }}">
                          Sends {{ $log->scheduled_at->diffForHumans() }}
                        </div>
                      @else
                        <div style="font-size:0.85rem;">{{ $log->created_at->timezone('Africa/Dar_es_Salaam')->format('M d, Y h:i A') }}</div>
                        <div class="small text-muted">{{ $log->created_at->diffForHumans() }}</div>
                      @endif
                    </td>
                    <td class="text-center">
                      @if($log->status === 'scheduled')
                        <form action="{{ route('admin.communication.cancel-scheduled', $log->id) }}" method="POST" style="display:inline;" class="cancel-sms-form">
                          @csrf
                          <button type="button" class="btn btn-sm btn-danger cancel-sms-btn"
                            data-phone="{{ $log->phone }}"
                            data-name="{{ $log->recipient_name ?? 'this recipient' }}"
                            style="font-size:0.8rem; padding:3px 10px;">
                            <i class="fa fa-ban mr-1"></i> Cancel
                          </button>
                        </form>
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="7" class="text-center text-muted p-5">
                      <i class="fa fa-comments-o fa-3x mb-2 d-block"></i>
                      No SMS logs found for the selected filter.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          @if($smsLogs->hasPages())
          <div class="d-flex justify-content-center mt-3">
            {{ $smsLogs->links('pagination::bootstrap-4') }}
          </div>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>


@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
  // Confirm dialog — adapts text based on send mode
  function confirmBroadcast(e) {
    e.preventDefault();
    var form   = e.target.closest('form');
    var button = e.target.closest('button');
    var type   = document.getElementById('sendTypeInput').value;
    var scheduledVal = document.getElementById('scheduledAt').value;

    var title, text, confirmText;
    if (type === 'scheduled' && scheduledVal) {
      var dt = new Date(scheduledVal);
      var formatted = dt.toLocaleString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric', 
        hour: '2-digit', 
        minute: '2-digit', 
        hour12: true 
      });
      title       = 'Schedule SMS Broadcast?';
      text        = 'The message will be queued and sent automatically on ' + formatted + '. You can view scheduled SMS in the Dispatch Logs tab.';
      confirmText = 'Yes, Schedule It!';
    } else {
      var group = document.getElementById('recipientGroup').value;
      title       = 'Send SMS Broadcast Now?';
      if (group === 'platform_staff') {
        text = 'This will send SMS to active platform staff with phone numbers.';
      } else if (group === 'business_staff') {
        text = 'This will send SMS to business staff (employees) with phone numbers.';
      } else {
        text = 'This will immediately send SMS to all selected/filtered business phones. Please double-check the message before proceeding.';
      }
      confirmText = 'Yes, Send Now!';
    }

    Swal.fire({
      title: title,
      text:  text,
      icon:  'warning',
      showCancelButton:    true,
      confirmButtonColor:  '#940000',
      cancelButtonColor:   '#6c757d',
      confirmButtonText:   confirmText,
      cancelButtonText:    'Cancel'
    }).then(function(result) {
      if (result.isConfirmed) {
        if (button) button.disabled = true;
        form.submit();
      }
    });
  }

  // setSendType must be global so onclick= can reach it
  function setSendType(type) {
    jQuery('#sendTypeInput').val(type);
    if (type === 'scheduled') {
      jQuery('#schedulingWrapper').removeClass('d-none');
      jQuery('#scheduledAt').prop('required', true);
      jQuery('#btnSendImmediate').removeClass('btn-danger active').addClass('btn-outline-secondary');
      jQuery('#btnSendScheduled').removeClass('btn-outline-secondary').addClass('btn-danger active');
      jQuery('#submitBtnLabel').text('Schedule Broadcast');
      jQuery('#broadcastSubmitBtn').find('i').attr('class', 'fa fa-calendar mr-2');
    } else {
      jQuery('#schedulingWrapper').addClass('d-none');
      jQuery('#scheduledAt').prop('required', false).val('');
      jQuery('#btnSendScheduled').removeClass('btn-danger active').addClass('btn-outline-secondary');
      jQuery('#btnSendImmediate').removeClass('btn-outline-secondary').addClass('btn-danger active');
      jQuery('#submitBtnLabel').text('Send SMS Broadcast');
      jQuery('#broadcastSubmitBtn').find('i').attr('class', 'fa fa-paper-plane mr-2');
    }
  }

  jQuery(document).ready(function($) {
    function updateLiveCountdowns() {
      const now = Math.floor(Date.now() / 1000);
      $('.live-countdown').each(function() {
        const target = parseInt($(this).data('timestamp'), 10);
        if (isNaN(target)) return;

        const diff = target - now;
        if (diff <= 0) {
          $(this).text('Sending now...').removeClass('text-muted').addClass('text-success font-weight-bold');
        } else {
          const hours = Math.floor(diff / 3600);
          const minutes = Math.floor((diff % 3600) / 60);
          const seconds = diff % 60;

          let timeString = '';
          if (hours > 0) {
            timeString += hours + 'h ';
          }
          if (hours > 0 || minutes > 0) {
            timeString += minutes + 'm ';
          }
          timeString += seconds + 's';

          $(this).text('Sends in ' + timeString);
        }
      });
    }

    // Run every second
    setInterval(updateLiveCountdowns, 1000);
    updateLiveCountdowns();

    // Initialize Select2 for businesses selection
    $('#selectedBusinesses').select2({
      placeholder: "Select one or more businesses",
      width: '100%'
    });

    // Toggle selected businesses dropdown based on selected recipient group
    $('#recipientGroup').on('change', function() {
      var value = $(this).val();
      var $wrapper = $('#selectedBusinessesWrapper');
      var $select = $('#selectedBusinesses');
      var $hint = $('#recipientGroupHint');
      var $filterHint = $('#businessStaffFilterHint');
      var $label = $('#selectedBusinessesLabel');

      if (value === 'selected') {
        $wrapper.removeClass('d-none');
        $select.prop('required', true);
        $label.text('Select Businesses');
        $filterHint.addClass('d-none');
        $hint.text('SMS is sent to each selected business phone number.');
      } else if (value === 'business_staff') {
        $wrapper.removeClass('d-none');
        $select.prop('required', false);
        $label.text('Limit to Businesses (optional)');
        $filterHint.removeClass('d-none');
        $hint.text('Sends to tenant employees with phone numbers (owners excluded).');
      } else if (value === 'platform_staff') {
        $wrapper.addClass('d-none');
        $select.prop('required', false).val(null).trigger('change');
        $filterHint.addClass('d-none');
        $hint.text('Sends to active Mauzo Link platform staff with phone numbers.');
      } else {
        $wrapper.addClass('d-none');
        $select.prop('required', false).val(null).trigger('change');
        $filterHint.addClass('d-none');
        $hint.text('Business options send to each business phone number on file.');
      }
    });

    // Character counter and SMS segment estimator
    const $messageTextarea = $('#broadcastMessage');
    const $charCountBadge  = $('#charCount');
    const $smsCountBadge   = $('#smsCount');

    function updateCounters() {
      const text   = $messageTextarea.val();
      const length = text.length;
      $charCountBadge.text(length + ' chars');

      let smsCount = 1;
      if (length > 160) {
        smsCount = Math.ceil(length / 153);
      }

      $smsCountBadge.text(smsCount + ' SMS' + (smsCount > 1 ? 's' : ''));

      if (length === 0) {
        $charCountBadge.removeClass('badge-success badge-warning badge-danger').addClass('badge-secondary');
      } else if (length <= 160) {
        $charCountBadge.removeClass('badge-secondary badge-warning badge-danger').addClass('badge-success');
      } else if (length <= 320) {
        $charCountBadge.removeClass('badge-secondary badge-success badge-danger').addClass('badge-warning');
      } else {
        $charCountBadge.removeClass('badge-secondary badge-success badge-warning').addClass('badge-danger');
      }
    }

    $messageTextarea.on('input', updateCounters);
    updateCounters();

    // Placeholder inserter
    $('.placeholder-btn').on('click', function() {
      const targetId     = $(this).data('target');
      const variableText = $(this).data('variable');
      const textarea     = document.getElementById(targetId);
      if (!textarea) return;

      const startPos = textarea.selectionStart;
      const endPos   = textarea.selectionEnd;
      const textVal  = textarea.value;

      textarea.value = textVal.substring(0, startPos) + variableText + textVal.substring(endPos);
      textarea.focus();
      textarea.selectionStart = startPos + variableText.length;
      textarea.selectionEnd   = startPos + variableText.length;

      if (targetId === 'broadcastMessage') updateCounters();
    });

    // Cancel individual scheduled SMS
    $('.cancel-sms-btn').on('click', function(e) {
      e.preventDefault();
      var btn = $(this);
      var form = btn.closest('form');
      var name = btn.data('name');
      var phone = btn.data('phone');

      Swal.fire({
        title: 'Cancel Scheduled SMS?',
        html: 'This will stop this queued message to <strong>' + name + '</strong> (<code>' + phone + '</code>) from being sent.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Cancel It',
        cancelButtonText: 'Cancel'
      }).then(function(result) {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    });
  });
</script>
@endpush


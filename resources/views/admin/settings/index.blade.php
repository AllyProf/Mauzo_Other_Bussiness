@extends('layouts.app')

@section('title', 'System Settings')

@section('styles')
<style>
  .settings-tabs .nav-link { font-weight: 600; color: #495057; border: none; border-bottom: 3px solid transparent; border-radius: 0; padding: 12px 18px; }
  .settings-tabs .nav-link.active { color: #940000; border-bottom-color: #940000; background: transparent; }
  .settings-tabs .nav-link:hover { color: #940000; }
  .setting-switch-row { padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
  .setting-switch-row:last-child { border-bottom: none; }
</style>
@endsection

@section('content')
@php $activeTab = request('tab', 'profile'); @endphp

<div class="app-title">
  <div>
    <h1><i class="fa fa-gears"></i> System Settings</h1>
    <p>Configure platform-wide rules, registration, subscriptions, mail, and security.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">System Settings</li>
  </ul>
</div>

<div class="row">
  <div class="col-lg-9">
    <div class="tile">
      <ul class="nav nav-tabs settings-tabs border-bottom mb-0 px-3 pt-2" role="tablist">
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'profile' ? 'active' : '' }}" data-toggle="tab" href="#tab-profile"><i class="fa fa-building"></i> Platform</a></li>
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'registration' ? 'active' : '' }}" data-toggle="tab" href="#tab-registration"><i class="fa fa-user-plus"></i> Registration</a></li>
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'subscription' ? 'active' : '' }}" data-toggle="tab" href="#tab-subscription"><i class="fa fa-credit-card"></i> Subscriptions</a></li>
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'mail' ? 'active' : '' }}" data-toggle="tab" href="#tab-mail"><i class="fa fa-envelope"></i> Mail</a></li>
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'security' ? 'active' : '' }}" data-toggle="tab" href="#tab-security"><i class="fa fa-shield"></i> Security</a></li>
      </ul>

      <div class="tile-body tab-content p-4">
        {{-- PLATFORM --}}
        <div class="tab-pane fade {{ $activeTab === 'profile' ? 'show active' : '' }}" id="tab-profile">
          <h5 class="mb-3 text-muted"><i class="fa fa-id-card"></i> Platform Identity</h5>
          <form method="POST" action="{{ route('admin.settings.profile.update') }}" class="settings-form">
            @csrf @method('PUT')
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Platform Name</label>
                  <input type="text" name="platform_name" class="form-control" value="{{ old('platform_name', $settings['platform_name']) }}" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Brand Color</label>
                  <input type="color" name="brand_color" class="form-control" value="{{ old('brand_color', $settings['brand_color']) }}" style="max-width:120px;height:38px;padding:2px;">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Support Email</label>
                  <input type="email" name="support_email" class="form-control" value="{{ old('support_email', $settings['support_email']) }}" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Support Phone</label>
                  <input type="text" name="support_phone" class="form-control" value="{{ old('support_phone', $settings['support_phone']) }}">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label font-weight-bold">WhatsApp</label>
                  <input type="text" name="support_whatsapp" class="form-control" value="{{ old('support_whatsapp', $settings['support_whatsapp']) }}" placeholder="+255...">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Timezone</label>
                  <input type="text" name="timezone" class="form-control" value="{{ old('timezone', $settings['timezone']) }}" required>
                  <small class="text-muted">e.g. Africa/Dar_es_Salaam</small>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Currency Code</label>
                  <input type="text" name="currency_code" class="form-control" value="{{ old('currency_code', $settings['currency_code']) }}" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Currency Symbol</label>
                  <input type="text" name="currency_symbol" class="form-control" value="{{ old('currency_symbol', $settings['currency_symbol']) }}" required>
                </div>
              </div>
              <div class="col-md-12">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Legal Footer</label>
                  <textarea name="legal_footer" class="form-control" rows="2" placeholder="Optional footer text for receipts or contracts">{{ old('legal_footer', $settings['legal_footer']) }}</textarea>
                </div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary settings-save-btn" style="background-color:#940000;border-color:#940000;"><i class="fa fa-save"></i> Save Platform Profile</button>
          </form>
        </div>

        {{-- REGISTRATION --}}
        <div class="tab-pane fade {{ $activeTab === 'registration' ? 'show active' : '' }}" id="tab-registration">
          <h5 class="mb-3 text-muted"><i class="fa fa-user-plus"></i> Business Registration</h5>
          <form method="POST" action="{{ route('admin.settings.registration.update') }}" class="settings-form">
            @csrf @method('PUT')
            <div class="setting-switch-row">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="allow_public_registration" name="allow_public_registration" value="1" {{ old('allow_public_registration', $settings['allow_public_registration']) ? 'checked' : '' }}>
                <label class="custom-control-label" for="allow_public_registration">
                  <strong>Allow public business registration</strong>
                  <br><small class="text-muted">When off, only you can create businesses from the admin panel.</small>
                </label>
              </div>
            </div>
            <div class="setting-switch-row">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="require_admin_approval" name="require_admin_approval" value="1" {{ old('require_admin_approval', $settings['require_admin_approval']) ? 'checked' : '' }}>
                <label class="custom-control-label" for="require_admin_approval">
                  <strong>Require admin approval for new signups</strong>
                  <br><small class="text-muted">New businesses stay inactive until you activate them.</small>
                </label>
              </div>
            </div>
            <div class="row mt-3">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Default Plan</label>
                  <select name="default_plan_id" class="form-control">
                    <option value="">— No plan —</option>
                    @foreach($plans as $plan)
                      <option value="{{ $plan->id }}" {{ (string) old('default_plan_id', $settings['default_plan_id']) === (string) $plan->id ? 'selected' : '' }}>
                        {{ $plan->name }} — {{ $plan->billingSummary() }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Default Trial (days)</label>
                  <input type="number" name="default_trial_days" class="form-control" min="1" max="365" value="{{ old('default_trial_days', $settings['default_trial_days']) }}" required>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Min Password Length</label>
                  <input type="number" name="min_password_length" class="form-control" min="6" max="32" value="{{ old('min_password_length', $settings['min_password_length']) }}" required>
                </div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary settings-save-btn" style="background-color:#940000;border-color:#940000;"><i class="fa fa-save"></i> Save Registration Settings</button>
          </form>
        </div>

        {{-- SUBSCRIPTION --}}
        <div class="tab-pane fade {{ $activeTab === 'subscription' ? 'show active' : '' }}" id="tab-subscription">
          <h5 class="mb-3 text-muted"><i class="fa fa-money"></i> Platform Revenue Model</h5>
          <p class="small text-muted">Default billing settings used when you create a new plan. Each plan can override these.</p>
          <form method="POST" action="{{ route('admin.settings.subscription.update') }}" class="settings-form mb-4">
            @csrf @method('PUT')
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Default Billing Model</label>
                  <select name="default_billing_model" class="form-control">
                    <option value="fixed_monthly" {{ old('default_billing_model', $settings['default_billing_model']) === 'fixed_monthly' ? 'selected' : '' }}>Fixed amount per period</option>
                    <option value="profit_share" {{ old('default_billing_model', $settings['default_billing_model']) === 'profit_share' ? 'selected' : '' }}>Percentage of business profit</option>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Default Profit Share (%)</label>
                  <input type="number" step="0.01" name="default_profit_share_percent" class="form-control" min="0" max="100" value="{{ old('default_profit_share_percent', $settings['default_profit_share_percent']) }}" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Default Profit Basis</label>
                  <select name="default_profit_share_basis" class="form-control">
                    <option value="net_profit" {{ old('default_profit_share_basis', $settings['default_profit_share_basis']) === 'net_profit' ? 'selected' : '' }}>Net profit</option>
                    <option value="gross_profit" {{ old('default_profit_share_basis', $settings['default_profit_share_basis']) === 'gross_profit' ? 'selected' : '' }}>Gross profit</option>
                  </select>
                </div>
              </div>
            </div>

            <hr>
            <h5 class="mb-3 text-muted"><i class="fa fa-credit-card"></i> Subscription Policy</h5>
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Grace Period (days)</label>
                  <input type="number" name="grace_period_days" class="form-control" min="0" max="90" value="{{ old('grace_period_days', $settings['grace_period_days']) }}" required>
                  <small class="text-muted">Extra days after expiry before lockout.</small>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Expiry Warning (days)</label>
                  <input type="number" name="expiry_warning_days" class="form-control" min="1" max="90" value="{{ old('expiry_warning_days', $settings['expiry_warning_days']) }}" required>
                  <small class="text-muted">Shown on your dashboard for expiring businesses.</small>
                </div>
              </div>
            </div>
            <div class="setting-switch-row">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="auto_suspend_on_expiry" name="auto_suspend_on_expiry" value="1" {{ old('auto_suspend_on_expiry', $settings['auto_suspend_on_expiry']) ? 'checked' : '' }}>
                <label class="custom-control-label" for="auto_suspend_on_expiry">
                  <strong>Auto-suspend when grace period ends</strong>
                  <br><small class="text-muted">Marks the business inactive after expiry + grace days.</small>
                </label>
              </div>
            </div>
            <div class="setting-switch-row">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="auto_email_billing_invoices" name="auto_email_billing_invoices" value="1" {{ old('auto_email_billing_invoices', $settings['auto_email_billing_invoices'] ?? true) ? 'checked' : '' }}>
                <label class="custom-control-label" for="auto_email_billing_invoices">
                  <strong>Email monthly subscription invoices automatically</strong>
                  <br><small class="text-muted">Runs on the 1st of each month for the previous month. Requires SMTP mail settings.</small>
                </label>
              </div>
            </div>
            <hr>
            <h5 class="mb-3 text-muted"><i class="fa fa-bell"></i> Payment Reminders & Auto-Suspend</h5>
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Invoice Reminder After (days)</label>
                  <input type="number" name="payment_reminder_days" class="form-control" min="1" max="90" value="{{ old('payment_reminder_days', $settings['payment_reminder_days'] ?? 7) }}" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Auto-Suspend Unpaid (days after expiry+grace)</label>
                  <input type="number" name="auto_suspend_unpaid_days" class="form-control" min="0" max="90" value="{{ old('auto_suspend_unpaid_days', $settings['auto_suspend_unpaid_days'] ?? 14) }}" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Reminder Channels</label>
                  <input type="text" name="payment_reminder_channels" class="form-control" value="{{ old('payment_reminder_channels', $settings['payment_reminder_channels'] ?? 'email,sms') }}" placeholder="email,sms">
                </div>
              </div>
            </div>
            <div class="form-group mt-3">
              <label class="control-label font-weight-bold">Payment Instructions</label>
              <textarea name="payment_instructions" class="form-control" rows="5" placeholder="Bank details, M-Pesa pay number, etc. shown to businesses renewing subscription">{{ old('payment_instructions', $settings['payment_instructions']) }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary settings-save-btn" style="background-color:#940000;border-color:#940000;"><i class="fa fa-save"></i> Save Subscription Policy</button>
          </form>
        </div>

        {{-- MAIL --}}
        <div class="tab-pane fade {{ $activeTab === 'mail' ? 'show active' : '' }}" id="tab-mail">
          <h5 class="mb-1 text-muted"><i class="fa fa-envelope"></i> Outgoing Mail (SMTP)</h5>
          <p class="small text-muted mb-4">Used for system emails when configured. Leave blank to use the server default.</p>
          <form method="POST" action="{{ route('admin.settings.mail.update') }}" class="settings-form">
            @csrf @method('PUT')
            <div class="row">
              <div class="col-md-8">
                <div class="form-group">
                  <label class="control-label font-weight-bold">SMTP Host</label>
                  <input type="text" name="mail_host" class="form-control" value="{{ old('mail_host', $settings['mail_host']) }}" placeholder="smtp.mailtrap.io">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Port</label>
                  <input type="number" name="mail_port" class="form-control" value="{{ old('mail_port', $settings['mail_port']) }}">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Username</label>
                  <input type="text" name="mail_username" class="form-control" value="{{ old('mail_username', $settings['mail_username']) }}">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Password</label>
                  <input type="password" name="mail_password" class="form-control" placeholder="Leave blank to keep current password">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Encryption</label>
                  <select name="mail_encryption" class="form-control">
                    <option value="tls" {{ old('mail_encryption', $settings['mail_encryption']) === 'tls' ? 'selected' : '' }}>TLS</option>
                    <option value="ssl" {{ old('mail_encryption', $settings['mail_encryption']) === 'ssl' ? 'selected' : '' }}>SSL</option>
                    <option value="" {{ old('mail_encryption', $settings['mail_encryption']) === '' ? 'selected' : '' }}>None</option>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label font-weight-bold">From Address</label>
                  <input type="email" name="mail_from_address" class="form-control" value="{{ old('mail_from_address', $settings['mail_from_address']) }}">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="control-label font-weight-bold">From Name</label>
                  <input type="text" name="mail_from_name" class="form-control" value="{{ old('mail_from_name', $settings['mail_from_name']) }}">
                </div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary settings-save-btn" style="background-color:#940000;border-color:#940000;"><i class="fa fa-save"></i> Save Mail Settings</button>
          </form>
        </div>

        {{-- SECURITY --}}
        <div class="tab-pane fade {{ $activeTab === 'security' ? 'show active' : '' }}" id="tab-security">
          <h5 class="mb-3 text-muted"><i class="fa fa-shield"></i> Security &amp; Maintenance</h5>
          <form method="POST" action="{{ route('admin.settings.security.update') }}" class="settings-form mb-4">
            @csrf @method('PUT')
            <div class="setting-switch-row">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="maintenance_mode" name="maintenance_mode" value="1" {{ old('maintenance_mode', $settings['maintenance_mode']) ? 'checked' : '' }}>
                <label class="custom-control-label" for="maintenance_mode">
                  <strong>Maintenance mode</strong>
                  <br><small class="text-muted">Blocks all tenant users. Super admin can still access the system.</small>
                </label>
              </div>
            </div>
            <div class="form-group">
              <label class="control-label font-weight-bold">Maintenance Message</label>
              <textarea name="maintenance_message" class="form-control" rows="3">{{ old('maintenance_message', $settings['maintenance_message']) }}</textarea>
            </div>
            <hr>
            <h6 class="font-weight-bold mb-3"><i class="fa fa-lock"></i> Admin Access & Retention</h6>
            <div class="form-group">
              <label class="control-label font-weight-bold">Admin IP Allowlist</label>
              <textarea name="admin_ip_allowlist" class="form-control" rows="3" placeholder="One IP per line. Leave empty to allow all.">{{ old('admin_ip_allowlist', $settings['admin_ip_allowlist'] ?? '') }}</textarea>
              <small class="text-muted">Restricts /admin access to listed IPs (supports CIDR e.g. 192.168.1.0/24).</small>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Admin Notification Email</label>
                  <input type="email" name="admin_notification_email" class="form-control" value="{{ old('admin_notification_email', $settings['admin_notification_email'] ?? '') }}" placeholder="Alerts for tickets & demo leads">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Admin Notification Phone</label>
                  <input type="text" name="admin_notification_phone" class="form-control" value="{{ old('admin_notification_phone', $settings['admin_notification_phone'] ?? '') }}" placeholder="+255... or 07...">
                  <small class="text-muted">SMS alerts for tickets and demo leads. Falls back to Support Phone if empty.</small>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Audit Log Retention (days)</label>
                  <input type="number" name="audit_log_retention_days" class="form-control" min="30" max="3650" value="{{ old('audit_log_retention_days', $settings['audit_log_retention_days'] ?? 365) }}" required>
                </div>
              </div>
            </div>
            <hr>
            <h6 class="font-weight-bold mb-3"><i class="fa fa-comment"></i> System SMS Notifications</h6>
            <p class="small text-muted">Control which platform actions send SMS via Mauzo Link. Payment expiry/invoice reminders also follow Reminder Channels on the Subscriptions tab.</p>
            <div class="setting-switch-row">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="sms_enabled" name="sms_enabled" value="1" {{ old('sms_enabled', $settings['sms_enabled'] ?? true) ? 'checked' : '' }}>
                <label class="custom-control-label" for="sms_enabled">
                  <strong>Enable platform SMS</strong>
                  <br><small class="text-muted">Master switch for all system SMS below.</small>
                </label>
              </div>
            </div>
            @php
              $smsToggles = [
                'sms_registration_verification' => ['Registration verification code', 'Sent when a business verifies their phone during signup.'],
                'sms_registration_approved' => ['Registration approved', 'Login password sent when you approve a pending business.'],
                'sms_registration_rejected' => ['Registration rejected', 'Notice when a pending registration is rejected.'],
                'sms_password_reset' => ['Owner password reset', 'When admin resets a business owner password with SMS checked.'],
                'sms_account_suspended' => ['Manual account suspend', 'When you suspend a business from admin.'],
                'sms_account_reactivated' => ['Account reactivated', 'When a suspended business is turned back on.'],
                'sms_auto_suspend' => ['Auto-suspend notice', 'When overdue subscription or unpaid invoice triggers auto-suspend.'],
                'sms_invoice_issued' => ['Invoice issued', 'When a monthly platform invoice is emailed to a business.'],
                'sms_payment_confirmed' => ['Payment confirmed', 'When an invoice is marked paid and subscription extended.'],
                'sms_ticket_new_admin' => ['New support ticket (admin)', 'Alert to admin phone when a tenant opens a ticket.'],
                'sms_ticket_reply_business' => ['Ticket reply (business)', 'Alert to business phone when support replies.'],
                'sms_staff_welcome' => ['New platform staff', 'Credentials SMS when you create admin staff with a phone number.'],
                'sms_demo_lead_admin' => ['Demo lead (admin)', 'Alert when someone submits the landing demo form.'],
              ];
            @endphp
            @foreach($smsToggles as $key => [$label, $help])
            <div class="setting-switch-row">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="{{ $key }}" name="{{ $key }}" value="1" {{ old($key, $settings[$key] ?? true) ? 'checked' : '' }}>
                <label class="custom-control-label" for="{{ $key }}">
                  <strong>{{ $label }}</strong>
                  <br><small class="text-muted">{{ $help }}</small>
                </label>
              </div>
            </div>
            @endforeach
            <hr>
            <h6 class="font-weight-bold mb-3"><i class="fa fa-envelope"></i> System Email Notifications</h6>
            <p class="small text-muted">Send the same platform notifications by email when SMTP is configured and a valid email address is available.</p>
            <div class="setting-switch-row">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="email_enabled" name="email_enabled" value="1" {{ old('email_enabled', $settings['email_enabled'] ?? true) ? 'checked' : '' }}>
                <label class="custom-control-label" for="email_enabled">
                  <strong>Enable platform email</strong>
                  <br><small class="text-muted">Master switch for all system emails below.</small>
                </label>
              </div>
            </div>
            @php
              $emailToggles = [
                'email_registration_verification' => ['Registration verification code', 'Also emailed when the applicant provides an email during signup.'],
                'email_registration_approved' => ['Registration approved', 'Login details emailed when you approve a pending business.'],
                'email_registration_rejected' => ['Registration rejected', 'Notice when a pending registration is rejected.'],
                'email_password_reset' => ['Owner password reset', 'When admin resets a business owner password.'],
                'email_account_suspended' => ['Manual account suspend', 'When you suspend a business from admin.'],
                'email_account_reactivated' => ['Account reactivated', 'When a suspended business is turned back on.'],
                'email_auto_suspend' => ['Auto-suspend notice', 'When overdue subscription or unpaid invoice triggers auto-suspend.'],
                'email_invoice_issued' => ['Invoice issued', 'Included with monthly platform invoice emails.'],
                'email_payment_confirmed' => ['Payment confirmed', 'When an invoice is marked paid and subscription extended.'],
                'email_ticket_new_admin' => ['New support ticket (admin)', 'Alert to admin email when a tenant opens a ticket.'],
                'email_ticket_reply_business' => ['Ticket reply (business)', 'Alert to business email when support replies.'],
                'email_staff_welcome' => ['New platform staff', 'Credentials email when you create admin staff.'],
                'email_demo_lead_admin' => ['Demo lead (admin)', 'Alert when someone submits the landing demo form.'],
              ];
            @endphp
            @foreach($emailToggles as $key => [$label, $help])
            <div class="setting-switch-row">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="{{ $key }}" name="{{ $key }}" value="1" {{ old($key, $settings[$key] ?? true) ? 'checked' : '' }}>
                <label class="custom-control-label" for="{{ $key }}">
                  <strong>{{ $label }}</strong>
                  <br><small class="text-muted">{{ $help }}</small>
                </label>
              </div>
            </div>
            @endforeach
            <button type="submit" class="btn btn-primary settings-save-btn mt-3" style="background-color:#940000;border-color:#940000;"><i class="fa fa-save"></i> Save Security Settings</button>
          </form>

          <hr>
          <h6 class="font-weight-bold mb-3"><i class="fa fa-key"></i> Change Super Admin Password</h6>
          <form method="POST" action="{{ route('admin.settings.password.update') }}" class="settings-form" style="max-width:480px;">
            @csrf @method('PUT')
            <div class="form-group">
              <label class="control-label font-weight-bold">Current Password</label>
              <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="form-group">
              <label class="control-label font-weight-bold">New Password</label>
              <input type="password" name="password" class="form-control" required minlength="8">
            </div>
            <div class="form-group">
              <label class="control-label font-weight-bold">Confirm New Password</label>
              <input type="password" name="password_confirmation" class="form-control" required minlength="8">
            </div>
            <button type="submit" class="btn btn-outline-primary"><i class="fa fa-lock"></i> Update Password</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-3">
    <div class="tile">
      <h3 class="tile-title">Admin Tools</h3>
      <div class="tile-body list-group list-group-flush">
        <a href="{{ route('admin.plans.index') }}" class="list-group-item list-group-item-action"><i class="fa fa-credit-card text-primary"></i> Subscription Plans</a>
        <a href="{{ route('admin.businesses.index') }}" class="list-group-item list-group-item-action"><i class="fa fa-building text-primary"></i> All Businesses</a>
        <a href="{{ route('admin.free-trials.index') }}" class="list-group-item list-group-item-action"><i class="fa fa-hourglass-half text-warning"></i> Free Trials</a>
        <a href="{{ route('admin.broadcasts.index') }}" class="list-group-item list-group-item-action"><i class="fa fa-bullhorn text-info"></i> System Broadcasts</a>
        <a href="{{ route('admin.tickets.index') }}" class="list-group-item list-group-item-action"><i class="fa fa-ticket text-danger"></i> Support Tickets</a>
        <a href="{{ route('admin.audit-logs.index') }}" class="list-group-item list-group-item-action"><i class="fa fa-history text-secondary"></i> Audit Logs</a>
      </div>
    </div>

    <div class="tile">
      <h3 class="tile-title">Settings Guide</h3>
      <div class="tile-body small text-muted">
        <p><strong>Platform</strong> — branding and support contacts shown to tenants.</p>
        <p><strong>Registration</strong> — controls the public signup page.</p>
        <p><strong>Subscriptions</strong> — billing model defaults, grace period, and renewal payment info.</p>
        <p><strong>Mail</strong> — SMTP for future system emails.</p>
        <p><strong>Security</strong> — maintenance mode and your admin password.</p>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
jQuery(function($) {
  $('.settings-form').on('submit', function() {
    const $btn = $(this).find('.settings-save-btn');
    if ($btn.length) {
      $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Saving...');
    }
  });

  @if($activeTab !== 'profile')
  $('a[href="#tab-{{ $activeTab }}"]').tab('show');
  @endif
});
</script>
@endsection

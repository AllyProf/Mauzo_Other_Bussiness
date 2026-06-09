@extends('layouts.app')

@section('title', __('settings.title'))

@section('styles')
<style>
  .settings-tabs .nav-link { font-weight: 600; color: #495057; border: none; border-bottom: 3px solid transparent; border-radius: 0; padding: 12px 18px; }
  .settings-tabs .nav-link.active { color: #940000; border-bottom-color: #940000; background: transparent; }
  .settings-tabs .nav-link:hover { color: #940000; }
  .setting-switch-row { padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
  .setting-switch-row:last-child { border-bottom: none; }
  .setting-switch-row .custom-control-label { cursor: pointer; }
  .plan-badge { font-size: 0.85rem; }
</style>
@endsection

@section('content')
@php $activeTab = request('tab', 'profile'); @endphp

<div class="app-title">
  <div>
    <h1><i class="fa fa-gears"></i> {{ __('settings.title') }}</h1>
    <p>Manage your shop profile, finance rules, and automation alerts.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">{{ __('common.settings') }}</li>
  </ul>
</div>

<div class="row">
  <div class="col-lg-9">
    <div class="tile">
      <ul class="nav nav-tabs settings-tabs border-bottom mb-0 px-3 pt-2" role="tablist">
        <li class="nav-item">
          <a class="nav-link {{ $activeTab === 'profile' ? 'active' : '' }}" data-toggle="tab" href="#tab-profile"><i class="fa fa-building"></i> {{ __('settings.tabs.profile') }}</a>
        </li>
        <li class="nav-item">
          <a class="nav-link {{ $activeTab === 'finance' ? 'active' : '' }}" data-toggle="tab" href="#tab-finance"><i class="fa fa-money"></i> {{ __('settings.tabs.finance') }}</a>
        </li>
        <li class="nav-item">
          <a class="nav-link {{ $activeTab === 'payments' ? 'active' : '' }}" data-toggle="tab" href="#tab-payments"><i class="fa fa-credit-card"></i> {{ __('settings.tabs.payments') }}</a>
        </li>
        <li class="nav-item">
          @if(plan_feature('automation_reminders'))
          <a class="nav-link {{ $activeTab === 'automation' ? 'active' : '' }}" data-toggle="tab" href="#tab-automation"><i class="fa fa-bell"></i> {{ __('settings.tabs.automation') }}</a>
          @endif
        </li>
        <li class="nav-item">
          <a class="nav-link {{ $activeTab === 'shifts' ? 'active' : '' }}" data-toggle="tab" href="#tab-shifts"><i class="fa fa-clock-o"></i> {{ __('settings.tabs.shifts') }}</a>
        </li>
        <li class="nav-item">
          <a class="nav-link {{ $activeTab === 'subscription' ? 'active' : '' }}" data-toggle="tab" href="#tab-subscription"><i class="fa fa-credit-card"></i> {{ __('settings.tabs.subscription') }}</a>
        </li>
      </ul>

      <div class="tile-body tab-content p-4">
        {{-- PROFILE --}}
        <div class="tab-pane fade {{ $activeTab === 'profile' ? 'show active' : '' }}" id="tab-profile">
          <h5 class="mb-3 text-muted"><i class="fa fa-id-card"></i> Shop &amp; Contact Information</h5>
          <p class="text-muted small mb-3">These details appear on printed and PDF invoices (name, address, phone, email, TIN, VAT, and logo).</p>
          <form method="POST" action="{{ route('settings.profile.update') }}" class="settings-form" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Business Name <span class="text-danger">*</span></label>
                  <input type="text" name="name" class="form-control" value="{{ old('name', $business->name) }}" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Business Email <span class="text-danger">*</span></label>
                  <input type="email" name="email" class="form-control" value="{{ old('email', $business->email) }}" required>
                  <small class="text-muted">Shown on invoices and business correspondence.</small>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Phone</label>
                  <input type="text" name="phone" class="form-control" value="{{ old('phone', $business->phone) }}" placeholder="+255...">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Contact Person</label>
                  <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person', $business->contact_person) }}" placeholder="Primary business contact">
                  <small class="text-muted">Shown on invoices as the main contact name.</small>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label font-weight-bold">TIN Number</label>
                  <input type="text" name="tin_number" class="form-control" value="{{ old('tin_number', $business->tin_number) }}" placeholder="Tax identification number">
                </div>
              </div>
              <div class="col-md-12">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Address</label>
                  <textarea name="address" class="form-control" rows="3" placeholder="Shop location / postal address">{{ old('address', $business->address) }}</textarea>
                </div>
              </div>
            </div>

            <hr class="my-4">
            <h6 class="text-muted mb-3"><i class="fa fa-file-text-o"></i> Invoice Branding</h6>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label font-weight-bold">Business Logo</label>
                  @if($business->logoUrl())
                    <div class="mb-2">
                      <img src="{{ $business->logoUrl() }}" alt="Business logo" style="max-height:80px;max-width:200px;object-fit:contain;border:1px solid #eee;padding:6px;border-radius:4px;">
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                      <input type="checkbox" class="custom-control-input" id="remove_logo" name="remove_logo" value="1" {{ old('remove_logo') ? 'checked' : '' }}>
                      <label class="custom-control-label" for="remove_logo">Remove current logo</label>
                    </div>
                  @endif
                  <input type="file" name="logo" class="form-control-file" accept="image/jpeg,image/png,image/webp">
                  <small class="text-muted">PNG or JPG, max 2 MB. Displayed at the top of invoices.</small>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="control-label font-weight-bold">VAT Registration Number</label>
                  <input type="text" name="vat_number" class="form-control" value="{{ old('vat_number', $business->vat_number) }}" placeholder="VRN / VAT number">
                </div>
                <div class="form-group">
                  <label class="control-label font-weight-bold">VAT Rate (%)</label>
                  <input type="number" name="vat_rate" class="form-control" value="{{ old('vat_rate', $business->vat_rate) }}" min="0" max="100" step="0.01" placeholder="e.g. 18">
                </div>
                <div class="custom-control custom-checkbox mb-2">
                  <input type="checkbox" class="custom-control-input" id="invoice_show_vat" name="invoice_show_vat" value="1" {{ old('invoice_show_vat', $business->invoice_show_vat) ? 'checked' : '' }}>
                  <label class="custom-control-label" for="invoice_show_vat">Show VAT breakdown on invoices</label>
                </div>
                <div class="custom-control custom-checkbox">
                  <input type="checkbox" class="custom-control-input" id="invoice_vat_inclusive" name="invoice_vat_inclusive" value="1" {{ old('invoice_vat_inclusive', $business->invoice_vat_inclusive ?? true) ? 'checked' : '' }}>
                  <label class="custom-control-label" for="invoice_vat_inclusive">Prices already include VAT</label>
                </div>
                <small class="text-muted d-block mt-1">When checked, VAT is calculated from the invoice total. Uncheck if your prices are before VAT.</small>
              </div>
            </div>
            <button type="submit" class="btn btn-primary settings-save-btn" style="background-color:#940000;border-color:#940000;">
              <i class="fa fa-save"></i> Save Profile
            </button>
          </form>
        </div>

        {{-- FINANCE --}}
        <div class="tab-pane fade {{ $activeTab === 'finance' ? 'show active' : '' }}" id="tab-finance">
          <h5 class="mb-3 text-muted"><i class="fa fa-line-chart"></i> Finance &amp; Reconciliation Rules</h5>
          <form method="POST" action="{{ route('settings.finance.update') }}" class="settings-form">
            @csrf @method('PUT')
            <div class="form-group">
              <label class="control-label font-weight-bold">Staff expenses from daily reconciliation deduct from:</label>
              <div class="mt-2">
                <div class="custom-control custom-radio mb-2">
                  <input type="radio" id="deduct_circulation" name="expense_deduct_from" value="circulation" class="custom-control-input" {{ old('expense_deduct_from', $business->expense_deduct_from ?? 'circulation') === 'circulation' ? 'checked' : '' }}>
                  <label class="custom-control-label" for="deduct_circulation">
                    <strong>Money in Circulation</strong>
                    <br><small class="text-muted">Staff expenses reduce working capital carried forward for restocking and operations.</small>
                  </label>
                </div>
                <div class="custom-control custom-radio">
                  <input type="radio" id="deduct_profit" name="expense_deduct_from" value="profit" class="custom-control-input" {{ old('expense_deduct_from', $business->expense_deduct_from) === 'profit' ? 'checked' : '' }}>
                  <label class="custom-control-label" for="deduct_profit">
                    <strong>Profit</strong>
                    <br><small class="text-muted">Staff expenses reduce daily profit. Collections still roll into circulation.</small>
                  </label>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label class="control-label font-weight-bold">Opening Circulation Balance (TZS)</label>
              <input type="number" name="circulation_balance" class="form-control" min="0" step="0.01" value="{{ old('circulation_balance', $business->circulation_balance ?? 0) }}" style="max-width:280px;">
              <small class="text-muted">Starting working capital before finalized daily reports. Updates automatically when you finalize the Master Sheet.</small>
            </div>

            <div class="alert alert-light border small mb-3">
              <i class="fa fa-info-circle text-primary"></i>
              Owner petty cash can be issued from <strong>profit</strong> or <strong>circulation</strong> per transaction on the
              <a href="{{ route('petty-cash.index') }}">Petty Cash</a> page.
            </div>

            <button type="submit" class="btn btn-primary settings-save-btn" style="background-color:#940000;border-color:#940000;">
              <i class="fa fa-save"></i> Save Finance Settings
            </button>
          </form>
        </div>

        {{-- PAYMENT METHODS --}}
        <div class="tab-pane fade {{ $activeTab === 'payments' ? 'show active' : '' }}" id="tab-payments">
          <h5 class="mb-1 text-muted"><i class="fa fa-credit-card"></i> Payment Methods</h5>
          <p class="small text-muted mb-4">Configure payment options for invoices and POS. Add each platform with its own Lipa number or bank account.</p>

          <form method="POST" action="{{ route('settings.payment-methods.update') }}" class="settings-form">
            @csrf @method('PUT')

            @foreach($paymentMethods as $method)
              @php
                $key = $method['key'];
                $accounts = old("methods.{$key}.accounts", $method['provider_accounts'] ?? []);
                $payNumberLabel = $key === 'bank' ? 'Account Number' : 'Lipa / Pay Number';
                $nameLabel = $key === 'bank' ? 'Account Name' : 'Registered Name';
                $platformLabel = $key === 'bank' ? 'Bank' : 'Platform';
              @endphp
              <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start flex-wrap mb-2">
                  <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="method_{{ $key }}_enabled" name="methods[{{ $key }}][enabled]" value="1" {{ old("methods.{$key}.enabled", $method['enabled']) ? 'checked' : '' }}>
                    <label class="custom-control-label font-weight-bold" for="method_{{ $key }}_enabled">
                      {{ ucfirst(str_replace('_', ' ', $key)) }}
                      @if(($method['type'] ?? '') === 'credit')
                        <span class="badge badge-warning ml-1">Credit</span>
                      @else
                        <span class="badge badge-success ml-1">Pay Now</span>
                      @endif
                    </label>
                  </div>
                </div>
                <div class="form-group mb-2">
                  <label class="small font-weight-bold">Display label</label>
                  <input type="text" name="methods[{{ $key }}][label]" class="form-control" value="{{ old("methods.{$key}.label", $method['label']) }}" required>
                  <small class="text-muted">Shown on invoice create and payment collection screens.</small>
                </div>
                @if(in_array($key, ['mobile_money', 'bank'], true))
                <div class="form-group mb-2">
                  <label class="small font-weight-bold d-block">{{ $platformLabel }}s &amp; pay numbers</label>
                  <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-2 provider-accounts-table" data-method="{{ $key }}" data-pay-label="{{ $payNumberLabel }}" data-name-label="{{ $nameLabel }}" data-platform-label="{{ $platformLabel }}">
                      <thead class="thead-light">
                        <tr>
                          <th>{{ $platformLabel }}</th>
                          <th>{{ $payNumberLabel }}</th>
                          <th>{{ $nameLabel }}</th>
                          <th style="width:50px;"></th>
                        </tr>
                      </thead>
                      <tbody>
                        @forelse($accounts as $i => $account)
                        <tr>
                          <td><input type="text" name="methods[{{ $key }}][accounts][{{ $i }}][name]" class="form-control form-control-sm" value="{{ $account['name'] ?? '' }}" placeholder="{{ $key === 'bank' ? 'CRDB' : 'M-Pesa' }}"></td>
                          <td><input type="text" name="methods[{{ $key }}][accounts][{{ $i }}][pay_number]" class="form-control form-control-sm" value="{{ $account['pay_number'] ?? '' }}" placeholder="{{ $key === 'bank' ? '0150...' : '123456' }}"></td>
                          <td><input type="text" name="methods[{{ $key }}][accounts][{{ $i }}][account_name]" class="form-control form-control-sm" value="{{ $account['account_name'] ?? '' }}"></td>
                          <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-provider-row" title="Remove">&times;</button></td>
                        </tr>
                        @empty
                        <tr>
                          <td><input type="text" name="methods[{{ $key }}][accounts][0][name]" class="form-control form-control-sm" placeholder="{{ $key === 'bank' ? 'CRDB' : 'M-Pesa' }}"></td>
                          <td><input type="text" name="methods[{{ $key }}][accounts][0][pay_number]" class="form-control form-control-sm"></td>
                          <td><input type="text" name="methods[{{ $key }}][accounts][0][account_name]" class="form-control form-control-sm"></td>
                          <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-provider-row" title="Remove">&times;</button></td>
                        </tr>
                        @endforelse
                      </tbody>
                    </table>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-primary add-provider-row" data-method="{{ $key }}"><i class="fa fa-plus"></i> Add {{ $platformLabel }}</button>
                </div>
                @endif
              </div>
            @endforeach

            <button type="submit" class="btn btn-primary settings-save-btn" style="background-color:#940000;border-color:#940000;">
              <i class="fa fa-save"></i> Save Payment Methods
            </button>
          </form>
        </div>

        {{-- AUTOMATION --}}
        @if(plan_feature('automation_reminders'))
        <div class="tab-pane fade {{ $activeTab === 'automation' ? 'show active' : '' }}" id="tab-automation">
          <h5 class="mb-1 text-muted"><i class="fa fa-bell"></i> Automation &amp; Notifications</h5>
          <p class="small text-muted mb-4">Control which reminders appear on your dashboard. Staff do not see owner-only alerts.</p>

          <form method="POST" action="{{ route('settings.automation.update') }}" class="settings-form">
            @csrf @method('PUT')

            <h6 class="font-weight-bold text-dark mt-2"><i class="fa fa-credit-card text-danger"></i> Debt Reminders</h6>
            <div class="setting-switch-row">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="notify_debt_overdue" name="notify_debt_overdue" value="1" {{ old('notify_debt_overdue', $automation['notify_debt_overdue']) ? 'checked' : '' }}>
                <label class="custom-control-label" for="notify_debt_overdue">
                  <strong>Alert when customer debts are overdue</strong>
                  <br><small class="text-muted">Shows on dashboard when due dates have passed and balances remain unpaid.</small>
                </label>
              </div>
            </div>
            <div class="setting-switch-row">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="notify_debt_due_soon" name="notify_debt_due_soon" value="1" {{ old('notify_debt_due_soon', $automation['notify_debt_due_soon']) ? 'checked' : '' }}>
                <label class="custom-control-label" for="notify_debt_due_soon">
                  <strong>Remind before debt due dates</strong>
                  <br><small class="text-muted">Early warning for accounts approaching their due date.</small>
                </label>
              </div>
            </div>
            <div class="row mb-3">
              <div class="col-md-3">
                <label class="small font-weight-bold">1st reminder (days before due)</label>
                <input type="number" name="debt_due_reminder_days" class="form-control form-control-sm" min="1" max="30" value="{{ old('debt_due_reminder_days', $automation['debt_due_reminder_days']) }}">
              </div>
              <div class="col-md-3">
                <label class="small font-weight-bold">Reminder frequency</label>
                @php $debtFrequency = old('debt_reminder_frequency', $automation['debt_reminder_frequency'] ?? 'once'); @endphp
                <select name="debt_reminder_frequency" id="debt_reminder_frequency" class="form-control form-control-sm">
                  <option value="once" {{ $debtFrequency === 'once' ? 'selected' : '' }}>Once (1st reminder only)</option>
                  <option value="twice" {{ $debtFrequency === 'twice' ? 'selected' : '' }}>Twice (1st + 2nd reminder)</option>
                </select>
              </div>
              <div class="col-md-3" id="debtSecondReminderField" style="{{ $debtFrequency === 'twice' ? '' : 'display:none;' }}">
                <label class="small font-weight-bold">2nd reminder (days before due)</label>
                <input type="number" name="debt_due_reminder_days_second" class="form-control form-control-sm" min="1" max="29" value="{{ old('debt_due_reminder_days_second', $automation['debt_due_reminder_days_second'] ?? 1) }}">
                <small class="text-muted">Must be fewer days than the 1st reminder.</small>
              </div>
              <div class="col-md-3">
                <label class="small font-weight-bold">Send SMS at</label>
                <input type="time" name="debt_reminder_send_time" class="form-control form-control-sm" value="{{ old('debt_reminder_send_time', $automation['debt_reminder_send_time'] ?? '08:00') }}">
                <small class="text-muted">Daily time for due-soon, due-today, and overdue SMS.</small>
              </div>
            </div>
            <div class="row mb-3">
              <div class="col-md-4">
                <label class="small font-weight-bold">Default credit term (days)</label>
                <input type="number" name="default_debt_due_days" class="form-control form-control-sm" min="1" max="365" value="{{ old('default_debt_due_days', $automation['default_debt_due_days']) }}">
                <small class="text-muted">Suggested due period for new credit sales.</small>
              </div>
            </div>

            <h6 class="font-weight-bold text-dark mt-3"><i class="fa fa-comment text-success"></i> Debt SMS Reminders</h6>
            <p class="small text-muted mb-2">Automatic SMS to debtors and the staff who recorded the sale. Uses your plan SMS quota.</p>
            <div class="setting-switch-row">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="sms_debt_enabled" name="sms_debt_enabled" value="1" {{ old('sms_debt_enabled', $automation['sms_debt_enabled'] ?? true) ? 'checked' : '' }}>
                <label class="custom-control-label" for="sms_debt_enabled">
                  <strong>Enable debt SMS</strong>
                </label>
              </div>
            </div>
            <div class="setting-switch-row mb-2">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="email_debt_enabled" name="email_debt_enabled" value="1" {{ old('email_debt_enabled', $automation['email_debt_enabled'] ?? true) ? 'checked' : '' }}>
                <label class="custom-control-label" for="email_debt_enabled">
                  <strong>Enable debt email</strong>
                  <br><small class="text-muted">Send the same debt reminder text by email when the customer or staff member has an email address.</small>
                </label>
              </div>
            </div>
            @php
              $debtSmsToggles = [
                'sms_debt_due_soon_customer' => ['Due soon — customer', 'SMS debtor on each scheduled day before the due date.'],
                'sms_debt_due_soon_staff' => ['Due soon — staff', 'SMS the staff member who recorded the credit sale on each pre-due reminder.'],
                'sms_debt_due_today_customer' => ['Due today — customer', 'SMS debtor on the due date.'],
                'sms_debt_due_today_staff' => ['Due today — staff', 'SMS staff on the due date to follow up.'],
                'sms_debt_overdue_customer' => ['Overdue — customer', 'SMS debtor once when payment becomes overdue.'],
                'sms_debt_overdue_staff' => ['Overdue — staff', 'SMS staff once when a debt becomes overdue.'],
              ];
            @endphp
            @foreach($debtSmsToggles as $key => [$label, $help])
            <div class="setting-switch-row">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="{{ $key }}" name="{{ $key }}" value="1" {{ old($key, $automation[$key] ?? true) ? 'checked' : '' }}>
                <label class="custom-control-label" for="{{ $key }}">
                  <strong>{{ $label }}</strong>
                  <br><small class="text-muted">{{ $help }}</small>
                </label>
              </div>
            </div>
            @endforeach

            <div class="mt-3 mb-4">
              <a class="btn btn-sm btn-outline-secondary" data-toggle="collapse" href="#debtSmsTemplates" role="button" aria-expanded="false" aria-controls="debtSmsTemplates">
                <i class="fa fa-pencil mr-1"></i> Customize SMS message templates
              </a>
              <div class="collapse mt-3" id="debtSmsTemplates">
                <p class="small text-muted mb-3">
                  Edit the text sent for each debt reminder. Use placeholders:
                  <code>{business}</code>, <code>{customer}</code>, <code>{amount}</code>, <code>{reference}</code>, <code>{due_date}</code>, <code>{days_before}</code>
                </p>
                @php
                  $debtTemplateDefaults = \App\Models\Business::defaultDebtSmsTemplates();
                  $debtTemplateLabels = \App\Models\Business::debtSmsTemplateLabels();
                @endphp
                @foreach($debtTemplateLabels as $templateKey => $templateLabel)
                <div class="form-group mb-3">
                  <label class="small font-weight-bold">{{ $templateLabel }}</label>
                  <textarea name="{{ $templateKey }}" class="form-control form-control-sm" rows="2" maxlength="480">{{ old($templateKey, $automation[$templateKey] ?? $debtTemplateDefaults[$templateKey]) }}</textarea>
                </div>
                @endforeach
              </div>
            </div>

            <h6 class="font-weight-bold text-dark mt-4"><i class="fa fa-cubes text-warning"></i> Inventory</h6>
            <div class="setting-switch-row">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="notify_low_stock" name="notify_low_stock" value="1" {{ old('notify_low_stock', $automation['notify_low_stock']) ? 'checked' : '' }}>
                <label class="custom-control-label" for="notify_low_stock">
                  <strong>Alert when items are low in stock</strong>
                  <br><small class="text-muted">Dashboard reminder when item quantity falls below your threshold.</small>
                </label>
              </div>
            </div>
            <div class="form-group" style="max-width:200px;">
              <label class="small font-weight-bold">Low stock threshold (units)</label>
              <input type="number" name="low_stock_threshold" class="form-control form-control-sm" min="0" max="1000" value="{{ old('low_stock_threshold', $automation['low_stock_threshold']) }}">
            </div>

            <h6 class="font-weight-bold text-dark mt-4"><i class="fa fa-briefcase text-primary"></i> Operations</h6>
            <div class="setting-switch-row">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="notify_pending_handover" name="notify_pending_handover" value="1" {{ old('notify_pending_handover', $automation['notify_pending_handover']) ? 'checked' : '' }}>
                <label class="custom-control-label" for="notify_pending_handover">
                  <strong>Pending staff handover verifications</strong>
                  <br><small class="text-muted">Remind you when reconciliations are submitted and awaiting boss verification.</small>
                </label>
              </div>
            </div>
            <div class="setting-switch-row">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="notify_finalize_daily_report" name="notify_finalize_daily_report" value="1" {{ old('notify_finalize_daily_report', $automation['notify_finalize_daily_report']) ? 'checked' : '' }}>
                <label class="custom-control-label" for="notify_finalize_daily_report">
                  <strong>Unfinalized Master Sheet days</strong>
                  <br><small class="text-muted">Remind you to finalize verified days so circulation and profit roll forward correctly.</small>
                </label>
              </div>
            </div>
            <div class="setting-switch-row mb-3">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="notify_unclosed_shifts" name="notify_unclosed_shifts" value="1" {{ old('notify_unclosed_shifts', $automation['notify_unclosed_shifts']) ? 'checked' : '' }}>
                <label class="custom-control-label" for="notify_unclosed_shifts">
                  <strong>Shifts left open from previous days</strong>
                  <br><small class="text-muted">Alert when staff shifts were not closed on time.</small>
                </label>
              </div>
            </div>
            <div class="setting-switch-row mb-3">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="notify_opening_stock_shortages" name="notify_opening_stock_shortages" value="1" {{ old('notify_opening_stock_shortages', $automation['notify_opening_stock_shortages'] ?? true) ? 'checked' : '' }}>
                <label class="custom-control-label" for="notify_opening_stock_shortages">
                  <strong>Opening stock shortages on open shifts</strong>
                  <br><small class="text-muted">Alert when staff record items short during shift opening stock check.</small>
                </label>
              </div>
            </div>

            <h6 class="font-weight-bold text-dark mt-4"><i class="fa fa-comment text-success"></i> Staff SMS Notifications</h6>
            <p class="small text-muted mb-3">Send SMS to employees when you manage their accounts. Uses your plan SMS quota. Staff must have a phone number on their profile.</p>
            <div class="setting-switch-row">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="sms_staff_enabled" name="sms_staff_enabled" value="1" {{ old('sms_staff_enabled', $automation['sms_staff_enabled'] ?? true) ? 'checked' : '' }}>
                <label class="custom-control-label" for="sms_staff_enabled">
                  <strong>Enable staff SMS</strong>
                  <br><small class="text-muted">Master switch for all staff account SMS below.</small>
                </label>
              </div>
            </div>
            <div class="setting-switch-row mb-2">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="email_staff_enabled" name="email_staff_enabled" value="1" {{ old('email_staff_enabled', $automation['email_staff_enabled'] ?? true) ? 'checked' : '' }}>
                <label class="custom-control-label" for="email_staff_enabled">
                  <strong>Enable staff email</strong>
                  <br><small class="text-muted">Send the same staff notification text by email when the employee has an email address.</small>
                </label>
              </div>
            </div>
            @php
              $staffSmsToggles = [
                'sms_staff_welcome' => ['New employee welcome', 'Login email and password when you register a staff member.'],
                'sms_staff_password_reset' => ['Password reset', 'New password when you reset or change a staff login password.'],
                'sms_staff_activated' => ['Account activated', 'Notice when a deactivated employee is turned back on.'],
                'sms_staff_deactivated' => ['Account deactivated', 'Notice when an employee account is suspended.'],
                'sms_staff_handover_submitted_owner' => ['Handover submitted (owner)', 'Alert owner when staff submits daily reconciliation.'],
                'sms_staff_handover_verified_staff' => ['Handover verified (staff)', 'Notify staff when owner verifies their reconciliation.'],
                'sms_staff_note_reminder' => ['Note reminders', 'SMS when a note reminder time is reached on Notes & Reminders.'],
              ];
            @endphp
            @foreach($staffSmsToggles as $key => [$label, $help])
            <div class="setting-switch-row">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="{{ $key }}" name="{{ $key }}" value="1" {{ old($key, $automation[$key] ?? true) ? 'checked' : '' }}>
                <label class="custom-control-label" for="{{ $key }}">
                  <strong>{{ $label }}</strong>
                  <br><small class="text-muted">{{ $help }}</small>
                </label>
              </div>
            </div>
            @endforeach

            <div class="mt-3 mb-4">
              <a class="btn btn-sm btn-outline-secondary" data-toggle="collapse" href="#staffSmsTemplates" role="button" aria-expanded="false" aria-controls="staffSmsTemplates">
                <i class="fa fa-pencil mr-1"></i> Customize staff SMS message templates
              </a>
              <div class="collapse mt-3" id="staffSmsTemplates">
                <p class="small text-muted mb-3">
                  Edit the text sent for each staff notification. Common placeholders:
                  <code>{business}</code>, <code>{staff_name}</code>, <code>{email}</code>, <code>{password}</code>,
                  <code>{submitter}</code>, <code>{verifier}</code>, <code>{owner}</code>, <code>{date}</code>,
                  <code>{amount}</code>, <code>{money_short}</code>, <code>{money_short_note}</code>,
                  <code>{title}</code>, <code>{when}</code>, <code>{preview}</code>
                </p>
                @php
                  $staffTemplateDefaults = \App\Models\Business::defaultStaffSmsTemplates();
                  $staffTemplateLabels = \App\Models\Business::staffSmsTemplateLabels();
                @endphp
                @foreach($staffTemplateLabels as $templateKey => $templateLabel)
                <div class="form-group mb-3">
                  <label class="small font-weight-bold">{{ $templateLabel }}</label>
                  <textarea name="{{ $templateKey }}" class="form-control form-control-sm" rows="2" maxlength="480">{{ old($templateKey, $automation[$templateKey] ?? $staffTemplateDefaults[$templateKey]) }}</textarea>
                </div>
                @endforeach
              </div>
            </div>

            <button type="submit" class="btn btn-primary settings-save-btn" style="background-color:#940000;border-color:#940000;">
              <i class="fa fa-save"></i> Save Automation Settings
            </button>
          </form>
        </div>
        @endif

        {{-- SALES SHIFTS --}}
        @php
          $shiftOpenMode = old('shift_open_mode', $automation['shift_open_mode'] ?? 'anytime');
          $shiftOpenDays = old('shift_open_days', $automation['shift_open_days'] ?? [0,1,2,3,4,5,6]);
          $weekdays = [
            1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
            5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday',
          ];
        @endphp
        <div class="tab-pane fade {{ $activeTab === 'shifts' ? 'show active' : '' }}" id="tab-shifts">
          <h5 class="mb-1 text-muted"><i class="fa fa-clock-o"></i> Sales Shift Rules</h5>
          <p class="small text-muted mb-4">Control when staff can open a shift and how long a shift may stay open before it must be closed.</p>

          <form method="POST" action="{{ route('settings.shift-rules.update') }}" class="settings-form" id="shiftRulesForm">
            @csrf @method('PUT')

            <h6 class="font-weight-bold text-dark mt-2"><i class="fa fa-sign-in text-primary"></i> When can staff open a shift?</h6>
            <div class="setting-switch-row">
              <div class="custom-control custom-radio mb-2">
                <input type="radio" id="shift_open_anytime" name="shift_open_mode" value="anytime" class="custom-control-input shift-open-mode" {{ $shiftOpenMode === 'anytime' ? 'checked' : '' }}>
                <label class="custom-control-label" for="shift_open_anytime">
                  <strong>Any time</strong>
                  <br><small class="text-muted">Staff can open a shift whenever they start work.</small>
                </label>
              </div>
              <div class="custom-control custom-radio">
                <input type="radio" id="shift_open_scheduled" name="shift_open_mode" value="scheduled" class="custom-control-input shift-open-mode" {{ $shiftOpenMode === 'scheduled' ? 'checked' : '' }}>
                <label class="custom-control-label" for="shift_open_scheduled">
                  <strong>Scheduled window only</strong>
                  <br><small class="text-muted">Restrict opening to specific days and hours (e.g. Mon–Sat, 8:00 AM – 6:00 PM).</small>
                </label>
              </div>
            </div>

            <div id="shiftScheduledFields" class="border rounded p-3 mb-4 bg-light" style="{{ $shiftOpenMode === 'scheduled' ? '' : 'display:none;' }}">
              <div class="row mb-3">
                <div class="col-md-4">
                  <label class="small font-weight-bold">Earliest open time</label>
                  <input type="time" name="shift_open_time_from" class="form-control form-control-sm" value="{{ old('shift_open_time_from', $automation['shift_open_time_from'] ?? '06:00') }}">
                </div>
                <div class="col-md-4">
                  <label class="small font-weight-bold">Latest open time</label>
                  <input type="time" name="shift_open_time_to" class="form-control form-control-sm" value="{{ old('shift_open_time_to', $automation['shift_open_time_to'] ?? '22:00') }}">
                </div>
              </div>
              <label class="small font-weight-bold d-block mb-2">Allowed days</label>
              <div class="d-flex flex-wrap">
                @foreach($weekdays as $dayNum => $dayLabel)
                  <div class="custom-control custom-checkbox mr-3 mb-2">
                    <input type="checkbox" class="custom-control-input" id="shift_day_{{ $dayNum }}" name="shift_open_days[]" value="{{ $dayNum }}" {{ in_array($dayNum, (array) $shiftOpenDays, true) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="shift_day_{{ $dayNum }}">{{ $dayLabel }}</label>
                  </div>
                @endforeach
              </div>
            </div>

            <h6 class="font-weight-bold text-dark mt-4"><i class="fa fa-sign-out text-danger"></i> When must a shift be closed?</h6>
            <p class="small text-muted">A shift must be ended and handed over within this period after it was opened.</p>
            <div class="row mb-3">
              <div class="col-md-3">
                <label class="small font-weight-bold">Maximum open period</label>
                <input type="number" name="shift_max_open_duration" class="form-control form-control-sm" min="1" max="365" value="{{ old('shift_max_open_duration', $automation['shift_max_open_duration'] ?? 1) }}" required>
              </div>
              <div class="col-md-3">
                <label class="small font-weight-bold">Unit</label>
                <select name="shift_max_open_unit" class="form-control form-control-sm">
                  <option value="days" {{ old('shift_max_open_unit', $automation['shift_max_open_unit'] ?? 'days') === 'days' ? 'selected' : '' }}>Day(s)</option>
                  <option value="weeks" {{ old('shift_max_open_unit', $automation['shift_max_open_unit'] ?? 'days') === 'weeks' ? 'selected' : '' }}>Week(s)</option>
                </select>
              </div>
            </div>
            <div class="setting-switch-row mb-3">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="shift_enforce_max_duration" name="shift_enforce_max_duration" value="1" {{ old('shift_enforce_max_duration', $automation['shift_enforce_max_duration'] ?? true) ? 'checked' : '' }}>
                <label class="custom-control-label" for="shift_enforce_max_duration">
                  <strong>Block sales when shift is open too long</strong>
                  <br><small class="text-muted">Staff cannot use POS until they close the shift and submit handover. Owner dashboard will also alert you.</small>
                </label>
              </div>
            </div>

            <div class="alert alert-light border small">
              <i class="fa fa-lightbulb-o text-warning"></i>
              Example: set <strong>1 day</strong> so each shift must close by end of business day, or <strong>1 week</strong> for weekly reconciliation cycles.
            </div>

            <button type="submit" class="btn btn-primary settings-save-btn" style="background-color:#940000;border-color:#940000;">
              <i class="fa fa-save"></i> Save Shift Rules
            </button>
          </form>
        </div>

        {{-- SUBSCRIPTION --}}
        <div class="tab-pane fade {{ $activeTab === 'subscription' ? 'show active' : '' }}" id="tab-subscription">
          <h5 class="mb-3 text-muted"><i class="fa fa-credit-card"></i> Plan &amp; Subscription</h5>

          @include('partials.subscription-billing', ['overview' => $billingOverview, 'business' => $business])

          <table class="table table-bordered mb-4">
            <tr><th style="width:38%;">Account Status</th><td>
              @if($business->is_active)
                <span class="badge badge-success">{{ __('tables.status.active') }}</span>
              @else
                <span class="badge badge-danger">Suspended</span>
              @endif
            </td></tr>
            <tr><th>Staff Limit</th><td>{{ $business->plan->max_users ?? 'Unlimited' }}</td></tr>
            <tr><th>Business Types Limit</th><td>{{ ($business->plan->max_business_types ?? 1) === 0 ? 'Unlimited' : ($business->plan->max_business_types ?? 1) }}</td></tr>
            <tr><th>Business Types Used</th><td>{{ $business->categoryBusinessTypesUsed() }}</td></tr>
            <tr><th>Branch Limit</th><td>{{ $business->branchesLimitLabel() }}</td></tr>
            <tr><th>Branches Registered</th><td>{{ $business->branches()->count() }}</td></tr>
          </table>

          <p class="small text-muted">Plan changes and renewals are managed by the platform administrator. Use the payment instructions above, then contact support once paid.</p>
          <a href="{{ route('tickets.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fa fa-question-circle"></i> Contact Support</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-3">
    <div class="tile">
      <h3 class="tile-title">Quick Links</h3>
      <div class="tile-body list-group list-group-flush">
        <a href="{{ route('petty-cash.index') }}" class="list-group-item list-group-item-action"><i class="fa fa-money text-primary"></i> Petty Cash</a>
        <a href="{{ route('owner-reports.index') }}" class="list-group-item list-group-item-action"><i class="fa fa-list-alt text-primary"></i> Master Sheet</a>
        <a href="{{ route('debts.index') }}" class="list-group-item list-group-item-action"><i class="fa fa-credit-card text-danger"></i> Debt Management</a>
        <a href="{{ route('employees.index') }}" class="list-group-item list-group-item-action"><i class="fa fa-users text-info"></i> Staff Management</a>
      </div>
    </div>

    <div class="tile">
      <h3 class="tile-title">Settings Guide</h3>
      <div class="tile-body small text-muted">
        <p><strong>Profile</strong> — shop name, contact details, logo, TIN/VAT — all shown on invoices.</p>
        <p><strong>Finance</strong> — how staff reconciliation expenses affect circulation vs profit.</p>
        <p><strong>Payment Methods</strong> — payment options with a pay number per platform (M-Pesa, CRDB, etc.).</p>
        <p><strong>Automation</strong> — dashboard reminders you want as business owner.</p>
        <p><strong>Sales Shifts</strong> — when staff can open shifts and how long they may stay open.</p>
        <p><strong>Subscription</strong> — your plan, monthly fee, invoices, and renewal amount.</p>
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
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Saving...');
  });

  @if($activeTab !== 'profile')
  $('a[href="#tab-{{ $activeTab }}"]').tab('show');
  @endif

  $('.shift-open-mode').on('change', function () {
    const scheduled = $('#shift_open_scheduled').is(':checked');
    $('#shiftScheduledFields').toggle(scheduled);
  });

  $('#debt_reminder_frequency').on('change', function () {
    $('#debtSecondReminderField').toggle($(this).val() === 'twice');
  });

  let providerRowIndex = 1000;

  function reindexProviderRows($table) {
    const method = $table.data('method');
    $table.find('tbody tr').each(function(index) {
      $(this).find('input').each(function() {
        const name = $(this).attr('name') || '';
        let field = 'name';
        if (name.indexOf('[pay_number]') !== -1) field = 'pay_number';
        if (name.indexOf('[account_name]') !== -1) field = 'account_name';
        $(this).attr('name', 'methods[' + method + '][accounts][' + index + '][' + field + ']');
      });
    });
  }

  $('.add-provider-row').on('click', function() {
    const method = $(this).data('method');
    const $table = $('.provider-accounts-table[data-method="' + method + '"]');
    const idx = providerRowIndex++;
    const payLabel = $table.data('pay-label');
    const nameLabel = $table.data('name-label');
    const platformLabel = $table.data('platform-label');
    const placeholder = method === 'bank' ? 'CRDB' : 'M-Pesa';
    const $row = $('<tr>' +
      '<td><input type="text" name="methods[' + method + '][accounts][' + idx + '][name]" class="form-control form-control-sm" placeholder="' + placeholder + '"></td>' +
      '<td><input type="text" name="methods[' + method + '][accounts][' + idx + '][pay_number]" class="form-control form-control-sm"></td>' +
      '<td><input type="text" name="methods[' + method + '][accounts][' + idx + '][account_name]" class="form-control form-control-sm"></td>' +
      '<td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-provider-row" title="Remove">&times;</button></td>' +
      '</tr>');
    $table.find('tbody').append($row);
    reindexProviderRows($table);
  });

  $(document).on('click', '.remove-provider-row', function() {
    const $table = $(this).closest('.provider-accounts-table');
    if ($table.find('tbody tr').length <= 1) {
      $(this).closest('tr').find('input').val('');
      return;
    }
    $(this).closest('tr').remove();
    reindexProviderRows($table);
  });
});
</script>
@endsection

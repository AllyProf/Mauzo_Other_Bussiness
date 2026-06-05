@extends('layouts.app')

@section('title', 'Sales Targets')

@section('styles')
<style>
  .target-progress-track { height: 10px; background: #eaecf4; border-radius: 10px; }
  .target-progress-fill { height: 10px; background: #940000; border-radius: 10px; }
  .period-pill { font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px; }
  tr.target-editing { background: #fff8f8 !important; }
</style>
@endsection

@section('content')
@php
  $editing = isset($editTarget) && $editTarget;
  $formPeriodType = old('period_type', $editing ? $editTarget->period_type : 'monthly');
  $formPeriodDate = old('period_date', $editing ? $editTarget->period_start->format('Y-m-d') : now()->format('Y-m-d'));
  $formPeriodMonth = old('period_date')
    ? \Carbon\Carbon::parse(old('period_date'))->format('Y-m')
    : ($editing ? $editTarget->period_start->format('Y-m') : now()->format('Y-m'));
  $formAmount = old('target_amount', $editing ? (int) $editTarget->target_amount : '');
  $formBranch = old('branch_id', $editing ? $editTarget->branch_id : '');
  $formDept = old('business_type_key', $editing ? $editTarget->business_type_key : '');
  $formStaff = old('user_id', $editing ? $editTarget->user_id : '');
  $formNotes = old('notes', $editing ? $editTarget->notes : '');
@endphp

<div class="app-title">
  <div>
    <h1><i class="fa fa-bullseye"></i> Sales Targets</h1>
    <p>Set daily, weekly, or monthly revenue goals by branch, department, or staff member.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ url('/home') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Sales Targets</li>
  </ul>
</div>

@include('partials.branch-business-filters', ['filterHint' => 'Targets list respects the active branch and business tab.'])

<div class="row">
  <div class="col-lg-4">
    <div class="tile">
      <h3 class="tile-title">{{ $editing ? 'Edit Target' : 'Set Target' }}</h3>
      @if($editing)
        <div class="alert alert-light border py-2 px-3 mb-3 small">
          <i class="fa fa-pencil text-primary"></i> Editing
          <strong>{{ strtoupper($editTarget->period_type) }}</strong> · {{ $editTarget->periodLabel() }}
        </div>
      @endif
      <form action="{{ $editing ? route('sales-targets.update', $editTarget) : route('sales-targets.store') }}" method="POST" id="targetForm">
        @csrf
        @if($editing)
          @method('PUT')
        @endif
        <div class="form-group">
          <label class="control-label font-weight-bold">Period Type <span class="text-danger">*</span></label>
          <select name="period_type" id="periodType" class="form-control" required>
            <option value="daily" @selected($formPeriodType === 'daily')>Daily</option>
            <option value="weekly" @selected($formPeriodType === 'weekly')>Weekly</option>
            <option value="monthly" @selected($formPeriodType === 'monthly')>Monthly</option>
          </select>
        </div>
        <div class="form-group">
          <label class="control-label font-weight-bold" id="periodDateLabel">Target Month <span class="text-danger">*</span></label>
          <input type="date" name="period_date" id="periodDateDaily" class="form-control period-date-input" value="{{ $formPeriodDate }}">
          <input type="month" id="periodDateMonthly" class="form-control period-date-input d-none" value="{{ $formPeriodMonth }}">
        </div>
        <div class="form-group">
          <label class="control-label font-weight-bold">Target Amount (TZS) <span class="text-danger">*</span></label>
          <input type="number" name="target_amount" class="form-control" min="1" step="1" required value="{{ $formAmount }}" placeholder="e.g. 500000">
        </div>

        <hr>
        <h6 class="text-muted text-uppercase mb-3"><i class="fa fa-filter"></i> Scope (optional)</h6>

        @if($branches->count() > 0)
        <div class="form-group">
          <label class="control-label font-weight-bold">Branch</label>
          <select name="branch_id" class="form-control">
            <option value="">All branches</option>
            @foreach($branches as $branch)
              <option value="{{ $branch->id }}" @selected((string) $formBranch === (string) $branch->id)>{{ $branch->name }}</option>
            @endforeach
          </select>
        </div>
        @endif

        @if(count($businessTypes) > 0)
        <div class="form-group">
          <label class="control-label font-weight-bold">Department</label>
          <select name="business_type_key" class="form-control">
            <option value="">All departments</option>
            @foreach($businessTypes as $type)
              <option value="{{ $type['key'] }}" @selected($formDept === $type['key'])>{{ $type['label'] }}</option>
            @endforeach
            <option value="other" @selected($formDept === 'other')>Other</option>
          </select>
          <small class="text-muted">Use when your shop runs multiple business types.</small>
        </div>
        @endif

        <div class="form-group">
          <label class="control-label font-weight-bold">Staff Member</label>
          <select name="user_id" id="targetStaffSelect" class="form-control">
            <option value="">All staff</option>
            @foreach($staff as $member)
              <option value="{{ $member->id }}" data-branch-id="{{ $member->branch_id ?? '' }}" @selected((string) $formStaff === (string) $member->id)>{{ $member->name }}</option>
            @endforeach
          </select>
          <small class="text-muted">Assigned staff always see their goal on their dashboard.</small>
        </div>

        <div class="form-group">
          <label class="control-label font-weight-bold">Notes</label>
          <input type="text" name="notes" class="form-control" maxlength="255" value="{{ $formNotes }}" placeholder="Optional note">
        </div>

        <button type="submit" class="btn btn-primary btn-block">
          <i class="fa fa-save"></i> {{ $editing ? 'Update Target' : 'Save Target' }}
        </button>
        @if($editing)
          <a href="{{ route('sales-targets.index') }}" class="btn btn-light btn-block mt-2">Cancel</a>
        @endif
      </form>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="tile">
      <h3 class="tile-title">Configured Targets</h3>
      <div class="table-responsive">
        <table class="table table-hover table-bordered mb-0">
          <thead style="background:#940000;color:#fff;">
            <tr>
              <th>{{ __('tables.columns.period') }}</th>
              <th>{{ __('tables.columns.scope') }}</th>
              <th class="text-right">Target</th>
              <th class="text-right">Actual</th>
              <th>{{ __('tables.columns.progress') }}</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse($targets as $row)
              @php $target = $row['model']; @endphp
              <tr class="{{ $editing && $editTarget->id === $target->id ? 'target-editing' : '' }}">
                <td>
                  <span class="badge badge-light period-pill">{{ strtoupper($target->period_type) }}</span>
                  <div class="small mt-1">{{ $target->periodLabel() }}</div>
                </td>
                <td class="small">{{ $row['scope_label'] }}</td>
                <td class="text-right font-weight-bold">{{ money($target->target_amount) }}</td>
                <td class="text-right">{{ money($row['actual']) }}</td>
                <td style="min-width:140px;">
                  <div class="d-flex justify-content-between small mb-1">
                    <span>{{ $row['progress'] }}%</span>
                  </div>
                  <div class="target-progress-track">
                    <div class="target-progress-fill" style="width:{{ $row['progress'] }}%;"></div>
                  </div>
                </td>
                <td class="text-center text-nowrap">
                  <a href="{{ route('sales-targets.index', ['edit' => $target->id]) }}" class="btn btn-sm btn-outline-primary" title="{{ __('tables.actions.edit') }}">
                    <i class="fa fa-pencil"></i>
                  </a>
                  <form action="{{ route('sales-targets.destroy', $target) }}" method="POST" class="d-inline">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="confirmAction(event, 'Remove target?', 'This target will be deleted.')" title="{{ __('tables.actions.delete') }}">
                      <i class="fa fa-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center text-muted py-4">No targets configured yet. Use the form to set your first goal.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if($targets->hasPages())
        <div class="mt-3">{{ $targets->links() }}</div>
      @endif
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
  const periodType = document.getElementById('periodType');
  const dailyInput = document.getElementById('periodDateDaily');
  const monthInput = document.getElementById('periodDateMonthly');
  const dateLabel = document.getElementById('periodDateLabel');
  const form = document.getElementById('targetForm');

  function syncPeriodInput() {
    const type = periodType.value;
    if (type === 'monthly') {
      dailyInput.classList.add('d-none');
      dailyInput.removeAttribute('name');
      monthInput.classList.remove('d-none');
      monthInput.setAttribute('name', 'period_date');
      dateLabel.innerHTML = 'Target Month <span class="text-danger">*</span>';
    } else {
      monthInput.classList.add('d-none');
      monthInput.removeAttribute('name');
      dailyInput.classList.remove('d-none');
      dailyInput.setAttribute('name', 'period_date');
      dateLabel.innerHTML = type === 'weekly'
        ? 'Week (pick any day in the week) <span class="text-danger">*</span>'
        : 'Target Day <span class="text-danger">*</span>';
    }
  }

  form.addEventListener('submit', function() {
    if (periodType.value === 'monthly' && monthInput.value) {
      dailyInput.value = monthInput.value + '-01';
      dailyInput.setAttribute('name', 'period_date');
      monthInput.removeAttribute('name');
      dailyInput.classList.remove('d-none');
      monthInput.classList.add('d-none');
    }
  });

  periodType.addEventListener('change', syncPeriodInput);
  syncPeriodInput();

  const staffSelect = document.getElementById('targetStaffSelect');
  const branchSelect = document.querySelector('select[name="branch_id"]');
  if (staffSelect && branchSelect) {
    staffSelect.addEventListener('change', function() {
      if ({{ $editing ? 'true' : 'false' }}) return;
      const option = staffSelect.options[staffSelect.selectedIndex];
      const branchId = option.getAttribute('data-branch-id');
      if (staffSelect.value && branchId && !branchSelect.value) {
        branchSelect.value = branchId;
      }
    });
  }
})();
</script>
@endsection

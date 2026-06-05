@forelse($staffPulse as $row)
<li class="list-group-item d-flex justify-content-between align-items-center px-0">
  <div>
    <strong>{{ $row->name }}</strong>
    <br><small class="text-muted">{{ trans_choice('live_sales.staff.sales_count', $row->orders, ['count' => $row->orders]) }}</small>
  </div>
  <span class="badge badge-primary badge-pill">{{ money($row->revenue, false) }}</span>
</li>
@empty
<li class="list-group-item text-muted text-center px-0">{{ __('live_sales.staff.empty') }}</li>
@endforelse

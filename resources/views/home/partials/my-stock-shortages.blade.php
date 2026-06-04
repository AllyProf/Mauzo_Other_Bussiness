@php
  $myStockShortages = $myStockShortages ?? collect();
  $myShortageStats = $myShortageStats ?? ['total' => 0, 'pending' => 0, 'will_be_paid' => 0, 'waived' => 0, 'amount_due' => 0];
  $alwaysShowShortageSection = $alwaysShowShortageSection ?? false;
@endphp

@if(($myShortageStats['total'] ?? 0) > 0 || $alwaysShowShortageSection)
<div class="row {{ $alwaysShowShortageSection ? 'mb-3' : 'mt-2' }}">
  <div class="col-md-12">
    @if(($myShortageStats['will_be_paid'] ?? 0) > 0)
      <div class="alert alert-warning mb-3">
        <i class="fa fa-money"></i>
        <strong>You have {{ $myShortageStats['will_be_paid'] }} shortage(s) marked <em>Will be paid</em>.</strong>
        Total amount to pay (at cost): <strong>{{ money($myShortageStats['amount_due'] ?? 0) }}</strong>
      </div>
    @elseif(($myShortageStats['pending'] ?? 0) > 0)
      <div class="alert alert-info mb-3">
        <i class="fa fa-hourglass-half"></i>
        {{ $myShortageStats['pending'] }} stock shortage(s) awaiting owner review.
      </div>
    @endif

    <div class="tile border-left border-warning shadow-sm" style="border-left-width: 4px !important;">
      <h3 class="tile-title text-dark">
        <i class="fa fa-warning text-danger"></i> My Shift Stock Shortages
        <small class="text-muted font-weight-normal">— recorded at shift open/close &amp; owner decisions</small>
      </h3>
      <div class="tile-body p-0">
        @if(($myShortageStats['total'] ?? 0) > 0)
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="thead-light">
              <tr>
                <th>Date</th>
                <th>Shift</th>
                <th>Item</th>
                <th class="text-right">Short By</th>
                <th>Your Reason</th>
                <th>Owner Decision</th>
                <th class="text-right">Amount</th>
              </tr>
            </thead>
            <tbody>
              @foreach($myStockShortages as $check)
                @php $impact = $check->financial_impact ?? []; @endphp
                <tr class="{{ $check->isWillBePaid() ? 'table-warning' : ($check->isVerified() ? 'table-light' : '') }}">
                  <td nowrap>{{ $check->recorded_at->format('d M, Y h:i A') }}</td>
                  <td>
                    <a href="{{ route('shifts.show', $check->shift) }}">#{{ $check->shift_id }}</a>
                    <br><small class="text-muted">{{ ucfirst($check->check_type) }}</small>
                  </td>
                  <td>
                    <strong>{{ $check->item->name ?? 'Item' }}</strong>
                    @if($check->item?->category)
                      <br><small class="text-muted">{{ $check->item->category->name }}</small>
                    @endif
                  </td>
                  <td class="text-right font-weight-bold text-danger">{{ number_format($check->shortageAmount(), 2) }}</td>
                  <td style="max-width:180px;">{{ $check->notes ?: '—' }}</td>
                  <td>
                    @if($check->isWillBePaid())
                      <span class="badge badge-primary"><i class="fa fa-money"></i> Will be paid</span>
                      @if($check->owner_notes)
                        <br><small class="text-muted">{{ $check->owner_notes }}</small>
                      @endif
                    @elseif($check->isWaived())
                      <span class="badge badge-success"><i class="fa fa-hand-paper-o"></i> Waived</span>
                      @if($check->owner_notes)
                        <br><small class="text-muted">{{ $check->owner_notes }}</small>
                      @endif
                    @elseif($check->isVerified())
                      <span class="badge badge-secondary">Reviewed</span>
                    @else
                      <span class="badge badge-warning">Awaiting review</span>
                    @endif
                    @if($check->isVerified() && $check->verified_at)
                      <br><small class="text-muted">{{ $check->verified_at->format('d M, h:i A') }}</small>
                    @endif
                  </td>
                  <td class="text-right">
                    @if($check->isWillBePaid())
                      <strong>{{ money($impact['cost_value'] ?? 0) }}</strong>
                      <br><small class="text-muted">cost · sell {{ money($impact['revenue_value'] ?? 0, false) }}</small>
                    @elseif($check->isWaived())
                      <span class="text-muted">—</span>
                    @else
                      <span class="text-muted small">Pending</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @else
        <div class="p-4 text-center text-muted">
          <i class="fa fa-check-circle fa-2x mb-2 d-block text-success"></i>
          No shift stock shortages recorded for you yet.
        </div>
        @endif
        <div class="p-3 border-top bg-light small text-muted mb-0">
          <i class="fa fa-info-circle"></i>
          <strong>Will be paid</strong> means your boss expects you to cover the missing stock value.
          <strong>Waived</strong> means no payment is required.
        </div>
      </div>
    </div>
  </div>
</div>
@endif

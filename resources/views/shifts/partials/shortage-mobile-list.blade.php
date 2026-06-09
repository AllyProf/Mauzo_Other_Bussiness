@forelse($shortages as $check)
  @php $impact = $check->financial_impact ?? []; @endphp
  <div class="shortage-mobile-card shortage-row {{ $check->isVerified() ? 'is-reviewed' : 'is-pending' }}"
       data-target="#mobile-shortage-detail-{{ $check->id }}"
       aria-expanded="false">
    <div class="shortage-mobile-head">
      <div>
        <div class="shortage-mobile-item">{{ $check->item->name ?? 'Item' }}</div>
        <div class="shortage-mobile-meta">
          {{ $check->recorded_at->format('d M, Y h:i A') }}
          · <a href="{{ route('shifts.show', $check->shift) }}" class="shortage-no-toggle">Shift #{{ $check->shift_id }}</a>
          · {{ $check->shift->user->name ?? '—' }}
        </div>
      </div>
      <div class="text-right">
        <span class="badge badge-{{ $check->check_type === 'opening' ? 'primary' : 'secondary' }}">{{ ucfirst($check->check_type) }}</span>
        <div class="text-danger font-weight-bold mt-1">−{{ number_format($check->shortageAmount(), 2) }}</div>
      </div>
    </div>
    <div class="shortage-mobile-grid">
      <div class="shortage-mobile-stat"><span>System</span><strong>{{ number_format($check->system_stock, 2) }}</strong></div>
      <div class="shortage-mobile-stat"><span>Counted</span><strong>{{ number_format($check->counted_stock, 2) }}</strong></div>
    </div>
    @if($check->notes || $check->owner_notes)
      <div class="shortage-mobile-notes small text-muted">
        @if($check->notes)<div>{{ $check->notes }}</div>@endif
        @if($check->owner_notes)<div class="text-success"><strong>Owner:</strong> {{ $check->owner_notes }}</div>@endif
      </div>
    @endif
    <div class="shortage-mobile-status">
      @if($check->isVerified())
        @if($check->isWillBePaid())
          <span class="badge badge-primary"><i class="fa fa-money"></i> Will be paid</span>
        @elseif($check->isWaived())
          <span class="badge badge-success"><i class="fa fa-hand-paper-o"></i> Waived</span>
        @else
          <span class="badge badge-success"><i class="fa fa-check"></i> Reviewed</span>
        @endif
        <small class="text-muted ml-1">{{ $check->verified_at->format('d M, h:i A') }}</small>
      @else
        <span class="badge badge-warning">{{ __('tables.status.pending') }}</span>
      @endif
      <span class="shortage-mobile-expand ml-auto"><i class="fa fa-chevron-down"></i></span>
    </div>
    <div class="shortage-mobile-actions shortage-no-toggle">
      @if(! $check->isVerified())
        <form action="{{ route('stock-shortages.verify', $check) }}" method="POST" class="shortage-decision-form w-100 ss-mobile-actions-row">
          @csrf
          <input type="hidden" name="owner_decision" value="">
          <input type="hidden" name="owner_notes" value="">
          <button type="button" class="btn btn-sm btn-primary shortage-decision-btn" data-decision="will_be_paid">
            <i class="fa fa-money"></i> Will Pay
          </button>
          <button type="button" class="btn btn-sm btn-success shortage-decision-btn" data-decision="waived">
            <i class="fa fa-hand-paper-o"></i> Waive
          </button>
        </form>
      @else
        <form action="{{ route('stock-shortages.revert', $check) }}" method="POST" class="shortage-revert-form shortage-no-toggle ml-auto">
          @csrf
          <button type="button" class="btn btn-sm btn-outline-secondary shortage-revert-btn">
            <i class="fa fa-undo"></i> Undo
          </button>
        </form>
      @endif
    </div>
  </div>
  <div id="mobile-shortage-detail-{{ $check->id }}" class="collapse shortage-detail shortage-mobile-detail">
    @include('shifts.partials.shortage-impact-panel', ['check' => $check, 'impact' => $impact])
  </div>
@empty
  <p class="text-center text-muted py-4 mb-0">No stock shortages recorded yet.</p>
@endforelse

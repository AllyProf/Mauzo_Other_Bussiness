@forelse($expenses as $expense)
  @php $isLocked = $expense->report && $expense->report->status === 'finalized'; @endphp
  <div class="pc-mobile-card">
    <div class="pc-mobile-head">
      <div>
        <div class="pc-mobile-date">{{ $expense->expense_date->format('d M, Y') }}</div>
        @if($multiBusiness ?? false)
          <div class="pc-mobile-meta">{{ $expense->business_type_key ? $expense->businessTypeLabel($business) : '—' }}</div>
        @endif
      </div>
      <span class="badge fund-badge-{{ $expense->fund_source ?? 'circulation' }}">{{ $expense->fundSourceLabel() }}</span>
    </div>
    <div class="pc-mobile-desc">{{ $expense->description }}</div>
    <div class="pc-mobile-grid">
      <div class="pc-mobile-stat">
        <span>Purpose</span>
        <strong><span class="badge badge-light border">{{ $expense->categoryLabel() }}</span></strong>
      </div>
      <div class="pc-mobile-stat">
        <span>Issued To</span>
        <strong>{{ $expense->issuedTo->name ?? '—' }}</strong>
      </div>
      <div class="pc-mobile-stat">
        <span>Amount</span>
        <strong class="text-danger">TZS {{ number_format($expense->amount, 0) }}</strong>
      </div>
      <div class="pc-mobile-stat">
        <span>Recorded By</span>
        <strong>{{ $expense->recorder->name ?? '—' }}</strong>
      </div>
    </div>
    <div class="pc-mobile-actions">
      @if(! $isLocked)
        <form action="{{ route('petty-cash.destroy', $expense) }}" method="POST" class="delete-petty-cash-form ml-auto">
          @csrf @method('DELETE')
          <button type="button" class="btn btn-sm btn-danger delete-petty-cash-btn" title="Remove"><i class="fa fa-trash"></i> Remove</button>
        </form>
      @else
        <span class="text-muted ml-auto small"><i class="fa fa-lock"></i> Finalized</span>
      @endif
    </div>
  </div>
@empty
  <p class="text-center text-muted py-4 mb-0">No petty cash issued yet{{ ($activeBusinessType ?? false) ? ' for this business' : '' }}.</p>
@endforelse

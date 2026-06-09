@forelse($tableRows as $row)
  <div class="report-mobile-card">
    <div class="report-mobile-head">
      <strong>{{ $row['date_label'] }}</strong>
      <span class="font-weight-bold">{{ money($row['total']) }}</span>
    </div>
    <div class="report-mobile-grid">
      <div class="report-mobile-stat">
        <span>Staff Expenses</span>
        <strong>{{ money($row['staff']) }}</strong>
      </div>
      <div class="report-mobile-stat">
        <span>Owner Expenses</span>
        <strong>{{ money($row['owner']) }}</strong>
      </div>
    </div>
  </div>
@empty
  <p class="text-center text-muted py-4 mb-0">No expenses recorded in this period.</p>
@endforelse

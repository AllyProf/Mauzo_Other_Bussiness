<div class="tile d-print-none mb-3 py-2">
  <form method="GET" action="{{ request()->url() }}" class="row align-items-end report-filter-form">
    @foreach(request()->except(['start_date', 'end_date', 'page']) as $key => $value)
      @if(is_array($value))
        @foreach($value as $item)
          <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
        @endforeach
      @else
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
      @endif
    @endforeach
    <div class="col-12 col-sm-6 col-md-3 form-group">
      <label class="small font-weight-bold mb-1">From</label>
      <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $dateRange['from'] ?? request('start_date') }}">
    </div>
    <div class="col-12 col-sm-6 col-md-3 form-group">
      <label class="small font-weight-bold mb-1">To</label>
      <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $dateRange['to'] ?? request('end_date') }}">
    </div>
    <div class="col-6 col-md-2 form-group">
      <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fa fa-search"></i> Apply</button>
    </div>
    <div class="col-6 col-md-2 form-group">
      <a href="{{ request()->url() }}" class="btn btn-outline-secondary btn-sm btn-block"><i class="fa fa-refresh"></i> Reset</a>
    </div>
    <div class="col-12 col-md-2 form-group">
      <p class="small text-muted mb-0">Default: last 7 days (min. 5)</p>
    </div>
  </form>
</div>

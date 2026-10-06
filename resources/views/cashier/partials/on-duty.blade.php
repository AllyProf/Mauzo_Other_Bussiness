@if(empty($onDuty))
  <p class="text-muted small mb-0"><i class="fa fa-info-circle"></i> No cashier has an open shift right now.</p>
@else
  <div class="row">
    @foreach($onDuty as $cashier)
      <div class="col-sm-6 col-lg-4 mb-2">
        <div class="border rounded p-2 h-100">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <strong>{{ $cashier['name'] }}</strong>
              @if($cashier['branch'])<span class="small text-muted"> · {{ $cashier['branch'] }}</span>@endif
              <div class="small text-muted">Shift #{{ $cashier['shift_id'] }} since {{ $cashier['opened_label'] }}</div>
            </div>
            <strong class="text-success">{{ money($cashier['total']) }}</strong>
          </div>
          <div class="small mt-1">
            {{ $cashier['payments_count'] }} payment(s)
            @if($cashier['last_payment_label']) · last {{ $cashier['last_payment_label'] }}@endif
          </div>
          <div class="small mt-1">
            @if($cashier['collecting_now'])
              <span class="badge badge-warning"><i class="fa fa-lock"></i> Collecting {{ $cashier['collecting_now']['reference_no'] ?? 'an order' }}</span>
            @else
              <span class="badge badge-light">Idle</span>
            @endif
          </div>
        </div>
      </div>
    @endforeach
  </div>
@endif

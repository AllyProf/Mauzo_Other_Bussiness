@forelse($customers as $customer)
  @php $debt = $outstandingByCustomer[$customer->id] ?? 0; @endphp
  <div class="cust-mobile-card {{ ! $customer->is_active ? 'is-inactive' : '' }}">
    <div class="cust-mobile-head">
      <div>
        <div class="cust-mobile-name">{{ $customer->name }}</div>
        <div class="cust-mobile-meta">
          <span><i class="fa fa-phone"></i> {{ $customer->phone }}</span>
          @if($customer->region)
            <span><i class="fa fa-map-marker"></i> {{ $customer->region }}</span>
          @endif
        </div>
      </div>
      <div class="text-right">
        @if($customer->is_active)
          <span class="badge badge-success">{{ __('tables.status.active') }}</span>
        @else
          <span class="badge badge-secondary">{{ __('tables.status.inactive') }}</span>
        @endif
        @if($debt > 0)
          <div class="text-danger font-weight-bold mt-1">{{ money($debt) }}</div>
        @endif
      </div>
    </div>
    @if($customer->email)
      <div class="cust-mobile-email text-muted small mb-2"><i class="fa fa-envelope"></i> {{ $customer->email }}</div>
    @endif
    <div class="cust-mobile-actions">
      <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-primary"><i class="fa fa-eye"></i> {{ __('tables.actions.view') }}</a>
      <a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm btn-info"><i class="fa fa-edit"></i></a>
      <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="d-inline ml-auto">
        @csrf @method('DELETE')
        <button type="button" class="btn btn-sm btn-danger btn-delete-customer" data-name="{{ $customer->name }}"><i class="fa fa-trash"></i></button>
      </form>
    </div>
  </div>
@empty
  <p class="text-center text-muted py-4 mb-0">{{ __('pages.customers.empty') }}</p>
@endforelse

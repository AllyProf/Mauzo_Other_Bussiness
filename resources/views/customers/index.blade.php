@extends('layouts.app')

@section('title', __('pages.customers.title'))

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-users"></i> {{ __('pages.customers.title') }}</h1>
    <p>{{ __('pages.customers.subtitle') }}</p>
  </div>
  @can('manage_customers')
  <a href="{{ route('customers.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> {{ __('pages.customers.register') }}</a>
  @endcan
</div>

@include('partials.branch-business-filters', [
  'filterHint' => 'Outstanding debt reflects sales from the selected branch and business only.',
  'filterNote' => 'debt from this department only',
])

<div class="row mb-3">
  <div class="col-md-4">
    <div class="widget-small primary coloured-icon"><i class="icon fa fa-users fa-3x"></i>
      <div class="info"><h4>{{ __('pages.customers.total_customers') }}</h4><p><b>{{ $stats['total'] }}</b></p></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small success coloured-icon"><i class="icon fa fa-check-circle fa-3x"></i>
      <div class="info"><h4>{{ __('tables.status.active') }}</h4><p><b>{{ $stats['active'] }}</b></p></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small warning coloured-icon"><i class="icon fa fa-credit-card fa-3x"></i>
      <div class="info"><h4>{{ __('pages.customers.with_debt') }}</h4><p><b>{{ $stats['with_debt'] }}</b></p></div>
    </div>
  </div>
</div>

<div class="tile d-print-none mb-3 py-2">
  <form method="GET" action="{{ route('customers.index') }}" class="row align-items-end">
    @if($activeBusinessType ?? false)
      <input type="hidden" name="business_type" value="{{ $activeBusinessType }}">
    @endif
    <div class="col-md-4">
      <label class="small font-weight-bold mb-0">{{ __('tables.filters.search') }}</label>
      <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="{{ __('pages.customers.search_placeholder') }}">
    </div>
    <div class="col-md-3">
      <label class="small font-weight-bold mb-0">{{ __('tables.filters.status') }}</label>
      <select name="status" class="form-control form-control-sm">
        <option value="">{{ __('tables.filters.all') }}</option>
        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('tables.filters.active_only') }}</option>
        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>{{ __('tables.filters.inactive_only') }}</option>
      </select>
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fa fa-search"></i> {{ __('tables.filters.filter') }}</button>
    </div>
    <div class="col-md-2">
      <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm btn-block"><i class="fa fa-refresh"></i> {{ __('tables.filters.reset') }}</a>
    </div>
  </form>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-body">
        <table class="table table-hover table-bordered">
          <thead>
            <tr>
              <th>{{ __('tables.columns.name') }}</th>
              <th>{{ __('tables.columns.phone') }}</th>
              <th>{{ __('tables.columns.email') }}</th>
              <th>{{ __('tables.columns.region') }}</th>
              <th>{{ __('tables.columns.status') }}</th>
              <th class="text-right">{{ __('tables.columns.outstanding') }}</th>
              <th>{{ __('tables.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse($customers as $customer)
            @php $debt = $outstandingByCustomer[$customer->id] ?? 0; @endphp
            <tr class="{{ ! $customer->is_active ? 'table-secondary' : '' }}">
              <td><strong>{{ $customer->name }}</strong></td>
              <td>{{ $customer->phone }}</td>
              <td>{{ $customer->email ?: '—' }}</td>
              <td>{{ $customer->region ? $customer->region : '—' }}</td>
              <td>
                @if($customer->is_active)
                  <span class="badge badge-success">{{ __('tables.status.active') }}</span>
                @else
                  <span class="badge badge-secondary">{{ __('tables.status.inactive') }}</span>
                @endif
              </td>
              <td class="text-right {{ $debt > 0 ? 'text-danger font-weight-bold' : 'text-muted' }}">
                {{ $debt > 0 ? money($debt) : '—' }}
              </td>
              <td style="white-space:nowrap;">
                <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-primary" title="{{ __('tables.actions.view') }}"><i class="fa fa-eye"></i></a>
                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm btn-info" title="{{ __('tables.actions.edit') }}"><i class="fa fa-edit"></i></a>
                <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="d-inline">
                  @csrf @method('DELETE')
                  <button type="button" class="btn btn-sm btn-danger btn-delete-customer" data-name="{{ $customer->name }}" title="{{ __('tables.actions.delete') }}"><i class="fa fa-trash"></i></button>
                </form>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">{{ __('pages.customers.empty') }}</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
$(document).on('click', '.btn-delete-customer', function() {
  const form = $(this).closest('form');
  const name = $(this).data('name');
  Swal.fire({
    title: 'Delete "' + name + '"?',
    text: 'Customers with sales history cannot be deleted.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, delete',
    cancelButtonText: 'Cancel',
  }).then((result) => {
    if (result.isConfirmed) form.submit();
  });
});
</script>
@endsection

@extends('layouts.app')

@section('title', __('pages.customers.title'))

@section('styles')
<style>
  .customers-page .widget-small { min-height: 88px; border-radius: 8px !important; margin-bottom: 12px; }
  .customers-page .widget-small .icon { min-width: 64px !important; padding: 10px !important; font-size: 2rem !important; }
  .customers-page .widget-small .info h4 { font-size: 0.82rem !important; }
  .customers-page .widget-small .info p { font-size: 15px !important; word-break: break-word; }
  .customers-page .cust-title-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
  .customers-page .cust-filter-form .form-group { margin-bottom: 0.75rem; }
  .customers-page .cust-mobile-card {
    border: 1px solid #dee2e6; border-radius: 8px; padding: 12px 14px; margin-bottom: 10px; background: #fff;
  }
  .customers-page .cust-mobile-card.is-inactive { background: #f8f9fa; opacity: 0.92; }
  .customers-page .cust-mobile-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 8px; }
  .customers-page .cust-mobile-name { font-weight: 700; color: #940000; font-size: 0.95rem; line-height: 1.35; }
  .customers-page .cust-mobile-meta { display: flex; flex-direction: column; gap: 2px; font-size: 0.82rem; color: #6c757d; margin-top: 4px; }
  .customers-page .cust-mobile-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding-top: 8px; border-top: 1px solid #eee; }

  @media (max-width: 991.98px) {
    .customers-page .app-title h1 { font-size: 1.35rem; line-height: 1.35; }
    .customers-page .app-title p { font-size: 0.88rem; }
    .customers-page .business-type-tabs { padding-bottom: 4px; -webkit-overflow-scrolling: touch; }
  }

  @media (max-width: 767.98px) {
    .customers-page .app-title { flex-direction: column; align-items: flex-start !important; }
    .customers-page .app-title h1 { font-size: 1.15rem; }
    .customers-page .app-title p { font-size: 0.82rem; }
    .customers-page .cust-title-actions { width: 100%; }
    .customers-page .cust-title-actions .btn { width: 100%; text-align: center; }
    .customers-page .widget-small .icon { min-width: 52px !important; font-size: 1.5rem !important; }
  }
</style>
@endsection

@section('content')
<div class="customers-page">
<div class="app-title">
  <div>
    <h1><i class="fa fa-users"></i> {{ __('pages.customers.title') }}</h1>
    <p>{{ __('pages.customers.subtitle') }}</p>
    @can('manage_customers')
    <div class="cust-title-actions d-print-none">
      <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> {{ __('pages.customers.register') }}</a>
    </div>
    @endcan
  </div>
</div>

@include('partials.branch-business-filters', [
  'filterHint' => 'Outstanding debt reflects sales from the selected branch and business only.',
  'filterNote' => 'debt from this department only',
])

<div class="row mb-3">
  <div class="col-12 col-sm-4">
    <div class="widget-small primary coloured-icon"><i class="icon fa fa-users fa-3x"></i>
      <div class="info"><h4>{{ __('pages.customers.total_customers') }}</h4><p><b>{{ $stats['total'] }}</b></p></div>
    </div>
  </div>
  <div class="col-12 col-sm-4">
    <div class="widget-small success coloured-icon"><i class="icon fa fa-check-circle fa-3x"></i>
      <div class="info"><h4>{{ __('tables.status.active') }}</h4><p><b>{{ $stats['active'] }}</b></p></div>
    </div>
  </div>
  <div class="col-12 col-sm-4">
    <div class="widget-small warning coloured-icon"><i class="icon fa fa-credit-card fa-3x"></i>
      <div class="info"><h4>{{ __('pages.customers.with_debt') }}</h4><p><b>{{ $stats['with_debt'] }}</b></p></div>
    </div>
  </div>
</div>

<div class="tile d-print-none mb-3 py-2">
  <form method="GET" action="{{ route('customers.index') }}" class="row align-items-end cust-filter-form">
    @if($activeBusinessType ?? false)
      <input type="hidden" name="business_type" value="{{ $activeBusinessType }}">
    @endif
    <div class="col-12 col-md-4 form-group">
      <label class="small font-weight-bold mb-0">{{ __('tables.filters.search') }}</label>
      <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="{{ __('pages.customers.search_placeholder') }}">
    </div>
    <div class="col-12 col-sm-6 col-md-3 form-group">
      <label class="small font-weight-bold mb-0">{{ __('tables.filters.status') }}</label>
      <select name="status" class="form-control form-control-sm">
        <option value="">{{ __('tables.filters.all') }}</option>
        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('tables.filters.active_only') }}</option>
        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>{{ __('tables.filters.inactive_only') }}</option>
      </select>
    </div>
    <div class="col-6 col-md-2 form-group">
      <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fa fa-search"></i> {{ __('tables.filters.filter') }}</button>
    </div>
    <div class="col-6 col-md-2 form-group">
      <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm btn-block"><i class="fa fa-refresh"></i> {{ __('tables.filters.reset') }}</a>
    </div>
  </form>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-body">
        <div class="d-lg-none mb-3">
          @include('customers.partials.customer-mobile-list', [
            'customers' => $customers,
            'outstandingByCustomer' => $outstandingByCustomer,
          ])
        </div>
        <div class="table-responsive d-none d-lg-block">
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

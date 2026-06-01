@extends('layouts.app')

@section('title', 'Customers')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-users"></i> Customer Management</h1>
    <p>Register and manage your customers for sales and credit tracking</p>
  </div>
  @can('manage_customers')
  <a href="{{ route('customers.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Register Customer</a>
  @endcan
</div>

<div class="row mb-3">
  <div class="col-md-4">
    <div class="widget-small primary coloured-icon"><i class="icon fa fa-users fa-3x"></i>
      <div class="info"><h4>Total Customers</h4><p><b>{{ $stats['total'] }}</b></p></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small success coloured-icon"><i class="icon fa fa-check-circle fa-3x"></i>
      <div class="info"><h4>Active</h4><p><b>{{ $stats['active'] }}</b></p></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="widget-small warning coloured-icon"><i class="icon fa fa-credit-card fa-3x"></i>
      <div class="info"><h4>With Outstanding Debt</h4><p><b>{{ $stats['with_debt'] }}</b></p></div>
    </div>
  </div>
</div>

<div class="tile d-print-none mb-3 py-2">
  <form method="GET" action="{{ route('customers.index') }}" class="row align-items-end">
    <div class="col-md-4">
      <label class="small font-weight-bold mb-0">Search</label>
      <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Name, phone, or email">
    </div>
    <div class="col-md-3">
      <label class="small font-weight-bold mb-0">Status</label>
      <select name="status" class="form-control form-control-sm">
        <option value="">All</option>
        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active only</option>
        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive only</option>
      </select>
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fa fa-search"></i> Filter</button>
    </div>
    <div class="col-md-2">
      <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm btn-block"><i class="fa fa-refresh"></i> Reset</a>
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
              <th>Name</th>
              <th>Phone</th>
              <th>Email</th>
              <th>Region</th>
              <th>Status</th>
              <th class="text-right">Outstanding</th>
              <th>Actions</th>
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
                  <span class="badge badge-success">Active</span>
                @else
                  <span class="badge badge-secondary">Inactive</span>
                @endif
              </td>
              <td class="text-right {{ $debt > 0 ? 'text-danger font-weight-bold' : 'text-muted' }}">
                {{ $debt > 0 ? money($debt) : '—' }}
              </td>
              <td style="white-space:nowrap;">
                <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-primary" title="View"><i class="fa fa-eye"></i></a>
                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm btn-info" title="Edit"><i class="fa fa-edit"></i></a>
                <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="d-inline">
                  @csrf @method('DELETE')
                  <button type="button" class="btn btn-sm btn-danger btn-delete-customer" data-name="{{ $customer->name }}" title="Delete"><i class="fa fa-trash"></i></button>
                </form>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">No customers registered yet. Click <strong>Register Customer</strong> to add your first customer.</td>
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

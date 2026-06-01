@extends('layouts.app')

@section('title', 'Items List - SpareParts POS')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-th-list"></i> Items Inventory</h1>
    <p>List of all registered spare parts</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="#">Items</a></li>
  </ul>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-title-w-btn">
        <h3 class="title">All Items</h3>
        <div class="d-flex align-items-center">
            @php
                $business = Auth::user()->business;
                $maxItems = $business->plan->max_items ?? 0;
                $currentItems = $items->count();
                $percentage = $maxItems > 0 ? min(100, ($currentItems / $maxItems) * 100) : 0;
                $progressColor = $percentage >= 90 ? 'danger' : ($percentage >= 70 ? 'warning' : 'success');
            @endphp
            @if($maxItems > 0)
                <div class="mr-4" style="width: 200px;">
                    <small>Plan Usage: <strong>{{ $currentItems }}/{{ $maxItems }}</strong> items</small>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-{{ $progressColor }}" role="progressbar" style="width: {{ $percentage }}%" aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            @endif
            @can('add_items')
            <p><a class="btn btn-primary icon-btn {{ ($maxItems > 0 && $currentItems >= $maxItems) ? 'disabled' : '' }}" href="{{ route('items.create') }}">
                <i class="fa fa-plus"></i> Add Item
            </a></p>
            @endcan
        </div>
      </div>
      <div class="tile-body">
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
        
        <table class="table table-hover table-bordered" id="sampleTable">
          <thead>
            <tr>
              <th>Name</th>
              <th>SKU</th>
              <th>Category</th>
              <th>Brand</th>
              <th>Packaging (Units)</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($items as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->sku }}</td>
                    <td>{{ $item->category->name ?? 'N/A' }}</td>
                    <td>{{ $item->brand }}</td>
                    <td>
                        @foreach($item->packagings as $pkg)
                            <span class="badge badge-info">{{ $pkg->packagingType->name }} ({{ $pkg->quantity_per_unit }} per unit)</span>
                        @endforeach
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="{{ route('items.show', $item->id) }}" class="btn btn-sm btn-primary" title="View Details"><i class="fa fa-eye"></i> View</a>
                            @can('edit_items')
                            <a href="{{ route('items.edit', $item->id) }}" class="btn btn-sm btn-info" title="Edit Item"><i class="fa fa-edit"></i></a>
                            @endcan
                            @can('delete_items')
                            <form action="{{ route('items.destroy', $item->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this item? This action cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" title="Delete Item"><i class="fa fa-trash"></i></button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
            @if($items->isEmpty())
                <tr>
                    <td colspan="7" class="text-center">No items registered yet.</td>
                </tr>
            @endif
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
    <script type="text/javascript" src="{{ asset('admin/js/plugins/jquery.dataTables.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('admin/js/plugins/dataTables.bootstrap.min.js') }}"></script>
    <script type="text/javascript">$('#sampleTable').DataTable();</script>
@endsection

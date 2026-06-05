@extends('layouts.app')

@section('title', __('pages.items.title') . ' - SpareParts POS')

@section('styles')
<style>
  .business-type-tabs { display: flex; gap: 6px; overflow-x: auto; flex-wrap: nowrap; flex: 1; min-width: 0; }
  .business-type-tab {
    cursor: pointer; padding: 5px 12px; border-radius: 20px; background: #fff; color: #495057;
    font-size: 11px; white-space: nowrap; border: 1px solid #dee2e6; font-weight: 600;
    transition: all .15s ease; line-height: 1.5;
  }
  .business-type-tab.active { background: #940000; color: #fff; border-color: #940000; }
  .business-type-tab:hover:not(.active) { border-color: #940000; color: #940000; }
  .business-type-tab i { margin-right: 5px; }
</style>
@endsection

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-th-list"></i> {{ __('pages.items.title') }}</h1>
    <p>{{ __('pages.items.subtitle') }}</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="#">Items</a></li>
  </ul>
</div>

@if($multiBusiness ?? false)
<div class="alert alert-light border mb-3 py-2">
  <i class="fa fa-info-circle text-primary"></i>
  <strong>Multi-department shop:</strong> use the tabs below to filter items by business type.
</div>
@endif

@if(!empty($activeBranchName))
<div class="alert alert-info mb-3 py-2">
  <i class="fa fa-map-marker"></i>
  Showing items for <strong>{{ $activeBranchName }}</strong>. Business tabs reflect this branch only.
</div>
@elseif($viewingAllBranches ?? false)
<div class="alert alert-light border mb-3 py-2">
  <i class="fa fa-building"></i>
  Viewing <strong>all branches</strong>. Switch branch in the header to filter items.
</div>
@endif

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-title-w-btn flex-wrap">
        <div class="d-flex align-items-center flex-wrap mb-2 mb-md-0" style="flex: 1; min-width: 0;">
          @if($multiBusiness ?? false)
          <div class="business-type-tabs mr-3" id="businessTypeTabs">
            <button type="button" class="business-type-tab active" data-business-type="all">
              <i class="fa fa-th-large"></i> All
            </button>
            @foreach($businessTypes as $type)
            <button type="button" class="business-type-tab" data-business-type="{{ $type['key'] }}">
              <i class="fa {{ $type['icon'] }}"></i> {{ $type['label'] }}
            </button>
            @endforeach
          </div>
          @else
          <h3 class="title mb-0">All Items</h3>
          @endif
        </div>
        <div class="d-flex align-items-center">
            @php
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
            <p class="mb-0"><a class="btn btn-primary icon-btn {{ ($maxItems > 0 && $currentItems >= $maxItems) ? 'disabled' : '' }}" href="{{ route('items.create') }}">
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
              <th>{{ __('tables.columns.name') }}</th>
              <th>{{ __('tables.columns.sku') }}</th>
              <th>{{ __('tables.columns.category') }}</th>
              <th>{{ __('tables.columns.brand') }}</th>
              <th>{{ __('tables.columns.packaging') }}</th>
              <th>{{ __('tables.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($items as $item)
                @php
                  $businessTypeKey = $item->category?->source_business_type_key ?: 'other';
                @endphp
                <tr data-business-type="{{ $businessTypeKey }}">
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
                <tr class="empty-row">
                    <td colspan="6" class="text-center">No items registered yet.</td>
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
    <script type="text/javascript" src="{{ asset('panel-assets/js/plugins/jquery.dataTables.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('panel-assets/js/plugins/dataTables.bootstrap.min.js') }}"></script>
    <script type="text/javascript">
    $(function () {
        const hasMultipleBusinessTypes = @json($multiBusiness ?? false);
        let activeBusinessType = 'all';

        const table = $('#sampleTable').DataTable({
            order: [[0, 'asc']],
        });

        if (hasMultipleBusinessTypes) {
            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                if (settings.nTable.id !== 'sampleTable') {
                    return true;
                }

                if (activeBusinessType === 'all') {
                    return true;
                }

                const row = table.row(dataIndex).node();

                return String($(row).attr('data-business-type')) === String(activeBusinessType);
            });

            $('#businessTypeTabs .business-type-tab').on('click', function () {
                $('#businessTypeTabs .business-type-tab').removeClass('active');
                $(this).addClass('active');
                activeBusinessType = String($(this).attr('data-business-type') || 'all');
                table.draw();
            });
        }
    });
    </script>
@endsection

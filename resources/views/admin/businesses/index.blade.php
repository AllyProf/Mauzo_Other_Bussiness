@extends('layouts.app')

@section('title', 'All Businesses - Software Owner')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-building"></i> Business Management</h1>
    <p>Manage all registered tenants and their subscriptions. To set <strong>fixed</strong> or <strong>profit share</strong> billing per business, click Edit and use the Revenue Collection section.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="#">Businesses</a></li>
  </ul>
</div>

<div class="row">
  <div class="col-md-12">
    @if($pendingRegistrations->isNotEmpty())
    <div class="tile mb-4" style="border-left: 4px solid #f39c12;">
      <h3 class="tile-title text-warning"><i class="fa fa-hourglass-half"></i> Pending Registrations ({{ $pendingRegistrations->count() }})</h3>
      <p class="text-muted mb-3">Review new sign-ups from the public registration page. Approve to start their free trial, or reject to remove the request.</p>
      <div class="table-responsive">
        <table class="table table-hover table-bordered mb-0">
          <thead>
            <tr>
              <th>{{ __('tables.columns.business') }}</th>
              <th>Owner</th>
              <th>{{ __('tables.columns.phone') }}</th>
              <th>{{ __('tables.columns.region') }}</th>
              <th>Business Type</th>
              <th>Registered</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($pendingRegistrations as $business)
            <tr>
              <td><strong>{{ $business->name }}</strong></td>
              <td>{{ $business->contact_person ?? $business->ownerUser?->name ?? '—' }}</td>
              <td>{{ $business->phone ?? '—' }}</td>
              <td>{{ $business->region ?? '—' }}<br><small class="text-muted">{{ $business->district ?? '' }}</small></td>
              <td>{{ collect($business->categoryBusinessTypesList())->first()['label'] ?? '—' }}</td>
              <td>{{ $business->created_at->format('M d, Y h:i A') }}</td>
              <td class="text-center text-nowrap">
                <a href="{{ route('admin.businesses.edit', $business->id) }}" class="btn btn-info btn-sm mr-1" title="View details"><i class="fa fa-eye"></i></a>
                <form action="{{ route('admin.businesses.approve', $business->id) }}" method="POST" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-success btn-sm mr-1" title="Approve & activate" onclick="confirmAction(event, 'Approve registration?', 'This will activate the account and start their free trial.')">
                    <i class="fa fa-check"></i> Approve
                  </button>
                </form>
                <form action="{{ route('admin.businesses.reject', $business->id) }}" method="POST" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-danger btn-sm" title="Reject registration" onclick="confirmAction(event, 'Reject registration?', 'This will permanently remove this registration request.')">
                    <i class="fa fa-times"></i>
                  </button>
                </form>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif

    <div class="tile">
      <div class="tile-title-w-btn">
        <h3 class="title">All Businesses</h3>
        <p><a class="btn btn-primary icon-btn" href="{{ route('admin.businesses.create') }}"><i class="fa fa-plus"></i>Register New Business</a></p>
      </div>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover table-bordered mb-0" id="businessTable">
            <thead>
              <tr>
                <th>Business Name</th>
                <th>Current Plan</th>
                <th>{{ __('tables.columns.status') }}</th>
                <th class="text-center" style="min-width: 220px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($businesses as $business)
              @php
                $ownerCount = $business->owner_user_id ? ($ownerBusinessCounts[$business->owner_user_id] ?? 0) : 0;
                $fee = $businessFees[$business->id] ?? null;
              @endphp
              <tr data-business-id="{{ $business->id }}">
                <td>
                  <strong>{{ $business->name }}</strong>
                  @if($business->email)
                  <br><small class="text-muted">{{ $business->email }}</small>
                  @endif
                </td>
                <td><span class="badge badge-info">{{ $business->plan->name ?? 'No Plan' }}</span></td>
                <td>
                  @if($business->pending_approval)
                    <span class="badge badge-warning">Pending Approval</span>
                  @elseif(!$business->is_active)
                    <span class="badge badge-danger">Suspended</span>
                  @elseif($business->expiry_date && \Carbon\Carbon::parse($business->expiry_date)->isPast())
                    <span class="badge badge-danger">Expired</span>
                  @else
                    <span class="badge badge-success">{{ __('tables.status.active') }}</span>
                  @endif
                </td>
                <td class="text-center text-nowrap">
                  <button type="button" class="btn btn-outline-secondary btn-sm btn-view-more mr-1" title="View more details">
                    <i class="fa fa-chevron-down"></i> View more
                  </button>

                  @if($business->pending_approval)
                  <form action="{{ route('admin.businesses.approve', $business->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm mr-1" title="Approve Registration" onclick="confirmAction(event, 'Approve registration?', 'This will activate the account and start their free trial.')">
                      <i class="fa fa-check"></i>
                    </button>
                  </form>
                  <form action="{{ route('admin.businesses.reject', $business->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm mr-1" title="Reject Registration" onclick="confirmAction(event, 'Reject registration?', 'This will permanently remove this registration request.')">
                      <i class="fa fa-times"></i>
                    </button>
                  </form>
                  @else
                  <a href="{{ route('admin.businesses.edit', $business->id) }}" class="btn btn-info btn-sm mr-1" title="Edit Business Details">
                    <i class="fa fa-edit"></i>
                  </a>
                  @endif

                  @if(!$business->pending_approval)
                  <form action="{{ route('admin.businesses.toggle-status', $business->id) }}" method="POST" class="d-inline">
                    @csrf
                    @if($business->is_active)
                      <button type="submit" class="btn btn-danger btn-sm mr-1" title="Suspend Business" onclick="confirmAction(event, 'Suspend Business?', 'This will lock out all staff from this business immediately!')">
                        <i class="fa fa-ban"></i>
                      </button>
                    @else
                      <button type="submit" class="btn btn-success btn-sm mr-1" title="Activate Business" onclick="confirmAction(event, 'Activate Business?', 'This will restore access for all staff members.')">
                        <i class="fa fa-check"></i>
                      </button>
                    @endif
                  </form>
                  @endif

                  @if(!$business->pending_approval)
                  <form action="{{ route('admin.impersonate', $business->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm mr-1" title="Login As Business" onclick="confirmAction(event, 'Impersonate Business?', 'You will be logged in as the owner of this business.')">
                      <i class="fa fa-user-secret"></i>
                    </button>
                  </form>
                  <form action="{{ route('admin.businesses.destroy', $business->id) }}" method="POST" class="d-inline business-delete-form" data-business-name="{{ $business->name }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="confirm_business_name" value="">
                    <button type="button" class="btn btn-outline-danger btn-sm btn-delete-business" title="Delete business permanently">
                      <i class="fa fa-trash"></i>
                    </button>
                  </form>
                  @endif
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        @foreach($businesses as $business)
        @php
          $ownerCount = $business->owner_user_id ? ($ownerBusinessCounts[$business->owner_user_id] ?? 0) : 0;
          $fee = $businessFees[$business->id] ?? null;
        @endphp
        <div id="business-details-{{ $business->id }}" class="d-none business-details-panel">
          <div class="row">
            <div class="col-md-6 col-lg-4 mb-2">
              <strong>Owner</strong><br>
              @if($business->ownerUser)
                {{ $business->ownerUser->name }}
                @if($ownerCount > 1)
                  <span class="badge badge-secondary ml-1">{{ $ownerCount }} businesses</span>
                @endif
              @else
                {{ $business->contact_person ?? '—' }}
              @endif
            </div>
            <div class="col-md-6 col-lg-4 mb-2">
              <strong>Email</strong><br>
              {{ $business->email ?? '—' }}
            </div>
            <div class="col-md-6 col-lg-4 mb-2">
              <strong>Phone</strong><br>
              {{ $business->phone ?? '—' }}
            </div>
            <div class="col-md-6 col-lg-4 mb-2">
              <strong>Billing Model</strong><br>
              {{ $business->billingModelLabel() }}
            </div>
            <div class="col-md-6 col-lg-4 mb-2">
              <strong>Billing Details</strong><br>
              <span class="text-muted">{{ $business->billingSummary() }}</span>
            </div>
            <div class="col-md-6 col-lg-4 mb-2">
              <strong>This Month Fee</strong><br>
              @if($fee)
                TZS {{ number_format($fee['amount'], 0) }}
                <small class="text-muted d-block">{{ $fee['label'] }}</small>
              @else
                —
              @endif
            </div>
            <div class="col-md-6 col-lg-4 mb-2">
              <strong>Expiry Date</strong><br>
              {{ $business->expiry_date ? \Carbon\Carbon::parse($business->expiry_date)->format('M d, Y') : 'N/A' }}
            </div>
            <div class="col-md-6 col-lg-4 mb-2">
              <strong>Registered</strong><br>
              {{ $business->created_at->format('M d, Y') }}
            </div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>
</div>
@endsection

@section('styles')
<style>
  .business-details-child {
    background: #f8f9fa;
    padding: 14px 16px;
    border-left: 3px solid #940000;
  }
  tr.business-row-expanded td {
    border-bottom: none;
  }
</style>
@endsection

@section('scripts')
<script type="text/javascript" src="{{ asset('panel-assets/js/plugins/jquery.dataTables.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('panel-assets/js/plugins/dataTables.bootstrap.min.js') }}"></script>
<script type="text/javascript">
(function () {
  var table = $('#businessTable').DataTable({
    order: [[0, 'asc']],
    columnDefs: [{ orderable: false, targets: 3 }]
  });

  $('#businessTable tbody').on('click', '.btn-delete-business', function (e) {
    e.preventDefault();
    e.stopPropagation();

    var form = $(this).closest('form');
    var expectedName = form.data('business-name');

    Swal.fire({
      title: 'Delete business permanently?',
      html: 'This removes <strong>all</strong> data, staff accounts, and branches for this tenant. Type the exact business name to confirm:<br><strong>' + $('<div>').text(expectedName).html() + '</strong>',
      input: 'text',
      inputPlaceholder: 'Business name',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545',
      confirmButtonText: 'Yes, delete forever',
      cancelButtonText: 'Cancel',
      preConfirm: function (value) {
        if ((value || '').trim() !== expectedName) {
          Swal.showValidationMessage('Name must match exactly.');
        }
      }
    }).then(function (result) {
      if (result.isConfirmed) {
        form.find('input[name="confirm_business_name"]').val(result.value.trim());
        form[0].submit();
      }
    });
  });

  $('#businessTable tbody').on('click', '.btn-view-more', function (e) {
    e.preventDefault();
    e.stopPropagation();

    var btn = $(this);
    var tr = btn.closest('tr');
    var row = table.row(tr);
    var businessId = tr.data('business-id');
    var panel = $('#business-details-' + businessId);

    if (row.child.isShown()) {
      row.child.hide();
      tr.removeClass('business-row-expanded');
      btn.html('<i class="fa fa-chevron-down"></i> View more');
    } else {
      row.child('<div class="business-details-child">' + panel.html() + '</div>').show();
      tr.addClass('business-row-expanded');
      btn.html('<i class="fa fa-chevron-up"></i> View less');
    }
  });
})();
</script>
@endsection

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
              <th>Business</th>
              <th>Owner</th>
              <th>Phone</th>
              <th>Region</th>
              <th>Business Type</th>
              <th>Registered</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($pendingRegistrations as $business)
            <tr>
              <td><strong>{{ $business->name }}</strong></td>
              <td>{{ $business->contact_person ?? $business->owner?->name ?? '—' }}</td>
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
        <table class="table table-hover table-bordered" id="businessTable">
          <thead>
            <tr>
              <th>Business Name</th>
              <th>Email</th>
              <th>Current Plan</th>
              <th>Billing Model</th>
              <th>Billing Details</th>
              <th>This Month Fee</th>
              <th>Expiry Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($businesses as $business)
                <tr>
                    <td>{{ $business->name }}</td>
                    <td>{{ $business->email }}</td>
                    <td><span class="badge badge-info">{{ $business->plan->name ?? 'No Plan' }}</span></td>
                    <td>{{ $business->billingModelLabel() }}</td>
                    <td><small class="text-muted">{{ $business->billingSummary() }}</small></td>
                    <td>
                      @if(isset($businessFees[$business->id]))
                        <strong>TZS {{ number_format($businessFees[$business->id]['amount'], 0) }}</strong>
                        <br><small class="text-muted">{{ $businessFees[$business->id]['label'] }}</small>
                      @else
                        —
                      @endif
                    </td>
                    <td>{{ $business->expiry_date ? \Carbon\Carbon::parse($business->expiry_date)->format('M d, Y') : 'N/A' }}</td>
                    <td>
                        @if($business->pending_approval)
                            <span class="badge badge-warning">Pending Approval</span>
                        @elseif(!$business->is_active)
                            <span class="badge badge-danger">Suspended</span>
                        @elseif($business->expiry_date && \Carbon\Carbon::parse($business->expiry_date)->isPast())
                            <span class="badge badge-danger">Expired</span>
                        @else
                            <span class="badge badge-success">Active</span>
                        @endif
                    </td>
                    <td class="text-center">
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
                            <button type="submit" class="btn btn-primary btn-sm" title="Login As Business" onclick="confirmAction(event, 'Impersonate Business?', 'You will be logged in as the owner of this business.')">
                                <i class="fa fa-user-secret"></i>
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
            @endforeach
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
    <script type="text/javascript">$('#businessTable').DataTable();</script>
@endsection

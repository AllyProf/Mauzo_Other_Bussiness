<div class="modal fade" id="pendingBusinessModal-{{ $business->id }}" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-building"></i> {{ $business->name }} <span class="badge badge-warning ml-2">Pending Approval</span></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6 mb-3"><strong>Owner</strong><br>{{ $business->contact_person ?? $business->ownerUser?->name ?? '—' }}</div>
          <div class="col-md-6 mb-3"><strong>Owner Email</strong><br>{{ $business->ownerUser?->email ?? $business->email ?? '—' }}</div>
          <div class="col-md-6 mb-3"><strong>Phone</strong><br>{{ $business->phone ?? '—' }}</div>
          <div class="col-md-6 mb-3"><strong>Business Email</strong><br>{{ $business->email ?? '—' }}</div>
          <div class="col-md-6 mb-3"><strong>Location</strong><br>{{ $business->region ?? '—' }}@if($business->district) ({{ $business->district }}) @endif</div>
          <div class="col-md-6 mb-3"><strong>Physical Address</strong><br>{{ $business->address ?? '—' }}</div>
          <div class="col-md-6 mb-3"><strong>TIN Number</strong><br>{{ $business->tin_number ?? '—' }}</div>
          <div class="col-md-6 mb-3"><strong>Business Type</strong><br>{{ collect($business->categoryBusinessTypesList())->pluck('label')->filter()->implode(', ') ?: '—' }}</div>
          <div class="col-md-6 mb-3"><strong>Plan</strong><br>{{ $business->plan->name ?? 'No Plan' }}</div>
          <div class="col-md-6 mb-3"><strong>Source</strong><br><span class="badge {{ $business->registrationSourceBadgeClass() }}">{{ $business->registrationSourceLabel() }}</span></div>
          <div class="col-md-6 mb-3"><strong>Registered</strong><br>{{ $business->created_at->format('M d, Y h:i A') }}</div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="{{ route('admin.businesses.edit', $business->id) }}" class="btn btn-outline-secondary mr-auto"><i class="fa fa-edit"></i> Edit</a>
        <form action="{{ route('admin.businesses.reject', $business->id) }}" method="POST" class="d-inline m-0">
          @csrf
          <button type="submit" class="btn btn-danger" onclick="confirmAction(event, 'Reject registration?', 'This will permanently remove this registration request.')">
            <i class="fa fa-times"></i> Reject
          </button>
        </form>
        <form action="{{ route('admin.businesses.approve', $business->id) }}" method="POST" class="d-inline m-0">
          @csrf
          <button type="submit" class="btn btn-success" onclick="confirmAction(event, 'Approve registration?', 'This will activate the account and start their free trial.')">
            <i class="fa fa-check"></i> Approve
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

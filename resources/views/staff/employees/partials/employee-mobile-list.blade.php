@foreach($staff as $member)
  <div class="emp-mobile-card {{ ! $member->isActiveAccount() ? 'is-inactive' : '' }}">
    <div class="emp-mobile-head">
      <div>
        <div class="emp-mobile-name">
          {{ $member->name }}
          @if($member->id == Auth::id())
            <span class="badge badge-secondary">You</span>
          @endif
        </div>
        <div class="emp-mobile-meta">
          @if($member->email)
            <span><i class="fa fa-envelope"></i> {{ $member->email }}</span>
          @endif
          @if($member->phone)
            <span><i class="fa fa-phone"></i> {{ $member->phone }}</span>
          @endif
        </div>
      </div>
      <div class="text-right">
        <span class="badge badge-primary">{{ $member->displayRoleName() }}</span>
        @if($member->isActiveAccount())
          <div class="mt-1"><span class="badge badge-success">{{ __('tables.status.active') }}</span></div>
        @else
          <div class="mt-1"><span class="badge badge-danger">Inactive</span></div>
        @endif
      </div>
    </div>
    <div class="emp-mobile-details small text-muted">
      <div><strong>{{ __('tables.columns.branch') }}:</strong>
        @if($member->role === 'staff')
          {{ $member->branch->name ?? '—' }}
        @else
          All branches
        @endif
      </div>
      <div><strong>{{ __('tables.columns.business') }}:</strong>
        @if($member->role === 'staff')
          {{ $member->displayBusinessTypeLabels() ?: '—' }}
        @else
          All
        @endif
      </div>
    </div>
    @can('manage_staff')
    <div class="emp-mobile-actions d-flex justify-content-end">
      @include('staff.employees.partials.actions-menu', ['member' => $member])
    </div>
    @endcan
  </div>
@endforeach

@foreach($staff as $member)
  @php
    $isStaffAccount = !in_array($member->role, ['owner', 'super_admin'], true);
  @endphp
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
    <div class="emp-mobile-actions employee-actions">
      <a href="{{ route('employees.edit', $member->id) }}" class="btn btn-sm btn-info" title="{{ __('tables.actions.edit') }}"><i class="fa fa-edit"></i> {{ __('tables.actions.edit') }}</a>

      @if($isStaffAccount)
        <form action="{{ route('employees.reset-password', $member->id) }}" method="POST">
          @csrf
          <button type="submit" class="btn btn-sm btn-warning" title="Reset / Generate Password"
            onclick="confirmAction(event, 'Reset Password?', 'A new random password will be generated for {{ $member->name }}. Copy it when shown — it cannot be viewed again.')">
            <i class="fa fa-key"></i> Reset
          </button>
        </form>

        @if($member->id != Auth::id())
          <form action="{{ route('employees.toggle-status', $member->id) }}" method="POST">
            @csrf
            @if($member->isActiveAccount())
              <button type="submit" class="btn btn-sm btn-secondary" title="Deactivate Account"
                onclick="confirmAction(event, 'Deactivate Account?', '{{ $member->name }} will not be able to log in until reactivated.')">
                <i class="fa fa-ban"></i>
              </button>
            @else
              <button type="submit" class="btn btn-sm btn-success" title="Activate Account"
                onclick="confirmAction(event, 'Activate Account?', '{{ $member->name }} will be able to log in again.')">
                <i class="fa fa-check"></i>
              </button>
            @endif
          </form>

          <form action="{{ route('employees.destroy', $member->id) }}" method="POST">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-sm btn-danger" title="Remove Employee"
              onclick="confirmAction(event, 'Remove Employee?', 'This will permanently delete {{ $member->name }}.')">
              <i class="fa fa-trash"></i>
            </button>
          </form>
        @endif
      @endif
    </div>
    @endcan
  </div>
@endforeach

@php
    $isStaffAccount = !in_array($member->role, ['owner', 'super_admin'], true);
@endphp
<div class="dropdown employee-actions-menu">
  <button type="button" class="btn btn-sm btn-light border employee-actions-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Actions">
    <i class="fa fa-ellipsis-v"></i>
  </button>
  <div class="dropdown-menu dropdown-menu-right shadow-sm">
    <a href="{{ route('employees.edit', $member->id) }}" class="dropdown-item">
      <i class="fa fa-edit text-info"></i> {{ __('tables.actions.edit') }}
    </a>

    @if($isStaffAccount)
      @if(Auth::user()->role === 'owner' && $member->isActiveAccount() && $member->id != Auth::id())
      <form action="{{ route('employees.impersonate', $member->id) }}" method="POST">
        @csrf
        <button type="submit" class="dropdown-item"
          onclick="confirmAction(event, 'View as {{ $member->name }}?', 'You will see the system exactly as this employee sees it. Use Switch Back to Owner when done.')">
          <i class="fa fa-user-secret text-primary"></i> View as this staff
        </button>
      </form>
      @endif

      <form action="{{ route('employees.reset-password', $member->id) }}" method="POST">
        @csrf
        <button type="submit" class="dropdown-item"
          onclick="confirmAction(event, 'Reset Password?', 'A new random password will be generated for {{ $member->name }}. Copy it when shown — it cannot be viewed again.')">
          <i class="fa fa-key text-warning"></i> Reset password
        </button>
      </form>

      @if($member->id != Auth::id())
        <form action="{{ route('employees.toggle-status', $member->id) }}" method="POST">
          @csrf
          @if($member->isActiveAccount())
            <button type="submit" class="dropdown-item"
              onclick="confirmAction(event, 'Deactivate Account?', '{{ $member->name }} will not be able to log in until reactivated.')">
              <i class="fa fa-ban text-secondary"></i> Deactivate
            </button>
          @else
            <button type="submit" class="dropdown-item"
              onclick="confirmAction(event, 'Activate Account?', '{{ $member->name }} will be able to log in again.')">
              <i class="fa fa-check text-success"></i> Activate
            </button>
          @endif
        </form>

        <div class="dropdown-divider"></div>
        <form action="{{ route('employees.destroy', $member->id) }}" method="POST">
          @csrf @method('DELETE')
          <button type="submit" class="dropdown-item text-danger"
            onclick="confirmAction(event, 'Remove Employee?', 'This will permanently delete {{ $member->name }}.')">
            <i class="fa fa-trash"></i> Remove
          </button>
        </form>
      @endif
    @endif
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile shadow-sm border-0" style="border-radius: 12px; border-left: 4px solid #940000 !important;">
      <div class="tile-body py-4">
        <div class="alert alert-warning mb-4" style="border-left: 4px solid #f39c12;">
          <h4 class="alert-heading mb-2">
            <i class="fa fa-user-times"></i>
            @if($missingRole ?? true)
              No role assigned to your account
            @else
              Your role has no permissions
            @endif
          </h4>
          <p class="mb-2">
            @if($missingRole ?? true)
              Your manager has not assigned a staff role to you yet. Without a role, the system cannot grant access to POS, inventory, shifts, or reports.
            @else
              You have a role (<strong>{{ Auth::user()->role_relation?->name }}</strong>), but it has no permissions enabled. Ask your manager to review the role settings.
            @endif
          </p>
          <p class="mb-0 small text-muted">
            That is why you only see <strong>Dashboard</strong> and <strong>Notes &amp; Reminders</strong> in the menu, and why sales or stock features are unavailable.
          </p>
        </div>

        <div class="row">
          <div class="col-md-6">
            <h5 class="mb-3"><i class="fa fa-id-card-o text-muted"></i> Your account</h5>
            <table class="table table-sm table-borderless mb-0">
              <tr>
                <th class="text-muted pl-0" style="width: 140px;">Name</th>
                <td>{{ Auth::user()->name }}</td>
              </tr>
              <tr>
                <th class="text-muted pl-0">Business</th>
                <td>{{ Auth::user()->business?->name ?? '—' }}</td>
              </tr>
              <tr>
                <th class="text-muted pl-0">Branch</th>
                <td>{{ $activeBranchLabel ?? Auth::user()->branch?->name ?? '—' }}</td>
              </tr>
              <tr>
                <th class="text-muted pl-0">Business type</th>
                <td>{{ Auth::user()->displayBusinessTypeLabels() ?? '—' }}</td>
              </tr>
              <tr>
                <th class="text-muted pl-0">Assigned role</th>
                <td>
                  @if($missingRole ?? true)
                    <span class="badge badge-warning">Not assigned</span>
                  @else
                    {{ Auth::user()->role_relation?->name ?? '—' }}
                  @endif
                </td>
              </tr>
            </table>
          </div>
          <div class="col-md-6">
            <h5 class="mb-3"><i class="fa fa-info-circle text-muted"></i> What to do next</h5>
            <ol class="mb-0 pl-3">
              <li class="mb-2">Contact your business owner or manager.</li>
              <li class="mb-2">Ask them to open <strong>Staff / Employees</strong>, edit your profile, and assign a role (for example Sales Officer or Cashier).</li>
              <li class="mb-0">After saving, log out and log back in if menu items do not appear immediately.</li>
            </ol>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

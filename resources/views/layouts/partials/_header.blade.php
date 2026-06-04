<header class="app-header"><a class="app-header__logo" href="{{ url('/home') }}" title="{{ $headerBrand ?? 'SP-POS' }}">{{ $headerBrand ?? 'SP-POS' }}</a>
  <!-- Sidebar toggle button--><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
  <!-- Navbar Right Menu-->
  <ul class="app-nav">
    @if(!empty($canSwitchBusiness) && ($ownerBusinesses ?? collect())->count() > 1)
    <li class="dropdown">
      <a class="app-nav__item d-flex align-items-center" href="#" data-toggle="dropdown" aria-label="Switch business" title="Switch business">
        <i class="fa fa-briefcase fa-lg"></i>
        <span class="d-none d-md-inline ml-2">{{ $activeBusinessLabel ?? 'Business' }}</span>
        <i class="fa fa-caret-down ml-1 d-none d-md-inline"></i>
      </a>
      <ul class="dropdown-menu dropdown-menu-right">
        <li class="dropdown-header">Switch Business</li>
        @foreach($ownerBusinesses as $ownerBusiness)
        <li>
          <form action="{{ route('businesses.switch') }}" method="POST">
            @csrf
            <input type="hidden" name="business_id" value="{{ $ownerBusiness->id }}">
            <button type="submit" class="dropdown-item {{ (int) ($activeBusinessId ?? 0) === (int) $ownerBusiness->id ? 'active font-weight-bold' : '' }}">
              <i class="fa fa-building mr-2"></i> {{ $ownerBusiness->name }}
            </button>
          </form>
        </li>
        @endforeach
      </ul>
    </li>
    @endif
    @if(!empty($canSwitchBranch) && ($ownerBranches ?? collect())->isNotEmpty())
    <li class="dropdown">
      <a class="app-nav__item d-flex align-items-center" href="#" data-toggle="dropdown" aria-label="Switch branch" title="Switch branch">
        <i class="fa fa-map-marker fa-lg"></i>
        <span class="d-none d-md-inline ml-2 branch-switch-label">{{ $activeBranchLabel ?? 'Branch' }}</span>
        <i class="fa fa-caret-down ml-1 d-none d-md-inline"></i>
      </a>
      <ul class="dropdown-menu dropdown-menu-right branch-switch-menu">
        <li class="dropdown-header">Switch Branch</li>
        <li>
          <form action="{{ route('branches.switch') }}" method="POST">
            @csrf
            <input type="hidden" name="branch_id" value="all">
            <button type="submit" class="dropdown-item {{ !empty($viewingAllBranches) ? 'active font-weight-bold' : '' }}">
              <i class="fa fa-building-o mr-2"></i> All Branches
            </button>
          </form>
        </li>
        <li class="dropdown-divider"></li>
        @foreach($ownerBranches as $branch)
        <li>
          <form action="{{ route('branches.switch') }}" method="POST">
            @csrf
            <input type="hidden" name="branch_id" value="{{ $branch->id }}">
            <button type="submit" class="dropdown-item {{ (int) ($activeBranchId ?? 0) === (int) $branch->id ? 'active font-weight-bold' : '' }}">
              <i class="fa fa-map-marker mr-2"></i> {{ $branch->name }}
              @if($branch->is_default)
                <small class="text-muted">(Default)</small>
              @endif
            </button>
          </form>
        </li>
        @endforeach
      </ul>
    </li>
    @endif
    <li class="app-search">
      <input class="app-search__input" type="search" placeholder="Search">
      <button class="app-search__button"><i class="fa fa-search"></i></button>
    </li>
    <!--Notification Menu-->
    @if(Auth::user()->role != 'super_admin')
    <li class="dropdown">
      <a class="app-nav__item position-relative" href="#" data-toggle="dropdown" aria-label="Show notifications">
        <i class="fa fa-bell-o fa-lg"></i>
        @if(($dueNoteRemindersCount ?? 0) > 0)
          <span class="badge badge-danger" style="position:absolute;top:8px;right:2px;font-size:0.65rem;">{{ $dueNoteRemindersCount }}</span>
        @endif
      </a>
      <ul class="app-notification dropdown-menu dropdown-menu-right">
        @if(($dueNoteReminders ?? collect())->isEmpty())
          <li class="app-notification__title">No due reminders.</li>
          <div class="app-notification__content">
            <li class="px-3 py-2 text-muted small">Create a note with a reminder time to get notified here.</li>
          </div>
        @else
          <li class="app-notification__title">You have {{ $dueNoteRemindersCount }} due reminder{{ $dueNoteRemindersCount === 1 ? '' : 's' }}.</li>
          <div class="app-notification__content">
            @foreach($dueNoteReminders as $reminder)
              <li>
                <a class="app-notification__item" href="{{ route('notes.index') }}">
                  <span class="app-notification__icon">
                    <span class="fa-stack fa-lg">
                      <i class="fa fa-circle fa-stack-2x text-danger"></i>
                      <i class="fa fa-sticky-note fa-stack-1x fa-inverse"></i>
                    </span>
                  </span>
                  <div>
                    <p class="app-notification__message">{{ $reminder->displayTitle() }}</p>
                    <p class="app-notification__meta">{{ $reminder->remind_at?->diffForHumans() }}</p>
                  </div>
                </a>
              </li>
            @endforeach
          </div>
        @endif
        <li class="app-notification__footer"><a href="{{ route('notes.index') }}">Manage notes & reminders</a></li>
      </ul>
    </li>
    @else
    <li class="dropdown"><a class="app-nav__item" href="#" data-toggle="dropdown" aria-label="Show notifications"><i class="fa fa-bell-o fa-lg"></i></a>
      <ul class="app-notification dropdown-menu dropdown-menu-right">
        <li class="app-notification__title">No new notifications.</li>
        <div class="app-notification__content">
          <li class="px-3 py-2 text-muted small">Platform notifications will appear here.</li>
        </div>
      </ul>
    </li>
    @endif
    <!-- User Menu-->
    <li class="dropdown"><a class="app-nav__item" href="#" data-toggle="dropdown" aria-label="Open Profile Menu"><i class="fa fa-user fa-lg"></i></a>
      <ul class="dropdown-menu settings-menu dropdown-menu-right">
        <li><a class="dropdown-item" href="#"><i class="fa fa-cog fa-lg"></i> Settings</a></li>
        <li><a class="dropdown-item" href="#"><i class="fa fa-user fa-lg"></i> Profile</a></li>
        <li>
            <a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                <i class="fa fa-sign-out fa-lg"></i> Logout
            </a>
            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                @csrf
            </form>
        </li>
      </ul>
    </li>
  </ul>
</header>

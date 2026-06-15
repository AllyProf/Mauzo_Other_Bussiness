<header class="app-header">
  <a class="app-header__logo" href="{{ url('/home') }}" title="{{ $headerBrand ?? 'SP-POS' }}">{{ $headerBrand ?? 'SP-POS' }}</a>
  <a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
  <ul class="app-nav app-nav--toolbar">
    @include('partials.language-switcher')
    @if(!empty($canSwitchBusiness) && ($ownerBusinesses ?? collect())->count() > 1)
    <li class="dropdown app-nav__action app-nav__business">
      <a class="app-nav__item app-nav__icon-btn" href="#" data-toggle="dropdown" aria-label="{{ __('common.switch_business') }}" title="{{ __('common.switch_business') }}">
        <i class="fa fa-briefcase"></i>
        <span class="d-none d-md-inline ml-2">{{ $activeBusinessLabel ?? __('common.business') }}</span>
        <i class="fa fa-caret-down ml-1 d-none d-md-inline"></i>
      </a>
      <ul class="dropdown-menu dropdown-menu-right">
        <li class="dropdown-header">{{ __('common.switch_business') }}</li>
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
    <li class="dropdown app-nav__action app-nav__branch">
      <a class="app-nav__item app-nav__icon-btn" href="#" data-toggle="dropdown" aria-label="{{ __('common.switch_branch') }}" title="{{ __('common.switch_branch') }}">
        <i class="fa fa-map-marker"></i>
        <span class="d-none d-md-inline ml-2 branch-switch-label">{{ $activeBranchLabel ?? __('common.branch') }}</span>
        <i class="fa fa-caret-down ml-1 d-none d-md-inline"></i>
      </a>
      <ul class="dropdown-menu dropdown-menu-right branch-switch-menu">
        <li class="dropdown-header">{{ __('common.switch_branch') }}</li>
        <li>
          <form action="{{ route('branches.switch') }}" method="POST">
            @csrf
            <input type="hidden" name="branch_id" value="all">
            <button type="submit" class="dropdown-item {{ !empty($viewingAllBranches) ? 'active font-weight-bold' : '' }}">
              <i class="fa fa-building-o mr-2"></i> {{ __('common.all_branches') }}
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
                <small class="text-muted">({{ __('common.default') }})</small>
              @endif
            </button>
          </form>
        </li>
        @endforeach
      </ul>
    </li>
    @endif
    <li class="app-search">
      <input class="app-search__input" type="search" placeholder="{{ __('common.search') }}">
      <button class="app-search__button"><i class="fa fa-search"></i></button>
    </li>
    <!--Notification Menu-->
    @if(Auth::user()->role != 'super_admin' && plan_feature('notes_reminders'))
    <li class="dropdown app-nav__action app-nav__notify">
      <a class="app-nav__item app-nav__icon-btn app-nav__icon-btn--badge" href="#" data-toggle="dropdown" aria-label="Show notifications">
        <i class="fa fa-bell-o"></i>
        @if(($dueNoteRemindersCount ?? 0) > 0)
          <span class="app-nav__badge badge badge-danger">{{ $dueNoteRemindersCount }}</span>
        @endif
      </a>
      <ul class="app-notification dropdown-menu dropdown-menu-right">
        @if(($dueNoteReminders ?? collect())->isEmpty())
          <li class="app-notification__title">{{ __('common.no_due_reminders') }}</li>
          <div class="app-notification__content">
            <li class="px-3 py-2 text-muted small">{{ __('common.reminder_hint') }}</li>
          </div>
        @else
          <li class="app-notification__title">{{ trans_choice('common.due_reminders', $dueNoteRemindersCount) }}</li>
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
        <li class="app-notification__footer"><a href="{{ route('notes.index') }}">{{ __('common.manage_notes') }}</a></li>
      </ul>
    </li>
    @else
    <li class="dropdown app-nav__action app-nav__notify"><a class="app-nav__item app-nav__icon-btn" href="#" data-toggle="dropdown" aria-label="Show notifications"><i class="fa fa-bell-o"></i></a>
      <ul class="app-notification dropdown-menu dropdown-menu-right">
        <li class="app-notification__title">{{ __('common.no_notifications') }}</li>
        <div class="app-notification__content">
          <li class="px-3 py-2 text-muted small">{{ __('common.platform_notifications_hint') }}</li>
        </div>
      </ul>
    </li>
    @endif
    <!-- User Menu-->
    <li class="dropdown app-nav__action app-nav__profile">
      <a class="app-nav__item app-nav__icon-btn" href="#" data-toggle="dropdown" aria-label="Open Profile Menu">
        @if(Auth::user()->profileImageUrl())
        <img src="{{ Auth::user()->profileImageUrl() }}" alt="{{ Auth::user()->name }}" class="rounded-circle" style="width: 28px; height: 28px; object-fit: cover;">
        @else
        <i class="fa fa-user"></i>
        @endif
      </a>
      <ul class="dropdown-menu settings-menu dropdown-menu-right">
        @if(Auth::user()->isPlatformAdmin())
        <li><a class="dropdown-item" href="{{ route('admin.settings.index') }}"><i class="fa fa-cog fa-lg"></i> {{ __('common.settings') }}</a></li>
        @elseif(Auth::user()->role === 'owner')
        <li><a class="dropdown-item" href="{{ route('settings.index') }}"><i class="fa fa-cog fa-lg"></i> {{ __('common.settings') }}</a></li>
        @endif
        <li><a class="dropdown-item" href="{{ route('profile.show') }}"><i class="fa fa-user fa-lg"></i> {{ __('common.profile') }}</a></li>
        <li>
            <a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                <i class="fa fa-sign-out fa-lg"></i> {{ __('common.logout') }}
            </a>
            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                @csrf
            </form>
        </li>
      </ul>
    </li>
  </ul>
</header>

<header class="app-header">
  <a class="app-header__logo @if(mb_strlen($headerBrand ?? '') > 18) is-long-name @endif @if(mb_strlen($headerBrand ?? '') > 26) is-very-long-name @endif"
     href="{{ url('/home') }}"
     title="{{ $headerBrand ?? 'SP-POS' }}">{{ $headerBrand ?? 'SP-POS' }}</a>
  <a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
  <ul class="app-nav app-nav--toolbar">
    @include('partials.language-switcher')
    <li class="app-nav__action app-nav__theme">
      <a class="app-nav__item app-nav__icon-btn" href="#" id="themeToggle" role="button" aria-label="Switch to dark mode" title="Switch to dark mode">
        <i class="fa fa-moon-o"></i>
      </a>
    </li>
    @if(!empty($canSwitchBusiness) && ($ownerBusinesses ?? collect())->count() > 1)
    <li class="dropdown app-nav__action app-nav__business">
      <a class="app-nav__item app-nav__icon-btn" href="#" data-toggle="dropdown" aria-label="{{ __('common.switch_business') }}" title="{{ __('common.switch_business') }}">
        <i class="fa fa-briefcase"></i>
        <span class="d-none d-md-inline ml-2">{{ $activeBusinessLabel ?? __('common.business') }}</span>
        <i class="fa fa-caret-down ml-1 d-none d-md-inline"></i>
      </a>
      <ul class="dropdown-menu dropdown-menu-right header-switch-menu">
        <li class="dropdown-header">{{ __('common.switch_business') }}</li>
        @foreach($ownerBusinesses as $ownerBusiness)
        <li>
          <form action="{{ route('businesses.switch') }}" method="POST">
            @csrf
            <input type="hidden" name="business_id" value="{{ $ownerBusiness->id }}">
            <button type="submit" class="dropdown-item {{ (int) ($activeBusinessId ?? 0) === (int) $ownerBusiness->id ? 'active' : '' }}">
              <span class="header-switch-menu__icon"><i class="fa fa-building"></i></span>
              <span class="header-switch-menu__label">{{ $ownerBusiness->name }}</span>
              <i class="fa fa-check header-switch-menu__check"></i>
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
      <ul class="dropdown-menu dropdown-menu-right header-switch-menu">
        <li class="dropdown-header">{{ __('common.switch_branch') }}</li>
        <li>
          <form action="{{ route('branches.switch') }}" method="POST">
            @csrf
            <input type="hidden" name="branch_id" value="all">
            <button type="submit" class="dropdown-item {{ !empty($viewingAllBranches) ? 'active' : '' }}">
              <span class="header-switch-menu__icon"><i class="fa fa-sitemap"></i></span>
              <span class="header-switch-menu__label">{{ __('common.all_branches') }}</span>
              <i class="fa fa-check header-switch-menu__check"></i>
            </button>
          </form>
        </li>
        <li class="dropdown-divider"></li>
        @foreach($ownerBranches as $branch)
        <li>
          <form action="{{ route('branches.switch') }}" method="POST">
            @csrf
            <input type="hidden" name="branch_id" value="{{ $branch->id }}">
            <button type="submit" class="dropdown-item {{ (int) ($activeBranchId ?? 0) === (int) $branch->id ? 'active' : '' }}">
              <span class="header-switch-menu__icon"><i class="fa fa-map-marker"></i></span>
              <span class="header-switch-menu__label">
                {{ $branch->name }}
                @if($branch->is_default)
                  <span class="header-switch-menu__tag">{{ __('common.default') }}</span>
                @endif
              </span>
              <i class="fa fa-check header-switch-menu__check"></i>
            </button>
          </form>
        </li>
        @endforeach
      </ul>
    </li>
    @endif
    <li class="app-search" id="globalSearch" data-url="{{ route('global-search') }}">
      <input class="app-search__input" type="search" id="globalSearchInput" placeholder="{{ __('common.search') }}" autocomplete="off" aria-label="{{ __('common.search') }}">
      <button class="app-search__button" type="button" tabindex="-1"><i class="fa fa-search"></i></button>
      <ul class="app-notification dropdown-menu dropdown-menu-right global-search__panel" id="globalSearchPanel" role="listbox"></ul>
    </li>
    <!--Notification Menu-->
    @if(Auth::user()->role != 'super_admin')
    @php
      $headerNotes = $dueNoteReminders ?? collect();
      $headerSupplies = $pendingBranchSupplies ?? collect();
      $headerCount = (int) ($headerNotificationCount ?? (($dueNoteRemindersCount ?? 0) + ($pendingBranchSuppliesCount ?? 0)));
      $hasHeaderNotes = $headerNotes->isNotEmpty();
      $hasHeaderSupplies = $headerSupplies->isNotEmpty();
    @endphp
    <li class="dropdown app-nav__action app-nav__notify">
      <a class="app-nav__item app-nav__icon-btn app-nav__icon-btn--badge" href="#" data-toggle="dropdown" aria-label="Show notifications">
        <i class="fa fa-bell-o"></i>
        @if($headerCount > 0)
          <span class="app-nav__badge badge badge-danger">{{ $headerCount }}</span>
        @endif
      </a>
      <ul class="app-notification dropdown-menu dropdown-menu-right">
        @if(! $hasHeaderNotes && ! $hasHeaderSupplies)
          <li class="app-notification__title">{{ __('common.no_notifications') }}</li>
          <div class="app-notification__content">
            <li class="px-3 py-2 text-muted small">{{ __('common.reminder_hint') }}</li>
          </div>
        @else
          <li class="app-notification__title">{{ trans_choice('common.header_notifications', $headerCount) }}</li>
          <div class="app-notification__content">
            @foreach($headerSupplies as $supply)
              <li>
                <a class="app-notification__item" href="{{ route('branch-transfers.show', $supply) }}">
                  <span class="app-notification__icon">
                    <span class="fa-stack fa-lg">
                      <i class="fa fa-circle fa-stack-2x text-warning"></i>
                      <i class="fa fa-truck fa-stack-1x fa-inverse"></i>
                    </span>
                  </span>
                  <div>
                    <p class="app-notification__message">{{ __('common.incoming_supply_bell', [
                      'ref' => $supply->reference_no,
                      'from' => $supply->fromBranch?->name ?? __('branch_transfers.from'),
                      'pieces' => fmod((float) $supply->total_pieces, 1.0) === 0.0 ? (int) $supply->total_pieces : number_format((float) $supply->total_pieces, 2),
                    ]) }}</p>
                    <p class="app-notification__meta">{{ $supply->created_at?->diffForHumans() }}</p>
                  </div>
                </a>
              </li>
            @endforeach
            @foreach($headerNotes as $reminder)
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
        <li class="app-notification__footer">
          @if($hasHeaderSupplies)
            <a href="{{ route('branch-transfers.index') }}">{{ __('common.view_incoming_supplies') }}</a>
          @elseif(plan_feature('notes_reminders'))
            <a href="{{ route('notes.index') }}">{{ __('common.manage_notes') }}</a>
          @else
            <a href="{{ route('branch-transfers.index') }}">{{ __('common.view_incoming_supplies') }}</a>
          @endif
        </li>
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

<style>
  #globalSearch { position: relative; }
  #globalSearch .global-search__panel { width: 340px; max-width: 92vw; margin-top: 8px; color: #333; }
  #globalSearch .app-notification__content { max-height: 60vh; }
  #globalSearch .app-notification__item { color: #333; text-decoration: none; align-items: center; }
  #globalSearch .app-notification__item.is-active { background-color: #e9ecef; }
  #globalSearch .app-notification__icon .fa-inverse { color: #fff; }
  #globalSearch .app-notification__message { margin-bottom: 2px; font-weight: 600; }
  #globalSearch .app-notification__message mark { background: #ffe8a3; padding: 0; }
  #globalSearch .global-search__group { padding: 6px 20px; background: #f5f5f5; border-bottom: 1px solid #ddd; font-size: 11px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; color: #6c757d; }
  #globalSearch .global-search__empty { padding: 14px 20px; color: #6c757d; }
  #globalSearch .global-search__typewriter {
    position: absolute; top: 0; bottom: 0; display: flex; align-items: center;
    pointer-events: none; white-space: nowrap; overflow: hidden; color: #6c757d;
  }
  #globalSearch .global-search__typewriter b { color: #28a745; font-weight: 700; }
  #globalSearch .global-search__typewriter i { font-style: normal; color: #28a745; animation: gsCaret 1s steps(1) infinite; }
  @keyframes gsCaret { 50% { opacity: 0; } }
</style>
<script>
(function () {
  var toggle = document.getElementById('themeToggle');
  if (!toggle) return;
  var html = document.documentElement;

  function sync() {
    var dark = html.classList.contains('theme-dark');
    var label = dark ? 'Switch to light mode' : 'Switch to dark mode';
    toggle.innerHTML = '<i class="fa ' + (dark ? 'fa-sun-o' : 'fa-moon-o') + '"></i>';
    toggle.setAttribute('title', label);
    toggle.setAttribute('aria-label', label);
  }

  toggle.addEventListener('click', function (e) {
    e.preventDefault();
    var dark = !html.classList.contains('theme-dark');
    html.classList.toggle('theme-dark', dark);
    try { localStorage.setItem('app-theme', dark ? 'dark' : 'light'); } catch (err) {}
    sync();
    document.dispatchEvent(new CustomEvent('app-theme-change', { detail: { dark: dark } }));
  });

  sync();
})();

(function () {
  var root = document.getElementById('globalSearch');
  if (!root) return;
  var input = document.getElementById('globalSearchInput');
  var panel = document.getElementById('globalSearchPanel');
  var url = root.dataset.url;
  var timer = null, controller = null, activeIndex = -1, lastTerm = '', remoteGroups = [];

  document.addEventListener('DOMContentLoaded', function typewriterPlaceholder() {
    var basePlaceholder = input.getAttribute('placeholder') || 'Search';
    var pages = [];
    document.querySelectorAll('.app-sidebar .app-menu__label, .app-sidebar .treeview-item').forEach(function (el) {
      var name = (el.textContent || '').replace(/\s+/g, ' ').trim();
      if (name && name.length <= 28 && pages.indexOf(name) === -1) pages.push(name);
    });
    if (!pages.length) return;

    var style = window.getComputedStyle(input);
    var overlay = document.createElement('span');
    overlay.className = 'global-search__typewriter';
    overlay.style.left = (input.offsetLeft + parseFloat(style.paddingLeft || 10)) + 'px';
    overlay.style.right = '34px';
    overlay.style.fontSize = style.fontSize;
    overlay.style.fontFamily = style.fontFamily;
    input.insertAdjacentElement('afterend', overlay);

    var page = 0, chars = 0, deleting = false;

    function render(name) {
      overlay.innerHTML = 'Search&nbsp;<b>' + esc(name) + '</b><i>|</i>';
    }

    function tick() {
      if (document.activeElement === input || input.value) {
        overlay.style.display = 'none';
        input.setAttribute('placeholder', basePlaceholder);
        return setTimeout(tick, 500);
      }
      overlay.style.display = '';
      input.setAttribute('placeholder', '');

      var name = pages[page];
      chars += deleting ? -1 : 1;
      render(name.slice(0, Math.max(0, chars)));

      var delay = deleting ? 40 : 90;
      if (!deleting && chars >= name.length) { deleting = true; delay = 1600; }
      else if (deleting && chars <= 0) { deleting = false; page = (page + 1) % pages.length; delay = 350; }
      setTimeout(tick, delay);
    }
    tick();
  });

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function highlight(text, term) {
    var safe = esc(text);
    if (!term) return safe;
    var re = new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
    return safe.replace(re, '<mark>$1</mark>');
  }

  function menuPages() {
    var pages = [], seen = {};
    document.querySelectorAll('.app-sidebar a.app-menu__item[href], .app-sidebar a.treeview-item[href]').forEach(function (a) {
      var href = a.getAttribute('href');
      if (!href || href === '#' || href.indexOf('javascript') === 0 || seen[href]) return;
      var label = (a.querySelector('.app-menu__label') || a).textContent.replace(/\s+/g, ' ').trim();
      if (!label) return;
      var tree = a.closest('.treeview');
      var parent = tree ? (tree.querySelector('.app-menu__label') || {}).textContent : '';
      var icon = (a.querySelector('i.icon, i.app-menu__icon') || {}).className || '';
      var iconMatch = (icon.match(/fa-[a-z0-9-]+/g) || []).filter(function (c) {
        return !/^fa-(lg|fw|[2-5]x|spin|inverse|stack.*)$/.test(c);
      });
      iconMatch = iconMatch.length ? iconMatch : null;
      seen[href] = true;
      pages.push({
        title: label,
        subtitle: parent ? parent.replace(/\s+/g, ' ').trim() : 'Page',
        icon: iconMatch ? iconMatch[iconMatch.length - 1] : 'fa-link',
        url: href
      });
    });
    return pages;
  }

  function matchPages(term) {
    var t = term.toLowerCase();
    return menuPages().filter(function (p) {
      return p.title.toLowerCase().indexOf(t) !== -1 || p.subtitle.toLowerCase().indexOf(t) !== -1;
    }).slice(0, 6);
  }

  function render(term, loading) {
    var groups = [];
    var pages = matchPages(term);
    if (pages.length) groups.push({ label: 'Pages', items: pages });
    groups = groups.concat(remoteGroups);

    var count = groups.reduce(function (n, g) { return n + g.items.length; }, 0);
    var html = '<li class="app-notification__title">'
      + (count ? count + ' result' + (count === 1 ? '' : 's') + ' for "' + esc(term) + '"' : 'Search "' + esc(term) + '"')
      + '</li><div class="app-notification__content">';

    groups.forEach(function (g) {
      var colour = GROUP_COLOURS[g.label] || 'text-secondary';
      html += '<li class="global-search__group">' + esc(g.label) + '</li>';
      g.items.forEach(function (item) {
        html += '<li><a class="app-notification__item global-search__item" role="option" href="' + esc(item.url) + '">'
          + '<span class="app-notification__icon"><span class="fa-stack fa-lg">'
          + '<i class="fa fa-circle fa-stack-2x ' + colour + '"></i>'
          + '<i class="fa ' + esc(item.icon || 'fa-search') + ' fa-stack-1x fa-inverse"></i>'
          + '</span></span><div>'
          + '<p class="app-notification__message">' + highlight(item.title, term) + '</p>'
          + (item.subtitle ? '<p class="app-notification__meta">' + highlight(item.subtitle, term) + '</p>' : '')
          + '</div></a></li>';
      });
    });

    if (loading) {
      html += '<li class="global-search__empty"><i class="fa fa-spinner fa-spin mr-1"></i> Searching records...</li>';
    } else if (!groups.length) {
      html += '<li class="global-search__empty">No results found.</li>';
    }
    html += '</div><li class="app-notification__footer"><a href="#" onclick="return false;">↑ ↓ to move · Enter to open · Esc to close</a></li>';

    panel.innerHTML = html;
    panel.classList.add('show');
    activeIndex = -1;
  }

  var GROUP_COLOURS = {
    'Pages': 'text-primary',
    'Items': 'text-info',
    'Customers': 'text-success',
    'Suppliers': 'text-warning',
    'Invoices': 'text-danger',
    'Sales': 'text-primary',
    'Businesses': 'text-info',
    'Subscription Invoices': 'text-danger'
  };

  function close() { panel.classList.remove('show'); activeIndex = -1; }

  function fetchRemote(term) {
    if (controller) controller.abort();
    if (term.length < 2) { remoteGroups = []; render(term, false); return; }
    controller = window.AbortController ? new AbortController() : null;
    fetch(url + '?q=' + encodeURIComponent(term), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      signal: controller ? controller.signal : undefined
    })
      .then(function (r) { return r.ok ? r.json() : { groups: [] }; })
      .then(function (data) {
        if (term !== lastTerm) return;
        remoteGroups = data.groups || [];
        render(term, false);
      })
      .catch(function (e) { if (e.name !== 'AbortError') { remoteGroups = []; render(term, false); } });
  }

  input.addEventListener('input', function () {
    var term = input.value.trim();
    lastTerm = term;
    clearTimeout(timer);
    if (!term) { remoteGroups = []; close(); return; }
    remoteGroups = [];
    render(term, term.length >= 2);
    timer = setTimeout(function () { fetchRemote(term); }, 250);
  });

  input.addEventListener('focus', function () { if (input.value.trim()) render(input.value.trim(), false); });

  input.addEventListener('keydown', function (e) {
    var items = panel.querySelectorAll('.global-search__item');
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      if (!items.length) return;
      e.preventDefault();
      activeIndex = e.key === 'ArrowDown' ? (activeIndex + 1) % items.length : (activeIndex - 1 + items.length) % items.length;
      items.forEach(function (el, i) { el.classList.toggle('is-active', i === activeIndex); });
      items[activeIndex].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
      e.preventDefault();
      var target = items[activeIndex >= 0 ? activeIndex : 0];
      if (target) window.location.href = target.getAttribute('href');
    } else if (e.key === 'Escape') {
      close();
      input.blur();
    }
  });

  root.querySelector('.app-search__button').addEventListener('click', function () { input.focus(); });

  document.addEventListener('click', function (e) { if (!root.contains(e.target)) close(); });

  document.addEventListener('keydown', function (e) {
    var tag = (document.activeElement && document.activeElement.tagName) || '';
    if ((e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(tag)) || ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k')) {
      e.preventDefault();
      input.focus();
      input.select();
    }
  });
})();
</script>

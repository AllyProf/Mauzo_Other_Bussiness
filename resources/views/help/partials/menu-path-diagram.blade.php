@php
  $diagram = $diagram ?? [];
  $groupKey = $diagram['group'] ?? null;
  $treeKey = $diagram['tree'] ?? null;
  $itemKey = $diagram['item'] ?? null;
  $stepTitle = $stepTitle ?? '';
  $openUrl = $openUrl ?? null;

  $inventoryItems = ['items', 'item_stock', 'categories', 'packaging_units', 'suppliers', 'stock_in'];
  $salesItems = ['store_pos', 'sales_shifts'];
  $myBusinessItems = ['business_settings', 'upgrade_plan'];
  $reportsItems = ['reports'];
  $staffItems = ['employees', 'roles'];

  $groups = [
    'group_inventory' => ['icon' => 'fa-cubes', 'items' => $inventoryItems],
    'group_sales' => ['icon' => 'fa-shopping-cart', 'items' => $salesItems],
    'group_my_business' => ['icon' => 'fa-briefcase', 'items' => $myBusinessItems],
    'group_reports' => ['icon' => 'fa-line-chart', 'items' => $reportsItems],
  ];

  $expandedGroup = $groupKey;
  $highlightItem = $itemKey;

  $arrowTop = '50%';
  if ($treeKey === 'staff_management') {
    $idx = array_search($highlightItem, $staffItems, true);
    $arrowTop = (48 + ($idx !== false ? $idx * 10 : 0)).'%';
  } elseif ($expandedGroup && isset($groups[$expandedGroup])) {
    $items = $groups[$expandedGroup]['items'];
    $idx = array_search($highlightItem, $items, true);
    $base = match ($expandedGroup) {
      'group_reports' => 46,
      'group_my_business' => 44,
      default => 40,
    };
    $arrowTop = ($base + ($idx !== false ? $idx * 7.2 : 0)).'%';
  }

  $renderTargetRow = function (string $sk, string $iconClass, string $label) use ($highlightItem, $openUrl) {
    $isTarget = $sk === $highlightItem;
    $classes = 'usage-menu-diagram__row usage-menu-diagram__row--sub'.($isTarget ? ' is-target' : '');
    $inner = '<i class="'.$iconClass.'"></i><span>'.$label.'</span>';
    if ($isTarget && $openUrl) {
      return '<a href="'.e($openUrl).'" class="'.$classes.' usage-menu-diagram__row--link">'.$inner.'</a>';
    }
    return '<div class="'.$classes.'">'.$inner.'</div>';
  };
@endphp
<div class="usage-menu-diagram">
  <p class="usage-menu-diagram__caption mb-1">{{ __('help.getting_started.menu_diagram_caption') }}</p>
  <div class="usage-menu-diagram__frame" style="--guide-arrow-top: {{ $arrowTop }};">
    <div class="usage-menu-diagram__sidebar">
      <div class="usage-menu-diagram__row usage-menu-diagram__row--top">
        <i class="fa fa-dashboard usage-menu-diagram__menu-icon"></i>
        <span>{{ __('menu.dashboard') }}</span>
      </div>
      <div class="usage-menu-diagram__row usage-menu-diagram__row--top usage-menu-diagram__row--muted">
        <i class="fa fa-book usage-menu-diagram__menu-icon"></i>
        <span>{{ __('menu.system_guide') }}</span>
      </div>

      @if($treeKey === 'staff_management')
        <div class="usage-menu-diagram__tree">
          <div class="usage-menu-diagram__row usage-menu-diagram__row--parent is-open">
            <i class="fa fa-users usage-menu-diagram__menu-icon"></i>
            <span>{{ __('menu.staff_management') }}</span>
            <i class="fa fa-angle-down usage-menu-diagram__caret"></i>
          </div>
          <div class="usage-menu-diagram__sub">
            @foreach($staffItems as $sk)
              {!! $renderTargetRow(
                $sk,
                'fa fa-'.($sk === 'roles' ? 'shield' : 'user').' usage-menu-diagram__sub-icon',
                __('menu.'.$sk)
              ) !!}
            @endforeach
          </div>
        </div>
      @elseif($expandedGroup && isset($groups[$expandedGroup]))
        @php $g = $groups[$expandedGroup]; @endphp
        <div class="usage-menu-diagram__tree">
          <div class="usage-menu-diagram__row usage-menu-diagram__row--parent is-open">
            <i class="fa {{ $g['icon'] }} usage-menu-diagram__menu-icon"></i>
            <span>{{ __('menu.'.$expandedGroup) }}</span>
            <i class="fa fa-angle-down usage-menu-diagram__caret"></i>
          </div>
          <div class="usage-menu-diagram__sub">
            @foreach($g['items'] as $sk)
              {!! $renderTargetRow(
                $sk,
                'fa fa-circle-o usage-menu-diagram__dot',
                __('menu.'.$sk)
              ) !!}
            @endforeach
          </div>
        </div>
      @endif
    </div>
    <div class="usage-menu-diagram__content">
      <div class="usage-menu-diagram__callout" aria-hidden="true">
        <span class="usage-menu-diagram__callout-shaft"></span>
      </div>
      <div class="usage-menu-diagram__mock-page">
        <p class="usage-menu-diagram__mock-label mb-1">{{ __('help.getting_started.diagram_opens_label') }}</p>
        <p class="usage-menu-diagram__mock-title mb-1">{{ $stepTitle }}</p>
        <p class="usage-menu-diagram__mock-hint mb-0">{{ __('help.getting_started.diagram_mock_hint') }}</p>
        @if($openUrl)
          <a href="{{ $openUrl }}" class="btn btn-sm btn-primary usage-menu-diagram__mock-btn mt-2">
            <i class="fa fa-external-link"></i> {{ __('help.getting_started.open_screen') }}
          </a>
        @endif
      </div>
    </div>
  </div>
</div>

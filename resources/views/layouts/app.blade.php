<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta name="description" content="SpareParts POS - SaaS Management System">
    <title>@yield('title', 'SpareParts POS')</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
      (function () {
        try {
          if (localStorage.getItem('app-theme') === 'dark') document.documentElement.classList.add('theme-dark');
        } catch (e) {}
      })();
    </script>
    <!-- Main CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('panel-assets/css/main.css') }}">
    <!-- Font-icon css-->
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body, .app-menu__item, .app-title, .tile-title, h1, h2, h3, h4, h5, h6 {
            font-family: 'Century Gothic', 'Segoe UI', sans-serif !important;
        }
        .app-header {
            background-color: #940000 !important;
        }
        .app-header__logo {
            background-color: #940000 !important;
            font-family: 'Century Gothic', 'Segoe UI', sans-serif !important;
            font-weight: 700 !important;
            font-size: 14px !important;
            letter-spacing: 0.2px;
            line-height: 1.15 !important;
            display: flex !important;
            align-items: center;
            justify-content: flex-start;
            padding: 0 12px !important;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            text-transform: none;
        }
        @media (min-width: 768px) {
            .app-header__logo {
                width: 230px;
                max-width: 230px;
                height: 50px;
                font-size: 13px !important;
            }
            .app-header__logo.is-long-name {
                font-size: 12px !important;
            }
            .app-header__logo.is-very-long-name {
                font-size: 11px !important;
                letter-spacing: 0;
            }
            .app-nav__icon-btn .fa {
                font-size: 1.33333333em;
            }
            .app-nav__badge {
                position: absolute;
                top: 8px;
                right: 2px;
                font-size: 0.65rem;
            }
            .app-nav__icon-btn--badge {
                position: relative;
            }
        }
        @media (max-width: 767.98px) {
            .app-header {
                display: flex;
                align-items: center;
                height: 50px;
                padding: 0 6px 0 0;
                gap: 0;
            }
            .app-sidebar__toggle {
                flex: 0 0 42px;
                width: 42px;
                height: 50px;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0;
                line-height: 1;
            }
            .app-sidebar__toggle:before {
                font-size: 20px;
            }
            .app-header__logo {
                flex: 1 1 auto;
                min-width: 0;
                max-width: none;
                width: auto;
                height: 50px;
                text-align: left;
                font-size: 13px !important;
                font-weight: 700 !important;
                padding: 0 8px 0 2px !important;
                line-height: 1.15 !important;
            }
            .app-header__logo.is-long-name {
                font-size: 12px !important;
            }
            .app-header__logo.is-very-long-name {
                font-size: 11px !important;
            }
            .app-nav--toolbar {
                flex: 0 0 auto;
                display: flex;
                align-items: center;
                gap: 1px;
                margin: 0;
                padding: 3px;
                background: rgba(0, 0, 0, 0.15);
                border-radius: 10px;
            }
            .app-nav--toolbar > li {
                flex-shrink: 0;
                display: flex;
                align-items: center;
            }
            .app-nav__branch { order: 1; }
            .app-nav__business { order: 2; }
            .app-nav__language { order: 3; }
            .app-nav__notify { order: 4; }
            .app-nav__profile { order: 5; }
            .app-nav__icon-btn {
                display: flex !important;
                align-items: center;
                justify-content: center;
                width: 36px;
                height: 36px;
                padding: 0 !important;
                margin: 0;
                border-radius: 8px;
                line-height: 1;
            }
            .app-nav__icon-btn .fa {
                font-size: 17px;
                width: 17px;
                text-align: center;
            }
            .app-nav__icon-btn:hover,
            .app-nav__icon-btn:focus {
                background: rgba(255, 255, 255, 0.14);
            }
            .app-nav__profile .app-nav__icon-btn {
                background: rgba(255, 255, 255, 0.1);
            }
            .app-nav__icon-btn--badge {
                position: relative;
            }
            .app-nav__badge {
                position: absolute;
                top: 2px;
                right: 2px;
                min-width: 16px;
                height: 16px;
                padding: 0 4px;
                font-size: 0.6rem;
                line-height: 16px;
                border-radius: 999px;
            }
            .app-search {
                display: none !important;
            }
        }
        @media (max-width: 575.98px) {
            .app-header__logo {
                font-size: 13px;
            }
            .app-nav--toolbar {
                gap: 0;
                padding: 2px;
            }
            .app-nav__icon-btn {
                width: 34px;
                height: 34px;
            }
            .app-nav__icon-btn .fa {
                font-size: 16px;
                width: 16px;
            }
        }
        @media (max-width: 380px) {
            .app-header__logo {
                font-size: 12px;
                padding-right: 4px;
            }
            .app-sidebar__toggle {
                flex: 0 0 38px;
                width: 38px;
            }
            .app-nav__icon-btn {
                width: 32px;
                height: 32px;
            }
            .app-nav__icon-btn .fa {
                font-size: 15px;
                width: 15px;
            }
        }
        .app-sidebar__user {
            padding: 12px 12px !important;
            margin-bottom: 0 !important;
            background: #e9ecef;
            color: #212529;
            border-bottom: 1px solid rgba(0, 0, 0, 0.15);
        }
        .app-sidebar__user-avatar-container {
            flex: 0 0 auto;
            margin-right: 12px;
        }
        .app-sidebar__user-avatar,
        .app-sidebar__user-initial {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            margin: 0 !important;
            border-radius: 2px !important;
            object-fit: cover;
        }
        .app-sidebar__user-initial {
            background: #940000;
            color: #fff;
            font-size: 20px;
            font-weight: 700;
        }
        .app-sidebar__user > div:last-child {
            min-width: 0;
        }
        .app-sidebar__user-name {
            color: #111 !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .app-sidebar__user-designation {
            color: #6c7a89 !important;
            font-size: 12px !important;
            text-transform: uppercase;
        }
        .app-menu {
            margin-bottom: 0;
            padding-bottom: 20px;
        }
        .app-menu > li {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .app-menu__item.active, .app-menu__item:hover, .app-menu__item:focus {
            background: #0d1214 !important;
            border-left-color: #940000 !important;
            color: #fff !important;
        }
        .app-menu > li.treeview.has-active > .app-menu__item,
        .app-menu > li.treeview.is-expanded > .app-menu__item {
            background: #0d1214 !important;
            border-left-color: #940000 !important;
        }
        .danger-zone-menu > .danger-zone-menu__toggle,
        .danger-zone-menu > .danger-zone-menu__toggle .app-menu__icon,
        .danger-zone-menu > .danger-zone-menu__toggle .app-menu__label {
            color: #ff6b6b !important;
        }
        .app-menu > li.danger-zone-menu.is-expanded > .danger-zone-menu__toggle,
        .app-menu > li.danger-zone-menu > .danger-zone-menu__toggle:hover {
            background: rgba(220, 53, 69, 0.18) !important;
            border-left-color: #dc3545 !important;
        }
        .app-sidebar .danger-zone-menu__items .treeview-item.active,
        .app-sidebar .danger-zone-menu__items .treeview-item:hover,
        .app-sidebar .danger-zone-menu__items .treeview-item.active .icon,
        .app-sidebar .danger-zone-menu__items .treeview-item:hover .icon {
            color: #ff6b6b !important;
        }
        .app-sidebar .treeview-menu {
            transition: max-height 0.5s ease-in-out !important;
        }
        .app-sidebar .treeview-indicator {
            transition: transform 0.5s ease-in-out !important;
        }
        .app-sidebar .app-menu__item {
            min-height: 45px;
            padding: 12px 14px 12px 13px;
            color: #fff;
            font-size: 15px;
            line-height: 1.3;
        }
        .app-sidebar .app-menu__icon {
            width: 16px;
            min-width: 16px;
            margin-right: 12px;
            color: #fff;
            font-size: 15px;
            text-align: center;
            line-height: 1;
        }
        .app-sidebar .app-menu__label {
            font-size: 15px;
            font-weight: 400;
            flex: 1 1 auto;
            min-width: 0;
        }
        .app-sidebar .treeview-indicator {
            margin-left: auto;
            font-size: 15px;
            color: #b8c7ce;
            opacity: 1;
        }
        .app-sidebar .treeview-menu {
            background: #2a383e !important;
        }
        .app-sidebar .treeview.is-expanded > .treeview-menu {
            padding: 3px 0 5px;
        }
        .app-sidebar .treeview-item {
            min-height: 32px;
            padding: 7px 12px 7px 32px;
            border-left: 3px solid transparent;
            color: #b8c7ce;
            font-size: 13px;
            font-weight: 400;
            line-height: 1.25;
            background: transparent !important;
            transition: background-color 0.2s ease, border-left-color 0.2s ease, color 0.2s ease;
        }
        .app-sidebar .treeview-item:hover,
        .app-sidebar .treeview-item:focus,
        .app-sidebar .treeview-item.active {
            background: #0d1214 !important;
            border-left-color: #940000 !important;
        }
        .app-sidebar .treeview-item .icon {
            width: 14px;
            min-width: 14px;
            margin-right: 11px;
            color: #b8c7ce;
            font-size: 12px;
            text-align: center;
            line-height: 1;
        }
        .app-sidebar .treeview-item:hover,
        .app-sidebar .treeview-item:focus,
        .app-sidebar .treeview-item.active,
        .app-sidebar .treeview-item:hover .icon,
        .app-sidebar .treeview-item.active .icon {
            color: #fff !important;
            text-decoration: none;
        }
        .btn-primary, .bg-primary, .badge-primary { background-color: #940000 !important; border-color: #940000 !important; }
        .app-sidebar__toggle:hover,
        .app-sidebar__toggle:focus,
        .app-sidebar__toggle:active {
            color: #fff !important;
            background-color: #6b0000 !important;
        }
        .btn-primary:hover, .btn-primary:focus, .btn-primary:active, .btn-primary.active,
        .btn-primary:not(:disabled):not(.disabled):active,
        .show > .btn-primary.dropdown-toggle,
        a.bg-primary:hover, button.bg-primary:hover,
        .badge-primary[href]:hover, .badge-primary[href]:focus,
        .sweet-alert button:active {
            background-color: #6b0000 !important;
            border-color: #6b0000 !important;
        }
        a.text-primary:hover, a.text-primary:focus { color: #6b0000 !important; }
        ::selection { color: #fff; background-color: #940000; }
        ::-moz-selection { color: #fff; background-color: #940000; }
        .text-primary { color: #940000 !important; }
        .sweet-overlay {
            background-color: rgba(0, 0, 0, 0.7) !important; /* Darker, more professional overlay */
        }
        .sweet-alert h2 {
            font-family: 'Century Gothic', sans-serif !important;
            font-weight: 700;
        }
        label.control-label:has(+ input[required])::after,
        label.control-label:has(+ select[required])::after,
        label.control-label:has(+ textarea[required])::after {
            content: ' *';
            color: #dc3545;
            font-weight: 700;
        }
        .branch-switch-label {
            color: #fff;
            font-size: 0.85rem;
            max-width: 160px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .header-switch-menu {
            min-width: 240px;
            max-height: 360px;
            overflow-y: auto;
            padding: 6px;
            border: 0;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.16);
        }
        .header-switch-menu .dropdown-header {
            padding: 6px 10px 8px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #8a8f94;
        }
        .header-switch-menu .dropdown-divider { margin: 4px 0; }
        .header-switch-menu form { margin: 0; }
        .header-switch-menu .dropdown-item {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 8px 10px;
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: inherit;
            text-align: left;
            font-size: 14px;
            white-space: normal;
        }
        .header-switch-menu .dropdown-item:hover,
        .header-switch-menu .dropdown-item:focus {
            background: rgba(148, 0, 0, 0.06);
            color: inherit;
        }
        .header-switch-menu .dropdown-item.active,
        .header-switch-menu .dropdown-item:active {
            background: rgba(148, 0, 0, 0.1);
            color: #940000;
            font-weight: 600;
        }
        .header-switch-menu__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 28px;
            width: 28px;
            height: 28px;
            margin-right: 10px;
            border-radius: 50%;
            background: rgba(148, 0, 0, 0.08);
            color: #940000;
            font-size: 13px;
        }
        .header-switch-menu__icon--flag { background: transparent; }
        .header-switch-menu__label { flex: 1 1 auto; min-width: 0; }
        .header-switch-menu__tag {
            display: inline-block;
            margin-left: 6px;
            padding: 1px 6px;
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.06);
            color: #6c757d;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            vertical-align: middle;
        }
        .header-switch-menu__check {
            margin-left: 10px;
            color: #940000;
            visibility: hidden;
        }
        .header-switch-menu .dropdown-item.active .header-switch-menu__check { visibility: visible; }
        .theme-dark .header-switch-menu__tag { background: rgba(255, 255, 255, 0.1); color: #adb5bd; }
        .theme-dark .header-switch-menu .dropdown-item.active,
        .theme-dark .header-switch-menu__check,
        .theme-dark .header-switch-menu__icon { color: #ff8a8a; }

        /* Hide thin Pace bar — use simple page loader instead */
        .pace { display: none !important; }

        .app-page-loader {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            z-index: 999999;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.15);
            pointer-events: none;
            opacity: 1;
            visibility: visible;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .app-page-loader.is-done {
            opacity: 0;
            visibility: hidden;
        }
        .app-page-loader__bar {
            position: absolute;
            top: 0;
            bottom: 0;
            left: -40%;
            width: 40%;
            background: linear-gradient(90deg, transparent, #ffd24d 30%, #ffffff 70%, transparent);
            box-shadow: 0 0 10px rgba(255, 210, 77, 0.9);
            animation: appLoaderSlide 1.1s ease-in-out infinite;
        }
        @keyframes appLoaderSlide {
            0% { left: -40%; }
            100% { left: 100%; }
        }
    </style>
    @yield('styles')
    @stack('styles')
    <style>
        /* Unified statistic cards: white card, maroon top line, label / value / note, no icon. */
        body.app .app-content .widget-small {
            display: flex !important;
            flex-direction: column;
            justify-content: center;
            height: auto !important;
            min-height: 96px;
            margin-bottom: 16px;
            padding: 14px 16px 12px;
            background: #fff !important;
            color: #212529 !important;
            border: 1px solid #e3e3e3;
            border-top: 2px solid #940000 !important;
            border-radius: 2px !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
            overflow: hidden;
            transform: none !important;
        }
        body.app .app-content .widget-small > .icon {
            display: none !important;
        }
        body.app .app-content .widget-small .info {
            display: block;
            flex: 1 1 auto;
            min-width: 0;
            padding: 0 !important;
            color: #212529 !important;
            align-self: stretch;
        }
        body.app .app-content .widget-small .info h4 {
            margin: 0 0 8px !important;
            max-height: none;
            font-size: 11px !important;
            font-weight: 700 !important;
            letter-spacing: 0.04em !important;
            line-height: 1.3;
            text-transform: uppercase;
            color: #212529 !important;
            display: block;
            -webkit-line-clamp: unset;
        }
        body.app .app-content .widget-small .info p {
            margin: 0 !important;
            font-size: 1.45rem !important;
            font-weight: 700 !important;
            line-height: 1.2;
            color: #111;
            white-space: normal !important;
            overflow: visible;
            overflow-wrap: anywhere;
        }
        body.app .app-content .widget-small .info p b {
            font-weight: 700;
        }
        body.app .app-content .widget-small .info > small,
        body.app .app-content .widget-small .info p + p,
        body.app .app-content .widget-small .info > .text-muted {
            display: block;
            margin-top: 4px !important;
            font-size: 12px !important;
            font-weight: 400 !important;
            line-height: 1.35;
            color: #495057 !important;
        }
        body.app .app-content .widget-small .info p small {
            font-size: 12px !important;
            font-weight: 400;
            color: #495057 !important;
        }
        body.app .app-content .widget-small .text-white {
            color: inherit !important;
        }
        .app-broadcast {
            display: flex;
            align-items: stretch;
            margin: -30px -30px 20px;
            padding: 0;
            height: 40px;
            background: #940000;
            color: #fff;
            border: 0;
            border-radius: 0;
            overflow: hidden;
        }
        .app-broadcast__label {
            display: flex;
            align-items: center;
            flex: 0 0 auto;
            gap: 8px;
            padding: 0 16px;
            background: #6b0000;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .app-broadcast__track {
            position: relative;
            flex: 1 1 auto;
            min-width: 0;
            overflow: hidden;
        }
        .app-broadcast__text {
            position: absolute;
            top: 50%;
            left: 0;
            padding-left: 100%;
            font-size: 14px;
            white-space: nowrap;
            transform: translateY(-50%);
            animation: appBroadcastScroll 40s linear infinite;
        }
        .app-broadcast:hover .app-broadcast__text {
            animation-play-state: paused;
        }
        .app-broadcast__close {
            flex: 0 0 40px;
            border: 0;
            background: transparent;
            color: #fff;
            font-size: 22px;
            line-height: 1;
            opacity: 0.85;
            cursor: pointer;
        }
        .app-broadcast__close:hover { opacity: 1; background: rgba(0, 0, 0, 0.15); }
        @keyframes appBroadcastScroll {
            from { transform: translate(0, -50%); }
            to { transform: translate(-100%, -50%); }
        }
        @media (prefers-reduced-motion: reduce) {
            .app-broadcast__text { animation: none; padding-left: 12px; }
        }
        @media (max-width: 480px) {
            .app-broadcast { margin: -15px -15px 15px; }
            .app-broadcast__label span { display: none; }
            .app-broadcast__label { padding: 0 12px; }
        }
        body.app .app-content .stat-card {
            display: flex !important;
            align-items: stretch !important;
            min-height: 96px;
            height: 100%;
            margin-bottom: 0;
            padding: 14px 16px 12px !important;
            background: #fff !important;
            color: #212529 !important;
            border: 1px solid #e3e3e3;
            border-top: 2px solid #940000 !important;
            border-radius: 2px !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
            transform: none !important;
        }
        body.app .app-content .stat-card:hover {
            transform: none !important;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08) !important;
        }
        body.app .app-content .stat-card .stat-icon {
            display: none !important;
        }
        body.app .app-content .stat-card .stat-body {
            display: flex;
            flex-direction: column;
            justify-content: center;
            flex: 1 1 auto;
            min-width: 0;
        }
        body.app .app-content .stat-card .stat-label {
            order: 0;
            margin: 0 0 8px !important;
            color: #212529 !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            letter-spacing: 0.04em !important;
            text-transform: uppercase !important;
            opacity: 1 !important;
        }
        body.app .app-content .stat-card .stat-value {
            order: 1;
            margin: 0 !important;
            color: #111 !important;
            font-size: 1.45rem !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
            overflow-wrap: anywhere;
        }
        body.app .app-content .stat-card .stat-sub {
            order: 2;
            margin-top: 4px !important;
            color: #495057 !important;
            font-size: 12px !important;
            font-weight: 400 !important;
            opacity: 1 !important;
        }
        @media (max-width: 767.98px) {
            body.app .app-content .stat-card {
                min-height: 84px;
                padding: 12px 12px 10px !important;
            }
            body.app .app-content .stat-card .stat-value {
                font-size: 1.15rem !important;
            }
        }
        @media (max-width: 767.98px) {
            body.app .app-content .widget-small {
                min-height: 84px;
                padding: 12px 12px 10px;
            }
            body.app .app-content .widget-small .info p {
                font-size: 1.15rem !important;
            }
        }
    </style>
    @php
        $activeBroadcast = \App\Models\Broadcast::where('is_active', true)->first();
    @endphp
    <link rel="stylesheet" href="{{ asset('css/dark-mode.css') }}?v={{ @filemtime(public_path('css/dark-mode.css')) }}">
    @php
      $appBackground = ! Auth::check() ? null : (Auth::user()->isPlatformAdmin()
          ? \App\Models\Business::resolveBackground((array) platform_settings('admin_appearance', []))
          : Auth::user()->business?->appBackground());
    @endphp
    @if($appBackground)
    <style>
      body.app .app-content {
        background-image: linear-gradient(rgba(245,245,245,{{ $appBackground['overlay'] }}), rgba(245,245,245,{{ $appBackground['overlay'] }})), url('{{ $appBackground['url'] }}');
        background-repeat: {{ $appBackground['tile'] ? 'repeat' : 'no-repeat' }};
        background-size: {{ $appBackground['tile'] ? 'auto, 420px auto' : 'cover' }};
        background-position: center top;
        background-attachment: fixed;
      }
      html.theme-dark body.app .app-content {
        background-image: linear-gradient(rgba(21,25,28,.9), rgba(21,25,28,.9)), url('{{ $appBackground['url'] }}');
      }
    </style>
    @endif
  </head>
  <body class="app sidebar-mini rtl">
    @include('layouts.partials._page-loader')
    <!-- Navbar-->
    @include('layouts.partials._header')
    
    <!-- Sidebar menu-->
    @include('layouts.partials._sidebar')

    <main class="app-content">
      @if($activeBroadcast)
          <div class="app-broadcast alert fade show" role="alert" style="height: {{ max(40, $activeBroadcast->fontSizePx() + 20) }}px;">
              <div class="app-broadcast__label"><i class="fa fa-bullhorn"></i> <span>Announcement</span></div>
              <div class="app-broadcast__track" aria-live="polite">
                  <div class="app-broadcast__text js-broadcast-text"
                       data-speed="{{ $activeBroadcast->pixelsPerSecond() }}"
                       style="color: {{ $activeBroadcast->textColor() }}; font-family: {{ $activeBroadcast->fontCss() }}; font-size: {{ $activeBroadcast->fontSizePx() }}px;">{{ $activeBroadcast->message }}</div>
              </div>
              <button type="button" class="app-broadcast__close" data-dismiss="alert" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
              </button>
          </div>
      @endif

      @if(session('error'))
          <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
              <i class="fa fa-exclamation-circle mr-2"></i> {{ session('error') }}
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
              </button>
          </div>
      @endif

      @if(session()->has('impersonate_staff_original_user'))
          <div class="alert alert-info d-flex justify-content-between align-items-center mb-4 flex-wrap">
              <div class="mb-2 mb-md-0">
                  <i class="fa fa-user-secret mr-2"></i> You are viewing the system as staff: <strong>{{ Auth::user()->name }}</strong>.
              </div>
              <form action="{{ route('stop-impersonating') }}" method="POST">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-dark">
                      <i class="fa fa-arrow-left mr-1"></i> Switch Back to Owner
                  </button>
              </form>
          </div>
      @elseif(session()->has('impersonate_original_user'))
          <div class="alert alert-warning d-flex justify-content-between align-items-center mb-4 flex-wrap">
              <div class="mb-2 mb-md-0">
                  <i class="fa fa-info-circle mr-2"></i> You are currently impersonating <strong>{{ Auth::user()->business?->name ?? 'Business' }}</strong>.
              </div>
              <form action="{{ route('stop-impersonating') }}" method="POST">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-dark">
                      <i class="fa fa-arrow-left mr-1"></i> Switch Back to Admin
                  </button>
              </form>
          </div>
      @endif
      @yield('content')
    </main>

    @include('layouts.partials._support-fab')

    <!-- Essential javascripts for application to work-->
    <script src="{{ asset('panel-assets/js/jquery-3.2.1.min.js') }}"></script>
    <script src="{{ asset('panel-assets/js/popper.min.js') }}"></script>
    <script src="{{ asset('panel-assets/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('panel-assets/js/main.js') }}"></script>
    <script>
    window.appBroadcastSpeed = function (el) {
        if (!el) return;
        var speed = parseFloat(el.getAttribute('data-speed')) || 50;
        var distance = el.offsetWidth || 600;
        el.style.animationDuration = Math.max(6, distance / speed).toFixed(1) + 's';
    };
    (function () {
        function applyAll() {
            document.querySelectorAll('.js-broadcast-text').forEach(window.appBroadcastSpeed);
        }
        if (document.readyState !== 'loading') { applyAll(); } else { document.addEventListener('DOMContentLoaded', applyAll); }
        window.addEventListener('resize', applyAll);
    })();
    </script>
    <script>
    (function ($) {
        'use strict';

        function collapseTreeview($treeview) {
            var $submenu = $treeview.children('.treeview-menu');
            if (!$submenu.length) {
                return;
            }

            var height = $submenu[0].scrollHeight;
            $submenu.css('max-height', height + 'px');
            void $submenu[0].offsetHeight;
            $treeview.removeClass('is-expanded');
            $submenu.css('max-height', '0');
        }

        function expandTreeview($treeview) {
            var $submenu = $treeview.children('.treeview-menu');
            if (!$submenu.length) {
                return;
            }

            $treeview.addClass('is-expanded');
            $submenu.css('max-height', '0');
            void $submenu[0].offsetHeight;
            $submenu.css('max-height', $submenu[0].scrollHeight + 'px');
            $submenu.one('transitionend', function (event) {
                if (event.target !== $submenu[0] || !$treeview.hasClass('is-expanded')) {
                    return;
                }

                $submenu.css('max-height', '');
            });
        }

        $(function () {
            var $treeviewMenu = $('.app-menu');

            $("[data-toggle='treeview']").off('click').on('click', function (event) {
                event.preventDefault();

                var $treeview = $(this).parent('.treeview');
                if (!$treeview.length) {
                    return;
                }

                if (!$treeview.hasClass('is-expanded')) {
                    $treeviewMenu.find('.treeview.is-expanded').not($treeview).each(function () {
                        collapseTreeview($(this));
                    });
                    expandTreeview($treeview);
                } else {
                    collapseTreeview($treeview);
                }
            });
        });
    })(jQuery);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      (function () {
        var loader = document.getElementById('appPageLoader');
        if (!loader) return;

        function hideLoader() {
          loader.classList.add('is-done');
          loader.setAttribute('aria-busy', 'false');
        }

        function showLoader() {
          loader.classList.remove('is-done');
          loader.setAttribute('aria-busy', 'true');
        }

        window.appShowPageLoader = showLoader;
        window.appHidePageLoader = hideLoader;

        if (document.readyState === 'complete' || document.readyState === 'interactive') {
          // Hide as soon as DOM is ready; don't wait on slow third-party assets.
          setTimeout(hideLoader, 0);
        }
        window.addEventListener('DOMContentLoaded', hideLoader);
        window.addEventListener('load', hideLoader);
        // Safety: never block UI if something hangs
        setTimeout(hideLoader, 4000);

        document.addEventListener('click', function (e) {
          var link = e.target.closest('a[href]');
          if (!link) return;
          var href = link.getAttribute('href') || '';
          if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
          if (link.target === '_blank' || link.hasAttribute('download')) return;
          if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
          try {
            var url = new URL(link.href, window.location.origin);
            if (url.origin !== window.location.origin) return;
            if (url.pathname === window.location.pathname && url.search === window.location.search) return;
          } catch (err) {
            return;
          }
          showLoader();
          setTimeout(function () {
            if (e.defaultPrevented) {
              hideLoader();
            }
          }, 0);
        }, true);

        // Bubble phase so page scripts can preventDefault (e.g. confirmation modals)
        // before the loader appears.
        document.addEventListener('submit', function (e) {
          var form = e.target;
          if (!(form instanceof HTMLFormElement)) return;
          if (e.defaultPrevented) return;
          if (form.getAttribute('data-no-loader') !== null) return;
          if (form.target === '_blank') return;
          showLoader();
        });

        window.addEventListener('pageshow', function (event) {
          if (event.persisted) hideLoader();
        });
      })();
    </script>

    <script type="text/javascript">
      const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
          toast.addEventListener('mouseenter', Swal.stopTimer)
          toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
      });

      @if(session('success'))
        Toast.fire({
          icon: 'success',
          title: @json(session('success'))
        });
      @endif

      @if(session('error'))
        Toast.fire({
          icon: 'error',
          title: @json(session('error'))
        });
      @endif

      @if(session('warning'))
        Toast.fire({
          icon: 'warning',
          title: @json(session('warning'))
        });
      @endif

      @if(session('info'))
        Toast.fire({
          icon: 'info',
          title: @json(session('info'))
        });
      @endif

      @if(($newNoteReminderToasts ?? collect())->isNotEmpty())
        @if($newNoteReminderToasts->count() === 1)
          Toast.fire({
            icon: 'warning',
            title: @json('Reminder: ' . $newNoteReminderToasts->first()->displayTitle())
          });
        @else
          Toast.fire({
            icon: 'warning',
            title: @json($newNoteReminderToasts->count() . ' note reminders are due')
          });
        @endif
      @endif

      function setSubmitButtonLoading(form, clickedButton) {
        if (!form || form.dataset.noSubmitLoader !== undefined) return;
        if (form.dataset.submitLoading === '1') return;
        form.dataset.submitLoading = '1';

        const buttons = clickedButton
          ? [clickedButton]
          : Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'));

        buttons.forEach(function (btn) {
          if (btn.dataset.submitLoading === '1') return;
          btn.dataset.submitLoading = '1';
          btn.disabled = true;

          if (btn.tagName === 'BUTTON') {
            if (!btn.dataset.originalHtml) {
              btn.dataset.originalHtml = btn.innerHTML;
            }
            const label = btn.textContent.replace(/\s+/g, ' ').trim() || 'Processing...';
            btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> ' + label;
          } else if (btn.tagName === 'INPUT') {
            if (!btn.dataset.originalValue) {
              btn.dataset.originalValue = btn.value;
            }
            btn.value = 'Processing...';
          }
        });
      }

      let lastSubmitButton = null;

      document.addEventListener('click', function (e) {
        const btn = e.target.closest('button[type="submit"], input[type="submit"]');
        if (btn) {
          lastSubmitButton = btn;
        }
      }, true);

      document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (e.defaultPrevented) return;
        if (form.dataset.noSubmitLoader !== undefined) return;
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
          if (typeof form.reportValidity === 'function') {
            form.reportValidity();
          }
          return;
        }

        const clicked = lastSubmitButton && lastSubmitButton.form === form ? lastSubmitButton : null;
        setSubmitButtonLoading(form, clicked);
        lastSubmitButton = null;
      });

      function confirmAction(e, title = "Are you sure?", text = "You won't be able to revert this!") {
        e.preventDefault();
        e.stopPropagation();
        var form = e.target.closest('form') || e.target.form;
        var button = e.target.closest('button, input[type="submit"]') || e.target;
        if (!form) return;
        Swal.fire({
          title: title,
          text: text,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#940000',
          cancelButtonColor: '#6c757d',
          confirmButtonText: 'Yes, proceed!',
          cancelButtonText: 'No, cancel!'
        }).then((result) => {
          if (result.isConfirmed) {
            setSubmitButtonLoading(form, button);
            if (typeof window.appShowPageLoader === 'function') {
              window.appShowPageLoader();
            }
            form.submit();
          }
        });
      }
    </script>
    
    @stack('scripts')
    @yield('scripts')
  </body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta name="description" content="SpareParts POS - SaaS Management System">
    <title>@yield('title', 'SpareParts POS')</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.favicon')
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
        /* Page titles use negative top margin; cancel it when renewal banner is above page content */
        .app-content > .subscription-renewal-banner {
            position: relative;
            z-index: 2;
            margin-bottom: 1rem;
        }
        .app-content:has(> .subscription-renewal-banner) .app-title {
            margin-top: 0 !important;
        }
        .app-title > div:first-child {
            flex: 1 1 auto;
            min-width: 0;
            padding-right: 12px;
        }
        /* Breadcrumb row: manual icon + path crumbs aligned on one line */
        .app-title .app-breadcrumb.breadcrumb {
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: flex-end;
            align-self: center;
            gap: 0;
            padding: 0;
            margin: 0;
            min-height: 32px;
            max-width: min(100%, 520px);
            background: transparent;
            border-radius: 0;
            list-style: none;
        }
        .app-title .app-breadcrumb .breadcrumb-item {
            display: inline-flex;
            align-items: center;
            float: none;
            line-height: 1.25;
            padding-top: 0;
            padding-bottom: 0;
            white-space: nowrap;
        }
        .app-title .app-breadcrumb .breadcrumb-item + .breadcrumb-item {
            padding-left: 0.4rem;
        }
        .app-title .app-breadcrumb .breadcrumb-item + .breadcrumb-item::before {
            display: inline-block;
            padding-right: 0.4rem;
            color: #adb5bd;
            line-height: 1;
            vertical-align: middle;
        }
        .app-title .app-breadcrumb .breadcrumb-item a {
            display: inline-flex;
            align-items: center;
            color: inherit;
        }
        .app-title .app-breadcrumb .breadcrumb-item .fa-home,
        .app-title .app-breadcrumb .breadcrumb-item .fa-lg {
            font-size: 1.05rem;
            line-height: 1;
            vertical-align: middle;
        }
        .app-title .app-breadcrumb .breadcrumb-manual-item {
            flex: 0 0 auto;
            padding-left: 0;
            padding-right: 0;
            margin-right: 0.5rem;
        }
        .app-title .app-breadcrumb .breadcrumb-manual-item + .breadcrumb-item::before {
            display: none;
        }
        .app-title .app-breadcrumb .breadcrumb-manual-item + .breadcrumb-item {
            padding-left: 0;
        }
        .breadcrumb-manual-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #e9ecef;
            color: #1a1a1a;
            text-decoration: none;
            line-height: 1;
            flex-shrink: 0;
            transition: background-color 0.15s ease, box-shadow 0.15s ease;
        }
        .breadcrumb-manual-link:hover {
            background: #dee2e6;
            color: #000;
            text-decoration: none;
        }
        .breadcrumb-manual-link.is-active {
            background: #940000;
            color: #fff;
            box-shadow: 0 0 0 2px rgba(148, 0, 0, 0.2);
        }
        .breadcrumb-manual-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            line-height: 1;
        }
        @media (min-width: 768px) {
            .app-title .app-breadcrumb.breadcrumb {
                flex-wrap: nowrap;
            }
        }
        @media (max-width: 767.98px) {
            .app-title .app-breadcrumb.breadcrumb {
                justify-content: flex-start;
                flex-wrap: wrap;
                align-content: center;
                row-gap: 4px;
                column-gap: 0;
                max-width: 100%;
                width: 100%;
            }
        }
        html.theme-dark .app-title .app-breadcrumb .breadcrumb-item + .breadcrumb-item::before {
            color: var(--dm-text-muted, #7a8288);
        }
        html.theme-dark .breadcrumb-manual-link {
            background: var(--dm-surface-2, #272d32);
            color: var(--dm-text, #e3e6e8);
        }
        html.theme-dark .breadcrumb-manual-link:hover {
            background: var(--dm-border, #363d43);
            color: #fff;
        }
        html.theme-dark .breadcrumb-manual-link.is-active {
            background: #940000;
            color: #fff;
        }
        /* Page manual modal (breadcrumb book icon) — brand-aligned layout */
        .page-manual-modal__dialog { max-width: 1020px; width: calc(100% - 24px); margin: 1rem auto; }
        .page-manual-modal__dialog:has(.page-manual-modal--has-screenshot) { max-width: 920px; }
        .page-manual-modal__dialog--zoomable { max-height: calc(100vh - 2rem); display: flex; flex-direction: column; }
        /* Bootstrap sets .modal-dialog { pointer-events: none } and only .modal-content is auto — toolbar sits outside content */
        .page-manual-modal__dialog--zoomable > .page-manual-modal-zoom { pointer-events: auto; }
        .page-manual-modal-zoom {
            --pm-zoom: 1;
            display: flex; flex-direction: column; width: 100%; min-height: 0; flex: 1;
        }
        .page-manual-modal-zoom__toolbar {
            display: flex; align-items: center; justify-content: flex-end; gap: 6px; flex-shrink: 0;
            padding: 0 0 8px; margin: 0;
        }
        .page-manual-modal-zoom__btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 4px;
            min-width: 34px; height: 32px; padding: 0 10px;
            border: 1px solid #ced4da; border-radius: 6px;
            background: #fff; color: var(--pm-brand-dark, #6d0000);
            font-size: 13px; font-weight: 600; cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,.12);
            transition: background 0.15s, border-color 0.15s, color 0.15s;
        }
        .page-manual-modal-zoom__btn:hover:not(:disabled) {
            background: var(--pm-brand-light, #fff5f5); border-color: var(--pm-brand, #940000); color: var(--pm-brand, #940000);
        }
        .page-manual-modal-zoom__btn:disabled { opacity: 0.45; cursor: not-allowed; }
        .page-manual-modal-zoom__pct {
            min-width: 52px; text-align: center; font-size: 12px; font-weight: 700;
            color: #495057; user-select: none;
        }
        .page-manual-modal-zoom__viewport {
            overflow: auto; flex: 1; min-height: 0; max-height: calc(100vh - 5rem);
            -webkit-overflow-scrolling: touch;
        }
        .page-manual-modal-zoom__sizer { position: relative; min-width: 100%; }
        .page-manual-modal-zoom__inner {
            width: calc(100% / var(--pm-zoom, 1));
            transform: scale(var(--pm-zoom, 1));
            transform-origin: top left;
        }
        .page-manual-modal-zoom .page-manual-modal__content { width: 100%; }
        .page-manual-modal__stack--vertical { display: flex; flex-direction: column; width: 100%; }
        .page-manual-modal__figures {
            background: #f8f9fa; border-bottom: 1px solid #e9ecef;
        }
        .page-manual-modal__steps-column { background: #fff; }
        .page-manual-modal__steps-interleaved { background: #fff; }
        .page-manual-modal__step-section {
            margin-bottom: 1.25rem; padding-bottom: 1.25rem;
            border-bottom: 1px dashed #dee2e6;
        }
        .page-manual-modal__step-section:last-child {
            margin-bottom: 0; padding-bottom: 0; border-bottom: none;
        }
        .page-manual-modal__step-figure { margin-top: 14px; }
        .page-manual-modal--has-screenshot .page-manual-modal__step-figure .page-manual-screenshot__frame {
            border-color: #ced4da;
        }
        .page-manual-modal__content {
            --pm-brand: #940000;
            --pm-brand-dark: #6d0000;
            --pm-brand-light: #fff5f5;
            --pm-brand-soft: #fdeaea;
            border: 2px solid var(--pm-brand); border-radius: 4px; overflow: hidden; background: #fff;
        }
        .page-manual-modal__header-row { display: flex; min-height: 44px; }
        .page-manual-modal__header-dark {
            flex: 1; background: var(--pm-brand); color: #fff; font-weight: 700; font-size: 15px;
            display: flex; align-items: center; padding: 0 16px;
        }
        .page-manual-modal__header-brand {
            flex: 0 0 auto; min-width: 220px;
            background: linear-gradient(135deg, var(--pm-brand) 0%, #c0392b 100%);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 12px 0 14px; border-left: 2px solid var(--pm-brand-dark);
        }
        .page-manual-modal__brand-wrap { display: inline-flex; align-items: center; gap: 8px; }
        .page-manual-modal__brand-logo { object-fit: contain; flex-shrink: 0; }
        .page-manual-modal__brand-text { font-weight: 800; font-size: 13px; color: #fff; letter-spacing: 0.02em; }
        .page-manual-modal__close {
            color: #fff; opacity: 1; text-shadow: none; font-size: 1.75rem; font-weight: 700;
            margin: 0; padding: 0 0 0 8px; line-height: 1;
        }
        .page-manual-modal__close:hover { color: #fff; opacity: 0.85; }
        .page-manual-modal__hero-box {
            display: flex; align-items: center; justify-content: space-between;
            margin: 10px 14px 0; padding: 16px 18px;
            background: var(--pm-brand-light); border: 2px solid var(--pm-brand);
        }
        .page-manual-modal__menu-path { font-size: 13px; color: var(--pm-brand-dark); font-weight: 600; }
        .page-manual-modal__screen-title { font-size: 1.45rem; font-weight: 800; color: var(--pm-brand); }
        .page-manual-modal__hero-icon { font-size: 2.75rem; color: var(--pm-brand); padding-left: 16px; flex-shrink: 0; opacity: 0.9; }
        .page-manual-modal__body { background: #fff; }
        .page-manual-modal__left {
            padding: 14px 16px 18px; background: #f0f0f0; border-right: 2px solid #dee2e6; min-height: 340px;
        }
        .page-manual-modal__preview-label {
            font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em;
            color: #6c757d; margin-bottom: 8px;
        }
        .page-manual-modal__right { padding: 18px 20px 22px; background: #fff; }
        .page-manual-modal__desc-badge {
            display: inline-block; background: var(--pm-brand); color: #fff;
            font-size: 13px; font-weight: 700; padding: 6px 20px; border-radius: 999px;
        }
        .page-manual-modal__screen-ref { font-weight: 700; font-size: 15px; color: #212529; }
        .page-manual-modal__diamond { color: var(--pm-brand); margin-right: 4px; }
        .page-manual-modal__intro { font-size: 14px; color: #495057; line-height: 1.5; }
        .page-manual-modal__step-head {
            display: flex; align-items: flex-start; gap: 10px; margin-bottom: 6px;
        }
        .page-manual-modal__step-badge {
            flex-shrink: 0; width: 26px; height: 26px; border-radius: 4px;
            background: var(--pm-brand); color: #fff; font-weight: 800; font-size: 14px;
            display: inline-flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 6px rgba(148, 0, 0, 0.35);
        }
        .page-manual-modal__step-label { font-size: 14px; color: #212529; line-height: 1.35; padding-top: 2px; }
        .page-manual-modal__step-block {
            background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px;
            padding: 12px 14px;
        }
        .page-manual-modal--has-screenshot .page-manual-modal__step-block {
            border-left: 3px solid var(--pm-brand);
        }
        .page-manual-modal__step-text { font-size: 13px; line-height: 1.55; color: #495057; margin: 0; }
        .page-manual-modal__step-details {
            font-size: 12px; line-height: 1.5; color: #5c636a; padding-left: 0; list-style: none;
            margin: 8px 0 0 36px;
        }
        .page-manual-modal__step-details li { position: relative; padding-left: 1rem; margin-bottom: 4px; }
        .page-manual-modal__step-details li::before {
            content: '✓'; position: absolute; left: 0; color: var(--pm-brand); font-weight: 700; font-size: 12px;
        }
        .page-manual-modal__footer-meta { padding: 8px 20px 4px; background: #fff; }
        .page-manual-modal__footer-rule {
            border: none; border-top: 2px dashed #ced4da; margin: 0 0 10px;
        }
        .page-manual-modal__footer-logo-row {
            display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 700; color: var(--pm-brand);
        }
        .page-manual-modal__footer {
            display: flex; align-items: center; justify-content: flex-end;
            background: var(--pm-brand); padding: 10px 14px; border-top: 2px solid var(--pm-brand-dark);
        }
        .page-manual-modal__footer-spacer { flex: 1; }
        .page-manual-modal__footer-actions { display: flex; gap: 8px; }
        .page-manual-modal__btn { min-width: 88px; font-weight: 600; color: var(--pm-brand-dark); border: 1px solid #fff; background: #fff; }
        .page-manual-modal__btn:hover { background: var(--pm-brand-light); color: var(--pm-brand); }
        .page-manual-modal__btn--close { background: var(--pm-brand-dark); color: #fff; border-color: var(--pm-brand-dark); }
        .page-manual-modal__btn--close:hover { background: #5a0000; color: #fff; }
        .page-manual-wireframe {
            position: relative; background: #fff; border: 2px solid #868e96;
            min-height: 320px; overflow: hidden; box-shadow: 0 3px 12px rgba(0,0,0,.15);
        }
        .page-manual-modal--has-screenshot .page-manual-modal__hero-box { margin-bottom: 0; }
        .page-manual-modal--has-screenshot .page-manual-modal__intro {
            font-size: 13px; margin-bottom: 12px !important;
        }
        .page-manual-wireframe--screenshot {
            min-height: 0; border: none; background: transparent; box-shadow: none;
        }
        .page-manual-screenshots-stack {
            display: flex; flex-direction: column; gap: 12px;
        }
        .page-manual-screenshot { width: 100%; }
        .page-manual-screenshot__frame {
            position: relative; line-height: 0; border-radius: 8px; overflow: hidden;
            border: 1px solid #dee2e6; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        }
        .page-manual-screenshot__img {
            width: 100%; height: auto; display: block;
        }
        .page-manual-pin {
            position: absolute; transform: translate(-50%, -50%); z-index: 5;
            pointer-events: none;
        }
        .page-manual-pin__num {
            min-width: 26px; height: 26px; padding: 0 6px; border-radius: 3px;
            background: var(--pm-brand, #940000); color: #fff;
            font-weight: 800; font-size: 14px; line-height: 1;
            display: inline-flex; align-items: center; justify-content: center;
            border: 2px solid #fff; outline: 1px solid var(--pm-brand-dark, #6d0000);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
        }
        .page-manual-modal__content .pmwf-app-chrome {
            display: flex; align-items: center; gap: 10px; height: 32px; padding: 0 10px;
            background: var(--pm-brand); color: #fff; font-size: 11px; font-weight: 700;
        }
        .pmwf-app-chrome__burger { opacity: 0.95; font-size: 12px; }
        .pmwf-app-body { position: relative; min-height: 280px; background: #f8f9fa; padding-bottom: 8px; }
        .pmwf-inner-title { height: 14px; width: 42%; margin: 10px 10px 6px; background: #dee2e6; border-radius: 2px; }
        .pmwf-screen-toolbar {
            display: flex; justify-content: flex-end; gap: 8px; padding: 4px 10px 8px;
        }
        .pmwf-labeled-btn {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 64px; height: 26px; padding: 0 10px; font-size: 11px; font-weight: 700;
            color: #495057; background: #e9ecef; border: 1px solid #adb5bd; border-radius: 3px;
        }
        .pmwf-labeled-btn--search { min-width: 72px; }
        .page-manual-modal__content .pmwf-labeled-btn--accent { background: var(--pm-brand); color: #fff; border-color: var(--pm-brand-dark); }
        .pmwf-labeled-btn--block { display: flex; margin: 0 10px 8px; width: calc(100% - 20px); }
        .pmwf-data-table-wrap { padding: 0 8px; }
        .pmwf-data-table {
            width: 100%; border-collapse: collapse; font-size: 10px; background: #fff;
            border: 1px solid #dee2e6;
        }
        .pmwf-data-table th {
            background: #e9ecef; font-weight: 700; text-align: left; padding: 5px 6px;
            border: 1px solid #dee2e6; color: #495057;
        }
        .pmwf-data-table td { padding: 5px 6px; border: 1px solid #eee; color: #868e96; height: 22px; }
        .pmwf-status { display: inline-block; width: 100%; min-width: 48px; height: 14px; border-radius: 2px; }
        .pmwf-status--ok { background: transparent; }
        .pmwf-status--warn { background: #fd7e14; }
        .pmwf-status--danger { background: #dc3545; }
        .pmwf-screen-footer-btns {
            display: flex; justify-content: space-between; gap: 10px; padding: 10px 10px 6px; margin-top: 6px;
        }
        .pmwf-screen-footer-btns--pos { justify-content: flex-end; }
        .pmwf-receiving-layout { display: flex; gap: 8px; padding: 8px; min-height: 260px; }
        .pmwf-receiving-main { flex: 1; min-width: 0; }
        .pmwf-receiving-aside { flex: 0 0 28%; display: flex; flex-direction: column; gap: 8px; }
        .pmwf-receiving-fields { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
        .pmwf-labeled-field {
            flex: 1; min-width: 72px; height: 26px; font-size: 9px; font-weight: 700; color: #495057;
            display: inline-flex; align-items: center; justify-content: center;
            background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 3px;
        }
        .pmwf-labeled-field--sm { flex: 0 0 56px; }
        .pmwf-receiving-search { margin-bottom: 8px; }
        .pmwf-data-table--receiving { font-size: 9px; margin-bottom: 0; }
        .pmwf-receiving-summary {
            flex: 1; min-height: 100px; border-radius: 6px;
            background: linear-gradient(135deg, #940000 0%, #c0392b 100%);
            border: 1px solid #6d0000;
        }
        .page-manual-modal__content .page-manual-callout {
            position: absolute; transform: translate(-50%, -50%); z-index: 8;
            min-width: 26px; height: 24px; padding: 0 7px; border-radius: 2px;
            background: var(--pm-brand); color: #fff; font-weight: 800; font-size: 14px;
            display: inline-flex; align-items: center; justify-content: center;
            border: 2px solid #fff; outline: 1px solid var(--pm-brand-dark); box-shadow: 0 2px 8px rgba(148, 0, 0, 0.45);
        }
        .page-manual-modal.show { display: block !important; }
        body.page-manual-open .modal-backdrop { opacity: 0.55; }
        .pmwf-bar { height: 10px; background: #940000; }
        .pmwf-bar--muted { background: #e9ecef; height: 8px; }
        .pmwf-toolbar { display: flex; gap: 8px; padding: 10px; align-items: center; }
        .pmwf-search { flex: 1; height: 28px; background: #f1f3f5; border-radius: 4px; border: 1px solid #dee2e6; }
        .pmwf-btn--primary { background: #940000; height: 28px; border-radius: 4px; min-width: 56px; }
        .pmwf-btn--inline { display: inline-block; width: 48px; }
        .pmwf-btn--bottom { position: absolute; bottom: 12px; right: 12px; width: 56px; height: 28px; }
        .pmwf-ghost-label { font-size: 0; }
        .pmwf-page-title-bar { height: 28px; background: #fff; border-bottom: 1px solid #eee; margin-bottom: 4px; }
        .pmwf-table { padding: 6px 8px 10px; }
        .pmwf-table-head { height: 22px; background: #e9ecef; border-radius: 3px; margin-bottom: 6px; }
        .pmwf-table-cols {
            display: grid; grid-template-columns: 1fr 1.2fr 1fr 1fr; gap: 4px;
            font-size: 9px; font-weight: 700; color: #868e96; text-transform: uppercase;
            padding: 4px 6px; background: #e9ecef; border: 1px solid #dee2e6; margin-bottom: 4px;
        }
        .pmwf-table-row { height: 16px; background: #fff; border: 1px solid #eee; margin-bottom: 3px; }
        .pmwf-table-actions { display: flex; justify-content: flex-end; gap: 6px; margin-top: 10px; padding-top: 8px; border-top: 1px dashed #dee2e6; }
        .pmwf-btn--ghost { width: 64px; height: 24px; background: #e9ecef; border-radius: 3px; display: inline-block; }
        .pmwf-btn--sm { width: 72px; height: 24px; }
        .pmwf-cards { display: flex; gap: 6px; padding: 10px; }
        .pmwf-card { flex: 1; height: 36px; background: #f1f3f5; border: 1px solid #dee2e6; border-radius: 4px; }
        .page-manual-modal__content .pmwf-card--active { border-color: var(--pm-brand); background: var(--pm-brand-light); }
        .pmwf-row .pmwf-btn--primary { margin: 0 10px 8px; width: calc(100% - 20px); }
        .pmwf-tabs { display: flex; gap: 4px; padding: 10px 10px 0; }
        .pmwf-tab { flex: 1; height: 24px; background: #e9ecef; border-radius: 4px 4px 0 0; }
        .page-manual-modal__content .pmwf-tab--active { background: var(--pm-brand); opacity: 0.85; }
        .pmwf-form { padding: 12px; }
        .pmwf-form--padded { padding-top: 16px; }
        .pmwf-field { height: 22px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 3px; margin-bottom: 10px; }
        .pmwf-field--lg { height: 28px; }
        .pmwf-field--select, .pmwf-field--date { flex: 1; }
        .pmwf-filters { display: flex; gap: 8px; padding: 12px; align-items: center; }
        .pmwf-pos { display: flex; flex-direction: column; height: 100%; min-height: 260px; }
        .pmwf-pos-search { height: 32px; margin: 10px; background: #f1f3f5; border-radius: 4px; border: 1px solid #dee2e6; }
        .pmwf-pos-grid { display: flex; flex-wrap: wrap; gap: 6px; padding: 0 10px; flex: 1; }
        .pmwf-pos-tile { width: calc(33.33% - 4px); height: 40px; background: #f8f9fa; border: 1px solid #eee; border-radius: 4px; }
        .pmwf-pos-cart { border-top: 1px solid #dee2e6; padding: 10px; margin-top: 8px; }
        .pmwf-dash-cards { display: flex; gap: 10px; padding: 14px; }
        .pmwf-dash-card { flex: 1; height: 56px; background: linear-gradient(135deg, #fff5f5, #f8f9fa); border: 1px solid #dee2e6; border-radius: 6px; }
        .pmwf-dash-links { padding: 0 14px 14px; }
        .pmwf-dash-link { height: 32px; background: #f1f3f5; border-radius: 4px; margin-bottom: 8px; }
        html.theme-dark .page-manual-modal__content { background: var(--dm-surface, #1b2023); border-color: var(--dm-border, #363d43); }
        html.theme-dark .page-manual-modal__left { background: var(--dm-surface-2, #272d32); border-color: var(--dm-border, #363d43); }
        html.theme-dark .page-manual-modal__right { background: var(--dm-surface, #1b2023); }
        html.theme-dark .page-manual-wireframe { background: var(--dm-surface, #1b2023); border-color: var(--dm-border, #363d43); }
        html.theme-dark .page-manual-modal__screen-title,
        html.theme-dark .page-manual-modal__screen-ref,
        html.theme-dark .page-manual-modal__step-title,
        html.theme-dark .page-manual-modal__step-text { color: var(--dm-text, #e3e6e8); }
        html.theme-dark .page-manual-modal__footer-meta { background: var(--dm-surface, #1b2023); }
        html.theme-dark .pmwf-data-table { background: var(--dm-surface, #1b2023); border-color: var(--dm-border, #363d43); }
        html.theme-dark .pmwf-data-table th { background: var(--dm-surface-2, #272d32); color: var(--dm-text-muted, #9aa0a6); border-color: var(--dm-border, #363d43); }
        html.theme-dark .pmwf-data-table td { border-color: var(--dm-border, #363d43); color: var(--dm-text-muted, #9aa0a6); }
        html.theme-dark .page-manual-modal__content {
            --pm-brand-light: #2a1f1f;
            --pm-brand-soft: #331818;
        }
        html.theme-dark .page-manual-modal__hero-box { background: var(--pm-brand-light); border-color: var(--pm-brand); }
        html.theme-dark .page-manual-modal__menu-path { color: #e8b4b4; }
        html.theme-dark .page-manual-modal__screen-title { color: #f5d0d0; }
        html.theme-dark .page-manual-modal__footer-logo-row { color: #f5d0d0; }
        html.theme-dark .page-manual-modal-zoom__btn { background: var(--dm-surface-2, #272d32); border-color: var(--dm-border, #363d43); color: #f5d0d0; }
        html.theme-dark .page-manual-modal__figures { background: var(--dm-surface-2, #272d32); border-color: var(--dm-border, #363d43); }
        html.theme-dark .page-manual-modal__steps-column,
        html.theme-dark .page-manual-modal__steps-interleaved { background: var(--dm-surface, #1b2023); }
        html.theme-dark .page-manual-modal__step-section { border-color: var(--dm-border, #363d43); }
        html.theme-dark .pmwf-app-body { background: var(--dm-surface-2, #272d32); }
        @media (max-width: 991.98px) {
            .page-manual-modal__left { border-right: none !important; border-bottom: 1px solid #dee2e6; }
        }
        @media print {
            body * { visibility: hidden; }
            .page-manual-printable, .page-manual-printable * { visibility: visible; }
            .page-manual-printable { position: absolute; left: 0; top: 0; width: 100%; }
            .page-manual-modal__footer { display: none !important; }
        }
        @media (max-width: 480px) {
            .app-content:has(> .subscription-renewal-banner) .app-title {
                margin-top: 0 !important;
            }
        }
        .app-header__datetime {
            flex: 1 1 auto;
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 0 10px;
            color: rgba(255, 255, 255, 0.92);
            font-size: 13px;
            font-weight: 600;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .app-header__time-sep {
            opacity: 0.75;
        }
        @media (max-width: 767.98px) {
            .app-header__datetime {
                flex: 0 1 auto;
                max-width: 42%;
                padding: 0 4px 0 0;
                font-size: 11px;
                font-weight: 700;
            }
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
        .app-nav__branch-toggle {
            display: inline-flex;
            align-items: center;
            max-width: 200px;
            padding: 12px 10px !important;
        }
        .branch-switch-label {
            color: #fff;
            font-size: 0.85rem;
            max-width: 160px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        @media (max-width: 767.98px) {
            .app-nav__branch-toggle {
                max-width: 88px;
                padding: 10px 6px !important;
            }
            .branch-switch-label {
                max-width: 64px;
                font-size: 0.75rem;
            }
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
    @include('partials.business-type-tabs-styles')
    @yield('styles')
    @if(auth()->check() && auth()->user()->business && ! auth()->user()->canCollectCustomerPayments())
      <style>.open-payment-modal-btn { display: none !important; }</style>
    @endif
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
      @unless(request()->routeIs('subscription.upgrade') || request()->is('home'))
        @include('partials.subscription-renewal-banner')
      @endunless
      @yield('content')
    </main>

    @include('layouts.partials._support-fab')

    @if(tenant_system_guide_visible())
      @include('help.partials.page-manual-modal')
    @endif

    <!-- Essential javascripts for application to work-->
    <script src="{{ asset('panel-assets/js/jquery-3.2.1.min.js') }}"></script>
    <script src="{{ asset('panel-assets/js/popper.min.js') }}"></script>
    <script src="{{ asset('panel-assets/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('panel-assets/js/main.js') }}"></script>
    <script>
    (function ($) {
      if (!$) return;
      $(document).on('click', '.js-page-manual-open, [data-target="#pageManualModal"]', function (e) {
        e.preventDefault();
        var $m = $('#pageManualModal');
        if ($m.length) {
          $m.modal('show');
        }
      });
      var pageManualZoomLevels = [0.75, 1, 1.25, 1.5, 1.75, 2, 2.5, 3];

      function pageManualNearestZoom(level) {
        var best = pageManualZoomLevels[0];
        var diff = Math.abs(level - best);
        for (var i = 1; i < pageManualZoomLevels.length; i++) {
          var d = Math.abs(level - pageManualZoomLevels[i]);
          if (d < diff) {
            diff = d;
            best = pageManualZoomLevels[i];
          }
        }
        return best;
      }

      function pageManualMeasureZoomInner($inner) {
        var el = $inner[0];
        if (!el) return { w: 0, h: 0 };
        var prevTransform = el.style.transform;
        el.style.transform = 'none';
        var w = el.offsetWidth;
        var h = el.offsetHeight;
        el.style.transform = prevTransform;
        return { w: w, h: h };
      }

      function pageManualApplyZoom($root, level) {
        level = pageManualNearestZoom(level);
        $root.attr('data-zoom', level);
        $root.css('--pm-zoom', level);
        var $label = $root.find('.js-page-manual-zoom-label').first();
        var $inner = $root.find('.js-page-manual-modal-zoom-inner').first();
        var $sizer = $root.find('.js-page-manual-modal-zoom-sizer').first();
        $label.text(Math.round(level * 100) + '%');
        $root.find('.js-page-manual-zoom-out').prop('disabled', level <= pageManualZoomLevels[0]);
        $root.find('.js-page-manual-zoom-in').prop('disabled', level >= pageManualZoomLevels[pageManualZoomLevels.length - 1]);
        window.requestAnimationFrame(function () {
          var size = pageManualMeasureZoomInner($inner);
          if (size.w > 0 && size.h > 0) {
            $sizer.css({ width: Math.ceil(size.w * level), height: Math.ceil(size.h * level) });
          }
        });
      }

      function pageManualInitModalZoom($modal) {
        var $root = $modal.find('.js-page-manual-modal-zoom').first();
        if (!$root.length) return;
        pageManualApplyZoom($root, 1);
        $modal.find('.page-manual-screenshot__img').each(function () {
          var img = this;
          var recalc = function () {
            pageManualApplyZoom($root, parseFloat($root.attr('data-zoom')) || 1);
          };
          if (img.complete) {
            recalc();
          } else {
            $(img).one('load', recalc);
          }
        });
      }

      $('#pageManualModal').on('shown.bs.modal', function () {
        $('body').addClass('page-manual-open');
        pageManualInitModalZoom($(this));
      }).on('hidden.bs.modal', function () {
        $('body').removeClass('page-manual-open');
        var $root = $(this).find('.js-page-manual-modal-zoom').first();
        if ($root.length) {
          pageManualApplyZoom($root, 1);
          $(this).find('.js-page-manual-modal-zoom-viewport').scrollTop(0).scrollLeft(0);
        }
      });

      $(document).on('click', '.page-manual-modal-zoom__toolbar .page-manual-modal-zoom__btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
      });
      $(document).on('click', '.js-page-manual-zoom-in', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $root = $(this).closest('.js-page-manual-modal-zoom');
        var current = parseFloat($root.attr('data-zoom')) || 1;
        var idx = pageManualZoomLevels.indexOf(current);
        if (idx < 0) idx = pageManualZoomLevels.indexOf(pageManualNearestZoom(current));
        if (idx < pageManualZoomLevels.length - 1) {
          pageManualApplyZoom($root, pageManualZoomLevels[idx + 1]);
        }
      });
      $(document).on('click', '.js-page-manual-zoom-out', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $root = $(this).closest('.js-page-manual-modal-zoom');
        var current = parseFloat($root.attr('data-zoom')) || 1;
        var idx = pageManualZoomLevels.indexOf(current);
        if (idx < 0) idx = pageManualZoomLevels.indexOf(pageManualNearestZoom(current));
        if (idx > 0) {
          pageManualApplyZoom($root, pageManualZoomLevels[idx - 1]);
        }
      });
      $(document).on('click', '.js-page-manual-zoom-reset', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $root = $(this).closest('.js-page-manual-modal-zoom');
        pageManualApplyZoom($root, 1);
        $root.find('.js-page-manual-modal-zoom-viewport').scrollTop(0).scrollLeft(0);
      });

      $(document).on('click', '#pageManualDownloadBtn', function () {
        window.print();
      });
    })(window.jQuery);
    </script>
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
      window.confirmAction = confirmAction;
    </script>

    <style>
      .search-typewriter {
        position: absolute; display: flex; align-items: center; pointer-events: none;
        white-space: nowrap; overflow: hidden; color: #6c757d; z-index: 4;
      }
      .search-typewriter b { color: #28a745; font-weight: 700; }
      .search-typewriter i { font-style: normal; color: #28a745; animation: searchTwCaret 1s steps(1) infinite; }
      @keyframes searchTwCaret { 50% { opacity: 0; } }
    </style>
    <script>
      /*
       * Typewriter hint for search fields: grey "Search" + green keyword.
       * Keywords come from data-typewriter='["a","b"]' or from the placeholder ("Search customer, phone, ref...").
       * Opt out with data-typewriter="off".
       */
      (function () {
        function esc(s) {
          return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
          });
        }

        function keywordsFor(input) {
          var custom = input.getAttribute('data-typewriter');
          if (custom) {
            try { var list = JSON.parse(custom); if (Array.isArray(list) && list.length) return list; } catch (e) {}
          }
          var text = (input.getAttribute('placeholder') || '').replace(/[.…]+\s*$/, '');
          text = text.replace(/^\s*(search|tafuta)(\s+(by|for|kwa))?\s*/i, '');
          var terms = text.split(/,|\/|\s+or\s+|\s+and\s+|\s+au\s+|\s+na\s+/i)
            .map(function (t) { return t.trim(); })
            .filter(function (t) { return t && !/search|tafuta/i.test(t); });
          return terms.length ? terms : ['here'];
        }

        function attach(input) {
          if (input.dataset.typewriterReady || input.getAttribute('data-typewriter') === 'off') return;
          input.dataset.typewriterReady = '1';

          var parent = input.parentElement;
          if (window.getComputedStyle(parent).position === 'static') parent.style.position = 'relative';

          var basePlaceholder = input.getAttribute('placeholder') || '';
          var prefix = /^\s*tafuta/i.test(basePlaceholder) ? 'Tafuta' : 'Search';
          var words = keywordsFor(input);
          var overlay = document.createElement('span');
          overlay.className = 'search-typewriter';
          input.insertAdjacentElement('afterend', overlay);

          var word = 0, chars = 0, deleting = false;

          function place() {
            var style = window.getComputedStyle(input);
            overlay.style.left = (input.offsetLeft + parseFloat(style.paddingLeft || 8) + parseFloat(style.borderLeftWidth || 0)) + 'px';
            overlay.style.top = input.offsetTop + 'px';
            overlay.style.height = input.offsetHeight + 'px';
            overlay.style.maxWidth = Math.max(0, input.offsetWidth - 24) + 'px';
            overlay.style.fontSize = style.fontSize;
            overlay.style.fontFamily = style.fontFamily;
          }

          function tick() {
            var hidden = input.offsetParent === null;
            if (hidden || document.activeElement === input || input.value) {
              overlay.style.display = 'none';
              input.setAttribute('placeholder', basePlaceholder);
              return setTimeout(tick, 500);
            }
            place();
            overlay.style.display = '';
            input.setAttribute('placeholder', '');

            var current = words[word];
            chars += deleting ? -1 : 1;
            overlay.innerHTML = esc(prefix) + '&nbsp;<b>' + esc(current.slice(0, Math.max(0, chars))) + '</b><i>|</i>';

            var delay = deleting ? 40 : 90;
            if (!deleting && chars >= current.length) { deleting = true; delay = 1600; }
            else if (deleting && chars <= 0) { deleting = false; word = (word + 1) % words.length; delay = 350; }
            setTimeout(tick, delay);
          }
          tick();
        }

        function scan() {
          document.querySelectorAll(
            '.app-content input[type="search"], .app-content input[name="search"], .app-content input[name="q"], ' +
            '.app-content input[id*="Search"], .app-content input[id*="search"], .app-content input[data-typewriter]'
          ).forEach(function (input) {
            if (input.type === 'hidden' || input.closest('.modal')) return;
            var hint = (input.getAttribute('placeholder') || '') + ' ' + (input.getAttribute('data-typewriter') || '');
            if (input.hasAttribute('data-typewriter') || /search|tafuta/i.test(hint)) attach(input);
          });
        }

        document.addEventListener('DOMContentLoaded', scan);
        window.addEventListener('load', scan);
        window.appSearchTypewriter = scan;
      })();
    </script>

    @stack('scripts')
    @yield('scripts')
  </body>
</html>

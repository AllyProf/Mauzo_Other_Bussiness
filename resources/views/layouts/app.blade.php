<!DOCTYPE html>
<html lang="en">
  <head>
    <meta name="description" content="SpareParts POS - SaaS Management System">
    <title>@yield('title', 'SpareParts POS')</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
            font-family: 'Century Gothic', sans-serif !important;
            font-weight: 900 !important;
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .app-sidebar__user-name {
            font-weight: 600 !important;
        }
        .app-menu__item.active, .app-menu__item:hover {
            border-left-color: #940000 !important;
        }
        .btn-primary, .bg-primary, .badge-primary { background-color: #940000 !important; border-color: #940000 !important; }
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
        .branch-switch-menu .dropdown-item {
            width: 100%;
            text-align: left;
            border: 0;
            background: transparent;
        }
        .branch-switch-menu .dropdown-item.active {
            color: #940000;
        }
        .branch-switch-menu form {
            margin: 0;
        }
    </style>
    @yield('styles')
    @stack('styles')
    @php
        $activeBroadcast = \App\Models\Broadcast::where('is_active', true)->first();
    @endphp
  </head>
  <body class="app sidebar-mini rtl">
    <!-- Navbar-->
    @include('layouts.partials._header')
    
    <!-- Sidebar menu-->
    @include('layouts.partials._sidebar')

    <main class="app-content">
      @if($activeBroadcast)
          <div class="alert alert-info alert-dismissible fade show mb-4" role="alert" style="background-color: #940000; color: white; border: none;">
              <i class="fa fa-bullhorn mr-2"></i> <strong>Announcement:</strong> {{ $activeBroadcast->message }}
              <button type="button" class="close text-white" data-dismiss="alert" aria-label="Close">
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

      @if(session()->has('impersonate_original_user'))
          <div class="alert alert-warning d-flex justify-content-between align-items-center mb-4">
              <div>
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
    <!-- The javascript plugin to display page loading on top-->
    <script src="{{ asset('panel-assets/js/plugins/pace.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
          title: "{{ session('success') }}"
        });
      @endif

      @if(session('error'))
        Toast.fire({
          icon: 'error',
          title: "{{ session('error') }}"
        });
      @endif

      @if(session('warning'))
        Toast.fire({
          icon: 'warning',
          title: "{{ session('warning') }}"
        });
      @endif

      @if(session('info'))
        Toast.fire({
          icon: 'info',
          title: "{{ session('info') }}"
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
        var form = e.target.closest('form') || e.target.form;
        var button = e.target.closest('button[type="submit"], input[type="submit"]') || e.target;
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
            form.submit();
          }
        });
      }
    </script>
    
    @stack('scripts')
    @yield('scripts')
  </body>
</html>

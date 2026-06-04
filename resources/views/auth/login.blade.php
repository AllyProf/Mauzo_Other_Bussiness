<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Main CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('panel-assets/css/main.css') }}">
    <!-- Font-icon css-->
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <title>Login - {{ $platformSettings['platform_name'] ?? 'SpareParts POS' }}</title>
    <style>
      body, .login-content .logo h1, .login-box .login-head {
        font-family: 'Century Gothic', 'Segoe UI', sans-serif !important;
      }
      .material-half-bg .cover {
        background-image: linear-gradient(rgba(0, 0, 0, 0.35), rgba(0, 0, 0, 0.35)),
          url('{{ asset('landing/img/happy-african-american-woman-customer-with-colored-shopping-bags-paying-by-credit-card-near-cash-teminal-with-pos.jpg') }}');
        background-color: #000;
        background-size: cover;
        background-position: center center;
        background-repeat: no-repeat;
        height: 100vh;
        width: 100%;
      }
      .login-content {
        position: relative;
        z-index: 1;
      }
      .login-content .logo h1 {
        font-weight: 900;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #fff;
      }
      .login-box .login-head {
        color: #940000 !important;
      }
      .btn-primary {
        background-color: #940000 !important;
        border-color: #940000 !important;
      }
      .password-toggle-wrap {
        position: relative;
      }
      .password-toggle-wrap .form-control {
        padding-right: 42px;
      }
      .password-toggle-btn {
        position: absolute;
        top: 50%;
        right: 12px;
        transform: translateY(-50%);
        border: none;
        background: transparent;
        color: #888;
        padding: 0;
        cursor: pointer;
        line-height: 1;
      }
      .password-toggle-btn:hover,
      .password-toggle-btn:focus {
        color: #940000;
        outline: none;
      }
    </style>
  </head>
  <body>
    <section class="material-half-bg">
      <div class="cover"></div>
    </section>
    <section class="login-content">
      <div class="logo">
        <h1>{{ $platformSettings['platform_name'] ?? 'SpareParts' }}</h1>
      </div>
      <div class="login-box">
        <form class="login-form" action="{{ route('login') }}" method="POST">
          @csrf
          <h3 class="login-head"><i class="fa fa-lg fa-fw fa-user"></i>SIGN IN</h3>
          
          @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
          @endif

          @if(session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
          @endif

          <div class="form-group">
            <label class="control-label">EMAIL</label>
            <input class="form-control" type="email" name="email" placeholder="Enter your email address" value="{{ old('email') }}" required autofocus>
          </div>
          <div class="form-group">
            <label class="control-label">PASSWORD</label>
            <div class="password-toggle-wrap">
              <input class="form-control" type="password" name="password" id="loginPassword" placeholder="Enter your password" required>
              <button type="button" class="password-toggle-btn" id="passwordToggle" aria-label="Show password">
                <i class="fa fa-eye fa-lg" id="passwordToggleIcon"></i>
              </button>
            </div>
          </div>
          <div class="form-group">
            <div class="utility">
              <div class="animated-checkbox">
                <label>
                  <input type="checkbox" name="remember"><span class="label-text">Remember Me</span>
                </label>
              </div>
            </div>
          </div>
          <div class="form-group btn-container">
            <button type="submit" class="btn btn-primary btn-block" id="loginSubmitBtn"><i class="fa fa-sign-in fa-lg fa-fw"></i>SIGN IN</button>
          </div>

        </form>
      </div>
    </section>
    <!-- Essential javascripts for application to work-->
    <script src="{{ asset('panel-assets/js/jquery-3.2.1.min.js') }}"></script>
    <script src="{{ asset('panel-assets/js/popper.min.js') }}"></script>
    <script src="{{ asset('panel-assets/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('panel-assets/js/main.js') }}"></script>
    <script type="text/javascript">
      $('.login-form').on('submit', function() {
        var $btn = $('#loginSubmitBtn');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin fa-lg fa-fw"></i> Signing in...');
      });

      $('#passwordToggle').on('click', function() {
        var input = $('#loginPassword');
        var icon = $('#passwordToggleIcon');
        var isHidden = input.attr('type') === 'password';

        input.attr('type', isHidden ? 'text' : 'password');
        icon.toggleClass('fa-eye fa-eye-slash');
        $(this).attr('aria-label', isHidden ? 'Hide password' : 'Show password');
      });
    </script>
  </body>
</html>

<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Main CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('admin/css/main.css') }}">
    <!-- Font-icon css-->
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <title>Register Business - {{ $platformSettings['platform_name'] ?? 'SpareParts POS' }}</title>
    <style>
      body, .login-content .logo h1, .login-box .login-head {
        font-family: 'Century Gothic', 'Segoe UI', sans-serif !important;
      }
      .material-half-bg .cover {
        background-color: #940000;
        height: 50vh;
      }
      .login-box {
          min-width: 450px;
          min-height: 700px;
          padding: 30px;
      }
      .login-content .logo h1 {
        font-weight: 900;
        color: #fff;
      }
      .btn-primary {
        background-color: #940000 !important;
        border-color: #940000 !important;
      }
      .login-box .login-head {
        color: #940000 !important;
      }
    </style>
  </head>
  <body>
    <section class="material-half-bg">
      <div class="cover"></div>
    </section>
    <section class="login-content">
      <div class="logo">
        <h1><i class="fa fa-building"></i> {{ $platformSettings['platform_name'] ?? 'SpareParts SaaS' }}</h1>
      </div>
      <div class="login-box">
        <form class="login-form" action="{{ route('register.business') }}" method="POST">
          @csrf
          <h3 class="login-head"><i class="fa fa-lg fa-fw fa-rocket"></i>START YOUR BUSINESS</h3>
          
          @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
          @endif

          <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label class="control-label">BUSINESS NAME</label>
                    <input class="form-control" type="text" name="business_name" placeholder="Enter Business Name" value="{{ old('business_name') }}" required autofocus>
                </div>
                <div class="form-group">
                    <label class="control-label">BUSINESS EMAIL</label>
                    <input class="form-control" type="email" name="business_email" placeholder="Business Email (for invoices)" value="{{ old('business_email') }}" required>
                </div>
                <hr>
                <h5 class="text-center mb-3">Owner Account Settings</h5>
                <div class="form-group">
                    <label class="control-label">FULL NAME</label>
                    <input class="form-control" type="text" name="name" placeholder="Owner's Full Name" value="{{ old('name') }}" required>
                </div>
                <div class="form-group">
                    <label class="control-label">PERSONAL EMAIL</label>
                    <input class="form-control" type="email" name="email" placeholder="Login Email" value="{{ old('email') }}" required>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">PASSWORD</label>
                            <input class="form-control" type="password" name="password" placeholder="Password" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">CONFIRM</label>
                            <input class="form-control" type="password" name="password_confirmation" placeholder="Confirm" required>
                        </div>
                    </div>
                </div>
            </div>
          </div>

          <div class="form-group btn-container mt-3">
            <button class="btn btn-primary btn-block"><i class="fa fa-check-circle fa-lg fa-fw"></i>CREATE MY ACCOUNT</button>
          </div>
          <div class="form-group mt-3">
            <p class="semibold-text mb-0">Already registered? <a href="{{ route('login') }}">Sign In here</a></p>
          </div>
        </form>
      </div>
    </section>
    <!-- Essential javascripts for application to work-->
    <script src="{{ asset('admin/js/jquery-3.2.1.min.js') }}"></script>
    <script src="{{ asset('admin/js/popper.min.js') }}"></script>
    <script src="{{ asset('admin/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('admin/js/main.js') }}"></script>
  </body>
</html>

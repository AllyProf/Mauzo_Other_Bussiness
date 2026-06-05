@extends('landing.layout')

@section('title', 'Register')
@section('meta_description', 'Register your business on Mauzo Link and request access to the platform.')

@section('content')
@php
    $trialDays = (int) ($platformSettings['default_trial_days'] ?? 30);
    $oldPhone = old('phone');
    if ($oldPhone) {
        $oldPhone = preg_replace('/^\+?255/', '', preg_replace('/\D/', '', $oldPhone));
    }
    $businessTypes = config('category_templates', []);
@endphp

<section class="breadcrumb-area section-padding-80-0 register-hero">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="section-heading text-center">
                    <div class="line mx-auto"></div>
                    <h2>Register Your Business</h2>
                    <p>Submit your details for review. Once approved, you can sign in and start your {{ $trialDays }}-day free trial.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="register-section section-padding-100-0 pb-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="register-card wow fadeInUp">
                    @if($errors->any())
                    <div class="alert alert-danger">
                        <strong>Please fix the following:</strong>
                        <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                    @endif

                    <div id="register-alert" class="alert alert-danger d-none"></div>

                    <form id="register-form" action="{{ route('register.business.store') }}" method="POST" novalidate>
                        @csrf

                        <h5 class="register-section-title"><i class="fa fa-user"></i> Your Details</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label font-weight-bold">Full Name</label>
                                    <input class="form-control" type="text" name="name" id="name" value="{{ old('name') }}" placeholder="Owner's full name" required autofocus>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label font-weight-bold">Phone Number</label>
                                    <div class="phone-input-group">
                                        <span class="phone-prefix">+255</span>
                                        <input class="form-control" type="tel" name="phone" id="phone" value="{{ $oldPhone }}" placeholder="712 345 678" inputmode="numeric" maxlength="12" required>
                                    </div>
                                    <small class="field-help">Verification code will be sent to this number via SMS.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label font-weight-bold">Email <span class="optional-tag">(optional)</span></label>
                                    <input class="form-control" type="email" name="email" id="email" value="{{ old('email') }}" placeholder="you@example.com">
                                    <small class="field-help">If left blank, you can sign in with your phone number after approval.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label font-weight-bold">Business Type</label>
                                    <select class="form-control" name="business_type" id="business_type" required>
                                        <option value="">Select business type</option>
                                        @foreach($businessTypes as $key => $type)
                                        <option value="{{ $key }}" {{ old('business_type') === $key ? 'selected' : '' }}>{{ $type['label'] ?? $key }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr class="register-divider">
                        <h5 class="register-section-title"><i class="fa fa-map-marker"></i> Location</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label font-weight-bold">Region</label>
                                    <select class="form-control" name="region" id="businessRegion" required>
                                        <option value="">Select region</option>
                                        @foreach(tanzania_regions() as $region)
                                        <option value="{{ $region }}" {{ old('region') === $region ? 'selected' : '' }}>{{ $region }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label font-weight-bold">District</label>
                                    <select class="form-control" name="district" id="businessDistrict" required>
                                        <option value="">{{ old('region') ? 'Select district' : 'Select region first' }}</option>
                                        @foreach(old('region') ? tanzania_districts(old('region')) : [] as $district)
                                        <option value="{{ $district }}" {{ old('district') === $district ? 'selected' : '' }}>{{ $district }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group mb-0">
                                    <label class="control-label font-weight-bold">Physical Address</label>
                                    <textarea class="form-control" name="address" id="address" rows="2" placeholder="Street, building, plot number, landmark" required>{{ old('address') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div id="verification-section" class="verification-section d-none">
                            <hr class="register-divider">
                            <h5 class="register-section-title"><i class="fa fa-mobile"></i> Verify Phone</h5>
                            <p class="field-help mb-3">Enter the 6-digit code sent to <strong id="sent-phone-display"></strong></p>
                            <div class="form-group mb-2">
                                <input class="form-control verification-code-input" type="text" name="verification_code" id="verification_code" inputmode="numeric" maxlength="6" placeholder="000000" autocomplete="one-time-code">
                            </div>
                            <button type="button" id="resend-code-btn" class="btn btn-link btn-sm px-0 resend-link">Resend code</button>
                        </div>

                        <div class="alert alert-brand small mt-4">
                            <i class="fa fa-info-circle"></i>
                            After you register, our team will review your details. Once approved, your login password will be sent to your phone by SMS.
                        </div>

                        <button type="submit" id="register-submit-btn" class="btn credit-btn btn-lg btn-block mt-3">
                            <i class="fa fa-check-circle"></i> <span id="register-submit-text">Submit Registration</span>
                        </button>
                        <p class="text-center mt-3 mb-0 login-link-text">
                            Already registered? <a href="{{ route('login') }}">Sign in here</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<div id="register-progress-overlay" class="register-progress-overlay d-none" aria-hidden="true">
    <div class="register-progress-card">
        <div class="register-progress-spinner"></div>
        <h4 id="register-progress-title">Setting up your account</h4>
        <p id="register-progress-message">Good things are coming...</p>
        <div class="register-progress-bar"><span id="register-progress-fill"></span></div>
    </div>
</div>
@endsection

@push('scripts')
@include('partials.tanzania-location-select2', [
    'selectedDistrict' => old('district', ''),
    'disableDistrictWhenEmpty' => true,
])
<script>
(function () {
    var form = document.getElementById('register-form');
    var overlay = document.getElementById('register-progress-overlay');
    var progressTitle = document.getElementById('register-progress-title');
    var progressMessage = document.getElementById('register-progress-message');
    var progressFill = document.getElementById('register-progress-fill');
    var submitBtn = document.getElementById('register-submit-btn');
    var submitText = document.getElementById('register-submit-text');
    var verificationSection = document.getElementById('verification-section');
    var verificationInput = document.getElementById('verification_code');
    var sentPhoneDisplay = document.getElementById('sent-phone-display');
    var resendBtn = document.getElementById('resend-code-btn');
    var alertBox = document.getElementById('register-alert');
    var codeSent = false;
    var messageTimer = null;
    var resendCooldown = null;

    var sendingMessages = [
        'Good things are coming...',
        'Stay tuned — we are preparing Mauzo Link for you...',
        'Sending verification code to your phone...',
        'Almost there...'
    ];

    var creatingMessages = [
        'Verifying your code...',
        'Submitting your registration...',
        'Almost done — our team will review your details...',
        'Stay tuned — good things are coming...'
    ];

    function csrfToken() {
        return document.querySelector('input[name="_token"]').value;
    }

    function formData() {
        var data = new FormData(form);
        var phone = (data.get('phone') || '').toString().replace(/\D/g, '');
        data.set('phone', phone);
        return data;
    }

    function showAlert(message) {
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    }

    function hideAlert() {
        alertBox.classList.add('d-none');
        alertBox.textContent = '';
    }

    function showOverlay(title, messages) {
        overlay.classList.remove('d-none');
        overlay.setAttribute('aria-hidden', 'false');
        progressTitle.textContent = title;
        progressFill.style.width = '12%';
        var index = 0;
        progressMessage.textContent = messages[0];
        clearInterval(messageTimer);
        messageTimer = setInterval(function () {
            index = (index + 1) % messages.length;
            progressMessage.textContent = messages[index];
            progressFill.style.width = Math.min(92, 12 + (index + 1) * (80 / messages.length)) + '%';
        }, 1800);
    }

    function hideOverlay() {
        overlay.classList.add('d-none');
        overlay.setAttribute('aria-hidden', 'true');
        clearInterval(messageTimer);
        progressFill.style.width = '0%';
    }

    function parseErrors(payload) {
        if (payload && payload.errors) {
            return Object.values(payload.errors).flat().join(' ');
        }
        return (payload && payload.message) ? payload.message : 'Something went wrong. Please try again.';
    }

    function sendVerificationCode() {
        hideAlert();
        showOverlay('Sending verification code', sendingMessages);
        submitBtn.disabled = true;

        return fetch(@json(route('register.business.send-code')), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json'
            },
            body: formData()
        })
        .then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            });
        })
        .then(function (result) {
            hideOverlay();
            submitBtn.disabled = false;

            if (!result.ok) {
                showAlert(parseErrors(result.data));
                return false;
            }

            codeSent = true;
            verificationSection.classList.remove('d-none');
            sentPhoneDisplay.textContent = result.data.phone_display || '+255';
            verificationInput.required = true;
            verificationInput.focus();
            submitText.textContent = 'Verify & Submit';
            return true;
        })
        .catch(function () {
            hideOverlay();
            submitBtn.disabled = false;
            showAlert('Network error. Please check your connection and try again.');
            return false;
        });
    }

    function completeRegistration() {
        hideAlert();
        showOverlay('Submitting registration', creatingMessages);
        submitBtn.disabled = true;

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json'
            },
            body: formData()
        })
        .then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            }).catch(function () {
                return { ok: response.ok, data: {} };
            });
        })
        .then(function (result) {
            if (!result.ok) {
                hideOverlay();
                submitBtn.disabled = false;
                showAlert(parseErrors(result.data));
                return;
            }

            progressFill.style.width = '100%';
            progressTitle.textContent = 'Registration received';
            progressMessage.textContent = result.data.message || 'Your registration is pending approval.';
            clearInterval(messageTimer);

            setTimeout(function () {
                window.location.href = result.data.redirect || @json(route('login'));
            }, 2200);
        })
        .catch(function () {
            hideOverlay();
            submitBtn.disabled = false;
            showAlert('Network error. Please try again.');
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (!codeSent) {
            sendVerificationCode();
            return;
        }

        if (!verificationInput.value || verificationInput.value.trim().length !== 6) {
            showAlert('Please enter the 6-digit verification code from your SMS.');
            verificationInput.focus();
            return;
        }

        completeRegistration();
    });

    resendBtn.addEventListener('click', function () {
        if (resendCooldown) {
            return;
        }
        verificationInput.value = '';
        sendVerificationCode().then(function (sent) {
            if (!sent) {
                return;
            }
            resendBtn.disabled = true;
            var seconds = 60;
            resendBtn.textContent = 'Resend code in ' + seconds + 's';
            resendCooldown = setInterval(function () {
                seconds -= 1;
                if (seconds <= 0) {
                    clearInterval(resendCooldown);
                    resendCooldown = null;
                    resendBtn.disabled = false;
                    resendBtn.textContent = 'Resend code';
                    return;
                }
                resendBtn.textContent = 'Resend code in ' + seconds + 's';
            }, 1000);
        });
    });

    document.getElementById('phone').addEventListener('input', function (event) {
        event.target.value = event.target.value.replace(/\D/g, '').slice(0, 9);
    });
})();
</script>
@endpush

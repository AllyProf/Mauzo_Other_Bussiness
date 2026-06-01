@extends('landing.layout')

@section('title', $platformSettings['platform_name'] ?? 'Mauzo Link')
@section('meta_description', 'Mauzo Link — cloud point of sale for every business. Sales, stock, shifts, debt management and daily reconciliation.')

@section('content')
@php
    $trialDays = (int) ($platformSettings['default_trial_days'] ?? 30);
    $currency = $platformSettings['currency_symbol'] ?? 'TZS';
@endphp

<!-- Hero -->
<div class="hero-area">
    <div class="hero-slideshow owl-carousel">
        <div class="single-slide bg-img">
            <div class="slide-bg-img bg-img bg-overlay" style="background-image: url({{ asset('landing/img/bg-img/1.jpg') }});"></div>
            <div class="container h-100">
                <div class="row h-100 align-items-center justify-content-center">
                    <div class="col-12 col-lg-9">
                        <div class="welcome-text text-center">
                            <h6 data-animation="fadeInUp" data-delay="100ms">Point of Sale for Every Business</h6>
                            <h2 data-animation="fadeInUp" data-delay="300ms">Sell smarter with <span>Mauzo Link</span></h2>
                            <p data-animation="fadeInUp" data-delay="500ms">Track sales, manage stock, run shifts, reconcile daily cash and grow — whether you run a shop, spare parts store, or service business.</p>
                            @if($registrationOpen)
                            <a href="{{ route('register.business') }}" class="btn credit-btn mt-50" data-animation="fadeInUp" data-delay="700ms">Start {{ $trialDays }}-Day Free Trial</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="slide-du-indicator"></div>
        </div>
        <div class="single-slide bg-img">
            <div class="slide-bg-img bg-img bg-overlay hero-slide-dark"></div>
            <div class="container h-100">
                <div class="row h-100 align-items-center justify-content-center">
                    <div class="col-12 col-lg-9">
                        <div class="welcome-text text-center">
                            <h6 data-animation="fadeInDown" data-delay="100ms">Shifts · Reports · Reconciliation</h6>
                            <h2 data-animation="fadeInDown" data-delay="300ms">Your business, <span>one dashboard</span></h2>
                            <p data-animation="fadeInDown" data-delay="500ms">Staff handovers, circulation vs profit, debt tracking, petty cash and master sheet — built for owners who need clarity every day.</p>
                            <a href="{{ route('landing.index') }}#pricing" class="btn credit-btn mt-50" data-animation="fadeInDown" data-delay="700ms">View Plans</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="slide-du-indicator"></div>
        </div>
    </div>
</div>

<!-- Features intro -->
<section class="features-area section-padding-100-0" id="features">
    <div class="container">
        <div class="row align-items-end">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="single-features-area mb-100 wow fadeInUp" data-wow-delay="100ms">
                    <div class="section-heading">
                        <div class="line"></div>
                        <p>Built for Tanzania</p>
                        <h2>Why Mauzo Link?</h2>
                    </div>
                    <h6>From opening shift to closing handover — every sale, expense and profit is tracked in one place.</h6>
                    @if($registrationOpen)
                    <a href="{{ route('register.business') }}" class="btn credit-btn mt-50">Get Started</a>
                    @endif
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="single-features-area mb-100 wow fadeInUp" data-wow-delay="300ms">
                    <img src="{{ asset('landing/img/bg-img/2.jpg') }}" alt="Stock and inventory">
                    <h5>Stock &amp; Inventory</h5>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="single-features-area mb-100 wow fadeInUp" data-wow-delay="500ms">
                    <img src="{{ asset('landing/img/bg-img/3.jpg') }}" alt="Shift management">
                    <h5>Shift Management</h5>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="single-features-area mb-100 wow fadeInUp" data-wow-delay="700ms">
                    <img src="{{ asset('landing/img/bg-img/4.jpg') }}" alt="Daily reports">
                    <h5>Daily Reports</h5>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-area d-flex flex-wrap">
    <div class="cta-thumbnail bg-img jarallax" style="background-image: url({{ asset('landing/img/bg-img/5.jpg') }});"></div>
    <div class="cta-content">
        <div class="section-heading white">
            <div class="line"></div>
            <p>For shop owners &amp; managers</p>
            <h2>Helping businesses like yours every day</h2>
        </div>
        <h6>Mauzo Link gives you a full POS — sales counter, invoices, customer debt, staff permissions, branch support, and owner financial review. No spreadsheets. No guesswork.</h6>
        <div class="d-flex flex-wrap mt-50">
            <div class="skills-stat mb-70 mr-4"><strong>24/7</strong><span>Cloud access</span></div>
            <div class="skills-stat mb-70 mr-4"><strong>Multi</strong><span>Staff &amp; shifts</span></div>
            <div class="skills-stat mb-70"><strong>{{ $currency }}</strong><span>Local currency</span></div>
        </div>
        <a href="{{ route('landing.index') }}#pricing" class="btn credit-btn box-shadow btn-2">See Pricing</a>
    </div>
</section>

<section class="cta-2-area wow fadeInUp" data-wow-delay="100ms">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="cta-content d-flex flex-wrap align-items-center justify-content-between">
                    <div class="cta-text"><h4>Ready to modernise your shop? Start your free trial today.</h4></div>
                    <div class="cta-btn">
                        @if($registrationOpen)
                        <a href="{{ route('register.business') }}" class="btn credit-btn box-shadow">Register Now</a>
                        @else
                        <a href="{{ route('login') }}" class="btn credit-btn box-shadow">Sign In</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Services / POS modules -->
<section class="services-area section-padding-100-0">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="section-heading text-center mb-100 wow fadeInUp" data-wow-delay="100ms">
                    <div class="line"></div>
                    <p>Everything you need</p>
                    <h2>POS Features</h2>
                </div>
            </div>
        </div>
        <div class="row">
            @foreach([
                ['icon' => 'icon-profits', 'title' => 'Sales & POS', 'text' => 'Fast checkout, multiple payment methods — cash, mobile money and bank.'],
                ['icon' => 'icon-money-1', 'title' => 'Debt Management', 'text' => 'Track credit sales and customer balances until they pay.'],
                ['icon' => 'icon-coin', 'title' => 'Daily Reconciliation', 'text' => 'Staff shift handover with boss verification and master sheet.'],
                ['icon' => 'icon-smartphone-1', 'title' => 'Multi-Branch', 'text' => 'Run multiple branches under one business account.'],
                ['icon' => 'icon-diamond', 'title' => 'Circulation & Profit', 'text' => 'Separate sales capital from profit — know your real numbers.'],
                ['icon' => 'icon-piggy-bank', 'title' => 'Petty Cash & Expenses', 'text' => 'Issue petty cash from circulation or profit with full audit trail.'],
            ] as $i => $service)
            <div class="col-12 col-md-6 col-lg-4">
                <div class="single-service-area d-flex mb-100 wow fadeInUp" data-wow-delay="{{ 200 + ($i * 100) }}ms">
                    <div class="icon"><i class="{{ $service['icon'] }}"></i></div>
                    <div class="text">
                        <h5>{{ $service['title'] }}</h5>
                        <p>{{ $service['text'] }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- Pricing -->
<section class="miscellaneous-area bg-gray section-padding-100-0" id="pricing">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="section-heading text-center mb-5 wow fadeInUp">
                    <div class="line mx-auto"></div>
                    <p>Flexible billing</p>
                    <h2>Choose Your Plan</h2>
                    <p class="text-muted mt-2">Start with a free trial, then choose fixed monthly billing or a percentage of your profit.</p>
                </div>
            </div>
        </div>
        <div class="row justify-content-center">
            @if($registrationOpen)
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="pricing-card trial featured wow fadeInUp" data-wow-delay="100ms">
                    <span class="plan-badge">Start Here</span>
                    <h4>Free Trial</h4>
                    <p class="text-muted small mb-0">No payment required</p>
                    <div class="price">
                        {{ $currency }} 0
                        <small>/ {{ $trialDays }} days</small>
                    </div>
                    <ul>
                        <li><i class="fa fa-check"></i> Full POS access during trial</li>
                        <li><i class="fa fa-check"></i> Sales, stock &amp; shift management</li>
                        <li><i class="fa fa-check"></i> Daily reports &amp; reconciliation</li>
                        <li><i class="fa fa-check"></i> Choose a paid plan before trial ends</li>
                    </ul>
                    <a href="{{ route('register.business') }}" class="btn credit-btn btn-block">Start Free Trial</a>
                </div>
            </div>
            @endif
            @forelse($plans as $index => $plan)
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="pricing-card wow fadeInUp {{ $plans->count() >= 2 && $index === 1 ? 'featured' : '' }}" data-wow-delay="{{ ($index + 2) * 100 }}ms">
                    @if($plans->count() >= 2 && $index === 1)<span class="plan-badge">Popular</span>@endif
                    <h4>{{ $plan->name }}</h4>
                    <p class="text-muted small mb-0">{{ $plan->billingModelLabel() }}</p>
                    <div class="price">
                        @if($plan->usesProfitShareBilling())
                            {{ number_format((float) $plan->profit_share_percent, 1) }}%
                            <small>of {{ $plan->profit_share_basis === 'gross_profit' ? 'gross' : 'net' }} profit / month</small>
                        @else
                            {{ $currency }} {{ number_format((float) $plan->price, 0) }}
                            <small>/ {{ $plan->duration_months }} month(s)</small>
                        @endif
                    </div>
                    <ul>
                        <li><i class="fa fa-check"></i> {{ $plan->formatLimit($plan->max_items) }} products</li>
                        <li><i class="fa fa-check"></i> {{ $plan->formatLimit($plan->max_users) }} staff users</li>
                        <li><i class="fa fa-check"></i> {{ ($plan->max_branches ?? 1) === 0 ? 'Unlimited' : ($plan->max_branches ?? 1) }} branch(es)</li>
                        <li><i class="fa fa-check"></i> {{ $plan->formatLimit($plan->max_sms ?? 0) }} SMS / month</li>
                        @if($plan->features)
                        <li><i class="fa fa-check"></i> {{ $plan->features }}</li>
                        @endif
                    </ul>
                    @if($registrationOpen)
                    <a href="{{ route('register.business', ['plan' => $plan->id]) }}" class="btn credit-btn btn-block">Choose {{ $plan->name }}</a>
                    @endif
                </div>
            </div>
            @empty
            @if(!$registrationOpen)
            <div class="col-12 text-center text-muted py-4">Plans coming soon. Contact us for pricing.</div>
            @endif
            @endforelse
        </div>
        @if($registrationOpen)
        <p class="text-center text-muted small mt-3">
            <i class="fa fa-gift"></i> Every new business starts with a <strong>{{ $trialDays }}-day free trial</strong> — no credit card required.
        </p>
        @endif
    </div>
</section>

<!-- Contact strip -->
<section class="newsletter-area section-padding-100 bg-img jarallax" style="background-image: url({{ asset('landing/img/bg-img/6.jpg') }});">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-lg-8">
                <div class="nl-content text-center">
                    <h2>Questions? We're here to help.</h2>
                    <p class="mb-4">Email us at <strong>{{ $platformSettings['support_email'] ?? 'support@mauzolink.com' }}</strong> or register and start selling in minutes.</p>
                    @if($registrationOpen)
                    <a href="{{ route('register.business') }}" class="btn credit-btn btn-lg">Create Free Account</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

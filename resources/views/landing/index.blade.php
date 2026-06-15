@extends('landing.layout')

@section('title', ($platformName ?? 'Mauzo Link').' — POS & Business Management')
@section('meta_description', 'Modern point of sale, inventory, shift closing, and owner reports for Tanzanian retail, spare parts, pharmacy, and service businesses.')

@section('content')
  @include('landing.partials.hero')
  @include('landing.partials.about')
  @include('landing.partials.features')
  @include('landing.partials.pricing')
  @include('landing.partials.stats')
  @include('landing.partials.testimonials')
  @include('landing.partials.cta')
  @include('landing.partials.contact')
@endsection

@push('scripts')
@if($errors->any())
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var contact = document.getElementById('contact');
    if (contact) contact.scrollIntoView({ behavior: 'smooth' });
  });
</script>
@endif
@endpush

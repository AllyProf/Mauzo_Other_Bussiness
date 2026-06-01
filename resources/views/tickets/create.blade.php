@extends('layouts.app')

@section('title', 'Create Support Ticket')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-plus"></i> New Support Ticket</h1>
    <p>Submit a request to the Software Owner</p>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-md-8">
    <div class="tile">
      <div class="tile-body">
        <form action="{{ route('tickets.store') }}" method="POST">
          @csrf
          <div class="form-group">
            <label class="control-label">Subject</label>
            <input class="form-control" type="text" name="subject" placeholder="What do you need help with?" required>
          </div>
          <div class="form-group">
            <label class="control-label">Your Message</label>
            <textarea class="form-control" name="message" rows="6" placeholder="Describe your issue in detail..." required></textarea>
          </div>
          <div class="tile-footer">
            <button class="btn btn-primary" type="submit"><i class="fa fa-paper-plane"></i> Submit Ticket</button>
            <a href="{{ route('tickets.index') }}" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

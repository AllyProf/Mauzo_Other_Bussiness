@extends('layouts.app')

@section('title', 'Closing History')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-history"></i> Reconciliation History</h1>
    <p>Reports submitted by staff — for owner review</p>
  </div>
  @can('process_sales')
  <a href="{{ route('day-closing.index') }}" class="btn btn-primary"><i class="fa fa-balance-scale"></i> Daily Reconciliation</a>
  @endcan
</div>

@include('partials.branch-business-filters', ['filterHint' => 'Select a business tab to filter reconciliation history by department.'])

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-body">
        <table class="table table-hover table-bordered">
          <thead>
            <tr>
              <th>Date</th>
              <th>Submitted By</th>
              @if($multiBusiness ?? false)
              <th>Business</th>
              @endif
              <th>Sales</th>
              <th>Gross Sales</th>
              <th>Received</th>
              <th>Expenses</th>
              <th>Net Submitted</th>
              <th>Status</th>
              <th>Submitted At</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @foreach($closings as $closing)
              <tr>
                <td><strong>{{ $closing->closing_date->format('M d, Y') }}</strong></td>
                <td>{{ $closing->user->name }}</td>
                @if($multiBusiness ?? false)
                <td nowrap>{{ ($activeBusinessType ?? false) ? ($activeBusinessLabel ?? $activeBusinessType) : (implode(', ', $closing->business_type_labels ?? []) ?: '—') }}</td>
                @endif
                <td>{{ $closing->sales_count }}</td>
                <td>TZS {{ number_format($closing->gross_sales, 2) }}</td>
                <td>TZS {{ number_format($closing->payments_received, 2) }}</td>
                <td>TZS {{ number_format($closing->total_expenses, 2) }}</td>
                <td class="text-success font-weight-bold">TZS {{ number_format($closing->net_amount, 2) }}</td>
                <td>
                  @if($closing->status === 'verified')
                    <span class="badge badge-success">Verified</span>
                  @elseif($closing->status === 'disputed')
                    <span class="badge badge-danger">Disputed</span>
                  @else
                    <span class="badge badge-info">Submitted</span>
                  @endif
                </td>
                <td>{{ $closing->submitted_at?->format('M d, Y h:i A') }}</td>
                <td>
                  @if($closing->status === 'verified')
                  <a href="{{ route('owner-reports.show', $closing->closing_date->format('Y-m-d')) }}" class="btn btn-sm btn-primary mr-1">
                    <i class="fa fa-list-alt"></i> Master Sheet
                  </a>
                  @endif
                  <a href="{{ route('day-closing.show', $closing->id) }}" class="btn btn-sm btn-secondary">
                    <i class="fa fa-eye"></i> Reconciliation
                  </a>
                </td>
              </tr>
            @endforeach
            @if($closings->isEmpty())
              <tr><td colspan="10" class="text-center">No reconciliation reports submitted yet.</td></tr>
            @endif
          </tbody>
        </table>
        {{ $closings->links() }}
      </div>
    </div>
  </div>
</div>
@endsection

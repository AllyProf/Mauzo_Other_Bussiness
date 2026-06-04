@extends('layouts.app')

@section('title', 'Regional Report')

@section('content')
<div class="app-title"><div><h1><i class="fa fa-map-marker"></i> Regional Report</h1><p>Business distribution by Tanzania region.</p></div></div>

<div class="row mb-3">
  <div class="col-md-4"><div class="widget-small primary coloured-icon"><i class="icon fa fa-building fa-3x"></i><div class="info"><h4>Total</h4><p><b>{{ $data['total_businesses'] }}</b></p></div></div></div>
  <div class="col-md-4"><div class="widget-small info coloured-icon"><i class="icon fa fa-map fa-3x"></i><div class="info"><h4>With Region</h4><p><b>{{ $data['with_region'] }}</b></p></div></div></div>
</div>

<div class="tile">
  <div class="tile-body table-responsive">
    <table class="table table-hover table-bordered mb-0">
      <thead><tr><th>Region</th><th>Total</th><th>Active</th><th>Share</th></tr></thead>
      <tbody>
        @foreach($data['regions'] as $row)
        <tr>
          <td>{{ $row->region }}</td>
          <td>{{ $row->total }}</td>
          <td>{{ $row->active }}</td>
          <td>{{ $data['with_region'] ? round(($row->total / $data['with_region']) * 100, 1) : 0 }}%</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection

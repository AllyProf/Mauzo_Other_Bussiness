@extends('layouts.app')

@section('title', 'Suppliers List')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-truck"></i> Suppliers Management</h1>
    <p>Manage your business suppliers and contact information</p>
  </div>
  <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Register Supplier</a>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-body">
        <table class="table table-hover table-bordered" id="sampleTable">
          <thead>
            <tr>
              <th>Name</th>
              <th>Phone Number</th>
              <th>Email</th>
              <th>Region</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($suppliers as $supplier)
                <tr>
                    <td><strong>{{ $supplier->name }}</strong></td>
                    <td>{{ $supplier->phone }}</td>
                    <td>{{ $supplier->email ?: 'N/A' }}</td>
                    <td><span class="badge badge-info">{{ $supplier->region ?: 'N/A' }}</span></td>
                    <td>
                        <a href="{{ route('suppliers.edit', $supplier->id) }}" class="btn btn-sm btn-info"><i class="fa fa-edit"></i></a>
                        <form action="{{ route('suppliers.destroy', $supplier->id) }}" method="POST" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Remove this supplier?')"><i class="fa fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

<table class="table table-hover table-bordered cashier-queue-table mb-0">
  <thead class="thead-light">
    <tr>
      <th>Order</th>
      <th>Sales Officer</th>
      <th>Customer</th>
      <th>Items</th>
      <th class="text-right">Total</th>
      @if($tab === 'paid')
        <th>Collected By</th>
        <th>Method</th>
        <th class="text-center">Paid At</th>
      @else
        <th class="text-right">Paid</th>
        <th class="text-right">Balance</th>
        <th class="text-center">Status</th>
      @endif
      <th class="text-center">Action</th>
    </tr>
  </thead>
  <tbody>
    @include('cashier.partials.queue-rows', ['sales' => $sales, 'tab' => $tab, 'locks' => $locks ?? []])
  </tbody>
</table>

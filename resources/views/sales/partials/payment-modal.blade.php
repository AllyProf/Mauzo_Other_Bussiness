<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form id="paymentForm" method="POST" action="">
        @csrf
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title"><i class="fa fa-money"></i> Process Payment</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
            <div class="alert alert-info mb-3">
                <div class="row">
                    <div class="col-sm-6 col-6"><strong>Order Ref:</strong> <span id="payRef"></span></div>
                    <div class="col-sm-6 col-6 text-sm-right text-right"><strong>Order Total:</strong> <span id="payOrderTotal"></span></div>
                    <div class="col-sm-6 col-6 mt-2"><strong>Already Paid:</strong> <span id="payAmountPaid"></span></div>
                    <div class="col-sm-6 col-6 mt-2 text-sm-right text-right"><strong class="text-danger">Balance Due:</strong> <span id="payBalance"></span></div>
                </div>
            </div>

            <h6 class="text-muted text-uppercase mb-2"><i class="fa fa-list"></i> Order Items</h6>
            <p class="small text-muted mb-2">Adjust each line with a custom unit price or a discount before collecting payment.</p>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered mb-0" id="payItemsTable">
                    <thead class="thead-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-center" style="width:60px;">Qty</th>
                            <th class="text-right" style="width:110px;">List Price</th>
                            <th style="width:120px;">Adjust By</th>
                            <th style="width:200px;">Value</th>
                            <th class="text-right" style="width:110px;">Line Total</th>
                        </tr>
                    </thead>
                    <tbody id="payItemsBody"></tbody>
                    <tfoot>
                        <tr>
                            <th colspan="5" class="text-right">Revised Order Total</th>
                            <th class="text-right text-success" id="payRevisedTotal">TZS 0</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div id="payLineItemsHidden"></div>

            <div class="row">
              {{-- Left: Payment --}}
              <div class="col-md-6 pr-md-3 payment-modal-column">
                <h6 class="text-muted text-uppercase mb-3"><i class="fa fa-credit-card"></i> Payment Details</h6>

                <div class="form-group">
                    <label class="control-label font-weight-bold">Payment Option</label>
                    <select name="payment_method" id="paymentMethod" class="form-control" required>
                        <option value="">-- Select Option --</option>
                        @foreach($paymentMethods ?? [] as $method)
                        <option value="{{ $method['key'] }}"
                          data-type="{{ $method['type'] }}"
                          data-requires-reference="{{ !empty($method['requires_reference']) ? '1' : '0' }}"
                          data-provider-accounts='@json($method['provider_accounts'] ?? [])'>
                          {{ $method['label'] }}
                        </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Choose how the customer pays today.</small>
                </div>

                <div id="payReceiveDetailsBox" class="alert alert-info py-2 small" style="display:none;">
                  <strong>Pay to:</strong> <span id="payReceiveDetailsText"></span>
                </div>

                <div id="paymentAmountSection" style="display: none;">
                    <div class="form-group">
                        <label class="control-label required">Amount Paying Now</label>
                        <input type="number" name="amount_paid" id="amountPaid" class="form-control" min="1" step="1">
                        <small class="text-muted">Full balance or partial amount (whole TZS).</small>
                    </div>
                    <div class="form-group">
                        <label class="control-label">Balance After This Payment</label>
                        <input type="text" id="remainingAfterPay" class="form-control bg-light text-danger font-weight-bold" readonly>
                    </div>
                </div>

                <div id="providerFields" style="display: none;">
                    <div class="form-group">
                        <label class="control-label required">Provider / Platform</label>
                        <select id="paymentProvider" class="form-control">
                            <option value="">-- Select Provider --</option>
                        </select>
                        <input type="hidden" name="payment_provider" id="paymentProviderValue">
                        <input type="text" id="paymentProviderCustom" class="form-control mt-2" placeholder="Or type another provider (e.g. Mixx by Yas)">
                        <small class="text-muted">Choose from the list or enter a custom provider name.</small>
                    </div>
                    <div class="form-group mb-0">
                        <label class="control-label required">Transaction Reference No.</label>
                        <input type="text" name="transaction_reference" id="transactionReference" class="form-control" placeholder="e.g. 7XG12AB9Z">
                    </div>
                </div>
              </div>

              {{-- Right: Customer --}}
              <div class="col-md-6 pl-md-3 payment-modal-column border-left" id="customerColumn">
                <h6 class="text-muted text-uppercase mb-3"><i class="fa fa-user"></i> Customer Details</h6>

                <div id="customerOnRecord" class="alert alert-light border mb-3" style="display: none;">
                  <div class="small text-muted text-uppercase mb-1">Customer on this invoice</div>
                  <div class="font-weight-bold" id="payCustomerDisplayName">—</div>
                  <div class="text-muted" id="payCustomerDisplayPhone"></div>
                </div>

                <div id="customerInfoPlaceholder" class="text-center text-muted py-5">
                    <i class="fa fa-check-circle fa-2x mb-2 text-success"></i>
                    <p class="mb-0 small">Customer details are not required for full payment.</p>
                </div>

                <div id="customerInfoFields" style="display: none;">
                    <div id="partialPaymentAlert" class="alert alert-warning py-2" style="display: none;">
                        <small><i class="fa fa-info-circle"></i> Partial payment — record customer details and repayment date for the remaining balance.</small>
                    </div>
                    <div id="debtPaymentAlert" class="alert alert-warning py-2" style="display: none;">
                        <small><i class="fa fa-info-circle"></i> No payment now — record customer details and when they will repay.</small>
                    </div>
                    <div class="form-group">
                        <label class="control-label">Select Customer</label>
                        <select name="customer_id" id="payCustomerSelect" class="form-control">
                            <option value="">Walk-in / Manual entry</option>
                            @foreach($customers ?? [] as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }} — {{ $customer->phone }}</option>
                            @endforeach
                        </select>
                        @if(($customers ?? collect())->isEmpty())
                        <small class="text-muted"><a href="{{ route('customers.create') }}" target="_blank">Register a customer</a></small>
                        @endif
                    </div>
                    <div class="form-group">
                        <label class="control-label">Customer Name</label>
                        <input type="text" name="customer_name" id="payCustomerName" class="form-control" placeholder="Customer full name">
                    </div>
                    <div class="form-group">
                        <label class="control-label">Customer Phone</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">+255</span>
                            </div>
                            <input type="tel" id="payCustomerPhoneLocal" class="form-control" placeholder="712345678" maxlength="10" inputmode="numeric">
                        </div>
                        <input type="hidden" name="customer_phone" id="payCustomerPhone">
                        <small class="text-muted">Enter number without country code (e.g. 712345678).</small>
                    </div>
                    <div class="form-group" id="repayDateSection" style="display: none;">
                        <label class="control-label required">Repayment Due Date</label>
                        <input type="date" name="due_date" id="payDueDate" class="form-control" min="{{ date('Y-m-d') }}">
                        <small class="text-muted">When the customer will pay the remaining balance.</small>
                    </div>
                    <div class="form-group mb-0">
                        <label class="control-label">Note</label>
                        <textarea name="notes" id="payNotes" class="form-control" rows="3" placeholder="e.g. Will pay balance on Friday"></textarea>
                    </div>
                </div>
              </div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-success" id="paymentSubmitBtn"><i class="fa fa-check"></i> Save Payment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
  @media (min-width: 768px) {
    #paymentModal .payment-modal-column.border-left {
      border-left: 1px solid #dee2e6 !important;
    }
  }
  @media (max-width: 767.98px) {
    #paymentModal .payment-modal-column.border-left {
      border-left: none !important;
      border-top: 1px solid #dee2e6;
      margin-top: 1rem;
      padding-top: 1rem !important;
    }
    #paymentModal .payment-modal-column.pr-md-3 {
      padding-right: 15px !important;
    }
  }
</style>

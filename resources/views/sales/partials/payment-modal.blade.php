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
            <div class="alert alert-info mb-3 py-2">
                <div class="row">
                    <div class="col-sm-6 col-6"><strong>Ref:</strong> <span id="payRef"></span></div>
                    <div class="col-sm-6 col-6 text-sm-right text-right"><strong>Total:</strong> <span id="payOrderTotal"></span></div>
                    <div class="col-sm-6 col-6 mt-2"><strong>Paid:</strong> <span id="payAmountPaid"></span></div>
                    <div class="col-sm-6 col-6 mt-2 text-sm-right text-right"><strong class="text-danger">Due:</strong> <span id="payBalance"></span></div>
                </div>
                <p class="mb-0 mt-2 small text-muted" id="linkedOrdersNote" style="display:none;"></p>
            </div>
            <div id="linkedSaleInputs"></div>

            <h6 class="text-muted text-uppercase mb-2"><i class="fa fa-list"></i> Items</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-hover table-bordered mb-0" id="payItemsTable">
                    <thead class="thead-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-center" style="width:60px;">Qty</th>
                            <th class="text-right" style="width:110px;">List Price</th>
                            <th style="width:120px;">Adjust</th>
                            <th style="width:200px;">Value</th>
                            <th class="text-right" style="width:110px;">Line Total</th>
                            <th class="text-center" style="width:40px;"><i class="fa fa-trash"></i></th>
                        </tr>
                    </thead>
                    <tbody id="payItemsBody"></tbody>
                    <tfoot>
                        <tr>
                            <th colspan="5" class="text-right">Revised Total</th>
                            <th class="text-right text-success" id="payRevisedTotal">TZS 0</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div id="payLineItemsHidden"></div>

            <div class="row">
              <div class="col-md-6 pr-md-3 payment-modal-column">
                <h6 class="text-muted text-uppercase mb-3"><i class="fa fa-credit-card"></i> Payment</h6>

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
                </div>

                <div id="payReceiveDetailsBox" class="alert alert-info py-2 small mb-3" style="display:none;">
                  <span id="payReceiveDetailsText"></span>
                </div>

                <div id="paymentAmountSection" style="display: none;">
                    <div class="form-group">
                        <label class="control-label font-weight-bold">Amount Paying Now</label>
                        <input type="number" name="amount_paid" id="amountPaid" class="form-control" min="1" step="1">
                    </div>
                    <div id="splitPaymentPrompt" class="mb-2" style="display: none;">
                        <div class="custom-control custom-checkbox mb-0">
                            <input type="checkbox" class="custom-control-input" id="splitPaymentEnabled">
                            <label class="custom-control-label" for="splitPaymentEnabled">Split with another method</label>
                        </div>
                    </div>
                    <div id="splitPaymentLines" style="display: none;"></div>
                    <div class="form-group mb-2">
                        <label class="control-label font-weight-bold">Balance After Payment</label>
                        <input type="text" id="remainingAfterPay" class="form-control bg-light text-danger font-weight-bold" readonly>
                    </div>
                    <p id="splitPaymentSummary" class="small text-muted mb-2" style="display: none;"></p>
                </div>

                <div id="providerFields" style="display: none;">
                    <div class="form-group">
                        <label class="control-label font-weight-bold">Provider / Platform</label>
                        <select id="paymentProvider" class="form-control">
                            <option value="">-- Select Provider --</option>
                        </select>
                        <input type="hidden" name="payment_provider" id="paymentProviderValue">
                        <input type="text" id="paymentProviderCustom" class="form-control mt-2" placeholder="Custom provider">
                    </div>
                    <div class="form-group mb-0">
                        <label class="control-label font-weight-bold">Transaction Reference</label>
                        <input type="text" name="transaction_reference" id="transactionReference" class="form-control" placeholder="Reference no.">
                    </div>
                </div>
              </div>

              <div class="col-md-6 pl-md-3 payment-modal-column border-left" id="customerColumn">
                <h6 class="text-muted text-uppercase mb-3"><i class="fa fa-user"></i> Customer</h6>

                <div id="customerOnRecord" class="alert alert-light border mb-3 py-2" style="display: none;">
                  <div class="font-weight-bold mb-0" id="payCustomerDisplayName">—</div>
                  <div class="text-muted small" id="payCustomerDisplayPhone"></div>
                </div>

                <div id="customerInfoFields" style="display: none;">
                    <div id="partialPaymentAlert" class="alert alert-warning py-2 mb-3" style="display: none;">
                        <small class="mb-0">Partial payment — customer and due date required.</small>
                    </div>
                    <div id="debtPaymentAlert" class="alert alert-warning py-2 mb-3" style="display: none;">
                        <small class="mb-0">On credit — customer and due date required.</small>
                    </div>
                    <div class="form-group">
                        <label class="control-label font-weight-bold">Select Customer</label>
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
                        <label class="control-label font-weight-bold">Customer Name</label>
                        <input type="text" name="customer_name" id="payCustomerName" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="control-label font-weight-bold">Customer Phone</label>
                        @include('partials.phone-country-input', ['id' => 'payCustomerPhoneLocal', 'nameless' => true, 'placeholder' => '712345678'])
                        <input type="hidden" name="customer_phone" id="payCustomerPhone">
                    </div>
                    <div class="form-group" id="repayDateSection" style="display: none;">
                        <label class="control-label font-weight-bold">Repayment Due Date</label>
                        <input type="date" name="due_date" id="payDueDate" class="form-control" min="{{ date('Y-m-d') }}">
                    </div>
                    <div class="form-group mb-0">
                        <label class="control-label font-weight-bold">Note</label>
                        <textarea name="notes" id="payNotes" class="form-control" rows="2"></textarea>
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
  #paymentModal .split-payment-line {
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 12px;
    margin-bottom: 10px;
    background: #f8f9fa;
  }
  #paymentModal .split-payment-line .split-line-title {
    font-size: 0.8rem;
    text-transform: uppercase;
    color: #6c757d;
    font-weight: 700;
    margin-bottom: 10px;
  }
</style>

@if(!empty($paymentMethods))
<script type="text/javascript">
    window.salePaymentMethods = @json(collect($paymentMethods)->filter(fn ($m) => ($m['type'] ?? '') !== 'credit')->values());
</script>
@endif

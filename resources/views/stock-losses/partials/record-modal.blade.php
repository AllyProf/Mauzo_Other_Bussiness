@if($form)
@php
  $multiBusiness = $form['multiBusiness'] ?? false;
@endphp
<div class="modal fade" id="recordLossModal" tabindex="-1" role="dialog" aria-labelledby="recordLossModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg stock-loss-modal-dialog modal-dialog-scrollable" role="document">
    <div class="modal-content stock-loss-modal-content">
      <form action="{{ route('stock-losses.store') }}" method="POST" id="loss-form">
        @csrf
        <div class="modal-header stock-loss-modal-header">
          <div>
            <h5 class="modal-title mb-0" id="recordLossModalLabel">
              <i class="fa fa-minus-circle"></i> Record Stock Loss
            </h5>
            <small class="d-block mt-1 opacity-90">Write off lost, damaged, or expired stock</small>
          </div>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body stock-loss-modal-body">
          <div class="stock-loss-steps mb-3">
            <div class="stock-loss-step"><span>1</span> Date &amp; reason</div>
            @if($multiBusiness)
            <div class="stock-loss-step"><span>2</span> Business &amp; category</div>
            <div class="stock-loss-step"><span>3</span> Quantities lost</div>
            @else
            <div class="stock-loss-step"><span>2</span> Choose category</div>
            <div class="stock-loss-step"><span>3</span> Quantities lost</div>
            @endif
          </div>

          <div class="stock-loss-panel mb-3">
            <h6 class="stock-loss-section-title"><i class="fa fa-info-circle"></i> Loss Details</h6>
            <div class="row">
              <div class="col-sm-6 mb-2 mb-sm-0">
                <label class="control-label font-weight-bold">Loss Date</label>
                <input type="date" name="loss_date" id="loss_date" class="form-control" value="{{ old('loss_date', date('Y-m-d')) }}" required>
              </div>
              <div class="col-sm-6 mb-2 mb-sm-0">
                <label class="control-label font-weight-bold">Reason</label>
                <select name="reason" id="loss_reason" class="form-control" required>
                  <option value="">-- Select reason --</option>
                  @foreach($form['reasons'] as $key => $label)
                    <option value="{{ $key }}" {{ old('reason') === $key ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="row mt-3">
              @if($multiBusiness)
              <div class="col-sm-6 mb-2 mb-sm-0">
                <label class="control-label font-weight-bold">Business Type</label>
                <select id="business-type-selector" class="form-control">
                  <option value="">-- Select business --</option>
                  @foreach($form['businessTypes'] as $type)
                    <option value="{{ $type['key'] }}">{{ $type['label'] }}</option>
                  @endforeach
                </select>
                <small class="text-muted">Which department are these items from?</small>
              </div>
              <div class="col-sm-6">
                <label class="control-label font-weight-bold">Category</label>
                <select id="category-selector" class="form-control" disabled>
                  <option value="">-- Select business first --</option>
                </select>
              </div>
              @else
              <div class="col-sm-12">
                <label class="control-label font-weight-bold">Category</label>
                <select id="category-selector" class="form-control">
                  <option value="">-- Select category to load items --</option>
                  @foreach($form['categoriesList'] ?? [] as $cat)
                    <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                  @endforeach
                </select>
              </div>
              @endif
            </div>
          </div>

          <div class="alert alert-warning py-2 mb-3 small">
            <i class="fa fa-warning"></i> Stock is reduced <strong>immediately</strong> on save.
          </div>

          <h6 class="stock-loss-section-title mb-2"><i class="fa fa-cubes"></i> Items to Write Off</h6>
          <div class="stock-loss-table-wrap">
            <table class="table table-bordered table-hover table-sm mb-0" id="loss-items-table">
              <thead class="thead-light">
                <tr>
                  <th>{{ __('tables.columns.item') }}</th>
                  <th class="text-center" style="width:72px;">Stock</th>
                  <th class="text-center" style="width:88px;">Qty Lost</th>
                  <th class="text-right" style="width:96px;">Unit Cost</th>
                  <th class="text-right" style="width:100px;">Value</th>
                  <th style="width:120px;">Notes</th>
                </tr>
              </thead>
              <tbody id="items-body">
                <tr id="empty-row">
                  <td colspan="6" class="text-center text-muted py-4">
                    <i class="fa fa-folder-open-o fa-lg d-block mb-2"></i>
                    <span id="empty-row-message">
                      @if($multiBusiness)
                        Select business type and category above
                      @else
                        Select a category above to load items
                      @endif
                    </span>
                  </td>
                </tr>
              </tbody>
              <tfoot class="bg-light">
                <tr>
                  <th colspan="4" class="text-right align-middle small">Total cost value:</th>
                  <th class="text-right align-middle text-danger" id="grand-total">TZS 0</th>
                  <th></th>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="row mt-3">
            <div class="col-md-7">
              <label class="control-label font-weight-bold">General Notes <span class="text-muted font-weight-normal">(optional)</span></label>
              <textarea name="notes" id="loss_notes" class="form-control" rows="2" placeholder="What happened?">{{ old('notes') }}</textarea>
            </div>
            <div class="col-md-5 mt-2 mt-md-0">
              <div class="stock-loss-summary-box">
                <div class="small text-muted text-uppercase mb-1">Summary</div>
                <div class="d-flex justify-content-between mb-1">
                  <span class="small">Lines with qty</span>
                  <strong id="loss-line-count">0</strong>
                </div>
                <div class="d-flex justify-content-between">
                  <span class="small">Total value</span>
                  <strong class="text-danger" id="loss-summary-total">TZS 0</strong>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer stock-loss-modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button class="btn btn-danger" type="submit" id="submit-btn" disabled>
            <i class="fa fa-save"></i> Record Stock Loss
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

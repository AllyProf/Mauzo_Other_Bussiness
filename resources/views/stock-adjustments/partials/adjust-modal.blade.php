@if($form)
@php $multiBusiness = $form['multiBusiness'] ?? false; @endphp
<div class="modal fade" id="adjustStockModal" tabindex="-1" role="dialog" aria-labelledby="adjustStockModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg danger-adjust-modal-dialog modal-dialog-scrollable" role="document">
    <div class="modal-content danger-adjust-modal-content">
      <form action="{{ route('stock-adjustments.store') }}" method="POST" id="adjust-form">
        @csrf
        <div class="modal-header danger-adjust-modal-header">
          <div>
            <h5 class="modal-title mb-0" id="adjustStockModalLabel">
              <i class="fa fa-exclamation-triangle"></i> {{ __('stock_adjustments.modal.title') }}
            </h5>
            <small class="d-block mt-1 opacity-90">{{ __('stock_adjustments.modal.subtitle') }}</small>
          </div>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body danger-adjust-modal-body">
          <div class="alert alert-danger py-2 mb-3 small">
            <i class="fa fa-warning"></i> {{ __('stock_adjustments.danger_hint') }}
          </div>

          <div class="danger-adjust-panel mb-3">
            <div class="row">
              <div class="col-sm-6 mb-2 mb-sm-0">
                <label class="control-label font-weight-bold">{{ __('stock_adjustments.show.date') }}</label>
                <input type="date" name="adjustment_date" class="form-control" value="{{ old('adjustment_date', date('Y-m-d')) }}" required>
              </div>
              <div class="col-sm-6">
                <label class="control-label font-weight-bold">{{ __('stock_adjustments.show.reason') }}</label>
                <select name="reason" class="form-control" required>
                  <option value="">--</option>
                  @foreach($form['reasons'] as $key => $label)
                    <option value="{{ $key }}" {{ old('reason') === $key ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="row mt-3">
              @if($multiBusiness)
              <div class="col-sm-6 mb-2 mb-sm-0">
                <label class="control-label font-weight-bold">{{ __('categories.business_type') }}</label>
                <select id="adjust-business-type" class="form-control">
                  <option value="">--</option>
                  @foreach($form['businessTypes'] as $type)
                    <option value="{{ $type['key'] }}">{{ $type['label'] }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-sm-6">
                <label class="control-label font-weight-bold">{{ __('categories.category_name') }}</label>
                <select id="adjust-category" class="form-control" disabled>
                  <option value="">--</option>
                </select>
              </div>
              @else
              <div class="col-sm-12">
                <label class="control-label font-weight-bold">{{ __('categories.category_name') }}</label>
                <select id="adjust-category" class="form-control">
                  <option value="">--</option>
                  @foreach($form['categoriesList'] ?? [] as $cat)
                    <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                  @endforeach
                </select>
              </div>
              @endif
            </div>
          </div>

          <div class="alert alert-warning py-2 mb-3 small">
            <i class="fa fa-info-circle"></i> {{ __('stock_adjustments.modal.unit_notice') }}
          </div>

          <div class="danger-adjust-table-wrap">
            <table class="table table-bordered table-sm mb-0" id="adjust-items-table">
              <thead class="thead-light">
                <tr>
                  <th>{{ __('tables.columns.item') }}</th>
                  <th class="text-center" style="width:80px;">{{ __('stock_adjustments.modal.current_stock') }}</th>
                  <th class="text-center" style="width:100px;">{{ __('stock_adjustments.modal.new_stock') }}</th>
                  <th class="text-center" style="width:90px;">{{ __('stock_adjustments.modal.change') }}</th>
                  <th style="width:120px;">{{ __('tables.columns.notes') }}</th>
                </tr>
              </thead>
              <tbody id="adjust-items-body">
                <tr id="adjust-empty-row">
                  <td colspan="5" class="text-center text-muted py-4">{{ __('stock_adjustments.modal.step_category') }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="row mt-3">
            <div class="col-md-7">
              <label class="control-label font-weight-bold">{{ __('tables.columns.notes') }}</label>
              <textarea name="notes" class="form-control" rows="2" placeholder="{{ __('stock_adjustments.show.subtitle') }}">{{ old('notes') }}</textarea>
            </div>
            <div class="col-md-5 mt-2 mt-md-0">
              <div class="custom-control custom-checkbox mt-md-4">
                <input type="checkbox" class="custom-control-input" id="confirm_ack" name="confirm_ack" value="1" {{ old('confirm_ack') ? 'checked' : '' }} required>
                <label class="custom-control-label small" for="confirm_ack">{{ __('stock_adjustments.modal.confirm_label') }}</label>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer danger-adjust-modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button class="btn btn-danger" type="submit" id="adjust-submit-btn" disabled>
            <i class="fa fa-wrench"></i> {{ __('stock_adjustments.modal.submit') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

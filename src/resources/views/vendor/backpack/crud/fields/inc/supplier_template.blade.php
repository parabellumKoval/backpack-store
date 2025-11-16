@php
  if(isset($item['countries'])) {
    $contries_string = collect($item['countries'])->pluck('code')->join(',');
  }else {
    $contries_string = '';
  }
@endphp
<div class="supplier-item well row m-1 p-2" data-index="{{ $key }}">
        
    <div class="controls position-absolute" style="right: 20px; top: 10px; z-index:2;">
        <button type="button" class="btn btn-sm btn-danger remove-supplier"><i class="la la-trash"></i> {{ __('backpack-store::admin.supplier.delete') }}</button>
    </div>

    <input type="hidden" name="{{ $field['name'] }}[{{ $key }}][currency]" value="{{ $item['currency'] ?? '' }}" class="currency-input">
    <input type="hidden" name="{{ $field['name'] }}[{{ $key }}][countries]" value="{{ $contries_string }}" class="countries-input">
    
    <div class="form-group col-md-12">        
        <div class="checkbox">
            <input type="hidden" name="{{ $field['name'] }}[{{ $key }}][is_active]" value="0">
            <input type="checkbox" name="{{ $field['name'] }}[{{ $key }}][is_active]" value="1" {{ ($item['is_active'] ?? true) ? 'checked' : '' }}>
            <label class="form-check-label">{{ __('backpack-store::admin.supplier.is_active') }}</label>
        </div>
    </div>



    <div class="form-group col-md-4">
        <label>{{ __('backpack-store::admin.supplier.title') }}</label>
        <select class="form-control supplier-select" name="{{ $field['name'] }}[{{ $key }}][supplier]">
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier['id'] }}" 
                        data-currency="{{ $supplier['currency'] }}"
                        data-countries="{{ $contries_string }}"
                        {{ ($item['supplier'] ?? '') == $supplier['id'] ? 'selected' : '' }}>
                    {{ $supplier['name'] }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group col-md-4">
        <label>{{ __('backpack-store::admin.supplier.product_code') }}</label>
        <input type="text" class="form-control" name="{{ $field['name'] }}[{{ $key }}][code]" value="{{ $item['code'] ?? '' }}">
    </div>

    <div class="form-group col-md-4">
        <label>{{ __('backpack-store::admin.supplier.barcode') }}</label>
        <input type="text" class="form-control" name="{{ $field['name'] }}[{{ $key }}][barcode]" value="{{ $item['barcode'] ?? '' }}">
    </div>

    <div class="form-group col-md-4">
        <label>{{ __('backpack-store::admin.supplier.in_stock') }}</label>
        <input type="number" class="form-control" name="{{ $field['name'] }}[{{ $key }}][in_stock]" value="{{ $item['in_stock'] ?? '' }}">
    </div>

    <div class="form-group col-md-4">
        <label>{{ __('backpack-store::admin.supplier.price') }}</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text currency-label">{{ $item['currency'] ?? '' }}</span>
            </div>
            <input type="number" step="0.01" min="0" class="form-control" name="{{ $field['name'] }}[{{ $key }}][price]" value="{{ $item['price'] ?? '' }}">
        </div>
    </div>

    <div class="form-group col-md-4">
        <label>{{ __('backpack-store::admin.supplier.old_price') }}</label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text currency-label">{{ $item['currency'] ?? '' }}</span>
            </div>
            <input type="number" step="0.01" min="0" class="form-control" name="{{ $field['name'] }}[{{ $key }}][old_price]" value="{{ $item['old_price'] ?? '' }}">
        </div>
    </div>

    <div class="form-group col-md-4">
        <label>{{ __('backpack-store::admin.supplier.last_update') }}</label>
        <div class="d-flex align-items-center">
            <input type="text" readonly disabled class="form-control" name="{{ $field['name'] }}[{{ $key }}][updated_at]" value="{{ $item['updated_at'] ?? '' }}">
        </div>
    </div>

    <div class="form-group col-md-8">
        <label>{{ __('backpack-store::admin.supplier.supplier_countries') }}</label>
        <div class="d-flex align-items-center">
            <input type="text" readonly disabled class="form-control countries-label" value="{{ isset($item['countries']) ? collect($item['countries'])->pluck('name')->join(', ') : '' }}">
        </div>
    </div>

</div>

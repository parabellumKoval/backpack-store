{{-- REPEATABLE FIELD TYPE --}}

@php
  $field['value'] = old($field['name']) ? old($field['name']) : (isset($field['value']) ? $field['value'] : (isset($field['default']) ? $field['default'] : [] ));
  $suppliers = $field['suppliers'];
  $currencies = $field['currencies'];

  // Helper function to format countries data for data attributes
  function formatCountriesForDataAttr($countries) {
      return collect($countries)->pluck('code')->join(',');
  }
@endphp

@include('crud::fields.inc.wrapper_start')

<div class="suppliers-container">
    <label>{!! $field['label'] !!}</label>
    @foreach($field['value'] as $key => $item)
        @include('crud::fields.inc.supplier_template', ['key' => $key, 'item' => $item, 'currencies' => $currencies])
    @endforeach
</div><!-- end .suppliers-container -->

<div class="">
    <button type="button" class="btn btn-outline-primary add-supplier">
        <i class="la la-plus"></i> {{ __('backpack-store::admin.supplier.add') }}
    </button>
</div>

  {{-- HINT --}}
  @if (isset($field['hint']))
    <p class="help-block">{!! $field['hint'] !!}</p>
  @endif
  
@include('crud::fields.inc.wrapper_end')

@if ($crud->fieldTypeNotLoaded($field))
  @php
      $crud->markFieldTypeAsLoaded($field);
  @endphp

  @push('crud_fields_styles')
      <style>
        .form-group label {
          color: #000;
        }
        .form-group.text-danger > label {
          color: #df4759;
        }
        .supplier-item {
            position: relative;
            border: 1px solid #dee2e6;
            border-radius: 3px;
            margin-bottom: 1rem !important;
        }
        .countries-label {
            color: #666;
            font-size: 0.9em;
        }
        
      </style>
  @endpush
  
@push('crud_fields_scripts')
<script>
(function($){

  const suppliers = @json($suppliers);
  const currencies = @json($currencies);

  const supplierTemplate = `{!! str_replace("\n", "", addslashes(view('crud::fields.inc.supplier_template', [
      'field' => $field,
      'suppliers' => $suppliers,
      'currencies' => $currencies,
      'key' => '__INDEX__',
      'item' => []
  ])->render())) !!}`;

  function updateSupplierInfo($item) {
    const supplierId = $item.find('.supplier-select').val();
    const supplier = suppliers.find(s => s.id == supplierId);
    if (!supplier) return;

    // Префиксы валюты
    $item.find('.currency-label').text(supplier.currency);
    $item.find('.currency-input').val(supplier.currency);

    // Страны
    const countryNames = supplier.countries.map(c => c.name);
    $item.find('.countries-label').val(countryNames.join(', '));
    $item.find('.countries-input').val(supplier.countries.map(c => c.code).join(','));
  }

  function initSupplierEvents($container) {
    // Смена поставщика
    $container.on('change', '.supplier-select', function(){
      const $item = $(this).closest('.supplier-item');
      updateSupplierInfo($item);
    });

    // Удалить поставщика
    $container.on('click', '.remove-supplier', function(){
      $(this).closest('.supplier-item').remove();
    });
  }

  function addSupplier() {
    const $container = $('.suppliers-container');
    const newIndex = $container.children().length;
    const html = supplierTemplate.replace(/__INDEX__/g, newIndex);
    const $newItem = $(html);
    $container.append($newItem);
    updateSupplierInfo($newItem);
  }

  $(function(){
    const $root = $('.suppliers-container');
    initSupplierEvents($root);

    // Инициализация существующих
    $('.supplier-item').each(function(){
      updateSupplierInfo($(this));
    });

    // Добавление нового
    $('.add-supplier').on('click', function(){
      addSupplier();
    });
  });

})(jQuery);
</script>
@endpush

@endif

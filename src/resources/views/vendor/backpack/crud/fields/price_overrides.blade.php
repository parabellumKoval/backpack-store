@php
  // $field['value'] = [
  //   ['country' => 'uk', 'currency' => 'UAH', 'price' => 10.5, 'old_price' => 12.0],
  // ]
  // $field['currencies'] = [['code'=>'UAH','name'=>'Hryvnia'], ...]
  // $field['countries']  = ['uk' => ['country'=>'Ukraine', /*...*/], ...]  // без enabled/currency требований
  $value      = $field['value'] ?? old($field['name']) ?? [];
  $currencies = $field['currencies'] ?? [];
  $countries  = $field['countries']  ?? [];
  $name       = $field['name'];

  // Преобразуем ассоц. массив стран -> массив {code,name}
  $countryOptions = [];
  foreach ($countries as $code => $info) {
    $countryOptions[] = [
      'code' => (string)$code,
      'name' => $info['country'] ?? strtoupper((string)$code),
    ];
  }
@endphp

@if ($crud->fieldTypeNotLoaded($field))
  @php $crud->markFieldTypeAsLoaded($field); @endphp

  @include('crud::fields.inc.wrapper_start')
  <label>{!! $field['label'] ?? __('backpack-store::admin.price_overrides.label') !!}</label>

  <div class="price-overrides" data-name="{{ $name }}"
        data-countries='@json($countryOptions)'
        data-currencies='@json($currencies)'>
    <div class="por-list">
      @foreach($value as $i => $row)
        @php
          $rowCountry = $row['country'] ?? '';
          $rowCurrency= $row['currency'] ?? '';
        @endphp
        <div class="por-row row align-items-start g-2 mb-2">
          <div class="form-group col-12 col-md-4">
            <label class="small mb-1 d-block">{{ __('backpack-store::admin.price_overrides.country') }}</label>
            <select class="form-control por-country" name="{{ $name }}[{{ $i }}][country]">
              @foreach($countryOptions as $opt)
                <option value="{{ $opt['code'] }}" {{ $rowCountry === $opt['code'] ? 'selected' : '' }}>
                  {{ $opt['name'] }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="form-group col-12 col-md-3">
            <label class="small mb-1 d-block">{{ __('backpack-store::admin.price_overrides.currency') }}</label>
            <select class="form-control por-currency" name="{{ $name }}[{{ $i }}][currency]">
              @foreach($currencies as $cur)
                <option value="{{ $cur['code'] }}" {{ $rowCurrency === $cur['code'] ? 'selected' : '' }}>
                  {{ $cur['name'] }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="form-group col-12 col-md-2">
            <label class="small mb-1 d-block">{{ __('backpack-store::admin.price_overrides.price') }}</label>
            <div class="input-group">
              <div class="input-group-prepend"><span class="input-group-text por-currency-badge">{{ $rowCurrency }}</span></div>
              <input type="number" step="0.01" class="form-control" name="{{ $name }}[{{ $i }}][price]" value="{{ $row['price'] ?? '' }}">
            </div>
          </div>

          <div class="form-group col-12 col-md-2">
            <label class="small mb-1 d-block">{{ __('backpack-store::admin.price_overrides.old_price') }}</label>
            <div class="input-group">
              <div class="input-group-prepend"><span class="input-group-text por-currency-badge">{{ $rowCurrency }}</span></div>
              <input type="number" step="0.01" class="form-control" name="{{ $name }}[{{ $i }}][old_price]" value="{{ $row['old_price'] ?? '' }}">
            </div>
          </div>

          <div class="col-auto align-self-center">
            <button type="button" class="btn btn-outline-danger por-remove" title="{{ __('backpack-store::admin.remove') }}">
              <i class="la la-trash"></i>
            </button>
          </div>
        </div>
      @endforeach
    </div>

    <button type="button" class="btn btn-outline-primary por-add">
      <i class="la la-plus"></i> {{ __('backpack-store::admin.price_overrides.add') }}
    </button>

    <template class="por-template">
      <div class="por-row row align-items-end g-2 mb-2">
        <div class="form-group col-12 col-md-4">
          <label class="small mb-1 d-block">{{ __('backpack-store::admin.price_overrides.country') }}</label>
          <select class="form-control por-country" name="{{ $name }}[__index__][country]">
            @foreach($countryOptions as $opt)
              <option value="{{ $opt['code'] }}">{{ $opt['name'] }}</option>
            @endforeach
          </select>
        </div>

        <div class="form-group col-12 col-md-3">
          <label class="small mb-1 d-block">{{ __('backpack-store::admin.price_overrides.currency') }}</label>
          <select class="form-control por-currency" name="{{ $name }}[__index__][currency]">
            @foreach($currencies as $cur)
              <option value="{{ $cur['code'] }}">{{ $cur['name'] }}</option>
            @endforeach
          </select>
        </div>

        <div class="form-group col-12 col-md-2">
          <label class="small mb-1 d-block">{{ __('backpack-store::admin.price_overrides.price') }}</label>
          <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text por-currency-badge"></span></div>
            <input type="number" step="0.01" class="form-control" name="{{ $name }}[__index__][price]">
          </div>
        </div>

        <div class="form-group col-12 col-md-2">
          <label class="small mb-1 d-block">{{ __('backpack-store::admin.price_overrides.old_price') }}</label>
          <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text por-currency-badge"></span></div>
            <input type="number" step="0.01" class="form-control" name="{{ $name }}[__index__][old_price]">
          </div>
        </div>

        <div class="col-auto align-self-center">
          <button type="button" class="btn btn-outline-danger por-remove" title="{{ __('backpack-store::admin.remove') }}">
            <i class="la la-trash"></i>
          </button>
        </div>
      </div>
    </template>
  </div>


  {{-- HINT --}}
  @if (isset($field['hint']))
    <p class="help-block">{!! $field['hint'] !!}</p>
  @endif
  @include('crud::fields.inc.wrapper_end')

  @push('crud_fields_scripts')
  <script>
  (function($){

    function getCountries($root){
      // Берём напрямую из data-countries (массив {code,name})
      let data = $root.attr('data-countries');
      try { return JSON.parse(data) || []; } catch(e) { return $root.data('countries') || []; }
    }

    function uniqueCountries($root){
      return $root.find('.por-row .por-country').map(function(){
        return ($(this).val()||'').toString().toLowerCase();
      }).get().filter(Boolean).filter((v,i,a)=>a.indexOf(v)===i);
    }

    function refreshAddVisibility($root){
      const total = getCountries($root).length;
      const used  = uniqueCountries($root).length;
      $root.find('.por-add').toggle(used < total);
    }

    function refreshCountryOptions($root){
      const all = getCountries($root); // [{code,name}]
      const usedSet = new Set(uniqueCountries($root));

      $root.find('.por-row').each(function(){
        const $row = $(this);
        const $sel = $row.find('.por-country');
        const current = ($sel.val()||'').toLowerCase();

        const allowed = all.filter(o => !usedSet.has(o.code.toLowerCase()) || o.code.toLowerCase()===current);

        const selected = $sel.val();
        $sel.empty();
        allowed.forEach(o => $sel.append(`<option value="${o.code}">${o.name}</option>`));
        if (allowed.find(o => o.code === selected)) $sel.val(selected);
        else if (allowed.length) $sel.val(allowed[0].code);

        $sel.trigger('change.select2'); // если используешь select2
      });
    }

    function refreshCurrencyBadge($row){
      const code = $row.find('.por-currency').val() || '';
      $row.find('.por-currency-badge').text(code);
    }

    function renumberNames($root){
      const field = $root.data('name');
      $root.find('.por-row').each(function(i){
        $(this).find('select, input').each(function(){
          const name = $(this).attr('name'); if(!name) return;
          const re = new RegExp(field.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\[[0-9]+\\]');
          $(this).attr('name', name.replace(re, field+'['+i+']'));
        });
      });
    }

    function firstAvailableCountry($root){
      const all = getCountries($root);
      const usedSet = new Set(uniqueCountries($root));
      return all.find(o => !usedSet.has(o.code.toLowerCase())) || null;
    }

    function addRow($root){
      const $tpl = $root.find('template.por-template');
      let html = $tpl.html().replace(/__index__/g, $root.find('.por-row').length);
      const $row = $(html);

      // Присвоим первой доступной стране
      const first = firstAvailableCountry($root);
      if (first) $row.find('.por-country').val(first.code);

      $root.find('.por-list').append($row);

      refreshCurrencyBadge($row);
      refreshCountryOptions($root);
      refreshAddVisibility($root);
      renumberNames($root);
    }

    $(function(){
      const $root = $('.price-overrides');

      // init
      $root.each(function(){
        const $r = $(this);
        $r.find('.por-row').each(function(){ refreshCurrencyBadge($(this)); });
        refreshCountryOptions($r);
        refreshAddVisibility($r);
        renumberNames($r);
      });

      // events
      $(document).on('click', '.price-overrides .por-add', function(){
        addRow($(this).closest('.price-overrides'));
      });

      $(document).on('click', '.price-overrides .por-remove', function(){
        const $r = $(this).closest('.price-overrides');
        $(this).closest('.por-row').remove();
        refreshCountryOptions($r);
        refreshAddVisibility($r);
        renumberNames($r);
      });

      $(document).on('change', '.price-overrides .por-country', function(){
        const $r = $(this).closest('.price-overrides');
        // НЕ меняем валюту по стране (по твоему требованию)
        refreshCountryOptions($r);
        refreshAddVisibility($r);
      });

      $(document).on('change', '.price-overrides .por-currency', function(){
        refreshCurrencyBadge($(this).closest('.por-row'));
      });
    });
  })(jQuery);
  </script>
  @endpush
@endif

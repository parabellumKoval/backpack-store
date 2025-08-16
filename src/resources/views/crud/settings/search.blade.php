@extends(backpack_view('blank'))

@php
  $defaultBreadcrumbs = [
    trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
    __('translator::settings.settings_title') => false
  ];

  $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;
@endphp

@section('header')
  <div class="container-fluid">
    <h2>
      <span class="text-capitalize">{{ trans('backpack-store::search.title') }}</span>
      <!-- <small id="datatable_info_stack">{{ trans('backpack-store::search.desc') }}</small> -->
    </h2>
  </div>
@endsection

@section('content')
<form method="POST" action="{{ route('backpack.search-settings') }}">
  @csrf

  {{-- 🔥 Популярные товары --}}
  <div class="card mb-4">
    <div class="card-header"><h4>{{ trans('backpack-store::search.popular_products.title') }}</h4></div>
    <div class="card-body">
      <div class="form-group">
        <label>{{ trans('backpack-store::search.popular_products.source') }}</label>
        <select name="popular_products__source" id="popular_products_source" class="form-control js-source" data-target="products">
          <option value="auto" {{ $settings['popular_products__source'] == 'auto' ? 'selected' : '' }}>
            {{ trans('backpack-store::search.common.auto') }}
          </option>
          <option value="manual" {{ $settings['popular_products__source'] == 'manual' ? 'selected' : '' }}>
            {{ trans('backpack-store::search.common.manual') }}
          </option>
        </select>
      </div>

      <div id="popular_products_manual" class="{{ $settings['popular_products__source'] == 'manual' ? '' : 'd-none' }}">
        <label>{{ trans('backpack-store::search.popular_categories.order') }}</label>
        <select id="products-select" class="form-control mb-2"></select>
        <ul id="products-sortable" class="list-group mb-3">
          @foreach($settings['popular_products__order'] ?? [] as $product)
            <li class="list-group-item d-flex justify-content-between align-items-center" data-id="{{ $product['id'] }}">
              <span class="handle"><i class="la la-arrows-alt mr-2"></i><span class="item-title">{{ $product['name'] }}</span></span>
              <span class="btn-group btn-group-sm">
                <button type="button" class="btn btn-light move-up"><i class="la la-arrow-up"></i></button>
                <button type="button" class="btn btn-light move-down"><i class="la la-arrow-down"></i></button>
                <button type="button" class="btn btn-light remove-item"><i class="la la-times"></i></button>
              </span>
            </li>
          @endforeach
        </ul>
        <input type="hidden" name="popular_products_order_json" id="popular_products_order_json" value="">
      </div>

      <div class="custom-control custom-switch">
        <input type="hidden" name="popular_products__in_stock" value="0">
        <input name="popular_products__in_stock" type="checkbox" class="custom-control-input" id="popular_products_in_stock" value="1" {{ $settings['popular_products__in_stock'] ? 'checked' : '' }}>
        <label class="custom-control-label" for="popular_products_in_stock">{{ trans('backpack-store::search.popular_products.in_stock') }}</label>
      </div>

      <div class="custom-control custom-switch">
        <input type="hidden" name="popular_products__show_discount" value="0">
        <input name="popular_products__show_discount" type="checkbox" class="custom-control-input" id="popular_products_show_discount" value="1" {{ $settings['popular_products__show_discount'] ? 'checked' : '' }}>
        <label class="custom-control-label" for="popular_products_show_discount">{{ trans('backpack-store::search.popular_products.show_discount') }}</label>
      </div>
    </div>
  </div>

  {{-- 🧭 Популярные категории --}}
  <div class="card mb-4">
    <div class="card-header"><h4>{{ trans('backpack-store::search.popular_categories.title') }}</h4></div>
    <div class="card-body">
      <div class="form-group">
        <label>{{ trans('backpack-store::search.popular_categories.source') }}</label>
        <select name="popular_categories__source" id="popular_categories_source" class="form-control js-source" data-target="categories">
          <option value="auto" {{ $settings['popular_categories__source'] == 'auto' ? 'selected' : '' }}>
            {{ trans('backpack-store::search.common.auto') }}
          </option>
          <option value="manual" {{ $settings['popular_categories__source'] == 'manual' ? 'selected' : '' }}>
            {{ trans('backpack-store::search.common.manual') }}
          </option>
        </select>
      </div>

      <div id="popular_categories_manual" class="{{ $settings['popular_categories__source'] == 'manual' ? '' : 'd-none' }}">
        <label>{{ trans('backpack-store::search.popular_categories.order') }}</label>
        <select id="categories-select" class="form-control mb-2"></select>
        <ul id="categories-sortable" class="list-group mb-3">
          @foreach($settings['popular_categories__order'] ?? [] as $category)
            <li class="list-group-item d-flex justify-content-between align-items-center" data-id="{{ $category['id'] }}">
              <span class="handle"><i class="la la-arrows-alt mr-2"></i><span class="item-title">{{ $category['name'] }}</span></span>
              <span class="btn-group btn-group-sm">
                <button type="button" class="btn btn-light move-up"><i class="la la-arrow-up"></i></button>
                <button type="button" class="btn btn-light move-down"><i class="la la-arrow-down"></i></button>
                <button type="button" class="btn btn-light remove-item"><i class="la la-times"></i></button>
              </span>
            </li>
          @endforeach
        </ul>
        <input type="hidden" name="popular_categories_order_json" id="popular_categories_order_json">
      </div>
    </div>
  </div>

  {{-- 📜 История поиска --}}
  <div class="card mb-4">
    <div class="card-header"><h4>{{ trans('backpack-store::search.search_history.title') }}</h4></div>
    <div class="card-body">
      <div class="custom-control custom-switch">
        <input type="hidden" name="history__enabled" value="0">
        <input name="history__enabled" type="checkbox" class="custom-control-input" id="history_enabled" value="1" {{ $settings['history__enabled'] ? 'checked' : '' }}>
        <label class="custom-control-label" for="history_enabled">{{ trans('backpack-store::search.search_history.enabled') }}</label>
      </div>

      <div class="form-group mt-2">
        <label>{{ trans('backpack-store::search.search_history.limit') }}</label>
        <input type="number" name="history__limit" class="form-control" value="{{ $settings['history__limit'] ?? 50 }}">
      </div>

      <div class="custom-control custom-switch">
        <input type="hidden" name="history__clear_allowed" value="0">
        <input name="history__clear_allowed" type="checkbox" class="custom-control-input" id="history_clear_allowed" value="1" {{ $settings['history__clear_allowed'] ? 'checked' : '' }}>
        <label class="custom-control-label" for="history_clear_allowed">{{ trans('backpack-store::search.search_history.clear_allowed') }}</label>
      </div>
    </div>
  </div>

  {{-- ⚙️ Алгоритм поиска --}}
  <div class="card mb-4">
    <div class="card-header"><h4>{{ trans('backpack-store::search.algorithm.title') }}</h4></div>
    <div class="card-body">
      <div class="custom-control custom-switch">
        <input type="hidden" name="transliteration__enabled" value="0">
        <input name="transliteration__enabled" type="checkbox" class="custom-control-input" id="transliteration_enabled" value="1" {{ $settings['transliteration__enabled'] ? 'checked' : '' }}>
        <label class="custom-control-label" for="transliteration_enabled">{{ trans('backpack-store::search.algorithm.transliteration') }}</label>
      </div>

      <div class="custom-control custom-switch">
        <input type="hidden" name="spellcheck__enabled" value="0">
        <input name="spellcheck__enabled" type="checkbox" class="custom-control-input" id="spellcheck_enabled" value="1" {{ $settings['spellcheck__enabled'] ? 'checked' : '' }}>
        <label class="custom-control-label" for="spellcheck_enabled">{{ trans('backpack-store::search.algorithm.spellcheck') }}</label>
      </div>

      <div class="form-group mt-3">
        <h5>{{ trans('backpack-store::search.algorithm.fields') }}</h5>
        @php
          $availableFields = ['name', 'description', 'brand', 'category', 'sku', 'tags'];
        @endphp
        @foreach($availableFields as $field)
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="fields[]" value="{{ $field }}"
              {{ in_array($field, $settings['fields'] ?? []) ? 'checked' : '' }}>
            <label class="form-check-label">{{ ucfirst($field) }}</label>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- 🧪 Дополнительные функции --}}
  <div class="card mb-4">
    <div class="card-header"><h4>{{ trans('backpack-store::search.extra.title') }}</h4></div>
    <div class="card-body">
      @foreach([
        'global_stats__enabled' => 'extra.stats',
        'autocomplete__enabled' => 'extra.autocomplete',
        'multilang__enabled' => 'extra.multilang',
        'admin_analytics__enabled' => 'extra.analytics',
      ] as $key => $label)
        <div class="custom-control custom-switch">
          <input type="hidden" name="{{ $key }}" value="0">
          <input name="{{ $key }}" type="checkbox" class="custom-control-input" id="{{ $key }}" value="1" {{ $settings[$key] ? 'checked' : '' }}>
          <label class="custom-control-label" for="{{ $key }}">{{ trans('backpack-store::search.' . $label) }}</label>
        </div>
      @endforeach
    </div>
  </div>

  <button type="submit" class="btn btn-primary">{{ trans('backpack-store::search.save') }}</button>
</form>
@endsection

@section('after_scripts')
  <link href="{{ asset('packages/select2/dist/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
  <link href="{{ asset('packages/select2-bootstrap-theme/dist/select2-bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
  <script src="{{ asset('packages/select2/dist/js/select2.full.min.js') }}"></script>
  <script src="{{ asset('packages/select2/dist/js/i18n/' . str_replace('_', '-', app()->getLocale()) . '.js') }}"></script>
  <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
  <script>
    $(function () {
      const locale = '{{ app()->getLocale() }}';

      function updateHidden($sortable, $hidden) {
        const result = $sortable.children().map(function () {
          return {
            id: $(this).data('id'),
            name: $(this).find('.item-title').text().trim()
          };
        }).get();
        $hidden.val(JSON.stringify(result));
      }

      function initSection(type, url) {
        const $source = $('#popular_' + type + '_source');
        const $wrapper = $('#popular_' + type + '_manual');
        const $select = $('#' + type + '-select');
        const $sortable = $('#' + type + '-sortable');
        const $hidden = $('#popular_' + type + '_order_json');

        $sortable.sortable({
          handle: '.handle',
          update: function () { updateHidden($sortable, $hidden); }
        });

        $sortable.on('click', '.move-up', function () {
          const $li = $(this).closest('li');
          $li.prev().before($li);
          updateHidden($sortable, $hidden);
        });
        $sortable.on('click', '.move-down', function () {
          const $li = $(this).closest('li');
          $li.next().after($li);
          updateHidden($sortable, $hidden);
        });
        $sortable.on('click', '.remove-item', function () {
          $(this).closest('li').remove();
          updateHidden($sortable, $hidden);
        });

        $select.select2({
          theme: 'bootstrap',
          ajax: {
            url: url,
            dataType: 'json',
            delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) {
              return {
                results: data.data.map(function (item) {
                  let text = item.uniq_title;
                  if (typeof text === 'object') {
                    text = text[locale] || Object.values(text)[0];
                  }
                  return { id: item.id, text: text };
                })
              };
            }
          },
          minimumInputLength: 1
        }).on('select2:select', function (e) {
          const data = e.params.data;
          if ($sortable.find('li[data-id="' + data.id + '"]').length === 0) {
            const $li = $('<li class="list-group-item d-flex justify-content-between align-items-center" data-id="' + data.id + '">' +
              '<span class="handle"><i class="la la-arrows-alt mr-2"></i><span class="item-title">' + data.text + '</span></span>' +
              '<span class="btn-group btn-group-sm">' +
              '<button type="button" class="btn btn-light move-up"><i class="la la-arrow-up"></i></button>' +
              '<button type="button" class="btn btn-light move-down"><i class="la la-arrow-down"></i></button>' +
              '<button type="button" class="btn btn-light remove-item"><i class="la la-times"></i></button>' +
              '</span></li>');
            $sortable.append($li);
            updateHidden($sortable, $hidden);
          }
          $select.val(null).trigger('change');
        });

        $source.on('change', function () {
          if ($(this).val() === 'manual') {
            $wrapper.removeClass('d-none');
          } else {
            $wrapper.addClass('d-none');
          }
        }).trigger('change');

        updateHidden($sortable, $hidden);
      }

      initSection('products', '{{ url(config('backpack.base.route_prefix', 'admin') . '/api/product') }}');
      initSection('categories', '{{ url(config('backpack.base.route_prefix', 'admin') . '/api/category') }}');
    });
  </script>
@endsection

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
      <small id="datatable_info_stack">{{ trans('translator::settings.settings_desc') }}</small>
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
        <select name="popular_products_source" class="form-control">
          <option value="auto" {{ $settings['popular_products_source'] == 'auto' ? 'selected' : '' }}>
            {{ trans('backpack-store::search.common.auto') }}
          </option>
          <option value="manual" {{ $settings['popular_products_source'] == 'manual' ? 'selected' : '' }}>
            {{ trans('backpack-store::search.common.manual') }}
          </option>
        </select>
      </div>

      <div class="custom-control custom-switch">
        <input type="hidden" name="popular_products_in_stock" value="0">
        <input name="popular_products_in_stock" type="checkbox" class="custom-control-input" id="popular_products_in_stock" value="1" {{ $settings['popular_products_in_stock'] ? 'checked' : '' }}>
        <label class="custom-control-label" for="popular_products_in_stock">{{ trans('backpack-store::search.popular_products.in_stock') }}</label>
      </div>

      <div class="custom-control custom-switch">
        <input type="hidden" name="popular_products_show_discount" value="0">
        <input name="popular_products_show_discount" type="checkbox" class="custom-control-input" id="popular_products_show_discount" value="1" {{ $settings['popular_products_show_discount'] ? 'checked' : '' }}>
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
        <select name="popular_categories_source" class="form-control">
          <option value="auto" {{ $settings['popular_categories_source'] == 'auto' ? 'selected' : '' }}>
            {{ trans('backpack-store::search.common.auto') }}
          </option>
          <option value="manual" {{ $settings['popular_categories_source'] == 'manual' ? 'selected' : '' }}>
            {{ trans('backpack-store::search.common.manual') }}
          </option>
        </select>
      </div>

      @if($settings['popular_categories_source'] == 'manual')
        <label>{{ trans('backpack-store::search.popular_categories.order') }}</label>
        <ul id="categories-sortable" class="list-group mb-3">
          @foreach($settings['popular_categories_order'] ?? [] as $category)
            <li class="list-group-item" data-id="{{ $category['id'] }}">
              <i class="fa fa-arrows-alt mr-2"></i> {{ $category['name'] }}
            </li>
          @endforeach
        </ul>
        <input type="hidden" name="popular_categories_order_json" id="popular_categories_order_json">
      @endif
    </div>
  </div>

  {{-- 📜 История поиска --}}
  <div class="card mb-4">
    <div class="card-header"><h4>{{ trans('backpack-store::search.search_history.title') }}</h4></div>
    <div class="card-body">
      <div class="custom-control custom-switch">
        <input type="hidden" name="search_history_enabled" value="0">
        <input name="search_history_enabled" type="checkbox" class="custom-control-input" id="search_history_enabled" value="1" {{ $settings['search_history_enabled'] ? 'checked' : '' }}>
        <label class="custom-control-label" for="search_history_enabled">{{ trans('backpack-store::search.search_history.enabled') }}</label>
      </div>

      <div class="form-group mt-2">
        <label>{{ trans('backpack-store::search.search_history.limit') }}</label>
        <input type="number" name="search_history_limit" class="form-control" value="{{ $settings['search_history_limit'] ?? 50 }}">
      </div>

      <div class="custom-control custom-switch">
        <input type="hidden" name="search_history_clear_allowed" value="0">
        <input name="search_history_clear_allowed" type="checkbox" class="custom-control-input" id="search_history_clear_allowed" value="1" {{ $settings['search_history_clear_allowed'] ? 'checked' : '' }}>
        <label class="custom-control-label" for="search_history_clear_allowed">{{ trans('backpack-store::search.search_history.clear_allowed') }}</label>
      </div>
    </div>
  </div>

  {{-- ⚙️ Алгоритм поиска --}}
  <div class="card mb-4">
    <div class="card-header"><h4>{{ trans('backpack-store::search.algorithm.title') }}</h4></div>
    <div class="card-body">
      <div class="custom-control custom-switch">
        <input type="hidden" name="transliteration_enabled" value="0">
        <input name="transliteration_enabled" type="checkbox" class="custom-control-input" id="transliteration_enabled" value="1" {{ $settings['transliteration_enabled'] ? 'checked' : '' }}>
        <label class="custom-control-label" for="transliteration_enabled">{{ trans('backpack-store::search.algorithm.transliteration') }}</label>
      </div>

      <div class="custom-control custom-switch">
        <input type="hidden" name="spellcheck_enabled" value="0">
        <input name="spellcheck_enabled" type="checkbox" class="custom-control-input" id="spellcheck_enabled" value="1" {{ $settings['spellcheck_enabled'] ? 'checked' : '' }}>
        <label class="custom-control-label" for="spellcheck_enabled">{{ trans('backpack-store::search.algorithm.spellcheck') }}</label>
      </div>

      <div class="form-group mt-3">
        <h5>{{ trans('backpack-store::search.algorithm.fields') }}</h5>
        @php
          $availableFields = ['name', 'description', 'brand', 'category', 'sku', 'tags'];
        @endphp
        @foreach($availableFields as $field)
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="search_fields[]" value="{{ $field }}"
              {{ in_array($field, $settings['search_fields'] ?? []) ? 'checked' : '' }}>
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
        'global_stats_enabled' => 'extra.stats',
        'autocomplete_enabled' => 'extra.autocomplete',
        'multilang_enabled' => 'extra.multilang',
        'admin_analytics_enabled' => 'extra.analytics',
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
@if($settings['popular_categories_source'] == 'manual')
  <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
  <script>
    $(function () {
      $('#categories-sortable').sortable({
        update: function () {
          const result = $(this).children().map(function () {
            return {
              id: $(this).data('id'),
              name: $(this).text().trim()
            };
          }).get();
          $('#popular_categories_order_json').val(JSON.stringify(result));
        }
      });
    });
  </script>
@endif
@endsection

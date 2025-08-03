<?php

namespace Backpack\Store\app\Http\Controllers\Admin\Settings;

use Illuminate\Http\Request;
use Backpack\Store\Facades\Settings;
use Backpack\CRUD\app\Http\Controllers\CrudController;

class SearchController extends CrudController
{
    public function edit()
    {
      $settings = [
          // 🔥 Популярные товары
          'popular_products_source' => Settings::get('search.popular_products.source', 'auto'),
          'popular_products_in_stock' => Settings::get('search.popular_products.in_stock', false),
          'popular_products_show_discount' => Settings::get('search.popular_products.show_discount', false),

          // 🧭 Популярные категории
          'popular_categories_source' => Settings::get('search.popular_categories.source', 'auto'),
          'popular_categories_order' => Settings::get('search.popular_categories.order', []),

          // 📜 История поиска
          'search_history_enabled' => Settings::get('search.history.enabled', true),
          'search_history_limit' => Settings::get('search.history.limit', 50),
          'search_history_clear_allowed' => Settings::get('search.history.clear_allowed', true),

          // ⚙️ Алгоритм поиска
          'transliteration_enabled' => Settings::get('search.algorithm.transliteration_enabled', true),
          'spellcheck_enabled' => Settings::get('search.algorithm.spellcheck_enabled', true),
          'search_fields' => Settings::get('search.algorithm.fields', ['name', 'description', 'brand']),

          // 🧪 Дополнительные функции
          'global_stats_enabled' => Settings::get('search.extra.global_stats_enabled', true),
          'autocomplete_enabled' => Settings::get('search.extra.autocomplete_enabled', true),
          'multilang_enabled' => Settings::get('search.extra.multilang_enabled', true),
          'admin_analytics_enabled' => Settings::get('search.extra.admin_analytics_enabled', false),
      ];


        return view('store-crud::settings.search', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'popular_products_source' => 'required|in:auto,manual',
            'popular_products_in_stock' => 'nullable|boolean',
            'popular_products_show_discount' => 'nullable|boolean',
            // ... остальные поля по аналогии
        ]);

        foreach ($data as $key => $value) {
            Settings::set('search.' . str_replace('_', '.', $key), $value);
        }

        \Alert::success('Настройки сохранены')->flash();
        return redirect()->back();
    }
}

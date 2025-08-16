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
          'popular_products__source' => Settings::get('search.popular_products.source', 'auto'),
          'popular_products__in_stock' => Settings::get('search.popular_products.in_stock', false),
          'popular_products__show_discount' => Settings::get('search.popular_products.show_discount', false),
          'popular_products__order' => Settings::get('search.popular_products.order', []),

          // 🧭 Популярные категории
          'popular_categories__source' => Settings::get('search.popular_categories.source', 'auto'),
          'popular_categories__order' => Settings::get('search.popular_categories.order', []),

          // 📜 История поиска
          'history__enabled' => Settings::get('search.history.enabled', true),
          'history__limit' => Settings::get('search.history.limit', 50),
          'history__clear_allowed' => Settings::get('search.history.clear_allowed', true),

          // ⚙️ Алгоритм поиска
          'transliteration__enabled' => Settings::get('search.transliteration.enabled', true),
          'spellcheck__enabled' => Settings::get('search.spellcheck.enabled', true),
          'fields' => Settings::get('search.fields', ['name', 'description', 'brand']),

          // 🧪 Дополнительные функции
          'global_stats__enabled' => Settings::get('search.global_stats.enabled', true),
          'autocomplete__enabled' => Settings::get('search.autocomplete.enabled', true),
          'multilang__enabled' => Settings::get('search.multilang.enabled', true),
          'admin_analytics__enabled' => Settings::get('search.admin_analytics.enabled', false),
      ];

      // dd(compact('settings'));
        return view('store-crud::settings.search', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
          'popular_products__source' => 'required|in:auto,manual',
          'popular_products__in_stock' => 'nullable|boolean',
          'popular_products__show_discount' => 'nullable|boolean',
          'popular_products_order_json' => 'nullable|string',
          'popular_categories__source' => 'required|in:auto,manual',
          'popular_categories_order_json' => 'nullable|string',
          'history__enabled' => 'nullable|boolean',
          'history__limit' => 'nullable|integer',
          'history__clear_allowed' => 'nullable|boolean',
          'transliteration__enabled' => 'nullable|boolean',
          'spellcheck__enabled' => 'nullable|boolean',
          'fields' => 'nullable|array',
          'global_stats__enabled' => 'nullable|boolean',
          'autocomplete__enabled' => 'nullable|boolean',
          'multilang__enabled' => 'nullable|boolean',
          'admin_analytics__enabled' => 'nullable|boolean',
        ]);

        foreach ($data as $key => $value) {
            if (in_array($key, ['popular_products_order_json', 'popular_categories_order_json'])) {
                continue;
            }
            Settings::set('search.' . str_replace('__', '.', $key), $value);
        }

        Settings::set('search.popular_products.order', json_decode($data['popular_products_order_json'] ?? '[]', true));
        Settings::set('search.popular_categories.order', json_decode($data['popular_categories_order_json'] ?? '[]', true));

        \Alert::success('Настройки сохранены')->flash();
        return redirect()->back();
    }
}

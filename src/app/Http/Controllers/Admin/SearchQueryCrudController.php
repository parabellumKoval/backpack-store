<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

class SearchQueryCrudController extends CrudController
{
    use ListOperation, ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(\Backpack\Store\app\Models\SearchQuery::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/search-queries');
        CRUD::setEntityNameStrings('запрос', 'История поисковых запросов');

        // Read-only
        CRUD::denyAccess(['create','update','delete']);
        CRUD::enableExportButtons();
    }

    protected function setupListOperation(): void
    {
        $this->crud->addButtonFromModelFunction('top', 'reviews_settings', 'getSettingsButtonHtml', 'end');

        CRUD::addColumns([
            ['name' => 'created_at',    'type' => 'datetime', 'label' => 'Когда'],
            ['name' => 'q',             'type' => 'text',     'label' => 'Запрос'],
            ['name' => 'normalized_q',  'type' => 'text',     'label' => 'Нормализованный'],
            ['name' => 'country_code',  'type' => 'text',     'label' => 'Страна', 'limit' => 10],
            ['name' => 'locale',        'type' => 'text',     'label' => 'Локаль', 'limit' => 10],
            ['name' => 'user_id',       'type' => 'number',   'label' => 'Пользователь'],
            ['name' => 'ip',            'type' => 'text',     'label' => 'IP'],
            ['name' => 'results_count', 'type' => 'number',   'label' => 'Найдено'],
            ['name' => 'took_ms',       'type' => 'number',   'label' => 'Время, мс'],
        ]);

        // Фильтр по дате
        CRUD::filter('created_between')
            ->type('date_range')
            ->label('Дата')
            ->whenActive(function($value) {
                $start = $value['from'].' 00:00:00';
                $end   = $value['to'].' 23:59:59';
                CRUD::addClause('whereBetween', 'created_at', [$start, $end]);
            });

        // Фильтр страна
        CRUD::filter('country_code')
            ->type('dropdown')
            ->label('Страна')
            ->values(function () {
                return \DB::table('ak_search_queries')
                    ->select('country_code')
                    ->whereNotNull('country_code')
                    ->distinct()->pluck('country_code','country_code')->toArray();
            })
            ->whenActive(fn($val) => CRUD::addClause('where', 'country_code', $val));

        // Фильтр «есть результаты»
        CRUD::filter('has_results')
            ->type('dropdown')
            ->label('Результаты')
            ->values(['1' => 'Есть', '0' => 'Нет'])
            ->whenActive(function($val) {
                if ($val === '1') CRUD::addClause('where', 'results_count', '>', 0);
                if ($val === '0') CRUD::addClause('where', 'results_count', '=', 0);
            });

        CRUD::orderBy('created_at', 'desc');
    }

    protected function setupShowOperation(): void
    {
        CRUD::set('show.setFromDb', false);
        $this->setupListOperation(); // те же поля, что в списке
    }
}

<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Store\app\Models\Admin\CurrencyRate;
use Illuminate\Support\Facades\Artisan;
use Prologue\Alerts\Facades\Alert;

class CurrencyRateCrudController extends CrudController
{
    use ListOperation, ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(CurrencyRate::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/currency-rates');
        CRUD::setEntityNameStrings(
            trans('backpack-store::currency-rates.entity_singular'),
            trans('backpack-store::currency-rates.entity_plural')
        );

        // Никаких create/update/delete
        $this->crud->denyAccess(['create', 'update', 'delete']);
        $this->crud->enableExportButtons();

        // Кнопка вверху: "Обновить сейчас"
        $this->crud->addButtonFromView('top', 'refresh_exchange_rates', 'refresh_exchange_rates', 'beginning');

        // Деталка со списком всех курсов
        $this->crud->enableDetailsRow();

        // Сортировка по дате выгрузки
        $this->crud->orderBy('fetched_at', 'desc');
    }

    public function showDetailsRow($id)
    {
        $this->crud->hasAccessOrFail('list');

        $entry = $this->crud->getEntry($id);

        return view('crud::details.currency_rates', [
            'entry' => $entry,
            'crud'  => $this->crud,
        ]);
    }

    protected function setupListOperation(): void
    {
        CRUD::column('fetched_at')->type('datetime')->label(trans('backpack-store::currency-rates.fetched_at'));
        CRUD::column('source')->type('text')->label(trans('backpack-store::currency-rates.source'));
        CRUD::column('base')->type('text')->label(trans('backpack-store::currency-rates.base'));
        CRUD::addColumn([
            'name' => 'rates_count',
            'type' => 'number',
            'label' => trans('backpack-store::currency-rates.rates_count'),
            'searchLogic' => false,
        ]);
        CRUD::column('created_at')->type('datetime')->label(trans('backpack-store::currency-rates.created_at'));
    }

    protected function setupShowOperation(): void
    {
        CRUD::column('fetched_at')->type('datetime')->label(trans('backpack-store::currency-rates.fetched_at'));
        CRUD::column('source')->type('text')->label(trans('backpack-store::currency-rates.source'));
        CRUD::column('base')->type('text')->label(trans('backpack-store::currency-rates.base'));

        // красивый вывод курсов через blade
        CRUD::addColumn([
            'name'        => 'rates_table',
            'label'       => trans('backpack-store::currency-rates.rates_array_label'),
            'type'        => 'view',
            'view'        => 'crud::columns.rates_table',
            'searchLogic' => false,
            'orderable'   => false,
        ]);

        CRUD::column('created_at')->type('datetime')->label(trans('backpack-store::currency-rates.created_at'));
        CRUD::column('updated_at')->type('datetime')->label(trans('backpack-store::currency-rates.updated_at'));
    }

    /**
     * POST /admin/currency-rates/refresh
     */
    public function refreshNow()
    {
        try {
            // Вызов консольной команды, которую ты уже добавил в пакет
            Artisan::call('store:currency:refresh');

            Alert::success(trans('backpack-store::currency-rates.refresh_ok'))->flash();
        } catch (\Throwable $e) {
            Alert::error(trans('backpack-store::currency-rates.refresh_fail', ['msg' => $e->getMessage()]))->flash();
        }

        return back();
    }
}

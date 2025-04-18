<?php

namespace Aimix\Shop\app\Http\Controllers\Admin;

use Aimix\Shop\app\Http\Requests\PaymentRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use Backpack\LangFileManager\app\Models\Language;

/**
 * Class PaymentCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class PaymentCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    private $languages = 'ru';
    
    public function setup()
    {
        $this->crud->setModel('Aimix\Shop\app\Models\Payment');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/payment');
        $this->crud->setEntityNameStrings(
            trans('backpack-store::payment.entity_singular'),
            trans('backpack-store::payment.entity_plural')
        );
        
        $this->languages = config('backpack.crud.locales');
	      $this->crud->query = $this->crud->query->withoutGlobalScopes();
	      $this->crud->model->clearGlobalScopes();
/*
        if(config('aimix.aimix.enable_languages')) {
          $this->languages = Language::getActiveLanguagesNames();
          
        }
*/
    }

    protected function setupListOperation()
    {
        if(config('aimix.aimix.enable_languages')) {
            $this->crud->addFilter([
                'name'  => 'language',
                'type'  => 'select2',
                'label' => trans('backpack-store::payment.filters.language')
            ], function () {
                return $this->languages;
            }, function ($value) {
                $this->crud->addClause('where', 'language_abbr', $value);
            });
        }

        $this->crud->addColumn([
            'name' => 'language_abbr',
            'label' => trans('backpack-store::payment.fields.region'),
        ]);

        $this->crud->addColumn([
            'name' => 'is_active',
            'label' => trans('backpack-store::payment.fields.is_active'),
            'type' => 'boolean'
        ]);

        $this->crud->addColumn([
            'name' => 'name',
            'label' => trans('backpack-store::payment.fields.name'),
        ]);
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(PaymentRequest::class);

        $this->crud->addField([
            'name' => 'language_abbr',
            'label' => trans('backpack-store::payment.fields.region'),
            'type' => 'select_from_array',
            'options' => $this->languages
        ]);

        $this->crud->addField([
            'name' => 'is_active',
            'label' => trans('backpack-store::payment.fields.is_active'),
            'type' => 'boolean',
            'default' => 1
        ]);

        $this->crud->addField([
            'name' => 'name',
            'label' => trans('backpack-store::payment.fields.name')
        ]);

        $this->crud->addField([
            'name' => 'slug',
            'label' => trans('backpack-store::payment.fields.slug'),
            'prefix' => url('/payment').'/',
            'hint' => trans('backpack-store::payment.fields.slug_hint')
        ]);

        $this->crud->addField([
            'name' => 'image',
            'label' => trans('backpack-store::payment.fields.image'),
            'type' => 'browse',
        ]);

        $this->crud->addField([
            'name' => 'icon',
            'label' => trans('backpack-store::payment.fields.icon'),
            'type' => 'textarea',
            'attributes' => [
                'rows' => '7'
            ],
            'hint' => trans('backpack-store::payment.fields.icon_hint'),
        ]);

        $this->crud->addField([
            'name' => 'description',
            'label' => trans('backpack-store::payment.fields.description'),
            'type' => 'ckeditor',
            'attributes' => [
                'rows' => 8,
            ]
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}

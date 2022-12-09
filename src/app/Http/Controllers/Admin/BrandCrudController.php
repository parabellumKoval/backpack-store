<?php

namespace Aimix\Shop\app\Http\Controllers\Admin;

use Aimix\Shop\app\Http\Requests\BrandRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use Backpack\LangFileManager\app\Models\Language;

use App\Models\Country;

/**
 * Class BrandCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class BrandCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    
    private $languages = 'ru';
    
    private $countries;

    public function setup()
    {
        $this->crud->setModel('Aimix\Shop\app\Models\Brand');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/brand');
        $this->crud->setEntityNameStrings('производетеля', 'производители');
        
        $this->countries = Country::NoEmpty()->pluck('name', 'id')->toArray();
        
        if(config('aimix.aimix.enable_languages')) {
          $this->languages = Language::getActiveLanguagesNames();
          
          $this->crud->query = $this->crud->query->withoutGlobalScopes();
          $this->crud->model->clearGlobalScopes();
        }
    }

    protected function setupListOperation()
    {
        // TODO: remove setFromDb() and manually define Columns, maybe Filters
        // $this->crud->setFromDb();
        $this->crud->addFilter([
          'name' => 'country_id',
          'label' => 'Страна',
          'type' => 'select2'
        ], function(){
          return $this->countries;
        }, function($value){
          $this->crud->addClause('where', 'country_id', $value);
        });
        
        if(config('aimix.aimix.enable_languages')) {
          $this->crud->addFilter([
            'name'  => 'language',
            'type'  => 'select2',
            'label' => 'Язык'
          ], function () {
            return $this->languages;
          }, function ($value) { // if the filter is active
            $this->crud->addClause('where', 'language_abbr', $value);
          });
          
          $this->crud->addColumn([
            'name' => 'language_abbr',
            'label' => 'Язык',
          ]);
        }
        
        $this->crud->addColumns([
          [
            'name' => 'logo',
            'label' => 'Логотип',
            'type' => 'image'
          ],
          [
            'name' => 'country_id',
            'label' => 'Страна',
            'type' => 'select',
            'entity' => 'country',
            'attribute' => 'name',
            'model' => 'App\Models\Country',
          ],
          [
            'name' => 'name',
            'label' => 'Название',
          ],
          [
            'name' => 'description',
            'label' => 'Описание',
          ],
        ]);
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(BrandRequest::class);

        // TODO: remove setFromDb() and manually define Fields
        // $this->crud->setFromDb();
      if(config('aimix.aimix.enable_languages')) {
        $this->crud->addField([
          'name' => 'language_abbr',
          'label' => 'Язык',
          'type' => 'select_from_array',
          'options' => $this->languages
        ]);
      }
        
        $this->crud->addFields([
          [
            'name' => 'name',
            'label' => 'Название',
          ],
          [
            'name' => 'slug',
            'label' => 'URL',
            'prefix' => url('/manufacturers').'/',
            'hint' => 'По умолчанию будет сгенерирован из названия.',
            'type' => 'text',
          ],
          [
            'name' => 'logo',
            'label' => 'Логотип',
            'type' => 'browse',
          ],
          [
            'name' => 'country_id',
            'label' => 'Страна',
            'type' => 'select2',
            'entity' => 'country',
            'attribute' => 'name',
            'model' => 'App\Models\Country',
          ],
          [
            'name' => 'description',
            'label' => 'Описание',
            'type' => 'textarea',
            'attributes' => [
              'rows' => '7',
            ]
          ]
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}

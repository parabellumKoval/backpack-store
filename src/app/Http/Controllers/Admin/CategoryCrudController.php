<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Aimix\Shop\app\Http\Requests\CategoryRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use Backpack\LangFileManager\app\Models\Language;

/**
 * Class CategoryCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class CategoryCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    
    private $languages = 'ru';

    public function setup()
    {
        $this->crud->setModel('Aimix\Shop\app\Models\Category');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/prod_category');
        $this->crud->setEntityNameStrings('категорию', 'категории');
        
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
        
        $this->crud->addColumn([
          'name' => 'name',
          'label' => 'Название',
        ]);
        
        $this->crud->addColumn([
          'name' => 'image',
          'label' => 'Изображение',
        ]);
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(CategoryRequest::class);

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
            'type' => 'text'
          ],
          [
            'name' => 'slug',
            'label' => 'URL',
            'prefix' => url('/').'/',
            'hint' => 'По умолчанию будет сгенерирован из названия.'
          ],
          [
            'name' => 'image',
            'label' => 'Изображение',
            'type' => 'browse',
          ],
          [
            'name' => 'description',
            'label' => 'Описание',
            'type' => 'ckeditor'
          ],
          [
            'name' => 'h1',
            'label' => 'H1 заголовок',
            'fake' => true,
			'store_in' => 'extras'
          ],
          [
            'name' => 'meta_title',
            'label' => 'Meta title',
            'fake' => true,
			'store_in' => 'extras'
          ],
          [
            'name' => 'meta_description',
            'label' => 'Meta description',
            'type' => 'textarea',
            'fake' => true,
			'store_in' => 'extras'
          ],
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}

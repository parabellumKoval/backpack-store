<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Backpack\Store\app\Http\Requests\BrandRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use Backpack\LangFileManager\app\Models\Language;

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
        $this->crud->setModel('Backpack\Store\app\Models\Brand');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/brand');
        $this->crud->setEntityNameStrings('бренд', 'бренды');
        
    }

    protected function setupListOperation()
    {
        // TODO: remove setFromDb() and manually define Columns, maybe Filters
        // $this->crud->setFromDb();
        
        $this->crud->addColumn([
          'name' => 'imageSrc',
          'label' => '📷',
          'type' => 'image',
          'height' => '40px',
          'width'  => '40px',
        ]);


        $this->crud->addColumn([
          'name' => 'is_active',
          'label' => '✅',
          'type' => 'check'
        ]);


        $this->crud->addColumn([
          'name' => 'name',
          'label' => 'Название'
        ]);


        $this->crud->addColumn([
            'name' => 'slug',
            'label' => 'Slug',
        ]);
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(BrandRequest::class);

        // TODO: remove setFromDb() and manually define Fields
        // $this->crud->setFromDb();
      
        
        

        // IS ACTIVE
        $this->crud->addField([
          'name' => 'is_active',
          'label' => 'Активен',
          'type' => 'boolean',
          'default' => '1',
          'tab' => 'Основное'
        ]);
        
        // NAME
        $this->crud->addField([
          'name' => 'name',
          'label' => 'Название',
          'type' => 'text',
          'tab' => 'Основное'
        ]);

        // SLUG
        $this->crud->addField([
          'name' => 'slug',
          'label' => 'URL',
          'hint' => 'По умолчанию будет сгенерирован из названия.',
          'tab' => 'Основное'
        ]);


        // DESCRIPTION
        $this->crud->addField([
          'name' => 'content',
          'label' => 'Описание',
          'type' => 'ckeditor',
          'attributes' => [
            'rows' => 7
          ],
          'tab' => 'Основное'
        ]);

        $this->crud->addField([
          'name'  => 'images',
          'label' => 'Изображения',
          'type'  => 'repeatable',
          'fields' => [
            [
              'name' => 'src',
              'label' => 'Изображение',
              'type' => 'browse',
              'hint' => 'Названия файлов загруженных через файловый менеджен должны быть на латинице и без пробелов.'
            ],
            [
              'name' => 'alt',
              'label' => 'alt'
            ],
            [
              'name' => 'title',
              'label' => 'title'
            ],
            [
              'name' => 'size',
              'type' => 'radio',
              'label' => 'Размер',
              'options' => [
                'cover' => 'Cover',
                'contain' => 'Contain'
              ],
              'inline' => true
            ]
          ],
          'new_item_label'  => 'Добавить изобрежение',
          'init_rows' => 1,
          'default' => [],
          'tab' => 'Изображения'
        ]);

        $this->crud->addField([
          'name' => 'meta_title',
          'label' => "Meta Title", 
          'type' => 'text',
          'fake' => true, 
          'store_in' => 'seo',
          'tab' => 'SEO'
        ]);

        $this->crud->addField([
          'name' => 'meta_description',
          'label' => "Meta Description", 
          'type' => 'textarea',
          'fake' => true, 
          'store_in' => 'seo',
          'tab' => 'SEO'
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}

<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Backpack\Store\app\Http\Requests\CategoryRequest;
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
    use \Backpack\CRUD\app\Http\Controllers\Operations\ReorderOperation;
    

    public function setup()
    {
        $this->crud->setModel('Backpack\Store\app\Models\Category');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/category');
        $this->crud->setEntityNameStrings('категорию', 'категории');
    }

    protected function setupReorderOperation()
    {
        // define which model attribute will be shown on draggable elements 
        $this->crud->set('reorder.label', 'name');
        // define how deep the admin is allowed to nest the items
        // for infinite levels, set it to 0
        $this->crud->set('reorder.max_level', 2);
    }
    
    protected function setupListOperation()
    {
        // TODO: remove setFromDb() and manually define Columns, maybe Filters
        // $this->crud->setFromDb(); 
        $this->crud->addColumn([
          'name' => 'imageSrc',
          'label' => 'Фото',
          'type' => 'image',
          'height' => '50px',
          'width'  => '50px',
        ]);
        
        $this->crud->addColumn([
          'name' => 'id',
          'label' => 'ID',
        ]);

        $this->crud->addColumn([
          'name' => 'name',
          'label' => 'Название',
        ]);
        
        $this->crud->addColumn([
          'name' => 'parent',
          'label' => 'Род. категория',
        ]);
        
        $this->crud->addColumn([
          'name' => 'depth',
          'label' => 'Уровень',
        ]);
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(CategoryRequest::class);

        // TODO: remove setFromDb() and manually define Fields
        // $this->crud->setFromDb();
        
        $this->crud->addFields([
          [
            'name' => 'is_active',
            'label' => 'Активна',
            'type' => 'boolean',
            'default' => '1',
            'tab' => 'Основное'
          ],
          [
            'name' => 'name',
            'label' => 'Название',
            'type' => 'text',
            'tab' => 'Основное'
          ],
          [
            'name' => 'slug',
            'label' => 'URL',
            'hint' => 'По умолчанию будет сгенерирован из названия.',
            'tab' => 'Основное'
          ],
          [
            'name' => 'parent',
            'label' => 'Родительская категория',
            'type' => 'relationship',
            'tab' => 'Основное'
          ],
          [
            'name' => 'content',
            'label' => 'Описание',
            'type' => 'ckeditor',
            'tab' => 'Основное'
          ],
          [
            'name'  => 'images',
            'label' => 'Изображения',
            'type'  => 'repeatable',
            'fields' => [
              [
                'name' => 'src',
                'label' => 'Изображение',
                'type' => 'browse'
              ],
              [
                'name' => 'alt',
                'label' => 'alt'
              ],
              [
                'name' => 'title',
                'label' => 'title'
              ]
            ],
            'new_item_label'  => 'Добавить изобрежение',
            'init_rows' => 1,
            'tab' => 'Изображения'
          ],
          [
            'name' => 'h1',
            'label' => 'H1 заголовок',
            'fake' => true,
			      'store_in' => 'seo',
            'tab' => 'SEO'
          ],
          [
            'name' => 'meta_title',
            'label' => 'Meta title',
            'fake' => true,
			      'store_in' => 'seo',
            'tab' => 'SEO'
          ],
          [
            'name' => 'meta_description',
            'label' => 'Meta description',
            'type' => 'textarea',
            'fake' => true,
			      'store_in' => 'seo',
            'tab' => 'SEO'
          ],
          [
            'name' => 'params',
            'label' => 'Параметры',
            'type' => 'table',
            'columns'  => [
              'key'  => 'Ключ',
              'value'  => 'Значение',
            ],
            'fake' => true,
			      'store_in' => 'extras',
            'tab' => 'Дополнительно'
          ],
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}

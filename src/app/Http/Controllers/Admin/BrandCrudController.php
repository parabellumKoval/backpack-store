<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Illuminate\Http\Request;
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


    use \App\Http\Controllers\Admin\Traits\BrandCrud;
    
    private $brand_class = null;

    private $available_languages = null;

    private $langs_list = null;

    public function setup()
    {
      $this->brand_class = config('backpack.store.brands.class', 'Backpack\Store\app\Models\Brand');

        $this->crud->setModel($this->brand_class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/brand');
        $this->crud->setEntityNameStrings('бренд', 'бренды');
        

        $this->available_languages = config('backpack.crud.locales');
        $this->langs_list = array_keys($this->available_languages);
    }

    protected function setupListOperation()
    {
        $langs_list = $this->langs_list;
        
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
          'label' => 'Название',
          'searchLogic' => function ($query, $column, $searchTerm) use($langs_list) {
            $query->where(function($query) use ($searchTerm, $langs_list){
              foreach($langs_list as $index => $lang_key) {
                $function_name = $index === 0? 'whereRaw': 'orWhereRaw';
                $query->{$function_name}('LOWER(JSON_EXTRACT(name, "$.' . $lang_key . '")) LIKE ? ', ['%'.trim(mb_strtolower($searchTerm)).'%']);
              }
            });
          },
        ]);


        $this->crud->addColumn([
            'name' => 'slug',
            'label' => 'Slug',
        ]);

        $this->listOperation();

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

        // IS POPULAR
        $this->crud->addField([
          'name' => 'is_popular',
          'label' => 'Популярный?',
          'type' => 'boolean',
          'fake' => true, 
          'store_in' => 'extras',
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
          'name' => 'h1',
          'label' => 'H1 заголовок',
          'fake' => true,
          'store_in' => 'seo',
          'tab' => 'SEO'
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


      $this->createOperation();
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }


    public function getBrands(Request $request) {
      $search_term = $request->input('q');
      $id = $request->input('keys');

      // langs
      $langs_list = $this->langs_list;

      if($id) {
        $brands = [];
        $brand = $this->brand_class::find($id);

        if($brand) {
          $brands[] = $brand;
        }
        return $brands;
      }

      if ($search_term)
      {
        $locale = \Lang::locale();

        $results = $this->brand_class::
            // where("name->{$locale}", 'LIKE', "%" . $search_term . "%")
            where(function($query) use ($search_term, $langs_list){
              foreach($langs_list as $index => $lang_key) {
                $function_name = $index === 0? 'whereRaw': 'orWhereRaw';
                $query->{$function_name}('LOWER(JSON_EXTRACT(name, "$.' . $lang_key . '")) LIKE ? ', ['%'.trim(mb_strtolower($search_term)).'%']);
              }
            })
          ->orWhere('slug', 'LIKE', '%'.$search_term.'%')
          ->paginate(20);
      }
      else
      {
        $results = $this->brand_class::paginate(20);
      }

      return $results;
    }
}

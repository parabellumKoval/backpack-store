<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Store\app\Http\Controllers\Admin\Base\CrudController;
use Backpack\Store\app\Http\Requests\FaqTemplateRequest;

class FaqTemplateCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\BulkDeleteOperation;

    public function setup(): void
    {
        CRUD::setModel(\Backpack\Store\app\Models\FaqTemplate::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/faq-template');
        CRUD::setEntityNameStrings('FAQ шаблон', 'FAQ шаблоны');
    }

    protected function setupListOperation(): void
    {
        CRUD::addColumn([
            'name' => 'id',
            'label' => '#',
            'type' => 'number',
        ]);

        CRUD::addColumn([
            'name' => 'is_active',
            'label' => 'Активен',
            'type' => 'boolean',
        ]);

        CRUD::addColumn([
            'name' => 'name',
            'label' => 'Название',
            'type' => 'text',
        ]);

        CRUD::addColumn([
            'name' => 'faq_items_count',
            'label' => 'Пунктов FAQ',
            'type' => 'model_function',
            'function_name' => 'getAdminFaqItemsCount',
        ]);
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(FaqTemplateRequest::class);

        CRUD::addField([
            'name' => 'is_active',
            'label' => 'Активен',
            'type' => 'boolean',
            'default' => true,
            'tab' => 'Основное',
        ]);

        CRUD::addField([
            'name' => 'name',
            'label' => 'Название шаблона',
            'type' => 'text',
            'tab' => 'Основное',
            'hint' => 'Используется при выборе шаблона в товарах и категориях.',
        ]);

        CRUD::addField([
            'name' => 'faq_items',
            'label' => 'FAQ пункты',
            'type' => 'repeatable',
            'new_item_label' => 'Добавить пункт FAQ',
            'init_rows' => 0,
            'min_rows' => 0,
            'fake' => true,
            'store_in' => 'extras_trans',
            'tab' => 'FAQ',
            'fields' => [
                [
                    'name' => 'group_title',
                    'label' => 'Группа',
                    'type' => 'text',
                    'wrapper' => [
                        'class' => 'form-group col-md-4',
                    ],
                ],
                [
                    'name' => 'question',
                    'label' => 'Вопрос',
                    'type' => 'text',
                    'wrapper' => [
                        'class' => 'form-group col-md-8',
                    ],
                ],
                [
                    'name' => 'answer',
                    'label' => 'Ответ',
                    'type' => 'textarea',
                    'wrapper' => [
                        'class' => 'form-group col-md-12',
                    ],
                    'attributes' => [
                        'rows' => 4,
                    ],
                ],
            ],
            'hint' => 'Для "универсального пункта" создайте группу с одним вопросом/ответом.',
        ]);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}

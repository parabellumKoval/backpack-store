<?php

namespace Backpack\Store\app\Http\Controllers\Admin\Traits\Fields;

trait SourceTrait {
  protected function getStructureStylesArray($key, $type) {
    $key_name = "{$key}_structure";
    $settings_type = $this->entry->settings[$key_name] ?? null;
    return $settings_type === $type?[]:['style' => 'display: none;'];
  }

  protected function getStructureAttributesArray($key, $type) {
    $key_name = "{$key}_structure";
    $settings_type = $this->entry->settings[$key_name] ?? null;
    return $settings_type === $type?[]:['disabled' => 'disabled'];
  }

  protected function delimiterField($key, $label, $tab) {
    $this->crud->addField([
      'name' => "delim_{$key}_structure",
      'type' => 'custom_html',
      'value' => "<h3>Расположение {$label}</h3>",
      'wrapper' => [
        'data-field-purpose' => 'file'
      ],
      'tab' => $tab
    ]);
  }

  protected function levelIndexField($key, $label, $tab) {

    $this->crud->addField([
      'name' => "{$key}_level_index",
      'label' => "Уровень на котором разположены {$label}",
      'type' => 'number',
      'tab' => $tab,
      'wrapper' => [
        'data-field-purpose' => 'file',
        'data-field-subpurpose' => "{$key}_level"
      ] + $this->getStructureStylesArray($key, 'levels'),
      'attributes' => [] + $this->getStructureAttributesArray($key, 'levels'),
      'fake' => true,
      'store_in' => 'settings',
      'hint' => 'Если открыть файл, то в нем будут кнопки +/- с помощью которых можно сворачивать и разворачивать данные - это значит структура иерархическая (с уровнями).'
    ]);
  

    $html_instruction = file_get_contents(public_path('backpack-store/instruction/levels-sheets.html'));

    $text = $html_instruction;
    $images = [
      '/backpack-store/instruction/instruction-4.png'
    ];

    $this->crud->addField([
      'name'  => "level_index_rules_$key",
      'type'  => 'custom_html',
      'value' => $this->getMoreBtn($text, $images),
      'wrapper' => [
        'data-field-purpose' => 'file',
        'data-field-subpurpose' => "{$key}_level"
      ] + $this->getStructureStylesArray($key, 'levels'),
      'tab' => $tab
    ]);

  }

  protected function visualIdField($key, $label, $tab) {
    $this->crud->addField([
      'name' => "{$key}_visual_id",
      'label' => "Укажите через запятую свойства, которыми визуально выделяется строка(ячейка) {$label}",
      'wrapper' => [
        'data-field-purpose' => 'file',
        'data-field-subpurpose' => "{$key}_visual_id"
      ] + $this->getStructureStylesArray($key, 'visual'),
      'attributes' => [] + $this->getStructureAttributesArray($key, 'visual'),
      'fake' => true,
      'store_in' => 'settings',
      'tab' => $tab
    ]);
  }


  protected function htmlRulesField($key, $tab) {

    $html_instruction = file_get_contents(public_path('backpack-store/instruction/hex-color-sheets.html'));

    $text = $html_instruction;
    $images = [
      '/backpack-store/instruction/instruction-2.png',
      '/backpack-store/instruction/instruction-3.png'
    ];

    $this->crud->addField([
      'name'  => "rules_$key",
      'type'  => 'custom_html',
      'value' => $this->getMoreBtn($text, $images),
      'wrapper' => [
        'data-field-purpose' => 'file',
        'data-field-subpurpose' => "{$key}_visual_id"
      ] + $this->getStructureStylesArray($key, 'visual'),
      'tab' => $tab
    ]);
  }


  protected function columnLetterField($key, $label, $tab) {
    $this->crud->addField([
      'name' => "{$key}_column_letter",
      'label' => "Укажите букву колонки в которой расположены {$label}",
      'wrapper' => [
        'data-field-purpose' => 'file',
        'data-field-subpurpose' => "{$key}_column_letter"
      ] + $this->getStructureStylesArray($key, 'column'),
      'attributes' => [
        'max' => 2
      ] + $this->getStructureAttributesArray($key, 'column'),
      'fake' => true,
      'store_in' => 'settings',
      'tab' => $tab
    ]);
  }

  protected function structureTypeField($key, $label, $label2, $tab){

    $js_attributes = [
      'data-value' => '',
      'onfocus' => "this.setAttribute('data-value', this.value);",
      'onchange' => "
        const value = event.target.value;

        const fields = {
          levels: document.querySelectorAll('[data-field-subpurpose = {$key}_level]'),
          visual: document.querySelectorAll('[data-field-subpurpose = {$key}_visual_id]'),
          column: document.querySelectorAll('[data-field-subpurpose = {$key}_column_letter]')
        };

        Object.values(fields).forEach((fieldGroup) => {
          fieldGroup.forEach((field) => {
            field.style.display = 'none';
            toggleInputs(field, true);
          });
        });

        if (fields[value]) {
          fields[value].forEach((field) => {
            field.style.display = 'block';
            toggleInputs(field, false);
          });
        }

        function toggleInputs(container, disable) {
          container.querySelectorAll('input').forEach((input) => {
            input.disabled = disable;
          });
        }
      "
    ];

    $this->crud->addField([
      'name' => "{$key}_structure",
      'label' => "Структура {$label}",
      'type' => 'select_from_array',
      'attributes' => $js_attributes,
      'options' => [
        'levels' => "{$label2} расположены в отдельных строках на различных иерархических уровнях (в документе есть кнопки +/-)",
        'visual' => "{$label2} расположены в отдельных строках и размечены визуально (например жирность или размер шрифта)",
        'column' => "{$label2} находятся в отдельных колонках (указаны к каждому товару)"
      ],
      'allows_null' => true,
      'default' => null,
      'wrapper' => [
        'data-field-purpose' => 'file'
      ],
      'fake' => true,
      'store_in' => 'settings',
      'hint' => "Укажите каким образом в документе размечены {$label2}",
      'tab' => $tab
    ]);

  }

  protected function fileCategoriesField() {
    $tab = 'Настройки категорий';
    // header
    $this->delimiterField('categories', 'категорий', $tab);

    // 
    $this->structureTypeField('categories', 'категорий', 'Категории', $tab);
    
    $this->columnLetterField('categories', 'категории', $tab);
    $this->levelIndexField('categories', 'Категории', $tab);
    $this->visualIdField('categories', 'Категорий', $tab);
    $this->htmlRulesField("categories", $tab);
  }

  protected function fileBrandsField() {
    $tab = 'Настройки брендов';

    // header
    $this->delimiterField('brands', 'брендов', $tab);

    // 
    $this->structureTypeField('brands', 'брендов', 'Бренды', $tab);
    
    $this->columnLetterField('brands', 'бренды', $tab);
    $this->levelIndexField('brands', 'Бренды', $tab);
    $this->visualIdField('brands', 'Брендов', $tab);
    $this->htmlRulesField("brands", $tab);
  }


}
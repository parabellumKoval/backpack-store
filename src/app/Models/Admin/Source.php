<?php

namespace Backpack\Store\app\Models\Admin;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;

// MODEL
use Backpack\Store\app\Models\Source as BaseSource;

class Source extends BaseSource
{   
  // public function getLinkAttribute(){
  //   if($this->type === 'xml_link') {
  //     return $this->link;
  //   }else {
  //     return null;
  //   }
  // }

  // public function getFileAttribute($value) {
  //   return $this->attributes['link'];
  // }

    /**
     * setRulesAttribute
     *
     * @param  mixed $values
     * @return void
     */
    public function setRulesAttribute($values)
    {
        $filtered_value = [];

        foreach ($values as $value) {
            // если элемент — массив, приводим к объекту для единообразного доступа
            if (is_array($value)) {
                $value = (object)$value;
            }

            // clear by type
            if ($value->type !== 'overprice') {
                $value->overprice = null;
            }

            if ($value->type !== 'exchange') {
                $value->exchange_coff = null;
            }

            // clear by target
            if ($value->target !== 'brand') {
                $value->brands = null;
            }

            if ($value->target !== 'code') {
                $value->codes = null;
            }

            if ($value->target !== 'name') {
                $value->names = null;
            }

            if ($value->target !== 'category') {
                $value->categories = null;
            }

            if ($value->target !== 'price') {
                $value->min_price = null;
                $value->max_price = null;
            }

            if ($value->target !== 'inStock') {
                $value->in_stock = null;
            }

            $filtered_value[] = $value;
        }

        $this->attributes['rules'] = json_encode($filtered_value);
    }

    public function setFileAttribute($value)
    {
      // if($this->attributes['type'] === 'xml_link') {
      //   $this->attributes['link'] = $value;
      //   return;
      // }

      $disk_name = 'excel'; // Используем диск "excel"
  
      // dd($value);
      if ($value instanceof UploadedFile) {
          // Удаляем старый файл, если он существует
          if (!empty($this->file) && Storage::disk($disk_name)->exists($this->file)) {
            Storage::disk($disk_name)->delete($this->file);
          }
  
          // Генерируем случайное имя файла с его оригинальным расширением
          $randomName = Str::random(20) . '.' . $value->getClientOriginalExtension();

          // Сохраняем новый файл на диск "excel"
          $path = Storage::disk($disk_name)->putFileAs('', $value, $randomName); // Пустая строка = сохранение без подпапок
          $this->attributes['file'] = $path;
      }elseif(empty($value)) {
        $this->attributes['file'] = null;
      }
    }
}

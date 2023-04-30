<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
    
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;

// TRANSLATIONS
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Attribute extends Model
{
    use HasFactory;
    use CrudTrait;
    use Sluggable;
    use SluggableScopeHelpers;
    use HasTranslations;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_attributes';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];
    // protected $dates = [];
    protected $casts = [
      'values' => 'object'
    ];

    protected $translatable = ['name', 'values', 'content', 'default_value', 'si'];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    public function clearGlobalScopes()
    {
        static::$globalScopes = [];
    }
    
    public function toArray()
    {
      
      return [
        'id' => $this->id,
        'name' => $this->name,
        'slug' => $this->slug,
        'attribute_group_id' => $this->attribute_group_id,
        'icon' => $this->icon,
        'description' => $this->description,
        'si' => $this->si,
        'default_value' => $this->default_value,
        'values' => $this->values,
        'type' => $this->type,
        'is_important' => $this->is_important,
        'is_active' => $this->is_active,
        'in_filters' => $this->in_filters,
        'in_properties' => $this->in_properties,
      ];
    }
    
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'slug_or_name',
            ],
        ];
    }

    public function getCurrentLang() {
      $lang = request()->query('locale');

      //dd($lang);

      if(!$lang) {
        $lang = config('app.locale', 'en');
      }

      return $lang;
    }
    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function categories()
    {
        return $this->belongsToMany('Backpack\Store\app\Models\Category', 'ak_attribute_category');
    }
    
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    // public function scopeNoEmpty($query){
    //   return $query->has('modifications');
    // }
    
    public function scopeAllFromCategory($query, $category) {
      if(!$category)
        return $query->get();
      else
        return $category->attributes()->get();
    }
    
    public function scopeImportant($query)
    {
      return $query->where('is_important', 1);
    }
    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    public function getSlugOrNameAttribute()
    {
        if ($this->slug != '') {
            return $this->slug;
        }

        return $this->name;
    }
    
    public function getMaxValueAttribute(){
     if(!$this->modifications->count())
      return 0;
     
     $max = $this->modifications->max('pivot.value');
     return $max;
    }
    
    public function getMinValueAttribute(){
     if(!$this->modifications->count())
      return 0;
       
     $min = $this->modifications->min('pivot.value');
     return $min;
    }
    
    public function getJsonAllAttribute(){
     return collect($this->getAttributes())->toJson();   
    }
    
    public function getInputValuesAttribute(){
      $values = array(
          'color' => null,
          'number' => [
            'step' => 0,
            'min' => '',
            'max' => ''
          ],
          'range' => [
            'step' => 0,
            'min' => '',
            'max' => ''
          ],
          'datetime' => [
            'datetime' => null,
            'date' => null,
            'daterange' => null,
          ],
          'select' => null,  
      );

      $this_values = json_decode($this->values);
        
      if($this->type == 'range')
      {
        if(isset($this_values->step))
        $values['range']['step'] = $this_values->step;
        
        if(isset($this_values->min))
        $values['range']['min'] = $this_values->min;
        
        if(isset($this_values->max))
        $values['range']['max'] = $this_values->max;
        
      }elseif($this->type == 'color')
      {
        $values['color'] = $this_values;

      }elseif($this->type == 'number')
      {
        if(isset($this_values->step))
        $values['number']['step'] = $this_values->step;
        
        if(isset($this_values->min))
        $values['number']['min'] = $this_values->min;
        
        if(isset($this_values->max))
        $values['number']['max'] = $this_values->max;
        
      }elseif($this->type == 'datetime')
      {
        if(isset($this_values) && $this_values == 'datetime')
        $values['datetime']['datetime'] = 'selected="selected"';
        
        if(isset($this_values) && $this_values == 'date')
        $values['datetime']['date'] = 'selected="selected"';
        
        if(isset($this_values) && $this_values == 'daterange')
        $values['datetime']['daterange'] = 'selected="selected"';
        
      }elseif($this->type == 'select' || $this->type == 'checkbox' || $this->type == 'radio')
      {
        if(isset($this_values))
        $values['select'] = $this_values;
        
      }

        return $values;
    }
      
    public function getPivotValueAttribute(){
      if(!$this->pivot || $this->pivot->value === null)
        return null;
      
      $this_values = json_decode($this->values);

      if($this->type === 'checkbox') 
      {
        $indexes = json_decode($this->pivot->value);
        
        if($indexes && !empty($indexes))
          $human_value = array_map(fn($index) => isset($this_values[$index])? $this_values[$index]: null, $indexes);
        else
          $human_value = null;
      }
      elseif($this->type === 'radio')
      {
        $index = $this->pivot->value;
        $human_value = isset($this_values[$index])? $this_values[$index]: null;
      }
      else 
      {
        $human_value = $this->pivot->value;
      }
        
      return $human_value;
    }
    
    
    
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    // public function setSiAttribute($value){
    //   $requestValue = \Request::all()['value'];
    //   if(is_array($requestValue))
    //   $attr_value = collect(array_filter($requestValue))->toJson();
    //  else
    //   $attr_value = json_encode($requestValue);

    //   $this->attributes['value'] = $attr_value;
      
    // }

    // public function setValuesAttribute($value) {
    //   $this->setTranslation('values', 'en', json_encode($value));
    //   //dd($value);
    // }

    public function setTypeAttribute($value){
      $this->attributes['type'] = $value['type'];
      //$this->attributes['values'] = isset($value['values']) ? json_encode($value['values']) : null;

      if(isset($value['values'])) {
        //$this->setTranslation('values', $this->getCurrentLang(), json_encode($value['values']));
        //dd($value['values']);
        //$this->values = $value['values'];
      }
    }
}

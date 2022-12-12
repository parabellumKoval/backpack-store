<?php

namespace Backpack\Store\Models;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
    
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Attribute extends Model
{
    use HasFactory;
    use CrudTrait;
    use Sluggable;
    use SluggableScopeHelpers;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'attributes';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];
    // protected $dates = [];
    protected $casts = [
      'values' => 'object'
    ];

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
        'human_value' => $this->humanValue,
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

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function categories()
    {
        return $this->belongsToMany('Backpack\Store\Models\Category');
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
        
        if($this->type == 'range')
        {
          if(isset($this->values->step))
          $values['range']['step'] = $this->values->step;
          
          if(isset($this->values->min))
          $values['range']['min'] = $this->values->min;
          
          if(isset($this->values->max))
          $values['range']['max'] = $this->values->max;
          
        }elseif($this->type == 'color')
        {
          $values['color'] = $this->values;

        }elseif($this->type == 'number')
        {
          if(isset($this->values->step))
          $values['number']['step'] = $this->values->step;
          
          if(isset($this->values->min))
          $values['number']['min'] = $this->values->min;
          
          if(isset($this->values->max))
          $values['number']['max'] = $this->values->max;
          
        }elseif($this->type == 'datetime')
        {
          if(isset($this->values) && $this->values == 'datetime')
          $values['datetime']['datetime'] = 'selected="selected"';
          
          if(isset($this->values) && $this->values == 'date')
          $values['datetime']['date'] = 'selected="selected"';
          
          if(isset($this->values) && $this->values == 'daterange')
          $values['datetime']['daterange'] = 'selected="selected"';
          
        }elseif($this->type == 'select' || $this->type == 'checkbox' || $this->type == 'radio')
        {
          if(isset($this->values))
          $values['select'] = $this->values;
          
        }
        return $values;
      }
      
      public function getPivotValueAttribute(){
        if(!$this->pivot || !$this->pivot->value)
          return null;
          
          return $this->pivot->value;
      }
        
      public function getHumanValueAttribute()
      {
        // return $this->pivotValue !== null? ($this->pivotValue . ' ' . $this->si) : null;
        return $this->pivotValue !== null? ($this->pivotValue . $this->si) : null;
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
    public function setTypeAttribute($value){
    
      $this->attributes['type'] = $value['type'];
      $this->attributes['values'] = isset($value['values']) ? json_encode($value['values']) : null;
    }
}

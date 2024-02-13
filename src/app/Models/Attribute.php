<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
    
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;

// TRANSLATIONS
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Store\database\factories\AttributeFactory;

// MODELS
use Backpack\Store\app\Models\AttributeValue;
use Backpack\Store\app\Models\AttributeProduct;

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
    // protected $guarded = ['id'];
    protected $fillable = [
      'name',
      'slug',
      'values',
      'content',
      'extras',
      'extras_trans',
      'type',
      'is_active',
      'in_filters',
      'in_properties',
      'si',
      'default_value'
    ];
    // protected $hidden = [];
    // protected $dates = [];
    protected $casts = [
      'extras' => 'array',
    ];

    protected $fakeColumns = [
      'si', 'default_value', 'min', 'max', 'step', 'extras', 'extras_trans'
    ];

    protected $translatable = ['name', 'content', 'extras_trans'];

    public static $TYPES;
    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    public function __construct(array $attributes = array()) {
      parent::__construct($attributes);

      self::$TYPES = [
        'checkbox' => __('shop.fieldType.checkbox'),
        'radio' => __('shop.fieldType.radio'),
        'number' => __('shop.fieldType.number')
      ];
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
      return AttributeFactory::new();
    }
        
    /**
     * clearGlobalScopes
     *
     * @return void
     */
    public function clearGlobalScopes()
    {
        static::$globalScopes = [];
    }
        
    /**
     * toArray
     *
     * @return void
     */
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
        
    /**
     * sluggable
     *
     * @return array
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'slug_or_name',
            ],
        ];
    }
    
    /**
     * getCurrentLang
     *
     * @return void
     */
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

    public function values()
    {
      return $this->hasMany(AttributeValue::class);
    }
    
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    // public function scopeNoEmpty($query){
    //   return $query->has('modifications');
    // }
        
    /**
     * scopeAllFromCategory
     *
     * @param  mixed $query
     * @param  mixed $category
     * @return void
     */
    public function scopeAllFromCategory($query, $category) {
      if(!$category)
        return $query->get();
      else
        return $category->attributes()->get();
    }
        
    /**
     * scopeActive
     *
     * @param  mixed $query
     * @return void
     */
    public function scopeActive($query)
    {
      return $query->where('is_active', 1);
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
        
    /**
     * getMaxValueAttribute
     *
     * @return void
     */
    public function getMaxValueAttribute(){
     if(!$this->modifications->count())
      return 0;
     
     $max = $this->modifications->max('pivot.value');
     return $max;
    }
        
    /**
     * getMinValueAttribute
     *
     * @return void
     */
    public function getMinValueAttribute(){
     if(!$this->modifications->count())
      return 0;
       
     $min = $this->modifications->min('pivot.value');
     return $min;
    }
        
    /**
     * getJsonAllAttribute
     *
     * @return void
     */
    public function getJsonAllAttribute(){
     return collect($this->getAttributes())->toJson();   
    }
        
    /**
     * getInputValuesAttribute
     * 
     * Prepage values to Dashboard
     *
     * @return void
     */
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
    
      // type range
      if($this->type == 'range')
      {
        if(isset($this_values->step))
        $values['range']['step'] = $this_values->step;
        
        if(isset($this_values->min))
        $values['range']['min'] = $this_values->min;
        
        if(isset($this_values->max))
        $values['range']['max'] = $this_values->max;
        
      }
      // type color
      elseif($this->type == 'color')
      {
        $values['color'] = $this_values;
      }
      // type number
      elseif($this->type == 'number')
      {
        if(isset($this_values->step))
        $values['number']['step'] = $this_values->step;
        
        if(isset($this_values->min))
        $values['number']['min'] = $this_values->min;
        
        if(isset($this_values->max))
        $values['number']['max'] = $this_values->max;
      }
      // type datetime
      elseif($this->type == 'datetime')
      {
        if(isset($this_values) && $this_values == 'datetime')
        $values['datetime']['datetime'] = 'selected="selected"';
        
        if(isset($this_values) && $this_values == 'date')
        $values['datetime']['date'] = 'selected="selected"';
        
        if(isset($this_values) && $this_values == 'daterange')
        $values['datetime']['daterange'] = 'selected="selected"';
      }
      // type checkbox / radio / select
      elseif($this->type == 'select' || $this->type == 'checkbox' || $this->type == 'radio')
      {
        if(isset($this_values))
          $values['select'] = $this_values;  
      }

        return $values;
    }
          
    /**
     * getPivotValueAttribute
     * 
     * Pivot field contains:
     * -- indexes of available values from Attribute if type: radio / checkbox
     * -- value itself if Attribute type is: number / string
     *
     * @return void
     */
    public function getPivotValueAttribute(){
      // If empty return null
      if(!$this->pivot || $this->pivot->value === null)
        return null;
      
      // Get values from attribute
      $this_values = json_decode($this->values);

      if($this->type === 'checkbox') 
      {
        // Get indexes
        $indexes = json_decode($this->pivot->value);
        
        if($indexes && !empty($indexes)) {
          // Get values 
          $human_value = array_map(fn($index) => isset($this_values[$index])? $this_values[$index]: null, $indexes);
        } else {
          $human_value = null;
        }
      }
      elseif($this->type === 'radio')
      {
        // Correct value is - one index
        $index = $this->pivot->value;

        // Try find value from values-list using index
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
    
}

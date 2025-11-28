<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
    
// TRANSLATIONS
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Store\database\factories\AttributeValueFactory;

use Backpack\Store\app\Models\Attribute;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class AttributeValue extends Model
{
    use HasFactory;
    use CrudTrait;
    use HasTranslations;
    use FormatsUniqAttribute;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_attribute_values';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    // protected $guarded = ['id'];
    protected $fillable = ['value', 'attribute_id', 'transform', 'extras'];
    // protected $hidden = [];
    // protected $dates = [];
    protected $casts = [
      'extras' => 'array'
    ];

    protected $translatable = ['value'];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
      return AttributeValueFactory::new();
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
        'value' => $this->value
      ];
    }
        

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }
    
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    
    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    public function getUniqStringAttribute(): string
    {
        $attribute = $this->relationLoaded('attribute') ? $this->getRelation('attribute') : null;

        return $this->formatUniqString([
            '#'.$this->id,
            $this->value,
            $attribute?->name ?? sprintf('attribute #%s', $this->attribute_id ?? '?'),
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $attribute = $this->relationLoaded('attribute') ? $this->getRelation('attribute') : null;
        $headline = $this->formatUniqString([
            '#'.$this->id,
            $this->value,
        ]);

        return $this->formatUniqHtml($headline, [
            $attribute?->name ?? sprintf('attribute #%s', $this->attribute_id ?? '?'),
            $this->transform_value_string ? 'transform: '.$this->transform_value_string : null,
        ]);
    }
    
    /**
     * getTransformValueAttribute
     *
     * @return void
     */
    public function getTransformValueAttribute(){
      if(!isset($this->extras['transform_value']) || empty($this->extras['transform_value'])) {
        return null;
      }

      return $this->extras['transform_value'];
    }
    
    /**
     * getTransformValueStringAttribute
     *
     * @return void
     */
    public function getTransformValueStringAttribute(){
      if(!isset($this->extras['transform_value']) || empty($this->extras['transform_value'])) {
        return '';
      }

      if(!is_array($this->extras['transform_value'])) {
        return $this->extras['transform_value'];
      }
      
      return implode('|', $this->extras['transform_value']);
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    
}

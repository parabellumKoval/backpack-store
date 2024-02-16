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

class AttributeValue extends Model
{
    use HasFactory;
    use CrudTrait;
    use HasTranslations;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_attribute_values';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    // protected $guarded = ['id'];
    protected $fillable = ['value', 'type', 'attribute_id', 'attribute'];
    // protected $hidden = [];
    // protected $dates = [];
    // protected $casts = [
    //   'value' => 'object'
    // ];

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

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    
}

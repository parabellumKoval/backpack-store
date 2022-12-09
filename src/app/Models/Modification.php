<?php

namespace Aimix\Shop\app\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;

use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;

class Modification extends Model
{
    use CrudTrait;
    use Sluggable;
    use SluggableScopeHelpers;
    //use HasTranslations;
    
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'modifications';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];
    // protected $dates = [];
    protected $casts = [
      'extras' => 'array',
      'images' => 'array'
    ];
    
   // protected $translatable = ['price'];
    
    public $attributes_array;
    
    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    
    public function sluggable()
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
    public function attrs()
    {
        return $this->belongsToMany('Aimix\Shop\app\Models\Attribute')->using('Aimix\Shop\app\Models\AttributeModification')->withPivot('value');
    }
    
    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }
    
    
    
    public function getPluckedAttributesArray()
    {
        //dd($this->attrs);
        return $this->attrs->pluck('pivot.value', 'id');

    }
      
    public function toArray()
    {
      return [
        'id' => $this->id,
        'name' => $this->name,
        'slug' => $this->slug,
        'price' => $this->price,
        'old_price' => $this->old_price,
        'is_active' => $this->is_active,
        'is_default' => $this->is_default,
        'is_pricehidden' => $this->is_pricehidden,
        'in_stock' => $this->in_stock,
        'code' => $this->code,
        'attrs' => $this->getPluckedAttributesArray(),
        'extras' => $this->extras,
        'images' => $this->images,
        'amount' => isset($this->amount)? $this->amount : 1,
        'product_name' => $this->product->name,
        'product_image' => url($this->product->image),
        'product_link' => $this->product->link
      ];
    }
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopeBase($query)
    {
      return $query->where('is_default', 1)->first();
    }
    public function scopeNotBase($query)
    {
      return $query->where('is_default', 0);
    }
    
    public function scopeComplectation($query, $name)
    {
      return $query->where('extras', 'like', '%'.$name.'%');
    }
    
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

    public function getImagesAttribute($value)
    {
	    $images = json_decode($value);
	    
	    if($images)
		    $images = array_map(function($item){
			    return url(str_replace('uploads', 'glide', $item) . '?h=311&w=311');
		    }, $images);
		else
			$images = [];
	    
        return $images;
    }
    
    public function getProductImageAttribute()
    {
        return $this->product->image;
    }

    public function getProductLinkAttribute()
    {
        return $this->product->link;
    }

    public function getProductNameAttribute()
    {
        return $this->product->name;
    }

    public function getPivotAmountAttribute()
    {
        return $this->pivot->amount;
    }
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    
    public function setAttrsAttribute($value)
    {
      $this->attributes_array = $value;
    }
}

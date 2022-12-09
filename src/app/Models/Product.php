<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;

class Product extends Model
{
    use CrudTrait;
    use Sluggable;
    use SluggableScopeHelpers;
    // use HasTranslations;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_products';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];
    // protected $dates = [];
    protected $casts = [
      'extras' => 'array'
    ];
    protected $fakeColumns = [
      'sales', 'extras'
    ];
    
    protected $translatable = ['name', 'description', 'extras'];
    
    public $modifications_array = [];
    
    public $isModificationRelation = false;
    public $test = [];

    public $images_array = [];
    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    protected static function boot()
    {
        parent::boot();
        if(config('aimix.aimix.enable_languages')) {
          $language = session()->has('lang')? session()->get('lang'): 'ru';
          
          static::addGlobalScope('language', function (Builder $builder) use ($language) {
              $builder->where('language_abbr', $language);
          });
        }
    }
    
    public function clearGlobalScopes()
    {
        static::$globalScopes = [];
    }
    
    public function toArray()
    {
      $lang = session()->has('lang')? session()->get('lang') : 'ru';
      
      $salePercent = $this->baseModification->old_price ? number_format(($this->baseModification->old_price - $this->baseModification->price) * 100 / $this->baseModification->old_price, 0) : null;
      
      return [
        'id' => $this->id,
        'name' => $this->name,
        'slug' => $this->slug,
        'category_id' => $this->category_id,
        'brand_id' => $this->brand_id,
        'price' => $this->baseModification->price,
        'old_price' => $this->baseModification->old_price,
        'sale_percent' => $salePercent,
        'is_active' => $this->is_active,
        'is_hit' => $this->is_hit,
        'rating' => $this->rating,
        'attrs' => $this->baseModification->getPluckedAttributesArray(),
        'extras' => $this->extras,
        'image' => url($this->image),
        'images' => $this->baseModification->images,
        'link' => $this->link,
        'amount' => isset($this->amount)? $this->amount : 1,
        'code' => $this->baseModification->code,
        'in_stock' => $this->baseModification->in_stock,
        'description' => nl2br($this->description),
        
      ];
    }
    

    
    public function sluggable():array
    {
        return [
            'slug' => [
                'source' => 'slug_or_name',
            ],
        ];
    }
    
    public function getLanguageAttribute(){
      $locale = $this->locale?: str_replace('-', '_', \Session::get('lang'));
      $locale_parts = explode('_', $locale);
      $locale_parts[1] = isset($locale_parts[1])? strtoupper($locale_parts[1]): null;
      
      $locale = implode('_', $locale_parts);
      
      return $locale;
    }
    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    // public function modifications()
    // {
    //   $this->isModificationRelation = true;
    //   return $this->hasMany('App\Models\Modification');
    // }
    
    public function category()
    {
      return $this->belongsTo('Backpack\Store\app\Models\Category', 'category_id');
    }
    
    // public function brand()
    // {
    //   return $this->belongsTo('\Aimix\Shop\app\Models\Brand');
    // }

    // public function reviews()
    // {
    //   return $this->hasMany('\Aimix\Review\app\Models\Review');
    // }
    
    public function orders()
    {
      return $this->belongsToMany('Backpack\Store\app\Models\Order');
    }
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
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
    
    public function getSalesAttribute()
    {
      return json_decode($this->extras['sales']);
    }
    
    public function getComplectationsAttribute()
    {
	    if(!$this->baseModification->count())
	    	return null;
	    	
	    $extras = $this->modifications()->base()->extras;
	    $complectations = $extras? $this->modifications()->base()->extras['complectations']: null;
	    	
      return $complectations;
    }

    public function getNotBaseModificationsAttribute()
    {
/*
      if($this->locale){
      	return $this->hasMany('App\Models\Modification')->where('language_abbr', $this->language);
      }
      else{
	      if($this->hasMany('App\Models\Modification')->where('language_abbr', $this->language)->count()){
	      	return $this->hasMany('App\Models\Modification')->where('language_abbr', $this->language);
	      }else
	      	return $this->hasMany('App\Models\Modification');
      }
*/
     // dd($this->modifications()->where('language_abbr', $this->language)->notBase()->count());
      return $this->modifications()->where('language_abbr', $this->language)->notBase();
    }
    
    public function getBaseModificationAttribute()
    {
      return $this->modifications()->base();
    }
    
    public function getBaseAttributesAttribute()
    {
      return $this->baseModification->attrs()->important()->get();
    }
    public function getFullnameAttribute()
    {
      return $this->brand->name . ' ' . $this->name;
    }
    
    public function getPriceAttribute()
    {
      
      $price = $this->baseModification->price;
      $old_price = $this->baseModification->old_price;
      
      // foreach($this->notBaseModifications->get() as $mod) {
      //   if($mod->price && (!$price || $mod->price < $price)) {
      //     $price = $mod->price;
      //     $old_price = $mod->old_price;
      //   }
      // }
      
      return $price;
    }
    
    public function getOldPriceAttribute()
    {
      $price = $this->baseModification->price;
      $old_price = $this->baseModification->old_price;
      
      // foreach($this->notBaseModifications->get() as $mod) {
      //   if($mod->price && (!$price || $mod->price < $price)) {
      //     $price = $mod->price;
      //     $old_price = $mod->old_price;
      //   }
      // }
      
      return $old_price;
    }
    
    public function getLinkAttribute()
    {
      $category_slug = $this->category->slug;
      
      return url('/catalog/' . $category_slug . '/' . $this->slug);
    }
    
    public function getImagesAttribute()
    {
	    if(!$this->baseModification->count())
	    	return null;
	    	
      return $this->baseModification->images;
    }


	public function getMTitleAttribute(){
		if(!is_array($this->extras))
			return null;
		
		if(isset($this->extras['meta_title']))
			return $this->extras['meta_title'];
	}

	public function getMDescriptionAttribute(){
		if(!is_array($this->extras))
			return null;
		
		if(isset($this->extras['meta_description']))
			return $this->extras['meta_description'];
	}    
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    public function setModAttribute($value)
    {
      $this->modifications_array = $value;
    }
        
    public function setImagesAttribute($value)
    {
      $this->images_array = $value;
    }
}
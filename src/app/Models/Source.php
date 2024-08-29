<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;

// MODEL
use Backpack\Store\app\Models\Supplier;
use Backpack\Store\app\Models\CategorySource;
use Backpack\Store\app\Models\BrandSource;

class Source extends Model
{
    use HasFactory;
    use CrudTrait;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_sources';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = ['name', 'key', 'supplier_id', 'link', 'content', 'is_active', 'type', 'overprice', 'last_loading', 'every_minutes', 'categoriesData', 'brandsData', 'settings', 'rules'];
    // protected $hidden = [];
    protected $dates = ['last_loading'];

    protected $casts = [
      'settings' => 'array',
      'rules' => 'array'
    ];

    protected $fakeColumns = ['settings'];

    private $supplier_class = null;
    private $upload_class = null;
    private $category_class = null;
    private $brand_class = null;
    
    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    
    /**
     * __constract
     *
     * @param  mixed $attributes
     * @return void
     */
    public function __constract(array $attributes = array()) {
      parent::__construct($attributes);
    }
    
    /**
     * boot
     *
     * @return void
     */
    protected static function boot()
    {
      parent::boot();
    }

    
    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */    
    /**
     * supplier
     *
     * @return void
     */
    public function supplier()
    {
      $this->supplier_class = config('backpack.store.supplier.class', 'Backpack\Store\app\Models\Supplier');
      return $this->belongsTo($this->supplier_class);
    }
    
    /**
     * history
     *
     * @return void
     */
    public function history()
    {
      $this->upload_class = config('backpack.store.source.upload_class', 'Backpack\Store\app\Models\UploadHistory');
      return $this->hasMany($this->upload_class);
    }

    /**
     * categories
     *
     * @return void
     */
    public function categories()
    {
      $this->category_class = config('backpack.store.category.class', 'Backpack\Store\app\Models\Category');
      return $this->belongsToMany($this->category_class, 'ak_category_source')->withPivot('name');
    }

    /**
     * brands
     *
     * @return void
     */
    public function brands()
    {
      $this->brand_class = config('backpack.store.brands.class', 'Backpack\Store\app\Models\Brand');
      return $this->belongsToMany($this->brand_class, 'ak_brand_source')->withPivot('name');
    }

    /**
     * CategorySource
     *
     * @return void
     */
    public function cs()
    {
      return $this->hasMany(CategorySource::class);
    }

    /**
     * BrandSource
     *
     * @return void
     */
    public function bs()
    {
      return $this->hasMany(BrandSource::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopeActive($query){
      return $query->where('is_active', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
    
    /**
     * getBrandsDataAttribute
     *
     * @return void
     */
    public function getBrandsDataAttribute() {
      $arr = [];

      foreach($this->bs as $bs) {
        $arr[] = [
          'brand' => $bs->name,
          'brand_id' => $bs->brand_id,
        ];
      }

      return $arr;
    }
    
    /**
     * getCategoriesDataAttribute
     *
     * @return void
     */
    public function getCategoriesDataAttribute() {
      $arr = [];

      foreach($this->cs as $cs) {
        $arr[] = [
          'category' => $cs->name,
          'category_id' => $cs->category_id,
        ];
      }

      return $arr;
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
        
    /**
     * setCategoriesDataAttribute
     *
     * @param  mixed $value
     * @return void
     */
    public function setCategoriesDataAttribute($value) {
      // Dettach all existed 
      $this->cs()->delete();

      $value_arr = json_decode($value, true);

      if(!$value_arr) {
        return;
      }

      foreach($value_arr as $item) {
        CategorySource::create([
          'category_id' => $item['category_id'] ?? null,
          'source_id' => $this->id,
          'name' => $item['category']
        ]);
      } 
    }
    
    /**
     * setBrandsDataAttribute
     *
     * @param  mixed $value
     * @return void
     */
    public function setBrandsDataAttribute($value) {
      // Dettach all existed 
      $this->bs()->delete();

      $value_arr = json_decode($value, true);

      if(!$value_arr) {
        return;
      }

      foreach($value_arr as $item) {
        BrandSource::create([
          'brand_id' => $item['brand_id'] ?? null,
          'source_id' => $this->id,
          'name' => $item['brand']
        ]);
      } 
    }
}

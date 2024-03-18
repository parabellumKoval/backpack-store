<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

// SLUGS
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;

// TRANSLATIONS
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Store\database\factories\CategoryFactory;

class Category extends Model
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

    protected $table = 'ak_product_categories';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];
    // protected $dates = [];
    protected $fakeColumns = ['seo', 'extras', 'images', 'params'];
    protected $casts = [
	    //'seo' => 'array',
	    'params' => 'array',
	    //'extras' => 'array',
      'images' => 'array'
    ];

    protected $translatable = ['name', 'content', 'seo', 'extras'];
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
      return CategoryFactory::new();
    }

    public function toArray(){
      return [
        'id' => $this->id,
        'name' => $this->name,
        'slug' => $this->slug,
        'children' => $this->children
      ];    
    }
    
    public function clearGlobalScopes()
    {
        static::$globalScopes = [];
    }
    
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'slug_or_name',
            ],
        ];
    }
    
    /**
     * getCategoryNodeIdList
     *
     * @param  mixed $slug
     * @param  mixed $id
     * @return void
     */
    public static function getCategoryNodeIdList(string $slug = null, int $id = null) {

      if($slug !== null) {
        $category = Category::where('slug', $slug)->first();
      }elseif($id !== null) {
        $category = Category::find($id);
      }else {
        $category = null;
      }

      $node_ids = $category? $category->nodeIds: null;

      return $node_ids;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function products()
    {
      return $this->belongsToMany('Backpack\Store\app\Models\Product', 'ak_category_product');
    }

    public function parent()
    {
      return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
      return $this->hasMany(self::class, 'parent_id');
    }
    
    public function attributes()
    {
        return $this->belongsToMany('Backpack\Store\app\Models\Attribute', 'ak_attribute_category');
    }
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopeNoEmpty($query){
      return $query->has('products');
    }

    public function scopeActive($query){
      return $query->where('is_active', 1);
    }

    public function scopeRoot($query){
      return $query->where('parent_id', NULL);
    }
    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
    public function getSeoToArrayAttribute() {
      return !empty($this->seo)? json_decode($this->seo): null;
    }

    public function getExtrasToArrayAttribute() {
      return !empty($this->extras)? json_decode($this->extras): null;
    }

    public function getImageAttribute() {
      if(isset($this->images[0]))
        return $this->images[0];
      else 
        return null;
    }

    public function getImageSrcAttribute() {
      if($this->image && isset($this->image['src']))
        return $this->image['src'];
      else
        return null;
    }
    

    public function getSlugOrNameAttribute()
    {
        if ($this->slug != '') {
            return $this->slug;
        }

        return $this->name;
    }

    
    /**
     * getNodeIdsAttribute
     *
     * @param  mixed $category
     * @return void
     */
    public function getNodeIdsAttribute($category){
			$category = $category? $category: $this;
			
			$start_carry = $category === $this? array($category->id): array();
			
			return $category->children->reduce(function ($carry, $item) {
				
				$carry[] = $item->id;
				
				if($item->children)
					$ids = $this->getNodeIdsAttribute($item);
				
			  return array_merge($carry, $ids);
			}, $start_carry);
    }
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}

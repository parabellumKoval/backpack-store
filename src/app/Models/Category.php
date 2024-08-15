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
    protected $fakeColumns = ['seo', 'extras', 'extras_trans', 'images', 'params'];
    protected $casts = [
	    'params' => 'array',
	    'extras' => 'array',
      'images' => 'array'
    ];

    protected $translatable = ['name', 'content', 'seo', 'extras_trans'];

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

    public function getAdminColumnSeo() {
      $html = "";
      // $html .= "T: <span>🔴</span>";
      // $html .= "D: <span>🟢</span>";
      // $html .= "H1: <span>🔴</span>";

      $common_style = "display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; margin-right: 3px; border-radius: 9px; color: #fff; font-size: 12px; font-weight: bold;";

      // $arr = $this->seoToArray;
      // foreach($arr as $k => $v) {
      //   \Log::info($k . ' - ' . $v);
      // }

      if(!$this->seoToArray || !isset($this->seoToArray['meta_title']) || empty($this->seoToArray['meta_title'])) {
        $html .= "<div style='" . $common_style ." background: red;'>T</div>";
      }else {
        $html .= "<div style='" . $common_style ." background: #00a65a;'>T</div>";
      }

      if(!$this->seoToArray || !isset($this->seoToArray['meta_description']) ||empty($this->seoToArray['meta_description'])) {
        $html .= "<div style='" . $common_style ." background: red;'>D</div>";
      }else {
        $html .= "<div style='" . $common_style ." background: #00a65a;'>D</div>";
      }

      if(!$this->seoToArray || !isset($this->seoToArray['h1']) || empty($this->seoToArray['h1'])) {
        $html .= "<div style='" . $common_style ." background: red;'>H1</div>";
      }else {
        $html .= "<div style='" . $common_style ." background: #00a65a;'>H1</div>";
      }

      return $html;
    }

    /**
     * getSeoToArrayAttribute
     *
     * @return void
     */
    public function getSeoToArrayAttribute() {
      return !empty($this->seo)? json_decode($this->seo, true): null;
    }
    
    /**
     * getExtrasToArrayAttribute
     *
     * @return void
     */
    public function getExtrasToArrayAttribute() {
      return !empty($this->extras)? json_decode($this->extras): null;
    }
    
    /**
     * getImageAttribute
     *
     * @return void
     */
    public function getImageAttribute() {
      if(isset($this->images[0]))
        return $this->images[0];
      else 
        return null;
    }
    
    /**
     * getImageSrcAttribute
     *
     * @return void
     */
    public function getImageSrcAttribute() {
      $base_path = config('backpack.store.category.image.base_path', '/');

      if(isset($this->image['src'])) {
        return $base_path . $this->image['src'];
      }else {
        return null;
      }
    }
        
    /**
     * getSlugOrNameAttribute
     *
     * @return void
     */
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

    
    /**
     * getRootCategory
     *
     * @return void
     */
    public function getRootCategoryAttribute() {
      $this_category = $this;
      
      while($this_category->parent) {
        $this_category = $this_category->parent;
      }

      return $this_category;
    }
    
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}

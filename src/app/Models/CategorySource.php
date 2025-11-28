<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class CategorySource extends Model
{
    use CrudTrait;
    use FormatsUniqAttribute;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_category_source';
    // protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];
    // protected $dates = [];

    protected $casts = [];

    protected $fakeColumns = [];
    
    private $source_class = null;
    private $category_class = null;
    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
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
    public function source()
    {
      $this->source_class = \Settings::get('dress.source.model', 'Backpack\Store\app\Models\Source');
      return $this->belongsTo($this->source_class);
    }

    /**
     * categories
     *
     * @return void
     */
    public function category()
    {
      $this->category_class = \Settings::get('dress.category.model', 'Backpack\Store\app\Models\Category');
      return $this->belongsTo($this->category_class);
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
        $category = $this->relationLoaded('category') ? $this->getRelation('category') : null;
        $source = $this->relationLoaded('source') ? $this->getRelation('source') : null;

        return $this->formatUniqString([
            '#'.$this->id,
            $this->name,
            $category?->name ?? sprintf('category #%s', $this->category_id ?? '?'),
            $source?->name ?? sprintf('source #%s', $this->source_id ?? '?'),
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $category = $this->relationLoaded('category') ? $this->getRelation('category') : null;
        $source = $this->relationLoaded('source') ? $this->getRelation('source') : null;
        $headline = $this->formatUniqString([
            '#'.$this->id,
            $this->name,
        ]);

        return $this->formatUniqHtml($headline, [
            $category?->name ?? sprintf('category #%s', $this->category_id ?? '?'),
            $source?->name ?? sprintf('source #%s', $this->source_id ?? '?'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}

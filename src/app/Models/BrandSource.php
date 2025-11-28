<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class BrandSource extends Model
{
    use CrudTrait;
    use FormatsUniqAttribute;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_brand_source';
    // protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];
    // protected $dates = [];

    protected $casts = [];

    protected $fakeColumns = [];
    
    private $source_class = null;
    private $brand_class = null;
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
    public function brand()
    {
      $this->brand_class = \Settings::get('dress.brand.model', 'Backpack\Store\app\Models\Brand');
      return $this->belongsTo($this->brand_class);
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
        $brand = $this->relationLoaded('brand') ? $this->getRelation('brand') : null;
        $source = $this->relationLoaded('source') ? $this->getRelation('source') : null;

        return $this->formatUniqString([
            '#'.$this->id,
            $this->name,
            $brand?->name ?? sprintf('brand #%s', $this->brand_id ?? '?'),
            $source?->name ?? sprintf('source #%s', $this->source_id ?? '?'),
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $brand = $this->relationLoaded('brand') ? $this->getRelation('brand') : null;
        $source = $this->relationLoaded('source') ? $this->getRelation('source') : null;
        $headline = $this->formatUniqString([
            '#'.$this->id,
            $this->name,
        ]);

        return $this->formatUniqHtml($headline, [
            $brand?->name ?? sprintf('brand #%s', $this->brand_id ?? '?'),
            $source?->name ?? sprintf('source #%s', $this->source_id ?? '?'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}

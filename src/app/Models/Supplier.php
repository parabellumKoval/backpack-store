<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Store\database\factories\BrandFactory;

// MODEL
use Backpack\Store\app\Models\Product;

class Supplier extends Model
{
    use HasFactory;
    use CrudTrait;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_suppliers';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];
    // protected $dates = [];

    protected $casts = [
      'extras' => 'array',
      'regions' => 'array',
    ];

    protected $fakeColumns = ['extras'];
    
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
    public function products()
    {
      return $this->belongsToMany(Product::class, 'ak_supplier_product');
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
     * Supported AI description generation modes.
     */
    const DESCRIPTION_MODE_SCRATCH = 'scratch';
    const DESCRIPTION_MODE_REWRITE = 'rewrite';

    /**
     * Whether the AI generator should deep-rewrite this supplier's imported
     * description instead of generating one from scratch.
     *
     * @return bool
     */
    public function isRewriteMode() {
      return ($this->description_mode ?? self::DESCRIPTION_MODE_SCRATCH) === self::DESCRIPTION_MODE_REWRITE;
    }

    /**
     * Human readable label of the current description mode (for admin columns).
     *
     * @return string
     */
    public function getDescriptionModeLabel() {
      return $this->isRewriteMode()
        ? 'Глубокий рерайт описания поставщика'
        : 'С нуля';
    }

    public function getColorAttribute() {
      return $this->extras['color'] ?? '#000000';
    }

    public function getAdminColorAttribute() {
      return "<div style='background: " . $this->color . "; width: 25px; height:25px; border-radius: 100%;'></div>";
    }
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}

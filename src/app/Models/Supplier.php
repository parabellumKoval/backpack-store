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
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class Supplier extends Model
{
    use HasFactory;
    use CrudTrait;
    use FormatsUniqAttribute;

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
    

    public function toArray()
    {
      return [
        'id' => $this->id,
        'name' => $this->name,
        'currency' => $this->currency,
        'countries' => $this->countriesArray,
      ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    // public function products()
    // {
    //   return $this->belongsToMany(Product::class, 'ak_supplier_product');
    // }
    
    // public function countriesServed()
    // {
    //     return $this->belongsToMany(
    //         Country::class,
    //         'ak_supplier_country',
    //         'supplier_id',
    //         'country_code',
    //         'id',
    //         'code'
    //     );
    // }

    public function products()
    {
        return $this->belongsToMany(
            Product::class,
            'ak_supplier_product',
            'supplier_id',
            'product_id'
        )->withPivot(['price', 'old_price', 'in_stock', 'is_active']);
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

    public function getUniqStringAttribute(): string
    {
        $countryList = is_array($this->regions) ? implode(', ', $this->regions) : null;

        return $this->formatUniqString([
            '#'.$this->id,
            $this->name,
            'currency: '.($this->currency_code ?? '-'),
            $countryList ? 'countries: '.$countryList : null,
            sprintf('status: %s', ($this->is_active ?? false) ? 'active' : 'inactive'),
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $countryList = is_array($this->regions) ? implode(', ', $this->regions) : null;
        $headline = $this->formatUniqString([
            '#'.$this->id,
            $this->name,
        ]);

        return $this->formatUniqHtml($headline, [
            'currency: '.($this->currency_code ?? '-'),
            $countryList ? 'countries: '.$countryList : null,
            sprintf('status: %s', ($this->is_active ?? false) ? 'active' : 'inactive'),
        ]);
    }

    public function getColorAttribute() {
      return $this->extras['color'] ?? '#000000';
    }

    public function getAdminColorAttribute() {
      return "<div style='background: " . $this->color . "; width: 25px; height:25px; border-radius: 100%;'></div>";
    }


    public function getCountriesAttribute()
    {
        
        return \DB::table('ak_supplier_country')
            ->where('supplier_id', $this->id)
            ->pluck('country_code')
            ->toArray();
    }

    // public function getCountriesCollectioiAttribute()
    // {
        
    //     return \DB::table('ak_supplier_country')
    //         ->where('supplier_id', $this->id)
    //         ->pluck('country_code');
    // }

    public function getCountriesArrayAttribute()
    {
        $countries = \Store::countries();

        return array_map(function($code) use($countries) {
          $cntr = [
            'code' => $code,
            'name' => null
          ];

          if(isset($countries[$code])) {
            $cntr['name'] = $countries[$code]['country'] ?? null;
          }
          
          return $cntr;
        }, $this->countries);
    }

    public function getCurrencyAttribute()
    {
        return $this->currency_code;
    }
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}

<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
    
// TRANSLATIONS
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Store\database\factories\AttributeValueFactory;

use Backpack\Store\app\Models\Attribute;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class AttributeValue extends Model
{
    use HasFactory;
    use CrudTrait;
    use HasTranslations;
    use FormatsUniqAttribute;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_attribute_values';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    // protected $guarded = ['id'];
    protected $fillable = ['value', 'slug', 'attribute_id', 'transform', 'extras'];
    // protected $hidden = [];
    // protected $dates = [];
    protected $casts = [
      'extras' => 'array'
    ];

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

    protected static function booted()
    {
      static::saving(function (self $attributeValue) {
        $attributeValue->syncSlug($attributeValue->slug);
      });
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
        'value' => $this->value,
        'slug' => $this->slug,
      ];
    }

    public function resolveSlugSource(): ?string
    {
      $translations = method_exists($this, 'getTranslations')
        ? (array) $this->getTranslations('value')
        : [];

      $candidates = [];

      if (!empty($translations['en']) && is_string($translations['en'])) {
        $candidates[] = $translations['en'];
      }

      foreach ($translations as $translation) {
        if (is_string($translation)) {
          $candidates[] = $translation;
        }
      }

      if (is_string($this->value)) {
        $candidates[] = $this->value;
      }

      foreach ($candidates as $candidate) {
        $normalized = trim((string) $candidate);

        if ($normalized !== '') {
          return $normalized;
        }
      }

      return null;
    }

    public function syncSlug(?string $preferredSlug = null): self
    {
      $manualSlug = trim((string) ($preferredSlug ?? ''));
      $baseSlug = $manualSlug !== ''
        ? (Str::slug($manualSlug) ?: $manualSlug)
        : $this->buildSlugCandidate($this->resolveSlugSource());

      $this->slug = $this->makeUniqueSlug($baseSlug);

      return $this;
    }

    protected function buildSlugCandidate(?string $source): string
    {
      $slug = Str::slug((string) ($source ?? ''));

      return $slug !== '' ? $slug : 'value';
    }

    protected function makeUniqueSlug(string $baseSlug): string
    {
      $slug = $baseSlug;
      $suffix = 2;

      while ($this->slugExists($slug)) {
        $slug = sprintf('%s-%d', $baseSlug, $suffix);
        $suffix++;
      }

      return $slug;
    }

    protected function slugExists(string $slug): bool
    {
      if (!$this->attribute_id) {
        return false;
      }

      return static::query()
        ->where('attribute_id', $this->attribute_id)
        ->where('slug', $slug)
        ->when($this->exists, function ($query) {
          $query->where($this->getKeyName(), '!=', $this->getKey());
        })
        ->exists();
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

    public function getUniqStringAttribute(): string
    {
        $attribute = $this->relationLoaded('attribute') ? $this->getRelation('attribute') : null;

        return $this->formatUniqString([
            '#'.$this->id,
            $this->value,
            $attribute?->name ?? sprintf('attribute #%s', $this->attribute_id ?? '?'),
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $attribute = $this->relationLoaded('attribute') ? $this->getRelation('attribute') : null;
        $headline = $this->formatUniqString([
            '#'.$this->id,
            $this->value,
        ]);

        return $this->formatUniqHtml($headline, [
            $attribute?->name ?? sprintf('attribute #%s', $this->attribute_id ?? '?'),
            $this->transform_value_string ? 'transform: '.$this->transform_value_string : null,
        ]);
    }
    
    /**
     * getTransformValueAttribute
     *
     * @return void
     */
    public function getTransformValueAttribute(){
      if(!isset($this->extras['transform_value']) || empty($this->extras['transform_value'])) {
        return null;
      }

      return $this->extras['transform_value'];
    }
    
    /**
     * getTransformValueStringAttribute
     *
     * @return void
     */
    public function getTransformValueStringAttribute(){
      if(!isset($this->extras['transform_value']) || empty($this->extras['transform_value'])) {
        return '';
      }

      if(!is_array($this->extras['transform_value'])) {
        return $this->extras['transform_value'];
      }
      
      return implode('|', $this->extras['transform_value']);
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    
}

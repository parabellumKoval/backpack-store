<?php

namespace Backpack\Store\app\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Campaign extends Model
{
    use CrudTrait;
    use Sluggable;
    use SluggableScopeHelpers;
    use HasTranslations;

    protected $table = 'ak_campaigns';

    protected $fillable = [
        'is_active',
        'name',
        'slug',
        'short_description',
        'conditions_html',
        'discount_percent',
        'priority',
        'is_timed',
        'starts_at',
        'ends_at',
        'show_timer_card',
        'show_timer_product',
        'horizontal_banner',
        'vertical_banner',
        'add_to_main_banner',
        'add_banner_to_catalog',
        'catalog_banner_frequency',
        'catalog_banner_position',
        'countries',
        'product_source',
        'product_filters',
        'manual_products',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'discount_percent' => 'float',
        'priority' => 'integer',
        'is_timed' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'show_timer_card' => 'boolean',
        'show_timer_product' => 'boolean',
        'add_to_main_banner' => 'boolean',
        'add_banner_to_catalog' => 'boolean',
        'catalog_banner_frequency' => 'integer',
        'catalog_banner_position' => 'integer',
        'countries' => 'array',
        'product_filters' => 'array',
        'manual_products' => 'array',
    ];

    protected $translatable = [
        'name',
        'short_description',
        'conditions_html',
        'horizontal_banner',
        'vertical_banner',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $campaign) {
            app(\Backpack\Store\app\Services\Campaign\CampaignProductSyncService::class)->syncCampaign($campaign);
        });

        static::deleted(function (self $campaign) {
            app(\Backpack\Store\app\Services\Campaign\CampaignProductSyncService::class)->detachCampaign($campaign);
        });
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    public function scopeActiveAt(Builder $query, ?Carbon $moment = null): Builder
    {
        $moment = $moment ?: now();

        return $query
            ->where('is_active', true)
            ->where('discount_percent', '>', 0)
            ->where(function (Builder $q) use ($moment) {
                $q->where('is_timed', false)
                    ->orWhere(function (Builder $range) use ($moment) {
                        $range->where('is_timed', true)
                            ->where(function (Builder $from) use ($moment) {
                                $from->whereNull('starts_at')
                                    ->orWhere('starts_at', '<=', $moment);
                            })
                            ->where(function (Builder $to) use ($moment) {
                                $to->whereNull('ends_at')
                                    ->orWhere('ends_at', '>=', $moment);
                            });
                    });
            });
    }

    public function scopeActiveForCountry(Builder $query, ?string $country): Builder
    {
        $country = strtolower(trim((string) $country));

        return $query->where(function (Builder $q) use ($country) {
            $q->whereNull('countries');

            if ($country !== '') {
                $q->orWhereJsonContains('countries', $country);
            }
        });
    }

    public function getIsActiveNowAttribute(): bool
    {
        if (!$this->is_active || (float) $this->discount_percent <= 0) {
            return false;
        }

        if (!$this->is_timed) {
            return true;
        }

        $now = now();
        $startsOk = $this->starts_at === null || $this->starts_at->lessThanOrEqualTo($now);
        $endsOk = $this->ends_at === null || $this->ends_at->greaterThanOrEqualTo($now);

        return $startsOk && $endsOk;
    }

    public function getManualProductIdsAttribute(): array
    {
        $raw = $this->manual_products ?? [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        $rows = is_array($raw) ? $raw : [];
        $ids = [];

        foreach ($rows as $row) {
            $id = is_array($row) ? ($row['product_id'] ?? null) : $row;
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique(array_filter($ids, fn($id) => $id > 0)));
    }

    public function getProductFilterRulesAttribute(): array
    {
        $raw = $this->product_filters ?? [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }

    public function setCountriesAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['countries'] = null;
            return;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : [$value];
        }

        if (!is_array($value)) {
            $this->attributes['countries'] = null;
            return;
        }

        $normalized = [];
        foreach ($value as $code) {
            if (!is_scalar($code)) {
                continue;
            }

            $country = strtolower(trim((string) $code));
            if ($country === '') {
                continue;
            }

            $normalized[] = $country;
        }

        $normalized = array_values(array_unique($normalized));
        $this->attributes['countries'] = empty($normalized)
            ? null
            : json_encode($normalized, JSON_UNESCAPED_UNICODE);
    }

    public function getPeriodLabelAttribute(): string
    {
        if (!$this->is_timed) {
            return 'Бессрочная';
        }

        $from = $this->starts_at?->format('d.m.Y H:i') ?? '...';
        $to = $this->ends_at?->format('d.m.Y H:i') ?? '...';

        return $from . ' - ' . $to;
    }
}

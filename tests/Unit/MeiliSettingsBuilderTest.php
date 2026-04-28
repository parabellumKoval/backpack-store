<?php

namespace Backpack\Store\Tests\Unit;

require_once dirname(__DIR__).'/TestCase.php';

use Backpack\Store\app\Job\ApplyMeiliSettingsJob;
use Backpack\Store\app\Models\Catalog;
use Backpack\Store\app\Services\Search\MeiliSettingsBuilder;
use Backpack\Store\app\Services\Store;
use Backpack\Store\Tests\TestCase;

class MeiliSettingsBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('backpack.crud.locales', [
            'uk' => 'Ukrainian',
            'cs' => 'Czech',
        ]);

        config()->set('dress.search.models.products', Catalog::class);
        config()->set('dress.search.meilisearch.settings', [
            'searchableAttributes' => ['id', 'name', 'categories', 'storefront_code'],
            'filterableAttributes' => ['brandName'],
        ]);

        config()->set('dress.multistore.countries', [
            'cz' => [
                'code' => 'cz',
                'country' => 'Czech Republic',
                'locale' => 'cs',
            ],
            'ua' => [
                'code' => 'ua',
                'country' => 'Ukraine',
                'locale' => 'uk',
            ],
        ]);
        config()->set('dress.multistore.support_global', false);

        config()->set('dress.storefront.enabled', true);
        config()->set('dress.storefront.default', 'main');
        config()->set('dress.storefront.values', [
            'main' => 'Main storefront',
            'telegram' => 'Telegram storefront',
        ]);

        $ref = new \ReflectionClass(Store::class);
        $cache = $ref->getProperty('normalizedStorefrontsCache');
        $cache->setAccessible(true);
        $cache->setValue(null, null);
    }

    public function test_build_merges_required_and_model_filterable_attributes(): void
    {
        $settings = MeiliSettingsBuilder::build();

        $this->assertEqualsCanonicalizing(
            ['brandName', 'in_stock', 'country_code', 'storefront_code', 'category_ids', 'brand_id'],
            $settings['filterableAttributes']
        );
    }

    public function test_build_expands_translatable_searchable_attributes_per_locale(): void
    {
        $settings = MeiliSettingsBuilder::build();

        $this->assertEqualsCanonicalizing(
            ['id', 'name_uk', 'name_cs', 'categories_uk', 'categories_cs', 'storefront_code'],
            $settings['searchableAttributes']
        );
    }

    public function test_apply_meili_settings_job_resolves_country_and_storefront_indexes(): void
    {
        $method = new \ReflectionMethod(ApplyMeiliSettingsJob::class, 'resolveIndexUids');
        $method->setAccessible(true);

        $indexes = $method->invoke(null);

        $this->assertEqualsCanonicalizing([
            'products',
            'products_cz',
            'products_cz_main',
            'products_cz_telegram',
            'products_ua',
            'products_ua_main',
            'products_ua_telegram',
        ], $indexes);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AddSlugToAkAttributeValuesTable extends Migration
{
    public function up()
    {
        Schema::table('ak_attribute_values', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('value');
        });

        $usedSlugs = [];

        DB::table('ak_attribute_values')
            ->select(['id', 'attribute_id', 'value'])
            ->orderBy('attribute_id')
            ->orderBy('id')
            ->each(function ($row) use (&$usedSlugs) {
                $attributeId = (int) $row->attribute_id;
                $baseSlug = $this->makeBaseSlug($row->value);

                if (!isset($usedSlugs[$attributeId])) {
                    $usedSlugs[$attributeId] = [];
                }

                $slug = $baseSlug;
                $suffix = 2;

                while (in_array($slug, $usedSlugs[$attributeId], true)) {
                    $slug = sprintf('%s-%d', $baseSlug, $suffix);
                    $suffix++;
                }

                $usedSlugs[$attributeId][] = $slug;

                DB::table('ak_attribute_values')
                    ->where('id', $row->id)
                    ->update(['slug' => $slug]);
            });

        Schema::table('ak_attribute_values', function (Blueprint $table) {
            $table->unique(['attribute_id', 'slug'], 'ak_attribute_values_attribute_slug_unique');
        });
    }

    public function down()
    {
        Schema::table('ak_attribute_values', function (Blueprint $table) {
            $table->dropUnique('ak_attribute_values_attribute_slug_unique');
            $table->dropColumn('slug');
        });
    }

    private function makeBaseSlug($rawValue): string
    {
        $text = null;
        $decoded = is_string($rawValue) ? json_decode($rawValue, true) : null;

        if (is_array($decoded)) {
            if (!empty($decoded['en']) && is_string($decoded['en'])) {
                $text = $decoded['en'];
            } else {
                foreach ($decoded as $translation) {
                    if (is_string($translation) && trim($translation) !== '') {
                        $text = $translation;
                        break;
                    }
                }
            }
        } elseif (is_string($rawValue)) {
            $text = $rawValue;
        }

        $slug = Str::slug((string) ($text ?? ''));

        return $slug !== '' ? $slug : 'value';
    }
}

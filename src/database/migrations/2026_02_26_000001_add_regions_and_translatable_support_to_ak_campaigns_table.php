<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected array $translatableColumns = [
        'name',
        'short_description',
        'conditions_html',
        'horizontal_banner',
        'vertical_banner',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('ak_campaigns')) {
            return;
        }

        Schema::table('ak_campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('ak_campaigns', 'countries')) {
                $table->json('countries')->nullable();
            }

            $table->text('name')->change();
            $table->text('horizontal_banner')->nullable()->change();
            $table->text('vertical_banner')->nullable()->change();
        });

        $defaultLocale = (string) config('app.locale', 'en');

        DB::table('ak_campaigns')
            ->select(array_merge(['id'], $this->translatableColumns))
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($defaultLocale) {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($this->translatableColumns as $column) {
                        $normalized = $this->normalizeTranslatableValue($row->{$column} ?? null, $defaultLocale);
                        if ($normalized !== null && $normalized !== $row->{$column}) {
                            $updates[$column] = $normalized;
                        }
                    }

                    if (!empty($updates)) {
                        DB::table('ak_campaigns')
                            ->where('id', $row->id)
                            ->update($updates);
                    }
                }
            }, 'id');
    }

    public function down(): void
    {
        if (!Schema::hasTable('ak_campaigns')) {
            return;
        }

        Schema::table('ak_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('ak_campaigns', 'countries')) {
                $table->dropColumn('countries');
            }
        });
    }

    protected function normalizeTranslatableValue(mixed $value, string $locale): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_array($decoded) && $this->isAssociative($decoded)) {
                return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if (is_string($decoded) && trim($decoded) !== '') {
                $raw = trim($decoded);
            }
        }

        return json_encode([$locale => $raw], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function isAssociative(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
};

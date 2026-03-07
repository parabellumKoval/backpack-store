<?php

namespace Backpack\Store\app\Models\Traits;

trait HasFaqData
{
    public function getFaqItems(?string $locale = null): array
    {
        $extras = $this->getLocalizedExtrasTransPayload($locale);
        $rows = $this->normalizeArrayPayload($extras['faq_items'] ?? []);

        $items = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $groupTitle = trim((string) ($row['group_title'] ?? ''));
            $question = trim((string) ($row['question'] ?? ($row['q'] ?? '')));
            $answer = (string) ($row['answer'] ?? ($row['a'] ?? ''));

            if ($question === '' || trim(strip_tags($answer)) === '') {
                continue;
            }

            $items[] = [
                'group_title' => $groupTitle,
                'question' => $question,
                'answer' => $answer,
            ];
        }

        return $items;
    }

    public function getFaqTemplateIds(): array
    {
        $extras = $this->normalizeArrayPayload($this->extras ?? []);
        $rows = $this->normalizeArrayPayload($extras['faq_template_links'] ?? []);

        $ids = [];

        foreach ($rows as $row) {
            $candidate = is_array($row) ? ($row['template_id'] ?? null) : $row;

            if (is_numeric($candidate)) {
                $id = (int) $candidate;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public function getExtrasTransValue(string $key, ?string $locale = null)
    {
        $extras = $this->getLocalizedExtrasTransPayload($locale);

        return $extras[$key] ?? null;
    }

    protected function getLocalizedExtrasTransPayload(?string $locale = null): array
    {
        $target = $this->resolveFaqLocale($locale);
        $fallback = (string) config('app.fallback_locale', 'en');

        $translations = method_exists($this, 'getTranslations')
            ? ($this->getTranslations('extras_trans') ?? [])
            : [];

        if (!is_array($translations)) {
            $translations = [];
        }

        $candidates = array_values(array_unique(array_filter([
            $target,
            $fallback,
            (string) app()->getLocale(),
        ])));

        foreach ($candidates as $lang) {
            if (!array_key_exists($lang, $translations)) {
                continue;
            }

            $payload = $this->normalizeArrayPayload($translations[$lang]);
            if (!empty($payload)) {
                return $payload;
            }
        }

        foreach ($translations as $value) {
            $payload = $this->normalizeArrayPayload($value);
            if (!empty($payload)) {
                return $payload;
            }
        }

        return $this->normalizeArrayPayload($this->extras_trans ?? []);
    }

    protected function resolveFaqLocale(?string $locale = null): string
    {
        if (is_string($locale) && $locale !== '') {
            return $this->normalizeLocaleKey($locale);
        }

        $fromBackpack = function_exists('backpack_translatable_request_locale')
            ? backpack_translatable_request_locale(null)
            : null;

        if (is_string($fromBackpack) && $fromBackpack !== '') {
            return $this->normalizeLocaleKey($fromBackpack);
        }

        $fromRequest = request()->header('Accept-Language');
        if (is_string($fromRequest) && $fromRequest !== '') {
            $first = explode(',', $fromRequest)[0] ?? '';
            $normalized = trim((string) $first);
            if ($normalized !== '') {
                return $this->normalizeLocaleKey($normalized);
            }
        }

        return $this->normalizeLocaleKey((string) app()->getLocale());
    }

    protected function normalizeLocaleKey(string $locale): string
    {
        $normalized = trim(mb_strtolower($locale));
        if ($normalized === '') {
            return (string) config('app.fallback_locale', 'en');
        }

        if (str_contains($normalized, '-')) {
            $normalized = explode('-', $normalized)[0] ?? $normalized;
        }

        if (str_contains($normalized, '_')) {
            $normalized = explode('_', $normalized)[0] ?? $normalized;
        }

        return $normalized;
    }

    protected function normalizeArrayPayload($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded)
            ? $decoded
            : [];
    }
}

@php
    $effective = method_exists($entry, 'effective') ? $entry->effective() : $entry;
    $images = $effective->images ?? [];
    $imageUrl = null;

    if (is_array($images) && !empty($images)) {
        $firstImage = $images[0];
        if (is_array($firstImage) && isset($firstImage['src'])) {
            $imageUrl = $firstImage['src'];
        } elseif (is_string($firstImage)) {
            $imageUrl = $firstImage;
        }
    }

    $title = $effective->name ?? $entry->name ?? null;
    if (is_array($title)) {
        $title = collect($title)->filter()->first();
    }

    $shortName = $effective->short_name ?? $entry->short_name ?? null;
    if (is_array($shortName)) {
        $shortName = collect($shortName)->filter()->first();
    }

    $code = $effective->code ?? $entry->code ?? null;
    $price = $effective->price ?? $entry->price ?? null;
    $currency = $effective->currency ?? $entry->currency ?? null;

    $parentId = $entry->parent_id ?? null;
    $modifications = collect();

    if (method_exists($effective, 'modifications')) {
        $mods = $effective->modifications;
        if ($mods instanceof \Illuminate\Support\Collection) {
            $modifications = $mods;
        } elseif ($mods !== null) {
            $modifications = collect($mods);
        }
    }

    if ($modifications->isEmpty() && $parentId === null && method_exists($entry, 'children')) {
        $mods = $entry->relationLoaded('children') ? $entry->children : $entry->children()->get();
        $modifications = collect($mods);
    }

    $showAggregated = $parentId === null && $modifications->isNotEmpty();

    $codeDisplay = $code ?: '—';
    $priceDisplay = $price !== null
        ? trim(number_format((float) $price, 2, '.', ' ') . ($currency ? ' ' . $currency : ''))
        : '—';

    if ($showAggregated) {
        $codeOptions = collect([$code])->filter();
        $codeOptions = $codeOptions->concat($modifications->pluck('code')->filter());
        $codeOptions = $codeOptions->unique()->values();
        $codeDisplay = $codeOptions->isNotEmpty() ? $codeOptions->implode(', ') : '—';

        $priceValues = $modifications->pluck('price')
            ->filter(fn ($value) => $value !== null && $value !== '' && is_numeric($value))
            ->map(fn ($value) => (float) $value)
            ->filter(fn ($value) => is_finite($value))
            ->sort()->values();

        $modCurrency = $modifications->pluck('currency')->filter()->first();
        $currency = $modCurrency ?: $currency;

        if ($priceValues->isNotEmpty()) {
            $minPrice = $priceValues->first();
            $maxPrice = $priceValues->last();

            $minLabel = number_format($minPrice, 2, '.', ' ');
            $maxLabel = number_format($maxPrice, 2, '.', ' ');

            if (abs($minPrice - $maxPrice) < 0.01) {
                $priceDisplay = $minLabel . ($currency ? ' ' . $currency : '');
            } else {
                $priceDisplay = __('от :min до :max', ['min' => $minLabel, 'max' => $maxLabel]);

                if ($currency) {
                    $priceDisplay .= ' ' . $currency;
                }
            }
        } else {
            $priceDisplay = '—';
        }
    }
@endphp

<div class="service-card service-card--product d-flex align-items-center border rounded p-3 h-100">
    <div class="service-card__media mr-3">
        @if ($imageUrl)
            <img src="{{ url($imageUrl) }}" alt="{{ $title }}" class="rounded" style="width:96px;height:96px;object-fit:cover;">
        @else
            <div class="bg-light text-muted rounded d-flex align-items-center justify-content-center" style="width:96px;height:96px;">
                <span class="la la-image" aria-hidden="true"></span>
            </div>
        @endif
    </div>
    <div class="flex-grow-1">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="font-weight-bold">{{ $title ?: __('Без названия') }}</div>
                @if ($shortName)
                    <div class="text-muted small">{{ $shortName }}</div>
                @endif
            </div>
            <span class="badge badge-light">#{{ $entry->getKey() }}</span>
        </div>
        <dl class="row small mt-3 mb-0">
            <dt class="col-5 text-muted">{{ __('Код') }}</dt>
            <dd class="col-7">{{ $codeDisplay }}</dd>
            <dt class="col-5 text-muted">{{ __('Цена') }}</dt>
            <dd class="col-7 font-weight-bold">{{ $priceDisplay }}</dd>
        </dl>
    </div>
</div>
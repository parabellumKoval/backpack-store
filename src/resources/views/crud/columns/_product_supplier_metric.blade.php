@php
    $matrix = $entry->adminSupplierMatrix();
    $suppliers = $matrix['suppliers'] ?? [];
    $isComposite = (bool) ($matrix['is_composite'] ?? false);
    $metricType = $metric ?? 'price';

    $formatNumber = function ($value, int $decimals = 0) {
        if ($value === null) {
            return null;
        }

        $formatted = number_format((float) $value, $decimals, '.', ' ');

        if ($decimals > 0) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted;
    };

    $renderNumericMetric = function ($data, int $decimals = 0, ?string $suffix = null) use ($isComposite, $formatNumber) {
        if (!($data['has_data'] ?? false)) {
            return '-';
        }

        $min = $data['min'];
        $max = $data['max'];

        $makeValue = function ($number) use ($formatNumber, $decimals, $suffix) {
            if ($number === null) {
                return null;
            }

            $base = $formatNumber($number, $decimals);

            if ($base === null) {
                return null;
            }

            $trimSuffix = $suffix ? trim($suffix) : '';

            return $trimSuffix === '' ? $base : trim($base . ' ' . $trimSuffix);
        };

        if ($isComposite) {
            if ((float) $min === 0.0 && (float) $max === 0.0) {
                return '0';
            }

            if ($min !== null && $max !== null && (float) $min === (float) $max) {
                return $makeValue($min) ?? '0';
            }

            $start = $makeValue($min) ?? '-';
            $end = $makeValue($max) ?? '-';

            return "{$start} - {$end}";
        }

        return $makeValue($min ?? $max) ?? '-';
    };
@endphp

@if(empty($suppliers))
    <span class="text-muted small">-</span>
@else
    <div class="supplier-metric supplier-metric--{{ $metricType }}" style="max-width: 300px;">
        @foreach($suppliers as $supplierData)
            @php
                $supplierModel = $supplierData['supplier'] ?? null;
                $supplierName = $supplierModel->name ?? 'Supplier';
                $supplierColor = $supplierModel->color ?? '#6c757d';
                $currency = $supplierModel->currency ?? null;

                if ($metricType === 'price') {
                    $content = $renderNumericMetric($supplierData['price'] ?? [], 2, $currency);
                } elseif ($metricType === 'stock') {
                    $content = $renderNumericMetric($supplierData['stock'] ?? [], 0);
                } else {
                    $codes = $supplierData['codes'] ?? [];
                    $content = empty($codes) ? '-' : implode(', ', $codes);
                }

                $isOutOfStock = false;

                if ($metricType === 'stock' && trim((string) $content) === '0') {
                    $content = 'нет в наличии';
                    $isOutOfStock = true;
                }
            @endphp

            <div class="supplier-metric__item  rounded px-2 py-2 mb-1 bg-light">
                <div class="d-flex align-items-start">
                    
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap flex-column align-items-baseline">
                            <span class="small mr-1 text-truncate " title="{{ $supplierName }}">
                                <!-- <span class="supplier-metric__dot mr-2" style="display:inline-block;width:10px;height:10px;border-radius:999px;background-color: {{ $supplierColor }};"></span> -->
                                {{ $supplierName }}:
                            </span>
                            <span class="supplier-metric__value text-monospace text-wrap small {{ $isOutOfStock ? 'text-danger font-weight-bold' : '' }}">
                                {{ $content }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@php
    $modifications = $modifications instanceof \Illuminate\Support\Collection
        ? $modifications
        : collect($modifications ?? []);

    $hasRealModifications = $modifications->count() > 1;

    $formatPrice = function ($value, $displayCurrency = null) use ($currency) {
        if ($value === null || $value === '') {
            return '—';
        }

        $numeric = (float) $value;
        $formatted = number_format($numeric, 2, '.', ' ');
        $symbolSource = $displayCurrency !== null && $displayCurrency !== '' ? $displayCurrency : $currency;
        $symbol = trim((string) $symbolSource);

        return $symbol === '' ? $formatted : trim($formatted . ' ' . $symbol);
    };
@endphp

@once
    <style>
        .product-details-mods {
            font-size: 14px;
            line-height: 1.35;
            color: #1f2937;
            padding: 30px;
        }

        .product-details-mods__list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .product-details-mod-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.25rem;
            background: #fff;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.4);
        }

        .product-details-mod-card.is-current {
            border-color: #2563eb;
            box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.2);
        }

        .product-details-mod-card__heading {
            display: flex;
            gap: 1rem;
            align-items: stretch;
            flex-wrap: wrap;
        }

        .product-details-mod-card__image {
            width: 120px;
            min-height: 120px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9fafb;
        }

        .product-details-mod-card__image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-details-mod-card__image-placeholder {
            color: #9ca3af;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .product-details-mod-card__overview {
            flex: 1;
            min-width: 250px;
        }

        .product-details-mod-card__title {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: baseline;
        }

        .product-details-mod-card__name {
            font-size: 1.05rem;
            font-weight: 600;
            color: #111827;
        }

        .product-details-mod-card__short {
            color: #6b7280;
            font-size: 0.95rem;
        }

        .product-details-mod-card__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 0.5rem;
            color: #4b5563;
        }

        .product-details-mod-card__meta-item {
            font-size: 0.9rem;
        }

        .product-details-mod-card__prices {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-top: 0.75rem;
            color: #111827;
        }

        .product-details-mod-card__prices .label {
            color: #6b7280;
            font-size: 0.85rem;
            margin-right: 0.25rem;
        }

        .product-details-mod-card__badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            margin-top: 0.75rem;
            padding: 0.2rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            background: #eef2ff;
            color: #4338ca;
        }

        .product-details-mod-card__badge.is-current-badge {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .product-details-mod-card__suppliers {
            margin-top: 1.25rem;
        }

        .product-details-mod-card__suppliers h5 {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 0.4rem;
            color: #111827;
        }

        .product-details-mod-card__table-wrapper {
            overflow: auto;
        }

        .product-details-mod-card__table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .product-details-mod-card__table thead {
            background: #f3f4f6;
        }

        .product-details-mod-card__table th,
        .product-details-mod-card__table td {
            padding: 0.55rem 0.6rem;
            border: 1px solid #e5e7eb;
            text-align: left;
        }

        .product-details-mod-card__status {
            font-weight: 600;
        }

        .product-details-mod-card__status.is-active {
            color: #047857;
        }

        .product-details-mod-card__status.is-inactive {
            color: #b91c1c;
        }

        .product-details-mod-card__empty {
            padding: 0.75rem 1rem;
            border: 1px dashed #d1d5db;
            border-radius: 8px;
            color: #6b7280;
            font-size: 0.9rem;
        }

        .supplier-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 500;
        }

        .supplier-chip .supplier-color {
            width: 12px;
            height: 12px;
            border-radius: 9999px;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .product-details-mod-card__heading {
                flex-direction: column;
            }

            .product-details-mod-card__image {
                width: 100%;
            }
        }
    </style>
@endonce

<div class="product-details-mods">
    @if($modifications->isEmpty())
        <div class="product-details-mod-card__empty">Нет данных по модификациям.</div>
    @else
        <div class="product-details-mods__list">
            @foreach($modifications as $modification)
                @php
                    $isBase = $modification->id === $baseProductId;
                    $isCurrent = $modification->id === $currentProductId;
                    $imagePath = collect($modification->getImageCollectionPaths('images', 1))->first();
                    $imageUrl = $imagePath ? $modification->formatImageUrlForAttribute('images', $imagePath) : null;
                    $suppliers = $modification->suppliers instanceof \Illuminate\Support\Collection
                        ? $modification->suppliers
                        : collect($modification->suppliers ?? []);
                @endphp

                <div class="product-details-mod-card {{ $isCurrent ? 'is-current' : '' }}">
                    <div class="product-details-mod-card__heading">
                        <div class="product-details-mod-card__image">
                            @if($imageUrl)
                                <img src="{{ $imageUrl }}" alt="{{ $modification->name }}" loading="lazy">
                            @else
                                <div class="product-details-mod-card__image-placeholder">Нет фото</div>
                            @endif
                        </div>
                        <div class="product-details-mod-card__overview">
                            <div class="product-details-mod-card__title">
                                <span class="product-details-mod-card__name">{{ $modification->name ?? '—' }}</span>
                                <span class="product-details-mod-card__short">{{ $modification->short_name ?? '—' }}</span>
                            </div>
                            <div class="product-details-mod-card__meta">
                                <span class="product-details-mod-card__meta-item">ID: {{ $modification->id }}</span>
                                <span class="product-details-mod-card__meta-item">
                                    Код: {{ $modification->code ?? '—' }}
                                </span>
                                <span class="product-details-mod-card__meta-item">
                                    Статус:
                                    <span class="product-details-mod-card__status {{ $modification->is_active ? 'is-active' : 'is-inactive' }}">
                                        {{ $modification->is_active ? 'Активен' : 'Неактивен' }}
                                    </span>
                                </span>
                            </div>
                            <div class="product-details-mod-card__prices">
                                <div>
                                    <span class="label">Цена:</span>
                                    <strong>{{ $formatPrice($modification->price) }}</strong>
                                </div>
                                <div>
                                    <span class="label">Старая цена:</span>
                                    <strong>{{ $formatPrice($modification->old_price) }}</strong>
                                </div>
                                <div>
                                    <span class="label">Наличие:</span>
                                    <strong>{{ $modification->in_stock ?? '—' }}</strong>
                                </div>
                            </div>

                            <div class="product-details-mod-card__meta" style="margin-top:0.5rem;">
                                <span class="product-details-mod-card__meta-item">Краткое название: {{ $modification->short_name ?? '—' }}</span>
                            </div>

                            @if($isBase)
                                <span class="product-details-mod-card__badge">Базовый товар</span>
                            @endif

                            @if($isCurrent && !$isBase)
                                <span class="product-details-mod-card__badge is-current-badge">Текущая строка</span>
                            @endif
                        </div>
                    </div>

                    @php
                        $displaySuppliers = !$isBase || !$hasRealModifications;
                    @endphp

                    @if($displaySuppliers)
                        <div class="product-details-mod-card__suppliers">
                            <h5>Склады</h5>
                            @if($suppliers->isNotEmpty())
                                <div class="product-details-mod-card__table-wrapper">
                                    <table class="product-details-mod-card__table">
                                        <thead>
                                            <tr>
                                                <th>Название</th>
                                                <th>Код</th>
                                                <th>ШК</th>
                                                <th>Наличие</th>
                                                <th>Цена</th>
                                                <th>Старая цена</th>
                                                <th>Статус</th>
                                                <th>Обновлено</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($suppliers as $supplier)
                                                @php
                                                    $pivot = $supplier->pivot;
                                                    $code = $pivot->code ?? null;
                                                    $barcode = $pivot->barcode ?? null;
                                                    $stock = $pivot->in_stock ?? null;
                                                    $pivotPrice = $pivot->price ?? null;
                                                    $pivotOldPrice = $pivot->old_price ?? null;
                                                    $pivotActive = (bool) ($pivot->is_active ?? false);
                                                    $supplierCurrency = (string) ($supplier->currency ?? $supplier->currency_code ?? '');
                                                    $updatedAt = '—';

                                                    if ($pivot && $pivot->updated_at) {
                                                        $updatedAtValue = $pivot->updated_at;

                                                        if ($updatedAtValue instanceof \Carbon\CarbonInterface) {
                                                            $updatedAt = $updatedAtValue->format('d.m.Y H:i');
                                                        } else {
                                                            $updatedAt = (string) $updatedAtValue;
                                                        }
                                                    }
                                                @endphp
                                                <tr>
                                                    <td>
                                                        <div class="supplier-chip">
                                                            <span class="supplier-color" style="background-color: {{ $supplier->color ?? '#d1d5db' }}"></span>
                                                            <span>{{ $supplier->name ?? '—' }}</span>
                                                        </div>
                                                    </td>
                                                    <td>{{ $code ?? '—' }}</td>
                                                    <td>{{ $barcode ?? '—' }}</td>
                                                    <td>{{ $stock ?? '—' }}</td>
                                                    <td>{{ $formatPrice($pivotPrice, $supplierCurrency) }}</td>
                                                    <td>{{ $formatPrice($pivotOldPrice, $supplierCurrency) }}</td>
                                                    <td>
                                                        <span class="product-details-mod-card__status {{ $pivotActive ? 'is-active' : 'is-inactive' }}">
                                                            {{ $pivotActive ? 'Активен' : 'Неактивен' }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $updatedAt }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="product-details-mod-card__empty">
                                    Для модификации ещё не привязаны склады.
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

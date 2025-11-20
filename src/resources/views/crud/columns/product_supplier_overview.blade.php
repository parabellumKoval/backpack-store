@php
    $grid = $entry->adminSupplierWarehouseGrid();
    $modifications = collect($grid['modifications'] ?? []);
    $suppliers = collect($grid['suppliers'] ?? []);
    $hasRealModifications = (bool) ($grid['has_real_modifications'] ?? false);
    $modCount = max($modifications->count(), 1);
    $currentProductId = $grid['current_product_id'] ?? null;

    $formatNumber = function ($value, int $decimals = 2) {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) $value;
        $formatted = number_format($number, $decimals, '.', ' ');

        if ($decimals > 0) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted;
    };

    $formatPrice = function ($value, ?string $currency = null) use ($formatNumber) {
        $formatted = $formatNumber($value, 2);

        if ($formatted === null) {
            return null;
        }

        $currency = trim((string) $currency);

        return $currency === '' ? $formatted : trim($formatted . ' ' . $currency);
    };

    $formatStock = function ($value) use ($formatNumber) {
        if ($value === null || $value === '') {
            return null;
        }

        $numeric = (float) $value;

        if ((int) $numeric === $numeric) {
            return (string) (int) $numeric;
        }

        return $formatNumber($numeric, 2);
    };

    $modLabel = function (array $mod) {
        $short = trim((string) ($mod['short_name'] ?? ''));

        if ($short !== '') {
            return $short;
        }

        $name = trim((string) ($mod['name'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        return 'ID ' . ($mod['id'] ?? '—');
    };
@endphp

@once
    @push('crud_list_column_styles')
        <style>
        .product-supplier-grid {
            font-size: 12px;
            line-height: 1.35;
            color: #111827;
            overflow-x: auto;
            max-width: 100%;
            padding: 0.75rem 1rem;
            margin: -0.75rem -1rem;
            /* border-top: 1px solid #eee; */
            background: rgba(0,0,0,0.04);
            border-radius: 10px 10px 0 0;
        }

        .product-supplier-grid__table {
            display: inline-table;
            border-collapse: separate;
            border-spacing: 6px;
            width: auto !important;
            min-width: 0;
            max-width: none;
            table-layout: auto;
        }

        .product-supplier-grid__table thead {
            display: table-header-group !important;
        }

        .product-supplier-grid__table tbody {
            display: table-row-group !important;
        }

        .product-supplier-grid__table tr {
            display: table-row !important;
        }

        .product-supplier-grid__table th,
        .product-supplier-grid__table td {
            display: table-cell !important;
            padding: 0;
            border: none;
            vertical-align: top;
            text-align: left;
        }

        .psg-cell {
            border-radius: 8px;
            padding: 6px 8px;
            background: #fff;
            min-height: 52px;
        }

        .psg-cell--head {
            background: transparent !important;
            border: none;
            padding: 0;
            min-height: auto;
            padding: 0 !important;
        }

        .psg-cell--placeholder,
        .psg-code--muted {
            color: #9ca3af;
        }

        .psg-cell--supplier {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            font-weight: 600;
            flex-wrap: wrap;
            word-break: break-word;
            white-space: normal;
            min-width: 0;
            background: transparent !important;
            padding: 5px !important;
            vertical-align: middle !important;
        }
        .psg-supplier-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 999px;
            flex-shrink: 0;
            transform: translateY(1px);
        }

        .psg-supplier-name {
            flex: 1 1 100%;
            min-width: 0;
        }

        .psg-cell--data {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 4px;
            min-width: 0;
            border: 1px solid #e5e7eb !important;
            background: #fff !important;
            padding: 5px !important;
        }

        .psg-cell--data {
            padding-left: 10px !important;
            padding-right: 10px !important;

        }

        .psg-code {
            font-weight: 500;
            color: #1f2937;
        }

        .psg-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: baseline;
        }

        .psg-stock {
            font-weight: 600;
            color: #047857;
        }

        .psg-price {
            font-weight: 600;
            color: #111827;
        }

        .psg-old-price {
            color: #9ca3af;
            text-decoration: line-through;
            font-weight: 500;
        }

        .psg-no-stock {
            font-weight: 700;
            color: #dc2626;
        }

        .psg-no-data {
            color: #9ca3af;
        }

        .psg-mod-tag {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 5px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .psg-mod-tag:hover {
            background: #dbeafe;
            color: #1e3a8a;
        }

        .psg-mod-tag.is-inactive {
            opacity: 0.55;
        }

        .psg-mod-tag.is-current {
            border-color: #1d4ed8;
            box-shadow: 0 0 0 1px rgba(29, 78, 216, 0.2);
        }

        .psg-empty {
            margin-top: 0;
            color: #9ca3af;
            background: transparent;
            border: none;
            padding: 0;
            min-height: auto;
        }
        </style>
    @endpush
@endonce

@if($modifications->isEmpty() && $suppliers->isEmpty())
    <span class="text-muted small">—</span>
@else
    <div class="product-supplier-grid">
        <table class="product-supplier-grid__table">

            @if($hasRealModifications)
                <thead>
                    <tr>
                        <th class="psg-cell psg-cell--head"></th>
                        @forelse($modifications as $mod)
                            <th class="psg-cell psg-cell--head">
                                <a href="{{ backpack_url('product/' . $mod['id'] . '/edit') }}"
                                   class="psg-mod-tag {{ ($mod['is_active'] ?? true) ? '' : 'is-inactive' }} {{ ($mod['id'] ?? null) === $currentProductId ? 'is-current' : '' }}"
                                   title="Перейти к модификации #{{ $mod['id'] }}">
                                    {{ $modLabel($mod) }}
                                </a>
                            </th>
                        @empty
                            <th class="psg-cell psg-cell--head psg-cell--placeholder">—</th>
                        @endforelse
                    </tr>
                </thead>
            @endif

            <tbody>
                @forelse($suppliers as $supplier)
                    @php
                        $supplierCurrency = $supplier['currency'] ?? null;
                    @endphp
                    <tr>
                        <th scope="row" class="psg-cell psg-cell--supplier">
                            <span class="psg-supplier-dot" style="background-color: {{ $supplier['color'] ?? '#d1d5db' }}"></span>
                            {{ $supplier['name'] ?? '—' }}
                        </th>
                        @forelse($modifications as $mod)
                            @php
                                $cell = $supplier['items'][$mod['id']] ?? null;
                                $code = $cell['code'] ?? $cell['barcode'] ?? null;
                                $stock = $cell['in_stock'] ?? null;
                                $price = $cell['price'] ?? null;
                                $oldPrice = $cell['old_price'] ?? null;
                                $hasStock = $stock !== null && (float) $stock > 0;
                                $stockText = $formatStock($stock);
                                $priceText = $formatPrice($price, $supplierCurrency);
                                $oldPriceText = $formatPrice($oldPrice, $supplierCurrency);
                            @endphp
                            <td class="psg-cell psg-cell--data">
                                @if($cell)
                                    <div class="psg-code">{{ $code ?? '—' }}</div>
                                    <div class="psg-meta">
                                        @if($hasStock)
                                            <span class="psg-stock">{{ $stockText }} ед.</span>
                                            @if($priceText)
                                                <span class="psg-price">{{ $priceText }}</span>
                                            @endif
                                            @if($oldPriceText)
                                                <span class="psg-old-price">{{ $oldPriceText }}</span>
                                            @endif
                                        @else
                                            <span class="psg-no-stock">НЕТ</span>
                                        @endif
                                    </div>
                                @else
                                    <div class="psg-code psg-code--muted">—</div>
                                    <div class="psg-meta">
                                        <span class="psg-no-data">нет данных</span>
                                    </div>
                                @endif
                            </td>
                        @empty
                            <td class="psg-cell psg-cell--data">
                                <div class="psg-code psg-code--muted">—</div>
                                <div class="psg-meta">
                                    <span class="psg-no-data">нет данных</span>
                                </div>
                            </td>
                        @endforelse
                    </tr>
                @empty
                    <tr>
                        <td class="psg-cell psg-empty" colspan="{{ $modCount + 1 }}">Нет активных складов для отображения.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif

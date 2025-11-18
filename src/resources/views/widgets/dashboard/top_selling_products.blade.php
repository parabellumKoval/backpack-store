@php
    $products = collect($products ?? []);
    $baseCurrency = strtoupper(config('dress.store.base_currency', 'USD'));
@endphp

<div class="card shadow-sm h-100">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <div class="mb-2 mb-md-0">
            <strong>{{ trans('backpack-store::dashboard.widgets.top_products_title') }}</strong>
            <div class="text-muted small">{{ trans('backpack-store::dashboard.widgets.top_products_subtitle') }}</div>
        </div>
        <span class="badge badge-light">{{ $products->count() }}</span>
    </div>

    @if($products->isEmpty())
        <div class="text-center text-muted py-5">
            {{ trans('backpack-store::dashboard.widgets.empty_state') }}
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="thead-light">
                    <tr>
                        <th>{{ trans('backpack-store::dashboard.widgets.top_products_column_product') }}</th>
                        <th class="text-center">{{ trans('backpack-store::dashboard.widgets.top_products_column_rating') }}</th>
                        <th class="text-center">{{ trans('backpack-store::dashboard.widgets.top_products_column_sold') }}</th>
                        <th class="text-center">{{ trans('backpack-store::dashboard.widgets.top_products_column_revenue') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                        @php
                            $ratingValue = $product['rating'] ?? null;
                            $ratingsCount = (int) ($product['ratings_count'] ?? 0);
                            $hasRating = $ratingValue !== null && $ratingsCount > 0;
                            $rating = $hasRating ? (float) $ratingValue : 0.0;
                            $fullStars = $hasRating ? floor($rating) : 0;
                            $hasHalf = $hasRating ? (($rating - $fullStars) >= 0.5) : false;
                            $emptyStars = 5 - $fullStars - ($hasHalf ? 1 : 0);
                        @endphp
                        <tr>
                            <td>
                                <div class="media align-items-center">
                                    <div class="product-thumb mr-3">
                                        @if(!empty($product['image_url']))
                                            <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}" class="img-fluid">
                                        @else
                                            <span class="thumb-placeholder la la-image"></span>
                                        @endif
                                    </div>
                                    <div class="media-body">
                                        <div class="font-weight-bold">{{ $product['name'] }}</div>
                                        <div class="text-muted small">{{ $product['price_display'] ?? '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center align-middle">
                                @if($hasRating)
                                    <div class="rating-stars mb-1" title="{{ number_format($rating, 1) }}/5">
                                        @for($i = 0; $i < $fullStars; $i++)
                                            <i class="la la-star text-warning"></i>
                                        @endfor
                                        @if($hasHalf)
                                            <i class="la la-star-half-o text-warning"></i>
                                        @endif
                                        @for($i = 0; $i < $emptyStars; $i++)
                                            <i class="la la-star-o text-muted"></i>
                                        @endfor
                                    </div>
                                @else
                                    <div class="rating-stars mb-1">
                                        @for($i = 0; $i < 5; $i++)
                                            <i class="la la-star-o text-muted"></i>
                                        @endfor
                                    </div>
                                @endif
                                <div class="text-muted small">
                                    {{ trans_choice('backpack-store::dashboard.widgets.top_products_reviews', $ratingsCount, ['count' => $ratingsCount]) }}
                                </div>
                            </td>
                            <td class="text-center align-middle">
                                <div class="h5 mb-0">{{ number_format((int) ($product['units_sold'] ?? 0)) }}</div>
                                <div class="text-muted small">{{ trans('backpack-store::dashboard.widgets.top_products_units_label') }}</div>
                            </td>
                            <td class="text-center align-middle">
                                <!-- <div class="font-weight-bold">{{ $product['price_display'] ?? '—' }}</div>
                                <div class="text-muted small mb-2">{{ trans('backpack-store::dashboard.widgets.top_products_price_current') }}</div> -->
                                <div class="font-weight-bold">{{ $product['revenue_display'] ?? '—' }}</div>
                                <div class="text-muted small">
                                    {{ trans('backpack-store::dashboard.widgets.top_products_revenue_note', ['currency' => $baseCurrency]) }}
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@push('after_styles')
    <style>
        .product-thumb {
            width: 56px;
            height: 56px;
            border-radius: 8px;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .product-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .thumb-placeholder {
            font-size: 1.5rem;
            color: #9ca3af;
        }
    </style>
@endpush

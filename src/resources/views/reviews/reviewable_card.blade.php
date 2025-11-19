{{--
Product Card for Reviews List
Displays product image, name (clickable), price, and code
--}}

@php
    $product = $reviewable ?? null;
    
    if (!$product) {
        echo '<span class="text-muted">—</span>';
        return;
    }
    
    // Get product data
    $image = $product->getFirstImageForApi()['src'] ?? null;
    $name = $product->name ?? 'Без названия';
    $code = $product->code ?? '—';
    $price = $product->price ?? null;
    $editUrl = backpack_url('product/' . $product->id . '/edit') ?? $editRoute;
    
    // Format price
    $formattedPrice = $price ? number_format($price, 2, ',', ' ') . ' ₴' : '—';
    
    // Get review rating
    $rating = data_get($entry, 'rating', 0);
    
    // Rating configuration for stars
    $ratingColumn = [
        'name' => 'rating',
        'max' => 5,
        'color' => '#f2c200',
        'size' => '14px',
        'show_value' => true,
    ];
@endphp

<div class="reviewable-card product-card" style="display: flex; align-items: center; gap: 12px; padding: 8px 0;  border-radius: 6px; border: 1px solid #e0e0e0;">
    {{-- Product Image --}}
    <div class="product-image" style="flex-shrink: 0;">
        @if($image)
            <img src="{{ $image }}" 
                 alt="{{ $name }}" 
                 style="width: 60px; height: 60px; object-fit: cover;">
        @else
            <div style="width: 60px; height: 60px; background: #f5f5f5; border-radius: 6px; display: flex; align-items: center; justify-content: center; border: 1px solid #e0e0e0;">
                <i class="la la-image" style="font-size: 24px; color: #ccc;"></i>
            </div>
        @endif
    </div>
    
    {{-- Product Info --}}
    <div class="product-info" style="flex-grow: 1; min-width: 0;">
        
        {{-- Rating Stars --}}
        @if($rating > 0)
        <div class="product-rating" style="margin-bottom: 4px;">
            @include('crud::columns.rating_stars', ['column' => $ratingColumn, 'entry' => $entry])
        </div>
        @endif
        {{-- Product Name (clickable) --}}
        <div class="product-name" style="margin-bottom: 4px;">
            <a href="{{ $editUrl }}" 
               style="color: #333; font-weight: 700; text-decoration: none; font-size: 14px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"
               title="{{ $name }}">
                {{ $name }}
            </a>
        </div>
        
        {{-- Product Code and Price --}}
        <div class="product-meta" style="display: flex; align-items: center; gap: 12px; font-size: 13px; color: #666;">
            <!-- <span class="product-code" style="display: flex; align-items: center; gap: 4px;">
                <i class="la la-barcode" style="font-size: 16px;"></i>
                <span>{{ $code }}</span>
            </span> -->
            
            <span class="product-price" style="display: flex; align-items: center; gap: 4px; color: #28a745; font-weight: 600;">
                <i class="la la-tag" style="font-size: 16px;"></i>
                <span>{{ $formattedPrice }}</span>
            </span>
        </div>
    </div>
</div>

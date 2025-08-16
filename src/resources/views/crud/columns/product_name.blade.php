@php
    $currentUrl = backpack_url('product');
    $currentParams = request()->query();
@endphp
<div class="product-admin-name">
 
    <div class="product-admin-name__title">{{ $name }}</div>

    <div class="product-admin-name__metadata">
        @if($brand)
            <span class="product-admin-name__metadata-label">Бренд:</span>
            <b>{!! $brandLinkAdmin !!}</b>&nbsp;&nbsp;
        @endif
        
        @if($category)
            <span class="product-admin-name__metadata-label">Категории:</span>
            <b>{!! $categoryLinksAdmin !!}</b>
        @endif
    </div>

    @if($modifications)
        <div class="mt-2">
            @foreach($modifications as $modification)
                <a href="{{ url('/admin/product/' . $modification['id'] . '/edit') }}" class="btn btn-outline-primary btn-sm mod-btn">{{ $modification['name'] }}</a>
            @endforeach
        </div>
    @endif
</div>
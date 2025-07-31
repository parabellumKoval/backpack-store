<div class="admin-props-specs-tooltip">
    <ul class="mb-0 pl-3">
        @foreach($details as $key => $item)
            <li>
                <strong>{{ __("backpack-store::product_quality.$key") ?? ucfirst($key) }}</strong>: 
                {{ $item['value'] }} {{ $item['unit'] }} 
                <span>({{ $item['rate'] }})</span>
            </li>
        @endforeach
    </ul>
</div>
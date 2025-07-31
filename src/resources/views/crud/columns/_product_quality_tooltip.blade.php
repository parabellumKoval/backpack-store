@if($details)
<div class="admin-props-specs-tooltip">
    <h5 class="text-nowrap">{{ __("backpack-store::product_quality.title") }}</h5>
    <ul class="mb-0 pl-3">
        @foreach($details as $key => $item)
          @php
            $unit = $item['unit'];
            $rate = (int)$item['rate'];
          @endphp
            <li class="text-nowrap">
                <strong>{{ __("backpack-store::product_quality.$key") ?? ucfirst($key) }}</strong>: 
                {{ $item['value'] }} {{  $unit? __("backpack-store::product_quality.$unit"): '' }}
                <span class="badge {{ $rate <= 30 ? 'badge-danger' : ($rate <= 70 ? 'badge-warning' : 'badge-success') }}">{{ $rate }}%</span>
            </li>
        @endforeach
    </ul>
</div>
@endif
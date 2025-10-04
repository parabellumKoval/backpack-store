@php
    /** @var \Backpack\Store\app\Models\Admin\CurrencyRate $entry */
    $base  = e($entry->base ?? 'BASE');
    $rates = is_array($entry->rates ?? null) ? $entry->rates : [];
    ksort($rates);
@endphp

<div class="mb-2">
  <strong>{{ trans('backpack-store::currency-rates.base') }}:</strong> {{ $base }}
  &nbsp;|&nbsp;
  <strong>{{ trans('backpack-store::currency-rates.total_rates') }}:</strong> {{ count($rates) }}
</div>

@if($rates)
  <table class="table table-sm table-striped mb-0">
    <thead>
      <tr>
        <th style="width:140px">{{ trans('backpack-store::currency-rates.code') }}</th>
        <th>{{ trans('backpack-store::currency-rates.rate_per_base') }} ({{ $base }})</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rates as $code => $rate)
        <tr>
          <td><code>{{ e($code) }}</code></td>
          <td class="text-right">
            @if(is_numeric($rate))
              {{ number_format((float)$rate, 6, '.', ' ') }}
            @else
              {{ e($rate) }}
            @endif
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
@else
  <em>{{ trans('backpack-store::currency-rates.no_rates') }}</em>
@endif

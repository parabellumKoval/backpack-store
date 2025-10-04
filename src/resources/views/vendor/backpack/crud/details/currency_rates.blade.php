{{-- $entry доступен автоматически --}}
@if(is_array($entry->rates) && count($entry->rates))
    <div class="m-b-10">
        <strong>{{ trans('backpack-store::currency-rates.base') }}:</strong> {{ $entry->base }}
        &nbsp;|&nbsp;
        <strong>{{ trans('backpack-store::currency-rates.total_rates') }}:</strong> {{ count($entry->rates) }}
    </div>

    <table class="table table-sm table-striped m-b-0">
        <thead>
            <tr>
                <th style="width:120px">{{ trans('backpack-store::currency-rates.code') }}</th>
                <th>{{ trans('backpack-store::currency-rates.rate_per_base') }} ({{ $entry->base }})</th>
            </tr>
        </thead>
        <tbody>
            @foreach(collect($entry->rates)->sortKeys()->all() as $code => $rate)
                <tr>
                    <td><code>{{ $code }}</code></td>
                    <td>{{ is_numeric($rate) ? number_format($rate, 6, '.', ' ') : e($rate) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <em>{{ trans('backpack-store::currency-rates.no_rates') }}</em>
@endif

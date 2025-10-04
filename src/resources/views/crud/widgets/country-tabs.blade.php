@php
    /** @var array $countries */
    /** @var string $active */
    /** @var string $param */
    /** @var string $filterKey */
    $baseUrl = url(request()->path());
    $qs = request()->query();
    // Удаляем конфликтующие параметры фильтра Backpack (оставим их, чтобы фильтры жили), но country_code перебьём табом:
    unset($qs['country_code']); // фильтр привяжем к табу
@endphp

<div class="d-flex align-items-center flex-wrap">
    @php
        $makeUrl = function (?string $code) use ($baseUrl, $qs, $param) {
            $qs[$param] = $code ?? 'all';
            return $baseUrl.'?'.http_build_query($qs);
        };
        $pillClass = 'btn btn-sm btn-outline-primary mr-1 mb-1';
        $pillActive = 'btn-primary text-white';
    @endphp

    <a href="{{ $makeUrl(null) }}"
       class="{{ $pillClass }} {{ ($active==='all') ? $pillActive : '' }}">
        Все
    </a>

    @foreach($countries as $code)
        <a href="{{ $makeUrl($code) }}"
           class="{{ $pillClass }} {{ ($active===$code) ? $pillActive : '' }}">
            {{ strtoupper($code) }}
        </a>
    @endforeach
</div>

{{-- Когда переключили таб — синхронно проставим реальный Backpack-фильтр в querystring (чтобы «в строю» остались все остальные фильтры) --}}
@if(($active ?? 'all') !== 'all')
    @push('after_scripts')
        <script>
            (function () {
                const url = new URL(window.location.href);
                url.searchParams.set('{{$filterKey}}', '{{$active}}');
                // не перезагружаем: список и так переотрисован сервером
                history.replaceState({}, '', url.toString());
            })();
        </script>
    @endpush
@else
    @push('after_scripts')
        <script>
            (function () {
                const url = new URL(window.location.href);
                url.searchParams.delete('{{$filterKey}}');
                history.replaceState({}, '', url.toString());
            })();
        </script>
    @endpush
@endif

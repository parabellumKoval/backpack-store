@php
    /** @var array $cards each: ['code','name','total','new'] */
    extract($widget['content']);

    $qs = request()->query();
    $countryUrl = function (string $code) use ($qs, $listUrl, $param, $filterKey) {
        if ($code === 'all') {
            unset($qs[$param], $qs[$filterKey]);
        } else {
            $qs[$param] = $code;
            $qs[$filterKey] = $code;
        }
        return $listUrl.'?'.http_build_query($qs);
    };
@endphp

<div class="d-flex flex-wrap">
    @foreach($cards as $c)
        @php
            $isActive = ($active === $c['code']) || ($c['code'] === 'all' && ($active === 'all' || $active === null));
            $classes  = 'card mr-2 mb-2';
            if ($isActive) $classes .= ' bg-secondary';
        @endphp

        <a href="{{ $countryUrl($c['code']) }}" class="{{ $classes }}" style="min-width:160px;text-decoration:none;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="font-weight-bold">{{ $c['name'] }}</div>
                    <span class="badge {{ $isActive ? 'badge-primary' : 'badge-secondary' }}">{{ $c['total'] }}</span>
                </div>
                <div class="small text-muted mt-1">Новые</div>
                <div class="h5 m-0">
                    <div class="badge badge-{{ $c['new'] ? 'warning' : 'secondary' }}">{{ $c['new'] }}</div>
                </div>
            </div>
        </a>
    @endforeach
</div>

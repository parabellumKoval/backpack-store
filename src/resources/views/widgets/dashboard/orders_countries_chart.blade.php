@php
    $chart = $chart ?? ['labels' => [], 'datasets' => []];
    $countries = collect($countries ?? []);
    $totalOrders = (int) ($totalOrders ?? 0);
    $range = $range ?? [];
    $monthsCount = max(1, (int) ($range['months'] ?? count($chart['labels'] ?? [])));
    $chartId = 'orders-country-chart-' . uniqid();
    $hasChart = !empty($chart['datasets']);
    $subtitle = trans_choice('backpack-store::dashboard.widgets.orders_countries_months', $monthsCount, ['count' => $monthsCount]);
@endphp

<div class="card shadow-sm h-100" id="orders-by-country-widget">
    <div class="card-body">
        <div class="row">
            <div class="col-sm-6">
                <h4 class="card-title mb-0">{{ trans('backpack-store::dashboard.widgets.orders_countries_title') }}</h4>
                <div class="small text-muted">{{ $subtitle }}</div>
            </div>
            <div class="col-sm-6 text-sm-right mt-3 mt-sm-0">
                <div class="text-muted small">{{ trans('backpack-store::dashboard.widgets.orders_countries_total') }}</div>
                <div class="h4 mb-0">{{ number_format($totalOrders, 0, '.', ' ') }}</div>
            </div>
        </div>
        <div class="chart-wrapper mt-4" style="height:300px;">
            @if($hasChart)
                <canvas id="{{ $chartId }}" data-chart='@json($chart)'></canvas>
            @else
                <div class="text-center text-muted py-5">{{ trans('backpack-store::dashboard.widgets.orders_countries_empty') }}</div>
            @endif
        </div>
    </div>
    <div class="card-footer">
        <div class="row text-center align-items-center">
            @forelse($countries as $country)
                <div class="col-sm-12 col-md mb-sm-2 mb-3 mb-md-0">
                    <div class="orders-country-card d-flex flex-column align-items-center">
                        <div class="d-flex align-items-center mb-2">
                            @if(!empty($country['flag']))
                                <span class="orders-country-flag">{!! $country['flag'] !!}</span>
                            @endif
                            <div class="text-left">
                                <div class="font-weight-bold">{{ $country['label'] }}</div>
                                <div class="text-muted small">{{ strtoupper($country['code'] ?? '') }}</div>
                            </div>
                        </div>
                        <strong>
                            {{ trans_choice('backpack-store::dashboard.widgets.orders_countries_orders', $country['orders'], [
                                'count' => number_format($country['orders'], 0, '.', ' ')
                            ]) }}
                        </strong>
                        <div class="text-muted small">{{ $country['percent_display'] ?? $country['percent'] }}%</div>
                        <div class="progress progress-xs mt-2 w-100">
                            <div class="progress-bar" role="progressbar"
                                 style="width: {{ $country['percent'] ?? 0 }}%; background-color: {{ $country['color'] }};"
                                 aria-valuenow="{{ $country['percent'] ?? 0 }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center text-muted py-3">{{ trans('backpack-store::dashboard.widgets.orders_countries_empty') }}</div>
            @endforelse
        </div>
    </div>
</div>

@if($hasChart)
    @push('after_scripts')
        <script>
            window.addEventListener('DOMContentLoaded', function () {
                const canvas = document.getElementById('{{ $chartId }}');
                if (!canvas || typeof Chart === 'undefined') {
                    return;
                }
                const ctx = canvas.getContext('2d');
                const config = JSON.parse(canvas.dataset.chart || '{}');
                if (!config.labels || !config.labels.length || !config.datasets || !config.datasets.length) {
                    return;
                }
                new Chart(ctx, {
                    type: 'line',
                    data: config,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: {
                                intersect: false,
                                mode: 'index',
                                callbacks: {
                                    label: (context) => {
                                        const raw = context.parsed;
                                        const value = typeof raw === 'object' && raw !== null ? raw.y : raw;
                                        const label = context.dataset?.label ? context.dataset.label + ': ' : '';
                                        return `${label}${value ?? 0}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0 },
                                grid: { color: 'rgba(226,232,240,0.5)' }
                            }
                        }
                    }
                });
            });
        </script>
    @endpush
@endif

@push('after_styles')
    <style>
        #orders-by-country-widget .orders-country-flag {
            font-size: 1.5rem;
            line-height: 1;
            margin-right: 0.5rem;
        }
        #orders-by-country-widget .orders-country-card {
            min-height: 140px;
        }
    </style>
@endpush

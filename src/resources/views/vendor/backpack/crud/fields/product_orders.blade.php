@php
    $field['fetch_url'] = $field['fetch_url'] ?? '#';
    $field['per_page'] = $field['per_page'] ?? 10;
    $translations = trans('backpack-store::product.orders_tab');
@endphp

@include('crud::fields.inc.wrapper_start')

<div class="product-orders-widget" data-product-orders
     data-fetch-url="{{ $field['fetch_url'] }}"
     data-per-page="{{ (int) $field['per_page'] }}"
     data-i18n='@json($translations, JSON_UNESCAPED_UNICODE)'>
    <div class="product-orders-alert product-orders-alert--error alert alert-danger d-none" data-orders-error></div>

    <div class="product-orders-loading" data-orders-loading>
        <span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>
        <span>{{ $translations['messages']['loading'] ?? 'Loading…' }}</span>
    </div>

    <div class="product-orders-content d-none" data-orders-content>
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small mb-1">{{ $translations['summary']['orders'] ?? 'Orders' }}</div>
                        <div class="h3 mb-0" data-summary-orders-value>—</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small mb-1">{{ $translations['summary']['quantity'] ?? 'Units' }}</div>
                        <div class="h3 mb-0" data-summary-quantity-value>—</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small mb-1">{{ $translations['summary']['revenue'] ?? 'Revenue' }}</div>
                        <div class="h3 mb-1" data-summary-revenue-value>—</div>
                        <div class="text-muted small" data-summary-revenue-hint></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>{{ $translations['chart']['title'] ?? 'Demand dynamics' }}</span>
            </div>
            <div class="card-body">
                <canvas data-orders-chart height="110"></canvas>
                <div class="text-muted small mt-2 d-none" data-chart-empty>{{ $translations['messages']['chart_empty'] ?? 'Not enough data for the chart yet.' }}</div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ $translations['table']['order'] ?? 'Order' }}</th>
                                <th>{{ $translations['table']['status'] ?? 'Statuses' }}</th>
                                <th>{{ $translations['table']['customer'] ?? 'Customer' }}</th>
                                <th class="text-center">{{ $translations['table']['quantity'] ?? 'Qty' }}</th>
                                <th class="text-right">{{ $translations['table']['price'] ?? 'Price' }}</th>
                                <th class="text-right">{{ $translations['table']['total'] ?? 'Total' }}</th>
                            </tr>
                        </thead>
                        <tbody data-orders-table></tbody>
                    </table>
                </div>
                <div class="p-3 text-center text-muted d-none" data-orders-empty>{{ $translations['messages']['empty'] ?? 'No orders yet.' }}</div>
            </div>
            <div class="card-footer d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div class="text-muted small mb-2 mb-md-0" data-pagination-summary></div>
                <nav>
                    <ul class="pagination pagination-sm mb-0" data-pagination></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

@include('crud::fields.inc.wrapper_end')

@if ($crud->fieldTypeNotLoaded($field))
    @php $crud->markFieldTypeAsLoaded($field); @endphp

    @push('crud_fields_styles')
        <style>
            .product-orders-widget {
                padding: 1.5rem;
                background-color: #fff;
                border-radius: .75rem;
                border: 1px solid rgba(15, 23, 42, 0.08);
            }
            @media (max-width: 767.98px) {
                .product-orders-widget {
                    padding: 1rem;
                }
            }
            .product-orders-widget .card {
                border-radius: .5rem;
            }
            .product-orders-loading {
                padding: 1rem 0;
                display: flex;
                align-items: center;
                gap: .5rem;
            }
            .product-orders-loading.is-hidden {
                display: none !important;
            }
            .product-orders-alert--error {
                margin-bottom: 1rem;
            }
            .product-orders-table-status span {
                margin-right: .25rem;
            }
        </style>
    @endpush

    @push('crud_fields_scripts')
        @once('product-orders-chart-js')
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        @endonce
        <script>
            (function () {
                const initWidget = (container) => {
                const fetchUrl = container.dataset.fetchUrl;
                if (!fetchUrl) {
                    return;
                }

                const i18n = JSON.parse(container.dataset.i18n || '{}');
                const perPage = parseInt(container.dataset.perPage || '10', 10);

                    const els = {
                    loading: container.querySelector('[data-orders-loading]'),
                    content: container.querySelector('[data-orders-content]'),
                    error: container.querySelector('[data-orders-error]'),
                    empty: container.querySelector('[data-orders-empty]'),
                    table: container.querySelector('[data-orders-table]'),
                    pagination: container.querySelector('[data-pagination]'),
                    paginationSummary: container.querySelector('[data-pagination-summary]'),
                    ordersValue: container.querySelector('[data-summary-orders-value]'),
                    quantityValue: container.querySelector('[data-summary-quantity-value]'),
                        revenueValue: container.querySelector('[data-summary-revenue-value]'),
                        revenueHint: container.querySelector('[data-summary-revenue-hint]'),
                        chartCanvas: container.querySelector('[data-orders-chart]'),
                        chartEmpty: container.querySelector('[data-chart-empty]'),
                };

                const state = {
                    page: 1,
                    perPage: perPage,
                    chart: null,
                };

                    const t = (path, fallback = '') => {
                        return path.split('.').reduce((carry, key) => (carry && carry[key] !== undefined) ? carry[key] : undefined, i18n) ?? fallback;
                    };

                    const numberFormatter = new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 });
                    const moneyFormatter = (value, currency) => {
                        if (value === null || value === undefined) {
                            return '—';
                        }
                        const numericValue = Number(value);
                        if (Number.isFinite(numericValue)) {
                            try {
                                return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD', minimumFractionDigits: 2 }).format(numericValue);
                            } catch (e) {
                                return `${currency || ''} ${numericValue.toFixed(2)}`.trim();
                            }
                        }
                        return `${currency || ''} ${value}`.trim();
                    };

                    const toggleChartEmpty = (show) => {
                        if (!els.chartEmpty) {
                            return;
                        }
                        els.chartEmpty.classList.toggle('d-none', !show);
                        if (show) {
                            els.chartEmpty.textContent = t('messages.chart_empty', els.chartEmpty.textContent || '');
                        }
                    };

                    const setLoading = (isLoading) => {
                        if (!els.loading) {
                            return;
                        }
                        if (isLoading) {
                            els.loading.classList.remove('is-hidden');
                            els.content?.classList.add('d-none');
                            els.error?.classList.add('d-none');
                        } else {
                            els.loading.classList.add('is-hidden');
                        }
                    };

                    const showContent = () => {
                        els.loading?.classList.add('is-hidden');
                        els.content?.classList.remove('d-none');
                        els.error?.classList.add('d-none');
                    };

                    const showError = (message) => {
                        els.loading?.classList.add('is-hidden');
                        els.content?.classList.add('d-none');
                        els.error?.classList.remove('d-none');
                        els.error.textContent = message || (t('messages.error') || 'Failed to load data');
                    };

                    const renderSummary = (summary) => {
                        if (!summary) {
                            return;
                        }
                        els.ordersValue.textContent = numberFormatter.format(summary.orders_count || 0);
                        els.quantityValue.textContent = numberFormatter.format(summary.total_quantity || 0);
                        els.revenueValue.textContent = summary.revenue_display || '—';
                        if (Array.isArray(summary.revenue_per_currency) && summary.revenue_per_currency.length > 0) {
                            const hint = summary.revenue_per_currency.map((item) => {
                                const amount = Number(item.amount ?? 0);
                                const amountText = Number.isFinite(amount) ? amount.toFixed(2) : (item.amount ?? '0');
                                return `${item.currency || ''} ${amountText}`.trim();
                            }).join(' / ');
                            els.revenueHint.textContent = hint;
                        } else {
                            els.revenueHint.textContent = t('summary.revenue_empty', '');
                        }
                    };

                    const renderChart = (chart) => {
                        if (!els.chartCanvas || typeof Chart === 'undefined') {
                            return;
                        }
                        if (!chart || !chart.labels || chart.labels.length === 0) {
                            if (state.chart) {
                                state.chart.destroy();
                                state.chart = null;
                            }
                            toggleChartEmpty(true);
                            return;
                        }
                        toggleChartEmpty(false);
                        const ctx = els.chartCanvas.getContext('2d');
                        if (state.chart) {
                            state.chart.destroy();
                        }
                        state.chart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: chart.labels,
                            datasets: [
                                {
                                    type: 'bar',
                                    label: t('chart.quantity_label', 'Units'),
                                    data: chart.quantity || [],
                                    backgroundColor: 'rgba(70, 127, 208, 0.3)',
                                    borderColor: 'rgba(70, 127, 208, 1)',
                                    borderWidth: 1,
                                    yAxisID: 'y',
                                },
                                {
                                    type: 'line',
                                    label: t('chart.revenue_label', 'Revenue'),
                                    data: chart.revenue || [],
                                    borderColor: 'rgba(246, 101, 163, 1)',
                                    backgroundColor: 'rgba(246, 101, 163, 0.15)',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.3,
                                    yAxisID: 'y1',
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { intersect: false, mode: 'index' },
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    ticks: { precision: 0 }
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    grid: { drawOnChartArea: false },
                                    ticks: { callback: (value) => moneyFormatter(value, chart.currency || 'USD') }
                                }
                            },
                            plugins: { legend: { display: true } }
                        }
                    });
                };

                    const renderTable = (orders) => {
                        const rows = orders?.data || [];
                        els.empty?.classList.toggle('d-none', rows.length !== 0);
                        els.table.innerHTML = rows.map((order) => {
                            const statusBadge = order.status?.label ? `<span class="badge badge-light">${order.status.label}</span>` : '';
                            const payBadge = order.pay_status?.label ? `<span class="badge badge-light">${order.pay_status.label}</span>` : '';
                            const deliveryBadge = order.delivery_status?.label ? `<span class="badge badge-light">${order.delivery_status.label}</span>` : '';
                            const customer = order.customer || '';
                            const qty = order.quantity != null ? numberFormatter.format(order.quantity) : '—';
                            const unit = order.unit_price != null ? moneyFormatter(order.unit_price, order.currency) : '—';
                            const total = order.total != null ? moneyFormatter(order.total, order.currency) : '—';
                            const code = order.code || order.id;
                            const orderLink = order.edit_url ? `<a href="${order.edit_url}" target="_blank" rel="noopener">#${code}</a>` : `#${code}`;
                            const createdAt = order.created_at_human || '';
                            return `
                                <tr>
                                    <td>
                                        <div>${orderLink}</div>
                                        <div class="text-muted small">${createdAt}</div>
                                    </td>
                                    <td class="product-orders-table-status">
                                        ${[statusBadge, payBadge, deliveryBadge].filter(Boolean).join(' ')}
                                    </td>
                                    <td>${customer}</td>
                                    <td class="text-center">${qty}</td>
                                    <td class="text-right">${unit}</td>
                                    <td class="text-right font-weight-bold">${total}</td>
                                </tr>
                            `;
                        }).join('');

                        renderPagination(orders);
                    };

                    const renderPagination = (orders) => {
                        const meta = orders || {};
                        const tpl = t('pagination.showing', 'Showing :from–:to of :total');
                        els.paginationSummary.textContent = tpl
                            .replace(':from', meta.from ?? 0)
                            .replace(':to', meta.to ?? 0)
                            .replace(':total', meta.total ?? 0);

                        els.pagination.innerHTML = '';
                        if ((meta.last_page || 1) <= 1) {
                            return;
                        }

                        for (let page = 1; page <= meta.last_page; page++) {
                            const li = document.createElement('li');
                            li.className = 'page-item' + (page === meta.current_page ? ' active' : '');
                            const button = document.createElement('button');
                            button.type = 'button';
                            button.className = 'page-link';
                            button.textContent = page;
                            button.addEventListener('click', () => {
                                if (state.page === page) {
                                    return;
                                }
                                state.page = page;
                                fetchData(page);
                            });
                            li.appendChild(button);
                            els.pagination.appendChild(li);
                        }
                    };

                    const fetchData = (page = 1) => {
                        setLoading(true);
                        let url;
                        try {
                            url = new URL(fetchUrl, window.location.origin);
                        } catch (e) {
                            url = new URL(window.location.origin + '/' + fetchUrl.replace(/^\//, ''));
                        }
                        url.searchParams.set('page', page);
                        url.searchParams.set('per_page', state.perPage);
                        fetch(url.toString(), {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        })
                            .then((response) => {
                                if (!response.ok) {
                                    throw new Error('Network');
                                }
                                return response.json();
                            })
                            .then((data) => {
                                renderSummary(data.summary);
                                renderChart(data.chart);
                                renderTable(data.orders);
                                showContent();
                            })
                            .catch(() => {
                                showError();
                            })
                            .finally(() => setLoading(false));
                    };

                    fetchData(state.page);
                };

                const boot = () => {
                    document.querySelectorAll('[data-product-orders]:not([data-orders-ready])').forEach((container) => {
                        container.setAttribute('data-orders-ready', '1');
                        initWidget(container);
                    });
                };

                boot();
                document.addEventListener('DOMContentLoaded', boot);
                window.initProductOrdersWidgets = boot;
            })();
        </script>
    @endpush
@endif

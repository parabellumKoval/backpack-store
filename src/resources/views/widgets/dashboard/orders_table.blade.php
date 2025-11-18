@php
    $orders = collect($orders ?? []);
    $countries = collect($countries ?? []);
    $activeCountry = strtolower($activeCountry ?? 'all');
    $columnCount = 7;
@endphp

<div class="card shadow-sm h-100" id="store-orders-widget">
    <div class="card-header">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
            <div class="mb-3 mb-lg-0">
                <strong>{{ trans('backpack-store::dashboard.widgets.orders_widget_title') }}</strong>
                <div class="text-muted small">{{ trans('backpack-store::dashboard.widgets.orders_widget_subtitle') }}</div>
            </div>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Orders filters">
                <button type="button"
                        class="btn {{ $activeCountry === 'all' ? 'btn-primary' : 'btn-outline-secondary' }} mb-1"
                        data-orders-filter="all">
                    {{ trans('backpack-store::dashboard.widgets.orders_widget_all') }}
                </button>
                @foreach($countries as $country)
                    @php
                        $code = strtolower($country['code'] ?? '');
                        $isActive = $activeCountry === $code;
                    @endphp
                    <button type="button"
                            class="btn {{ $isActive ? 'btn-primary' : 'btn-outline-secondary' }} mb-1 d-flex align-items-center"
                            data-orders-filter="{{ $code }}">
                        @if(!empty($country['flag']))
                            <span class="orders-flag mr-2">{!! $country['flag'] !!}</span>
                        @endif
                        {{ $country['label'] ?? strtoupper($country['code'] ?? '') }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>
    <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead class="thead-light">
                    <tr>
                        <th style="min-width: 120px;">{{ trans('backpack-store::dashboard.widgets.orders_widget_date') }}</th>
                        <th>{{ trans('backpack-store::dashboard.widgets.orders_widget_country') }}</th>
                        <th>{{ trans('backpack-store::dashboard.widgets.orders_widget_code') }}</th>
                        <th>{{ trans('backpack-store::dashboard.widgets.orders_widget_status') }}</th>
                        <th>{{ trans('backpack-store::dashboard.widgets.orders_widget_customer') }}</th>
                        <th class="text-right">{{ trans('backpack-store::dashboard.widgets.orders_widget_total') }}</th>
                        <th class="text-right">{{ trans('backpack-store::dashboard.widgets.orders_widget_action') }}</th>
                    </tr>
                </thead>
                <tbody data-orders-table>
                    @forelse($orders as $order)
                        <tr>
                            <td>
                                <div>{{ $order['created_at'] ?? '—' }}</div>
                                @if(!empty($order['created_human']))
                                    <div class="text-muted small">{{ $order['created_human'] }}</div>
                                @endif
                            </td>
                            <td class="align-middle">
                                @if(!empty($order['country_code']))
                                    <div class="d-flex flex-column align-items-center text-center ">
                                        @if(!empty($order['country_flag']))
                                            <span class="orders-flag">{!! $order['country_flag'] !!}</span>
                                        @endif
                                        <div>
                                            <div class="text-muted small">{{ strtoupper($order['country_code']) }}</div>
                                            @if(!empty($order['country_label']))
                                                <!-- <div class="text-muted small">{{ $order['country_label'] }}</div> -->
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="font-weight-bold">#{{ $order['code'] }}</div>
                                <div class="text-muted small">ID {{ $order['id'] }}</div>
                            </td>
                            <td>
                                <span class="badge badge-pill {{ $order['status_badge_class'] ?? 'badge-secondary' }}">{{ $order['status_label'] }}</span>
                                @if(!empty($order['pay_status_label']))
                                    <div class="">
                                        <span class="badge badge-pill {{ $order['pay_status_badge_class'] ?? 'badge-light' }}">{{ $order['pay_status_label'] }}</span>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div>{{ $order['customer'] }}</div>
                                @if(!empty($order['contacts']))
                                    <div class="text-muted small">{{ $order['contacts'] }}</div>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="font-weight-bold">{{ $order['total'] }}</div>
                                <div class="text-muted small">
                                    {{ $order['items_label'] ?? trans_choice('backpack-store::dashboard.widgets.orders_widget_items', (int) ($order['items_count'] ?? 0), ['count' => (int) ($order['items_count'] ?? 0)]) }}
                                </div>
                            </td>
                            <td class="text-right">
                                <a href="{{ $order['show_url'] }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">
                                    {{ trans('backpack-store::dashboard.widgets.orders_widget_view') }}
                                </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $columnCount }}" class="text-center text-muted py-4">
                            {{ trans('backpack-store::dashboard.widgets.orders_widget_empty') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('after_styles')
    <style>
        #store-orders-widget .orders-flag {
            font-size: 1.25rem;
            line-height: 1;
        }
    </style>
@endpush

@push('after_scripts')
    <script>
        (function () {
            const widget = document.getElementById('store-orders-widget');
            if (!widget) return;

            const tableBody = widget.querySelector('[data-orders-table]');
            const buttons = widget.querySelectorAll('[data-orders-filter]');
            let currentCountry = '{{ $activeCountry }}';
            const endpoint = @json($ordersEndpoint);
            const messages = {
                loading: @json(trans('backpack-store::dashboard.widgets.orders_widget_loading')),
                empty: @json(trans('backpack-store::dashboard.widgets.orders_widget_empty')),
                error: @json(trans('backpack-store::dashboard.widgets.orders_widget_error')),
                view: @json(trans('backpack-store::dashboard.widgets.orders_widget_view')),
            };
            const columns = {{ $columnCount }};

            buttons.forEach((button) => {
                button.addEventListener('click', () => {
                    const targetCountry = button.getAttribute('data-orders-filter');
                    if (targetCountry === currentCountry) {
                        return;
                    }
                    currentCountry = targetCountry;
                    toggleActive(targetCountry);
                    fetchOrders(targetCountry);
                });
            });

            function toggleActive(code) {
                buttons.forEach((button) => {
                    const isActive = button.getAttribute('data-orders-filter') === code;
                    button.classList.toggle('btn-primary', isActive);
                    button.classList.toggle('btn-outline-secondary', !isActive);
                });
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function placeholderRow(text) {
                return `<tr><td colspan="${columns}" class="text-center text-muted py-4">${escapeHtml(text)}</td></tr>`;
            }

            function renderRows(orders) {
                if (!orders || !orders.length) {
                    return placeholderRow(messages.empty);
                }

                return orders.map((order) => {
                    const code = escapeHtml(order.code);
                    const id = escapeHtml(order.id);
                    const createdAt = escapeHtml(order.created_at);
                    const countryCode = escapeHtml(order.country_code || '');
                    const flag = order.country_flag ?? '';
                    const status = escapeHtml(order.status_label);
                    const customer = escapeHtml(order.customer);
                    const contacts = escapeHtml(order.contacts);
                    const total = escapeHtml(order.total);
                    const currency = escapeHtml(order.currency ?? '');
                    const url = escapeHtml(order.show_url);

                    return `
                        <tr>
                            <td>
                                <div>${createdAt || '—'}</div>
                                ${order.created_human ? `<div class="text-muted small">${escapeHtml(order.created_human)}</div>` : ''}
                            </td>
                            <td class="align-middle">
                                ${countryCode ? `
                                    <div class="d-flex flex-column align-items-center text-center ">
                                        ${flag ? `<span class="orders-flag">${flag}</span>` : ''}
                                        <div>
                                            <div class="text-muted small">${countryCode}</div>
                                        </div>
                                    </div>
                                ` : '<span class="text-muted">—</span>'}
                            </td>
                            <td>
                                <div class="font-weight-bold">#${code}</div>
                                <div class="text-muted small">ID ${id}</div>
                            </td>
                            <td>
                                <span class="badge badge-pill ${order.status_badge_class || 'badge-secondary'}">${status}</span>
                                ${order.pay_status_label ? `<div class=""><span class="badge badge-pill ${order.pay_status_badge_class || 'badge-light'}">${escapeHtml(order.pay_status_label)}</span></div>` : ''}
                            </td>
                            <td>
                                <div>${customer}</div>
                                ${contacts ? `<div class="text-muted small">${contacts}</div>` : ''}
                            </td>
                            <td class="text-right">
                                <div class="font-weight-bold">${total}</div>
                                <div class="text-muted small">${escapeHtml(order.items_label || '')}</div>
                            </td>
                            <td class="text-right">
                                <a href="${url}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">
                                    ${messages.view}
                                </a>
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            async function fetchOrders(country) {
                setRows(placeholderRow(messages.loading));

                try {
                    const url = new URL(endpoint, window.location.origin);
                    if (country && country !== 'all') {
                        url.searchParams.set('country', country);
                    } else {
                        url.searchParams.set('country', 'all');
                    }

                    const response = await fetch(url.toString(), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Request failed');
                    }

                    const payload = await response.json();
                    currentCountry = payload.active || 'all';
                    toggleActive(currentCountry);
                    setRows(renderRows(payload.orders || []));
                } catch (error) {
                    setRows(placeholderRow(messages.error));
                }
            }

            function setRows(html) {
                tableBody.innerHTML = html;
            }
        })();
    </script>
@endpush

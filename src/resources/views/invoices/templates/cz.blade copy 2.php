@php
    use Illuminate\Support\Arr;
    use Illuminate\Support\Carbon;

    $formatting = config('dress.invoice.formatting');

    $formatDate = function ($date) use ($formatting) {
        if (!$date) return '';
        if (!$date instanceof Carbon) $date = Carbon::parse($date);
        return $date->locale(config('dress.invoice.locale', 'cs_CZ'))
            ->translatedFormat($formatting['date'] ?? 'd. m. Y');
    };

    $formatMoney = function ($value) use ($formatting) {
        return number_format(
            (float) $value,
            $formatting['number_decimals'] ?? 2,
            $formatting['decimal_separator'] ?? ',',
            $formatting['thousands_separator'] ?? ' '
        );
    };

    $formatQuantity = function ($value) use ($formatting) {
        return number_format(
            (float) $value,
            $formatting['quantity_decimals'] ?? 2,
            $formatting['decimal_separator'] ?? ',',
            $formatting['thousands_separator'] ?? ' '
        );
    };

    $seller = Arr::get($invoice, 'seller', []);
    $buyer  = Arr::get($invoice, 'buyer', []);
    $bank   = Arr::get($invoice, 'bank', []);
    $totals = Arr::get($invoice, 'totals', []);
    $lines  = Arr::get($invoice, 'lines', []);
@endphp
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <style type="text/css">
        @page { margin: 16mm 12mm; }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111;
            font-size: 12px;
            line-height: 1.45;
            margin: 0;
            padding: 0;
        }
        .muted { color:#666; }
        .right { text-align:right; }
        .up { text-transform:uppercase; }

        /* Шапка: слева заголовки, справа большая сумма */
        .head-table {
            width:100%;
            border-collapse:separate;
            border-spacing:0;
            margin-bottom:14px;
        }
        .head-title {
            font-size:22px;
            font-weight:bold;
            text-transform:uppercase;
            margin:0 0 4px 0;
        }
        .head-sub { margin:0; }
        .amount-box {
            border:1px solid #d6d6d6;
            padding:10px 12px;
        }
        .amount-label{ font-size:12px; color:#666; margin-bottom:6px; }
        .amount-value{ font-size:22px; font-weight:bold; }

        /* Второй ряд под шапкой: три даты + опционально логотип */
        .info-table {
            width:100%;
            table-layout:fixed;
            border-collapse:separate;
            border-spacing:10px 0;
            margin-bottom:14px;
        }
        .info-cell {
            border:1px solid #d6d6d6;
            padding:10px 12px;
            vertical-align:top;
        }
        .info-title{ font-size:11px; color:#666; margin:0 0 4px 0; }
        .logo img { max-height:40px; }

        /* Три бокса: Dodavatel / Odběratel / Platební údaje */
        .grid3 {
            width:100%;
            table-layout:fixed;
            border-collapse:separate;
            border-spacing:10px 0;
            margin-bottom:14px;
        }
        .grid3-td {
            border:1px solid #d6d6d6;
            padding:12px 14px;
            vertical-align:top;
        }
        .box-title {
            margin:0 0 6px 0;
            font-size:13px;
            font-weight:bold;
            text-transform:uppercase;
            letter-spacing:.3px;
        }
        .p0 { margin:0; }

        /* Таблица позиций */
        table.items {
            width:100%;
            border-collapse:collapse;
            margin-top:6px;
        }
        .items thead th {
            font-size:11px;
            text-transform:uppercase;
            color:#666;
            border-bottom:1px solid #d6d6d6;
            padding:8px 4px;
            text-align:left;
        }
        .items tbody td {
            padding:9px 4px;
            border-bottom:1px solid #ededed;
            vertical-align:top;
        }

        /* Сводка + QR */
        .totals {
            width:100%;
            border-collapse:separate;
            border-spacing:10px 0;
            margin-top:14px;
        }
        .sum-cell {
            border:1px solid #d6d6d6;
            padding:12px 14px;
            vertical-align:top;
            width:65%;
        }
        .qr-cell {
            vertical-align:top;
            text-align:right;
            width:35%;
        }
        .sum-table {
            width:100%;
            border-collapse:collapse;
        }
        .sum-table td, .sum-table th { padding:6px 0; }
        .sum-head th {
            font-size:11px;
            text-transform:uppercase;
            color:#666;
            border-bottom:1px solid #e3e3e3;
        }
        .sum-total td {
            font-size:14px;
            font-weight:bold;
            padding-top:10px;
            border-top:1px solid #d6d6d6;
        }
        .qr-img { width:132px; height:132px; }
        .qr-note { font-size:10px; color:#666; margin-top:4px; }

        /* Низ */
        .notes { margin-top:16px; font-size:11px; color:#666; }
        .footer {
            width:100%;
            border-collapse:separate;
            border-spacing:0;
            margin-top:18px;
        }
        .sign-box {
            display:inline-block;
            vertical-align:bottom;
            text-align:center;
            margin-right:22px;
        }
        .sign-img { max-height:70px; }
        .thanks { text-align:right; font-size:11px; color:#666; }
    </style>
</head>
<body>
<div>

    <!-- ШАПКА -->
    <table class="head-table">
        <tr>
            <td style="width:60%; vertical-align:top;">
                <div class="head-title">FAKTURA {{ $invoice['invoice_number'] }}</div>
                <p class="head-sub muted">Daňový doklad</p>
                <p class="head-sub">Číslo objednávky {{ $invoice['order_number'] }}</p>
            </td>
            <td style="width:40%; vertical-align:top;">
                <div class="amount-box right">
                    <div class="amount-label">Částka k úhradě</div>
                    <div class="amount-value">
                        {{ $formatMoney($totals['grand_total'] ?? 0) }} {{ $invoice['currency'] }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- БЛОК ДАТ + ЛОГО -->
    <table class="info-table">
        <tr>
            <td class="info-cell" style="width:25%;">
                <div class="info-title">Datum vystavení</div>
                <div>{{ $formatDate($invoice['issued_at']) }}</div>
            </td>
            <td class="info-cell" style="width:25%;">
                <div class="info-title">Datum splatnosti</div>
                <div>{{ $formatDate($invoice['due_at']) }}</div>
            </td>
            <td class="info-cell" style="width:25%;">
                <div class="info-title">Datum zdan. plnění</div>
                <div>{{ $formatDate($invoice['taxed_at']) }}</div>
            </td>
            <td class="info-cell" style="width:25%; text-align:center;">
                <div class="logo">
                    @if(Arr::get($invoice, 'assets.logo'))
                        <img src="{{ Arr::get($invoice, 'assets.logo') }}" alt="Logo">
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <!-- DODAVATEL / ODBĚRATEL / PLATEBNÍ ÚDAJE -->
    <table class="grid3">
        <tr>
            <td class="grid3-td">
                <div class="box-title">Dodavatel</div>
                <p class="p0"><strong>{{ Arr::get($seller, 'name') }}</strong></p>
                <p class="p0">{{ Arr::get($seller, 'address.street') }}</p>
                <p class="p0">{{ Arr::get($seller, 'address.zip') }} {{ Arr::get($seller, 'address.city') }}</p>
                <p class="p0">{{ Arr::get($seller, 'address.country') }}</p>
                <p class="p0">IČO {{ Arr::get($seller, 'ico') }}</p>
                @if(Arr::get($seller, 'dic'))
                    <p class="p0">DIČ {{ Arr::get($seller, 'dic') }}</p>
                @endif
            </td>
            <td class="grid3-td">
                <div class="box-title">Odběratel</div>
                <p class="p0"><strong>{{ Arr::get($buyer, 'name') }}</strong></p>
                <p class="p0">{{ Arr::get($buyer, 'address.street') }}</p>
                <p class="p0">{{ Arr::get($buyer, 'address.zip') }} {{ Arr::get($buyer, 'address.city') }}</p>
                <p class="p0">{{ Arr::get($buyer, 'address.country') }}</p>
            </td>
            <td class="grid3-td">
                <div class="box-title">Platební údaje</div>
                @if(Arr::get($bank, 'bank_name'))
                    <p class="p0"><strong>{{ Arr::get($bank, 'bank_name') }}</strong></p>
                @endif
                @if(Arr::get($bank, 'account_display'))
                    <p class="p0">Bankovní účet {{ Arr::get($bank, 'account_display') }}</p>
                @endif
                @if(Arr::get($bank, 'iban'))
                    <p class="p0">IBAN {{ Arr::get($bank, 'iban') }}</p>
                @endif
                @if(Arr::get($bank, 'bic'))
                    <p class="p0">BIC/SWIFT {{ Arr::get($bank, 'bic') }}</p>
                @endif
                <p class="p0">Variabilní symbol {{ Arr::get($invoice, 'qr_payload.variable_symbol') }}</p>
                @if(Arr::get($invoice, 'payment.method'))
                    <p class="p0">Způsob platby {{ Arr::get($invoice, 'payment.method') }}</p>
                @endif
            </td>
        </tr>
    </table>

    <!-- ПОЗИЦИИ -->
    <table class="items">
        <thead>
        <tr>
            <th style="width:26px;">#</th>
            <th>Položka</th>
            <th class="right" style="width:70px;">Množství</th>
            <th class="right" style="width:56px;">MJ</th>
            <th class="right" style="width:95px;">Cena za MJ</th>
            <th class="right" style="width:60px;">DPH</th>
            <th class="right" style="width:115px;">Celkem bez DPH</th>
        </tr>
        </thead>
        <tbody>
        @foreach($lines as $line)
            <tr>
                <td>{{ $line['position'] }}</td>
                <td>{{ $line['name'] }}</td>
                <td class="right">{{ $formatQuantity($line['quantity']) }}</td>
                <td class="right">{{ $line['unit'] }}</td>
                <td class="right">{{ $formatMoney($line['unit_price']) }} {{ $invoice['currency'] }}</td>
                <td class="right">{{ (int) $line['vat_rate'] }} %</td>
                <td class="right">{{ $formatMoney($line['total_ex_vat']) }} {{ $invoice['currency'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <!-- ИТОГИ + QR -->
    <table class="totals">
        <tr>
            <td class="sum-cell">
                <table class="sum-table">
                    <tr class="sum-head">
                        <th style="text-align:left; width:20%;">Sazba</th>
                        <th class="right" style="width:40%;">Základ</th>
                        <th class="right" style="width:40%;">DPH</th>
                    </tr>
                    @if(!empty($totals['by_vat_rate']))
                        @foreach($totals['by_vat_rate'] as $rate => $group)
                            <tr>
                                <td>{{ $group['rate'] }} %</td>
                                <td class="right">{{ $formatMoney($group['base']) }} {{ $invoice['currency'] }}</td>
                                <td class="right">{{ $formatMoney($group['vat']) }} {{ $invoice['currency'] }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td>0 %</td>
                            <td class="right">{{ $formatMoney($totals['without_vat'] ?? 0) }} {{ $invoice['currency'] }}</td>
                            <td class="right">{{ $formatMoney($totals['vat'] ?? 0) }} {{ $invoice['currency'] }}</td>
                        </tr>
                    @endif
                    <tr class="sum-total">
                        <td class="right" colspan="2">Cena celkem</td>
                        <td class="right">{{ $formatMoney($totals['grand_total'] ?? 0) }} {{ $invoice['currency'] }}</td>
                    </tr>
                </table>
                <div class="muted" style="margin-top:8px;">Děkujeme</div>
            </td>
            <td class="qr-cell">
                @if(Arr::get($invoice, 'qr.data_url'))
                    <img class="qr-img" src="{{ Arr::get($invoice, 'qr.data_url') }}" alt="QR platba">
                    <div class="qr-note">QR platba</div>
                @endif
            </td>
        </tr>
    </table>

    <!-- ПРИМЕЧАНИЯ / ПОДПИСИ -->
    @if(!empty($invoice['notes']))
        <div class="notes">
            @foreach($invoice['notes'] as $note)
                <div>{{ $note }}</div>
            @endforeach
        </div>
    @endif

    <table class="footer">
        <tr>
            <td style="vertical-align:bottom;">
                @if(Arr::get($invoice, 'assets.signature'))
                    <div class="sign-box">
                        <img class="sign-img" src="{{ Arr::get($invoice, 'assets.signature') }}" alt="Podpis">
                        <div>Podpis</div>
                    </div>
                @endif
                @if(Arr::get($invoice, 'assets.stamp'))
                    <div class="sign-box">
                        <img class="sign-img" src="{{ Arr::get($invoice, 'assets.stamp') }}" alt="Razítko">
                        <div>Razítko</div>
                    </div>
                @endif
            </td>
            <td class="thanks"> </td>
        </tr>
    </table>

</div>
</body>
</html>

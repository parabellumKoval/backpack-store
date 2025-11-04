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

    $countryCode = Arr::get($buyer, 'address.country');
    $country = \Store::countryLabel($countryCode);
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
            margin-bottom: 120px;
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
            font-size:30px;
            font-weight:bold;
            text-transform:uppercase;
        }
        .head-sub {
            display: inline-block;
            margin:0 0 0 15px;
        }
        .head-cell {
            padding: 0 10px 20px 10px;
        }
        .head-logo-cell {
            width:40%; 
            vertical-align:middle;
        }
        .head-info-cell {
            text-align: right;
            width:60%; 
            vertical-align:middle;
        }

        .logo img { max-height:40px; }

        /* Второй ряд */
        .info-table {
            width:100%;
            table-layout:fixed;
            border-collapse:separate;
            border-spacing: 0;
            background-color: #595959;
            color: #ffffff;
        }
        .info-cell {
            border: 0;
            vertical-align: top;
            padding: 30px;
        }

        .amount-box {}
        .amount-label{}
        .amount-value{ font-size:30px; font-weight:bold; line-height: 30px;}

        .bank-info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .bank-info-table td:first-child {
            text-align: right;
            padding: 2px 8px 2px 0;
            white-space: nowrap;
        }
        .bank-info-table td:last-child {
            text-align: left;
            padding: 2px 0 2px 8px;
        }

        /* Третий ряд под шапкой: три даты */
        .date-table {
            width:100%;
            table-layout:fixed;
            border-collapse:separate;
            border-spacing: 0;
            background-color: #6a6a6a;
            color: #ffffff;
        }
        .date-cell {
            border-top:1px solid #d6d6d6;
            border-right:1px solid #d6d6d6;
            padding:15px;
            vertical-align:top;
            font-size:11px;
        }
        .date-title{}
        .date-date{ font-weight: 600; }

        /* QR */
        .qr-table {
            width:100%;
            table-layout:fixed;
            border-collapse:separate;
            border-spacing: 0;
            margin-bottom: 70px;
            background-color: #eeeeee;
        }

        .qr-cell {
            vertical-align:top;
            width:35%;
            padding: 30px;
        }

        .qr-image-cell {
            text-align: right;
        }
        .qr-image-block {
            margin-bottom: -90px;
        }

        /* Три бокса: Dodavatel / Odběratel / Platební údaje */
        /* .grid3 {
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
        } */
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
            width: 50%;
            border-collapse:separate;
            border-spacing:10px 0;
            margin-top:14px;
            margin-left: auto;
        }
        .sum-cell {
            padding:12px 14px;
            vertical-align:top;
            width:65%;
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
            position: fixed;
            bottom: 100px;
            left: 0;
            right: 0;
            width:100%;
            border-collapse:separate;
            border-spacing:0;
            margin-top:18px;
        }
        .thank-cell {
            width: 70%;
        }
        .thank-title {
            font-size: 18px;
        }
        .seller-info {
            width: 100%;
        }
        .seller-address {
            float: left;
            width: 60%;
        }
        .seller-ids {
            float: right;
            width: 40%;
            white-space: nowrap;
        }
        .sign-box {
            display:inline-block;
            vertical-align:bottom;
            text-align:center;
            margin-bottom: -40px;
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
            <td class="head-cell head-logo-cell">
                <div class="logo">
                    @if(Arr::get($invoice, 'assets.logo'))
                        <img src="{{ Arr::get($invoice, 'assets.logo') }}" alt="Logo">
                    @endif
                </div>
            </td>
            <td class="head-cell head-info-cell">
                <div class="head-title">FAKTURA {{ $invoice['invoice_number'] }}</div>
                <p class="head-sub muted">Daňový doklad</p>
                <p class="head-sub"><span class="muted">Číslo objednávky</span> {{ $invoice['order_number'] }}</p>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="info-cell">
                <div class="amount-box">
                    <div class="amount-label">Částka k úhradě</div>
                    <div class="amount-value">
                        {{ $formatMoney($totals['grand_total'] ?? 0) }} {{ $invoice['currency'] }}
                    </div>
                </div>
            </td>
            <td class="info-cell">
                <table class="bank-info-table">
                    @if(Arr::get($bank, 'bank_name'))
                        <tr>
                            <td>Banka:</td>
                            <td><strong>{{ Arr::get($bank, 'bank_name') }}</strong></td>
                        </tr>
                    @endif
                    @if(Arr::get($bank, 'account_display'))
                        <tr>
                            <td>Bankovní účet:</td>
                            <td>{{ Arr::get($bank, 'account_display') }}</td>
                        </tr>
                    @endif
                    @if(Arr::get($bank, 'iban'))
                        <tr>
                            <td>IBAN:</td>
                            <td>{{ Arr::get($bank, 'iban') }}</td>
                        </tr>
                    @endif
                    @if(Arr::get($bank, 'bic'))
                        <tr>
                            <td>BIC/SWIFT:</td>
                            <td>{{ Arr::get($bank, 'bic') }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td>Variabilní symbol:</td>
                        <td>{{ Arr::get($invoice, 'qr_payload.variable_symbol') }}</td>
                    </tr>
                    @if(Arr::get($invoice, 'payment.method'))
                        <tr>
                            <td>Způsob platby:</td>
                            <td>{{ Arr::get($invoice, 'payment.method') }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <!-- БЛОК ДАТ + ЛОГО -->
    <table class="date-table">
        <tr>
            <td class="date-cell" style="width:33%;">
                <div class="date-title">Datum vystavení</div>
                <div class="date-date">{{ $formatDate($invoice['issued_at']) }}</div>
            </td>
            <td class="date-cell" style="width:33%;">
                <div class="date-title">Datum splatnosti</div>
                <div class="date-date">{{ $formatDate($invoice['due_at']) }}</div>
            </td>
            <td class="date-cell" style="width:33%;">
                <div class="date-title">Datum zdan. plnění</div>
                <div class="date-date">{{ $formatDate($invoice['taxed_at']) }}</div>
            </td>
        </tr>
    </table>

    <!-- DODAVATEL / ODBĚRATEL / PLATEBNÍ ÚDAJE -->
    <table class="qr-table">
        <tr>
            <td class="qr-cell">
                <div class="box-title">Odběratel</div>
                <p class="p0"><strong>{{ Arr::get($buyer, 'name') }}</strong></p>
                <p class="p0"><strong>{{ Arr::get($buyer, 'contacts.phone') }}</strong></p>
                <p class="p0">{{ Arr::get($buyer, 'address.street') }}</p>
                <p class="p0">{{ Arr::get($buyer, 'address.zip') }} {{ Arr::get($buyer, 'address.city') }}</p>
                <p class="p0">{{ $country }}</p>
            </td>
            <td class="qr-cell qr-image-cell">
                @if(Arr::get($invoice, 'qr.data_url'))
                    <div class="qr-image-block">
                        <img class="qr-img" src="{{ Arr::get($invoice, 'qr.data_url') }}" alt="QR platba">
                        <div class="qr-note">QR platba</div>
                    </div>
                @endif
            </td
        </tr>
    </table>
    <!-- <table class="grid3">
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
    </table> -->

    <!-- ПОЗИЦИИ -->
    <table class="items">
        <thead>
        <tr>
            <th style="width:26px;"></th>
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
                        <td class="right" colspan="2"></td>
                        <td class="right">{{ $formatMoney($totals['grand_total'] ?? 0) }} {{ $invoice['currency'] }}</td>
                    </tr>
                </table>
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
            <td class="thank-cell">
                <div class="thank-title muted">Děkujeme</div>
                <p class="p0"><strong>{{ Arr::get($seller, 'name') }}</strong></p>
                <div class="seller-info">
                    <div class="seller-address">
                        <p class="p0">{{ Arr::get($seller, 'address.street') }}</p>
                        <p class="p0">{{ Arr::get($seller, 'address.zip') }} {{ Arr::get($seller, 'address.city') }}, {{ Arr::get($seller, 'address.country') }}</p>
                    </div>
                    <div class="seller-ids">
                        <p class="p0">IČO {{ Arr::get($seller, 'ico') }}</p>
                        @if(Arr::get($seller, 'dic'))
                            <p class="p0">DIČ {{ Arr::get($seller, 'dic') }}</p>
                        @endif
                    </div>
                </div>
            </td>
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
                    </div>
                @endif
            </td>
        </tr>
    </table>

</div>
</body>
</html>

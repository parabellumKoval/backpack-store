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
        @page { margin: 18mm 12mm; }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }

        .document { width: 100%; }

        /* Заголовок на таблицах: 3 колонки (титул, лого, мета) */
        .heading-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 18px;
        }
        .heading-title {
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .heading-meta {
            text-align: right;
            font-size: 12px;
        }
        .logo img {
            max-width: 160px;
            height: auto;
        }

        /* Сетка из 3-х блоков (поставщик/покупатель/банк) — таблица с отступами */
        .grid-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 18px 0; /* имитация gap по горизонтали */
            margin-bottom: 18px;
        }
        .grid-td {
            border: 1px solid #d6d6d6;
            padding: 12px 14px;
            vertical-align: top;
        }
        .box-title {
            margin: 0 0 6px 0;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .p0 { margin: 0; }

        /* Таблица позиций */
        table.items {
            width: 100%;
            border-collapse: collapse;
        }
        .items thead th {
            font-size: 11px;
            text-transform: uppercase;
            color: #666;
            border-bottom: 1px solid #d6d6d6;
            padding: 8px 4px;
            text-align: left;
        }
        .items tbody td {
            padding: 10px 4px;
            border-bottom: 1px solid #ededed;
            vertical-align: top;
        }
        .right { text-align: right; }

        /* Блок итогов + QR — таблица из 2-х колонок */
        .totals-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 18px 0;
            margin-top: 18px;
        }
        .summary-cell {
            border: 1px solid #d6d6d6;
            padding: 12px 14px;
            vertical-align: top;
            width: 65%;
        }
        .qr-cell {
            vertical-align: top;
            text-align: right;
            width: 35%;
        }
        .summary-inner {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-inner td {
            padding: 6px 0;
        }
        .summary-total td {
            font-size: 15px;
            font-weight: bold;
            padding-top: 10px;
        }
        .qr-img {
            width: 140px;
            height: 140px;
        }
        .qr-caption {
            font-size: 10px;
            color: #666;
            margin-top: 6px;
        }

        .notes {
            margin-top: 24px;
            font-size: 11px;
            color: #666;
        }

        /* Подвал: подписи/печать — таблица, чтобы выровнять по низу */
        .footer-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 36px;
        }
        .signatures-cell { vertical-align: bottom; }
        .signature-box {
            text-align: center;
            display: inline-block; /* работает в dompdf */
            vertical-align: bottom;
            margin-right: 24px;
        }
        .signature-img {
            max-height: 80px;
        }
        .thanks-cell {
            text-align: right;
            vertical-align: bottom;
            font-size: 11px;
            color: #666;
        }
    </style>
</head>
<body>
<div class="document">

    <!-- Заголовок -->
    <table class="heading-table">
        <tr>
            <td style="width: 45%; vertical-align: top;">
                <div class="heading-title">Faktura</div>
                <div>Daňový doklad č. {{ $invoice['invoice_number'] }}</div>
                <div>Číslo objednávky: {{ $invoice['order_number'] }}</div>
            </td>
            <td style="width: 25%; vertical-align: top; text-align: center;">
                <div class="logo">
                    @if(Arr::get($invoice, 'assets.logo'))
                        <img src="{{ Arr::get($invoice, 'assets.logo') }}" alt="Logo">
                    @endif
                </div>
            </td>
            <td style="width: 30%; vertical-align: top;">
                <div class="heading-meta">
                    <div>Vystaveno: {{ $formatDate($invoice['issued_at']) }}</div>
                    <div>Splatnost: {{ $formatDate($invoice['due_at']) }}</div>
                    <div>Zdan. plnění: {{ $formatDate($invoice['taxed_at']) }}</div>
                    <div>Měna: {{ $invoice['currency'] }}</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Поставщик / Покупатель / Банк -->
    <table class="grid-table">
        <tr>
            <td class="grid-td">
                <h3 class="box-title">Dodavatel</h3>
                <p class="p0"><strong>{{ Arr::get($seller, 'name') }}</strong></p>
                <p class="p0">{{ Arr::get($seller, 'address.street') }}</p>
                <p class="p0">{{ Arr::get($seller, 'address.zip') }} {{ Arr::get($seller, 'address.city') }}</p>
                <p class="p0">{{ Arr::get($seller, 'address.country') }}</p>
                <p class="p0">IČO: {{ Arr::get($seller, 'ico') }}</p>
                <p class="p0">DIČ: {{ Arr::get($seller, 'dic') }}</p>
                @if(Arr::get($seller, 'contacts.email'))
                    <p class="p0">E-mail: {{ Arr::get($seller, 'contacts.email') }}</p>
                @endif
                @if(Arr::get($seller, 'contacts.phone'))
                    <p class="p0">Tel: {{ Arr::get($seller, 'contacts.phone') }}</p>
                @endif
            </td>
            <td class="grid-td">
                <h3 class="box-title">Odběratel</h3>
                <p class="p0"><strong>{{ Arr::get($buyer, 'name') }}</strong></p>
                <p class="p0">{{ Arr::get($buyer, 'address.street') }}</p>
                <p class="p0">{{ Arr::get($buyer, 'address.zip') }} {{ Arr::get($buyer, 'address.city') }}</p>
                <p class="p0">{{ Arr::get($buyer, 'address.country') }}</p>
                @if(Arr::get($buyer, 'ico'))
                    <p class="p0">IČO: {{ Arr::get($buyer, 'ico') }}</p>
                @endif
                @if(Arr::get($buyer, 'dic'))
                    <p class="p0">DIČ: {{ Arr::get($buyer, 'dic') }}</p>
                @endif
                @if(Arr::get($buyer, 'contacts.email'))
                    <p class="p0">E-mail: {{ Arr::get($buyer, 'contacts.email') }}</p>
                @endif
            </td>
            <td class="grid-td">
                <h3 class="box-title">Platební údaje</h3>
                <p class="p0"><strong>{{ Arr::get($bank, 'bank_name') }}</strong></p>
                <p class="p0">IBAN: {{ Arr::get($bank, 'iban') }}</p>
                @if(Arr::get($bank, 'account_display'))
                    <p class="p0">Číslo účtu: {{ Arr::get($bank, 'account_display') }}</p>
                @endif
                @if(Arr::get($bank, 'bic'))
                    <p class="p0">BIC/SWIFT: {{ Arr::get($bank, 'bic') }}</p>
                @endif
                <p class="p0">Variabilní symbol: {{ Arr::get($invoice, 'qr_payload.variable_symbol') }}</p>
            </td>
        </tr>
    </table>

    <!-- Таблица позиций -->
    <table class="items">
        <thead>
            <tr>
                <th style="width:30px;">#</th>
                <th>Položka</th>
                <th class="right" style="width:80px;">Množství</th>
                <th class="right" style="width:60px;">MJ</th>
                <th class="right" style="width:90px;">Cena bez DPH</th>
                <th class="right" style="width:60px;">DPH %</th>
                <th class="right" style="width:110px;">Celkem bez DPH</th>
            </tr>
        </thead>
        <tbody>
        @foreach($lines as $line)
            <tr>
                <td>{{ $line['position'] }}</td>
                <td>{{ $line['name'] }}</td>
                <td class="right">{{ $formatQuantity($line['quantity']) }}</td>
                <td class="right">{{ $line['unit'] }}</td>
                <td class="right">{{ $formatMoney($line['unit_price']) }}</td>
                <td class="right">{{ $formatMoney($line['vat_rate']) }}</td>
                <td class="right">{{ $formatMoney($line['total_ex_vat']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <!-- Итоги + QR -->
    <table class="totals-table">
        <tr>
            <td class="summary-cell">
                <table class="summary-inner">
                    <tr>
                        <td>Základ bez DPH</td>
                        <td class="right">{{ $formatMoney($totals['without_vat'] ?? 0) }} {{ $invoice['currency'] }}</td>
                    </tr>
                    <tr>
                        <td>DPH celkem</td>
                        <td class="right">{{ $formatMoney($totals['vat'] ?? 0) }} {{ $invoice['currency'] }}</td>
                    </tr>
                    <tr class="summary-total">
                        <td>Částka k úhradě</td>
                        <td class="right">{{ $formatMoney($totals['grand_total'] ?? 0) }} {{ $invoice['currency'] }}</td>
                    </tr>
                </table>

                @if(!empty($totals['by_vat_rate']))
                    <table class="summary-inner" style="margin-top:12px;">
                        <thead>
                        <tr>
                            <th style="text-align:left;">DPH %</th>
                            <th class="right">Základ</th>
                            <th class="right">DPH</th>
                            <th class="right">Celkem</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($totals['by_vat_rate'] as $rate => $group)
                            <tr>
                                <td>{{ $group['rate'] }}</td>
                                <td class="right">{{ $formatMoney($group['base']) }}</td>
                                <td class="right">{{ $formatMoney($group['vat']) }}</td>
                                <td class="right">{{ $formatMoney($group['total']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </td>
            <td class="qr-cell">
                @if(Arr::get($invoice, 'qr.data_url'))
                    <img class="qr-img" src="{{ Arr::get($invoice, 'qr.data_url') }}" alt="QR platba">
                    <div class="qr-caption">QR platba</div>
                @endif
            </td>
        </tr>
    </table>

    <!-- Примечания -->
    @if(!empty($invoice['notes']))
        <div class="notes">
            @foreach($invoice['notes'] as $note)
                <div>{{ $note }}</div>
            @endforeach
        </div>
    @endif

    <!-- Подписи / Печать / Спасибо -->
    <table class="footer-table">
        <tr>
            <td class="signatures-cell">
                @if(Arr::get($invoice, 'assets.signature'))
                    <div class="signature-box">
                        <img class="signature-img" src="{{ Arr::get($invoice, 'assets.signature') }}" alt="Podpis">
                        <div>Podpis</div>
                    </div>
                @endif
                @if(Arr::get($invoice, 'assets.stamp'))
                    <div class="signature-box">
                        <img class="signature-img" src="{{ Arr::get($invoice, 'assets.stamp') }}" alt="Razítko">
                        <div>Razítko</div>
                    </div>
                @endif
            </td>
            <td class="thanks-cell">
                Děkujeme za váš nákup!
            </td>
        </tr>
    </table>

</div>
</body>
</html>

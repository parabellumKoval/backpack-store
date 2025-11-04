@php
    use Illuminate\Support\Arr;
    use Illuminate\Support\Carbon;

    $formatting = config('dress.invoice.formatting');

    $formatDate = function ($date) use ($formatting) {
        if (!$date) {
            return '';
        }

        if (!$date instanceof Carbon) {
            $date = Carbon::parse($date);
        }

        return $date->locale(config('dress.invoice.locale', 'cs_CZ'))->translatedFormat($formatting['date'] ?? 'd. m. Y');
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
    $buyer = Arr::get($invoice, 'buyer', []);
    $bank = Arr::get($invoice, 'bank', []);
    $totals = Arr::get($invoice, 'totals', []);
    $lines = Arr::get($invoice, 'lines', []);
@endphp
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 18mm 12mm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111;
            font-size: 12px;
            line-height: 1.4;
        }
        .document {
            width: 100%;
        }
        .heading {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 18px;
        }
        .heading .title {
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .heading .meta {
            text-align: right;
            font-size: 12px;
        }
        .logo img {
            max-width: 160px;
            height: auto;
        }
        .grid {
            display: flex;
            gap: 18px;
            margin-bottom: 18px;
        }
        .grid .box {
            flex: 1;
            border: 1px solid #d6d6d6;
            padding: 12px 14px;
        }
        .box h3 {
            margin: 0 0 6px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .box p {
            margin: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table thead th {
            font-size: 11px;
            text-transform: uppercase;
            color: #666;
            border-bottom: 1px solid #d6d6d6;
            padding: 8px 4px;
            text-align: left;
        }
        table tbody td {
            padding: 10px 4px;
            border-bottom: 1px solid #ededed;
        }
        table tbody tr:last-child td {
            border-bottom: none;
        }
        .right {
            text-align: right;
        }
        .totals {
            margin-top: 18px;
            display: flex;
            gap: 18px;
        }
        .totals .summary {
            flex: 1;
            border: 1px solid #d6d6d6;
            padding: 12px 14px;
        }
        .summary table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary td {
            padding: 6px 0;
        }
        .summary tr.total td {
            font-size: 15px;
            font-weight: bold;
            padding-top: 10px;
        }
        .notes {
            margin-top: 24px;
            font-size: 11px;
            color: #666;
        }
        .footer {
            margin-top: 36px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .footer .signatures {
            display: flex;
            gap: 24px;
        }
        .signature-block {
            text-align: center;
        }
        .signature-block img {
            max-height: 80px;
        }
        .qr {
            text-align: right;
        }
        .qr img, .qr svg {
            width: 140px;
            height: 140px;
        }
    </style>
</head>
<body>
    <div class="document">
        <div class="heading">
            <div>
                <div class="title">Faktura</div>
                <div>Daňový doklad č. {{ $invoice['invoice_number'] }}</div>
                <div>Číslo objednávky: {{ $invoice['order_number'] }}</div>
            </div>
            <div class="logo">
                @if(Arr::get($invoice, 'assets.logo'))
                    <img src="{{ Arr::get($invoice, 'assets.logo') }}" alt="Logo">
                @endif
            </div>
            <div class="meta">
                <div>Vystaveno: {{ $formatDate($invoice['issued_at']) }}</div>
                <div>Splatnost: {{ $formatDate($invoice['due_at']) }}</div>
                <div>Zdan. plnění: {{ $formatDate($invoice['taxed_at']) }}</div>
                <div>Měna: {{ $invoice['currency'] }}</div>
            </div>
        </div>

        <div class="grid">
            <div class="box">
                <h3>Dodavatel</h3>
                <p><strong>{{ Arr::get($seller, 'name') }}</strong></p>
                <p>{{ Arr::get($seller, 'address.street') }}</p>
                <p>{{ Arr::get($seller, 'address.zip') }} {{ Arr::get($seller, 'address.city') }}</p>
                <p>{{ Arr::get($seller, 'address.country') }}</p>
                <p>IČO: {{ Arr::get($seller, 'ico') }}</p>
                <p>DIČ: {{ Arr::get($seller, 'dic') }}</p>
                @if(Arr::get($seller, 'contacts.email'))
                    <p>E-mail: {{ Arr::get($seller, 'contacts.email') }}</p>
                @endif
                @if(Arr::get($seller, 'contacts.phone'))
                    <p>Tel: {{ Arr::get($seller, 'contacts.phone') }}</p>
                @endif
            </div>
            <div class="box">
                <h3>Odběratel</h3>
                <p><strong>{{ Arr::get($buyer, 'name') }}</strong></p>
                <p>{{ Arr::get($buyer, 'address.street') }}</p>
                <p>{{ Arr::get($buyer, 'address.zip') }} {{ Arr::get($buyer, 'address.city') }}</p>
                <p>{{ Arr::get($buyer, 'address.country') }}</p>
                @if(Arr::get($buyer, 'ico'))
                    <p>IČO: {{ Arr::get($buyer, 'ico') }}</p>
                @endif
                @if(Arr::get($buyer, 'dic'))
                    <p>DIČ: {{ Arr::get($buyer, 'dic') }}</p>
                @endif
                @if(Arr::get($buyer, 'contacts.email'))
                    <p>E-mail: {{ Arr::get($buyer, 'contacts.email') }}</p>
                @endif
            </div>
            <div class="box">
                <h3>Platební údaje</h3>
                <p><strong>{{ Arr::get($bank, 'bank_name') }}</strong></p>
                <p>IBAN: {{ Arr::get($bank, 'iban') }}</p>
                @if(Arr::get($bank, 'account_display'))
                    <p>Číslo účtu: {{ Arr::get($bank, 'account_display') }}</p>
                @endif
                @if(Arr::get($bank, 'bic'))
                    <p>BIC/SWIFT: {{ Arr::get($bank, 'bic') }}</p>
                @endif
                <p>Variabilní symbol: {{ Arr::get($invoice, 'qr_payload.variable_symbol') }}</p>
            </div>
        </div>

        <table>
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

        <div class="totals">
            <div class="summary">
                <table>
                    <tr>
                        <td>Základ bez DPH</td>
                        <td class="right">{{ $formatMoney($totals['without_vat'] ?? 0) }} {{ $invoice['currency'] }}</td>
                    </tr>
                    <tr>
                        <td>DPH celkem</td>
                        <td class="right">{{ $formatMoney($totals['vat'] ?? 0) }} {{ $invoice['currency'] }}</td>
                    </tr>
                    <tr class="total">
                        <td>Částka k úhradě</td>
                        <td class="right">{{ $formatMoney($totals['grand_total'] ?? 0) }} {{ $invoice['currency'] }}</td>
                    </tr>
                </table>

                @if(!empty($totals['by_vat_rate']))
                    <table style="margin-top:12px;">
                        <thead>
                        <tr>
                            <th>DPH %</th>
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
            </div>
            <div class="qr">
                @if(Arr::get($invoice, 'qr.format') === 'svg' && Arr::get($invoice, 'qr.inline_svg'))
                    {!! $invoice['qr']['inline_svg'] !!}
                @else
                    <img src="{{ Arr::get($invoice, 'qr.data_url') }}" alt="QR platba">
                @endif
                <div style="font-size:10px; margin-top:6px; color:#666;">QR platba</div>
            </div>
        </div>

        @if(!empty($invoice['notes']))
            <div class="notes">
                @foreach($invoice['notes'] as $note)
                    <div>{{ $note }}</div>
                @endforeach
            </div>
        @endif

        <div class="footer">
            <div class="signatures">
                @if(Arr::get($invoice, 'assets.signature'))
                    <div class="signature-block">
                        <img src="{{ Arr::get($invoice, 'assets.signature') }}" alt="Podpis">
                        <div>Podpis</div>
                    </div>
                @endif
                @if(Arr::get($invoice, 'assets.stamp'))
                    <div class="signature-block">
                        <img src="{{ Arr::get($invoice, 'assets.stamp') }}" alt="Razítko">
                        <div>Razítko</div>
                    </div>
                @endif
            </div>
            <div style="font-size:11px; color:#666;">
                Děkujeme za váš nákup!
            </div>
        </div>
    </div>
</body>
</html>

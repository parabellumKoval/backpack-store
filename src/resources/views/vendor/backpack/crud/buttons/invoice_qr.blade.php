@if(method_exists($entry, 'requiresInvoice') && $entry->requiresInvoice())
    <a href="{{ url('api/store/invoices/'.$entry->getKey().'/qr') }}" target="_blank" class="btn btn-sm btn-outline-info" title="{{ __('Показать QR для оплаты') }}">
        <i class="la la-qrcode"></i>
    </a>
@endif

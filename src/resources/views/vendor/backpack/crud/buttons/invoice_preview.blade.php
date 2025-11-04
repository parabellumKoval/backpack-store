@if(method_exists($entry, 'requiresInvoice') && $entry->requiresInvoice())
    <a href="{{ url('api/store/invoices/'.$entry->getKey()) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="{{ __('Просмотр счета PDF') }}">
        <i class="la la-file-pdf"></i> PDF
    </a>
@endif

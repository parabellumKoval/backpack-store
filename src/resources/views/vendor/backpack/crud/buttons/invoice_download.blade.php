@if(method_exists($entry, 'requiresInvoice') && $entry->requiresInvoice())
    <a href="{{ url('api/store/invoices/'.$entry->getKey().'/download') }}" class="btn btn-sm btn-outline-success" title="{{ __('Скачать PDF счет') }}">
        <i class="la la-cloud-download"></i>
    </a>
@endif

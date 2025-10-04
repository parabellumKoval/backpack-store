@php
    $route = route('backpack.store.currency-rates.refresh');
@endphp

<form method="POST" action="{{ $route }}" style="display:inline" id="refresh-rates-form">
    @csrf
</form>

<a href="javascript:void(0)" onclick="confirmRefresh()" class="btn btn-primary">
    <i class="la la-dollar-sign"></i>
    {{ trans('backpack-store::currency-rates.refresh_now') }}
</a>

@push('after_scripts')
<script>
    function confirmRefresh() {
        swal({
            title: "{{ trans('backpack-store::currency-rates.refresh_now') }}",
            text: "{!! trans('backpack-store::currency-rates.refresh_confirm') !!}",
            icon: "info",
            buttons: {
                cancel: {
                    text: "{!! trans('backpack::base.cancel') !!}",
                    value: null,
                    visible: true,
                    className: "bg-secondary",
                    closeModal: true,
                },
                confirm: {
                    text: "{!! trans('backpack::crud.yes') !!}",
                    value: true,
                    visible: true,
                    className: "bg-primary",
                }
            },
        }).then((value) => {
            if (value) {
                document.getElementById('refresh-rates-form').submit();
            }
        });
    }
</script>
@endpush

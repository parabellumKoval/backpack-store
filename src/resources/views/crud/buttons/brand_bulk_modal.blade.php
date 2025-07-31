<!-- Модальное окно для выбора бренда -->
<div class="modal fade" id="brandModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ trans('backpack-store::bulk_actions.select_brand') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <select name="brand_id" id="brand_id" class="form-control select2">
                    <option value="">{{ trans('backpack-store::bulk_actions.select_brand') }}</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ trans('backpack-store::bulk_actions.cancel') }}</button>
                <button type="button" class="btn btn-primary" id="confirmBrand">{{ trans('backpack-store::bulk_actions.confirm') }}</button>
            </div>
        </div>
    </div>
</div>

@push('after_styles')
<style>
    #brandModal {
        z-index: 1050 !important;
    }
    #brandModal .select2-container {
        z-index: 1051 !important;
        width: 100% !important;
    }
</style>
@endpush

@push('after_scripts')
<script>
$(document).ready(function() {
    // Move modal to body
    $('#brandModal').appendTo('body');

    // Initialize brand select2
    $('#brand_id').select2({
        dropdownParent: $('#brandModal'),
        placeholder: "{{ trans('backpack-store::bulk_actions.select_brand') }}"
    });

    // Export function to global scope
    window.showBrandModal = function() {
        $('#brand_id').val(null).trigger('change');
        $('#brandModal').modal('show');
    };

    // Handle brand bulk action
    $('#confirmBrand').click(function() {
        var selectedId = $('#brand_id').val();
        
        $.ajax({
            url: '{{ url($crud->route . "/bulk-action/set_brand") }}',
            type: 'POST',
            data: {
                ids: crud.checkedItems,
                brand_id: selectedId || null, // отправляем null если значение не выбрано
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    new Noty({
                        type: "success",
                        text: response.message
                    }).show();
                    
                    crud.checkedItems = [];
                    $("input.crud_bulk_actions_row_checkbox").prop('checked', false);
                    $("input.crud_bulk_actions_main_checkbox").prop('checked', false);
                    crud.table.draw(false);
                } else {
                    new Noty({
                        type: "error",
                        text: response.message
                    }).show();
                }
                $('#brandModal').modal('hide');
            },
            error: function() {
                new Noty({
                    type: "error",
                    text: "{{ trans('backpack-store::bulk_actions.error_occurred') }}"
                }).show();
                $('#brandModal').modal('hide');
            }
        });
    });
});
</script>
@endpush

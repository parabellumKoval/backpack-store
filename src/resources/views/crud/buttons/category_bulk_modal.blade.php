<!-- Модальное окно для выбора категорий -->
<div class="modal fade" id="categoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ trans('backpack-store::bulk_actions.select_categories') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <select name="category_ids[]" id="category_ids" class="form-control select2" multiple>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ trans('backpack-store::bulk_actions.cancel') }}</button>
                <button type="button" class="btn btn-primary" id="confirmCategories">{{ trans('backpack-store::bulk_actions.confirm') }}</button>
            </div>
        </div>
    </div>
</div>

@push('after_styles')
<style>
    #categoryModal {
        z-index: 1050 !important;
    }
    #categoryModal .select2-container {
        z-index: 1051 !important;
        width: 100% !important;
    }
</style>
@endpush

@push('after_scripts')
<script>
$(document).ready(function() {
    // Move modal to body
    $('#categoryModal').appendTo('body');

    // Initialize categories select2
    $('#category_ids').select2({
        dropdownParent: $('#categoryModal'),
        multiple: true,
        placeholder: "{{ trans('backpack-store::bulk_actions.select_categories') }}"
    });

    // Export function to global scope
    window.showCategoryModal = function() {
        $('#category_ids').val(null).trigger('change');
        $('#categoryModal').modal('show');
    };

    // Handle category bulk action
    $('#confirmCategories').click(function() {
        var selectedIds = $('#category_ids').val();
        
        $.ajax({
            url: '{{ url($crud->route . "/bulk-action/set_category") }}',
            type: 'POST',
            data: {
                ids: crud.checkedItems,
                category_ids: selectedIds,
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
                $('#categoryModal').modal('hide');
            },
            error: function() {
                new Noty({
                    type: "error",
                    text: "{{ trans('backpack-store::bulk_actions.error_occurred') }}"
                }).show();
                $('#categoryModal').modal('hide');
            }
        });
    });
});
</script>
@endpush

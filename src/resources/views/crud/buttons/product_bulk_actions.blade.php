@if ($crud->hasAccess('update'))
<?php
$categories = \Backpack\Store\app\Models\Category::all();
$brands = \Backpack\Store\app\Models\Brand::all();
?>
    <div class="btn-group">
        <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            {{ trans('backpack-store::bulk_actions.bulk_actions') }} <span class="caret"></span>
        </button>
        <ul class="dropdown-menu">
            <li class="dropdown-item"><a href="#" class="bulk-action" data-action="set_active">{{ trans('backpack-store::bulk_actions.activate') }}</a></li>
            <li class="dropdown-item"><a href="#" class="bulk-action" data-action="set_inactive">{{ trans('backpack-store::bulk_actions.deactivate') }}</a></li>
            <div class="dropdown-divider"></div>
            <li class="dropdown-item"><a href="#" class="bulk-action" data-action="set_category">{{ trans('backpack-store::bulk_actions.set_category') }}</a></li>
            <li class="dropdown-item"><a href="#" class="bulk-action" data-action="set_attribute">{{ trans('backpack-store::bulk_actions.set_attribute') }}</a></li>
            <li class="dropdown-item"><a href="#" class="bulk-action" data-action="set_brand">{{ trans('backpack-store::bulk_actions.set_brand') }}</a></li>
        </ul>
    </div>

    @include('store-crud::buttons.category_bulk_modal')
    @include('store-crud::buttons.attribute_bulk_modal')
    @include('store-crud::buttons.brand_bulk_modal')
    @push('after_scripts')
        <script>
            $(document).ready(function() {
                // Handle bulk action clicks
                $('.bulk-action').on('click', function(e) {
                    e.preventDefault();
                    var action = $(this).data('action');

                    if (typeof crud.checkedItems === 'undefined' || crud.checkedItems.length == 0) {
                        new Noty({
                            type: "warning",
                            text: "<strong>{{ trans('backpack-store::bulk_actions.no_items_selected') }}</strong><br>{{ trans('backpack-store::bulk_actions.please_select_items') }}"
                        }).show();
                        return;
                    }

                    if (action === 'set_category') {
                        window.showCategoryModal();
                    } else if (action === 'set_attribute') {
                        window.showAttributeModal();
                    } else if (action === 'set_brand') {
                        window.showBrandModal();
                    } else {
                        // Для activate/deactivate отправляем напрямую
                        $.ajax({
                            url: '{{ url($crud->route . "/bulk-action") }}/' + action,
                            type: 'POST',
                            data: {
                                ids: crud.checkedItems,
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
                            },
                            error: function() {
                                new Noty({
                                    type: "error",
                                    text: "{{ trans('backpack-store::bulk_actions.error_occurred') }}"
                                }).show();
                            }
                        });
                    }
                });
            });
        </script>
    @endpush
@endif

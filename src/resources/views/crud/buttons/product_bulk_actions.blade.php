@if ($crud->hasAccess('update'))
<?php
$categories = \Backpack\Store\app\Models\Category::all();
?>
    <div class="btn-group">
        <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            Массовые действия <span class="caret"></span>
        </button>
        <ul class="dropdown-menu">
            <li class="dropdown-item"><a href="#" class="bulk-action" data-action="set_active">Activate Selected</a></li>
            <li class="dropdown-item"><a href="#" class="bulk-action" data-action="set_inactive">Deactivate Selected</a></li>
            <div class="dropdown-divider"></div>
            <li class="dropdown-item"><a href="#" class="bulk-action" data-action="set_category">Set Category</a></li>
        </ul>
    </div>

    <!-- Модальное окно для выбора категории -->
    <div class="modal fade" id="categoryModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Category</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <select name="category_id" id="category_id" class="form-control select2">
                        <option value="">-- Без категории --</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmCategory">Apply</button>
                </div>
            </div>
        </div>
    </div>

    @push('after_styles')
    <style>
        .modal-backdrop {
            z-index: 1040 !important;
        }
        #categoryModal {
            z-index: 1050 !important;
        }
        #categoryModal .select2-container {
            z-index: 1051 !important;
        }
        .select2-dropdown {
            z-index: 1051 !important;
        }
    </style>

    @endpush
    @push('after_scripts')
        <script>
            $(document).ready(function() {
                // Move modal to body element
                $('#categoryModal').appendTo('body');
                
                // Initialize Select2
                $('#category_id').select2({
                    dropdownParent: $('#categoryModal')
                });

                // Handle bulk action clicks
                $('.bulk-action').on('click', function(e) {
                    e.preventDefault();
                    var action = $(this).data('action');

                    if (typeof crud.checkedItems === 'undefined' || crud.checkedItems.length == 0) {
                        new Noty({
                            type: "warning",
                            text: "<strong>No items selected</strong><br>Please select one or more items to perform this action."
                        }).show();
                        return;
                    }

                    if (action === 'set_category') {
                        // Открываем модальное окно для выбора категории
                        $('#categoryModal').modal('show');

                        $('#confirmCategory').off('click').on('click', function() {
                            var categoryId = $('#category_id').val();
                            performBulkAction(action, crud.checkedItems, categoryId);
                            $('#categoryModal').modal('hide');
                        });
                    } else {
                        // Для activate/deactivate отправляем напрямую
                        performBulkAction(action, crud.checkedItems);
                    }
                });

                // Функция отправки AJAX-запроса
                function performBulkAction(action, ids, categoryId = null) {
                    var data = {
                        ids: ids,
                        _token: '{{ csrf_token() }}'
                    };
                    if (categoryId !== undefined) {
                        data.category_id = categoryId;
                    }

                    $.ajax({
                        url: '{{ url($crud->route . "/bulk-action") }}/' + action,
                        type: 'POST',
                        data: data,
                        success: function(response) {
                            if (response.success) {
                                new Noty({
                                    type: "success",
                                    text: response.message
                                }).show();
                                
                                // Очищаем массив выбранных элементов
                                crud.checkedItems = [];
                                
                                // Снимаем все чекбоксы
                                $("input.crud_bulk_actions_row_checkbox").prop('checked', false);
                                $("input.crud_bulk_actions_main_checkbox").prop('checked', false);
                                
                                // Обновляем таблицу
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
                                text: "An error occurred while performing the action."
                            }).show();
                        }
                    });
                }
            });
        </script>
    @endpush
@endif
@if ($crud->hasAccess('update'))
<?php
$categories = \Backpack\Store\app\Models\Category::all();
?>
    <div class="btn-group">
        <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            Массовые действия <span class="caret"></span>
        </button>
        <ul class="dropdown-menu">
            <li class="dropdown-item"><a href="#" class="bulk-action" data-action="set_active">Активировать</a></li>
            <li class="dropdown-item"><a href="#" class="bulk-action" data-action="set_inactive">Деактивировать</a></li>
            <div class="dropdown-divider"></div>
            <li class="dropdown-item"><a href="#" class="bulk-action" data-action="set_category">Установить категорию</a></li>
        </ul>
    </div>

    <!-- Модальное окно для выбора категории -->
    <div class="modal fade" id="categoryModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Выберите категории</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <select name="category_id[]" id="category_id" class="form-control select2" multiple>
                        <!-- <option value="null">-- Без категории --</option> -->
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="confirmCategory">Принять</button>
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
            width: 100% !important;
            max-width: 100% !important;
        }
        .select2-dropdown {
            z-index: 1051 !important;
        }
        /* Ensure Select2 takes full width of its parent */
        .select2 {
            width: 100% !important;
        }
        .select2-selection {
            width: 100% !important;
        }
        .select2-search__field {
            width: 100% !important;
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
                    dropdownParent: $('#categoryModal'),
                    multiple: true,
                    placeholder: "Выберите категории",
                }).on('select2:select select2:unselect', function (e) {
                    var $select = $(this);
                    var noCategory = ''; // Value for "Без категории" option
                    
                    // If "Без категории" was selected
                    if (e.params.data.id === noCategory) {
                        // If this was a selection (not unselection)
                        if (e.type === 'select2:select') {
                            // Deselect all other options
                            var selected = $select.val() || [];
                            $select.val([noCategory]).trigger('change');
                        }
                    } else {
                        // If any other option was selected, remove "Без категории" from selection
                        var selected = $select.val() || [];
                        if (selected.includes(noCategory)) {
                            selected = selected.filter(value => value !== noCategory);
                            $select.val(selected).trigger('change');
                        }
                    }
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
                        // Clear previous selections
                        $('#category_id').val(null).trigger('change');
                        // Открываем модальное окно для выбора категории
                        $('#categoryModal').modal('show');

                        $('#confirmCategory').off('click').on('click', function() {
                            var categoryIds = $('#category_id').val();
                            performBulkAction(action, crud.checkedItems, categoryIds);
                            $('#categoryModal').modal('hide');
                        });
                    } else {
                        // Для activate/deactivate отправляем напрямую
                        performBulkAction(action, crud.checkedItems);
                    }
                });

                // Функция отправки AJAX-запроса
                function performBulkAction(action, ids, categoryIds = null) {
                    var data = {
                        ids: ids,
                        _token: '{{ csrf_token() }}'
                    };
                    if (categoryIds !== null) {
                        data.category_ids = categoryIds;
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
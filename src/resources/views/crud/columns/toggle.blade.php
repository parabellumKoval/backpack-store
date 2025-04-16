@php
    $isActive = $entry->{$column['name']};
    $entryId = $entry->getKey();
    $uniqueId = 'switch_'.$entryId;
@endphp

<td>
    <div class="custom-control custom-switch">
        <input type="checkbox" 
               class="custom-control-input toggle-switch" 
               id="{{ $uniqueId }}"
               data-id="{{ $entryId }}" 
               {{ $isActive ? 'checked' : '' }}>
        <label class="custom-control-label" for="{{ $uniqueId }}"></label>
    </div>
</td>

<script>
    if (typeof toggleSwitchInit != 'function') {
        function toggleSwitchInit() {
            $('body').on('change', '.toggle-switch', function(e) {
                e.preventDefault();
                var $this = $(this);
                var entryId = $this.data('id');
                var isActive = $this.is(':checked') ? 1 : 0;

                $.ajax({
                    url: '{{ url($crud->route) }}/' + entryId + '/toggle',
                    type: 'POST',
                    data: {
                        is_active: isActive,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(result) {
                        new Noty({
                            type: "success",
                            text: "Статус успешно обновлен"
                        }).show();
                    },
                    error: function(result) {
                        $this.prop('checked', !isActive);
                        new Noty({
                            type: "error",
                            text: "Ошибка при обновлении статуса"
                        }).show();
                    }
                });
            });
        }
    }

    // make it so that the function above is run after each DataTable draw event
    crud.addFunctionToDataTablesDrawEventQueue('toggleSwitchInit');
</script>
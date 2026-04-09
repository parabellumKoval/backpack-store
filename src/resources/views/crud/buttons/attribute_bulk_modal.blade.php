@php
    $bulkAttributes = \Backpack\Store\app\Models\Attribute::query()
        ->active()
        ->with('values')
        ->orderBy('id')
        ->get()
        ->map(function ($attribute) {
            $name = $attribute->name;

            if (is_array($name)) {
                $name = collect($name)->filter()->first();
            }

            return [
                'id' => (int) $attribute->id,
                'name' => (string) ($name ?: ('#'.$attribute->id)),
                'type' => (string) $attribute->type,
                'values' => $attribute->values
                    ->map(fn ($value) => [
                        'id' => (int) $value->id,
                        'value' => (string) $value->value,
                    ])
                    ->values()
                    ->all(),
            ];
        })
        ->values();
@endphp

<div class="modal fade" id="attributeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ trans('backpack-store::bulk_actions.select_attribute') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="bulk_attribute_id">{{ trans('backpack-store::bulk_actions.select_attribute') }}</label>
                    <select name="attribute_id" id="bulk_attribute_id" class="form-control select2">
                        <option value="">{{ trans('backpack-store::bulk_actions.select_attribute') }}</option>
                        @foreach ($bulkAttributes as $attribute)
                            <option value="{{ $attribute['id'] }}">{{ $attribute['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div id="bulkAttributeCheckboxGroup" class="form-group d-none">
                    <label for="bulk_attribute_value_ids">{{ trans('backpack-store::bulk_actions.select_attribute_values') }}</label>
                    <select name="attribute_value_ids[]" id="bulk_attribute_value_ids" class="form-control select2" multiple></select>
                </div>

                <div id="bulkAttributeRadioGroup" class="form-group d-none">
                    <label for="bulk_attribute_value_id">{{ trans('backpack-store::bulk_actions.select_attribute_value') }}</label>
                    <select name="attribute_value_id" id="bulk_attribute_value_id" class="form-control select2"></select>
                </div>

                <div id="bulkAttributeInputGroup" class="form-group d-none">
                    <label for="bulk_attribute_input_value">{{ trans('backpack-store::bulk_actions.attribute_value') }}</label>
                    <input type="text" name="attribute_value" id="bulk_attribute_input_value" class="form-control">
                </div>

                <small class="text-muted d-block">
                    {{ trans('backpack-store::bulk_actions.empty_attribute_value_hint') }}
                </small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ trans('backpack-store::bulk_actions.cancel') }}</button>
                <button type="button" class="btn btn-primary" id="confirmAttribute">{{ trans('backpack-store::bulk_actions.confirm') }}</button>
            </div>
        </div>
    </div>
</div>

@push('after_styles')
<style>
    #attributeModal {
        z-index: 1050 !important;
    }
    #attributeModal .select2-container {
        z-index: 1051 !important;
        width: 100% !important;
    }
</style>
@endpush

@push('after_scripts')
<script>
$(document).ready(function() {
    const attributesData = @json($bulkAttributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $('#attributeModal').appendTo('body');

    $('#bulk_attribute_id').select2({
        dropdownParent: $('#attributeModal'),
        placeholder: "{{ trans('backpack-store::bulk_actions.select_attribute') }}"
    });

    $('#bulk_attribute_value_ids').select2({
        dropdownParent: $('#attributeModal'),
        multiple: true,
        placeholder: "{{ trans('backpack-store::bulk_actions.select_attribute_values') }}"
    });

    $('#bulk_attribute_value_id').select2({
        dropdownParent: $('#attributeModal'),
        placeholder: "{{ trans('backpack-store::bulk_actions.select_attribute_value') }}"
    });

    function resetAttributeInputs() {
        $('#bulkAttributeCheckboxGroup').addClass('d-none');
        $('#bulkAttributeRadioGroup').addClass('d-none');
        $('#bulkAttributeInputGroup').addClass('d-none');
        $('#bulk_attribute_value_ids').empty().trigger('change');
        $('#bulk_attribute_value_id').empty().trigger('change');
        $('#bulk_attribute_input_value').val('');
        $('#bulk_attribute_input_value').attr('type', 'text');
    }

    function findSelectedAttribute() {
        const attributeId = $('#bulk_attribute_id').val();
        return attributesData.find(function(item) {
            return String(item.id) === String(attributeId);
        }) || null;
    }

    function renderAttributeInputs() {
        resetAttributeInputs();

        const attribute = findSelectedAttribute();
        if (!attribute) {
            return;
        }

        if (attribute.type === 'checkbox') {
            const options = attribute.values.map(function(value) {
                return new Option(value.value, value.id, false, false);
            });
            options.forEach(function(option) {
                $('#bulk_attribute_value_ids').append(option);
            });
            $('#bulkAttributeCheckboxGroup').removeClass('d-none');
            return;
        }

        if (attribute.type === 'radio') {
            $('#bulk_attribute_value_id').append(new Option("{{ trans('backpack-store::bulk_actions.empty_option') }}", '', false, false));
            attribute.values.forEach(function(value) {
                $('#bulk_attribute_value_id').append(new Option(value.value, value.id, false, false));
            });
            $('#bulkAttributeRadioGroup').removeClass('d-none');
            return;
        }

        $('#bulk_attribute_input_value').attr('type', attribute.type === 'number' ? 'number' : 'text');
        $('#bulkAttributeInputGroup').removeClass('d-none');
    }

    $('#bulk_attribute_id').on('change', renderAttributeInputs);

    window.showAttributeModal = function() {
        $('#bulk_attribute_id').val(null).trigger('change');
        resetAttributeInputs();
        $('#attributeModal').modal('show');
    };

    $('#confirmAttribute').click(function() {
        const attribute = findSelectedAttribute();

        if (!attribute) {
            new Noty({
                type: "warning",
                text: "{{ trans('backpack-store::bulk_actions.attribute_required') }}"
            }).show();
            return;
        }

        const payload = {
            ids: crud.checkedItems,
            attribute_id: attribute.id,
            _token: '{{ csrf_token() }}'
        };

        if (attribute.type === 'checkbox') {
            payload.attribute_value_ids = $('#bulk_attribute_value_ids').val() || [];
        } else if (attribute.type === 'radio') {
            payload.attribute_value_id = $('#bulk_attribute_value_id').val() || null;
        } else {
            payload.value = $('#bulk_attribute_input_value').val();
        }

        $.ajax({
            url: '{{ url($crud->route . "/bulk-action/set_attribute") }}',
            type: 'POST',
            data: payload,
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
                $('#attributeModal').modal('hide');
            },
            error: function() {
                new Noty({
                    type: "error",
                    text: "{{ trans('backpack-store::bulk_actions.error_occurred') }}"
                }).show();
                $('#attributeModal').modal('hide');
            }
        });
    });
});
</script>
@endpush

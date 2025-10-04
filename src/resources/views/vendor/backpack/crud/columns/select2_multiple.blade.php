@php
    $values = $entry->{$column['name']};
    $model = new $column['model'];
    $attribute = $column['attribute'];
    $entryId = $entry->getKey();
    $uniqueId = 'select2_multiple_'.$entryId;
    // Define max width - you can make this configurable through $column['max_width'] if needed
    $maxWidth = $column['max_width'] ?? '250px';
@endphp

<td>
    <!-- include select2 css-->
    <link href="{{ asset('packages/select2/dist/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('packages/select2-bootstrap-theme/dist/select2-bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
    <style>
        .select2-wrapper {
            position: relative;
            max-width: {{ $maxWidth }};
        }
        .select2-container {
            width: 100% !important;
            max-width: {{ $maxWidth }};
        }
        .select2-selection--multiple {
            min-height: 35px;
            max-height: 100px;
            overflow-y: auto;
        }
        .select2-container--bootstrap {
            display: block;
        }
        .select2-container .select2-selection--multiple .select2-selection__rendered {
            white-space: normal;
            display: block;
        }
        .select2-container--bootstrap .select2-selection--multiple .select2-selection__choice {
            white-space: normal;
            word-break: break-word;
            max-width: 100%;
        }
    </style>

    <div class="select2-wrapper">
        <select 
            id="{{ $uniqueId }}"
            class="form-control select2-multiple-ajax"
            data-id="{{ $entryId }}"
            data-field-attribute="{{ $attribute }}"
            data-connected-entity-key-name="{{ $model->getKeyName() }}"
            data-data-source="{{ $column['data_source'] ?? url($crud->route.'/fetch/'.$column['name']) }}"
            multiple>
            @if($values && count($values))
                @foreach($values as $value)
                    <option value="{{ $value->getKey() }}" selected>
                        {{ $value->$attribute }}
                    </option>
                @endforeach
            @endif
        </select>
    </div>
</td>

<script src="{{ asset('packages/select2/dist/js/select2.full.min.js') }}"></script>
@if (app()->getLocale() !== 'en')
<script src="{{ asset('packages/select2/dist/js/i18n/' . str_replace('_', '-', app()->getLocale()) . '.js') }}"></script>
@endif

<script>
    if (typeof select2MultipleAjaxInit != 'function') {
        function select2MultipleAjaxInit() {
            $('.select2-multiple-ajax').each(function() {
                let element = $(this);
                if (!element.hasClass("select2-hidden-accessible")) {
                    element.select2({
                        theme: 'bootstrap',
                        multiple: true,
                        placeholder: 'Выберите элементы',
                        minimumInputLength: 0,
                        dropdownParent: element.closest('.select2-wrapper'),
                        ajax: {
                            url: element.attr('data-data-source'),
                            dataType: 'json',
                            delay: 250,
                            data: function (params) {
                                return {
                                    q: params.term,
                                    page: params.page || 1
                                };
                            },
                            processResults: function (data, params) {
                                params.page = params.page || 1;

                                return {
                                    results: $.map(data.data || data, function (item) {
                                        return {
                                            text: item[element.attr('data-field-attribute')],
                                            id: item[element.attr('data-connected-entity-key-name')]
                                        }
                                    }),
                                    pagination: {
                                        more: data.current_page && data.current_page < data.last_page
                                    }
                                };
                            },
                            cache: true
                        }
                    }).on('change', function(e) {
                        let $this = $(this);
                        let entryId = $this.data('id');
                        let selectedValues = $this.val();

                        $.ajax({
                            url: '{{ url($crud->route) }}/' + entryId + '/select2-multiple',
                            type: 'POST',
                            data: {
                                values: selectedValues,
                                field: '{{ $column['name'] }}',
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(result) {
                                new Noty({
                                    type: "success",
                                    text: "Значения успешно обновлены"
                                }).show();
                            },
                            error: function(result) {
                                new Noty({
                                    type: "error",
                                    text: "Ошибка при обновлении значений"
                                }).show();
                            }
                        });
                    });
                }
            });
        }
    }

    // make it so that the function above is run after each DataTable draw event
    crud.addFunctionToDataTablesDrawEventQueue('select2MultipleAjaxInit');
</script>
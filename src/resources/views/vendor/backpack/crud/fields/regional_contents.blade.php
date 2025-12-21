@php
    $fieldName = $field['name'] ?? 'regional_contents';
    $countries = $field['countries'] ?? [];
    $localesConfig = $field['locales'] ?? config('backpack.crud.locales', []);
    $locales = array_keys($localesConfig);
    $values = old($fieldName, $field['value'] ?? []);
    $states = $field['states'] ?? [];

    $editorOptions = [
        'filebrowserBrowseUrl' => backpack_url('elfinder/ckeditor'),
        'extraPlugins' => 'embed,widget',
        'embed_provider' => '//ckeditor.iframe.ly/api/oembed?url={url}&callback={callback}',
    ];
@endphp

@include('crud::fields.inc.wrapper_start')
    <label>{!! $field['label'] !!}</label>
    @if (isset($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
    @endif

    <div class="small text-muted mb-2">
        Заполните локализованные описания по странам. Если все поля пустые — будет использовано глобальное описание товара.
    </div>

    <div class="regional-contents">
        @forelse($countries as $code => $config)
            @php
                $code = strtolower((string) $code);
                $countryLabel = $config['country'] ?? strtoupper($code);
                $countryValues = $values[$code] ?? [];
                $countryStates = $states[$code] ?? [];
            @endphp
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
                    <div>
                        <strong>{{ $countryLabel }}</strong>
                        <span class="text-muted">({{ strtoupper($code) }})</span>
                    </div>
                    @if (!empty($locales))
                        <div class="translatable-indicator__locales d-inline-flex flex-wrap" style="gap:4px;">
                            @foreach ($locales as $locale)
                                @php
                                    $stateContent = $countryStates['content'][$locale] ?? ['filled' => false, 'length' => 0];
                                    $stateExcerpt = $countryStates['excerpt'][$locale] ?? ['filled' => false, 'length' => 0];
                                    $stateMerchant = $countryStates['merchant_content'][$locale] ?? ['filled' => false, 'length' => 0];
                                    $filled = ($stateContent['filled'] ?? false) || ($stateExcerpt['filled'] ?? false) || ($stateMerchant['filled'] ?? false);
                                    $badgeClass = $filled ? 'badge-success' : 'badge-secondary';
                                    $label = $localesConfig[$locale] ?? strtoupper($locale);
                                @endphp
                                <span class="badge {{ $badgeClass }} badge-pill text-uppercase small" title="{{ $label }}">
                                    {{ strtoupper($locale) }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="card-body">
                    @if (empty($locales))
                        <div class="alert alert-warning mb-0">
                            Не заданы доступные языки (config backpack.crud.locales).
                        </div>
                    @else
                        <ul class="nav nav-pills mb-3" role="tablist">
                            @foreach ($locales as $index => $locale)
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link {{ $index === 0 ? 'active' : '' }}"
                                       data-toggle="tab"
                                       href="#{{ $fieldName }}-{{ $code }}-{{ $locale }}"
                                       role="tab">
                                        {{ $localesConfig[$locale] ?? strtoupper($locale) }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <div class="tab-content">
                            @foreach ($locales as $index => $locale)
                                @php
                                    $contentValue = old($fieldName.'.'.$code.'.content.'.$locale, $countryValues['content'][$locale] ?? '');
                                    $excerptValue = old($fieldName.'.'.$code.'.excerpt.'.$locale, $countryValues['excerpt'][$locale] ?? '');
                                    $merchantValue = old($fieldName.'.'.$code.'.merchant_content.'.$locale, $countryValues['merchant_content'][$locale] ?? '');
                                @endphp
                                <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}" id="{{ $fieldName }}-{{ $code }}-{{ $locale }}" role="tabpanel">
                                    <div class="form-group">
                                        <label>Описание ({{ strtoupper($locale) }})</label>
                                        <textarea
                                            name="{{ $fieldName }}[{{ $code }}][content][{{ $locale }}]"
                                            data-init-function="bpFieldInitCKEditorElement"
                                            data-options="{{ trim(json_encode($editorOptions)) }}"
                                            class="form-control">{{ $contentValue }}</textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Краткое описание ({{ strtoupper($locale) }})</label>
                                        <textarea
                                            name="{{ $fieldName }}[{{ $code }}][excerpt][{{ $locale }}]"
                                            class="form-control"
                                            rows="2">{{ $excerptValue }}</textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Merchant контент ({{ strtoupper($locale) }})</label>
                                        <textarea
                                            name="{{ $fieldName }}[{{ $code }}][merchant_content][{{ $locale }}]"
                                            class="form-control"
                                            rows="2">{{ $merchantValue }}</textarea>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="alert alert-info mb-0">
                Нет доступных стран для заполнения регионального контента.
            </div>
        @endforelse
    </div>
@include('crud::fields.inc.wrapper_end')

@push('crud_fields_scripts')
    @once('regional-contents-ckeditor')
        <script src="{{ asset('packages/ckeditor/ckeditor.js') }}"></script>
        <script src="{{ asset('packages/ckeditor/adapters/jquery.js') }}"></script>
        <script>
            if (typeof bpFieldInitCKEditorElement !== 'function') {
                function bpFieldInitCKEditorElement(element) {
                    element.on('backpack_field.deleted', function(e) {
                        var instanceName = element.siblings("[id^='cke_editor']").attr('id');

                        if (instanceName && instanceName.startsWith('cke_')) {
                            instanceName = instanceName.substr(4);
                        }

                        if (instanceName && CKEDITOR.instances[instanceName]) {
                            CKEDITOR.instances[instanceName].destroy(true);
                        }
                    });

                    element.ckeditor(element.data('options'));
                }
            }
        </script>
    @endonce
@endpush

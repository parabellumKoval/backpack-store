@php
  $code = strtoupper((string) data_get($entry, $column['name']));
  $flag = $code ? get_flag($code) : '';
  $label = $code ? \Store::countryLabel(strtolower($code)) : null;
@endphp

<div style="display: flex; flex-direction: column; line-height: 1.2;">
  <span style="font-size: 24px; line-height: 1;">{{ $flag ?: '🌍' }}</span>
  @if($label)
    <span style="font-size: 12px; color: #6c757d;">{{ $label }}</span>
  @endif
</div>

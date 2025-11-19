@php
  $style = isset($muted) && $muted ? 'opacity: 0.4;' : '';
  $methodKey = data_get($delivery, 'method');
  $config = $methodKey ? (array) (config('dress.admin_orders.deliveries.' . $methodKey) ?? []) : [];
  $labelKey = $config['label'] ?? null;
  $methodLabel = null;

  if ($labelKey) {
      $translated = __($labelKey);
      $methodLabel = $translated !== $labelKey ? $translated : null;
  }

  if (!$methodLabel && $methodKey) {
      $defaultKey = 'backpack-store::shop.delivery_methods.' . $methodKey;
      $translated = __($defaultKey);
      $methodLabel = $translated !== $defaultKey ? $translated : null;
  }

  if (!$methodLabel && $methodKey) {
      $methodLabel = ucwords(str_replace('_', ' ', $methodKey));
  }

  $logoPath = $config['logo'] ?? null;
  $logoUrl = $logoPath ? asset($logoPath) : null;
  $logoAlt = $config['logo_alt'] ?? $methodLabel;
  $icon = $config['icon'] ?? null;
@endphp

<div style="{{ $style }}">
  <div>
    @include('store-crud::columns.status', ['status' => $status, 'context' => 'delivery', 'type' => 'text'])
  </div>
  @if($methodLabel)
    <div style="margin-top: 4px; font-size: 12px; color: #6c757d; display: inline-flex; align-items: center; gap: 6px;">
      @if($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ $logoAlt }}" style="height: 18px;">
      @elseif($icon)
        <i class="la {{ $icon }}" style="font-size: 16px;"></i>
      @endif
      <span>{{ $methodLabel }}</span>
    </div>
  @endif
</div>

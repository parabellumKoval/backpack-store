@php
  $storefrontCode = \Backpack\Store\app\Services\Store::normalizeStorefrontCode(
    $entry->storefront_code ?? data_get($entry->info ?? [], 'storefront')
  );
  $storefront = $storefrontCode ? \Backpack\Store\app\Services\Store::storefrontMeta($storefrontCode) : null;
@endphp

<div class="order-code-with-storefront">
  @if($storefront)
    <span
      class="order-code-with-storefront__badge"
      title="{{ $storefront['label'] ?? $storefront['code'] }}"
      style="background-color: {{ $storefront['badge']['background'] ?? '#E5E7EB' }}; color: {{ $storefront['badge']['color'] ?? '#111827' }};"
    >
      {{ $storefront['code'] ?? $storefrontCode }}
    </span>
  @endif

  <div class="order-code-with-storefront__code">
    {{ $entry->code }}
  </div>
</div>

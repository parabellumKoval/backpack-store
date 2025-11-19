@php
  $rawInfo = (array) ($entry->info ?? []);
  $products = $rawInfo['products'] ?? [];

  if (empty($products) && method_exists($entry, 'getProductsAnywayAttribute')) {
      $products = $entry->getProductsAnywayAttribute() ?? [];
  }

  $currency = $entry->currency_code ?? \Settings::get('dress.store.currency.code', 'USD');
@endphp

<div style="padding: 24px; background: #f8f9fb;">
  <div style="margin-bottom: 16px;">
    <div style="font-size: 14px; color: #6c757d;">Состав заказа № {{ $entry->code }}</div>
    <div style="font-size: 18px; font-weight: 600; color: #212529;">Товары</div>
  </div>

  @forelse($products as $product)
    <div style="margin-bottom: 18px;">
      @include('store-crud::components.order.product-card', [
        'product' => $product,
        'currency' => $currency,
      ])
    </div>
  @empty
    <div style="padding: 28px; text-align: center; border: 1px dashed #cbd3da; border-radius: 10px; background: #fff;">
      Товары не найдены для этого заказа
    </div>
  @endforelse
</div>

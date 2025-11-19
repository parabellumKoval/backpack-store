@php
  $product = $product ?? [];
  $currency = $currency ?? \Settings::get('dress.store.currency.code', 'USD');
  $formatMoney = function ($value) use ($currency) {
    return number_format((float) $value, 2, '.', ' ') . ' ' . $currency;
  };

  $imagePath = data_get($product, 'image.src') ?? data_get($product, 'image');
  $imageUrl = $imagePath ? url($imagePath) : null;

  $name = $product['name'] ?? 'Товар без названия';
  $shortName = $product['short_name'] ?? null;
  $code = $product['code'] ?? $product['sku'] ?? null;
  $price = (float) ($product['price'] ?? 0);
  $oldPrice = (float) ($product['old_price'] ?? 0);
  $amount = (float) ($product['amount'] ?? 0);
  $total = $price * $amount;
@endphp

<div style="display: grid; grid-template-columns: 130px 1fr; gap: 0; border: 1px solid #dee2e6; border-radius: 10px; overflow: hidden; background: #fff; min-height: 140px;">
  <div style="background: #f4f5f7; display: flex; align-items: center; justify-content: center;">
    @if($imageUrl)
      <img src="{{ $imageUrl }}"
           alt="{{ $name }}"
           style="width: 100%; height: 100%; object-fit: cover;"
           onerror="this.style.display='none'">
    @else
      <div style="font-size: 12px; color: #6c757d; text-align: center; padding: 12px;">
        Без изображения
      </div>
    @endif
  </div>
  <div style="padding: 16px; display: flex; flex-direction: column; gap: 10px;">
    <div>
      <div style="font-size: 15px; font-weight: 600; color: #212529;">
        {{ $name }}
      </div>
      @if($shortName)
        <div style="font-size: 12px; color: #6c757d; margin-top: 2px;">
          {{ $shortName }}
        </div>
      @endif
    </div>

    @if($code)
      <div style="font-size: 12px; color: #6c757d;">
        Код товара: <strong style="color: #212529;">{{ $code }}</strong>
      </div>
    @endif

    <div style="display: flex; flex-wrap: wrap; gap: 16px; font-size: 13px; color: #495057;">
      <div>
        Цена:
        <strong style="color: #28a745;">{{ $formatMoney($price) }}</strong>
        @if($oldPrice > 0)
          <span style="color: #adb5bd; font-size: 12px;">
            <s>{{ $formatMoney($oldPrice) }}</s>
          </span>
        @endif
      </div>
      <div>
        Количество:
        <strong>{{ rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.') ?: '0' }} шт</strong>
      </div>
      <div>
        Сумма:
        <strong style="color: #212529;">{{ $formatMoney($total) }}</strong>
      </div>
    </div>
  </div>
</div>

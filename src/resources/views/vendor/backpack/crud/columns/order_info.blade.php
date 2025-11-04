@php
  $rawInfo = $entry->info ?? [];
  $products = $rawInfo['products'] ?? [];
  $info = \Illuminate\Support\Arr::except($rawInfo, ['products']);

  $user = isset($info['user']) ? array_filter($info['user']) : null;

  $payment = null;
  if(isset($info['payment'])) {
    if(is_array($info['payment'])) {
      $payment_items = array_filter($info['payment']);
      $payment = implode(', ', $payment_items);
    } else {
      $payment = $info['payment'];
    }
  }

  $delivery = null;
  if(isset($info['delivery'])) {
    if(is_array($info['delivery'])) {
      $delivery_items = array_filter($info['delivery']);
      $delivery = implode(', ', $delivery_items);
    } else {
      $delivery = $info['delivery'];
    }
  }

  $currency = $entry->currency_code ?? \Settings::get('dress.store.currency.code', 'USD');
  $formatMoney = function ($value) use ($currency) {
    return number_format((float)$value, 2, '.', ' ') . ' ' . $currency;
  };

  $bonusInfo = $rawInfo['bonuses'] ?? [];
  $bonusFiat = (float)($bonusInfo['fiat_amount'] ?? ($rawInfo['bonusesUsed'] ?? 0));
  $bonusPoints = (float)($bonusInfo['points'] ?? 0);
  $bonusWallet = $bonusInfo['wallet_currency'] ?? null;
  $bonusRefunded = (bool)($bonusInfo['refunded'] ?? false);

  $promoDiscount = max(0, (float)($entry->promocode_discount_total ?? 0));
  $personalDiscount = max(0, (float)($entry->personal_discount_total ?? 0));
  
  $subtotal = (float)($entry->subtotal ?? 0);
  $discountTotal = (float)($entry->discount_total ?? 0);
  $shippingTotal = (float)($entry->shipping_total ?? 0);
  $taxTotal = (float)($entry->tax_total ?? 0);
  $grandTotal = (float)($entry->grand_total ?? 0);
@endphp

<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 13px; line-height: 1.6; color: #333;">
  
  {{-- Информация о покупателе --}}
  @if($user && !empty($user))
    <div style="margin-bottom: 20px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
      <div style="font-weight: 600; color: #495057; margin-bottom: 4px;">👤 Покупатель</div>
      <div style="color: #212529;">{{ implode(', ', $user) }}</div>
    </div>
  @endif
  
  {{-- Способ оплаты --}}
  @if($payment && !empty($payment))
    <div style="margin-bottom: 12px; padding: 8px 12px; background: #fff; border-radius: 4px; border: 1px solid #e0e0e0;">
      <span style="color: #6c757d;">💳 Оплата:</span> 
      <strong style="color: #212529;">{{ $payment }}</strong>
    </div>
  @endif

  {{-- Способ доставки --}}
  @if($delivery && !empty($delivery))
    <div style="margin-bottom: 12px; padding: 8px 12px; background: #fff; border-radius: 4px; border: 1px solid #e0e0e0;">
      <span style="color: #6c757d;">📦 Доставка:</span> 
      <strong style="color: #212529;">{{ $delivery }}</strong>
    </div>
  @endif
  
  {{-- Комментарий --}}
  @if(isset($info['comment']) && !empty($info['comment']))
    <div style="margin-bottom: 20px; padding: 12px; background: #fff3cd; border-radius: 6px; border-left: 3px solid #ffc107;">
      <div style="font-weight: 600; color: #856404; margin-bottom: 4px;">💬 Комментарий</div>
      <div style="color: #856404; font-style: italic;">{{ $info['comment'] }}</div>
    </div>
  @endif
  
  {{-- Разделитель --}}
  <hr style="margin: 24px 0; border: none; border-top: 2px solid #e9ecef;">

  {{-- Товары --}}
  <div style="margin-bottom: 20px;">
    <h5 style="font-size: 16px; font-weight: 600; color: #212529; margin-bottom: 16px; display: flex; align-items: center;">
      🛍️ Товары
    </h5>

    @forelse($products as $index => $product)
      <div style="margin-bottom: 16px; padding: 16px; background: #ffffff; border: 1px solid #dee2e6; border-radius: 8px; display: flex; gap: 16px;">
        
        {{-- Изображение товара --}}
        @if(isset($product['image']['src']) && !empty($product['image']['src']))
          <div style="flex-shrink: 0;">
            <img src="{{ url($product['image']['src']) }}" 
                 alt="{{ $product['name'] ?? 'Товар' }}"
                 style="width: 80px; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid #e0e0e0;" 
                 onerror="this.style.display='none'" />
          </div>
        @endif

        {{-- Информация о товаре --}}
        <div style="flex: 1; min-width: 0;">
          <div style="font-weight: 600; color: #212529; margin-bottom: 8px; font-size: 14px;">
            {{ $product['name'] ?? 'Товар без названия' }}
            @if(!empty($product['short_name']))
              <span style="color: #6c757d; font-weight: 400; font-size: 13px;">{{ $product['short_name'] }}</span>
            @endif
          </div>
          
          <div style="display: grid; grid-template-columns: auto auto; gap: 8px; font-size: 13px;">
            @if(!empty($product['old_price']) && $product['old_price'] > 0)
              <div style="color: #6c757d;">
                Старая цена: <s>{{ $formatMoney($product['old_price']) }}</s>
              </div>
            @endif
            
            <div style="color: #495057;">
              Цена: <strong style="color: #28a745;">{{ $formatMoney($product['price'] ?? 0) }}</strong>
            </div>
            
            <div style="color: #495057;">
              Количество: <strong>{{ $product['amount'] ?? 0 }} шт</strong>
            </div>
            
            <div style="color: #495057;">
              Сумма: <strong style="color: #212529;">{{ $formatMoney(($product['price'] ?? 0) * ($product['amount'] ?? 0)) }}</strong>
            </div>
          </div>
        </div>
      </div>
    @empty
      <div style="padding: 20px; text-align: center; color: #6c757d; background: #f8f9fa; border-radius: 6px;">
        Товары не найдены
      </div>
    @endforelse
  </div>
  
  {{-- Использованные бонусы --}}
  @if($bonusFiat > 0)
    <div style="margin-bottom: 20px; padding: 12px; background: #d4edda; border-radius: 6px; border-left: 3px solid #28a745;">
      <div style="font-weight: 600; color: #155724; margin-bottom: 8px;">
        🎁 Использовано бонусов: <strong>{{ $formatMoney($bonusFiat) }}</strong>
      </div>
      <div style="color: #155724; font-size: 13px;">
        Бонусные баллы: <strong>{{ number_format($bonusPoints, 2, '.', ' ') }}</strong>
        @if($bonusWallet) {{ ' ' . strtoupper($bonusWallet) }}@endif
      </div>
      @if($bonusRefunded)
        <div style="margin-top: 6px; padding: 6px 10px; background: #fff; border-radius: 4px; color: #6c757d; font-size: 12px;">
          ℹ️ Бонусы возвращены на счёт пользователя
        </div>
      @endif
    </div>
  @endif

  {{-- Промокод --}}
  @if(isset($entry->promocode) && !empty($entry->promocode))
    <div style="margin-bottom: 20px; padding: 12px; background: #e7f3ff; border-radius: 6px; border-left: 3px solid #0066cc;">
      <div style="color: #004085;">
        🎟️ Промокод: 
        <strong>
          <a href='{{ url("/admin/promocode/{$entry->promocode['id']}/edit") }}' 
             style="color: #0066cc; text-decoration: none;">
            {{ $entry->promocode['code'] ?? 'N/A' }}
          </a>
        </strong>
        @if(isset($entry->promocodeSaleString))
          <span style="color: #495057;">({{ $entry->promocodeSaleString }})</span>
        @endif
      </div>
    </div>
  @endif

  {{-- Разделитель --}}
  <hr style="margin: 24px 0; border: none; border-top: 2px solid #e9ecef;">

  {{-- Сводка заказа --}}
  <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; border: 1px solid #dee2e6;">
    <h4 style="font-size: 15px; font-weight: 600; color: #212529; margin-bottom: 12px;">
      📊 Сводка заказа
    </h4>
    
    <div style="display: flex; flex-direction: column; gap: 8px; font-size: 13px;">
      {{-- Подытог --}}
      <div style="display: flex; justify-content: space-between; padding: 6px 0;">
        <span style="color: #6c757d;">Подытог:</span>
        <strong style="color: #212529;">{{ $formatMoney($subtotal) }}</strong>
      </div>
      
      {{-- Скидки --}}
      @if($discountTotal > 0)
        <div style="display: flex; justify-content: space-between; padding: 6px 0; color: #dc3545;">
          <span>Скидки всего:</span>
          <strong>-{{ $formatMoney($discountTotal) }}</strong>
        </div>
        
        <div style="margin-left: 16px; padding: 8px; background: #fff; border-radius: 4px; font-size: 12px;">
          @if($personalDiscount > 0)
            <div style="display: flex; justify-content: space-between; color: #6c757d; margin-bottom: 4px;">
              <span>Персональная скидка:</span>
              <span>-{{ $formatMoney($personalDiscount) }}</span>
            </div>
          @endif
          
          @if($promoDiscount > 0)
            <div style="display: flex; justify-content: space-between; color: #6c757d; margin-bottom: 4px;">
              <span>Промокоды:</span>
              <span>-{{ $formatMoney($promoDiscount) }}</span>
            </div>
          @endif
          
          @if($bonusFiat > 0)
            <div style="display: flex; justify-content: space-between; color: #6c757d;">
              <span>Бонусы:</span>
              <span>-{{ $formatMoney($bonusFiat) }}</span>
            </div>
          @endif
        </div>
      @endif
      
      {{-- Доставка --}}
      @if($shippingTotal > 0)
        <div style="display: flex; justify-content: space-between; padding: 6px 0;">
          <span style="color: #6c757d;">Доставка:</span>
          <strong style="color: #212529;">{{ $formatMoney($shippingTotal) }}</strong>
        </div>
      @endif
      
      {{-- Налоги --}}
      @if($taxTotal > 0)
        <div style="display: flex; justify-content: space-between; padding: 6px 0;">
          <span style="color: #6c757d;">Налоги:</span>
          <strong style="color: #212529;">{{ $formatMoney($taxTotal) }}</strong>
        </div>
      @endif
      
      {{-- Итого --}}
      <hr style="margin: 8px 0; border: none; border-top: 1px solid #dee2e6;">
      <div style="display: flex; justify-content: space-between; padding: 8px 0; font-size: 15px;">
        <span style="font-weight: 600; color: #212529;">Итого к оплате:</span>
        <strong style="color: #28a745; font-size: 16px;">{{ $formatMoney($grandTotal) }}</strong>
      </div>
    </div>
  </div>
</div>
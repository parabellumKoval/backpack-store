@php
  $products = $entry->info['products'] ?? [];
  $bonusesUsed = $entry->info['bonusesUsed'] ?? 0;
  $info = \Illuminate\Support\Arr::except($entry->info, ['products']);

  $user = isset($info['user'])? array_filter($info['user']): null;

  //$payment = isset($info['payment'])? array_filter($info['payment']): null;
  if(isset($info['payment'])) {
    if(is_array($info['payment'])) {
      $payment_items = array_filter($info['payment']);
      $payment = implode(', ', $payment_items);
    }else {
      $payment = $info['payment'];
    }
    //$payment = is_array($info['payment'])? array_filter($info['payment']): $info['payment'];
  }

  if(isset($info['delivery'])) {
    if(is_array($info['delivery'])) {
      $delivery_items = array_filter($info['delivery']);
      $delivery = implode(', ', $delivery_items);
    }else {
      $delivery = $info['delivery'];
    }
    //$delivery = is_array($info['delivery'])? array_filter($info['delivery']): $info['delivery'];
  }

  //$delivery = isset($info['delivery'])? array_filter($info['delivery']): null;
@endphp

<span>
  @if($user && !empty($user))
    <p>Покупатель: <strong>{{ implode(', ', $user) }}</strong></p>
  @endif
  
  @if($payment && !empty($payment))
    <p>Оплата: <strong>{{ $payment }}</strong></p>
  @endif

  @if($delivery && !empty($delivery))
    <p>Доставка: <strong>{{ $delivery }}</strong></p>
  @endif
  
  @if(isset($info['comment']))
    <p>Комментарий: <strong>{{ $info['comment'] }}</strong></p>
  @endif
  
  <hr>

  <h5>Товары:</h5>
  <br>

  @foreach($products as $product)
    <div>
      @if(isset($product['image']['src']))
        <p><img src="{{ url($product['image']['src']) }}" width="100" height="100" /></p>
      @endif

      <p><strong>{{ $product['name'] ?? '' }}</strong> {{ $product['short_name'] ?? '' }}</p>
      @if($product['old_price'])
      <p>Старая цена: <s>{{ config('backpack.store.currency.symbol') . $product['old_price'] }}</s></p>
      @endif
      <p>Цена: {{ config('backpack.store.currency.symbol') . $product['price'] }}</p>
      <p>Количество: {{ $product['amount'] }} шт</p>
      <p>Сумма: <strong>{{ config('backpack.store.currency.symbol') . ($product['price'] * $product['amount']) }}</strong></p>
    </div>
    <br>
  @endforeach

  <h4>Сумма заказа: <strong>{{ config('backpack.store.currency.symbol') . $entry->price }}</strong></h4>

  @if(config('backpack.store.order.enable_bonus', false))
    <h4>Использовано бонусов: <strong>{{ config('backpack.store.currency.symbol') . $bonusesUsed }}</strong></h4>
    <h4>Итого сумма заказа: <strong>{{ config('backpack.store.currency.symbol') . ($entry->price - $bonusesUsed) }}</strong></h4>
  @endif
</span>
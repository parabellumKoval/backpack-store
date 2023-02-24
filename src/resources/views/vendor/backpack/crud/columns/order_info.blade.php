@php
  $products = $entry->info['products'];
  $info = \Illuminate\Support\Arr::except($entry->info, ['products']);
@endphp

<span>
  @if($entry->userString)
    <p>Покупатель: <strong>{{ $entry->userString }}</strong></p>
  @endif
  
  @if($entry->addressString)
    <p>Адрес: <strong>{{ $entry->addressString }}</strong></p>
  @endif
  
  @if(isset($info['payment']))
    <p>Способ оплаты: <strong>{{ $info['payment'] }}</strong></p>
  @endif

  @if(isset($info['delivery']))
    <p>Способ доставки: <strong>{{ $info['delivery'] }}</strong></p>
  @endif
  
  @if(isset($info['point']))
    <p>Пункт выдачи: <strong>{{ $info['point'] }}</strong></p>
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
      <p>Цена: <strong>{{ $product['price'] }} $</strong></p>
      @if($product['old_price'])
      <p>Старая цена: <strong>{{ $product['old_price'] }} $</strong></p>
      @endif
      <p>Количество: <strong>{{ $product['amount'] }} шт</strong></p>
      <p>Сумма: <strong>{{ $product['price'] * $product['amount'] }} $</strong></p>
    </div>
    <br>
  @endforeach

  <h4>Сумма заказа: <strong>{{ $entry->price }} $</strong></h4>
</span>
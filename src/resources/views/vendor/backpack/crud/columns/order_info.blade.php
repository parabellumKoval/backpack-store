@php
  $products = $entry->info['products'];
  $info = \Illuminate\Support\Arr::except($entry->info, ['products']);
@endphp

<span>
  @if(isset($info['name']))
    <p>Имя: <strong>{{ $info['name'] }}</strong></p>
  @endif
  
  @if(isset($info['address']))
    <p>Адрес: <strong>{{ $info['city'] }}, {{ $info['address'] }}</strong></p>
  @elseif(isset($info['city']))
    <p>Город: <strong>{{ $info['city'] }}</strong></p>
  @endif

  @if(isset($info['tel']))
    <p>Телефон: <strong>{{ $info['tel'] }}</strong></p>
  @endif

  @if(isset($info['email']))
    <p>Email: <strong>{{ $info['email'] }}</strong></p>
  @endif
  
  <p>Способ оплаты: <strong>{{ $info['payment'] }}</strong></p>
  <p>Способ доставки: <strong>{{ $info['delivery'] }}</strong></p>
  
  @if(isset($info['point']))
    <p>Пункт выдачи: <strong>{{ $info['point'] }}</strong></p>
  @endif
  
  @if(isset($info['comment']))
    <p>Комментарий: <strong>{{ $info['comment'] }}</strong></p>
  @endif
  
  <br>
  <h5>Товары:</h5>
  @foreach($products as $product)
  <p>
    <strong>{{ $product['name'] }}</strong>
    <br>
    <p>Цена: <strong>{{ $product['price'] }} руб</strong></p>
    @if($product['old_price'])
    <p>Старая цена: <strong>{{ $product['old_price'] }} руб</strong></p>
    @endif
    <p>Количество: <strong>{{ $product['amount'] }} шт</strong></p>
    <p>Сумма: <strong>{{ $product['price'] * $product['amount'] }} руб</strong></p>
  </p>
  <hr>
  @endforeach

  <h4>Сумма заказа: <strong>{{ $entry->price }} руб</strong></h4>
</span>
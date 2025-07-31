<span>
  @php
      // $details — это массив вида:
      // [
      //    'description' => ['value' => 300, 'unit' => 'символы', 'rate' => '15%', 'weight' => 30],
      //    'images' => ['value' => 2, 'unit' => 'шт', 'rate' => '40%', 'weight' => 20],
      //    ...
      // ]
  @endphp

  @php
      // Рендерим partial в переменную
      $attrs_html = view('store-crud::columns._product_quality_tooltip', compact('details'))->render();
  @endphp

  <span data-placement='left' data-toggle='tooltip' data-html='true' title data-original-title='{{ $attrs_html }}'>
    @if($total <= 40)
      <span class="badge badge-danger">{{ $total }}</span>
    @elseif($total > 40 && $total <= 70)
      <span class="badge badge-warning">{{ $total }}</span>
    @else
      <span class="badge badge-success">{{ $total }}</span>
    @endif
  </span>
</span>
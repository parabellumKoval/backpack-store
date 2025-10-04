@php
  $url = backpack_url('product-list-item').'?list_id='.$entry->id;
@endphp
<a href="{{ $url }}" class="btn btn-sm btn-outline-primary">
  <i class="la la-th-list"></i> Товары списка
</a>

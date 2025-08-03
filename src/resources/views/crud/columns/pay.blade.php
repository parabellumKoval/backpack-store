<!-- fail, completed, pending, canceled, new -->
@php
$style = isset($muted) && $muted? 'opacity: 0.4;': '';
@endphp

<div style="{{ $style }}">
  @include('store-crud::columns.status', ['status' => $status, 'context' => 'pay', 'type' => 'text'])
  &nbsp; <span>({{ $payment['method'] ?? '' }})</span>
</div>
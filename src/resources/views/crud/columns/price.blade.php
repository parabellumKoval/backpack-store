<!-- fail, completed, pending, canceled, new -->
@php
$style = isset($muted) && $muted? 'opacity: 0.4;': '';
@endphp

<div style="{{ $style }}">
  <div class="text-monospace"><b>{{ $price }}</b></div>
</div>
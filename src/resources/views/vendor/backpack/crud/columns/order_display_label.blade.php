<span class="d-inline-flex align-items-center">
  @if($flag)[{!! $flag !!}]@endif
  <span class="text-nowrap">
    <strong>{{ $prefix . ' #' . e($parts[0] ?? '') }}</strong>

    @foreach(array_slice($parts, 1) as $p)
      / <span>{{ e($p) }}</span>
    @endforeach
  </span>
</span>

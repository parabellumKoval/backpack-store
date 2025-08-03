<!-- fail, completed, pending, canceled, new -->
@php
$user_string = '';
if(isset($user) && !empty($user)) {
  $user = array_filter($user, function($item){
    return !empty($item)? true: false;
  });

  $user_string = implode(', ', $user);
}

$style = isset($muted) && $muted? 'opacity: 0.4;': '';
@endphp

<div style="{{ $style }}">
  <span>{{ $user_string }}</span>
</div>
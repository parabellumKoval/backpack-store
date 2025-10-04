@if ($crud->hasAccess('reorder') && filled(data_get($entry, 'page')))
  @php
    $page = (string) data_get($entry, 'page');
    $reorderUrl = url($crud->route.'/reorder').'?page='.rawurlencode($page);
  @endphp

  <a href="{{ $reorderUrl }}"
     class="btn btn-sm btn-outline-primary"
     title="Отсортировать (page={{ e($page) }})">
    <i class="la la-arrows-v"></i> позиция
  </a>
@endif

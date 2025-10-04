@php

$op = $crud->getCurrentOperation(); // 'create'|'update'
$settings = $crud->getOperationSetting('variantTabs') ?? null;

$editUrl = fn($id) => url($crud->route.'/'.$id.'/edit');
$createForParent = fn($pid) => url($crud->route.'/create?parent_id='.$pid);
$deleteUrl = fn($id) => url($crud->route.'/'.$id);

$toggleFull = function(bool $full) {
    $qs = request()->query();
    $qs['full'] = $full ? 1 : 0;
    return url()->current().'?'.http_build_query($qs);
};
@endphp

@if($settings && ($settings['parentId'] ?? null))
  <div class="mod-sidebar card mb-3 ">
    <div class="card-header">
      Модификации товара
    </div>
    <div class="card-body py-2 d-flex flex-column" style="gap:.5rem;">
      @foreach(($settings['items'] ?? []) as $it)
        @php
          $active = (isset($settings['currentId']) && $settings['currentId'] == $it['id']);
          $parentUrl = isset($settings['parentId']) ? url($crud->route.'/'.$settings['parentId'].'/edit') : null;
        @endphp
        <div class="d-flex align-items-center justify-content-between">
          <a href="{{ $editUrl($it['id']) }}"
              class="btn btn-sm {{ $active ? 'btn-primary' : 'btn-outline-primary' }}">
            {{ $it['title'] }}
          </a>
          <button 
            onclick="deleteEntry(this)" 
            data-route="{{ $deleteUrl($it['id']) }}"
            data-parent-url="{{ $parentUrl }}"
            data-is-current="{{ $active ? '1' : '0' }}"
            class="btn btn-sm btn-danger">
            <i class="la la-trash"></i> Удалить
          </button>
        </div>
      @endforeach

      <a href="{{ $createForParent($settings['parentId']) }}"
          class="btn btn-sm btn-success">
        + Добавить модификацию
      </a>
    </div>
  </div>


  @push('after_styles')
      <style>
        .mod-sidebar {
          margin-top: 80px
        }

        @media (max-width: 767.98px) {
          .mod-sidebar {
            margin-top: 30px
          }
        }
      </style>
  @endpush

  @push('after_scripts')
  <script>
    function deleteEntry(button) {
      const $button = $(button);
      const deleteUrl = $button.data('route');
      const parentUrl = $button.data('parent-url');
      
      swal({
        title: "Вы уверены?",
        text: "Модификация будет удалена.",
        icon: "warning",
        buttons: {
          cancel: {
            text: "Отмена",
            value: null,
            visible: true,
            className: "bg-secondary",
            closeModal: true,
          },
          delete: {
            text: "Удалить",
            value: true,
            visible: true,
            className: "bg-danger",
          }
        },
      }).then((value) => {
        if (value) {
          $.ajax({
            url: deleteUrl,
            type: 'DELETE',
            headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(result) {
              new Noty({
                type: "success",
                text: "Модификация удалена"
              }).show();
              
              // Получаем значение isCurrent непосредственно перед использованием
              const isCurrent = $button.attr('data-is-current') === '1';
              
              if (isCurrent) {
                window.location.href = parentUrl || document.referrer || crud.route;
              } else {
                $button.closest('.d-flex').remove();
              }
            },
            error: function(result) {
              new Noty({
                type: "error",
                text: "Ошибка при удалении"
              }).show();
            }
          });
        }
      });
    }
  </script>
  @endpush
@endif

@if(!$settings['isBaseProduct'])
<div class="mb-3">
  @if(request()->boolean('full'))
    <a href="{{ $toggleFull(false) }}" class="btn btn-sm btn-secondary ml-auto">
      <i class="la la-minus"></i> Быстрое редактирование
    </a>
  @else
    <a href="{{ $toggleFull(true) }}" class="btn btn-sm btn-secondary ml-auto">
      <i class="la la-plus"></i>  Редактировать все поля
    </a>
  @endif
</div>
@endif
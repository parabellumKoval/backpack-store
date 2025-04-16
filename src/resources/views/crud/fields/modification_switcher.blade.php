@php
  $parent_id = \Request::get('parent_id') ?? null;

  if(isset($entry)){
    $modifications = $entry->modifications;
  }
  elseif($parent_id)
  {
    $modifications = $crud->model->find($parent_id)->modifications;
  }
  else{
    $modifications = null;
  }
@endphp

@include('crud::fields.inc.wrapper_start')
  
    <div>
      <label>{!! $field['label'] !!}</label>

      <div>
        @if($modifications)
          @foreach($modifications as $key => $modification)
            @php
              $name = '(id: ' . $modification->id . ')' . ' ' . ($modification->short_name ?? $modification->name);
            @endphp

            @if(isset($entry) && $modification->id === $entry->id)
              <span class="modification-item current-modification">{{ $name }}</span>
            @else
              <a href="{{ url('/admin/product/' . $modification->id . '/edit') }}" class="modification-item">{{ $name }}</a>
            @endif
          @endforeach
        @endif

        @if(isset($entry))
          <a href="{{ url('/admin/product/create?parent_id=' . $entry->base->id ) }}" class="btn btn-sm btn-primary">+ Добавить</a>
        @endif
      </div>

      {{-- HINT --}}
      @if (isset($field['hint']))
          <p class="help-block">{!! $field['hint'] !!}</p>
      @endif
    </div>
@include('crud::fields.inc.wrapper_end')

@if ($crud->fieldTypeNotLoaded($field))
    @php
        $crud->markFieldTypeAsLoaded($field);
    @endphp

    {{-- FIELD EXTRA CSS  --}}
    {{-- push things in the after_styles section --}}
    @push('crud_fields_styles')
      <style>
        .modification-item {
          padding: 0 10px 0 0;
        }
        .current-modification {
          font-weight: bold;
          text-decoration: underline;
          color: #999;
        }
      </style>
    @endpush

    {{-- FIELD EXTRA JS --}}
    {{-- push things in the after_scripts section --}}
    @push('crud_fields_scripts')
        <!-- no scripts -->
    @endpush
@endif
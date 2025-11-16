@extends(backpack_view('blank'))

@php
  $defaultBreadcrumbs = [
    trans('backpack::crud.admin') => backpack_url('dashboard'),
    $crud->entity_name_plural => url($crud->route),
    trans('backpack::crud.edit') => false,
  ];

  // if breadcrumbs aren't defined in the CrudController, use the default breadcrumbs
  $breadcrumbs = $breadcrumbs ?? $defaultBreadcrumbs;
@endphp

@section('header')
	<section class="container-fluid">
	  <h2>
        <span class="text-capitalize">{!! $crud->getHeading() ?? $crud->entity_name_plural !!}</span>
        <small>{!! $crud->getSubheading() ?? trans('backpack::crud.edit').' '.$crud->entity_name !!}.</small>

        @if ($crud->hasAccess('list'))
          <small><a href="{{ url($crud->route) }}" class="d-print-none font-sm"><i class="la la-angle-double-{{ config('backpack.base.html_direction') == 'rtl' ? 'right' : 'left' }}"></i> {{ trans('backpack::crud.back_to_all') }} <span>{{ $crud->entity_name_plural }}</span></a></small>
        @endif
	  </h2>
	</section>
@endsection

@section('content')

<div class="row">
	<div class="{{ $crud->getEditContentClass() }} order-2 order-md-1">
		<!-- Default box -->

		@include('crud::inc.grouped_errors')

		  <form method="post"
		  		action="{{ url($crud->route.'/'.$entry->getKey()) }}"
				@if ($crud->hasUploadFields('update', $entry->getKey()))
				enctype="multipart/form-data"
				@endif
		  		>
		  {!! csrf_field() !!}
		  {!! method_field('PUT') !!}

		  	@if ($crud->model->translationEnabled())
                @php
                    $translatableInputName = backpack_translatable_input_name();
                    $selectedLocale = request()->input($translatableInputName) ?? App::getLocale();
                @endphp
		    <div class="mb-2 text-right">
		    	<!-- Single button -->
				<div class="btn-group">
				  <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
				    {{trans('backpack::crud.language')}}: {{ $crud->model->getAvailableLocales()[$selectedLocale] ?? $selectedLocale }} &nbsp; <span class="caret"></span>
				  </button>
				  <ul class="dropdown-menu">
				  	@foreach ($crud->model->getAvailableLocales() as $key => $locale)
                        @php
                            $query = http_build_query([$translatableInputName => $key]);
                        @endphp
					  	<a class="dropdown-item" href="{{ url($crud->route.'/'.$entry->getKey().'/edit').'?'.$query }}">{{ $locale }}</a>
				  	@endforeach
				  </ul>
				</div>
		    </div>
		    @endif
		      <!-- load the view from the application if it exists, otherwise load the one in the package -->
		      @if(view()->exists('vendor.backpack.crud.form_content'))
		      	@include('vendor.backpack.crud.form_content', ['fields' => $crud->fields(), 'action' => 'edit'])
		      @else
		      	@include('crud::form_content', ['fields' => $crud->fields(), 'action' => 'edit'])
		      @endif

            @include('crud::inc.form_save_buttons')
		  </form>
	</div>
	<div class="col-md-4 bold-labels order-1 order-md-2">
		@include('crud::inc.product_modification_tabs')	
	</div>
</div>
@endsection

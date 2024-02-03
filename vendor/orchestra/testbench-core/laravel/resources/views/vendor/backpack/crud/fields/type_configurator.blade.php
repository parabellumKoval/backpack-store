<?php
	$current_options_name = old($field['options_name']) ? old($field['options_name']) : (isset($field['options_name']) ? $field['options_name'] : (isset($field['default']) ? $field['default'] : '' ));
	
	//$current_options_value = isset($entry->{$current_options_name}) && !empty($entry->{$current_options_name})? $entry->{$current_options_name}: null;

	$inputValues = array(
		'colors' => null,
		'color' => null,
    'number' => [
			'step' => '',
			'min' => '',
			'max' => ''
		],
		'range' => [
			'step' => 0,
			'min' => '',
			'max' => ''
		],
		'datetime' => [
			'datetime' => null,
			'date' => null,
			'daterange' => null,
		],
		'select' => null,
		'checkbox' => null,
    );
    
	$inputValues = isset($entry)? array_merge($inputValues, $entry->inputValues): $inputValues;

	//dd($entry->inputValues);
?>
<!-- enum -->
<div id="conf-all-wrapper" class="form-group col-sm-12" data-current-type="">
	
<div class="panel panel-default">

  <!-- SELECT TYPE -->
  <div class="panel-heading">
    <label>{!! $field['label'] !!}</label>
    
    <?php $entity_model = $crud->model; ?>
    <div style="margin-bottom: 15px">
      <select id="type-select" name="type[type]" class="form-control">
          @if ($entity_model::isColumnNullable($field['name']))
            <option value="">-</option>
          @endif

          @if (count($entity_model::getPossibleEnumValues($field['name'])))
            @foreach ($entity_model::getPossibleEnumValues($field['name']) as $possible_value)
                <option value="{{ $possible_value }}"
                    @if (( old($field['name']) &&  old($field['name']) == $possible_value) || (isset($field['value']) && $field['value']==$possible_value))
                        selected
                    @endif
                >{{ __('shop.fieldType.'.$possible_value) }}</option>
            @endforeach
          @endif
      </select>
    

      {{-- HINT --}}
      @if (isset($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
      @endif
    </div>
  </div> <!-- panel-heading -->

<!-- BODY -->
  <div class="panel-body">    
    <div class='conf-wrapper'>
	    
<!-- 	    RANGE TEMPLATE -->
	    <div class="conf-field-wrapper" data-field-type="range">
		    <div class="form-inline">
			  <div class="form-group">
			    <label for="exampleInputName2">Шаг</label>
			    <input type="number" min="0.00001" step="0.00001" name="{{ $current_options_name.'[step]' }}" value="{{ $inputValues['range']['step']? $inputValues['range']['step']: 1 }}" class="form-control" id="range-step" placeholder="Шаг" required="required" disabled>
			  </div>&nbsp;&nbsp;&nbsp; 
			  <div class="form-group">
			    <label for="exampleInputName2">От</label>
			    <input type="number" step="{{ $inputValues['range']['step'] }}" name="{{ $current_options_name.'[min]' }}" value="{{ $inputValues['range']['min'] }}" class="form-control" id="range-min" placeholder="От" required="required" disabled>
			  </div>&nbsp;&nbsp;&nbsp;
			  <div class="form-group">
			    <label for="exampleInputName2">До</label>
			    <input type="number" step="{{ $inputValues['range']['step'] }}" name="{{ $current_options_name.'[max]' }}" value="{{ $inputValues['range']['max'] }}" class="form-control" id="range-max" placeholder="До" required="required" disabled>
			  </div>
		    </div>  
	    </div>
<!-- 	    END RANGE TEMPLATE -->


<!-- 	    SELECT TEMPLATE -->
	    <div class="conf-field-wrapper" data-field-type="select">	    
		    @if($inputValues['select'])
			    @foreach($inputValues['select'] as $key => $value)
				    <div class="form-inline select-item">
					    <div class="input-group" style="width:75%">
						    <input type="text" name="values[]" value="{{ $value }}" class="form-control" style="display:inline-block; width: 100%">
					    </div>
					    <div class="input-group">
						    <a href="/" class="btn btn-default option-remove" ><i class="fa fa-trash"></i> Удалить</a>
					    </div>
				    </div>			    
			    @endforeach
		    @endif
	    </div>
<!-- 	    END SELECT TEMPLATE -->

<!-- 	    COLOR TEMPLATE -->
      <div class="conf-field-wrapper" data-field-type="color">	    
		    @if($inputValues['color'])
			    @foreach($inputValues['color'] as $key => $value)

				    <div class="form-inline select-item d-flex justify-content-between">
					    <div class="input-group d-flex justify-content-between" style="width:75%">
						    <input type="text" name="values[{{ $key }}][name]" value="{{ $value->name }}" class="form-control" style="width: 75%" placeholder="Название цвета" disabled>
								<input type="color" name="values[{{ $key }}][code]" value="{{ $value->code }}" class="form-control" style="width: 20%" disabled>
					    </div>
					    <div class="input-group">
						    <a href="/" class="btn btn-default option-remove" ><i class="fa fa-trash"></i> Удалить</a>
					    </div>
				    </div>			    
			    @endforeach
		    @endif
	    </div>
<!-- 	    END COLOR TEMPLATE -->

<!-- 	    DATETIME TEMPLATE -->
	    <div class="conf-field-wrapper" data-field-type="datetime">
		    <select name="value" class="form-control" disabled>
		    	<option value="datetime" {{ $inputValues['datetime']['datetime'] }}>Дата и время</option>
		    	<option value="date" {{ $inputValues['datetime']['date'] }}>Дата</option>
		    	<option value="daterange" {{ $inputValues['datetime']['daterange'] }}>Период (диапазон)</option>
		    </select>
	    </div>
<!-- 	    END DATETIME TEMPLATE -->

      </div> <!-- conf-wrapper -->
  </div> <!-- panel-body -->

<!-- FOOTER -->
  <div class="panel-footer">

<!-- 	    NUMBER TEMPLATE -->
    <div class="conf-field-wrapper" data-field-type="number">	    
      @if($inputValues['number'])
          <div class="form-inline select-item justify-content-between">
            <div class="input-group" style="width:30%">
              <input type="number" name="values[min]" value="{{ $inputValues['number']['min'] }}" class="form-control" style="display:inline-block; width: 100%" placeholder="Минимум">
            </div>
            <div class="input-group" style="width:30%">
              <input type="number" name="values[step]" value="{{ $inputValues['number']['step'] }}" step="0.001" class="form-control" style="display:inline-block; width: 100%" placeholder="Шаг">
            </div>
            <div class="input-group" style="width:30%">
              <input type="number" name="values[max]" value="{{ $inputValues['number']['max'] }}" class="form-control" style="display:inline-block; width: 100%" placeholder="Максимум">
            </div>
          </div>	
      @endif
    </div>
<!-- 	    END NUMBER TEMPLATE -->

<!-- SELECT CONTROL -->	
    <div class="form-inline select-template conf-fieldcontrol-wrapper" data-field-type="select">
      <div class="input-group col-8">
        <input type="text" name="" value="" class="form-control" style="display:inline-block; width: 100%">
      </div>
      <div class="input-group  col-4">
        <a href="/" class="btn btn-primary w-100 option-add" ><i class="fa fa-plus"></i> Добавить значение</a>
      </div>
      
      <p class="text-warning error-p hide">Введите значение</p>
    </div>
<!-- END SELECT CONTROL -->   

<!-- COLOR CONTROL -->	
    <div class="form-inline select-template conf-fieldcontrol-wrapper" data-field-type="color">
      <div class="input-group d-flex justify-content-between" style="width:75%">
        <input type="text" name="" value="" class="form-control" style="width: 75%" placeholder="Название цвета">
        <input type="color" name="" value="" class="form-control" style="width: 20%">
      </div>

      <div class="input-group">
        <a href="/" class="btn btn-primary option-add" ><i class="fa fa-plus"></i> Добавить значение</a>
      </div>
      
      <p class="text-warning error-p hide">Введите значение</p>
    </div>
<!-- END COLOR CONTROL -->    
  </div> 
<!-- panel-footer -->


</div> <!-- panel -->
</div>
{{-- FIELD CSS - will be loaded in the after_styles section --}}
@push('crud_fields_styles')
    {{-- YOUR CSS HERE --}}
    <style>
	    #conf-all-wrapper .panel {
		    margin-bottom: 0;
	    }
	    
	    #conf-all-wrapper .panel-body {
		    padding: 0;
	    }
	    
	    
	    #conf-all-wrapper .panel-footer {
		    visibility: hidden;
        max-height: 0;
	    }
	    
		.conf-field-wrapper, .conf-fieldcontrol-wrapper {
      visibility: hidden;
      max-height: 0;
      width: 100%;
		}

    .conf-field-wrapper {
      display: block;
    }

    .conf-fieldcontrol-wrapper {
      display: flex;
    }
/*
		
		.conf-field-wrapper.open {
			display: block;
		}
*/
		#conf-all-wrapper[data-current-type = 'range'] [data-field-type = 'range'],
		#conf-all-wrapper[data-current-type = 'datetime'] [data-field-type = 'datetime']{
			visibility: visible;
      max-height: initial;
		}

    #conf-all-wrapper[data-current-type = 'number'] [data-field-type = 'number'],
		#conf-all-wrapper[data-current-type = 'number'] .panel-footer,
		#conf-all-wrapper[data-current-type = 'color'] [data-field-type = 'color'],
		#conf-all-wrapper[data-current-type = 'color'] .panel-footer,
		#conf-all-wrapper[data-current-type = 'select'] [data-field-type = 'select'],
		#conf-all-wrapper[data-current-type = 'select'] .panel-footer,
		#conf-all-wrapper[data-current-type = 'checkbox'] [data-field-type = 'checkbox'],
		#conf-all-wrapper[data-current-type = 'checkbox'] .panel-footer{
			visibility: visible;
      max-height: initial;
		}

		#conf-all-wrapper[data-current-type = 'color'] .panel-body,
		#conf-all-wrapper[data-current-type = 'number'] .panel-body,
		#conf-all-wrapper[data-current-type = 'select'] .panel-body,
		#conf-all-wrapper[data-current-type = 'range'] .panel-body,
		#conf-all-wrapper[data-current-type = 'datetime'] .panel-body,
		#conf-all-wrapper[data-current-type = 'checkbox'] .panel-body  {
		    padding: 15px;
	    }
		
/* 		SELECT TYPE */
		.select-item {
			margin-bottom: 15px;
		}
		
		.select-template.select-item {
			margin-bottom: 0;
		}
		
		.error-p.hide {
			display: none;
		}
/* 		END SELECT TYPE */
	</style>
@endpush

{{-- FIELD JS - will be loaded in the after_scripts section --}}
@push('crud_fields_scripts')
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
    	
    <script>
	   $(document).ready(function(){
	   
	   		var fieldOptionsAssoc = {
					'colors' : null,
          'number' : 'number',
          'color' : 'color',
		   		'checkbox': 'select',
		   		'select': 'select',
		   		'radio': 'select',
		   		'range': 'range',
		   		'datetime': 'datetime'
	   		};

		   var fieldType = $('#type-select').val();
		   openFieldOptions(fieldType);
		   
		   $(document).on('change', '#type-select', function(event){
			   event.preventDefault();
			   var fieldType = $(this).val();
			   console.log('CHANGE', fieldType)
			   
			   openFieldOptions(fieldType);
		   }); 
		   
		   function openFieldOptions(fieldType){
			   fieldType = fieldOptionsAssoc[fieldType];


			   $('#conf-all-wrapper').attr('data-current-type', fieldType);

			   $('.conf-field-wrapper')
          .find('input, select, textarea')
          .attr('disabled', 'disabled')
          .end()
          .filter('[data-field-type = "'+fieldType+'"]')
          .find('input, select, textarea')
          .removeAttr('disabled');
		   }
		   
// 		   RANGE TYPE
		   $(document).on('change', '#range-step', function(){
				var step = $(this).val();
				
				$('#range-min, #range-max').attr('step', step); 
		   });   
// 		   END RANGE TYPE

			 var optionsLength = $('.conf-field-wrapper[data-current-type="' + fieldType + '"]').length;

// 			SELECT/COLOR TYPE
		    $(document).on('click', '.option-add', function(event){
				event.preventDefault();
				
				var fieldType = $('#type-select').val();

				fieldType = fieldOptionsAssoc[fieldType];

				var inputValue = $(this).parents('.select-template').find('input[type="text"]').val();

				if(fieldType == 'color') {
					var inputColorValue = $(this).parents('.select-template').find('input[type="color"]').val();
				}
			  
			  if(inputValue.length <= 0){
				 $('.select-template .error-p').removeClass('hide');
				 return;
			  }else{
				 $('.select-template .error-p').addClass('hide');
			  }

				var selectItem = $('.select-template[data-field-type="' + fieldType + '"]').clone().removeClass('select-template').addClass('select-item');

				if(fieldType == 'color') {
					$(selectItem).find('.option-add').removeClass('option-add btn-primary').addClass('option-remove btn-default').html('<i class="fa fa-trash"></i> Удалить').end().find('input[type="text"]').attr('name', 'values[' + optionsLength + '][name]').end().find('input[type="color"]').attr('name', 'values[' + optionsLength + '][code]').end().find('.error-p').remove();
					
					$('.conf-field-wrapper[data-field-type = "color"]').append(selectItem);
				} else {
					$(selectItem).find('.option-add').removeClass('option-add btn-primary').addClass('option-remove btn-default').html('<i class="fa fa-trash"></i> Удалить').end().find('input').attr('name', 'values[]').end().find('.error-p').remove();
					
					$('.conf-field-wrapper[data-field-type = "select"]').append(selectItem);
				}

				optionsLength++;
			  $('.select-template input').val('');
		    });
		  
			$(document).on('click', '.option-remove', function(event){
				event.preventDefault();
				optionsLength--;
				$(this).parents('.select-item').remove();
			});
//			END	SELECT/COLOR TYPE
	    });
	</script>
@endpush

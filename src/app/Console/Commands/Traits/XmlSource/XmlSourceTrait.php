<?php

namespace Backpack\Store\app\Console\Commands\Traits\XmlSource;

trait XmlSourceTrait {
  
    
    /**
     * loadFromXml
     *
     * @param  mixed $source
     * @return void
     */
    private function loadFromXml($source) {
      $this->bootSource($source);
      
      $xml = $this->getXMLCatalog($source->link);

      $item = array_reduce(explode('->', $this->settings['item']), function($model, $property) {
        return $model->{$property};
      }, $xml);

      if($this->IS_TEST_MODE) {
        // if $TEST_ITEMS === -1 it means all items
        $this->totalRecords = $this->TEST_ITEMS < 0? count($item): $this->TEST_ITEMS;
      }else {
        $this->totalRecords = count($item);
      }
      
      $this->totalUploadHistory($this->totalRecords);

      for($i = 0; $i < $this->totalRecords; $i++){
        
        // Get Attributes
        // foreach($item[$i]->attributes() as $k => $v) {
        //     \Log::info($k . ' = ' . $v);
        // }

        $xml_product = [
          'category' => $this->getItemFieldValue($item[$i], 'fieldCategory'),
          'name' => $this->getItemFieldValue($item[$i], 'fieldName'),
          'brand' => $this->getItemFieldValue($item[$i], 'fieldBrand'),
          'inStock' => $this->getItemFieldValue($item[$i], 'fieldInStock'),
          'code' => $this->getItemFieldValue($item[$i], 'fieldCode', null),
          'barcode' => $this->getItemFieldValue($item[$i], 'fieldBarcode', null),
          'price' => $this->getItemFieldValue($item[$i], 'fieldPrice')
        ];

        if(isset($this->settings['fieldImage']) && !empty($this->settings['fieldImage'])) {
          $images = $this->getItemImagesValue($item[$i]);
          if($images !== null && $images !== '') {
            $xml_product['images'] = $images;
          }
        }

        // \Log::info(print_r($xml_product, true));

        if($this->validateData($xml_product)) {
          // TRY TO FIND EXISTE PRODUCT
          try {
            $response = $this->updateOrCreateItem($xml_product);
            $this->updateUploadHistory($response);
          }catch(\Exception $e) {
            $this->errorUploadHistory();
				    \Log::channel('xml')->error('updateOrCreateItem error: ' . $e->getMessage());
            // throw new \Exception $e;
            continue;
          }
        }else {
          $this->processedUploadHistory();
          continue;
        }

      }

      $this->setStatusUploadHistory('done');
    }

    /**
     * getItemFieldValue
     *
     * @param  mixed $item
     * @param  string $settingFieldName
     * @param  mixed $default
     * @return mixed
     */
    private function getItemFieldValue($item, $settingFieldName, $default = '') {
      $fieldName = $this->settings[$settingFieldName] ?? null;
      if(empty($fieldName)) {
        return $default;
      }

      if($this->isFieldInAttributes($settingFieldName)) {
        $attributeValue = $this->getItemAttributeValue($item, $fieldName);
        return $attributeValue === null ? $default : $attributeValue;
      }

      if(!isset($item->{$fieldName})) {
        return $default;
      }

      return $item->{$fieldName}->__toString();
    }

    /**
     * getItemImagesValue
     *
     * @param  mixed $item
     * @return mixed
     */
    private function getItemImagesValue($item) {
      $fieldName = $this->settings['fieldImage'] ?? null;
      if(empty($fieldName)) {
        return null;
      }

      if($this->isFieldInAttributes('fieldImage')) {
        return $this->getItemAttributeValue($item, $fieldName);
      }

      if(!isset($item->{$fieldName})) {
        return null;
      }

      return $item->{$fieldName};
    }

    /**
     * getItemAttributeValue
     *
     * @param  mixed $item
     * @param  string $attributeName
     * @return string|null
     */
    private function getItemAttributeValue($item, $attributeName) {
      $attributes = $item->attributes();
      if(!$attributes || !isset($attributes[$attributeName])) {
        return null;
      }

      return (string)$attributes[$attributeName];
    }


    /**
     * getXMLCatalog
     *
     * @return void
     */
    private function getXMLCatalog($url) {
	    try 
	    {
	    	$xml = simplexml_load_file($url);
	    }
	    catch(\Exception $e)
	    {  
		    $message = "Can't get products catalog: " . $e->getMessage();
		    
				\Log::channel('xml')->error($message);
				throw new \Exception($message);
			}
	    
      return $xml;
    }
}

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
        $this->totalRecords = $this->TEST_ITEMS === -1? count($item): $this->TEST_ITEMS;
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
          'category' => $item[$i]->{$this->settings['fieldCategory']}->__toString(),
          'name' => $item[$i]->{$this->settings['fieldName']}->__toString(),
          'brand' => $item[$i]->{$this->settings['fieldBrand']}->__toString(),
          'inStock' => $item[$i]->{$this->settings['fieldInStock']}->__toString(),
          'code' => $item[$i]->{$this->settings['fieldCode']}->__toString() ?? null,
          'barcode' => $item[$i]->{$this->settings['fieldBarcode']}->__toString() ?? null,
          'price' => $item[$i]->{$this->settings['fieldPrice']}->__toString()
        ];

        if(isset($this->settings['fieldImage']) && !empty($this->settings['fieldImage'])) {
          $xml_product['images'] = $item[$i]->{$this->settings['fieldImage']};
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
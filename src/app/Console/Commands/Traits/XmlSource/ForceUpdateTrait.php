<?php
namespace Backpack\Store\app\Console\Commands\Traits\XmlSource;

use Illuminate\Support\Facades\Http;

trait ForceUpdateTrait {
    /**
     * Method forceUpdateFields
     *
     * @param $product $product [explicite description]
     * @param $data $data [explicite description]
     *
     * @return void
     */
    private function forceUpdateFields($product, $data) {
      if(!isset($this->settings['forceUpdate']) || empty($this->settings['forceUpdate'])) {
        return;
      }

      $was_updated = false;

      // IMAGES UPDATE
      if(in_array('image', $this->settings['forceUpdate'])) {
        $was_updated = $this->forceUpdateImages($product, $data);
      }
      
      // NAME UPDATE
      if(in_array('name', $this->settings['forceUpdate'])) {
        $this->setProductName($product, $data);

        $was_updated = true;
      }

      // BRAND UPDATE
      if(in_array('brand', $this->settings['forceUpdate'])) {
        try {
          // Set brand to product
          $this->attachProductBrand($product, $data);
        }catch(\Exception $e) {
          throw new \Exception('Set Brand Error: ' . $e->getMessage());
        }

        $was_updated = true;
      }

      // CATEGORY UPDATE
      if(in_array('category', $this->settings['forceUpdate'])) {
        // no realisation

        $was_updated = true;
      }

      if($was_updated){
        $product->save();
      }
    }

    private function forceUpdateImages($product, $data) {

      $was_cleared = $this->clearBrokenImages($product);

      if($was_cleared) {
        try {
          $this->setProductImage($product, $data);
        }catch(\Exception $e) {
          throw new \Exception('Set Image Error: ' . $e->getMessage());
        }

        return true;
      }else {
        return false;
      }
    }
    
    /**
     * Method clearBrokenImages
     *
     * @param &$product $product [explicite description]
     *
     * @return boolean
     */
    private function clearBrokenImages(&$product){
      if(!is_array($product->images)) {
          $product->images = null;
          $this->line('images is not array, set to null: ' . $product->id);
          \Log::channel('xml')->info('images is not array, set to null: ' . $product->id);
          return true;
      }

      $good_images = array_filter($product->images, function($item) {
          if(isset($item['src']) && !empty($item['src'])) {
              return $this->checkRemoteImage($item['src']);
          }else {
              return false;
          }
      });

      if(!count($good_images)) {
          $this->line('NO VALID IMAGES for product: ' . $product->id);
          \Log::channel('xml')->error('NO VALID IMAGES for product: ' . $product->id);
          $product->images = null;
          return true;
      }else {
        return false;
      }
    }

    
    /**
     * Method checkRemoteImage
     *
     * @param $url $url [explicite description]
     *
     * @return void
     */
    public function checkRemoteImage($url) {
      $base_path = config('backpack.store.product.image.base_path', '/');
      $image_url = $base_path . $url;

      $response = Http::get($image_url);

      if($response->ok()) {
        $this->info('file exist: ' . $image_url);
        return true;
      }else {
        $this->error('file is broken: ' . $image_url);
        \Log::channel('xml')->error('file is broken: ' . $image_url);
        return false;
      }
    }
}
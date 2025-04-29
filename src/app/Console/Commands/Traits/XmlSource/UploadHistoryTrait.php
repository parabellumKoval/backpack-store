<?php

namespace Backpack\Store\app\Console\Commands\Traits\XmlSource;

use Backpack\Store\app\Models\UploadHistory;

trait UploadHistoryTrait {

    
    /**
     * createUploadHistory
     *
     * @return void
     */
    private function createUploadHistory() {
      $uh = new UploadHistory;
      $uh->source_id = $this->currentSource->id;
      $uh->status = 'pending';
      $uh->rules = $this->currentSource->rules;
      $uh->save();
      //
      $this->uploadHistory = $uh;
    }

    /**
     * setStatusUploadHistory
     *
     * @param  mixed $status
     * @return void
     */
    private function setStatusUploadHistory($status) {
      $uh = $this->uploadHistory;
      $uh->status = $status;
      $uh->save();
    }
    
    /**
     * processedUploadHistory
     *
     * @return void
     */
    private function processedUploadHistory() {
      $uh = $this->uploadHistory;
      $uh->processed_items += 1;
      $uh->save();
    }
    
    /**
     * errorUploadHistory
     *
     * @return void
     */
    private function errorUploadHistory() {
      $uh = $this->uploadHistory;
      $uh->processed_items += 1;
      $uh->error_items += 1;
      $uh->save();
    }

        
    /**
     * totalUploadHistory
     *
     * @param  mixed $total
     * @return void
     */
    private function totalUploadHistory($total) {
      $uh = $this->uploadHistory;
      $uh->total_items = $total;
      $uh->save();
    }
    
    
    /**
     * updateUploadHistory
     *
     * @param  mixed $data
     * @return void
     */
    private function updateUploadHistory($data) {
      $uh = $this->uploadHistory;
      $uh->processed_items += 1;

      if($data === 'update') {
        $uh->updated_items += 1;
      }else if($data === 'create') {
        $uh->new_items += 1;
      }

      $uh->save();
    }
}
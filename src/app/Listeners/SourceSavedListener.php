<?php
 namespace Backpack\Store\app\Listeners;
 
 use Illuminate\Support\Facades\Storage;

use Backpack\Store\app\Events\SourceSaved;
use Backpack\Store\app\Models\Source;
use Backpack\Store\app\Models\UploadHistory;

 
class SourceSavedListener
{

  private $source = null;
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(){}
 


    /**
     * Handle the event.
     *
     * @param  \App\Events\SourceSaved  $event
     * @return void
     */
    public function handle(SourceSaved $event)
    {
      $this->source = $event->source;
 
      if($this->source->type === 'file') {
        $this->removeFile();
      }
    }

    private function removeFile() {
      $oldFile = $this->source->getOriginal('file');
      $newFile = $this->source->file;

      $disk_name = 'excel';

      // dd($newFile, $oldFile);
      // Если файл пуст, удаляем старый
      if(empty($newFile) || $newFile != $oldFile) {
        if (!empty($oldFile) && Storage::disk($disk_name)->exists($oldFile)) {
            Storage::disk($disk_name)->delete($oldFile);
        }
      }
    }
}
<?php
 namespace Backpack\Store\app\Listeners;
 
use Backpack\Settings\Events\SettingsGroupChanged;
use Illuminate\Support\Facades\Artisan;
 
class SettingsGroupChangedListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {

    }
 
    /**
     * Handle the event.
     *
     * @param  \App\Events\OrderShipped  $event
     * @return void
     */
    public function handle(SettingsGroupChanged $event)
    {
      if($event->group !== 'search')
        return;


      if(!$event->diff || empty($event->diff))
        return;


        \Log::info('SettingsGroupChangedListener');
      Artisan::call('store:search:apply');
    }
}
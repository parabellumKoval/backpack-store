<?php
 namespace Backpack\Store\app\Listeners;
 
use Backpack\Store\app\Events\OrderUpdating;
 
class OrderUpdatingListener
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
     * @param  \App\Events\OrderUpdating  $event
     * @return void
     */
    public function handle(OrderUpdating $event)
    {
      dd($event);
    }
}
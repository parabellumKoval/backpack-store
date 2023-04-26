<?php
 namespace Backpack\Store\app\Listeners;
 
use Backpack\Store\app\Events\OrderCreated;
 
class OrderCreatedListener
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
    public function handle(OrderCreated $event)
    {
      // Access the order using $event->order...
    }
}
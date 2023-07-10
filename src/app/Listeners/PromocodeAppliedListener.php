<?php
 namespace Backpack\Store\app\Listeners;
 
use Backpack\Store\app\Events\PromocodeApplied;
use Backpack\Store\app\Models\Promocode;
 
class PromocodeAppliedListener
{

  private $order;
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
     * @param  \App\Events\PromocodeApplied  $event
     * @return void
     */
    public function handle(PromocodeApplied $event)
    {
      $this->order = $event->order;
      
      if($this->order->promocode && isset($this->order->promocode['code']) && !empty($this->order->promocode['code'])){
        $promocode = Promocode::whereRaw('LOWER(`code`) LIKE ? ',[trim(strtolower($this->order->promocode['code'])).'%'])->first();
        
        if(!$promocode)
          return;

        $promocode->used_times = $promocode->used_times + 1;
        $promocode->save();
      }

    }
}
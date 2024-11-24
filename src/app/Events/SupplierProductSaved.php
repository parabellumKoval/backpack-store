<?php

namespace Backpack\Store\app\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplierProductSaved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $sp;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($sp)
    {
      $this->sp = $sp;
    }

}

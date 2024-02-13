<?php

namespace Backpack\Store\app\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

use Backpack\Store\app\Models\Admin\Attribute as AttributeAdmin;

class AttributeSaved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $attribute;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(AttributeAdmin $attribute)
    {
      $this->attribute = $attribute;
    }
}

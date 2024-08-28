<?php

namespace Backpack\Store\app\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


class uploadFromXmlSource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
				
    private $source = null;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($value)
    {
      $this->source = $value;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
    }
    
}

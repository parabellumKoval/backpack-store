<?php

namespace Backpack\Store\app\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use Backpack\Store\app\Models\Product;

ini_set('memory_limit', '500M');

class ProductPreprocessing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'preprocessing:product';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update products big data';


    public $client = null;
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
	    $products = Product::where('id', '>', 0);
      $data_cursor = $products->cursor();
      $data_count = $products->count();

      $bar = $this->output->createProgressBar($data_count);
      $bar->start();

      foreach($data_cursor as $item) {
        try {
          $fill = $item->fillQuality;
          $item->setDataToExtras('fill_quality', $fill);
          $item->save();
        }catch(\Throwable $e) {
          Log::error('Fail to saveOriginalName', [
            'message' => $e->getMessage(),
            'trace'   => $e->getTraceAsString()
          ]);
        }

        $bar->advance();
      }

      $bar->finish();
    }


    public function processingFillQuality($item){

    }
}

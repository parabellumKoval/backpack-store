<?php

// namespace App\Console\Commands;
namespace Backpack\Store\app\Console\Commands;

use Illuminate\Console\Command;

use Backpack\Store\app\Models\Brand;
use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Supplier;
use Backpack\Store\app\Models\SupplierProduct;
use Backpack\Store\app\Models\Source;
use Backpack\Store\app\Models\UploadHistory;
use Backpack\Store\app\Jobs\uploadFromXmlSource;


class XmlCorrectInStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xml:correct-in-stock';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';


    protected $isSuppliersEnabled = false;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
      parent::__construct();
      $this->isSuppliersEnabled = \Settings::get('dress.supplier.enable', false);
    }


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
      $sources = Source::
                      where('is_active', 1)
                    ->where('supplier_id', '!=', null)
                    ->whereHas('history', function($query){
                      $query->where('status', 'done');
                    })
                    ->get();
      
      if($sources->count() === 0) {
        return 0;
      } 

      SupplierProduct::where(function($query) use ($sources){
        foreach($sources as $index => $source) {
          $function_name = $index === 0? 'where': 'orWhere';

          $last_history = $source->history()->orderBy('created_at', 'desc')->first();

          $supplier_id = $source->supplier_id;
          $last_loading = $last_history->created_at->subMinutes(30);

          $query->{$function_name}(function($query) use($supplier_id, $last_loading) {
            $query->where('supplier_id',  $supplier_id)
                  ->where(function($query) use ($last_loading){
                    $query->where('checked_at', '<', $last_loading)
                          ->orWhere('checked_at', null);
                  });
          });
        }
      })->where('in_stock', '>', 0)->update(['in_stock' => 0]);

    }
}
<?php

namespace Backpack\Store\app\Traits;
use Illuminate\Support\Facades\Log;

trait Exchange {
  /**
   * getExchangeRate
   *
   * @return void
   */
  private function getRate() {
    try 
    {
      $privat_rates = file_get_contents('https://api.privatbank.ua/p24api/pubinfo?json&exchange&coursid=5');
    }
    catch(\Exception $e)
    {
      $message = "Can't get exchange rates: " . $e->getMessage();
      
      Log::channel('xml')->error($message);
      throw new \Exception($message);
    }
    
    $exchange_rates = json_decode($privat_rates);
    
    $usd = array_filter($exchange_rates, function($item) {
      return $item->ccy === "USD";
    });
    
    $usd = reset($usd);
    
    
    return (float)$usd->sale;	    
  }
}
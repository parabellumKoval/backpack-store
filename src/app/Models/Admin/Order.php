<?php

namespace Backpack\Store\app\Models\Admin;

use Backpack\Store\app\Models\Order as BaseOrder;

class Order extends BaseOrder
{
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    
    /**
     * getStatusStringAttribute
     * 
     * Return HTML status string for Dashboard
     *
     * @return string
     */
    public function getStatusStringAttribute(){
	    if($this->status == 'new' || $this->status == 'pending' || $this->status == 'paid' || $this->status == 'sent')
	    	return '<span class="icon-sent order-history-icon"></span><span class="text">'.$this->status.'</span>';
	    elseif($this->status == 'canceled')
	    	return '<span class="icon-canceled order-history-icon"></span><span class="text" style="color: #EB5757;">CANCELED</span>';
	    else
	    	return '<span class="icon-delivered order-history-icon"></span><span class="text" style="color: #ACDA53;">delivered</span>';
    }
        
    /**
     * getAddressStringAttribute
     * 
     * Return address string from order. 
     * Expected for example: {firstname_lastname}, {phone}, {email} 
     *
     * @return string|null
     */
    public function getAddressStringAttribute(){
      if(!isset($this->info['address']) || !count($this->info['address']))
        return null;
      
      return implode(', ', $this->info['address']);
    }
    
    /**
     * getUserStringAttribute
     * 
     * Return user string from order. 
     * Expected for example: {firstname_lastname}, {phone}, {email} 
     *
     * @return string|null
     */
    public function getUserStringAttribute() {
      if(!isset($this->info['user'])  || !count($this->info['user']))
        return null;

      // Clear empty array values
      $arr = array_filter($this->info['user'], function($item) {
        return !empty($item);
      });
      
      // Join to string
      return implode(', ', $arr);
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    
}

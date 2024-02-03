<?php

namespace Backpack\Store\app\Exceptions;

use Exception;

class OrderException extends Exception
{

  private $_options = [];

  public function __construct($message = 'Order Validation Error', $code = 400, Exception $previous = null, $options = array()) 
  {
      parent::__construct($message, $code, $previous);
      $this->_options = $options; 
  }

  public function getOptions() { 
    return $this->_options;
  }
}

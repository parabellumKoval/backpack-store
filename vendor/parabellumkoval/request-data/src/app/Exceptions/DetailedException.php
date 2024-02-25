<?php
namespace Rd\app\Exceptions;

use Exception;

class DetailedException extends Exception
{

  private $_options = [];

  public function __construct($message = 'Request Validation Error', $code = 400, Exception $previous = null, $options = array()) 
  {
      parent::__construct($message, $code, $previous);
      $this->_options = $options; 
  }

  public function getOptions() { 
    return $this->_options;
  }
}

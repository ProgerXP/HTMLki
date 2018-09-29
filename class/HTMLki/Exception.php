<?php namespace HTMLki;

class Exception extends \Exception {
  public $obj;            //= null, HTMLki\object

  function __construct($obj, $msg, $code = 0, $previous = null) {
    parent::__construct($msg, $code, $previous);
    $this->obj = $obj;
  }
}

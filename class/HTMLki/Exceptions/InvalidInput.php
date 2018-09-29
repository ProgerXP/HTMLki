<?php namespace HTMLki\Exceptions;

class InvalidInput extends \HTMLki\Exception {
  public $variable;

  function __construct($obj, $variable, $what) {
    $this->variable = $variable;
    parent::__construct($obj, "Variable $$variable has wrong $what for the template.");
  }
}

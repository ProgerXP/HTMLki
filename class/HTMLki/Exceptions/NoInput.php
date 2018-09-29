<?php namespace HTMLki\Exceptions;

class NoInput extends InvalidInput {
  function __construct($obj, $variable) {
    $this->variable = $variable;
    Exception::__construct($obj, "HTMLkiTemplate is missing required variable $$variable.");
  }
}

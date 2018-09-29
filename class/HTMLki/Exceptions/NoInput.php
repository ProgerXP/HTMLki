<?php namespace HTMLki\Exceptions;

class NoInput extends InvalidInput {
  function __construct($obj, $variable) {
    $this->variable = $variable;
    Exception::__construct($obj, "Template is missing required variable $$variable.");
  }
}

<?php namespace HTMLki\Exceptions;

class PcreError extends \HTMLki\Exception {
  public $pcreCode;         //= int as returned by preg_last_error()

  function __construct($code = null) {
    $code = $this->pcreCode = isset($code) ? $code : preg_last_error();
    parent::__construct(null, "PCRE error #$code. Make sure your files are".
                        ' encoded in UTF-8.');
  }
}

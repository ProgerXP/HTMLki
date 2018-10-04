<?php namespace HTMLki;

class TagCall {
  public $raw;                //= string raw parameter string

  public $lists = [];         // list variable names without leading '$'
  public $defaults = [];      // default attributes without wrapping quotes
  public $attributes = [];    //= array of array('keyWr', 'key', 'valueWr', 'value')
  public $values = [];        //= array of array('valueWr', string)

  public $tag;                //= string
  public $isEnd = false;
  public $isSingle = false;

  public $tpl;                //= Template
  public $vars;               //= hash

  function __construct() {
    $this->clear();
  }

  function clear() {
    $this->lists = $this->defaults = [];
    $this->attributes = $this->values = [];

    $this->raw = $this->tag = null;
    $this->isEnd = $this->isSingle = false;
  }

  function handle() {
    return $this->regularTag($this);
  }

  function config() {
    return $this->tpl->config();
  }

  function call($tag) {
    $call = clone $this;
    $call->tag = $tag;
    return $this->tpl->callTag($call);
  }

  function __call($name, $arguments) {
    return call_user_func_array([$this->tpl, $name], $arguments);
  }

  function attributes($config = null) {
    $config or $config = $this->tpl->config();

    $attributes = $this->attributes;
    $defaults = $config->defaultAttributesOf($this->tag);
    $notEmpty = array_flip( $config->notEmptyAttributesOf($this->tag) );
    $flags = array_flip( $config->flagAttributesOf($this->tag) );
    $enums = $config->enumAttributesOf($this->tag);

    $result = $config->defaultsOf($this->tag);

    $values = $this->tpl->evaluateWrapped($this->vars, $this->values);
    foreach ($values as &$ref) { $ref = end($ref); }

    foreach ($this->defaults as $str) {
      $default = array_shift($defaults);
      if ($default) {
        $result[$default] = $str;
      } else {
        $values[] = $str;
      }
    }

    foreach ($values as $str) {
      $str = trim($str);
      $str === '' or $config->expandAttributeOf($this->tag, $str, $result);
    }

    // If $enumAttributes includes 'y' => '|' then given <tag x=1 x=2 y=1 y=2> 
    // initial $attributes (omitting wrapper members)
    // [['x', 1], ['x', 2], ['y', 1], ['y', 2]] become
    // ['x' => 2, 'y' => '1|2'].
    foreach ($attributes as $attr) {
      list(, $name, , $value) = $attr;

      if (!isset($enums[$name])) {   
        $result[$name] = $value;
      } elseif ($value) {   // loosely true - add to the set.
        $present = isset($result[$name]);
        $result[$name] = ($present ? $result[$name].$enums[$name] : '').$value;
      }
    }

    // unset() rewinds foreach.
    $names = array_keys($result);

    foreach ($names as $name) {
      $ref = &$result[$name];
      $ref = $config->callAttributeHookOf($this->tag, $name, $ref);

      if (isset($notEmpty[$name])) {
        $ref = trim($ref);
        if (strlen($ref) === 0) {
          unset($result[$name]);
        }
      } elseif (isset($flags[$name])) {
        if ($ref == true) {
          $ref = $name;
        } else {
          unset($result[$name]);
        }
      }
    }

    return $result;
  }
}

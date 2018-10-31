<?php namespace HTMLki;

class TagCall {
  public $raw;                //= string raw parameter string

  public $lists = [];         // list variable names without leading '$'
  public $defaults = [];      //= array of array('"', '"default attribute"')
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

  function originalConfig() {
    return $this->tpl->originalConfig();
  }

  function call($tag) {
    $call = clone $this;
    $call->tag = $tag;
    return $this->tpl->callTag($call);
  }

  function __call($name, $arguments) {
    return call_user_func_array([$this->tpl, $name], $arguments);
  }

  function evaluateAllWrapped(array $vars = null) {
    isset($vars) or $vars = $this->vars;
    $this->defaults = $this->tpl->evaluateWrapped($vars, $this->defaults);
    $this->attributes = $this->tpl->evaluateWrapped($vars, $this->attributes);
    $this->values = $this->tpl->evaluateWrapped($vars, $this->values);
  }

  function attributes($config = null) {
    $config or $config = $this->tpl->config();

    $this->evaluateAllWrapped();
    $attributes = $this->attributes;
    $defaults = $config->defaultAttributesOf($this->tag);
    $notEmpty = array_flip( $config->notEmptyAttributesOf($this->tag) );
    $flags = array_flip( $config->flagAttributesOf($this->tag) );
    $enums = $config->enumAttributesOf($this->tag);

    $result = $config->defaultsOf($this->tag);

    $values = array_map(function ($item) { return end($item); }, $this->values);

    foreach ($this->defaults as $item) {
      $default = array_shift($defaults);
      if ($default) {
        $result[$default] = end($item);
      } else {
        // If there are no more configured default slots, add ->$defaults as
        // just ->$values (attributes without key).
        $values[] = end($item);
      }
    }

    for ($i = count($values) - 1; $i >= 0; --$i) {
      $item = $values[$i];
      // <a { array_combine($k, $v) }> -> <a href="foo" class="bar" ...>
      if (is_array($item)) {
        $item = array_filter($item, function ($v, $k) use (&$attributes) {
          if (is_int($k)) {
            return true;
          } else {
            $attributes[] = ['-', $k, '-', $v];
          }
        }, ARRAY_FILTER_USE_BOTH);
        array_splice($values, $i, 1, $item);
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

      foreach ((array) $name as $name) {
        if (!isset($enums[$name])) {   
          $result[$name] = $value;
        } elseif ($value) {   // loosely true - add to the set.
          $present = isset($result[$name]);
          $result[$name] = ($present ? $result[$name].$enums[$name] : '').$value;
        }
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
          $ref = $config->xhtml ? $name : '';
        } else {
          unset($result[$name]);
        }
      }
    }

    return $result;
  }

  // Doesn't expand the values, process wrappers, add default ones, join enum
  // tags, etc. Should be only used when $attributes were expanded or wrappers
  // are not important.
  function attributeMap() {
    $attr = [];
    foreach (array_reverse($this->attributes) as $attr) {
      $attr += [$attr[1] => $attr[3]];
    }
    return $attr;
  }
}

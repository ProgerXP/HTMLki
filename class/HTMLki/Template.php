<?php namespace HTMLki;

class Template extends Object
               implements TemplateEnv, \ArrayAccess, \Countable {
  // Populated when Config->$debugEval is set.
  //= array ('eval_str();', array $vars, Template $tpl, $evalResult, str $output)
  static $lastEval;

  protected $str;             //= null, string
  protected $file;            //= null, string

  protected $vars = array();  //= hash

  protected $strParseVars;
  protected $strParseEscapeExpr;
  protected $strParseValues;

  function __construct(Config $config) {
    $this->config = $config;
  }

  function loadStr($str) {
    $this->file = null;
    $this->str = $str;
    return $this;
  }

  function loadFile($file) {
    $this->file = $file;
    $this->str = null;
    return $this;
  }

  function loadedStr()    { return $this->str; }
  function loadedFile()   { return $this->file; }
  function isStr()        { return isset($this->str); }
  function isFile()       { return isset($this->file); }

  function offsetExists($var) {
    return $this->get($var) !== null;
  }

  function offsetGet($var) {
    return $this->get($var);
  }

  function offsetUnset($var) {
    $this->add($var, null);
  }

  function offsetSet($var, $value) {
    $this->add($var, $value);
  }

  function count() {
    return count($this->vars);
  }

  function __toString() {
    return $this->render();
  }

  //= $this if $vars, array otherwise
  function vars(array $vars = null) {
    isset($vars) and $this->vars = $vars;
    return isset($vars) ? $this : $this->vars;
  }

  function get($var) {
    return isset($this->vars[$var]) ? $this->vars[$var] : null;
  }

  //? add('varname', 3.14)
  //? add('varname', true)
  //? add('varname')
  //? add(array('varname' => true))
  function add($var, $value = true) {
    if (is_array($var)) {
      $this->vars = $var + $this->vars;
    } elseif ($var) {
      $this->vars[$var] = $value;
    }

    return $this;
  }

  // function (array $vars)
  //
  // function (string $var, callable[, $var, callable[, ...]])
  // Last $var without a callable is ignored.
  function append() {
    $toName = null;

    foreach (func_get_args() as $arg) {
      if (isset($toName)) {
        if (!isset($this->vars[$toName])) {
          is_callable($arg) and $arg = call_user_func($arg, $this, $toName);
          $this->add($toName, $arg);
          $toName = null;
        }
      } elseif (is_array($arg)) {
        $args = array();
        foreach ($arg as $var => $value) { array_push($args, $var, $value); }

        call_user_func_array(array($this, __FUNCTION__), $args);
      } else {
        $toName = $arg;
      }
    }

    return $this;
  }

  function mailto($email, $subject = '', $extra = '') {
    $subject and $subject = "?subject=".rawurlencode($subject);
    return '&#109;a&#x69;&#108;&#x74;&#111;:'.$this->email($email).$subject.$extra;
  }

  function email($email) {
    $replaces = array('.' => '&#46;', '@' => '&#x40;');
    return strtr($email, $replaces);
  }

  //= string
  function render() {
    return $this->evaluate($this->vars);
  }

  protected function evaluate(array $_vars_) {
    extract($_vars_, EXTR_SKIP);
    ${$this->config->selfVar} = $this;

    ob_start();
    isset($this->file) ? include($this->file) : eval('?>'.$this->str);
    return ob_get_clean();
  }

  function evaluateStr($_str_, array $_vars_, $_wrapByConfig_ = true) {
    $_str_ = "return $_str_;";

    if ($_wrapByConfig_) {
      $_str_ = $this->config->evalPrefix.$_str_.$this->config->evalSuffix;
    }

    $_debug_ = $this->config->debugEval;
    $_buffering_ = ($_debug_ or $this->config->warnOnFalseEval);

    if ($_debug_) {
      static::$lastEval = array($_str_, $_vars_, $this);
    }

    extract($_vars_, EXTR_SKIP);
    // "Before PHP 7, in this case eval() returned FALSE and execution of the
    // following code continued normally. It is not possible to catch a parse
    // error in eval() using set_error_handler()."
    $_buffering_ and ob_start();
    $res = eval($_str_);
    $output = $_buffering_ ? ob_get_flush() : null;

    if ($_debug_) {
      array_push(static::$lastEval, $res, $output);
      is_callable($_debug_) and call_user_func_array($_debug_, static::$lastEval);
    }

    if ($res === false and $this->config->warnOnFalseEval
        and strpos($output, 'Parse error') !== false) {
      $this->warning("possible syntax error in eval() code: $_str_", HTMLki::WARN_RENDER + 5);
    }

    return $res;
  }

  // $...    $$...   {...}   {{...
  function parseStr($str, array &$vars, $escapeExpr = false) {
    $this->strParseValues = array();

    if (strpbrk($str, '${') !== false) {
      $regexp = Compiler::inlineRegExp().'|'.Compiler::braceRegExp('{', '}');
      $regexp = "~$regexp~".$this->config->regexpMode;

      $this->strParseVars = $vars;
      $this->strParseEscapeExpr = $escapeExpr;
      $str = preg_replace_callback($regexp, array($this, 'strParser'), $str);
      HTMLki::pcreCheck($str);
    }

    return array($str, $this->strParseValues);
  }

    function strParser($match) {
      $match[0][0] === '$' or array_splice($match, 1, 1);
      list($full, $code) = $match;

      if (ltrim($code, $full[0]) === '') {
        $value = $code;
      } elseif ($full[0] === '$') {
        if (isset($this->strParseVars[$code])) {
          $value = $this->strParseVars[$code];
        } else {
          $value = $this[$code];
        }
      } else {
        $code = substr($code, 0, -1);

        $escaped = (!$this->strParseEscapeExpr or $code[0] === '=');
        $code[0] === '=' and $code = substr($code, 1);

        $value = $this->evaluateStr($code, $this->strParseVars + $this->vars);
        $escaped or $value = $this->escape($value);
      }

      do {
        $key = Compiler::Raw0. count($this->strParseValues) .Compiler::Raw1;
      } while (isset($this->strParseValues[$key]));

      $this->strParseValues[$key] = $value;
      return $key;
    }

  function formatStr($str, array &$vars) {
    list($str, $values) = $this->parseStr($str, $vars);
    return strtr($str, $values);
  }

  function lang($str, array $vars = array(), $escapeExpr = true) {
    list($str, $values) = $this->parseStr(trim($str), $vars, $escapeExpr);
    return $this->formatLang($str, $values);
  }

  function formatLang($str, array $values = array()) {
    if ($func = $this->config->language) {
      $placeholders = $this->placeholders($values);

      $str = str_replace(array_keys($values), $placeholders, $str);
      $str = call_user_func($func, $str);
      return str_replace($placeholders, $values, $str);
    } else {
      return strtr($str, $values);
    }
  }

  protected function placeholders(array $values) {
    $result = range(1, count($values));
    foreach ($result as &$i) { $i = ":$i"; }
    return $result;
  }

  // null $default assumes that $name is required. '' $default sets it
  // according to given $type (blank string for 'string', empty array for 'array',
  // null for '' (any), etc.).
  function input($vars, $name, &$value, $type, $coersible,
                 $default = null, $condition = '') {
    $failOn = null;
    $func = "is_$type";
    $given = array_key_exists($name, $vars);
    $required = $default === null;
    $defNull = ($default === '' or $default === 'null');

    if ($given and $defNull and $value === null) {
      $given = false;
    }

    if (!$given) {
      $vars[$name] = $value;

      if ($required) {
        throw new NoInput($this, $name);
      } elseif (!$defNull) {
        $value = $this->evaluateStr($default, $vars);
      } else {
        $default === '' and $value = $this->defaultForInput($type);
        $type = $condition = '';
      }
    }

    if (!$failOn and $type !== '' and !$func($value) and
        (!$coersible or !$this->coerseInput($type, $value))) {
      $failOn = 'type';
    }

    if (!$failOn and $condition !== '' and !$this->evaluateStr($condition, $vars)) {
      $failOn = 'value';
    }

    if (!$failOn) {
      return true;
    } elseif ($required) {
      $failOn .= ' ('.gettype($value).')';
      throw new InvalidInput($this, $name, $failOn);
    } else {
      $this->warning("wrong $failOn for $>$name - using default value", HTMLki::WARN_RENDER + 4);
      $value = $this->evaluateStr($default, $vars);
    }
  }

  function defaultForInput($type) {
    switch ($type) {
    case 'array':
      return array();
    case 'object':
      return new \stdClass;
    case 'bool':
      return false;
    case 'integer':
      return 0;
    case 'float':
      return 0.0;
    case 'string':
      return '';
    }
  }

  function coerseInput($type, &$value) {
    $null = $value === null;

    switch ($type) {
    case 'bool':
      if ($null or is_scalar($value)) {
        $str = (string) $value;
        $coersed = ($str === '' or $str === '0' or $str === '1');
      }
      $coersed and $value = (bool) $value;
      break;
    case 'integer':
      $coersed = ($null or filter_var($value, FILTER_VALIDATE_INT) !== false);
      $coersed and $value = (int) $value;
      break;
    case 'float':
      $coersed = ($null or is_int($value) or
                  filter_var($value, FILTER_VALIDATE_FLOAT) !== false);
      $coersed and $value = (float) $value;
      break;
    case 'string':
      $coersed = ($null or is_scalar($value));
      $coersed and $value = (string) $value;
      break;
    default:
      $coersed = false;
    }

    return $coersed;
  }

  // * $params string
  // = TagCall
  function parseParams($params, array $vars) {
    $call = new TagCall;
    $call->tpl = $this;

    $call->raw = $params = trim($params);

    while ($params !== '') {
      if ($params[0] === '$') {
        // <tag ${list...} params...>
        list($list, $params) = HTMLki::split(' ', substr($params, 1));

        if ($start = strrchr($list, '{') and !strrchr($start, '}')) {
          // <tag ${ list... } params...>
          list($end, $params) = HTMLki::split('}', $params);
          $list .= " $end}";
          if (($ch = substr($params, 0, 1)) > ' ') {
            // Convertor suffix: <tag ${ ... }? ...>
            $list .= $ch;
            $params = substr($params, 1);
          }
        }

        $call->lists[] = $list;
        $params = ltrim($params);
        continue;
      } elseif ($params[0] === '"') {
        list($default, $rest) = explode('"', substr($params, 1), 2);

        if ($rest === '' or $rest[0] !== '=') {
          $call->defaults[] = $this->formatStr($default, $vars);
          $params = ltrim($rest);
          continue;
        }
      }

      break;
    }

    if ($params !== '') {
      $name = Compiler::wrappedRegExp();
      $regexp = "~(\s|^)
                    (?: ($name|[^\s=]*) =)? ($name|[^\s]+)
                  (?=\s|$)~x".$this->config->regexpMode;

      if (!preg_match_all($regexp, $params, $matches, PREG_SET_ORDER)) {
        $original = $call->raw === $params ? '' : "; original: [$call->raw]";
        $this->warning("Cannot parse parameter string [$params]$original.", HTMLki::WARN_RENDER + 1);
      }

      // <tag x= y=z w>
      // x=     array('x=', '', '', 'x=')
      // y=z    array(' y=z', ' ', 'y', 'z')
      // w      array(' w', ' ', '', 'w')
      foreach ($matches as $match) {
        list(, , $key, $value) = $match;
        $valueWrapper = $this->wrapperOf($value);

        if ($key !== '') {
          $keyWrapper = $this->wrapperOf($key);
          $call->attributes[$key] = array($keyWrapper, $valueWrapper, $value);
        } elseif ($value[strlen($value) - 1] === '=') {
          // 'x=' - an attribute with empty value.
          $value = substr($value, 0, -1);
          $call->attributes[$value] = array('', $valueWrapper, $value);
        } else {
          $call->values[] = array($valueWrapper, $value);
        }
      }
    }

    return $call;
  }

  protected function wrapperOf($str) {
    switch ($str[0]) {
    case '"':
    case '{':   return $str[0];
    default:    return '';
    }
  }

  function setTagAttribute($tag, $key, array $attributes = array()) {
    $attributes or $attributes = array($key);
    $config = $this->ownConfig();

    foreach ($attributes as $value) {
      $config->shortAttributes[$tag][$key] = $value;

      if (strrchr($value, '=') === false) {
        $config->flagAttributes[$tag][] = $value;
      }
    }
  }

  function startTag($tag, $params = '', array $vars = array()) {
    $call = $this->parseParams($params, $vars);

    $call->tag = $tag;
    $call->vars = $vars;

    return $this->callTag($call);
  }

  function endTag($tag, $params = '', array $vars = array()) {
    $call = $this->parseParams($params, $vars);

    $call->tag = $tag;
    $call->vars = $vars;
    $call->isEnd = true;

    return $this->callTag($call);
  }

  function singleTag($tag, $params = '', array $vars = array()) {
    $call = $this->parseParams($params, $vars);

    $call->tag = $tag;
    $call->vars = $vars;
    $call->isEnd = true;
    $call->isSingle = true;

    return $this->callTag($call);
  }

  function callTag(TagCall $call) {
    $call->tag === '' and $call->tag = $this->config->defaultTag;

    $tag = strrchr($call->tag, '/');
    if ($tag !== false) {
      $this->multitag(substr($call->tag, 0, -1 * strlen($tag)), $call);
      $call->tag = substr($tag, 1);
    }

    $handler = $call->tag;
    $params = '';

    while ($alias = &$this->config->tags[$handler]) {
      if (is_string($alias)) {
        list($handler, $params) = explode(' ', "$alias ", 2);
      } elseif (is_array($alias) and is_string($alias[0]) and
                ltrim($alias[0], 'A..Z') !== '') {
        $params = $alias;
        $handler = array_shift($params);
      } else {
        $handler = $alias;
        $params = null;
        break;
      }

      $this->applyCallAliasTo($call, $handler, $params);
    }

    if (isset($params)) {
      $func = "tag_$handler";
      method_exists($this, $func) or $func = 'regularTag';

      $result = $this->$func($call);
    } elseif (!is_callable($handler)) {
      $handler = var_export($handler, true);
      $this->warning("Invalid tag handler [$handler]; original tag name: [{$call->tag}].", HTMLki::WARN_RENDER + 2);

      $result = null;
    } else {
      $call->attributes = $this->evaluateWrapped($call->vars, $call->attributes);
      $call->values = $this->evaluateWrapped($call->vars, $call->values);

      $result = call_user_func($handler, $call, $this);
    }

    return is_array($result) ? $result : array();
  }

  protected function applyCallAliasTo($call, $handler, $params) {
    $call->tag = $handler;
    is_array($params) or $params = explode(' ', $params);

    foreach ($params as $key => $value) {
      if (is_int($key)) {
        if ($value === '') { continue; }

        list($key, $value) = HTMLki::split('=', $value);
        isset($value) or $value = $key;
      }

      if (!isset($call->attributes[$key])) {
        $call->attributes[$key] = array('', '', $value);
      }
    }
  }

  function multitag($tags, TagCall $call) {
    $tags = array_filter( is_array($tags) ? $tags : explode('/', $tags) );

    if ($call->isEnd) {
      foreach ($tags as $tag) { echo "</$tag>"; }
    } else {
      $call = clone $call;
      $call->clear();

      foreach ($tags as $tag) {
        $call->tag = $tag;
        echo $this->htmlTagOf($call, $tag);
      }
    }
  }

  function evalListName($name, array $vars = array()) {
    $parsed = $this->parseListName($name);
    $parsed[0] = $this->getList($parsed[0], $vars, $parsed[3]);
    return $parsed;
  }

  // * $name string - format: "[name] [[{] expr [}]] [?]"
  function parseListName($name) {
    list($prefix, $expr) = HTMLki::split('{', trim($name));

    if ($expr === null) {
      // <t $expr> -> <t ${ $expr }>
      $expr = "$$prefix";
      $prefix = '';
    }

    $suffix = $prefix;
    if ($prefix === '_') {
      $prefix = false;    // disable variable creation, only condition checking.
    } elseif ($prefix !== '') {
      $prefix .= '_';
      $suffix = "_$suffix";
    }

    $convertor = substr($expr, -1);
    if ($convertor === '?') {
      $prefix = false;
    } else {
      $convertor = '';
    }

    $convertor === '' or $expr = substr($expr, 0, -1);
    return array(rtrim($expr, ' }'), $prefix, $suffix, $convertor);
  }

  // * $name string - an expression; if begins with '$' this list var is
  //   retrieved using Config->listVariable callback.
  function getList($name, array $vars = array(), $convertor = '') {
    $name = trim($name);

    if ($name === '') {
      $result = null;
    } elseif ($name[0] === '$' and $func = $this->config->listVariable) {
      $var = substr($name, 1);
      $expr = ltrim($var, 'a..zA..Z0..9_');
      $var = substr($var, 0, strlen($var) - strlen($expr));

      $value = call_user_func($func, $var, $this);

      if (isset($value) and trim($expr) === '') {
        $result = $value;
      } else {
        $vars += array($var => $value);
      }
    }

    isset($result) or $result = $this->evaluateStr($name, $vars);

    if ($convertor === '?') {
      return $result ? array(array()) : array();
    } elseif ($result === null or $result === false) {
      return array();
    } elseif (!is_array($result) and !($result instanceof Traversable)) {
      return array($result);
    } else {
      return $result;
    }
  }

  function setList($name, array $value = null) {
    $this[$name] = $value;
    return $this;
  }

  function regularTag($call) {
    $tag = $call->tag;

    $isCollapsed = ($call->isSingle and !in_array($tag, $this->config->singleTags));
    $call->isSingle &= !$isCollapsed;
    $call->isEnd &= !$isCollapsed;

    if ($call->isSingle) {
      // <... />
      if ($call->lists) {
        $self = $this;
        $this->loop($call, function ($vars) use ($self, $call)  {
          $call = clone $call;
          // unlike other loop calbacks <single $list /> doesn't return to the view
          // where extract() adds iteration variables to common pool; this means we
          // should add already defined variables in the template's scope manually:
          $vars += $call->vars;

          foreach ($call->defaults as &$s) {
            $s = $self->evaluateWrapped($vars, '', $s);
          }

          $call->attributes = $self->evaluateWrapped($vars, $call->attributes);
          $call->values = $self->evaluateWrapped($vars, $call->values);

          echo $self->htmlTagOf($call, $call->tag);
        });
      } else {
        $call->attributes = $this->evaluateWrapped($call->vars, $call->attributes);
        $call->values = $this->evaluateWrapped($call->vars, $call->values);

        echo $this->htmlTagOf($call, $tag);
      }
    } elseif ($call->isEnd) {
      // </...>
      if ($call->lists) {
        $lists = join(', ', $call->lists);
        $this->warning("</$tag> form cannot be called with list data: [$lists].", HTMLki::WARN_RENDER + 3);
      }

      echo "</$tag>";
    } elseif ($call->lists) {  // <...>
      $allVars = array();

      $this->loop($call, function ($vars) use (&$allVars) {
        $allVars[] = $vars;
      });

      if ($allVars) {
        $call->attributes = $this->evaluateWrapped($call->vars, $call->attributes);
        $call->values = $this->evaluateWrapped($call->vars, $call->values);

        echo $this->htmlTagOf($call, $tag);
      }

      return $allVars;
    } else {
      $call->attributes = $this->evaluateWrapped($call->vars, $call->attributes);
      $call->values = $this->evaluateWrapped($call->vars, $call->values);

      echo $this->htmlTagOf($call, $tag);
    }

    if ($isCollapsed) { echo "</$tag>"; }
  }

  // $listNameVars is only used to evaluate list name, not passing to $callback.
  protected function loop($lists, $callback, array $listNameVars = array()) {
    if ($lists instanceof TagCall) {
      $listNameVars = $lists->vars;
      $lists = $lists->lists;
    }

    foreach ($lists as $list) {
      $i = -1;
      list($list, $prefix, $suffix) = $this->evalListName($list, $listNameVars);
      $noVars = $prefix === false;

      if ($noVars) {
        for ($i = count($list); $i > 0; --$i) {
          call_user_func($callback, array());
        }
      } else {
        foreach ($list as $key => $item) {
          $vars = array(
            "key$suffix"      => $key,
            "i$suffix"        => ++$i,
            "item$suffix"     => $item,
            "isFirst$suffix"  => $i === 0,
            "isLast$suffix"   => $i >= count($list) - 1,
            "isEven$suffix"   => $i % 2 == 0,
            "isOdd$suffix"    => $i % 2 == 1,
          );

          if (is_array($item)) {
            foreach ($item as $key => $value) {
              $vars[$prefix.$key] = $value;
            }
          }

          call_user_func($callback, $vars);
        }
      }
    }
  }

  // * $tag string
  // = string HTML
  function htmlTagOf(TagCall $call, $tag, $isSingle = null) {
    $isSingle === null and $isSingle = $call->isSingle;
    return $this->htmlTag($tag, $call->attributes(), $isSingle);
  }

  // = string HTML
  function htmlTag($tag, array $attributes = array(), $isSingle = false) {
    $end = ($isSingle and $this->config->xhtml) ? ' /' : '';
    $attributes = $this->htmlAttributes($attributes);
    return "<$tag$attributes$end>";
  }

  function htmlAttribute($str, $trim = true) {
    $trim and $str = trim($str);

    if (strrchr($str, '"') === false) {
      return '"'.$this->escape($str).'"';
    } else {
      return '\''.str_replace("'", '&#039;', $this->escape($str, ENT_NOQUOTES)).'\'';
    }
  }

  function escape($str, $quotes = ENT_COMPAT, $doubleEncode = true) {
    if (is_array($str)) {
      foreach ($str as &$s) { $s = $this->escape($s, $quotes, $doubleEncode); }
    } else {
      return htmlspecialchars($str, $quotes, $this->config->charset, $doubleEncode);
    }
  }

  function htmlAttributes($attributes) {
    $result = '';

    foreach ($attributes as $name => $value) {
      $result .= ' '.$this->escape(trim($name)).'='.$this->htmlAttribute(trim($value));
    }

    return $result;
  }

  function evaluateWrapped(array &$vars, $wrapper, $value = null) {
    if (func_num_args() == 2) {
      $keys = array();

      foreach ($wrapper as $key => &$ref) {
        if (is_int($key)) {
          list($valueWrapper, $value) = $ref;
        } else {
          list($keyWrapper, $valueWrapper, $value) = $ref;
          $keys[] = $this->evaluateWrapped($vars, $keyWrapper, $key);
        }

        $last = count($ref) - 1;
        $ref[$last - 1] = '-';
        $ref[$last] = $this->evaluateWrapped($vars, $valueWrapper, $value);
      }

      return $keys ? array_combine($keys, $wrapper) : $wrapper;
    } elseif ($wrapper === '-') {
      return $value;
    } elseif ($wrapper === '{') {
      $value = substr($value, 1, -1);
      return $this->evaluateStr($value, $vars);
    } else {      // " or (none)
      $wrapper === '"' and $value = substr($value, 1, -1);
      return $this->formatStr($value, $vars);
    }
  }

  protected function tag_include($call) {
    $func = $this->config->template;

    if (!$func) {
      $this->warning("Cannot <include> a template - no \$template config handler set.", HTMLki::WARN_TAG + 1);
    } elseif (!$call->defaults) {
      $this->warning("Cannot <include> a template - no \"name\" given.", HTMLki::WARN_TAG + 2);
    } else {
      $listNameVars = $call->vars;

      if (!$call->values and !$call->attributes) {
        $call->vars += $this->vars;
      } elseif ($call->values and end($call->values[0]) === '0') {
        $included = $this->makeIncludeVars($call, 2);

        if (count($call->values) > 1) {
          $var = end($call->values[1]);
          if (isset($listNameVars[$var]) and is_array($listNameVars[$var])) {
            $included += $listNameVars[$var];
          } else {
            $inCondition = false;

            foreach ($call->lists as $list) {
              if ($inCondition = strpos(" $list", "$$var")) { break; }
            }

            if (!$inCondition) {
              // Believe that if $var is referenced in <include ${...}> then
              // the programmer knows why and when he's passing it.
              $this->warning("<include 0 $var> referenced an undefined/non-array value.", HTMLki::WARN_TAG + 3);
            }
          }
        }

        $call->vars = $included;
      } else {
        $call->vars = $this->makeIncludeVars($call);
      }

      $tpl = $file = call_user_func($func, $call->defaults[0], $this, $call);

      if (is_string($tpl)) {
        $config = $this->config->inheritConfig ? 'config' : 'originalConfig';

        $tpl = new static($this->$config());
        $tpl->loadFile($file);
        $tpl->vars($call->vars);
      }

      if ($call->lists) {
        $this->loop($call->lists, function ($vars) use ($tpl) {
          $tpl->add($vars);
          echo $tpl->render();
        }, $listNameVars);
      } else {
        echo $tpl->render();
      }
    }
  }

  protected function makeIncludeVars($call, $firstValue = 0) {
    $values = $this->evaluateWrapped($call->vars, $call->values);
    $attrs = $this->evaluateWrapped($call->vars, $call->attributes);
    $vars = array();

    foreach ($values as $value) {
      if (--$firstValue >= 0) { continue; }
      $name = strtok(end($value), '-');

      if (array_key_exists($name, $call->vars)) {
        $value = $call->vars[$name];
      } else {
        $this->warning("Passing on an undefined variable \$$name in <include>.", HTMLki::WARN_TAG + 4);
        $value = null;
      }

      $name = strtok(null) ?: $name;
      $vars[$name] = $value;
    }

    foreach ($attrs as $name => $attr) {
      $vars[$name] = end($attr);
    }

    return $vars;
  }

  protected function tag_each($call) {
    if (!$call->isEnd) {
      if ($call->lists) {
        $allVars = array();

        $this->loop($call, function ($vars) use (&$allVars) {
          $allVars[] = $vars;
        });

        if ($allVars and $call->attributes) {
          $first = &$allVars[0];
          $attrs = $this->evaluateWrapped($call->vars, $call->attributes);
          foreach ($attrs as $name => $attr) { $first[$name] = end($attr); }
        }

        return $allVars;
      } else {
        $this->warning('<each> called without list name.', HTMLki::WARN_TAG + 5);
      }
    }
  }

  protected function tag_if($call) {
    if (!$call->isEnd) {
      $holds = $this->evaluateStr($call->raw, $call->vars);
      return $holds ? array(array()) : array();
    }
  }

  protected function tag_lang($call) {
    $vars = $call->vars + $this->evaluateWrapped($call->vars, $call->attributes);

    foreach ($call->defaults as $lang) {
      $values = $this->evaluateWrapped($call->vars, $call->values);

      if ($values) {
        foreach ($values as &$ref) { $ref = end($ref); }
        $values = array_combine($this->placeholders($values), $values);
      }

      echo $this->formatLang($this->evaluateStr("\"$lang\"", $vars), $values);
    }
  }

  protected function tag_mailto($call) {
    if ($call->isEnd and !$call->isSingle) {
      echo '</a>';
    } else {
      $email = reset($call->defaults);
      echo '<a href="'.$this->mailto($email, next($call->defaults)).'">';

      if ($call->isSingle) { echo $this->email($email), '</a>'; }
    }
  }
}

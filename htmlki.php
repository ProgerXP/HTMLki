<?php
/*
  HTMLki - seamless templating with the HTML spirit
  in public domain | by Proger_XP | http://proger.me
  https://github.com/ProgerXP/HTMLki
*/

HTMLki::$config = new HTMLkiConfig;

class HTMLkiError extends Exception {
  public $obj;            //= null, object, HTMLkiObject

  function __construct($obj, $msg, $code = 0) {
    parent::__construct($msg, $code);
    $this->obj = $obj;
  }
}

class HTMLkiPcreError extends HTMLkiError {
  public $pcreCode;         //= int as returned by preg_last_error()

  function __construct($code = null) {
    $code = $this->pcreCode = isset($code) ? $code : preg_last_error();
    parent::__construct(null, "PCRE error #$code. Make sure your files are".
                        ' encoded in UTF-8.');
  }
}

class HTMLkiInvalidInput extends HTMLkiError {
  public $variable;

  function __construct($obj, $variable, $what) {
    $this->variable = $variable;
    parent::__construct($obj, "Variable $$variable has wrong $what for the template.");
  }
}

class HTMLkiNoInput extends HTMLkiInvalidInput {
  function __construct($obj, $variable) {
    $this->variable = $variable;
    HTMLkiError::__construct($obj, "HTMLkiTemplate is missing required variable $$variable.");
  }
}

class HTMLki {
  static $config;             //= HTMLkiConfig
  static $configs = array();  //= hash of HTMLkiConfig

  const WARN_COMPILE = 0;
  const WARN_RENDER = 50;
  const WARN_TAG = 100;

  // function (string $name)                      - return $name
  // function (string $name, HTMLkiConfig $new)  - set $new to $name and return it
  // function ('', HTMLkiConfig $new)            - set default config
  // function (HTMLkiConfig $config)             - return $config
  // function ()                                  - return default config (not its copy)
  static function config($return = null, HTMLkiConfig $new = null) {
    if (is_string($return)) {
      if ("$return" === '') {
        $return = &static::$config;
      } else {
        $return = &static::$configs[$return];
      }

      isset($new) and $return = $new;
    }

    return $return ?: static::$config;
  }

  //= string
  static function compile($str, $config = null) {
    $obj = new HTMLkiCompiler(static::config($config), $str);
    return $obj->compile();
  }

  //* $file str - path to HTMLki template file.
  //* $cachePath str - path to a folder where compiled templates are stored.
  //
  //= string
  //
  //? compileFileCaching('tpl/my.ki.html', 'cache/htmlki/')
  static function compileFileCaching($file, $cachePath, $config = null) {
    $hint = strtok(basename($file), '.');
    $cache = rtrim($cachePath, '\\/')."/$hint-".md5($file).'.php';

    if (!is_file($cache) or filemtime($cache) < filemtime($file)) {
      $res = static::compileFile($file, $config);
      is_dir($cachePath) or mkdir($cachePath, 0750, true);
      file_put_contents($cache, $res, LOCK_EX);
      return $res;
    } else {
      return file_get_contents($cache);
    }
  }

  //= string
  static function compileFile($file, $config = null) {
    $str = file_get_contents($file);

    if (!is_string($str)) {
      throw new HTMLkiError(null, "Cannot compile template [$file] - it doesn't exist.");
    }

    return static::compile($str, $config);
  }

  //= HTMLkiTemplate
  static function template($str, $config = null) {
    $obj = new HTMLkiTemplate(static::config($config));
    return $obj->loadStr($str);
  }

  //= HTMLkiTemplate
  static function templateFile($file, $config = null) {
    $obj = new HTMLkiTemplate(static::config($config));
    return $obj->loadFile($file);
  }

  //= $result
  static function pcreCheck($result = null) {
    $code = preg_last_error();

    if ($code == PREG_NO_ERROR) {
      return $result;
    } else {
      throw new HTMLkiPcreError($code);
    }
  }

  // $separ must be single character.
  static function split($separ, $str) {
    $tail = strrchr($str, $separ);
    if ($tail === false) {
      return array($str, null);
    } else {
      return explode($separ, $str, 2);
    }
  }
}

class HTMLkiConfig {
  /*-----------------------------------------------------------------------
  | COMMON OPTIONS
  |----------------------------------------------------------------------*/

  public $regexpMode = 'u';

  //= callable ($msg, HTMLkiObject $issuer)
  public $warning;

  // Name of variable referring to HTMLkiTemplate object (valid PHP identifier).
  // Must match on compile and rendering times.
  public $selfVar = '_ki';

  /*-----------------------------------------------------------------------
  | COMPILER-SPECIFIC
  |----------------------------------------------------------------------*/

  // Listed here compilers will be called unless also listed in $omitCompilers.
  public $compilers = array('lineMerge', 'php', 'varSet', 'tags', 'echo',
                            'lang', 'varEcho');

  // Two options (this and above) exist for easier overriding. Listed here but
  // not in $compilers cause nothing.
  //
  // 'lang' is disabled by default. It treats "quoted" text as language strings.
  // If enabled be prepared that   This "is" text   would turn into   This is text
  // (quotes are gone since "is" was "translated" into itself with no $language
  // callback set). Also controls ""escaping"".
  public $omitCompilers = array('lang');

  // If enabled tags start <start and end> on different lines are parsed and
  // merged. If disabled all tags should fit on one line (even if long).
  // Enabling can interfere with embedded scripts like this:
  //   if (s.match(/<a /)) { (ln) s += '>'
  public $multilineTags = false;

  public $singleTags = array('area', 'base', 'basefont', 'br', 'col', 'frame',
                             'hr', 'img', 'input', 'link', 'meta', 'param',
                             'lang', 'include');

  public $loopTags = array('each', 'if');

  // If true, all tags are always given get_defined_vars(). If false, all tags
  // receive empty array for vars. Otherwise an array of tag names to receive
  // all vars while others receive selective vars: <$variable> tags and tags with
  // { code } always get all vars, tags with $interpolations get only those
  // $vars, tags with neither get no vars (fastest).
  public $grabAllVarsTags = array('if', 'include', 'lang');

  // If set no guessing will be done to figure used variables for any expressions
  // including <tags> (above), "lang" and $>input@vars - they all will receive
  // full scope.
  public $grabAllVars = false;

  // Even regular tags (not loops or branches) can return variables to be
  // extract()'ed into the template scope. However, by default they don't and
  // putting extract() over each single tag call is performance-unwise. If this
  // is true, all tags are extracted, otherwise is an array of tag names to
  // extract. Tags with <t $list>, $loopTags and <$var> tags are always extracted.
  public $extractTags = array();

  // Format is like $extractTags. Control if tags that are not looping, branching,
  // shortcut tags (defined in $tags) and have no parameters are output <as> </is>.
  // If either is enabled you can no more track opening/closing tags with hooks
  // because they are output as raw text which is good for performance. Precise
  // tracking only makes sense if using custom tags or other advanced HTMLki stuff.
  public $rawStartTags = true;
  public $rawEndTags = true;

  public $addLineBreaks = true;

  //= string to start each compiled .php file with
  public $compiledHeader = '';

  //= string to end each compiled .php file with
  public $compiledFooter = '';

  // Values must correspond to existing is_XXX() functions.
  public $typeAliases = array(
    'boolean' => 'bool', 'num' => 'integer', 'int' => 'integer',
    'double' => 'float', 'real' => 'float', 'str' => 'string',
    'hash' => 'array', 'map' => 'array', 'obj' => 'object',
    'res' => 'resource',
  );

  /*-----------------------------------------------------------------------
  | RENDERING-SPECIFIC
  |----------------------------------------------------------------------*/

  // If true - stores evaluateStr() calls' info in HTMLkiTemplate::$lastEval.
  // If a callable - gets called after eval() has ran with arguments equal
  // to $lastEval contents. You can use it to look for "Parse error:"s,
  // undefined/mistyped variables, etc.
  public $debugEval = false;

  public $xhtml = true;

  // Used to quote HTML strings.
  public $charset = 'utf-8';

  //= null autodetect from short_open_tag from php.ini, bool
  public $shortPhp = null;

  // If true enables compilation of braceless outer function calls like in Ruby:
  // { number_format 1.23 }. It shouldn't interfere with normal PHP code.
  public $rubyLike = true;

  // If true and current template's config has been changed (see
  // HTMLkiObject->ownConfig()) included templates will inherit the changed
  // copy; otherwise they will get the config the parent template initially had.
  public $inheritConfig = true;

  //= string prepended to the evaluating string expression. See ->compiledHeader.
  public $evalPrefix = '';

  //= string appended to the evaluating string expression. See ->compiledFooter.
  public $evalSuffix = '';

  // Prior to PHP 7, syntax errors in eval() cannot be caught; the only indication
  // is eval() returning false and "Parse error: ..." present in the output.
  // Both conditions can happen in normal operation so it's unreliable but useful
  // while debugging. Enabling this will have some performance impact due to
  // numerous ob_start() calls.
  public $warnOnFalseEval = false;

  // Tag used when tag name is omitted, e.g. <"class"> -> <span "class">.
  // Works for closing tag as well: </> -> </span>.
  // Is used exactly as if it appears in the template - it can be a multitag,
  // regular tag attributes (flags, defaults) are used and so on.
  public $defaultTag = 'span';    //= string

  // Format of member items:
  // * string => string - alias of another tag: 'tag[ attr=value[ a2=v2 ...]]'
  // * string => array - the same as above but in prepared form:
  //   array( 'tag'[, 'attr' => 'v a l u e'[, 'a2' => 'v2', ...]] ).
  //   Note that if 'tag' starts with a capital letter or is an object
  //   this is considered a callable (see below).
  // * string => callable - function (HTMLkiTagCall $tag, HTMLkiTemplate $this)
  //
  // Aliases are resolved recursively; attributes are set after each iteration
  // so you can create multiple aliases and their attributes will be set
  // (later aliases do not override attributes that already exist).
  //
  // Unlisted tags are handled by HTMLkiTemplate or its default tag method.
  // If $rawStartTags/$rawEndTags are used this setting is used on compile-time.
  public $tags = array(
    'password' => 'input type=password',  'hidden' => 'input type=hidden',
    'file' => 'input type=file',          'check' => 'input type=checkbox',
    'checkbox' => 'input type=checkbox',  'radio' => 'input type=radio',
    'submit' => 'button type=submit',     'reset' => 'button type=reset',
    'get' => 'form method=get',           'post' => 'form method=post',
  );

  //= hash of array of string attribute names
  public $defaultAttributes = array(
    // for all tags not listed here:
    ''          => array('class'),
    'a'         => array('href', 'class'),
    'base'      => array('href'),
    'button'    => array('name', 'class'),
    'embed'     => array('src', 'class'),
    'form'      => array('action', 'class'),
    'img'       => array('src', 'class'),
    'input'     => array('name', 'class'),
    'link'      => array('href'),
    'meta'      => array('name', 'content'),
    'object'    => array('data', 'class'),
    'optgroup'  => array('label', 'class'),
    'option'    => array('value'),
    'param'     => array('name', 'value'),
    'script'    => array('src'),
    'select'    => array('name', 'class'),
    'source'    => array('src'),
    'style'     => array('media'),
    'textarea'  => array('name', 'class'),
    'track'     => array('src'),
  );

  //= hash of array of string attribute names
  public $defaults = array(
    // for all tags including those listed here (tag-specific ones override these):
    ''          => array(),
    'button'    => array('type' => 'button', 'value' => 1),
    'form'      => array('method' => 'post', 'accept-charset' => 'utf-8'),
    'input'     => array('type' => 'text'),
    'link'      => array('rel' => 'stylesheet'),
    'script'    => array('type' => 'text/javascript'),
    'style'     => array('type' => 'text/css'),
    'textarea'  => array('cols' => 50, 'rows' => 5),
  );

  //= hash of array of string attributes to trim and skip if their value is empty
  public $notEmptyAttributes = array(
    ''          => array('class'),
    'input'     => array('placeholder'),
    'textarea'  => array('placeholder'),
  );

  //= hash of array of string attribute names
  public $flagAttributes = array(
    // for all tags including those listed here:
    ''          => array('disabled'),
    'area'      => array('nohref'),
    'audio'     => array('autoplay', 'controls', 'loop'),
    'button'    => array('autofocus', 'formnovalidate'),
    'command'   => array('checked'),
    'details'   => array('open'),
    'frame'     => array('noresize'),
    'hr'        => array('noshade'),
    'img'       => array('ismap'),
    'input'     => array('autofocus', 'checked', 'readonly',
                         'formnovalidate', 'required'),
    'keygen'    => array('autofocus', 'challenge', 'disabled'),
    'option'    => array('selected'),
    'object'    => array('declare'),
    'script'    => array('defer'),
    'select'    => array('multiple'),
    'style'     => array('scoped'),
    'th'        => array('nowrap'),
    'td'        => array('nowrap'),
    'textarea'  => array('readonly'),
    'time'      => array('pubdate'),
    'track'     => array('default'),
    'video'     => array('autoplay', 'controls', 'loop', 'muted'),
  );

  // Format: 'attribute[=value...]' - if 'value' is omitted shortcut's name is used.
  //= hash of hash of strings (or arrays)
  public $shortAttributes = array(
    // for all tags including those listed here (tag-specific ones override these):
    ''          => array(
      'left'    => 'align',   'center'  => 'align',   'right'   => 'align',
      'justify' => 'align',   'top'     => 'align',   'middle'  => 'align',
      'bottom'  => 'align',   'ltr'     => 'dir',     'rtl'     => 'dir',
    ),
    'a'         => array(
      'new'     => 'target=_blank',
    ),
    'button'    => array(
      'submit'  => 'type',    'reset'   => 'type',    'button'  => 'type',
    ),
    'command'   => array(
      'checkbox' => 'type',   'command' => 'type',    'radio'   => 'type',
    ),
    'input'     => array(
      'button'  => 'type',    'checkbox' => 'type',   'file'    => 'type',
      'hidden'  => 'type',    'image'   => 'type',    'password' => 'type',
      'radio'   => 'type',    'reset'   => 'type',    'submit'  => 'type',
      'text'    => 'type',    'selectonfocus' => 'onfocus=this.select()',
    ),
    'keygen'    => array(
      'rsa'     => 'keytype', 'dsa'     => 'keytype', 'ec'      => 'keytype',
    ),
    'form'      => array(
      'get'     => 'method',  'post'    => 'method',
      'file'    => 'enctype=multipart/form-data',
      'upload'  => 'enctype=multipart/form-data',
      'multipart' => 'enctype=multipart/form-data',
    ),
    'li'        => array(
      'disc'    => 'type',    'square'  => 'type',    'circle'  => 'type',
    ),
    'param'     => array(
      'data'    => 'valuetype', 'ref'   => 'valuetype', 'object' => 'valuetype',
    ),
    'script'    => array(
      'preserve' => 'xml:space',
    ),
    'textarea'  => array(
      'selectonfocus' => 'onfocus=this.select()',
    ),
  );

  //= hash of hash of callable ($value, HTMLkiTagCall $call)
  public $attributes = array();

  //= callable ($str, array $format)
  public $language;

  // Returns string (path to the compiled template) or HTMLkiTemplate.
  //= callable ($template, HTMLkiTemplate $parent, HTMLkiTagCall $call).
  public $template;

  //= callable ($name, HTMLkiTemplate $tpl)
  public $listVariable;

  function defaultsOf($tag) {
    return $this->mergedOf($tag, 'defaults');
  }

  function notEmptyAttributesOf($tag) {
    return $this->mergedOf($tag, 'notEmptyAttributes');
  }

  function flagAttributesOf($tag) {
    return $this->mergedOf($tag, 'flagAttributes');
  }

  protected function mergedOf($tag, $prop) {
    $tagRef = &$this->{$prop}[$tag];
    $defRef = &$this->{$prop}[''];
    return array_merge((array) $tagRef, (array) $defRef);
  }

  function defaultAttributesOf($tag) {
    $ref = &$this->defaultAttributes[$tag];
    isset($ref) or $ref = &$this->defaultAttributes[''];
    return is_array($ref) ? $ref : array();
  }

  function expandAttributeOf($tag, $attr, array &$attributes) {
    $full = &$this->shortAttributes[$tag][$attr];
    $full or $full = &$this->shortAttributes[''][$attr];
    $full or $full = "$attr=$attr";

    foreach ((array) $full as $full) {
      list($name, $value) = HTMLki::split('=', $full);
      isset($value) or $value = $attr;
      $attributes[$name] = $value;
    }
  }

  function callAttributeHookOf($tag, $attribute, $value) {
    $hook = &$this->attributes[$tag][$attribute];
    $hook or $hook = &$this->attributes[''][$attribute];

    $hook and $value = call_user_func($hook, $value, $this);
    return $value;
  }
}

class HTMLkiTagCall {
  public $raw;                      //= string raw parameter string

  public $lists = array();          // list variable names without leading '$'
  public $defaults = array();       // default attributes without wrapping quotes
  public $attributes = array();     // 'attr' => array('keyWr', 'valueWr', 'value')
  public $values = array();         //= array of array('valueWr', string)

  public $tag;                      //= string
  public $isEnd = false;
  public $isSingle = false;

  public $tpl;                      //= HTMLkiTemplate
  public $vars;                     //= hash

  function __construct() {
    $this->clear();
  }

  function clear() {
    $this->lists = $this->defaults = array();
    $this->attributes = $this->values = array();

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
    return call_user_func_array(array($this->tpl, $name), $arguments);
  }

  function attributes($config = null) {
    $config or $config = $this->tpl->config();

    $attributes = $this->attributes;
    $defaults = $config->defaultAttributesOf($this->tag);
    $notEmpty = array_flip( $config->notEmptyAttributesOf($this->tag) );
    $flags = array_flip( $config->flagAttributesOf($this->tag) );

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

    foreach ($attributes as $name => $value) { $result[$name] = $value[2]; }

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

interface HTMLkiTemplateEnv {
  // Methods that accept $tag always receive it in lower case form.

  function startTag($tag, $params = '', array $vars = array());
  function endTag($tag, $params = '', array $vars = array());
  function singleTag($tag, $params = '', array $vars = array());

  function lang($string, array $vars = array());
  function setTagAttribute($tag, $key, array $attributes = array());
}

class HTMLkiObject {
  protected $config;          //= HTMLkiConfig
  protected $originalConfig;  //= HTMLkiConfig, null

  //= HTMLkiConfig, $this
  function config(HTMLkiConfig $new = null) {
    if ($new) {
      $this->config = $new;
      $this->originalConfig = null;
      return $this;
    } else {
      return $this->config;
    }
  }

  //= HTMLkiConfig
  function ownConfig() {
    if (!$this->originalConfig) {
      $this->originalConfig = $this->config;
      $this->config = clone $this->config;
    }

    return $this->config;
  }

  //= HTMLkiConfig
  function originalConfig() {
    return $this->originalConfig ?: $this->config;
  }

  function error($msg) {
    throw new HTMLkiError($this, $msg);
  }

  function warning($msg, $code = 0) {
    $func = $this->config->warning;
    $func and call_user_func($func, $msg, $code, $this);
  }
}

class HTMLkiTemplate extends HTMLkiObject
               implements HTMLkiTemplateEnv, \ArrayAccess, \Countable {
  // Populated when HTMLkiConfig->$debugEval is set.
  //= array ('eval_str();', array $vars, HTMLkiTemplate $tpl, $evalResult, str $output)
  static $lastEval;

  protected $str;             //= null, string
  protected $file;            //= null, string

  protected $vars = array();  //= hash

  protected $strParseVars;
  protected $strParseEscapeExpr;
  protected $strParseValues;

  function __construct(HTMLkiConfig $config) {
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
      $regexp = HTMLkiCompiler::inlineRegExp().'|'.HTMLkiCompiler::braceRegExp('{', '}');
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
        $key = HTMLkiCompiler::Raw0. count($this->strParseValues) .HTMLkiCompiler::Raw1;
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
        throw new HTMLkiNoInput($this, $name);
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
      throw new HTMLkiInvalidInput($this, $name, $failOn);
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
  // = HTMLkiTagCall
  function parseParams($params, array $vars) {
    $call = new HTMLkiTagCall;
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
      $name = HTMLkiCompiler::wrappedRegExp();
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

  function callTag(HTMLkiTagCall $call) {
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

  function multitag($tags, HTMLkiTagCall $call) {
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
  //   retrieved using HTMLkiConfig->listVariable callback.
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
    if ($lists instanceof HTMLkiTagCall) {
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
  function htmlTagOf(HTMLkiTagCall $call, $tag, $isSingle = null) {
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

class HTMLkiCompiler extends HTMLkiObject {
  const Raw0 = "\5\2";
  const Raw1 = "\2\5";

  protected $str;               //= string

  protected $raw = array();     //= hash of mask => original
  protected $rawSrc = array();  //= hash of mask => string replaced in the template
  protected $nesting = array(); //= array of hash tag=>, isLoopTag=>

  //= string
  static function braceRegExp($op, $ed = null, $delimiter = '~') {
    $op = preg_quote($op, $delimiter);
    $ed = isset($ed) ? preg_quote($ed, $delimiter) : $op;
    return "$op($op+|[^$ed\r\n]+$ed)";
  }

  //= string
  static function nestedBraceRegExp($op, $delimiter = '~') {
    $op = preg_quote($op, $delimiter);
    return "$op($op+|(?:$op$op|[^$op\r\n])+$op)";
  }

  //= string
  static function inlineRegExp() {
    return '\$(\$(?=[a-zA-Z_$])|[a-zA-Z_]\w*)';
  }

  static function quotedRegExp() {
    return '"[^"\r\n]*"';
  }

  static function wrappedRegExp() {
    return static::quotedRegExp().'|\{[^}\r\n]+}';
  }

  function __construct(HTMLkiConfig $config, $str) {
    $this->config = $config;
    $this->str = $str;
  }

  //= string original template
  function str() { return $this->str; }

  //= string PHP code
  function compile() {
    $source = $this->str;

    foreach ($this->config->compilers as $func) {
      if (in_array($func, $this->config->omitCompilers)) {
        // Skip.
      } elseif (!is_string($func)) {
        call_user_func($func, $source, $this);
      } elseif ($this->hasCompiler($func)) {
        $source = $this->{"compile_$func"}($source);
      } else {
        $this->error("Compiler function [$func] is not defined.");
      }
    }

    $source = $this->postCompile($source);
    return $source;
  }

  //= bool
  function hasCompiler($name) {
    return method_exists($this, "compile_$name");
  }

  protected function postCompile(&$str) {
    if ($this->nesting) {
      $tags = '';

      foreach ($this->nesting as $tag) {
        $tags .= ($tags ? ' -> ' : '').$tag['tag'];
      }

      $s = count($this->nesting) == 1 ? '' : 's';
      $this->warning("Unterminated \$list tag$s: $tags.");
    }

    $str = strtr($str, $this->raw);

    if ($this->config->addLineBreaks) {
      $regexp = '~^(.+(?:Tag|>lang)\(.+?)(\?>)(\r?\n)~m'.$this->config->regexpMode;
      $str = preg_replace_callback($regexp, function ($match) {
        list(, $code, $ending, $eoln) = $match;
        return $code.'; echo "'.addcslashes($eoln, "\r\n").'"'.$ending.$eoln;
      }, $str);
    }

    return $this->config->compiledHeader.$str.$this->config->compiledFooter;
  }

  //= string masked $str
  function raw($str, $src = null) {
    $str = $this->reraw($str);

    do {
      $key = static::Raw0. count($this->raw) .static::Raw1;
    } while (isset($this->raw[$key]));

    $this->raw[$key] = $str;
    $this->rawSrc[$key] = isset($src) ? $src : $str;

    return $key;
  }

  // Replaces any already collected raw parts in $str.
  function reraw($str) {
    return strtr($str, $this->rawSrc);
  }

  function rawPhp($code, $src = null) {
    $short = $this->config->shortPhp;
    isset($short) or $short = ini_get('short_open_tag');

    $code = trim($code);

    if ($short) {
      substr($code, 0, 5) === 'echo ' and $code = '='.substr($code, 5);
    } else {
      $code = "php $code";
    }

    return $this->raw("<?$code?>", $src);
  }

  // For evaluateStr().
  function grabStringVars($str, $prefix = '') {
    $grab = $this->config->grabAllVars ? true : null;

    if ($grab === null and strpos($str, static::Raw0) !== false) {
      $grab = true;
    }

    if ($grab === null and strrchr($str, '$') !== false) {
      $regexp = '~\$([a-zA-Z_]\w*)~'.$this->config->regexpMode;
      preg_match_all($regexp, $str, $matches) and $grab = $matches[1];
    }

    if ($grab === true) {
      return $prefix.'get_defined_vars()';
    } elseif ($grab) {
      $list = array();
      foreach ($grab as $name) { $list[] = "'$name'"; }
      return $prefix.'compact('.join(', ', $list).')';
    }
  }

  protected function quote($str) {
    return addcslashes($this->reraw($str), "'\\");
  }

  protected function replacing($method, $regexp, $str) {
    $regexp[0] === '~' or $regexp = "~$regexp~";
    $regexp .= $this->config->regexpMode;

    $method = 'match'.strrchr($method, '_');
    $result = preg_replace_callback($regexp, array($this, $method), $str);
    return HTMLki::pcreCheck($result);
  }

  // ... \ (ln)
  protected function compile_lineMerge(&$str) {
    return $this->replacing(__FUNCTION__, '(\\\\+)[ \t]*(\r?\n[ \t]*)', $str);
  }

    protected function match_lineMerge($match) {
      list(, $slashes, $whitespace) = $match;
      strlen($slashes) % 2 and $whitespace = '';
      $slashes = str_repeat($slashes[0], strlen($slashes) / 2);
      return $slashes.$whitespace;
    }

  // <?...? >
  protected function compile_php(&$str) {
    return $this->replacing(__FUNCTION__, '<\?(php\b|=)?([\s\S]+?)\?>', $str);
  }

    protected function match_php($match) {
      list(, $prefix, $code) = $match;

      $prefix === '=' and $code = "echo $code";
      return $this->rawPhp(rtrim($code, ';'));
    }

  // $=...   $$=...   also >, ^ and *
  protected function compile_varSet(&$str) {
    $ws = '[ \t]';
    $id = '[a-zA-Z_]\w*';
    $regexp = "~^($ws*\\$)(\\$*)(([=>^*])($id)(@(?:\S*)?)?($ws+.*)?)(\r?\n|$)()~m";

    return $this->replacing(__FUNCTION__, $regexp, $str);
  }

    protected function match_varSet($match) {
      list(, $head, $escape, $body, $type, $var, $tag, $value, $eoln) = $match;
      // .* matches \r (but not \n if not in /s mode).
      $value = trim($value);
      $eoln = $this->config->addLineBreaks ? "\r\n" : '';
      $self = $this->config->selfVar;

      if ($escape) {
        return $this->raw($head.substr($escape, 1), $head.$escape).$body;
      } elseif ($type === '>') {
        return $this->match_input($head.$body, $var, $tag, $value).$eoln;
      } elseif ($type === '^' and $value !== '') {
        // "$^var value" is meaningless since inline assignment is just one line.
        return $this->raw($head.$escape).$body;
      } elseif ($type === '=') {
        if ($tag) {
          $attributes = array();

          $regexp = '~(\s|^)([a-zA-Z_]\w*)(=(?:"[^"]*"|[^\s]*))?()(?=\s|$)~'.
                    $this->config->regexpMode;

          if ($value and preg_match_all($regexp, $value, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
              $attr = $match[2].($match[3] ? trim($match[3], '"') : "=$match[2]");
              $attributes[] = "'".$this->quote($attr)."'";
            }
          }

          $tag = $this->quote(strtolower( substr($tag, 1) ));
          $attributes = join(', ', $attributes);
          $code = "\${$self}->setTagAttribute('$tag', '$var', array($attributes))";
        } elseif ($value === '') {
          $code = 'ob_start()';
        } else {
          $value = rtrim($value, ';');
          $code = "$$var = ($value)";
        }
      } elseif ($type === '*' and $value !== '') {
        $value = rtrim($value, ';');
        $code = "echo \${$self}->escape($$var = $value)";
      } else {
        $func = $type === '^' ? 'clean' : 'flush';
        $code = "$$var = ob_get_$func()";
      }

      return $this->rawPhp($code, $head.$body).$eoln;
    }

    protected function match_input($src, $var, $tag, $value) {
      list($type, $cond) = HTMLki::split(' ', $value);

      $coersible = substr($type, -1) === '!' ? 'false' : 'true';
      $coersible[0] === 'f' and $type = substr($type, 0, -1);

      $real = &$this->config->typeAliases[$type];
      $real and $type = $real;

      $cond = trim($cond);
      if (strtok($cond, ' ') === 'of') {
        // [array] of string [cond]
        strtok(' ');
        $cond = (string) strtok(null);
      }

      if (strtok($cond, ' ') === 'if') {
        $cond = strtok(null);
      } elseif ($cond !== '') {
        $cond = "$$var $cond";
      }

      $type === 'any' and $type = '';
      if ($type !== '' and !function_exists("is_$type")) {
        $this->warning("unknown type $type for input variable $>$var", HTMLki::WARN_COMPILE + 1);
        $type = '';
      }

      $default = $tag === '' ? 'null' : "'".$this->quote(substr($tag, 1))."'";
      $cond = $this->quote($cond);
      $vars = $this->grabStringVars("$$var $default $cond");
      $code = "if (!isset($$var)";
      $type and $code .= " or !is_$type($$var)";
      $cond and $code .= " or !($cond)";

      // Must pass $vars first because the by-reference argument will define it.
      $self = $this->config->selfVar;
      $code .= ") \${$self}->input($vars, '$var', $$var, '$type', $coersible,".
               " $default, '$cond')";

      return $this->rawPhp($code, $src);
    }

  // <...>   <.../>   </...>
  protected function compile_tags(&$str) {
    $ws = $this->config->multilineTags ? '\s' : ' ';
    $mul = $this->config->multilineTags ? ']|[' : '';
    $attr = static::wrappedRegExp()."|->|$ws>=?$ws|[^>$mul\\r\\n]";

    $quoted = static::quotedRegExp();
    $regexp = "<(/?)($quoted|[$\\w/]+)($ws+($attr)*|/)?>()";
    return $this->replacing(__FUNCTION__, $regexp, $str);
  }

    protected function match_tags($match) {
      list($match, $isEnd, $tag, $params) = $match;

      $params = trim($params);
      $isVariable = $isElse = $isLoopStart = $isLoopEnd = $isSingle = false;
      $isDefault = ($tag[0] === '"' or $match === '</>');

      if ($isDefault) {
        $isEnd = $match === '</>';
        $isSingle = (!$isEnd and substr($params, -1) === '/');
        $params = $isEnd ? '' : rtrim("$tag $params", ' /');
        $tag = '';
      } else {
        if ($params === '' and substr($tag, -1) === '/') {
          // Regexp matches <tag/> as 'tag/'. <tag /> is correct: 'tag' + ' /'.
          $params = '/';
          $tag = substr($tag, 0, -1);
        }

        $isVariable = strrchr($tag, '$') !== false;
        $isVariable or $tag = strtolower($tag);

        $isElse = substr($tag, 0, 4) === 'else';
        $isElse and $tag = (string) substr($tag, 4);
        $isLoopTag = (!$isVariable and in_array($tag, $this->config->loopTags));

        $isSingle = substr($params, -1) === '/';
        if ($isSingle) {
          $params = rtrim(substr($params, 0, -1));
        } elseif (!$isVariable and !$isEnd) {
          $isSingle = in_array($tag, $this->config->singleTags);
        }

        if (!$isSingle) {
          if ($isEnd) {
            if (!$isElse) {
              $isLoopEnd = substr($tag, 0, 3) === 'end';

              if ($isLoopEnd) {
                $tag = (string) substr($tag, 3);
              } else {
                $isLoopEnd = $isLoopTag;
              }
            }
          } elseif ($isLoopTag or substr($params, 0, 1) === '$') {
            $isLoopStart = true;
          }
        }

        if ($tag === '') {
          $code = $isElse ? '} else {' : ($isLoopEnd ? '}' : '');
          if ($isLoopEnd) {
            $this->checkNesting(array_pop($this->nesting), '', '/end');
          } elseif ($isElse and !$this->nesting) {
            // <else> matches any opening since closing is never output.
            $this->checkNesting(null, '', 'else');
          }
        }
      }

      $isMultitag = strrchr($tag, '/');

      if (!isset($code) and $params === '' and !$isElse and !$isLoopStart
          and !$isLoopEnd and !isset($this->config->tags[$tag])
          and !$isSingle and !$isMultitag and !$isVariable and !$isDefault) {
        $list = $this->config->{$isEnd ? 'rawEndTags' : 'rawStartTags'};
        if ($list === true or in_array($tag, $list)) {
          return $isEnd ? "</$tag>" : "<$tag>";
        }
      }

      if (!isset($code)) {
        $func = $isEnd ? 'endTag' : ($isSingle ? 'singleTag' : 'startTag');

        $tagParam = $isVariable ? "strtolower(\"$tag\")" : "\"$tag\"";
        $self = $this->config->selfVar;
        $func = "\${$self}->$func($tagParam";

        $params = strtr($params, array("\r" => '', "\n" => ' '));
        $params = $this->quote($params);
        $grabVars = $this->grabVarsForTag($tag, $params);
        $func .= ", '$params'$grabVars)";

        $code = '';

        if ($isElse) {
          $code .= '} else ';
          $this->checkNesting(end($this->nesting), $tag, 'else');
        } elseif ($isLoopStart) {
          $this->nesting[] = compact('tag', 'isLoopTag');
        }

        $seqVar = sprintf('$_i%03s', count($this->nesting));

        if ($isLoopStart) {
          $code .= "if ($seqVar = $func)".
                   " foreach ($seqVar as \$_iteration_) {".
                   " extract(\$_iteration_)";
        } elseif ($isLoopEnd) {
          $code .= "} $seqVar and extract($func)";
          $this->checkNesting(array_pop($this->nesting), $tag, '/end');
        } else {
          // This <else> has a tag (elsetag) so assume it matches </endtag>
          // (if it's there) and make </endtag> always close the tag even if
          // preceding if/elseif conditions didn't match it.
          $isElse and $code .= "{ $seqVar = true; ";
          $list = $this->config->extractTags;

          if ($isVariable or $isDefault or $list === true or in_array($tag, $list)) {
            $code .= "extract($func)";
          } else {
            $code .= $func;
          }
        }
      }

      return $this->rawPhp($code, $match);
    }

    protected function checkNesting($last, $tag, $msg1) {
      if (!$last) {
        $op = $tag === '' ? join('/', $this->config->loopTags) : $tag;
        $this->warning("No matching opening <$op $> for <$msg1$tag>.", HTMLki::WARN_COMPILE + 2);
      } elseif ($tag !== $last['tag'] and (!$last['isLoopTag'] or $tag !== '')) {
        $this->warning("Opening <$last[tag] $> mismatches <$msg1$tag>.", HTMLki::WARN_COMPILE + 3);
      }
    }

    protected function grabVarsForTag($tag, $params) {
      // <$Variable> and <"default"> tags are not optimized.
      $grab = ($tag === '' or strrchr($tag, '$')) ? true : null;

      if ($grab === null) {
        $list = $this->config->grabAllVarsTags;

        if (is_bool($list) or $list === null) {
          $grab = !!$list;
        } elseif (in_array($tag, $list)) {
          $grab = true;
        }
      }

      // For formatStr().
      if ($grab === null and strrchr($params, '{')) {
        $grab = true;
      }

      if ($grab) {
        return ', get_defined_vars()';
      } else {
        return $this->grabStringVars($params, ', ');
      }
    }

  // {...}   {{...
  protected function compile_echo(&$str) {
    return $this->replacing(__FUNCTION__, static::braceRegExp('{', '}'), $str);
  }

    protected function match_echo($match) {
      if (ltrim($match[1], '{') === '') {
        return $match[1];
      } else {
        $isRaw = $match[1][0] === '=';
        $isRaw and $match[1] = substr($match[1], 1);

        $code = rtrim(trim( substr($match[1], 0, -1) ), ';');

        $rubyCallRE = '~^([\w\\\\][\w\d:->\\\\]*)\s+(["\'$[\w\d].*)$~'.
                      $this->config->regexpMode;

        if ($code !== '' and ltrim($code, 'a..zA..Z0..9_') === '' and
            ltrim($code[0], 'a..z_') === '') {
          $code = "$$code";
        } elseif ($this->config->rubyLike and
                  preg_match($rubyCallRE, $code, $rubyMatch)) {
          $code = "$rubyMatch[1]($rubyMatch[2])";
        }

        $self = $this->config->selfVar;
        $isRaw or $code = "\${$self}->escape($code)";
        return $this->rawPhp("echo $code", $match[0]);
      }
    }

  // "..."   ""...
  protected function compile_lang(&$str) {
    return $this->replacing(__FUNCTION__, static::nestedBraceRegExp('"'), $str);
  }

    protected function match_lang($match) {
      if (ltrim($match[1], '"') === '') {
        return $match[1];
      } else {
        $lang = $this->quote( str_replace('""', '"', substr($match[1], 0, -1)) );
        $vars = $this->grabStringVars($lang);
        $self = $this->config->selfVar;
        return $this->rawPhp("echo \${$self}->lang('$lang'$vars)", $match[0]);
      }
    }

  // $abc123_...
  protected function compile_varEcho(&$str) {
    return $this->replacing(__FUNCTION__, static::inlineRegExp(), $str);
  }

    protected function match_varEcho($match) {
      if (ltrim($match[1], '$') === '') {
        return $match[1];
      } else {
        $self = $this->config->selfVar;
        return $this->rawPhp("echo \${$self}->escape($$match[1])", $match[0]);
      }
    }
}

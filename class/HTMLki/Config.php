<?php namespace HTMLki;

class Config {
  /*-----------------------------------------------------------------------
  | COMMON OPTIONS
  |----------------------------------------------------------------------*/

  public $regexpMode = 'u';

  //= callable ($msg, Object $issuer)
  public $warning;

  // Name of variable referring to Template object (valid PHP identifier).
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

  // If true - stores evaluateStr() calls' info in Template::$lastEval.
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
  // Object->ownConfig()) included templates will inherit the changed
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
  // * string => callable - function (TagCall $tag, Template $this)
  //
  // Aliases are resolved recursively; attributes are set after each iteration
  // so you can create multiple aliases and their attributes will be set
  // (later aliases do not override attributes that already exist).
  //
  // Unlisted tags are handled by Template or its default tag method.
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

  //= hash of hash of callable ($value, TagCall $call)
  public $attributes = array();

  //= callable ($str, array $format)
  public $language;

  // Returns string (path to the compiled template) or Template.
  //= callable ($template, Template $parent, TagCall $call).
  public $template;

  //= callable ($name, Template $tpl)
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
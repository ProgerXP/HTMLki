<?php namespace HTMLki;

class Config {
  /*-----------------------------------------------------------------------
  | COMMON OPTIONS
  |----------------------------------------------------------------------*/

  public $regexpMode = 'u';

  //= callable ($msg, Configurable $issuer)
  public $warning;

  // Name of variable referring to Template object (valid PHP identifier).
  // Must match on compile and rendering times.
  public $selfVar = '_ki';

  /*-----------------------------------------------------------------------
  | COMPILER-SPECIFIC
  |----------------------------------------------------------------------*/

  // Listed here compilers will be called unless also listed in $omitCompilers.
  public $compilers = ['lineMerge', 'php', 'varSet', 'tags', 'echo',
                        'lang', 'varEcho'];

  // Two options (this and above) exist for easier overriding. Listed here but
  // not in $compilers cause nothing.
  //
  // 'lang' is disabled by default. It treats "quoted" text as language strings.
  // If enabled be prepared that   This "is" text   would turn into   This is text
  // (quotes are gone since "is" was "translated" into itself with no $language
  // callback set). Also controls ""escaping"".
  public $omitCompilers = ['lang'];

  // If enabled tags start <start and end> on different lines are parsed and
  // merged. If disabled all tags should fit on one line (even if long).
  // Enabling can interfere with embedded scripts like this:
  //   if (s.match(/<a /)) { (ln) s += '>'
  public $multilineTags = false;

  // Non-single tags when used as <... /> get expanded to <...></...>. Single tags don't require '/' making <...> the same as <... /> (like <br> = <br /> in HTML 5).
  public $singleTags = ['area', 'base', 'basefont', 'br', 'col', 'frame',
                        'hr', 'img', 'input', 'link', 'meta', 'param',
                        'lang', 'include', 'rinclude'];

  // For these closing tags the "end" prefix is implied and </...> is the same
  // as </end...>. If a tag can be both looping and not, it shouldn't be listed
  // here. If a tag can be a non-looping single-tag or a looping regular tag,
  // then it can be listed since the <... /> form is unambiguous and requires
  // no end tag.
  public $loopTags = ['each', 'if'];

  // If true, all tags are always given get_defined_vars(). If false, all tags
  // receive empty array for vars. Otherwise an array of tag names to receive
  // all vars while others receive selective vars: <$variable> tags and tags with
  // { code } always get all vars, tags with $interpolations get only those
  // $vars, tags with neither get no vars (fastest).
  public $grabAllVarsTags = ['if', 'include', 'rinclude', 'lang'];

  // If set no guessing will be done to figure used variables for any expressions
  // including <tags> (above), "lang" and $>input@vars - they all will receive
  // full scope.
  public $grabAllVars = false;

  // Even regular tags (not loops or branches) can return variables to be
  // extract()'ed into the template scope. However, by default they don't and
  // putting extract() over each single tag call is performance-unwise. If this
  // is true, all tags are extracted, otherwise is an array of tag names to
  // extract. Tags with <t $list>, $loopTags and <$var> tags are always extracted.
  public $extractTags = ['include'];

  // If true collects actual variable state before returning from the template;
  // unset ones are also unset; can be retrieved with vars() as usual. If
  // 'compartment' collects only marked compartment variables. If false doesn't
  // collect any.
  public $grabFinalVars = 'compartment';

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
  public $typeAliases = [
    'boolean' => 'bool', 'num' => 'integer', 'int' => 'integer',
    'double' => 'float', 'real' => 'float', 'str' => 'string',
    'hash' => 'array', 'map' => 'array', 'obj' => 'object',
    'res' => 'resource',
  ];

  /*-----------------------------------------------------------------------
  | RENDERING-SPECIFIC
  |----------------------------------------------------------------------*/

  // If true - stores evaluateStr() calls' info in Template::$lastEval.
  // If a callable - gets called after eval() has ran with arguments equal
  // to $lastEval contents. You can use it to look for "Parse error:"s,
  // undefined/mistyped variables, etc.
  public $debugEval = false;

  //= true for HTML 4/XHTML, false for HTML 5
  //? <img ... /> is XHTML, <img ...> is HTML 5
  public $xhtml = false;

  // Used to quote HTML strings.
  public $charset = 'utf-8';

  //= null autodetect from short_open_tag from php.ini, bool
  public $shortPhp = null;

  // If true enables compilation of braceless outer function calls like in Ruby:
  // { number_format 1.23 }. It shouldn't interfere with normal PHP code.
  public $rubyLike = true;

  // If true and current template's config has been changed (see
  // Configurable->ownConfig()) included templates will inherit the changed
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
  // Works for closing tag as well: </> -> </span>. Can be a multitag. 
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
  public $tags = [
    'password' => 'input type=password',  'hidden' => 'input type=hidden',
    'file' => 'input type=file',          'check' => 'input type=checkbox',
    'checkbox' => 'input type=checkbox',  'radio' => 'input type=radio',
    'submit' => 'button type=submit',     'reset' => 'button type=reset',
    'get' => 'form method=get',           'post' => 'form method=post',
  ];

  //= hash of array of string attribute names
  public $defaultAttributes = [
    // For all tags not listed here:
    ''          => ['class'],
    'a'         => ['href', 'class'],
    'base'      => ['href'],
    'button'    => ['name', 'class'],
    'embed'     => ['src', 'class'],
    'form'      => ['action', 'class'],
    'img'       => ['src', 'class'],
    'input'     => ['name', 'class'],
    'link'      => ['href'],
    'meta'      => ['name', 'content'],
    'object'    => ['data', 'class'],
    'optgroup'  => ['label', 'class'],
    'option'    => ['value'],
    'param'     => ['name', 'value'],
    'script'    => ['src'],
    'select'    => ['name', 'class'],
    'source'    => ['src'],
    'style'     => ['media'],
    'textarea'  => ['name', 'class'],
    'track'     => ['src'],
  ];

  //= hash of array of string attribute names
  public $defaults = [
    // For all tags including those listed here (tag-specific ones override these):
    ''          => [],
    'button'    => ['type' => 'button', 'value' => 1],
    'form'      => ['method' => 'post', 'accept-charset' => 'utf-8'],
    'input'     => ['type' => 'text'],
    'link'      => ['rel' => 'stylesheet'],
    'script'    => ['type' => 'text/javascript'],
    'style'     => ['type' => 'text/css'],
    'textarea'  => ['cols' => 50, 'rows' => 5],
  ];

  //= hash of array of string attributes to trim and skip if their value is empty
  public $notEmptyAttributes = [
    ''          => ['class', 'id', 'style', 'title'],
    'input'     => ['placeholder'],
    'textarea'  => ['placeholder'],
    'a'         => ['rel', 'target'],
  ];

  // Listed attributes can be given as <x class=one class=two> producing
  // <x class="one two">. Typically all of them are also $notEmptyAttributes.
  // A member of this exact form: attr=$var? is similar to loop's boolean
  // suffix: if loose true, attr is set to the name of the variable, else to
  // null. Members loosely false are elided.
  //
  //= hash of hash of attr => separ
  public $enumAttributes = [
    ''          => ['class' => ' '],
    'a'         => ['rel' => ' '],
  ];

  //= hash of array of string attribute names
  public $flagAttributes = [
    // For all tags including those listed here:
    ''          => ['disabled'],
    'area'      => ['nohref'],
    'audio'     => ['autoplay', 'controls', 'loop'],
    'button'    => ['autofocus', 'formnovalidate'],
    'command'   => ['checked'],
    'details'   => ['open'],
    'frame'     => ['noresize'],
    'hr'        => ['noshade'],
    'img'       => ['ismap'],
    'input'     => ['autofocus', 'checked', 'readonly',
                    'formnovalidate', 'required'],
    'keygen'    => ['autofocus', 'challenge', 'disabled'],
    'option'    => ['selected'],
    'object'    => ['declare'],
    'script'    => ['defer'],
    'select'    => ['multiple'],
    'style'     => ['scoped'],
    'th'        => ['nowrap'],
    'td'        => ['nowrap'],
    'textarea'  => ['readonly'],
    'time'      => ['pubdate'],
    'track'     => ['default'],
    'video'     => ['autoplay', 'controls', 'loop', 'muted'],
  ];

  // Format: 'attribute[=value...]' - if 'value' is omitted shortcut's name is used.
  //= hash of hash of strings (or arrays)
  public $shortAttributes = [
    // For all tags including those listed here (tag-specific ones override these):
    ''          => [
      'left'    => 'align',   'center'  => 'align',   'right'   => 'align',
      'justify' => 'align',   'top'     => 'align',   'middle'  => 'align',
      'bottom'  => 'align',   'ltr'     => 'dir',     'rtl'     => 'dir',
      'hidden',
    ],
    'a'         => [
      'new'     => ['target=_blank', 'rel=noopener'],
    ],
    'button'    => [
      'submit'  => 'type',    'reset'   => 'type',    'button'  => 'type',
    ],
    'command'   => [
      'checkbox' => 'type',   'command' => 'type',    'radio'   => 'type',
    ],
    'input'     => [
      'button'  => 'type',    'checkbox' => 'type',   'file'    => 'type',
      'hidden'  => 'type',    'image'   => 'type',    'password' => 'type',
      'radio'   => 'type',    'reset'   => 'type',    'submit'  => 'type',
      'text'    => 'type',    'selectonfocus' => 'onfocus=this.select()',
    ],
    'keygen'    => [
      'rsa'     => 'keytype', 'dsa'     => 'keytype', 'ec'      => 'keytype',
    ],
    'form'      => [
      'get'     => 'method',  'post'    => 'method',
      'file'    => 'enctype=multipart/form-data',
      'upload'  => 'enctype=multipart/form-data',
      'multipart' => 'enctype=multipart/form-data',
    ],
    'li'        => [
      'disc'    => 'type',    'square'  => 'type',    'circle'  => 'type',
    ],
    'param'     => [
      'data'    => 'valuetype', 'ref'   => 'valuetype', 'object' => 'valuetype',
    ],
    'script'    => [
      'preserve' => 'xml:space',
    ],
    'textarea'  => [
      'selectonfocus' => 'onfocus=this.select()',
    ],
  ];

  //= hash of hash of callable ($value, TagCall $call)
  public $attributes = [];

  //= callable ($str, array $format)
  public $language;

  // Returns string (path to the compiled template) or an IncludeTemplate (with
  // set $call->vars).
  //= callable ($template, TagCall $call, Template $parent).
  public $template;

  //= callable ($name, Template $tpl)
  public $listVariable;

  // Used with the  $#key@config value  construct. $#key[@] always sets
  // Template->config()->$key.
  //= callable ($config, $key, $value)
  public $otherConfig;

  function __construct(array $options = null) {
    $options and $this->assign($options);
  }

  function assign(array $options) {
    foreach ($options as $name => $value) {
      $this->$name = $value;
    }
  }

  function makeCompiler($str) {
    return new Compiler($this, $str);
  }

  function makeTemplate() {
    return new Template($this);
  }

  function defaultsOf($tag) {
    return $this->mergedOf($tag, 'defaults');
  }

  function notEmptyAttributesOf($tag) {
    return $this->mergedOf($tag, 'notEmptyAttributes');
  }

  function flagAttributesOf($tag) {
    return $this->mergedOf($tag, 'flagAttributes');
  }

  function enumAttributesOf($tag) {
    return $this->mergedOf($tag, 'enumAttributes');
  }

  protected function mergedOf($tag, $prop) {
    $tagRef = &$this->{$prop}[$tag];
    $defRef = &$this->{$prop}[''];
    return array_merge((array) $tagRef, (array) $defRef);
  }

  function defaultAttributesOf($tag) {
    $ref = &$this->defaultAttributes[$tag];
    isset($ref) or $ref = &$this->defaultAttributes[''];
    return is_array($ref) ? $ref : [];
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

<?php
/*
  HTMLki - seamless templating with the HTML spirit
  Laravel interface | by Proger_XP
  http://proger.i-forge.net/HTMLki/SZS
*/

use HTMLkiTagCall as TagCall;

// Represents single Laravel View wrapping around an HTMLki instance.
class LHTMLki extends View implements Countable {
  // <input> 'type' values for which error messages (<errors>) can be displayed.
  static $errorableInputs = array('checkbox', 'color', 'date', 'datetime',
                                  'datetime-local', 'email', 'file', 'month',
                                  'number', 'password', 'radio', 'range', 'search',
                                  'tel', 'text', 'time', 'url', 'week');
  // <input> 'type' values which retain last user input. This doesn't include
  // 'checked' and so on as their values are programmer's-defined.
  static $userValueInputs = array('text', 'password');

  protected $htmlki;                //= null, HTMLkiTemplate
  protected $config;                //= null, HTMLkiConfig
  protected $lastInput;             //= null, string
  protected $defaultingTextarea;    //= bool

  // Builds a list of <input type="hidden"> inputs according to a query.
  //* $query hash, str a=b&c=...
  //* $prefix str - is prepended to inputs' name.
  //* $xhtml - whether to add '/>' or not.
  //= str HTML
  static function htmlInputs($query, $prefix = '', $xhtml = false) {
    is_string($query) and parse_str($query, $query);

    if (is_array($query)) {
      foreach ($query as $name => &$value) {
        if (is_array($value)) {
          $name = "$prefix" === '' ? $name : $prefix."[$name]";
          $value = static::htmlInputs($value, $name, $xhtml);
        } else {
          "$prefix" === '' or $name = $prefix."[$name]";
          $value = Form::hidden($name, $value);
          $xhtml and $value = substr($value, 0, -1).' />';
        }
      }

      return join("\n", $query);
    }
  }

  // function (array $var)
  // function (scalar $var, $value = true)
  // Binds variables to this view ignoring already existing ones.
  function add($var, $value = true) {
    $this->data += is_array($var) ? $var : array($var => $value);
    return $this;
  }

  // Counts bound variables. Used like count($view).
  function count() {
    return count($this->data);
  }

  // Renders this view. Overrides default View method.
  //= str
  function get() {
    $ki = $this->ki();
    $ki->vars($this->data);
    return $ki->render();
  }

  // Returns underlying HTMLkiTemplate instance creating it on first call - when
  // it's created the template is compiled or cache is loaded unless it's expired.
  //= HTMLkiTemplate
  function ki() {
    if (!$this->htmlki) {
      $id = $this->path;
      starts_with($id, $base = path('base')) and $id = substr($id, strlen($base));

      if (strlen($id) > $this->config()->compiledNameLength) {
        $id = md5($id);
      } else {
        $id = strtr($id, '\\/?*%:|"<>.$', '------------');
      }

      $compiled = path('storage')."views/$id.php";

      if (!is_file($compiled) or filemtime($compiled) < filemtime($this->path)) {
        $ok = File::put($compiled, HTMLki::compileFile($this->path, $this->config()));

        if (!is_int($ok)) {
          throw new HTMLkiError($this, "Error writing compiled HTMLki template to".
                                " [$compiled]; source template: [{$this->path}].");
        }
      }

      $this->htmlki = HTMLki::templateFile($compiled, $this->config());
    }

    return $this->htmlki;
  }

  // Returns HTMLki configuration used when compiling and rendering this view.
  //= HTMLkiConfig
  function config() {
    return $this->config ?: $this->config = $this->makeConfig();
  }

  // Creates new HTMLki configuration to use when processing this view (see config()).
  // Does initial assignment of some handlers (language, warning, etc.).
  //= HTMLkiConfig
  protected function makeConfig() {
    $config = clone (HTMLki::config('laravel') ?: HTMLki::config());
    $self = $this;

    $config->warning = function ($message, $obj) use ($self) {
      $title = is_object($obj) ? get_class($obj) : $obj;
      starts_with($title, 'HTMLki') and $title = 'HTMLki '.substr($title, 6);

      if ($obj instanceof HTMLkiTemplate) {
        $obj->isFile() and $title .= ' ['.$obj->loadedFile().']';
      }

      Log::warn($full = "$title: $message");

      if ($self->config()->failOnWarning) {
        throw new HTMLkiError($obj, $full);
      }
    };

    $config->language = function ($name) { return __($name)->get(); };

    $config->template = function ($name, HTMLkiTemplate $parent, TagCall $call)
                             use ($self) {
      return new LHTMLkiInclude($name, $call->vars + $self->data);
    };

    $config->listVariable = array($this, 'listVariable');

    foreach (get_class_methods($this) as $item) {
      foreach (array('attribute', 'tag') as $type) {
        if (strtok($item, '_') === $type) {
          $func = array($this, $item);
          $item = strtok(null);

          if ($type[0] === 'a') {
            $config->attributes[''][$item] = $func;
          } else {
            $config->{$type.'s'}[$item] = $func;
          }
        }
      }
    }

    return $config;
  }

  // HTMLki calls this function when it renders <tag $listVar> construct.
  // $name is 'listVar', $tpl typically is the same as $this->ki().
  // If this returns null view's variable is used, if any.
  //
  // By overriding this method you can handle variables like <ul $this> or
  // even some sort of expressions like <ul $this_or_that>. Note that $name
  // is always an identifier (having alphanumeric characters and '_') so
  // possibilities are somewhat limited.
  //
  //= null use view's variable, mixed use this value instead
  function listVariable($name, HTMLkiTemplate $tpl) { }

  // LHTMLki will detect methods of the following forms and attach them as
  // attribute/tag handlers (filters).
  //function attribute_XXX($value)
  //function tag_XXX(HTMLkiTagCall $call)

  /*-----------------------------------------------------------------------
  | ATTRIBUTE EXTENSIONS
  |------------------------------------------------------------------------
  | These functions start with 'attribute_', take a string value and return
  | a string. Called when rendering <tag attr=value> construct (here,
  | $this->attribute_attr('value') is called). Whatever is returned is used
  | as actual attribute's value, escaped and inserted into resulting HTML.
  |----------------------------------------------------------------------*/

  function attribute_action($value) {
    return $this->attribute_href($value);
  }

  function attribute_href($value) {
    @list($url, $query) = explode('?', $value, 2);

    if ($url and $url[0] === '+') {
      $url = substr($url, 1);

      @list(, $current) = explode('?', URI::full(), 2);
      parse_str($current, $current);
      parse_str($query, $query);
      $query = http_build_query(((array) $query) + ((array) $current), '', '&');
    }

    $slugs = function ($func) use ($url) {
      $slugs = explode('/', $url);
      $url = array_shift($slugs);
      return $func($url, $slugs);
    };

    if (strrchr($url, '@') !== false or strpos($url, '::')) {
      starts_with($url, 'mailto:') or $url = $slugs('action');
    } elseif (Router::find($url)) {
      $url = $slugs('route');
    } else {
      $url = url($url);
    }

    isset($query) and $url .= (strrchr($url, '?') === false ? '?' : '&').$query;
    return $url;
  }

  function attribute_src($value) {
    if (strpos($value, '::')) {
      list($bundle, $value) = explode('::', $value, 2);
      $value = "bundles/$bundle/".ltrim($value, '/');
    }

    return asset($value);
  }

  /*-----------------------------------------------------------------------
  | TAG EXTENSIONS
  |------------------------------------------------------------------------
  | These functions start with 'tag_', take a HTMLkiTagCall object and
  | return any value that's handled by the template itself. For example,
  | <each $x> returns an array of arrays of variables to iterate over.
  | Whatever they output is inserted into rendered output verbatim.
  | These methods let you most powerfully tune HTMLki but they need more
  | knowledge/investigation of its core.
  |----------------------------------------------------------------------*/

  // <input [novalidate]> - also <checkbox>, <radio>, etc.
  function tag_input($call) {
    $this->rememberInput($call);
    $old = $this->oldInput($call);

    if (array_get($call->attributes(), 'type') === 'checkbox') {
      if ($old) {
        $call->attributes['checked'] = array('', '', 'checked');
      } elseif (isset($old)) {
        unset( $call->attributes['checked'] );
      }
    } elseif (isset($old)) {
      $call->attributes['value'] = array('', '', $old);
    }

    return $call->handle();
  }

  // <select [novalidate] [defaulting]>
  function tag_textarea($call) {
    $this->rememberInput($call);

    if (!$call->lists) {
      if ($call->isSingle) {
        $call->isEnd = $call->isSingle = false;
        $call->handle();

        echo $this->oldInput($call);
        $call->isEnd = true;
      } elseif ($call->isEnd) {
        $input = ob_get_clean();
        $this->defaultingTextarea and $old = $this->oldInput($call);
        echo isset($old) ? $old : $input;
      } else {
        $this->defaultingTextarea = array_first($call->values, function ($i, $value) {
          return $value[1] === 'defaulting';
        });

        $call->handle();
        ob_start();
        return;
      }
    }

    return $call->handle();
  }

  // <select [novalidate]>
  function tag_select($call) {
    $this->rememberInput($call);
    return $call->handle();
  }

  // <errors>   <errors "email">
  function tag_errors($call) {
    $name = reset($call->defaults) ?: $this->lastInput;
    $config = $this->config();

    if ("$name" === '') {
      $this->ki()->warning('<errors> requires a name attribute unless used'.
                           ' after another input tag.', $this);
    } elseif ($errors = $this['errors']->get($name, $config->errorsItem)) {
      echo $this->ki()->htmlTag($config->errorsTag, $config->errorsAttributes),
           join($errors), '</', $config->errorsTag, '>';
    }
  }

  // <js media/layout.js> <js "layout.js" media/layout.js>
  function tag_js($call) {
    $this->assetTag($call, 'script');
  }

  // <css media/styles.less> <js "app.less" media/styles.less>
  function tag_css($call) {
    $this->assetTag($call, 'style');
  }

  // <csrf>   <csrf "var_name">
  function tag_csrf($call) {
    echo $this->ki()->htmlTag('input', array(
      'type'              => 'hidden',
      'name'              => reset($call->defaults) ?: Session::csrf_token,
      'value'             => Session::token(),
    ));
  }

  // <form "page?hidden=in&puts=">  <form action="the?same=">
  // Also handles non-common methods (other than GET and POST).
  function tag_form($call) {
    $hidden = '';

    if ($action = &$call->defaults[0] or $action = &$call->attributes['action']) {
      @list($action, $query) = explode('?', $action, 2);
      $call->defaults[0] or $action = array('', $action);
      $hidden .= static::htmlInputs($query, '', $this->config()->xhtml);
    } else {
      unset($call->attributes['action']);
    }

    $method = array_get($call->attributes(), 'method');

    if ($method and !in_array(strtolower($method), array('get', 'post'))) {
      $call->attributes['method'] = array('', '', 'POST');
      $hidden .= Form::hidden(Request::spoofer, $method);
    }

    $result = $call->handle();
    echo $hidden;
    return $result;
  }

  /*-----------------------------------------------------------------------
  | TAG UTILITIES
  |----------------------------------------------------------------------*/

  // Used by tag_js(0 and tag_css() to register a new asset.
  protected function assetTag(TagCall $call, $type) {
    $src = $call->values[0][1];

    Asset
      ::container(array_get($call->attributes(), 'to', 'default'))
      ->$type(basename(reset($call->defaults) ?: $src), asset($src));
  }

  // Determines an old value for an input (textarea, etc.) field.
  protected function oldInput(TagCall $call) {
    static $checkables = array('checkbox', 'radio');

    $old = null;
    $attr = $call->attributes();
    $name = array_get($attr, 'name', $this->lastInput);

    if ($name) {
      if ($call->tag === 'input' and in_array($attr['type'], $checkables)) {
        if (isset($attr['value']) and !isset($attr['checked']) and
            $this->inputValue($name) !== null) {
          $old = trim($this->inputValue($name)) === trim($attr['value']);
        }
      } elseif ($this->isAnInput($call, static::$userValueInputs) and
                !isset($attr['value'])) {
        $old = $this->inputValue( strtr($name, array('[' => '.', ']' => '')) );
      }

      is_scalar($old) or $old = array_get($attr, 'default');
      if (isset($attr['default'])) { unset($call->attributes['default']); }
    }

    return $old;
  }

  // Fetches previously entered input value from Laravel.
  protected function inputValue($name) {
    return Input::get($name, function () use ($name) { Input::old($name); });
  }

  // Remembers last input used in the template so later tags (like <errors>)
  // can determine which input to access.
  protected function rememberInput(TagCall $call) {
    if (!$call->lists) {
      $attr = $call->attributes();
      $name = array_get($attr, 'name');

      if (isset($name) and $this->isAnInput($call, static::$errorableInputs)) {
        if (!empty($attr['novalidate'])) {
          unset($call->attributes['novalidate']);
        } else {
          $name = strtr($name, array('[' => '.', ']' => ''));
          return $this->lastInput = $name;
        }
      }
    }
  }

  // Determines if given tag is inputable; for <input> this involves checking in
  // $list. Non-<input> are assumed to be inputable (like textarea, etc.).
  protected function isAnInput(TagCall $call, array $list) {
    if ($call->tag === 'input') {
      $type = strtolower( array_get($call->attributes(), 'type') );
      return !$type or in_array($type, $list);
    } else {
      return true;
    }
  }
}

// Mediator class for HTMLkiTemplate->tag_include()'s looping version (<include $var>).
class LHTMLkiInclude {
  public $view;

  function __construct($view, array $vars) {
    $this->view = View::make($view)->with($vars);
  }

  function add(array $vars) {
    return $this->view->with($vars);
  }

  function render() {
    return $this->view->render();
  }
}
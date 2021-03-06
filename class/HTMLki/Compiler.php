<?php namespace HTMLki;

class Compiler extends Configurable {
  const Raw0 = "\5\2";
  const Raw1 = "\2\5";

  protected $str;           //= string

  protected $raw = [];      //= hash of mask => original
  protected $rawSrc = [];   //= hash of mask => string replaced in the template
  protected $nesting = [];  //= array of hash tag=>, isLoopTag=>
  protected $varNesting = [];   //= array of var=>, type=>

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

  function __construct(Config $config, $str) {
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
      $tags = join(' -> ', array_map(function ($tag) {
        return $tag['tag']; 
      }, $this->nesting));

      $s = count($this->nesting) == 1 ? '' : 's';
      $this->warning("Unterminated \$list tag$s: $tags.");
    }

    if ($this->varNesting) {
      $names = join(', ', array_map(function ($set) {
        return "\$$set[type]$set[var]";
      }, $this->varNesting));

      $this->warning("Unterminated $names.");
    }

    if ($this->config->grabFinalVars) {
      $self = $this->config->selfVar;
      if ($this->config->grabFinalVars === 'compartment') {
        $code = "addVars(compact(\${$self}->getCompartmentVarNames()))";
      } else {
        // $_vars from Template->evaluate().
        $skip = array_map(function ($var) {
          return "'{$this->quote($var)}' => true,";
        }, [$self, '_vars']);
        $code = 'vars(array_filter(get_defined_vars(), function ($k) {'.
                '  static $skip = ['.join($skip).'];'.
                // match_tags().
                '  return strncmp($k, "_i0", 3) and !isset($skip[$k]);'.
                '}, ARRAY_FILTER_USE_KEY))';
      }
      $str .= $this->rawPhp("\${$self}->$code");
    }

    $str = strtr($str, $this->raw);

    if ($this->config->addLineBreaks) {
      $regexp = '~^(.+(?:Tag|>lang)\(.+?)(\?'.'>)(\r?\n)~m'.$this->config->regexpMode;
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

    return $this->raw("<?$code?".">", $src);
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
      $list = [];
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
    $result = preg_replace_callback($regexp, [$this, $method], $str);
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
    return $this->replacing(__FUNCTION__, '<\?(php\b|=)?([\s\S]+?)\?'.'>', $str);
  }

  protected function match_php($match) {
    list(, $prefix, $code) = $match;

    $prefix === '=' and $code = "echo $code";
    return $this->rawPhp(rtrim($code, ';'));
  }

  // $=...   $$=...   also > ^ * + #
  protected function compile_varSet(&$str) {
    $ws = '[ \t]';
    $id = '[a-zA-Z_]\w*';
    $regexp = "~^($ws*\\$)(\\$*)(([=>^*+#])($id)(@(?:\S*)?)?($ws+.*)?)(\r?\n|$)()~m";

    return $this->replacing(__FUNCTION__, $regexp, $str);
  }

  protected function match_varSet($match) {
    list(, $head, $escape, $body, $type, $var, $tag, $value, $eoln) = $match;

    // .* matches \r (but not \n if not in /s mode).
    $value = trim($value);
    // Brackets required in case operators with lower precedence than of
    // '=' are used in $value, like 'or'.
    $codeValue = '('.rtrim($value, ';').')';
    $this->config->addLineBreaks or $eoln = '';
    $self = $this->config->selfVar;

    if ($escape) {    // $$... -> $...
      return $this->raw($head.substr($escape, 1), $head.$escape).$body;
    }

    switch ($type) {
      default:
        $this->error("Unexpected match_varSet() \$type '$type'.");

      case '>':       // $>input
        return $this->match_input($head.$body, $var, $tag, $value).$eoln;

      case '=':       
        if ($tag) {   // $=attr@tag
          $attributes = [];

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
          $code = "\${$self}->setTagAttribute('$tag', '$var', [$attributes])";
        } elseif ($value === '') {    // $=multilinestart
          foreach ($this->varNesting as $set) {
            if ($set['var'] === $var) {
              $this->warning("Opening $=$var inside another $=$var.");
              break;
            }
          }
          $this->varNesting[] = compact('type', 'var');
          $code = 'ob_start()';
        } else {      // $=singleline assign
          $code = "$$var = $codeValue";
        }
        break;

      case '+':    // $+compartment
        $code = "\${$self}->markAsCompartments(['$var']);";

        if ($tag === '@') {   // $+comp@
          $code .= "$$var = [];";
        } else {  // $+comp or $+comp@key
          $code .= "isset($$var) or $$var = [];".
                   "is_array($$var) or $$var = (array) $$var;";
        }

        $tag = $this->quote(substr($tag, 1));
        strlen($tag) and $tag = "'$tag'";

        if ($value === '') {
          $this->checkVarNesting(array_pop($this->varNesting), $type, $var);
          // Add new item to the compartment:
          //   $=var
          //   ...
          //   $+var[@key]
          // Only mark var as a compartment, creating it as [] if unset:
          //   $=var
          //   $+var
          // Process items in the compartment:
          //   <each $var> ... e.g. { join $item } </each>
          //   use  $>var@ array  to pre-create an optional compartment variable 
          $code .= "ob_get_length() ? \${$var}[$tag] = ob_get_clean() : ob_end_clean();";
        } else {    // $+comp assign
          // Add:       $+var[@key] 123
          // Mark:      use the multiline form
          // Process:   <include $var "items">
          $code .= "\${$var}[$tag] = $codeValue";
        }
        break;

      case '#':   // $#config 
        $tag = $this->quote(substr($tag, 1));
        $code = "\${$self}->setConfig('$tag', '$var', $codeValue)";
        break;

      case '^':       // $^multilineassign
        if ($value !== '') {  
          // "$^var value" is meaningless since inline assignment is done
          // with "$=var value".
          return $this->raw($head.$escape).$body;
        }
      case '*':       
        if ($value !== '') {  // $*singleline assign & echo 
          $value = rtrim($value, ';');
          $code = "echo \${$self}->escape($$var = $codeValue)";
        }       
        // $*multilineassign&echo $^multilineassign
        if ($value === '') {
          $this->checkVarNesting(array_pop($this->varNesting), $type, $var);
          $func = $type === '^' ? 'clean' : 'flush';
          $code = "$$var = ob_get_$func()";
        }
        break;
    }

    return $this->rawPhp($code, $head.$body).$eoln;
  }

  protected function checkVarNesting($last, $type, $var) {
    if (!$last) {
      $op = $tag === '' ? join('/', $this->config->loopTags) : $tag;
      $this->warning("No matching opening $=$var for \${$type}$var.", HTMLki::WARN_COMPILE + 4);
    } elseif ($last['var'] !== $var) {
      $this->warning("Opening $=$last[var]'s name mismatches \${$type}$var's, using $$var.", HTMLki::WARN_COMPILE + 5);
    }
  }

  protected function match_input($src, $var, $tag, $value) {
    list($type, $cond) = HTMLki::split(' ', $value);

    $coercible = substr($type, -1) === '!' ? 'false' : 'true';
    $coercible[0] === 'f' and $type = substr($type, 0, -1);

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
    $code .= ") \${$self}->input($vars, '$var', $$var, '$type', $coercible,".
             " $default, '$cond')";

    return $this->rawPhp($code, $src);
  }

  // <...>   <.../>   </...>
  protected function compile_tags(&$str) {
    $ws = $this->config->multilineTags ? '\s' : ' ';
    $mul = $this->config->multilineTags ? ']|[' : '';
    $attr = static::wrappedRegExp()."|->|$ws>=?$ws|[^>$mul\\r\\n]";

    $quoted = static::quotedRegExp();
    $regexp = "<(/?)($quoted|[$\\w/]+)($ws+($attr)*|/)?".">()";
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
        $list = $this->config->{$isEnd ? 'relaxEndTags' : 'relaxStartTags'};
        if ($list === true or in_array($tag, $list)) {
          return '';
        } else {
          return $isEnd ? "</$tag>" : "<$tag>";
        }
      }
    }

    if (!isset($code)) {
      $func = $isEnd ? 'endTag' : ($isSingle ? 'singleTag' : 'startTag');

      $tagParam = $isVariable ? "strtolower(\"$tag\")" : "\"$tag\"";
      $self = $this->config->selfVar;
      $func = "\${$self}->$func($tagParam";

      $params = strtr($params, ["\r" => '', "\n" => ' ']);
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
                 " foreach ($seqVar as \$_iteration) {".
                 " extract(\$_iteration)";
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

      $rubyCallRE = '~^([\w\\\\][\w:->\\\\]*)\s+(["\'$[\w].*)$~'.
                    $this->config->regexpMode;

      if ($this->config->isIdentifier($code) and
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

  // Remove <!--comments--> and compress successive whitespace into one - this
  // is generally safe, unlike removing whitespace completely which requires
  // accurate knowledge about the context. 
  protected function compile_compact(&$str) {
    return $this->replacing(__FUNCTION__, '~(\s)*<!--.*?-->(\s)*|(\s)+~s', $str);
  }

  protected function match_compact($match) {
    if (count($match) > 1) {
      return end($match);
    }
  }
}

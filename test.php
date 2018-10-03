<?php
/*
  HTMLki | http://squizzle.me/php/htmlki
  This script is meant for local testing only because it allows execution of
  arbitrary PHP code.
*/

  error_reporting(-1);

  register_shutdown_function(function () {
    $error = error_get_last();
    if ($error and $lastEval = HTMLki\Template::$lastEval) {
      echo '<h2>Last evaluated expression</h2>';
      echo '<pre>', esc($lastEval), '</pre>';
    }
  });

  spl_autoload_register(function ($class) {
    $class = strtr($class, '\\', '/');
    require_once __DIR__."/class/$class.php";
  });

  class EchoTemplate implements HTMLki\IncludeTemplate {
    public $name = '???';
    public $vars = [];
    public $compNames = [];

    function addVars(array $vars) {
      $this->vars = $vars + $this->vars;
    }

    function markAsCompartments(array $vars) {
      $this->compNames += array_flip($vars);
    }

    function getCompartments() {
      return ["icomp" => [$this->name]];
    }

    // It actually doesn't return anything but echoes inclusion info
    // immediately so it ends up before the main $template contents is printed
    // on the test page.
    function render() {
      ob_start();

      var_dump(array_diff_key($this->vars, [
        '_ki' => 1, 
        '_vars' => 1,
      ]));

      $dump = ob_get_clean();

      if (!ini_get('xdebug.overload_var_dump') or !ini_get('html_errors')) {
        $dump = esc($dump);
      }
?>
<fieldset>
  <legend>Included template <b>"<?=esc($this->name)?>"</b></legend>
  <?php if ($this->compNames) {?>
    <p>
      Compartment variable(s): 
      <b><?=esc(join(', ', array_keys($this->compNames)))?></b>
    </p>
  <?php }?>
  <pre><?=$dump?></pre>
</fieldset>
<?php
    }
  }

  function esc($str) {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'utf-8');
  }

  $inputXHR = &$_REQUEST['xhr'];
  $inputTemplate = &$_REQUEST['tpl'];
  $inputCompileOnly = &$_REQUEST['compile'];
  $inputVars = &$_REQUEST['vars'];

  $warnings = [];
  $compiled = $template = null;

  if ($inputTemplate = trim($inputTemplate)) {
    HTMLki\HTMLki::config()->warning = function ($msg) use (&$warnings) {
      $warnings[] = $msg;
    };

    HTMLki\HTMLki::config()->template = function ($tpl, $call) {
      $stub = new EchoTemplate;
      $stub->name = $tpl;
      $stub->vars = $call->vars;
      return $stub;
    };

    $compiled = HTMLki\HTMLki::compile($inputTemplate);
    $inputCompileOnly or $template = HTMLki\HTMLki::template($compiled);

    if ($template) {
      if (!strncmp($inputVars, '{', 1)) {
        $template->addVars(json_decode($inputVars, true));
      } elseif ($inputVars) {
        $template->addVars(eval("return $inputVars;"));
      }
    }
  }

  $defaultTemplate = <<<'TEMPLATE'
<ul $menu>
  <li "$classes">
    <img $icon src=$icon>
    <a "$url" target=$target>$caption</a>
  </li>
</endul>
TEMPLATE;

  // Icons taken from http://brandspankingnew.net.
  $defaultVars = <<<'VARS'
[
  'menu' => [
    [
      'classes' => 'current',
      'icon' => 'data:image/gif;base64,R0lGODlhCgAKAKIAADMzM//M/93d3WZmZu7u7v///wAAAAAAACH5BAEHAAEALAAAAAAKAAoAAAMjGLoc8/C5Qit9Rehd3v7CQ4ACIRKohg4B4ALjADStSSuukgAAOw==',
      'url' => 'forum/topics.php',
      'target' => null,
      'caption' => 'Forum',
    ],
    [
      'classes' => '',
      'icon' => 'data:image/gif;base64,R0lGODlhCgAKAKIAADMzM//M/97e3mZmZv///zoxOjc3NwAAACH5BAEHAAEALAAAAAAKAAoAAAMdGDo8+mEQYeCa1UpqI+5b9jAT4IzExEFhFxhikAAAOw==',
      'url' => 'download.php',
      'target' => '_blank',
      'caption' => 'Download',
    ],
    [
      'classes' => 'submenu',
      'icon' => null,
      'url' => '#',
      'target' => null,
      'caption' => 'See also',
    ],
  ],
]
VARS;
?>

<?php if (!$inputXHR) {?>
<!DOCTYPE html>
<html>
  <head>
    <title>HTMLki test page</title>
    <style>
      * { box-sizing: border-box; }
      body { max-width: 1200px; margin: 0 auto; padding: 1em; background: #f4f4f4; }
      h1 { margin-top: 0; font-size: 1em; text-align: center; }
      .split { overflow: auto; margin-left: -1em; }
      .split > * { float: left; width: 50%; padding-left: 1em; }
      :focus { outline: 1px dashed blue; outline-offset: -2px; }
      textarea { 
        width: 100%;
        min-height: 25em;
        height: 25vh;
        resize: vertical;
        padding: .5em .75em;
      }
      fieldset { max-width: 40em; margin: 1em 0; padding: 0 .75em; }
      pre { color: navy; }
    </style>
  </head>
  <body>
    <h1>
      HTML<span style="color: red">ki</span> test page &bull;
      <a target="_blank" href="http://squizzle.me/php/htmlki">Documentation</a> &bull;
      <a href="https://github.com/ProgerXP/HTMLki">GitHub</a> 
    </h1>

    <form action="" method="get">
      <div class="split">
        <div>
          <label>
            The template (HTMLki syntax):
            <textarea name="tpl" autofocus required onfocus="this.select(); this.onfocus = null"><?=esc($inputTemplate ?: $defaultTemplate)?></textarea>
          </label>
        </div>
        <div>
          <label>
            Variables (a JSON object or PHP code returning an array):
            <textarea name="vars"><?=esc($inputVars ?: $defaultVars)?></textarea>
          </label>
        </div>
      </div>
      <p>
        <button type="submit">Render</button>
        <button type="submit" name="compile" value="1">Compile only/see warnings</button>
        Also <b>Ctrl/Shift/Alt+Enter</b>.
        <a href="<?=esc($_SERVER['SCRIPT_NAME'])?>">Reset the form</a>.
      </p>
    </form>

    <script>
      var clickedButton 

      document.querySelectorAll('[type="submit"]').forEach(function (node) {
        node.onclick = function () { clickedButton = this }
      })

      document.forms[0].onsubmit = function (e) {
        var xhr = new XMLHttpRequest
        xhr.open('POST', location.href, true)
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8')

        xhr.onload = function () {
          if (xhr.status >= 200 && xhr.status < 400) {
            document.getElementById('output').innerHTML = xhr.responseText
          }
        }

        var serializer = function (node) { 
          return encodeURIComponent(node.name) + '=' + 
                 encodeURIComponent(node.value)
        }

        var data = Array.prototype.map.call(
            this.querySelectorAll('[name]:not(button)'), serializer)
          .concat([
            clickedButton && serializer(clickedButton),
            'xhr=1',
          ])
          .join('&')

        xhr.send(data)
        e.preventDefault()
      }

      document.onkeyup = function (e) {
        if (e.keyCode == 13 && (e.ctrlKey || e.metaKey || e.altKey || e.shiftKey)) {
          // clickedButton not reset on purpose (repeat last submit type).
          document.forms[0].onsubmit(e)
        }
      }
    </script>

    <output id="output">
<?php }?>
      <?php if ($compiled) {?>
        <hr>
        <?php if ($template) {?>
          <?=$rendered = $template->render()?>
          <hr>
          <pre><?=esc($rendered)?></pre>
        <?php } else {?>
          <pre><?php highlight_string($compiled)?></pre>
        <?php }?>
      <?php }?>

      <?php if ($template and $comps = $template->getCompartments()) {?>
        <hr>
        <ul>
          <?php foreach ($comps as $var => $parts) {?>
            <li>
              <b><?=esc($var)?></b> compartment has 
              <?=count($parts)?> part(s): 
              <?=esc(join(', ', array_keys($parts)))?>
            </li>
          <?php }?>
        </ul>
      <?php }?>

      <?php if ($warnings) {?>
        <hr>
        <ul>
          <?php foreach ($warnings as $warn) {?>
            <li><?=esc($warn)?></li>
          <?php }?>
        </ul>
      <?php }?>
<?php if (!$inputXHR) {?>
    </output>
  </body>
</html>
<?php }?>

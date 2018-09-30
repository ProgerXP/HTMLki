<?php
spl_autoload_register(function ($class) {
  $class = strtr($class, '\\', '/');
  require_once __DIR__."/../class/$class.php";
});

class SuperTemplate extends HTMLki\Template {
  protected function tag_supertag($call) {
    if ($call->isSingle) {
      echo str_rot13($call->raw);
    }
  }
}

$template = <<<'TEMPLATE'
<supertag hello />, $world?
TEMPLATE;

$compiledTemplate = HTMLki\HTMLki::compile($template);

// uryyb, worth?
echo (new SuperTemplate(HTMLki\HTMLki::config()))
  ->loadStr($compiledTemplate)
  ->add('world', 'worth')
  ->render();

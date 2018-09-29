<?php namespace HTMLki;

interface TemplateEnv {
  // Methods that accept $tag always receive it in lower case form.

  function startTag($tag, $params = '', array $vars = []);
  function endTag($tag, $params = '', array $vars = []);
  function singleTag($tag, $params = '', array $vars = []);

  function lang($string, array $vars = []);
  function setTagAttribute($tag, $key, array $attributes = []);
}

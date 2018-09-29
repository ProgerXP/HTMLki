<?php namespace HTMLki;

interface TemplateEnv {
  // Methods that accept $tag always receive it in lower case form.

  function startTag($tag, $params = '', array $vars = array());
  function endTag($tag, $params = '', array $vars = array());
  function singleTag($tag, $params = '', array $vars = array());

  function lang($string, array $vars = array());
  function setTagAttribute($tag, $key, array $attributes = array());
}

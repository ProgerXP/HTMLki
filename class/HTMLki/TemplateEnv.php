<?php namespace HTMLki;

// The Compiler expects this interface to be implemented for the object used as
// $selfVar ($_ki). Methods that accept $tag always receive it in lower case
// form.
interface TemplateEnv {
  function setTagAttribute($tag, $key, array $attributes = []);
  //= object
  function config();
  //= string
  function escape($str);
  function input(array $vars, $var, &$value, $type, $coercible, $default = null, $cond = '');
  //= string
  function lang($string, array $vars = []);

  function startTag($tag, $params = '', array $vars = []);
  function endTag($tag, $params = '', array $vars = []);
  function singleTag($tag, $params = '', array $vars = []);

  function getCompartmentVarNames();
  function vars(array $vars);
  function markAsCompartments(array $vars);
}

<?php namespace HTMLki;

// <include> expects to find these methods on $template() callback's result.
// Be aware that this object may be cloned.
interface IncludeTemplate {
  function addVars(array $vars);
  //= string
  function render();
  // Called after render(). Returns actual variable state after finishing
  // rendering (see Config->$grabFinalVars).
  //= hash of array of mixed 
  //? getCompartments()   // ['head' => ['<js1>', '<meta2>'], 'body' => ...]
  function getCompartments();
  //= array of string var names
  function markAsCompartments(array $names);
}

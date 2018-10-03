<?php namespace HTMLki;

// A helper class to simplify integration with other frameworks using a single
// callback.
class IncludeProxy implements IncludeTemplate {
  // Returns [$rendered, array $compartmentVars].
  //= callable (array $vars, array $compVarNames)
  protected $renderer;
  protected $vars = [];
  //= hash var name => null
  protected $compartmentVars = [];
  protected $returnCompartments;

  function __construct(array $vars, $renderer) {
    $this->addVars($vars);
    $this->renderer = $renderer;
  }

  function addVars(array $vars) {
    $this->vars = $vars + $this->vars;
    return $this;
  }

  function markAsCompartments(array $vars) {
    $this->compartmentVars += array_flip($vars);
    return $this;
  }

  function render() {
    list($content, $this->returnCompartments) = 
      $this->renderer($this->vars, array_keys($this->compartmentVars));
    return $content;
  }

  function getCompartments() {
    return $this->returnCompartments;
  }
}

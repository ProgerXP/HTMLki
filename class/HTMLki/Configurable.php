<?php namespace HTMLki;

class Configurable {
  protected $config;          //= Config
  protected $originalConfig;  //= Config, null

  //= Config, $this
  function config(Config $new = null) {
    if ($new) {
      $this->config = $new;
      $this->originalConfig = null;
      return $this;
    } else {
      return $this->config;
    }
  }

  //= Config
  function ownConfig() {
    if (!$this->originalConfig) {
      $this->originalConfig = $this->config;
      $this->config = clone $this->config;
    }

    return $this->config;
  }

  //= Config
  function originalConfig() {
    return $this->originalConfig ?: $this->config;
  }

  function error($msg) {
    throw new Exception($this, $msg);
  }

  function warning($msg, $code = 0) {
    $func = $this->config->warning;
    $func and call_user_func($func, $msg, $code, $this);
  }
}

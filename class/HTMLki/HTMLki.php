<?php namespace HTMLki;
/*
  HTMLki - seamless templating with the HTML spirit
  in public domain | by Proger_XP | http://proger.me
  https://github.com/ProgerXP/HTMLki
*/

class HTMLki {
  static $config;             //= Config set at the bottom of this script
  static $configs = [];       //= hash of Config

  const WARN_COMPILE = 0;
  const WARN_RENDER = 50;
  const WARN_TAG = 100;

  // function (string $name)                      - return $name
  // function (string $name, Config $new)  - set $new to $name and return it
  // function ('', Config $new)            - set default config
  // function (Config $config)             - return $config
  // function ()                                  - return default config (not its copy)
  static function config($return = null, Config $new = null) {
    if (is_string($return)) {
      if ("$return" === '') {
        $return = &static::$config;
      } else {
        $return = &static::$configs[$return];
      }

      isset($new) and $return = $new;
    }

    return $return ?: static::$config;
  }

  //= string
  static function compile($str, $config = null) {
    return (new Compiler(static::config($config), $str))
      ->compile();
  }

  //* $file str - path to HTMLki template file.
  //* $cachePath str - path to a folder where compiled templates are stored.
  //
  //= string
  //
  //? compileFileCaching('tpl/my.ki.html', 'cache/htmlki/')
  static function compileFileCaching($file, $cachePath, $config = null) {
    $hint = strtok(basename($file), '.');
    $cache = rtrim($cachePath, '\\/')."/$hint-".md5($file).'.php';

    if (!is_file($cache) or filemtime($cache) < filemtime($file)) {
      $res = static::compileFile($file, $config);
      is_dir($cachePath) or mkdir($cachePath, 0750, true);
      file_put_contents($cache, $res, LOCK_EX);
      return $res;
    } else {
      return file_get_contents($cache);
    }
  }

  //= string
  static function compileFile($file, $config = null) {
    $str = file_get_contents($file);

    if (!is_string($str)) {
      throw new Exception(null, "Cannot compile template [$file] - it doesn't exist.");
    }

    return static::compile($str, $config);
  }

  //= Template
  static function template($str, $config = null) {
    return (new Template(static::config($config)))
      ->loadStr($str);
  }

  //= Template
  static function templateFile($file, $config = null) {
    return (new Template(static::config($config)))
      ->loadFile($file);
  }

  //= $result
  static function pcreCheck($result = null) {
    $code = preg_last_error();

    if ($code == PREG_NO_ERROR) {
      return $result;
    } else {
      throw new PcreError($code);
    }
  }

  // $separ must be single character.
  static function split($separ, $str) {
    return explode($separ, $str, 2) + ['', null];
  }
}

HTMLki::$config = new Config;

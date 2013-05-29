<?php
/*
  HTMLki - seamless templating with the HTML spirit
  Laravel interface | by Proger_XP
  http://proger.i-forge.net/HTMLki/SZS
*/

$classes =array_get(include __DIR__.'/bundle.php', 'htmlki.autoloads.map');

foreach ($classes as &$class) {
  $class = str_replace('(:bundle)', Bundle::path('htmlki'), $class);
}

Autoloader::map($classes);
Bundle::option('htmlki', 'auto') and LHTMLkiListener::attach();

function overrideHTMLki($path, array $options) {
  return LHTMLkiListener::override($path, $options);
}

// Mediator hooking Laravel View events and creating LHTMLki views according
// to current config values.
class LHTMLkiListener {
  static $overriden;

  // Adds a new overriding rule for views located under given $path.
  //* $path str - of form [bundle::][view[.sub[....]]]. Can also be '' or '::'
  //  as described in the config file.
  //* $options hash - config values to override, such as 'charset' => 'cp1250'.
  static function override($path, array $options) {
    Config::load('htmlki', 'htmlki');
    $path = str_replace('.', '/', $path);
    // Config::set() messes up if key contains multiple '::'.
    Config::$items['htmlki']['htmlki']['override'][$path] = $options;
    static::refresh();
  }

  // Attaches HTMLki to Laravel View events letting it handle new kind of templates.
  static function attach() {
    $self = get_called_class();

    Event::listen(View::loader, function ($bundle, $view) use ($self) {
      $path = Bundle::path($bundle).'views'.DS;
      $config = $self::configFor($path.$view);

      foreach ((array) $config['extensions'] as $ext) {
        if (is_file($file = $path.$view.$ext)) { return $file; }
      }
    });

    Event::listen(View::engine, function ($view) use ($self) {
      $config = $self::configFor($view->path);

      $isKi = array_first((array) $config['extensions'], function ($i, $ext) use ($view) {
        return ends_with($view->path, $ext);
      });

      if ($isKi) {
        $view = $self::factory($config['factory'] ?: 'LHTMLki', $view, $config);
        // Object must inherit from Laravel\View or at least be compatible (risky).
        return is_object($view) ? $view->get() : $view;
      }
    });
  }

  // Returns merged config values possibly overriden at given $path.
  //* $path str - file system path, not in [bundle::][view....] form.
  //= hash global options merged with local config values (if found)
  static function configFor($path) {
    $path = static::pathize($path);

    foreach (static::overriden() as $base => $options) {
      if ($base === '/' or starts_with($path, $base)) {
        return $options + Config::get('htmlki::htmlki');
      }
    }

    return Config::get('htmlki::htmlki');
  }

  // Normalizes file path for quick matching of overriden paths.
  static function pathize($path) {
    return rtrim(str_replace('\\', '/', $path), '/').'/';
  }

  // Caches and returns overriden configurations.
  //= hash like 'application/views/path/here/' => array(...)
  static function overriden() {
    $overriden = &static::$overriden;

    if (!isset($overriden)) {
      $overriden = array();

      foreach ((array) Config::get('htmlki::htmlki.override') as $base => $options) {
        if ($base !== '') {
          if (strpos($base, '::') === false) {
            $bundle = '';
          } else {
            list($bundle, $base) = explode('::', $base, 2);
          }

          $bundle === '' and $bundle = DEFAULT_BUNDLE;
          if (!Bundle::exists($bundle)) { continue; }
          $base = Bundle::path($bundle).'views/'.trim($base, '\\/').'/';
        }

        $base = static::pathize(str_replace('.', '/', $base));
        $overriden[$base] = $options;
      }
    }

    return $overriden;
  }

  // Reloads overriden HTMLki configurations from current Config state (it's
  // cached by overriden()). You're recommended to use override() instead of
  // manually doing Config::set('htmlki::htmlki.override.view/path', array(...)).
  static function refresh() {
    static::$overriden = null;
  }

  // Instantinates a new View object.
  //* $factory Closure, str class name - the way to instantinate it.
  //* $view - View object that's being overriden (has view data and path).
  //= str rendered view, Laravel\View
  static function factory($factory, Laravel\View $view, array $config) {
    if ($factory instanceof Closure) {
      $view = $factory($view, $config);
    } elseif (is_string($factory) and class_exists($factory)) {
      $view = $factory::make('path: '.$view->path, $view->data);

      foreach ($config as $name => $value) {
        $view->config()->$name = $value;
      }
    } else {
      throw new Exception("Invalid LHTMLki 'factory' value [$factory] - expected".
                          " to be a string (defined class name) or a closure.");
    }

    return $view;
  }
}

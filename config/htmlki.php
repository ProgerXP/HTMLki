<?php
return array(
  // List of view extensions for which HTMLki should be enabled.
  'extensions'            => array('.ki.php', '.ki.html', '.htmlki'),

  // Instantinates HTMLki view object. LHTMLki is the default Laravel interface.
  //* string - class name to use for creating with; must inherit from Laravel\View.
  //* closure - function (Laravel\View $view, array $config) - returns a Laravel\View
  //   object, its descendant or a string (rendered template). $config is a hash of
  //   merged config values like those specified in this file.
  'factory'               => 'LHTMLki',

  // Compiled PHP views are placed in storage/views, their name being the view's
  // original path MD5 hash - or that path in slug form. This setting sets the
  // maximum name length before it's hashed. Useful to set to higher value in
  // debug environment where you sometimes need to examine the compiled file and
  // don't want to rummage over dozens of hash file names in search for your view.
  'compiledNameLength'    => Laravel\Request::is_env('local') ? 80 : 32,

  // If set any HTMLki warning will throw a PHP exception.
  'failOnWarning'         => !Laravel\Request::is_env('local'),

  // Parameters for <errors> output.
  'errorsTag'             => 'ul',
  'errorsAttributes'      => array('class' => 'errors'),
  'errorsItem'            => '<li>:message</li>',

  // Specifies list of Input variables that, if present, will be prepended to
  // each <form> being output as <input type="hidden">. Can be single value
  // (converted to array), array or Closure (LHTMLki $view, HTMLkiTagCall $call).
  'stickyFormHiddens'     => array(),

  // Standard HTMLki settings can go here as well. Below are some of them.
  //
  // HTMLki does a very quick whitespace compression so the HTML it generates gets
  // in one line without breaks. For better source readability you can add them.
  'addLineBreaks'         => Laravel\Request::is_env('local'),
  // If enabled HTMLki generates XHTML/HTML 4 compatible output, otherwise - HTML 5.
  'xhtml'                 => false,
  // Character set used in HTMLki templates.
  'charset'               => Laravel\Config::get('application.charset', 'utf-8'),

  // Above were global options. Here you can override them. Key specifies the base
  // path in [bundle::][view[/sub[/...]]] notation - any view being made which
  // file belongs to one of these base directories uses settings specified as
  // this array's value plus global settings not overriden by it.
  //
  // If you want to override value for application views only leaving all bundles
  // with the global settings use '::' as the key. Also, if for some reason you
  // want to specify a 'catch-all' - use empty key ('') or '*'.
  //
  // You can use '.' here instead of '/' (bundle::view.sub) but don't do so in
  // Config::set('htmlki.override.bundle::view.sub') - because Laravel will treat
  // 'sub' as a subkey of 'bundle::view', not as part of the key. This glitch
  // is taken care of by overrideHTMLki() function which you're encouraged to use.
  //
  // Note that paths are matched in order they're defined so base directory
  // will shadow more specific directories defined after it - just like with routes.
  'override'              => array(
    // Sample overriding. All views of mybundle will be read in CP1251 charset and
    // rendered using MyBundle\MyHTMLkiView class instead of default LHTMLki.
    // All other options will use global values set above.
    /*
      'mybundle::'          => array(
        'factory'           => 'MyBundle\\LHTMLki',
        'charset'           => 'cp1251',
      ),
    */

    // Settings for application/views/admin/export/ and nested.
    //'admin/export'        => array('charset' => 'latin-1'),
    // Overrides all application/views but not bundles/*/views/:
    //'::'                  => array('extensions' => '.ki'),
    // Catch-all:
    //''                    => array('xhtml' => true),
  ),
);
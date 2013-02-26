<?php
return array(
  'htmlki' => array(
    // If autostarted HTMLki templates will be automatically handled. Otherwise
    // you need to set up view listeners or call LHTMLkiListener::attach() manually.
    'auto' => true,

    'autoloads' => array(
      'map' => array(
        // Standalone HTMLki classes.
        'HTMLki'          => '(:bundle)/htmlki.php',
        'HTMLkiCompiler'  => '(:bundle)/htmlki.php',
        'HTMLkiTemplate'  => '(:bundle)/htmlki.php',
        'HTMLkiTagCall'   => '(:bundle)/htmlki.php',
        // Laravel interface to HTMLki.
        'LHTMLki'         => '(:bundle)/laravel.php',
      ),
    ),
  ),
);
# HTMLki - seamless templating with the HTML spirit

**HTMLki** takes a non-mainstream approach. Unlike inventing yet another PHP or Mustache it imbues old good HTML with new features - loops, variables, localization, custom tags and more without breaking its original clean form.

```
  <ul $menu>
    <li "$classes">
      <img $icon src=$icon>
      <a "$url" target=$target>$caption</a>
    </li>
  </endul>
```

What we see here is:

* A loop. `<ul>` is only output if there's at least one item in `$menu`
* An «if» - `<img>` is only output if `$icon` is non-falsy
* A bunch of attribute magic - `<li "classes">` (`<li class="classes">`), `<a "url">` (`<a href="url">`)
* Anti-XSS: `$caption` is a variable, **escaped by default**

It has no dependencies and works out of the box in **PHP 5.2 and up** - simply include it and you're ready to go.

[ [Full syntax & reference](http://proger.i-forge.net/HTMLki/SZS)

## Usage

**Available for Composer** under `proger/htmlki` at [Packagist](https://packagist.org/packages/proger/htmlki).

**Standalone:**

```PHP
require_once 'htmlki.php';

echo HTMLki::template(HTMLki::compile('<radio>'));
    //=> <input type="radio">
```

## Features

HTMLki compiles into valid PHP code and thus has very small templating overhead. Any output is HTML-escaped **by default** so there's little chance of XSS.

HTMLki imbues HTML with:

* [loops and conditions](http://proger.i-forge.net/HTMLki/SZS#loops) - like in the above example: `<ul $list>` or `<if $a == 3>`
* [attribute magic](http://proger.i-forge.net/HTMLki/SZS#attr) - automatic expansion of `<form file>` into `<form enctype="multipart/form-data">`, `<div "span-6">` into `<div class="span-6">` and more
* [tag magic](http://proger.i-forge.net/HTMLki/SZS#tags)
  * shortcuts (`<radio>` into `<input type="radio">`)
  * multitags (`<thead/tr>` into `<thead><tr>`)
  * singletags (`<textarea />` into `<textarea></textarea>`)
  * and more
* [language lines](http://proger.i-forge.net/HTMLki/SZS#language) - simply text wrapped in double quotes: `<b>"Hello!"</b>`
* [expressions and variables](http://proger.i-forge.net/HTMLki/SZS#brackets) - like `{ date('d.m.y') }`
* [PHP code](http://proger.i-forge.net/HTMLki/SZS#php) - just as you guess: `(php)<?='string'?>` - short PHP tags expanded automatically so you don't have to care about `php.ini` settings
* [function-tags](http://proger.i-forge.net/HTMLki/SZS#funcs) - in form of custom tags like `<include>`
* most constructs can be escaped, such as `""Not a language."`, `{{ not_an_expr }` and `$$notAVar`.
* this list is not complete - refer to [Syntax](http://proger.i-forge.net/HTMLki/SZS#syntax) for all enhancements

The above doesn't require any additional integration code. However, if you can blossom HTMLki to the fullest by doing custom filtering on tag attributes and tag themselves.

For example, HTMLki might automatically expand `src`, `href` and `action` attributes to full URL and might have tags like `<errors>` that output the list of errors linked to an input field (textarea, selectbox, etc.).

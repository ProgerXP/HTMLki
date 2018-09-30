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

* A loop: `<ul>` is only output if there's at least one item in `$menu`
* An "if": `<img>` is only output if `$icon` is non-falsy
* A bunch of attribute magic: `<li "classes">` (`<li class="classes">`), `<a "url">` (`<a href="url">`)
* Anti-XSS: `$caption` is a variable, **escaped by default**

It has no dependencies and works out of the box in **PHP 5.6 and above**.

[ [Full documentation](http://squizzle.me/php/htmlki) ]

## Usage

**Available for Composer** under `proger/htmlki` at [Packagist](https://packagist.org/packages/proger/htmlki).

**Standalone:**

```PHP
// Configure your autoloader to load the HTMLki namespace from class/HTMLki/.

echo HTMLki\HTMLki::template(HTMLki\HTMLki::compile('<radio>'));
  //=> <input type="radio">
```

## Features

HTMLki compiles into valid PHP code and thus has very small templating overhead. Any output is HTML-escaped **by default** so there's little chance of XSS.

In a nutshell, HTMLki imbues HTML with:

* [loops and conditions](http://squizzle.me/php/htmlki#loops) - like in the above example: `<ul $list>` and `<if $a == 3>`
* [attribute magic](http://squizzle.me/php/htmlki#attr) - automatic expansion of `<form file>` into `<form enctype="multipart/form-data">`, `<div "span-6">` into `<div class="span-6">` and more
* [tag magic](http://squizzle.me/php/htmlki#tags)
  * shortcuts (`<radio>` into `<input type="radio">`)
  * multitags (`<thead/tr>` into `<thead><tr>`)
  * singletags (`<textarea />` into `<textarea></textarea>`)
  * and more
* [language lines](http://squizzle.me/php/htmlki#language) - simply any text wrapped in double quotes: `<b>"Hello!"</b>`
* [expressions and variables](http://squizzle.me/php/htmlki#brackets) - like `{ date('d.m.y') }`
* [PHP code](http://squizzle.me/php/htmlki#php) - just as you guess: `<?='string'?>` - short PHP tags expanded automatically so you don't have to care about any particular `php.ini` settings
* [function-tags](http://squizzle.me/php/htmlki#funcs) - in form of custom tags like `<include>`
* most constructs can be escaped, such as `""Not a language."`, `{{ not_an_expr }` and `$$notAVar`.
* this list is not complete - refer to the [documentation](http://squizzle.me/php/htmlki#syntax) for all enhancements

The above doesn't require any additional integration code. However, you can tailor HTMLki into a markup ideal for your particular application by adding handlers for specific tags, attributes, etc.

For example, HTMLki can automatically expand `src`, `href` and `action` attributes into full URLs, or have tags like `<errors>` that output the list of errors linked to some input field (textarea, selectbox, etc.).

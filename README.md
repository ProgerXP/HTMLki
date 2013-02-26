# HTMLki - seamless templating with the HTML spirit

**HTMLki** takes a non-mainstream approach. Unlike inventing yet another PHP or Mustache it imbues old good HTML with new features - loops, variables, localization, custom tags and more without breaking its original clean form.

```
  <ul $menu>
    <li "$classes">
      <img $icon src=$icon>
      <a "$url" target=$target>$caption</a>
    </li>
  </ul>
```

That's the loop.

It has no dependencies and works out of the box in **PHP 5.2 and up** - simply include it and you're ready to go.

**Unless you're using Laravel HTMLki is just a single file (`htmlki.php`).**

[ [Full syntax & reference](http://proger.i-forge.net/HTMLki/SZS) | [Laravel bundle page](http://bundles.laravel.com/bundle/htmlki) ]

## Usage

**Standalone:**

```PHP
require_once 'htmlki.php';

echo HTMLki::template(HTMLki::compile('<radio>'));
    //=> <input type="radio">
```

**Laravel bundle:**

```
php artisan bundle:install htmlki
```

After installation copy code from `bundle.php` into your `application/bundles.php`.
Make sure to read comments in `config/htmlki.php` - there are many ways to customize HTMLki.
You might also be interested in Laravel integration section below.

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

For example, in Laravel HTMLki automatically expands `src`, `href` and `action` attributes to full URL and there are tags like `<errors>` that output the list of errors linked to an input field (textarea, selectbox, etc.).

## Laravel integration

HTMLki officially comes with a [Laravel](http://laravel.com) bundle (http://bundles.laravel.com/bundle/htmlki) that automatically handles **Views** with extensions `.ki.php`, `.ki.html` and `.htmlki` (can be changed in the config file).

**LHTMLki** is a **View** extending `Laravel\View` which adds multiple features linked with Laravel which are described below one by one.

### Configuration
HTMLki is configurable using standard Laravel means (`bundles/htmlki/config/htmlki.php` file).

There are global options and per-path options affecting only **Views** residing under a certain path. These are specified in `override` array. On run-time you should not directly modify this array as it's cached - do this instead:
```PHP
  overrideHTMLki('mybundle::admin.sub', array('extensions' => '.ki'));
```

Make sure HTMLki bundle is started or this function won't be available.

### Last input values
Last input values are automatically inserted unless there's an explicit `<input value="x">`.

```
  <form "go">
    <text "login">
    <password "password" value="">
    <checkbox "remember">
  </form>
```

After this form is submitted `login` and `remember` fields will have the value/check state as on the previous page while `password` will always be blank. This works for `input` and `textarea% tags.

The **default** attribute can be used to specify non-overriding default value that's replaced by last input when it exists. It's similar to HTML 5's **placeholder** but acts as a normal value. For `<textarea>` it's named **defaulting**:
```
  <text "author" default=Anonymous>

  <textarea defaulting>This text won't be replaced.</textarea>
```

### Expansion of action and href
**action** and **href** attributes are automatically expanded to full URL using these simple rules:

* If it contains `@` it's **controller action**: `URL::to_action('cart@clear')`
* Otherwise, if a **named route** exists it's used: `URL::to_route('contacts')`
* Finally, it's resolved as **normal URL**, either relative or absolute: `URL::to('normal/page?goes=here')`

if URL starts with `mailto:` it's not processed.

If URL contains a query string it's appended to the expanded URL: `<a "user@login?login=$login">` becomes `<a href="/store/user/login?login=test">`.

Additionally, an URL can start with **plus sign (`+`)** - in this case current query variables are retained. If URL specifies a query string its variables override request's. For example, this template will produce different links depending on the page we open (such as `goods/show?page=1&sort=price`):
```
  <a  "goods@show?page=2">Next page</a>
  <!-- <a href="goods/show?page=2"> -->
  <a "+goods@show?page=2">Next page</a> retaining view options
  <!-- <a href="goods/show?sort=price&page=2"> -->
```

#### `<form>`'s action
In addition to the above expansion form's **action** is allowed to contain query variables which are removed and instead placed as a set of `<input type="hidden">` immediately after the opening tag.

```
  <form "cart@put?item[$id]=$qty">
```

Becomes:
```
  <form action="cart/put">
    <input type="hidden" name="item[75]" value="1">
```

#### Expansion of src
**src** attribute is expanded as an asset path (`URL::to_asset()`). Additionally, `bundle::` prefix can be present; if not application's asset is assumed.

For example: `<img "admin::logo.png">` becomes `<img src="public/bundles/admin/logo.png">`.

#### `<errors>` tag
`<errors>` tag is used to insert a list of errors usually produced by a **Validator**. The name of input for which errors are retrieved is given as `<errors "input_name">` - if omitted, last output input will be used or if there's none it's ignored an a warning is emitted (logged by default).

For example:
```
  <input "login">
  <errors>
  <!-- the same: -->
  <errors "login">
```

Produced output (tag name, attributes and item format are customizable through the config):
```
  <input type="text" name="login">

  <ul class="errors">
    <li>Login must be alphanumeric.</li>
    <li>Login must be at least 3 characters long.</li>
  </ul>
```

#### `<js>` and `<css>` tag
`<js>` and `<css>` tags register new assets.

```
  <js "jquery.js" js/jquery-1.9.1.js to=head>
  <css css/botstrap.css>
```

* asset name is optional and is specified as `"name.ext"`
* **to** attribute is optional and specifies the container (defaults to `default` - the global container)
* path to asset is expanded with `URL::to_asset()` and is the only required attribute

The above two tags are equivalents of calling this from your template (or elsewhere):
```PHP
  Asset::container('head')->script('jquery.js', 'js/jquery-1.9.1.js');
  Asset::style('bootstrap.css', 'css/bootstrap.css');
```

#### `<csrf>` tag
`<csrf>` tag lets you insert request token. Variable name can optionally be given as `<csrf "input_var">`.

```
  <csrf>
```

Becomes:
```
  <input type="hidden" name="csrf_token" value="60S9QvppzfWs3PnOBgB81x2nXe5yFwTx9Bm5L8GJ">
```

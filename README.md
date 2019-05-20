# PHPdebug

PHPdebug is a web based debug log/error collector for PHP and
Javascript. The goal is to provide the PHP developer a unified place
to check all errors, logs, phpinfo and user session to simplify and
accelerate the task of finding bugs.

PHPdebug is implemented as one PHP source code that is at the same
time the PHP web application with the debugger user interface and the
Ajax server that collects all debug info from the PHP app being
debugged.

## Features

* Collect logs, errors and debug messages from different sources and
  display in one place

* Present logs in a user friendly card driven structure

* Allow users to control the server and scrolling as well as request
  more information as needed

* Simple to install and use

* Can run under the user's httpd or as a PHP standalone httpd

## License

This software is distributed under the MIT license. Please read
LICENSE for information on the software availability and distribution.

## Dependencies

PHPdebug depends on:

* PHP 5.6

* Bootstrap 4.3.1 CDN

* JQuery 3.3.1 CDN

* Fontawesome 5.8.1 CDN

## Instalation

To install PHPdebug, you only really need the debug.php file. If you
*git clone* the project and want to use it under your httpd, put it in
a directory accesible thru a local URL. In a debian/ubuntu system for
instance it would be under /var/www/html. You can also create a link
to it under your httpd's root directory.

In debian/ubuntu, that can be done with the following `chmod` command:

```sh
chmod o+rx /var/log/apache2
```

Running in standalone mode, you can optionally use the `phpdebug.ini` to
tweak some parameters and use the `start.sh` shell to run it.

## Modes (httpd *vs* standalone)

PHPdebug can be run in two different modes: under a httpd server and
using the standalone PHP httpd. There are cons and pros for both
modes. In both cases, PHPdebug should have read access to the httpd's
error logs. If not, it won't show error from that source. There are
some errors that cannot be "catched" and can only be shown if the
httpd's error logs are accessible.

### Httpd mode

Under the httpd (apache or nginx): the debugger is just another PHP
application and can even be installed in the same directory as your
PHP app. But leaving debug.php in a production environment is not
recommended.

If installed in a different directory than you PHP application, you
should inform the URL to debug.php in the first argument of
`debug_start()` function, see below.

### Standalone mode

In standalone mode, phpdebug can be installed in any directory and
does not need to be under the httpd's directories. When run in
standalone mode, phpdebug will try to open a local TCP port to serve
HTTP connections as localhost. The default port is 8080 but that can
be changed in the `phpdebug.ini` file.

To run phpdebug in standalone mode, simply run the `start.sh` script.
If `DEBUG_LAUNCHBROWSER` is set to 1, `start.sh` will attempt to launch
the phpdebug URL.

To debug different applications in the same host avoiding having all
messages displayed together in the same UI, you can run more than one
instance of PHPdebug by setting different HTTP port numbers to each
instance.

## INI File

At standalone mode startup, the phpdebug.ini file is read to allow
tweaks to a few PHPdebug parameters. These are the settings that can
be changed by the phpdebug.ini file:

```ini
; TCP Port to run the local server
DEBUG_PORT=8080

; Set to 1 to log on the HTTP error log all requests with complete headers (default: 0)
DEBUG_LOGREQUEST=0

; Set the interval in seconds between queries to the debug server (default: 3)
DEBUG_QUERYINTERVAL=3

; Max error query size
DEBUG_MAXQUERYSIZE=128

; If 1, the start.sh script will attempt to launch a new browser window on the local server
DEBUG_LAUNCHBROWSER=0

; The HTTPd's error_log
DEBUG_HTTPDERRORLOG=/var/log/apache2/error.log

; If 1, log the errors from the HTTPd's error log too
DEBUG_LOGHTTPDERROR=1
```

## User Interface

The PHPdebug web UI is very simple: a header with some buttons and the
rest of the screen containing entries with problems or logs reported
by PHP, Javascript or the httpd's error log. It can also inform the
output of phpinfo() or the user's session data. Among the buttons
there's also a connection indicator and error messages.

![PHPdebug interface](https://github.com/rorabr/phpdebug/blob/master/images/screenshot.png)

### Stop and Start buttons

![PHPdebug interface](https://github.com/rorabr/phpdebug/blob/master/images/stop.png)
![PHPdebug interface](https://github.com/rorabr/phpdebug/blob/master/images/start.png)

If PHPdebug is stopped, it won't connect to the
server to fetch error messages which can acumulate and be lost after
reaching the storage limit (128 entries by default). The start
restores PHPdebug to connect and fetch entries from the server.

### Freeze and Resume buttons

![PHPdebug interface](https://github.com/rorabr/phpdebug/blob/master/images/freeze.png)
![PHPdebug interface](https://github.com/rorabr/phpdebug/blob/master/images/resume.png)

Stops and resume control the automatic screen
scrolling. Usefull to keep the current screen frozen in place until
the error is read/addressed. The UI will keep fetching records from
the server and inserting them in the web page, but not scrolling.

### Clear button

![PHPdebug interface](https://github.com/rorabr/phpdebug/blob/master/images/clear.png)

Delete all entries and clear the screen.

### Phpinfo button

![PHPdebug interface](https://github.com/rorabr/phpdebug/blob/master/images/phpinfo.png)

Only shown when the server is accessed thru a web
server like apache or nginx. When run from a standalone server, this
button is hidden because it doesn't make sense to check phpinfo() from
the PHP standalone http. This button requests a phpinfo from the
webserver and displays the whole content on screen. The output it's
usually bigger that the screen.

### Session button

![PHPdebug interface](https://github.com/rorabr/phpdebug/blob/master/images/session.png)

If the PHP application being debugged is sending it's
session data to the server this button will be shown and if used will
request the lastest session info. Check the SESSION section below.

### Card close button

Each entry can be deleted from the screen by clicking in the close
button on the top right of the message card.

### Session trash button

The session card has an extra trash button to delete the session from
the server. It also deletes the session card from the screen and hides
the session button until a new session is sent to the server.

## Application

To use PHPdebug you must *require* the debug.php PHP source and call
the functions to install the handlers. If debug.php is present in the
same directory of your application, the basic syntax would be:

```php
require_once("debug.php");
debug_start(); // start debugger with debug.php on the current directory using same httpd
```

If you keeping debug.php in a different directory, but still under the
same web server as your application:

```php
$phpdebug_directory = "..."; // or use the set_include_path() function
require_once("$phpdebug_directory/debug.php");
debug_start("http://localhost/debug/debug.php", true); // same httpd, different directory
```

And finally, if you're running PHPdebug in standalone mode:

```php
$phpdebug_directory = "..."; // or use the set_include_path() function
require_once("$phpdebug_directory/debug.php");
debug_start("http://localhost:8080/debug.php", true); // standalone httpd
```

The `debug_start()` function installs the necessary handler to catch
errors and send them to the debug server. Check the syntax of this
function bellow.

Also, you may want to install the Javascript error handler to catch
javascript errors that are shown in the browsers console window. To do
that, call `debug_installJShandler()` inside the HTML's header section:

```php
<html lang="en">
  <head>
    <meta name="description" content="My PHP App">
    <?php
      debug_installJShandler();
    ?>
    <script .../>
```

## Functions

### [PHP] debug\_start(url, session\_flag)

Start the debugger using `url` as the address to send data to the
server. This address is the URL by which the PHP page of the
debugger can send Ajax requests to store events. If the source file is
in the same directory as your PHP application, `url` can be empty. If
you're using PHPdebug in standalone mode, you should specify
the HTTP port if not 80 (the default is 8080).

The `session_flag` determines if at the end of your PHP script, session
data will be sent to the server. Use `true` if you want to check
session data in PHPdebug. Check sessions below.

### [PHP] debug\_installJShandler()

Installs the Javascript error handler in the HTML of your PHP
application. This function should be called in the header section of
the HTML. If you are using onerror directive of `<link>` and `<script>`,
this function should be called before these directives.

### [PHP] debug\_log(msg, stack\_level)

Send `msg` to PHPdebug to be displayed. If `stack_level` is greater
than 0, skip `stack_level` stack frames when to get the PHP source
code's line number. The default is 0, showing the line number of the
source code that called `debug_log`.

```php
debug_log("This will be logged on the debugger, remotelly");
```

If you create you own log functions or methods, use `stack_level` to
log the correct line number:

```php
function my_log($msg) {
  /* This will generate a log entry with the line of my_log's caller */
  assert(debug_log("Generated by my_log: $msg", 1)); // skip the first (1) stacklevel
}
```

### [Javascript] debug\_log(msg)

Send `msg` to PHPdebug from the Javascript in your application. This
is equivalent of calling `console.log(msg)` with the exception that
the message is displayed in PHPdebug as well as in the console.

### [Javascript] debug\_sendajax(type, msg, url, line\_number)

If you need more control of the message being displayed in PHPdebug,
use `debug_sendajax()`. You can specify the `type` of error (Error,
Notice, Warning, etc), the `msg` itself, the `url` from which the
message is generated and the `line_number`. Generally, `debug_log`
should be suficient for all Javascript logs.

## Tracking asset loading

PHPdebug can report asset loading errors using the onerror event
handler of `<link>`, `<script>` and `<img>` HTML tags. To track asset
loading, include the following HTML asset loading tags:

```html
onerror='debug_onloadhandler(this)'
```

For example, to load the bootstrap.min.css CSS stylesheet, the HTML
link tag would be:

```html
<link rel="stylesheet" href="bootstrap.min.css" onerror='debug_onloadhandler(this)'/>
```

In case there is an error loading the css file, PHPdebug will be
alerted to display this error.

The exact same syntax is used on the `<script>` and `<img>` HTML tags.
In production you probably will want to remove this. One way of doing
that is using PHP's `assert`, as shown below. If not using assert,
a condition can be used in production:

```php
<?php
  $onerror = " onerror='debug_onloadhandler(this)'"; // mind the space
  if ($production) {
    $onerror = "";
  }
?>
<link rel="stylesheet" href="bootstrap.min.css"<?= $onerror?>/>
```

## Sessions

PHPdebug does not use PHP's sessions to store data to avoid conflicts
with the PHP application it is debugging. If your application uses
sessions, checking it's contents is a very usefull tool to find bugs.
One way of doing that, is setting the parameter `session_flag` to true
when calling `debug_start()`. When this parameter is true, at the end
of your PHP script, PHPdebug will send all session's variables to the
debugger using a registered shutdown function.

When receiving a session from the application, the PHPdebug
interface will show the Session button. Otherwise it will remain
hidden. By clicking on the Session button, the interface requests the
PHPdebug server the last session stored and displays it as a new
error logs.

Only one session is stored in PHPdebug. After reloading your
application more than once, only the last session will be stored (and
displayed). To avoid confusion, the date and time shown by the
sessions header in the interface is the sessions's store time.

Please take care of using this function when sensitive data is stored
in PHP sessions (like passwords). All data from PHPdebug, including
sessions are stored in a file in the `/tmp` directory.

## Assertions

PHPdebug can benefit by PHP assertions, aka `assert()`. By using
`assert()` you can make sure the debugger will not be invoked in
production and performance compromised of calling PHPdebug's
functions unnecessarily. Assert can be used to require `debug.php`
conditionally on development:

```php
assert((require_once("debug.php")) || true);
```

The PHP functions exported by PHPdebug return true to be used in
`assert()` directly. Example calls using `assert()`:

```php
assert(debug_start("http://localhost/debug/debug.php", true));
assert(debug_installJShandler());
assert(debug_log("This will be logged on the debugger, remotelly"));
```

When tracking asset loading, assert() can be used to set the onload
event handler when not in production. In the following example a local
version of `bootstrap.css` is also set:

```php
<?php
assert(debug_installJShandler());
$asset_error = "";
assert(($asset_error = " onerror='debug_onloadhandler(this)'") || true);
$bootstrapcss = "https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css";
assert(($bootstrapcss = "assets/css/bootstrap.min.css") || true);
?>
  <link rel="stylesheet" href="<?= $bootstrapcss ?>"<?= $asset_error ?>/>
```

## Uncachable errors

Not all errors can be catched at runtime by PHP. Syntax errors on the
first PHP script will not be detected and sent to PHPdebug. These
error are logged only in the httpd's error log. Syntax error on
required scripts however are detected and logged.

If you do not have access to the httpd's error log, one strategy to
catch syntax errors is to have a base script that it's only job is to
setup PHPdebug and require all other PHP source code.

PHP exceptions are cached by PHPdebug but execution is stoped at that
point. Therefore, following errors are not detected after the
exception is triggered.

## Storage

PHPdebug uses a temporary file to store it's data instead of relying
on PHP sessions. This is not to contaminate the PHP session with
debugger data leaving all session's data with the user's information.
This temporary file is written to the /tmp directory by default,
therefore the user running PHPdebug must have write permissions to
/tmp.

To avoid using too much disk space, PHPdebug will limit the number of
debug entries to 128 by default but can be changed by the
`DEBUG_MAXQUERYSIZE` ini setting.

It's not recommended that the PHP session gets too big because it can
affect performance as PHP reads and writes the session between all
page loads. [How much data is too much?](https://stackoverflow.com/questions/17554990/session-variables-how-much-data-is-too-much?answertab=votes#tab-top)

## Example

Checkout the test.php source code on examples of using PHPdebug.
This file can be accessed to generate up to 8 different debug entries.
The exception is commented out because it will stop execution and
prevent the Javascript errors from being logged. Uncomment it to see
the exception handling in PHPdebug.

## Contributing

Please get in touch before making changes. Further information in
CONTRIBUTING.md.

## FAQ

1. Why cram everything into one source code?

  To make the user experience simpler by using only one URL for
  everything.

2. Why not use PHP sessions?

  To avoid conflicts with the user's app sessions.

3. Why use assets loaded from cdn server?

  To speed-up page loading due to browser (and ISP) cache.

## TODO

There are plenty of things I would like to see in PHPdebug in the
future.

1. Store data in memory. Either by using a memory cache like memcache
   or by making a third mode of operation: a complete httpd.

2. Load assets locally to run without an internet connection.

3. Test if exception\_handler is needed (register shutdown takes care
   of exception handling).

4. Handle Ajax alocation errors

5. Handle errors reading and parsing tmp PHPdebug JSON data file

6. Implement multiple data sessions (low priority?)

7. Translate to other languages

8. Implement PHP call tracing (very low priority)

## Bugs

1. When displaying the phpinfo, the style of the rest of the interface
   is affected. After clearing everything or removing the phpinfo, the
   original style is restored.

## Author

PHPdebug was developed by Rodrigo Antunes rorabr@github.com


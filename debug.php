<?php
/** debug.php - PHP/HTTP debugger/logger
    This is a PHP debugger that uses HTTP POSTS to store/display errors from PHP
    and Javascript onto a browser window/tab.
    developed by rorabr@github https://rora.com.br - 2019-04-02
  USE:  php [--php-ini phpdebug.ini] -S localhost:8080 debug.php     */

// Some constants and vars
const E_DEBUG_NONE = 0;
$debug_URL = "http://localhost:8080/debug.php";
$debug_fromError = array("start" => "User", "phperror" => "PHP", "jserror" => "Javascript", "httpd" => "Webserver", "user" => "User", "int" => "Internal", "session" => "Session");
$debug_fontAwesome = array("start" => "", "phperror" => "fab fa-php", "jserror" => "fab fa-js-square", "httpd" => "fas fa-server", "user" => "fa fa-user", "int" => "fa fa-cog", "session" => "fa fa-address-card");
$debug_errortype = array ( // define an assoc array of error string
    E_DEBUG_NONE         => '',
    E_ERROR              => 'Error',
    E_WARNING            => 'Warning',
    E_PARSE              => 'Parsing Error',
    E_NOTICE             => 'Notice',
    E_CORE_ERROR         => 'Core Error',
    E_CORE_WARNING       => 'Core Warning',
    E_COMPILE_ERROR      => 'Compile Error',
    E_COMPILE_WARNING    => 'Compile Warning',
    E_USER_ERROR         => 'User Error',
    E_USER_WARNING       => 'User Warning',
    E_USER_NOTICE        => 'User Notice',
    E_STRICT             => 'Runtime Notice',
    E_RECOVERABLE_ERROR  => 'Catchable Fatal Error'
  );
$debug_startFile = "";
$debug_startTime = "";
$debug_errorLogOk = 1; // set to 0 when the httpd's error log is not acessible
$debug_serverHasSession = 0; // set to 1 when phpdebug has sessions (informed to the UI)
$debug_clientSendSession = false; // if true, the client sends the session at shutdown

/** Return the complete request URL, source: https://stackoverflow.com/questions/6768793/get-the-full-url-in-php */
function debug_completeURL() {
  return((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . (isset($_SERVER["HTTP_PORT"]) && $_SERVER["HTTP_PORT"] != 80 ? ":" . $_SERVER["HTTP_PORT"] : "") . $_SERVER["REQUEST_URI"]);
}

/** Return the dir and name of the temporary file to store the error list */
function debug_tmpFname() {
  return(sys_get_temp_dir() . "/phpdebug_" . sha1("phpdebugsessionid" . debug_completeURL()) . ".tmp");
}

/** Generate a HTML to express an associative hash $h (recursive) */
function debug_assocHtml($h) {
  $s = "<table class='table table-sm'><tbody><thead class='thead-light'><tr><th>key</th><th>&nbsp;</th><th>value</th></tr></thead>";
  foreach ($h as $key => $value) {
    $s .= "<tr><td>$key</td><td><span class='text-right'><i class='fa fa-arrow-right'></i></span></td><td>" . (is_array($value) ? debug_assocHtml($value) : $value) . "</td></tr>";
  }
  $s .= "</tbody></table>";
  return($s);
}

/** HTTP POST the assocarray $data to $url encoded in JSON */
function debug_webpost($url, $data) {
  global $debug_startFile, $debug_startTime;
  if ($debug_startFile != "") { // piggy back the POST call to signal a new start
    $start = array("func" => "start", "time" => $debug_startTime, "errmsg" => "",
      "filename" => $debug_startFile, "line" => 1);
    $debug_startFile = "";
    $debug_startTime = "";
    debug_webpost($url, $start);
  }
  if (! isset($data)) return;
  $options = array(
    'http' => array( // use key 'http' even if you send the request to https://...
      'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
      'method'  => 'POST',
      'content' => json_encode($data)
    )
  );
  $context = stream_context_create($options);
  $result = file_get_contents($url, false, $context);
  //error_log("result: " . json_encode($result));
}

/** User defined error handling function: http://www.php.net/manual/en/errorfunc.examples.php */
function debug_errorHandler($errno, $errmsg, $filename, $linenum, $vars) {
  global $debug_URL, $debug_errortype;
  debug_webpost($debug_URL, array("func" => "phperror", "time" => date("Y-m-d H:i:s"), "errno" => $errno,
    "type" => $debug_errortype[$errno], "errmsg" => $errmsg, "filename" => $filename, "line" => $linenum));
}

/** Exception defined handling function
  * (not used anymore because of the shutdown handler) */
/*function exception_handler($exception) {
  global $debug_URL;
  debug_webpost($debug_URL, array("func" => "phperror", "time" => date("Y-m-d H:i:s"), "type" => "Exception",
    "errmsg" => $exception->getMessage(), "errno" => $exception->getCode(),
    "filename" => $exception->getFile(), "line" => $exception->getLine()));
  return(false);
}*/

/** This will be called when php script ends.
 *  source: https://stackoverflow.com/questions/1900208/php-custom-error-handler-handling-parse-fatal-errors
      and : https://www.hhutzler.de/blog/handling-php-parse-errors/ */
function debug_shutdownHandler() 
{
  global $debug_URL, $debug_errortype, $debug_clientSendSession, $debug_startFile, $debug_startTime;
  $lasterror = error_get_last();
  if (isset($lasterror)) {
    switch ($lasterror['type'])
    {
      case E_ERROR:
      case E_CORE_ERROR:
      case E_COMPILE_ERROR:
      case E_USER_ERROR:
      case E_RECOVERABLE_ERROR:
      case E_CORE_WARNING:
      case E_COMPILE_WARNING:
      case E_PARSE:
        $data = array("func" => "phperror", "time" => date("Y-m-d H:i:s"), "type" => $debug_errortype[$lasterror['type']],
          "errmsg" => $lasterror['message'] , "errno" => $lasterror['type'],
          "filename" => $lasterror['file'], "line" => $lasterror['line']);
        debug_webpost($debug_URL, $data);
    }
  }
  if ($debug_clientSendSession && isset($_SESSION) && count($_SESSION) > 0) {
    $data = array("func" => "session", "time" => date("Y-m-d H:i:s"), "type" => "",
        "errmsg" => "<div class='session'>" . debug_assocHtml($_SESSION) . "</div>", "errno" => 0);
    debug_webpost($debug_URL, $data);
    $debug_clientSendSession = false;
  }
  if ($debug_startFile != "") { // no other webpost, send in the start
    $start = array("func" => "start", "time" => $debug_startTime, "errmsg" => "",
      "filename" => $debug_startFile, "line" => 1);
    $debug_startFile = "";
    debug_webpost($debug_URL, $start);
  }
}

/** Prepares the PHP debugger client, receives the debugger URL (optional).
    If the URL is "", uses the request URL to build the debug.php URL. */
function debug_start($url = "", $sendSession = false) {
  global $debug_URL, $debug_clientSendSession, $debug_startFile, $debug_startTime;
  $debug_startFile = __FILE__;
  $debug_startTime = date("Y-m-d H:i:s");
  error_reporting(E_ALL | E_STRICT);
  if ($url == "") {
    $url = debug_completeURL();
    if (substr($url, -10) != "/debug.php") {
      $debug_URL = preg_replace('/\/[^\/]*$/', "/debug.php", $url);
    }
  } else {
    $debug_URL = $url;
  }
  $debug_clientSendSession = $sendSession;
  set_error_handler("debug_errorHandler");
  #set_exception_handler("exception_handler"); // TODO: check if needed
  register_shutdown_function("debug_shutdownHandler");
  return(true);
}

/** Helper function to send something to the PHP debugger from normal PHP code */
function debug_log($msg, $stacklevel = 0) {
  global $debug_URL;
  $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $stacklevel + 1);
  debug_webpost($debug_URL, array("func" => "user", "time" => date("Y-m-d H:i:s"), "type" => "Notice", "errmsg" => $msg, "filename" => $trace[$stacklevel]["file"], "line" => $trace[$stacklevel]["line"]));
  return(true); /* to satisfy assert(debug_log("message")) */
}

/** Output HTML/Javascript code to insert a Javascript error handler on the client side */
function debug_installJShandler() {
  global $debug_URL;
  ?>
  <script type="text/javascript">
    /* return the current line number, try to cope with bad browsers
     * source: https://stackoverflow.com/questions/2343343/how-can-i-determine-the-current-line-number-in-javascript */
    function debug_linenumber() {
      var e = new Error();
      if (!e.stack) try {
        /* IE requires the Error to actually be throw or else the Error's 'stack'
         * property is undefined. */
        throw e;
      } catch (e) {
        if (!e.stack) {
          return 0; /* IE < 10, likely */
        }
      }
      var stack = e.stack.toString().split(/\r\n|\n/);
      /* We want our caller's frame. It's index into |stack| depends on the
       * browser and browser version, so we need to search for the second frame: */
      var frameRE = /:(\d+):(?:\d+)[^\d]*$/;
      do {
        var frame = stack.shift();
      } while (!frameRE.exec(frame) && stack.length);
      return frameRE.exec(stack.shift())[1];
    }
    function debug_sendajax(type, msg, url, linen) {
      today=new Date(); /* source: https://tecadmin.net/get-current-date-time-javascript/ */
      var dt = today.getFullYear() + '-' + (today.getMonth() + 1) + '-' + today.getDate() + " " + today.getHours() + ":" + today.getMinutes() + ":" + today.getSeconds();
      var ajax = new XMLHttpRequest(); /* plain JS because it runs on the client, TODO: handle construct errors? */
      ajax.open("POST", "<?php echo($debug_URL); ?>", true);
      data = {func:"jserror", type:type, time:dt, errmsg:msg};
      if (url) {
        data["filename"] = encodeURI(url);
        data["line"] = linen;
      }
      ajax.send(JSON.stringify(data));
      return(false); /* errors keep being sent to the console */
    }
    window.onerror = function(msg, url, linen) {
      debug_sendajax("Error", msg, url, linen);
    }
    function debug_log(msg) {
      n = debug_linenumber();
      console.log("Phpdebugger user log: " + msg + " at " + window.location.href + " line " + n);
      debug_sendajax("User Notice", msg, window.location.href, n);
    }
    function debug_onloadhandler(obj) {
      url = obj.src ? obj.src : obj.href;
      debug_sendajax("Error", "Error loading " + obj.tagName + " " + url, obj.baseURI, 1);
    }
  </script>
  <?php
  return(true);
}

/** Insert a new row in the error list */
// TODO: differenciate between multiple sessions
function debug_insertRow($row) {
  // TODO: handle json, read and write errors
  $data = json_decode(file_get_contents(debug_tmpFname()), true);
  if ($row["func"] == "session") {
    $data["session"] = array($row);
  } else {
    if (isset($row["errmsg"])) {
      array_push($data["list"], $row);
      if (count($data["list"]) > get_cfg_var("DEBUG_MAXQUERYSIZE")) {
        array_splice($data["list"], 0, get_cfg_var("DEBUG_MAXQUERYSIZE") - count($data["list"]));
      }
    }
  }
  file_put_contents(debug_tmpFname(), json_encode($data));
}

/** Read the error list (optionally the HTTPd's error log) and empty it */
function debug_readList() {
  global $debug_fontAwesome, $debug_errorLogOk, $debug_serverHasSession;
  error_reporting(E_ERROR | E_STRICT);
  $data = json_decode(file_get_contents(debug_tmpFname()), true);
  if (! isset($data)) {
    $data = array("list" => array());
  }
  $debug_serverHasSession = isset($data["session"]);
  $errorlist = $data["list"];
  $data["list"] = array();
  if (get_cfg_var("DEBUG_LOGHTTPDERROR") || getenv("DEBUG_LOGHTTPDERROR")) {
    $f = get_cfg_var("DEBUG_HTTPDERRORLOG") != "" ? get_cfg_var("DEBUG_HTTPDERRORLOG") : getenv("DEBUG_HTTPDERRORLOG");
    $size = filesize($f);
    if ($size) {
      if (! isset($data["logsize"]) || (isset($data["logsize"]) && $size < $data["logsize"])) { // first read or error.log was rotated
        $data["logsize"] = 0;
      }
      if ($size > $data["logsize"]) {
        $handle = fopen($f, "r");
        fseek($handle, $data["logsize"], SEEK_SET);
        foreach (explode(PHP_EOL, fread($handle, $size - $data["logsize"])) as $error) {
          if ($error != "") {
            array_push($errorlist, array("func" => "httpd", "fa" => $debug_fontAwesome["httpd"],
              "time" => date("Y-m-d H:i:s"), "from" => "Webserver", "type" => "Error", "errmsg" => $error));
          }
        }
        fclose($handle);
        $data["logsize"] = $size;
        $debug_errorLogOk = 1;
      }
    } else {
      $debug_errorLogOk = 0;
    }
  } else {
    $debug_errorLogOk = 2;
  }
  file_put_contents(debug_tmpFname(), json_encode($data));
  error_reporting(E_ALL | E_STRICT);
  return($errorlist);
}

/** Read the session stored in the tmp file */
function debug_readSession() {
  global $debug_fontAwesome;
  error_reporting(E_ERROR | E_STRICT);
  $data = json_decode(file_get_contents(debug_tmpFname()), true);
  if (! isset($data)) {
    $data = array("list" => array());
  }
  #$session = array("func" => "session", "fa" => $debug_fontAwesome["session"],
  #            "time" => date("Y-m-d H:i:s"), "from" => "Webserver", "type" => "Error", "errmsg" => $error);
  $session = isset($data["session"]) ? $data["session"] : array();
  #$data["session"] = "";
  #file_put_contents(debug_tmpFname(), json_encode($data));
  error_reporting(E_ALL | E_STRICT);
  return($session);
}

/** Remove the current session */
function debug_removeSession() {
  error_reporting(E_ERROR | E_STRICT);
  $data = json_decode(file_get_contents(debug_tmpFname()), true);
  unset($data["session"]);
  file_put_contents(debug_tmpFname(), json_encode($data));
  error_reporting(E_ALL | E_STRICT);
}

/** Log the HTTP Request and all headers to the HTTP server's error log */
function debug_logRequest() {
  error_log($_SERVER["REQUEST_METHOD"] . " " . $_SERVER["REQUEST_URI"]);
  foreach (getallheaders() as $name => $value) {
    error_log("    $name: $value");
  }
}

/** Push an internal error onto the error stack */
function debug_internalError($msg) {
  global $debug_fontAwesome;
  $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
  debug_insertRow(array("func" => "int", "fa" => $debug_fontAwesome["int"],
    "time" => date("Y-m-d H:i:s"), "from" => "Internal", "type" => "Error", "errmsg" => $msg,
    "filename" => $trace[0]["file"], "line" => $trace[0]["line"]));
}

/** Uses the output buffer to grab the phpinfo(), may not always work...
    source: https://www.php.net/manual/en/function.ob-start.php */
function debug_grabPhpInfo() {
  ob_start();              // start a output buffer
  phpinfo();
  $phpinfo = ob_get_contents();
  ob_end_clean();          // clean ob2
  return($phpinfo);
}

/** Build the debugger HTML page containing the UI and Javascript */
function debug_buildHTML() {
  ?>
  <!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7" lang=""> <![endif]-->
  <!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8" lang=""> <![endif]-->
  <!--[if IE 8]>         <html class="no-js lt-ie9" lang=""> <![endif]-->
  <!--[if gt IE 8]><!-->
  <html class="no-js" lang="en">
  <!--<![endif]-->
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="author" content="Rodrigo Antunes">
    <title>PHP Remote Debugger/Logger</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQAQMAAAAlPW0iAAAABlBMVEUAAAAAAAClZ7nPAAAAAXRSTlMAQObYZgAAACtJREFUCNdjYGBgYHJgYD7AwP4AhOxqGPg/MMj/AKH6f1AGEOl/AalhYAAAIvYNYwAv6FQAAAAASUVORK5CYII=" rel="icon" type="image/x-icon"/>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css" integrity="sha384-50oBUHEmvpQ+1lW4y57PTFmhCaXp0ML5d60M1M7uH2+nqUivzIebhndOJK28anvf" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
    <script type="text/javascript">
      var cardnum = 0;     // card id used for delete
      var running = 1;     // query the server if 1
      var scrolling = 1;   // scroll down if 1
      // Error handler that forwards phpdebug interface errors to the server (not important after debug.php is ready)
      window.onerror = function(msg, url, linen) {
        today = new Date(); // source: https://tecadmin.net/get-current-date-time-javascript/
        var dt = today.getFullYear() + '-' + (today.getMonth() + 1) + '-' + today.getDate() + " " + today.getHours() + ":" + today.getMinutes() + ":" + today.getSeconds();
        var response = $.ajax({
          url: "debug.php",
          type: "POST",
          data: (JSON.stringify({func:"jserror", type:"Error", time:dt, errmsg:msg, filename:encodeURI(url), line:linen}))
        }).responseText;
        return(false);
      }
      // Post the error list on the UI
      function postErrList(msg) {
        $("#emessage").empty();
        $("#cmessage").empty();
        if (msg.ok && msg.errors) {
          for (var i=0; i<msg.errors.length; i++) {
            if (msg.errors[i].func == "start") {
              $("#debugcontainer").append("<div class='flex-grow-1 pl-1 pt-3 pb-3'><i class='fa fa-sync-alt mr-3'></i><b>" + msg.errors[i].time + "</b> PHPdebug start <b>" + msg.errors[i].filename + "</b></div>");
            } else {
              $("#debugcontainer").append("<div id='card" + cardnum + "' class='debugcard flex-grow-1 border border-dark rounded-sm mt-1'>" +
                "<div class='title p-1'>" +
                  "<i class='" + msg.errors[i].fa + "'></i>&nbsp;" +
                  "<span class='time ml-2'>" + msg.errors[i].time + "</span>" +
                  "<span class='actions'><i class='far fa-window-close' onclick='$(\"#card" + cardnum + "\").remove();'></i></span>" +
                  (msg.errors[i].from == "Session" ? "<span class='actions mr-2'><i class='far fa-trash-alt' onclick='removesession();$(\"#card" + cardnum + "\").remove();$(\"#sessionbtn\").hide();'></i></span>" : "") +
                "</div>" +
                "<div class='body p-1'><span class='from'>" + msg.errors[i].from + "</span>&nbsp;" +
                  (msg.errors[i].type ? "<span class='type'>" + msg.errors[i].type : "") +
                    (msg.errors[i].errno ? "(<span class='errno'>" + msg.errors[i].errno + ")</span>" : "") +
                  "</span>: <span class='msg'>" + msg.errors[i].errmsg +
                  (msg.errors[i].filename ? 
                    "</span> at <span class='filename'>" + msg.errors[i].filename + "</span>, " +
                    "line <span class='line'>" + msg.errors[i].line + "</span>" : "") +
                "</div></div>");
              if (scrolling && cardnum > 0) {
                document.getElementById("card"+cardnum).scrollIntoView();
              }
              cardnum ++;
            }
          }
          if (msg.logok == 0) {
            $("#emessage").html("<i class='fa fa-exclamation mr-1'></i>Error log is unacessible");
          } else if (msg.logok == 2) {
            $("#cmessage").html("Error log is not configured");
          }
          if (msg.sessok) {
            $("#sessionbtn").show();
          }
        }
      }
      // Query the debugger server for new entries and insert on the page as debugcards
      function queryDebugger() {
        if (running) {
          $("#bug").show().fadeOut(500);
          var response = $.ajax({
            url: "debug.php",
            type: "POST",
            data: (JSON.stringify({func:"query"})),
            success: function(msg) { // HTTP ok!
              postErrList(msg);
            }, error: function() {
              $("#emessage").html("<i class='fa fa-exclamation mr-1'></i>Server is offline!");
            }
          });
          if (running) {
            consoleTimer = setTimeout(queryDebugger, <?php echo(get_cfg_var("DEBUG_QUERYINTERVAL") ? get_cfg_var("DEBUG_QUERYINTERVAL") * 1000 : 3000)?>); // Reeschedule the query
          }
        }
      }
      function stop() { // stop querying the HTTP server 
        running = 0;
        clearTimeout(consoleTimer);
      }
      function start() { // start querying the HTTP server
        if (! running) {
          running = 1;
          queryDebugger();
        }
      }
      function freeze() { // stop scrolling down
        scrolling = 0;
      }
      function resume() { // resume scrolling down
        scrolling = 1;
      }
      function requestphpinfo() { // request phpinfo() from the HTTP server
        $.ajax({
          url: "debug.php",
          type: "POST",
          data: (JSON.stringify({func:"phpinfo"}))
        });
      }
      function requestsession() { // request last session recorded
        $.ajax({
          url: "debug.php",
          type: "POST",
          data: (JSON.stringify({func:"getsession"})),
          success: function(msg) {
            postErrList(msg);
          }, error: function() {
            $("#emessage").html("<i class='fa fa-exclamation mr-1'></i>Server is offline!");
          }
        });
      }
      function removesession() { // request session removed
        $.ajax({
          url: "debug.php",
          type: "POST",
          data: (JSON.stringify({func:"delsession"}))
        });
      }
    </script>
    <style type="text/css">
      html {
        border:0;
        padding:0;
        outline:0;
        margin:0;
      }
      body, .btn {
        line-height:1.0;
      }
      .container-fluid {
        padding-right:0;
        padding-left:0;
        margin-right:auto;
        margin-left:auto;
      }
      .row {
        margin: 0;
      }
      #header {
        background:lightgray;
      }
      #header div span {
        font-size:0.8em;
      }
      #startbtn, #resumebtn, #sessionbtn, #bug {
        display:none;
      }
      .debugcard {
        line-height:1.2;
      }
      .debugcard .title {
        background:#f0f0f0;
      }
      .debugcard .title .actions {
        float:right;
      }
      .debugcard .msg {
        font-weight:bold;
      }
      .debugcard .table {
        width:0%;
      }
      .debugcard .session {
        font-size:0.85em;
        font-weight:normal;
        white-space: nowrap;
      }
    </style>
  </head>
  <body class="container-fluid">
    <div id="header" class="p-1 border border-dark rounded-sm sticky-top d-flex flex-row">
      <div class="m-0 p-0 flex-grow-0">
        PHP Remote Debugger<br>
        <span class="m-0 p-0">by <a href="https://github.com/rorabr">rorabr@github</a></span>
      </div>
      <div class="m-0 p-0 pl-3 flex-grow-1 flex-fill">
        <button id="stopbtn" class="btn btn-dark" onclick="$('#stopbtn').hide();$('#startbtn').show();stop()"><i class="fa fa-hand-paper mr-2"></i>Stop</button>
        <button id="startbtn" class="btn btn-dark" onclick="$('#startbtn').hide();$('#stopbtn').show();start()"><i class="fa fa-power-off mr-2"></i>Start</button>
        <button id="freezebtn" class="btn btn-dark" onclick="$('#freezebtn').hide();$('#resumebtn').show();freeze()"><i class="fa fa-icicles mr-2"></i>Freeze</button>
        <button id="resumebtn" class="btn btn-dark" onclick="$('#resumebtn').hide();$('#freezebtn').show();resume()"><i class="fa fa-angle-double-down mr-2"></i>Resume</button>
        <?php
        if (! preg_match('/^PHP.*Development Server$/i', $_SERVER['SERVER_SOFTWARE'])) {
          ?>
          <button id="phpinfobtn" class="btn btn-dark" onclick="requestphpinfo()"><i class="fa fa-info mr-2"></i>PHPinfo</button>
          <?php
        }
        ?>
        <button id="sessionbtn" class="btn btn-dark" onclick="requestsession()"><i class="fa fa-address-card mr-2"></i>Session</button>
        <i id="bug" class="fa fa-bug"></i>
        <span id="emessage" class="text-font-bold ml-3"></span>
      </div>
      <div id="controlcontainer" class="row flex-grow-1 flex-fill justify-content-end">
        <span id="cmessage" class="text-font-bold mr-3"></span>
        <button id="clearbtn" class="btn btn-dark" onclick="$('#debugcontainer').empty()"><i class="fa fa-trash-alt mr-2"></i>Clear</button>
      </div>
    </div>
    <div id="debugcontainer" class="flex-column"></div>
    <script type="text/javascript">
      queryDebugger(); // first query
    </script>
  </body>
  </html>
  <?php
}

/* Log the request, if set */
if (get_cfg_var("DEBUG_LOGREQUEST")) {
  debug_logRequest();
}

/* Main code */
if (preg_match('/\/debug.php$/', $_SERVER['SCRIPT_FILENAME'])) {
  if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == "POST") {
    $post = file_get_contents("php://input");
    if (get_cfg_var("DEBUG_LOGREQUEST")) {
      error_log("    > $post");
    }
    $json = json_decode($post, true); // data from the Ajax call
    header("Content-type: application/json");
    header("Access-Control-Allow-Origin: *");
    if (isset($json["func"])) {
      if ($json["func"] == "query") {
        $response = array("ok" => 1, "msg" => "ok", "errors" => debug_readList(), "logok" => $debug_errorLogOk, "sessok" => $debug_serverHasSession);
      } else if ($json["func"] == "getsession") {
        $response = array("ok" => 1, "msg" => "ok", "errors" => debug_readSession(), "logok" => $debug_errorLogOk, "sessok" => $debug_serverHasSession);
      } else if ($json["func"] == "delsession") {
        debug_removeSession();
        $response = array("ok" => 1, "msg" => "session removed");
      } else if ($json["func"] == "start" || $json["func"] == "phperror" || $json["func"] == "jserror" || $json["func"] == "user" || $json["func"] == "session") {
        $from = $debug_fromError[$json["func"]];
        $json["from"] = $from;
        $json["fa"] = $debug_fontAwesome[$json["func"]];
        if ($json["func"] == "jserror") {
          $json["errmsg"] = preg_replace_callback('/%([0-9A-F]{2})/', function ($m) {return($m[1] == "0A" ? " | " : pack("H*",$m[1]));}, $json["errmsg"]);
        }
        debug_insertRow($json);
        $response = array("ok" => 1, "msg" => $from . " registered");
      } else if ($json["func"] == "phpinfo") {
        $msg = debug_grabPhpInfo();
        debug_insertRow(array("func" => "int", "fa" => $debug_fontAwesome["int"], "time" => date("Y-m-d H:i:s"), "from" => "Internal", "type" => "phpinfo", "errmsg" => $msg, "filename" => __FILE__, "line" => __LINE__));
        $response = array("ok" => 1, "msg" => "phpinfo registered");
      /*} else if ($json["func"] == "session") {
        debug_insertRow(array("func" => "session", "fa" => $debug_fontAwesome["session"], "time" => date("Y-m-d H:i:s"), "from" => "User", "type" => "session", "errmsg" => $msg, "filename" => __FILE__, "line" => __LINE__));
        $response = array("ok" => 1, "msg" => "session registered");*/
      } else {
        $msg = "Unkown function \"" . $json["func"] . "\"";
        debug_internalError($msg);
        $response = array("ok" => 0, "msg" => $msg);
      }
    } else {
      $msg = "Request received without a \"func\"";
      debug_internalError($msg);
      $response = array("ok" => 0, "msg" => $msg);
    }
    print json_encode($response);
  } else {
    debug_buildHTML();
  }
}
# vim: expandtab:ai:ts=2:sw=2
?>

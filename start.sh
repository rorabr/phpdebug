#!/bin/sh

# Simple bash script to run the PHP Debugger and launch a new browser window
# This script is part of PHPdebug.
# https://github.com/rorabr
# Copyright (C) 2019 Rodrigo Antunes - All Rights Reserved
# Permission to copy and modify is granted under the MIT license
# Last revised 2019-05-20

INI=phpdebug.ini
if [ -r "$INI" ]; then # try to use the INI file
  PORT=$(awk -F "=" '/^DEBUG_PORT=/ {print $2}' $INI)
  OPT="--php-ini $INI"
fi
PHP=$(command -v php)
if ! [ -x "$PHP" ]; then # test if php is installed
  echo 'Error: php is not installed' >&2
  exit 1
fi
if [ "$PORT" = "" ]; then # set the default port, if not
  PORT=8080
fi
LAUNCH=$(awk -F "=" '/^DEBUG_LAUNCHBROWSER=/ {print $2}' $INI)
if [ "$LAUNCH" = "1" ]; then # launch the browser
  (sleep 1; xdg-open "http://localhost:$PORT/debug.php")&
else
  echo "Open http://localhost:$PORT/debug.php in your browser to access the PHP Debugger"
fi
echo "The test.php is an example HTML page of how to use the PHP Debugger"
$PHP $OPT -S localhost:$PORT debug.php # start phpdebugger

# UhOh

This is a general Error Handling package.  It will display nice errors with tracebacks and sourcecode previews.

## License

This is released under the MIT License.

## Roadmap

This is an early release, with a lot of planned features:

* Syntax Highlighting on Debug Source
* Nicer looking output
* CLI detection and nice errors on the command line
* Ajax detection and nicer errors for ajax requests
* Add option for verbose/compact view
* Add support for Loggers (currently logs to the PHP error log)

## Installation

Add `dandoescode/uhoh` to your composer.json file.

## Usage

``` php
<?php
$uhoh = new UhOh\UhOh;
$uhoh->registerHandlers();
```

If you are using PHP 5.4 you can shorten it to a one-liner:

``` php
<?php
(new UhOh\UhOh)->registerHandlers();
```

## Changelog

#### 0.0.1

* Initial Release

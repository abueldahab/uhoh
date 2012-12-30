<?php

namespace UhOh;

use Exception;
use ErrorException;
use ReflectionMethod;
use ReflectionFunction;

class UhOh
{
    /**
     * Some nice names for the error types
     */
    protected static $phpErrors = array(
        E_ERROR              => 'Fatal Error',
        E_CORE_ERROR         => 'Fatal Core Error',
        E_USER_ERROR         => 'User Error',
        E_COMPILE_ERROR      => 'Compile Error',
        E_PARSE              => 'Parse Error',
        E_WARNING            => 'Warning',
        E_CORE_WARNING       => 'Core Warning',
        E_USER_WARNING       => 'User Warning',
        E_STRICT             => 'Strict',
        E_NOTICE             => 'Notice',
        E_USER_NOTICE        => 'User Notice',
        E_RECOVERABLE_ERROR  => 'Recoverable Error',
        E_DEPRECATED         => 'Deprecated',
        E_USER_DEPRECATED    => 'User Deprecated',
    );

    /**
     * The Shutdown errors to show (all others will be ignored).
     */
    protected static $shutdownErrors = array(E_PARSE, E_ERROR, E_USER_ERROR, E_CORE_ERROR, E_COMPILE_ERROR);

    public function registerHandlers()
    {
        set_exception_handler(array($this, 'exceptionHandler'));
        set_error_handler(array($this, 'errorHandler'));
        register_shutdown_function(array($this, 'shutdownHandler'));
    }

    public function errorHandler($code, $error, $file = null, $line = null)
    {
        if (error_reporting() & $code) {
            // This error is not suppressed by current error reporting settings
            // Convert the error into an ErrorException
            $this->exceptionHandler(new ErrorException($error, $code, 0, $file, $line));
        }

        // Do not execute the PHP error handler
        return true;
    }

    public function shutdownHandler()
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], self::$shutdownErrors)) {
            $this->cleanOb();

            // Fake an exception for nice debugging
            $this->exceptionHandler(new ErrorException($error['message'], $error['type'], 0, $error['file'], $error['line']));

            // Shutdown now to avoid a "death loop"
            exit(1);
        }
    }

    public function exceptionHandler(Exception $e)
    {
        try {
            // Get the exception information
            $type    = get_class($e);
            $code    = $e->getCode();
            $message = $e->getMessage();
            $file    = $e->getFile();
            $line    = $e->getLine();

            // Create a text version of the exception
            $errorText = $this->exceptionText($e);

            // Log the error message
            error_log($errorText);

            // Get the exception backtrace
            $trace = $e->getTrace();

            if ($e instanceof ErrorException && isset(self::$phpErrors[$code])) {
                // Use the human-readable error name
                $code = self::$phpErrors[$code];
            }

            // Start an output buffer
            ob_start();

            // This will include the custom error file.
            require __DIR__.'/../../views/php_error.php';

            // Display the contents of the output buffer
            echo ob_get_clean();

            return true;
        } catch (Exception $e) {
            $this->cleanOb();

            echo self::exceptionText($e), "\n";

            // Exit with an error status
            exit(1);
        }
    }

    public function debugSource($file, $lineNumber, $padding = 5)
    {
        if ( ! $file || ! is_readable($file)) {
            // Continuing will cause errors
            return null;
        }

        // Open the file and set the line position
        $file = fopen($file, 'r');
        $line = 0;

        // Set the reading range
        $range = array(
            'start' => $lineNumber - $padding,
            'end' => $lineNumber + $padding
        );

        // Set the zero-padding amount for line numbers
        $format = '% '.strlen($range['end']).'d';

        $source = '';
        while (($row = fgets($file)) !== FALSE) {
            // Increment the line number
            if (++$line > $range['end']) {
                break;
            }

            if ($line >= $range['start']) {
                // Make the row safe for output
                $row = htmlspecialchars($row, ENT_NOQUOTES);

                // Trim whitespace and sanitize the row
                $row = '<span class="number">'.sprintf($format, $line).'</span> '.$row;

                if ($line === $lineNumber) {
                    // Apply highlighting to this row
                    $row = '<span class="line highlight">'.$row.'</span>';
                } else {
                    $row = '<span class="line">'.$row.'</span>';
                }

                // Add to the captured source
                $source .= $row;
            }
        }

        // Close the file
        fclose($file);

        return $source;
    }

    public function parseTrace(array $trace = null)
    {
        if (is_null($trace)) {
            // Start a new trace
            $trace = debug_backtrace();
        }

        // Non-standard function calls
        $statements = array('include', 'include_once', 'require', 'require_once');

        $output = array();
        foreach ($trace as $step) {
            if ( ! isset($step['function'])) {
                // Invalid trace step
                continue;
            }

            if (isset($step['file']) && isset($step['line'])) {
                // Include the source of this step
                $source = $this->debugSource($step['file'], $step['line']);
            }

            if (isset($step['file'])) {
                $file = $step['file'];

                if (isset($step['line'])) {
                    $line = $step['line'];
                }
            }

            // function()
            $function = $step['function'];

            if (in_array($step['function'], $statements)) {
                if (empty($step['args'])) {
                    // No arguments
                    $args = array();
                } else {
                    $args = array($step['args'][0]);
                }
            }
            elseif (isset($step['args']))
            {
                if (strpos($step['function'], '{closure}') !== false) {
                    // Introspection on closures in a stack trace is impossible
                    $params = null;
                } else {
                    if (isset($step['class'])) {
                        if (method_exists($step['class'], $step['function'])) {
                            $reflection = new ReflectionMethod($step['class'], $step['function']);
                        } else {
                            $reflection = new ReflectionMethod($step['class'], '__call');
                        }
                    } else {
                        $reflection = new ReflectionFunction($step['function']);
                    }

                    // Get the function parameters
                    $params = $reflection->getParameters();
                }

                $args = array();

                foreach ($step['args'] as $i => $arg) {
                    if (isset($params[$i])) {
                        // Assign the argument by the parameter name
                        $args[$params[$i]->name] = $arg;
                    } else {
                        // Assign the argument by number
                        $args[$i] = $arg;
                    }
                }
            }

            if (isset($step['class'])) {
                // Class->method() or Class::method()
                $function = $step['class'].$step['type'].$step['function'];
            }

            $output[] = array(
                'function' => $function,
                'args'     => isset($args)   ? $args : NULL,
                'file'     => isset($file)   ? $file : NULL,
                'line'     => isset($line)   ? $line : NULL,
                'source'   => isset($source) ? $source : NULL,
            );

            unset($function, $args, $file, $line, $source);
        }

        return $output;
    }

    protected function exceptionText(Exception $e)
    {
        return sprintf('%s [ %s ]: %s ~ %s [ %d ]',
            get_class($e), $e->getCode(), strip_tags($e->getMessage()), $e->getFile(), $e->getLine());
    }

    protected function cleanOb()
    {
        while(ob_get_level()) {
            ob_end_clean();
        }
    }
}

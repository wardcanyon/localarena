<?php

define('AT_int', 1); // integer
define('AT_posint', 2); // positive integer
define('AT_float', 3); // float
define('AT_bool', 4); // 1/0/true/false
define('AT_enum', 5); // an enumeration; `argTypeDetails` lists the possible values as an array
define('AT_alphanum', 6); // a string with 0-9a-zA-Z_ and space
define('AT_alphanum_dash', 7); // a string with 0-9a-zA-Z_- and space
define('AT_numberlist', 8); // a list of numbers separated with "," or ";" (example: 1,4;2,3;-1,2)
define('AT_base64', 9); // a base64-encoded string (SECURITY WARNING*)
define('AT_json', 10); // a JSON stringified string (SECURITY WARNING**)

/**
 * Class APP_GameAction
 * @property array viewArgs
 * @property Table game
 * @property string view
 */
class APP_GameAction
{
    // XXX: Adding these to get rid of "creation of dynamic property"
    // warnings; need to type and document.
    public $params;
    public $game;

    /**
     * @param string $arg
     * @return bool
     */
    function isArg($arg)
    {
        return isset($this->params[$arg]);
    }

    /**
     * @param string $message
     */
    function trace($message)
    {
    }

    /**
     * @param string $arg
     * @param string $type
     * @param bool $required
     *
     * TODO: This is a great candidate for unit testing!  I'm also not
     * positive that the behavior matches BGA exactly (e.g. the return
     * types may not be identical, and so on).
     *
     * @return mixed
     */
    function getArg($arg, $type, $required = false, $unknownParam = null, $enumValues = [])
    {
        if (isset($this->params[$arg])) {
            $rawVal = $this->params[$arg];

            switch ($type) {
            case AT_int: {
                if (!preg_match('/^-?\d+$/', $rawVal)) {
                    throw new feException('Invalid value for argument of type AT_int: ' . $rawVal);
                }
                return intval($rawVal);
            }
            case AT_posint: {
                if (!preg_match('/^\d+$/', $rawVal)) {
                    throw new feException('Invalid value for argument of type AT_posint: ' . $rawVal);
                }
                return intval($rawVal);
            }
            case AT_float: {
                if (!preg_match('/^-?\d+(\.\d+)?$/', $rawVal)) {
                    throw new feException('Invalid value for argument of type AT_float: ' . $rawVal);
                }
                return floatval($rawVal);
            }
            case AT_bool: {
                switch ($rawVal) {
                case '0':
                case 'false':
                    return false;
                case '1':
                case 'true':
                    return true;
                default:
                    throw new feException('Invalid value for argument of type AT_bool: ' . $rawVal);
                }
            }
            case AT_enum: {
                if (!in_array($rawVal, $enumValues)) {
                    throw new feException('Invalid value for argument of type AT_enum: ' . $rawVal . ' (possible values: ' . implode(', ', $enumValues) . ')');
                }
                return $rawVal;
            }
            case AT_alphanum: {
                if (!preg_match('/^[0-9a-zA-Z ]+$/', $rawVal)) {
                    throw new feException('Invalid value for argument of type AT_alphanum: ' . $rawVal);
                }
                return $rawVal;
            }
            case AT_alphanum_dash: {
                if (!preg_match('/^[-0-9a-zA-Z ]+$/', $rawVal)) {
                    throw new feException('Invalid value for argument of type AT_alphanum_dash: ' . $rawVal);
                }
                return $rawVal;
            }
            case AT_numberlist: {
                if (!preg_match('/^-?\d+([;,]-?\d+)*$/', $rawVal)) {
                    throw new feException('Invalid value for argument of type AT_numberlist: ' . $rawVal);
                }
                // N.B.: This doesn't actually _parse_ the numberlist;
                // it returns a string.
                return $rawVal;
            }
            case AT_base64: {
                return base64_decode($rawVal, /*strict=*/true);
            }
            case AT_json: {
                return json_decode($rawVal, /*associative=*/true);
            }
            }
        } else {
            if ($required) {
                throw new feException("Required parameter " . $arg . " not found.");
            }
        }
        throw new feException('Unsupported arg type: ' . $type);
    }

    /**
     *
     */
    function setAjaxMode()
    {
    }

    /**
     *
     */
    function ajaxResponse()
    {
    }
}

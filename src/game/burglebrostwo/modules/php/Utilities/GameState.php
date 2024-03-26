<?php

namespace BurgleBrosTwo\Utilities;

/*
XXX: Here's how I manually tested this after first writing it; turn
these into unit tests once we have that framework set up.

        self::setGameStateInt("foo", 42);
        $foo = self::getGameStateInt("foo");
        if ($foo != 42) {  // XXX: This works even though $foo was initially a string!  Should test for that.
            throw new \feException("int set-get did not work: ".$foo);
        }
        self::setGameStateJson("bar", [10, 20, 30]);
        $bar = self::getGameStateJson("bar");
        throw new \feException("bar is: ".print_r($bar, true));
*/

trait GameState
{
    function getGameStateInt(string $gamestate_key): int
    {
        $record = self::getObjectFromDB(
            'SELECT * FROM `gamestate` WHERE gamestate_key = "' .
                $gamestate_key .
                '"'
        );
        if (is_null($record)) {
            throw new \feException(
                "getGameStateInt(): no value for key: " . $gamestate_key
            );
        }
        if (is_null($record["gamestate_value_int"])) {
            throw new \feException(
                "getGameStateInt(): key appears to have a value of a different type: " .
                    $gamestate_key
            );
        }
        // N.B.: The `intval()` call is necessary because the BGA
        // framework code returns this value as a string.
        return intval($record["gamestate_value_int"]);
    }

    function setGameStateInt(string $gamestate_key, int $value): void
    {
        if (!is_int($value)) {
            throw new \feException(
                "setGameStateInt(): value is not an int: value=" .
                    $value .
                    ", which is a " .
                    get_debug_type($value)
            );
        }
        self::DbQuery(
            "REPLACE INTO `gamestate` (gamestate_key, gamestate_value_int, gamestate_value_json) VALUES " .
                '("' .
                $gamestate_key .
                '", ' .
                $value .
                ", NULL)"
        );
    }

    // Returns a (potentially nested) array or associative array.
    function getGameStateJson(string $gamestate_key)
    {
        $record = self::getObjectFromDB(
            'SELECT * FROM `gamestate` WHERE gamestate_key = "' .
                $gamestate_key .
                '"'
        );
        if (is_null($record)) {
            throw new \feException(
                "getGameStateJson(): no value for key: " . $gamestate_key
            );
        }
        if (is_null($record["gamestate_value_json"])) {
            throw new \feException(
                "getGameStateJson(): key appears to have a value of a different type: " .
                    $gamestate_key
            );
        }

        // N.B.: The boolean parameter here makes this return an
        // associative array rather than an object.
        return json_decode(
            $record["gamestate_value_json"],
            /*associative=*/ true
        );
    }

    // $value must be a (potentially nested) array or associative
    // array.
    function setGameStateJson(string $gamestate_key, $value): void
    {
        $encoded_value = json_encode($value);
        self::DbQuery(
            "REPLACE INTO `gamestate` (gamestate_key, gamestate_value_int, gamestate_value_json) VALUES " .
                '("' .
                $gamestate_key .
                '", NULL, "' .
                self::escapeStringForDB($encoded_value) .
                '")'
        );
    }
}

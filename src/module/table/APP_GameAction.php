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
  /**
   * Request-parameter names that belong to the framework, and why.
   *
   * A game action's arguments share one namespace with the request
   * machinery that carries them, and the machinery wins.  Name an
   * argument after one of these and BGA overwrites it, or reads it as
   * its own, before the game ever sees it -- so the game gets a
   * framework error, or somebody else's value, instead of what the
   * client sent.  Nothing on BGA says so out loud; the symptom is a
   * red error box on one action, usually the least-travelled one.
   *
   * LocalArena would not otherwise reproduce that, because it keeps
   * its own dispatch fields out of the way in a `bgg_` namespace (see
   * `Table::doAction()`) and so hands a game whatever names it asked
   * for.  A game developed against LocalArena can therefore pass its
   * whole suite and still fail on BGA.  Hence this list: the names are
   * refused here, loudly, at the two places a game can touch one.
   *
   * `lock` is deliberately NOT here.  It is the client wrapper's --
   * `ajaxcall()` reads it off the parameters (see
   * `src/ebg/core/gamegui.ts`) -- but games pass it on every call, and
   * LocalArena forwards the whole parameter object to the server, so
   * refusing it would fail every existing game rather than a mistake.
   * It is a name to keep away from all the same.
   *
   * To add a name: put it here with the reason, and say in
   * `READABLE_RESERVED_ARG_NAMES` whether a game may still read the
   * framework's own value under it.
   */
  const RESERVED_ARG_NAMES = [
    'action' =>
      "BGA's front controller routes the request on `action`: it is what says which " .
      'entry point the request is for.',
    'table' =>
      'BGA puts the table id in `table` on every request; the stock `__default()` in ' .
      'every game\'s action file reads it back out.',
    'notifwindow' => 'A framework view parameter; the stock `__default()` branches on it.',
    'bgg_actionName' =>
      "LocalArena's own dispatch field: it names the act*() method to run. " .
      'BGA has no such parameter, so a game reading one works here and breaks there.',
    'bgg_player_id' =>
      "LocalArena's own dispatch field: it says who is acting. BGA has no such " .
      'parameter, so a game reading one works here and breaks there.',
  ];

  /**
   * The reserved names a game may still READ.
   *
   * The framework supplies these, and reading one gets the
   * framework's value, which is the whole point of it being there --
   * `$this->viewArgs['table'] = self::getArg('table', AT_posint,
   * true)` is BGA's own boilerplate.  What a game may not do is send
   * its own value under the name, which is what
   * `assertNoReservedArgNames()` refuses.
   */
  const READABLE_RESERVED_ARG_NAMES = ['table', 'notifwindow'];

  // XXX: Adding these to get rid of "creation of dynamic property"
  // warnings; need to type and document.
  public $params;
  public $game;

  /**
   * Why $name belongs to the framework, or null if it does not.
   *
   * @param string $name
   * @return string|null
   */
  public static function reservedArgReason($name)
  {
    return self::RESERVED_ARG_NAMES[$name] ?? null;
  }

  /**
   * Refuses a game's attempt to READ a reserved argument name.
   *
   * Called by `getArg()` and `isArg()`, which is every way a game
   * reaches its request parameters.  A game that names an argument
   * after a framework one has to read it back, so this is where the
   * mistake surfaces even when the request itself never got here.
   *
   * @param string $name
   */
  public static function assertArgNameNotReserved($name)
  {
    $reason = self::reservedArgReason($name);
    if ($reason === null || in_array($name, self::READABLE_RESERVED_ARG_NAMES, true)) {
      return;
    }
    throw new feException(
      'Request argument name "' .
        $name .
        '" is reserved by the framework, so a game may not read it: ' .
        $reason .
        '  Rename the argument.'
    );
  }

  /**
   * Refuses a request that carries a game argument named after a
   * framework one.
   *
   * The other half of the same rule, and the half that names the
   * mistake where it is made: this sees the arguments a caller chose,
   * before any dispatch field is merged in, so `table` and
   * `notifwindow` are refused here even though reading the
   * framework's own is fine.
   *
   * @param array $args The game's own arguments, without the dispatch fields.
   */
  public static function assertNoReservedArgNames($args)
  {
    foreach (array_keys($args) as $name) {
      $reason = self::reservedArgReason($name);
      if ($reason !== null) {
        throw new feException(
          'Request argument name "' .
            $name .
            '" is reserved by the framework, so a game may not send one: ' .
            $reason .
            '  Rename the argument.'
        );
      }
    }
  }

  /**
   * @param string $arg
   * @return bool
   */
  function isArg($arg)
  {
    self::assertArgNameNotReserved($arg);
    return isset($this->params[$arg]);
  }

  /**
   * @param string $message
   */
  function trace($message)
  {
  }

  /**
   * Reads, validates, and converts one request argument.
   *
   * An argument that is absent is an error only if $required; otherwise
   * $default is returned unconverted (it is a value the caller chose,
   * not request input, so there is nothing to validate).  BGA's
   * signature names this parameter $default -- see `_ide_helper.php`,
   * `getArg(string $argName, int $argType, bool $bMandatory = false,
   * mixed $default = null, array $argTypeDetails = [], ...)` -- so we
   * match that name, and games passing it by name still work.
   *
   * N.B.: BGA's trailing $bCanFail parameter is not implemented here.
   *
   * @param string $arg
   * @param int $type
   * @param bool $required
   * @param mixed $default
   * @param array $enumValues Possible values for AT_enum (BGA calls this $argTypeDetails).
   *
   * @return mixed
   */
  function getArg($arg, $type, $required = false, $default = null, $enumValues = [])
  {
    self::assertArgNameNotReserved($arg);

    if (isset($this->params[$arg])) {
      $rawVal = $this->params[$arg];

      switch ($type) {
        case AT_int:
          if (!preg_match('/^-?\d+$/', $rawVal)) {
            throw new feException('Invalid value for argument of type AT_int: ' . $rawVal);
          }
          return intval($rawVal);
        case AT_posint:
          if (!preg_match('/^\d+$/', $rawVal)) {
            throw new feException('Invalid value for argument of type AT_posint: ' . $rawVal);
          }
          return intval($rawVal);
        case AT_float:
          if (!preg_match('/^-?\d+(\.\d+)?$/', $rawVal)) {
            throw new feException('Invalid value for argument of type AT_float: ' . $rawVal);
          }
          return floatval($rawVal);
        case AT_bool:
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
        case AT_enum:
          if (!in_array($rawVal, $enumValues)) {
            throw new feException(
              'Invalid value for argument of type AT_enum: ' .
                $rawVal .
                ' (possible values: ' .
                implode(', ', $enumValues) .
                ')'
            );
          }
          return $rawVal;
        case AT_alphanum:
          if (!preg_match('/^[0-9a-zA-Z ]+$/', $rawVal)) {
            throw new feException('Invalid value for argument of type AT_alphanum: ' . $rawVal);
          }
          return $rawVal;
        case AT_alphanum_dash:
          if (!preg_match('/^[-0-9a-zA-Z ]+$/', $rawVal)) {
            throw new feException('Invalid value for argument of type AT_alphanum_dash: ' . $rawVal);
          }
          return $rawVal;
        case AT_numberlist:
          if (!preg_match('/^-?\d+([;,]-?\d+)*$/', $rawVal)) {
            throw new feException('Invalid value for argument of type AT_numberlist: ' . $rawVal);
          }
          // N.B.: This doesn't actually _parse_ the numberlist;
          // it returns a string.
          return $rawVal;
        case AT_base64:
          return base64_decode($rawVal, /*strict=*/ true);
        case AT_json:
          return json_decode($rawVal, /*associative=*/ true);
      }

      // The argument was supplied, but we were asked to convert it as
      // a type we don't recognize.
      throw new feException('Unsupported arg type: ' . $type);
    }

    if ($required) {
      throw new feException('Required parameter ' . $arg . ' not found.');
    }

    return $default;
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
